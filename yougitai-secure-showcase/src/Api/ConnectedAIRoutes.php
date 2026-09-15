<?php
namespace YougitAI\SecureShowcase\Api;

use YougitAI\SecureShowcase\Audit\Logger;
use YougitAI\SecureShowcase\GitHub\Client;
use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Security\ConnectionTokenService;
use YougitAI\SecureShowcase\Security\RateLimiter;
use YougitAI\SecureShowcase\Security\OAuthServer;
use YougitAI\SecureShowcase\Security\SecretScanner;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ConnectedAIRoutes {
    private ConnectionTokenService $tokens;
    private Client $github;
    private SecretScanner $scanner;
    private Logger $audit;

    public function __construct( private RepositoryService $repositories ) {
        $this->tokens = new ConnectionTokenService();
        $this->github = new Client();
        $this->scanner = new SecretScanner();
        $this->audit = new Logger();
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
        add_filter( 'rest_pre_serve_request', [ $this, 'force_oauth_challenge_headers' ], 5, 4 );
    }

    /**
     * Emit the OAuth challenge at the HTTP layer as well as on the WP_REST_Response.
     * Some WordPress/server stacks may otherwise lose WWW-Authenticate while serving
     * REST errors, which prevents MCP clients such as ChatGPT from discovering OAuth.
     */
    public function force_oauth_challenge_headers( $served, $result, WP_REST_Request $request, $server ) {
        if ( $request->get_route() !== '/yougitai-secure-showcase/v1/connected-ai/mcp' ) {
            return $served;
        }

        $status = is_object( $result ) && method_exists( $result, 'get_status' ) ? (int) $result->get_status() : 0;
        if ( $status !== 401 ) {
            return $served;
        }

        $scopes = implode( ' ', array_filter( OAuthServer::supported_scopes(), static fn( string $scope ): bool => $scope !== 'offline_access' ) );
        $challenge = 'Bearer error="invalid_token", error_description="Authorization required", resource_metadata="' . esc_url_raw( OAuthServer::resource_metadata_url() ) . '"';
        if ( $scopes !== '' ) {
            $challenge .= ', scope="' . esc_attr( $scopes ) . '"';
        }

        if ( ! headers_sent() ) {
            header( 'WWW-Authenticate: ' . $challenge, true );
            header( 'Cache-Control: no-store, private, max-age=0', true );
            header( 'Pragma: no-cache', true );
            header( 'Vary: Authorization', false );
            header( 'X-Content-Type-Options: nosniff', true );
        }

        return $served;
    }

    public function routes(): void {
        register_rest_route( 'yougitai-secure-showcase/v1', '/connected-ai/mcp', [
            'methods' => [ 'GET', 'POST' ],
            'callback' => [ $this, 'mcp' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'yougitai-secure-showcase/v1', '/connected-ai/status', [
            'methods' => 'GET',
            'callback' => static fn(): WP_REST_Response => new WP_REST_Response( [
                'name' => 'YougitAI Secure Showcase',
                'protocol' => 'mcp-jsonrpc',
                'version' => YOUGITAI_SS_VERSION,
                'authentication' => 'oauth',
                'authorization_server' => OAuthServer::issuer(),
                'protected_resource_metadata' => OAuthServer::resource_metadata_url(),
                'publish_tool_exposed' => false,
            ], 200 ),
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'yougitai-secure-showcase/v1', '/connected-ai/tool-schema', [
            'methods' => 'GET',
            'callback' => function (): WP_REST_Response {
                $tools = $this->tool_definitions();
                $tree = null;
                foreach ( $tools as $tool ) {
                    if ( ( $tool['name'] ?? '' ) === 'get_repository_tree' ) {
                        $tree = $tool;
                        break;
                    }
                }
                return new WP_REST_Response( [
                    'version' => YOUGITAI_SS_VERSION,
                    'tool' => $tree,
                    'parameter_names' => array_keys( (array) ( $tree['inputSchema']['properties'] ?? [] ) ),
                    'required' => array_values( (array) ( $tree['inputSchema']['required'] ?? [] ) ),
                ], 200 );
            },
            'permission_callback' => '__return_true',
        ] );
    }

    public function mcp( WP_REST_Request $request ): WP_REST_Response {
        $auth = (string) $request->get_header( 'authorization' );
        $token = preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ? trim( $m[1] ) : '';
        $connection = $this->tokens->authenticate( $token );
        if ( ! $connection ) {
            return $this->oauth_required_response();
        }

        if ( strtoupper( $request->get_method() ) === 'GET' ) {
            $response = new WP_REST_Response( null, 405 );
            $response->header( 'Allow', 'POST' );
            return $this->secure_response( $response );
        }
        $limit = (int) apply_filters( 'yougitai_ss_connected_ai_requests_per_minute', 120 );
        if ( ! RateLimiter::allow( 'connected-ai:' . (int) $connection['id'], $limit, 60 ) ) {
            return $this->rpc_error( null, -32029, __( 'Too many connected-AI requests. Please try again shortly.', 'yougitai-secure-showcase' ), 429 );
        }

        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return $this->rpc_error( null, -32700, __( 'Invalid JSON request.', 'yougitai-secure-showcase' ), 400 );
        }
        $id = $body['id'] ?? null;
        $method = (string) ( $body['method'] ?? '' );
        $params = is_array( $body['params'] ?? null ) ? $body['params'] : [];

        if ( $method === 'initialize' ) {
            return $this->rpc_result( $id, [
                'protocolVersion' => '2025-06-18',
                'serverInfo' => [ 'name' => 'yougitai-secure-showcase', 'version' => YOUGITAI_SS_VERSION ],
                'capabilities' => [ 'tools' => [ 'listChanged' => true ] ],
            ] );
        }
        if ( $method === 'notifications/initialized' ) {
            return $this->secure_response( new WP_REST_Response( null, 202 ) );
        }
        if ( $method === 'ping' ) {
            return $this->rpc_result( $id, (object) [] );
        }
        if ( $method === 'tools/list' ) {
            return $this->rpc_result( $id, [ 'tools' => $this->tool_definitions() ] );
        }
        if ( $method !== 'tools/call' ) {
            return $this->rpc_error( $id, -32601, __( 'MCP method not supported.', 'yougitai-secure-showcase' ) );
        }

        $name = sanitize_key( (string) ( $params['name'] ?? '' ) );
        $arguments = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : [];
        $result = $this->call_tool( $name, $arguments, $connection );
        if ( is_wp_error( $result ) ) {
            return $this->rpc_result( $id, [
                'isError' => true,
                'content' => [ [ 'type' => 'text', 'text' => $result->get_error_message() ] ],
            ] );
        }
        return $this->rpc_result( $id, [
            'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ] ],
            'structuredContent' => $result,
        ] );
    }

    private function tool_definitions(): array {
        return [
            $this->tool( 'get_connection_info', __( 'Describe the current connected-AI session, permissions, and repository restrictions.', 'yougitai-secure-showcase' ), [] ),
            $this->tool( 'list_repositories', __( 'List repositories available to this connected-AI session. Returns metadata only.', 'yougitai-secure-showcase' ), [] ),
            $this->tool( 'get_review_context', __( 'Get repository review context including protection profile, snapshots, rules, and unresolved finding counts without source code.', 'yougitai-secure-showcase' ), [ 'repository_id' => [ 'type' => 'integer' ] ], [ 'repository_id' ] ),
            $this->tool( 'get_portfolio_policy', __( 'Get the portfolio-safety classification policy used to balance skill visibility against IP exposure. Read this before reviewing source.', 'yougitai-secure-showcase' ), [] ),
            $this->tool( 'get_repository_tree', __( 'Read a paginated repository tree without file contents. Start without cursor (or with cursor 0), then pass next_cursor from the previous response until has_more is false. Optionally restrict the scan with prefix.', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer', 'description' => 'Repository ID returned by list_repositories.' ],
                'cursor' => [ 'type' => 'integer', 'minimum' => 0, 'description' => 'Optional zero-based pagination cursor. Omit for the first page; then pass the previous response next_cursor.' ],
                'prefix' => [ 'type' => 'string', 'description' => 'Optional path prefix such as backend/, lib/, or test/. Omit for the entire repository.' ],
                'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'description' => 'Optional page size. Defaults to 250 and is capped at 500.' ],
            ], [ 'repository_id' ] ),
            $this->tool( 'get_repository_tree_page', __( 'Compatibility alias for paginated repository tree access. ALWAYS pass cursor (0 first) and prefix (empty string for the whole repository).', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer', 'description' => 'Repository ID returned by list_repositories.' ],
                'cursor' => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'Zero-based pagination cursor. Pass the previous response next_cursor to continue.' ],
                'prefix' => [ 'type' => 'string', 'default' => '', 'description' => 'Path prefix such as lib/ or test/. Pass an empty string for the whole repository.' ],
                'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 250, 'description' => 'Maximum number of tree entries to return in this page.' ],
            ], [ 'repository_id', 'cursor', 'prefix' ] ),
            $this->tool( 'get_repository_tree_v2', __( 'Paginated repository tree access with explicit cursor and prefix arguments. Use cursor 0 and prefix empty string for the first page, then continue with next_cursor until has_more is false.', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer', 'description' => 'Repository ID returned by list_repositories.' ],
                'cursor' => [ 'type' => 'integer', 'minimum' => 0, 'description' => 'Zero-based pagination cursor. Use 0 for the first page and then pass the prior next_cursor.' ],
                'prefix' => [ 'type' => 'string', 'description' => 'Path prefix filter. Use an empty string for the entire repository.' ],
                'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 250, 'description' => 'Maximum number of entries to return in this page.' ],
            ], [ 'repository_id', 'cursor', 'prefix' ] ),
            $this->tool( 'get_repository_tree_paginated', __( 'Repository-wide paginated tree access. Use this tool for complete scans: start with cursor 0, then repeat with next_cursor until has_more is false. Prefix may be empty or restrict the scan to a subdirectory.', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer', 'description' => 'Repository ID returned by list_repositories.' ],
                'cursor' => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'Zero-based pagination cursor. Omit or use 0 for the first page; then pass next_cursor from the previous response.' ],
                'prefix' => [ 'type' => 'string', 'default' => '', 'description' => 'Optional path prefix such as backend/ or lib/. Omit or pass an empty string for the entire repository.' ],
                'limit' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 250, 'description' => 'Maximum number of tree entries in this page.' ],
            ], [ 'repository_id' ] ),
            $this->tool( 'get_file_for_review', __( 'Read one text file from the explicitly authorized repository for security review. This is read-only, repository-scoped, and detected secrets are masked before the content leaves WordPress.', 'yougitai-secure-showcase' ), [ 'repository_id' => [ 'type' => 'integer' ], 'path' => [ 'type' => 'string' ] ], [ 'repository_id', 'path' ] ),
            $this->tool( 'get_file_excerpt_for_review', __( 'Read a bounded excerpt from one text file in the explicitly authorized repository. This is read-only and masks detected secrets before returning content.', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer' ],
                'path' => [ 'type' => 'string' ],
                'start_line' => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
                'line_count' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 120 ],
            ], [ 'repository_id', 'path' ] ),
            $this->tool( 'get_files_for_review', __( 'Fetch up to ten source files in one review call. Secrets are masked before content leaves WordPress.', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer' ],
                'paths' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'minItems' => 1, 'maxItems' => 10 ],
            ], [ 'repository_id', 'paths' ] ),
            $this->tool( 'get_security_findings', __( 'Read security findings for a repository snapshot.', 'yougitai-secure-showcase' ), [ 'repository_id' => [ 'type' => 'integer' ], 'snapshot_id' => [ 'type' => 'integer' ] ], [ 'repository_id', 'snapshot_id' ] ),
            $this->tool( 'get_redaction_proposals', __( 'Read existing connected-AI redaction proposals and their approval status.', 'yougitai-secure-showcase' ), [ 'repository_id' => [ 'type' => 'integer' ] ], [ 'repository_id' ] ),
            $this->tool( 'create_redaction_proposal', __( 'Create a disabled redaction proposal for human review. This does not publish or expose source.', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer' ],
                'file_path' => [ 'type' => 'string' ],
                'rule_type' => [ 'type' => 'string', 'enum' => [ 'path', 'string', 'regex' ] ],
                'target' => [ 'type' => 'string' ],
                'action' => [ 'type' => 'string', 'enum' => [ 'hide', 'redact' ] ],
                'replacement' => [ 'type' => 'string' ],
                'reason' => [ 'type' => 'string' ],
                'classification' => [ 'type' => 'string', 'enum' => [ 'secret', 'private_endpoint', 'ai_prompt', 'security_logic', 'business_logic', 'core_ip', 'internal_schema', 'portfolio_safe' ] ],
                'decision' => [ 'type' => 'string', 'enum' => [ 'abstract', 'redact', 'hide' ] ],
            ], [ 'repository_id', 'rule_type', 'target', 'action', 'reason' ] ),
            $this->tool( 'create_redaction_proposals', __( 'Create up to fifty disabled redaction proposals in one call for human review.', 'yougitai-secure-showcase' ), [
                'repository_id' => [ 'type' => 'integer' ],
                'proposals' => [ 'type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [ 'type' => 'string' ],
                        'rule_type' => [ 'type' => 'string', 'enum' => [ 'path', 'string', 'regex' ] ],
                        'target' => [ 'type' => 'string' ],
                        'action' => [ 'type' => 'string', 'enum' => [ 'hide', 'redact' ] ],
                        'replacement' => [ 'type' => 'string' ],
                        'reason' => [ 'type' => 'string' ],
                        'classification' => [ 'type' => 'string', 'enum' => [ 'secret', 'private_endpoint', 'ai_prompt', 'security_logic', 'business_logic', 'core_ip', 'internal_schema', 'portfolio_safe' ] ],
                        'decision' => [ 'type' => 'string', 'enum' => [ 'abstract', 'redact', 'hide' ] ],
                    ],
                    'required' => [ 'rule_type', 'target', 'action', 'reason' ],
                    'additionalProperties' => false,
                ] ],
            ], [ 'repository_id', 'proposals' ] ),
            $this->tool( 'build_draft_snapshot', __( 'Create a new secure draft snapshot. It never publishes automatically.', 'yougitai-secure-showcase' ), [ 'repository_id' => [ 'type' => 'integer' ] ], [ 'repository_id' ] ),
        ];
    }

    private function tool( string $name, string $description, array $properties, array $required = [] ): array {
        $is_write = str_starts_with( $name, 'create_' ) || str_starts_with( $name, 'build_' );
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) $properties, 'required' => $required, 'additionalProperties' => false ],
            'outputSchema' => [ 'type' => 'object', 'additionalProperties' => true ],
            'annotations' => [
                'readOnlyHint' => ! $is_write,
                'destructiveHint' => false,
                'idempotentHint' => ! $is_write,
                'openWorldHint' => false,
            ],
        ];
    }

    private function call_tool( string $name, array $args, array $connection ) {
        $scope_map = [
            'get_connection_info' => 'repositories:read',
            'list_repositories' => 'repositories:read',
            'get_review_context' => 'repositories:read',
            'get_portfolio_policy' => 'repositories:read',
            'get_repository_tree' => 'repositories:read',
            'get_repository_tree_page' => 'repositories:read',
            'get_repository_tree_v2' => 'repositories:read',
            'get_repository_tree_paginated' => 'repositories:read',
            'get_file_for_review' => 'source:review',
            'get_file_excerpt_for_review' => 'source:review',
            'get_files_for_review' => 'source:review',
            'get_security_findings' => 'findings:read',
            'get_redaction_proposals' => 'findings:read',
            'create_redaction_proposal' => 'redactions:propose',
            'create_redaction_proposals' => 'redactions:propose',
            'build_draft_snapshot' => 'snapshots:create',
        ];
        if ( ! isset( $scope_map[ $name ] ) || ! in_array( $scope_map[ $name ], $connection['scopes'], true ) ) {
            return new WP_Error( 'yougitai_scope_denied', __( 'This connection is not allowed to use that tool.', 'yougitai-secure-showcase' ) );
        }
        $repository_id = absint( $args['repository_id'] ?? 0 );
        if ( $repository_id && ! $this->repository_allowed( $repository_id, $connection ) ) {
            return new WP_Error( 'yougitai_repository_denied', __( 'This connection is not allowed to access that repository.', 'yougitai-secure-showcase' ) );
        }
        return match ( $name ) {
            'get_connection_info' => $this->connection_info( $connection ),
            'list_repositories' => $this->list_repositories( $connection ),
            'get_review_context' => $this->review_context( $args ),
            'get_portfolio_policy' => $this->portfolio_policy(),
            'get_repository_tree' => $this->get_tree( $args ),
            'get_repository_tree_page' => $this->get_tree_page( $args ),
            'get_repository_tree_v2' => $this->get_tree_page( $args ),
            'get_repository_tree_paginated' => $this->get_tree_page( $args ),
            'get_file_for_review' => $this->get_file( $args, $connection ),
            'get_file_excerpt_for_review' => $this->get_file_excerpt( $args, $connection ),
            'get_files_for_review' => $this->get_files( $args, $connection ),
            'get_security_findings' => $this->get_findings( $args ),
            'get_redaction_proposals' => $this->get_proposals( $args ),
            'create_redaction_proposal' => $this->create_proposal( $args, $connection ),
            'create_redaction_proposals' => $this->create_proposals( $args, $connection ),
            'build_draft_snapshot' => $this->build_snapshot( $args, $connection ),
            default => new WP_Error( 'yougitai_unknown_tool', __( 'Unknown tool.', 'yougitai-secure-showcase' ) ),
        };
    }

    private function connection_info( array $connection ): array {
        return [
            'connection_id' => (int) $connection['id'],
            'label' => (string) $connection['label'],
            'expires_at' => (string) $connection['expires_at'],
            'scopes' => array_values( $connection['scopes'] ?? [] ),
            'repository_ids' => array_values( $connection['repository_ids'] ?? [] ),
            'all_repositories' => empty( $connection['repository_ids'] ),
            'publish_tool_exposed' => false,
            'human_approval_required_for_proposals' => true,
            'request_count' => (int) ( $connection['request_count'] ?? 0 ),
            'source_bytes_used' => (int) ( $connection['source_bytes'] ?? 0 ),
            'source_bytes_limit' => (int) apply_filters( 'yougitai_ss_connected_ai_source_budget', 5242880 ),
        ];
    }

    private function list_repositories( array $connection ): array {
        $rows = array_filter( $this->repositories->all(), fn( array $r ): bool => $this->repository_allowed( (int) $r['id'], $connection ) );
        $repositories = array_values( array_map( static fn( array $r ): array => [
            'id' => (int) $r['id'], 'title' => $r['title'], 'owner' => $r['owner'], 'repository' => $r['repo'],
            'branch' => $r['default_branch'], 'status' => $r['status'], 'protection_profile' => $r['protection_profile'],
            'active_snapshot_id' => (int) ( $r['active_snapshot_id'] ?? 0 ),
        ], $rows ) );
        return [ 'repositories' => $repositories, 'count' => count( $repositories ) ];
    }

    private function portfolio_policy(): array {
        return [
            'goal' => 'Show enough implementation detail to demonstrate engineering skill without exposing a reproducible product blueprint.',
            'classifications' => [
                'secret' => [ 'default' => 'redact', 'description' => 'Credentials, tokens, keys, private hosts, or authentication material.' ],
                'private_endpoint' => [ 'default' => 'abstract', 'description' => 'Non-public API paths, internal hosts, admin routes, or integration endpoints.' ],
                'ai_prompt' => [ 'default' => 'hide', 'description' => 'System prompts, proprietary prompt templates, routing prompts, or evaluation instructions.' ],
                'security_logic' => [ 'default' => 'abstract', 'description' => 'Security controls may remain visible conceptually, but bypass conditions and concrete enforcement internals should not.' ],
                'business_logic' => [ 'default' => 'abstract', 'description' => 'Domain workflows and rules that reveal how the product makes decisions.' ],
                'core_ip' => [ 'default' => 'hide', 'description' => 'Algorithms or implementation details that materially enable reconstruction of the product.' ],
                'internal_schema' => [ 'default' => 'abstract', 'description' => 'Internal data models, tenant/session schemas, privileged fields, and non-public contracts.' ],
                'portfolio_safe' => [ 'default' => 'show', 'description' => 'Architecture, tests, error handling, UI patterns, public interfaces, and implementation details that demonstrate skill without meaningful copying risk.' ],
            ],
            'decisions' => [
                'show' => 'Keep the implementation visible.',
                'abstract' => 'Keep structure and intent visible while replacing implementation-specific values or blocks.',
                'redact' => 'Mask the sensitive value or narrow code region.',
                'hide' => 'Exclude the file or core implementation from the public snapshot.',
            ],
            'review_order' => [ 'metadata', 'repository_tree', 'risk_candidates', 'targeted_source', 'classify', 'propose_redactions', 'build_draft' ],
            'constraints' => [ 'never_publish' => true, 'source_minimization' => true, 'human_approval_for_proposals' => true ],
        ];
    }

    private function repository_allowed( int $repository_id, array $connection ): bool {
        $allowed = array_values( array_filter( array_map( 'absint', $connection['repository_ids'] ?? [] ) ) );
        return empty( $allowed ) || in_array( $repository_id, $allowed, true );
    }

    private function review_context( array $args ) {
        $repository_id = absint( $args['repository_id'] ?? 0 );
        $repository = $this->repositories->find( $repository_id );
        if ( ! $repository ) return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        $snapshots = array_slice( $this->repositories->snapshots( $repository_id ), 0, 10 );
        $rules = array_map( static fn( array $rule ): array => [
            'id' => (int) $rule['id'],
            'file_path' => (string) $rule['file_path'],
            'rule_type' => (string) $rule['rule_type'],
            'action' => (string) $rule['action'],
            'source' => (string) $rule['source'],
            'enabled' => (bool) $rule['enabled'],
            'notes' => (string) ( $rule['notes'] ?? '' ),
        ], $this->repositories->rules( $repository_id ) );
        return [
            'repository' => [
                'id' => (int) $repository['id'], 'title' => $repository['title'], 'owner' => $repository['owner'],
                'repository' => $repository['repo'], 'branch' => $repository['default_branch'],
                'protection_profile' => $repository['protection_profile'], 'is_private' => (bool) $repository['is_private'],
            ],
            'recent_snapshots' => array_map( static fn( array $snapshot ): array => [
                'id' => (int) $snapshot['id'], 'source_sha' => (string) $snapshot['source_sha'], 'status' => $snapshot['status'],
                'review_status' => $snapshot['review_status'], 'files' => (int) $snapshot['file_count'],
                'findings' => (int) $snapshot['finding_count'], 'critical' => (int) $snapshot['critical_count'],
                'created_at' => $snapshot['created_at'],
            ], $snapshots ),
            'redaction_rules' => $rules,
            'workflow' => [
                'source_is_ephemeral' => true,
                'proposals_require_human_approval' => true,
                'publish_tool_exposed' => false,
            ],
        ];
    }

    private function get_tree( array $args ) {
        $repository = $this->repositories->find( absint( $args['repository_id'] ?? 0 ) );
        if ( ! $repository ) return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        $tree = $this->github->get_tree( $repository['owner'], $repository['repo'], $repository['default_branch'] );
        if ( is_wp_error( $tree ) ) return $tree;

        $prefix = ltrim( sanitize_text_field( (string) ( $args['prefix'] ?? '' ) ), '/' );
        $cursor = max( 0, absint( $args['cursor'] ?? 0 ) );
        $limit = min( 500, max( 1, absint( $args['limit'] ?? 250 ) ) );
        $all = [];
        foreach ( $tree['tree'] ?? [] as $node ) {
            $path = (string) ( $node['path'] ?? '' );
            if ( $path === '' || ( $prefix !== '' && ! str_starts_with( $path, $prefix ) ) ) continue;
            $all[] = [
                'path' => $path,
                'type' => (string) ( $node['type'] ?? '' ),
                'size' => (int) ( $node['size'] ?? 0 ),
                'risk_hints' => $this->portfolio_hints_for_path( $path ),
            ];
        }
        $items = array_slice( $all, $cursor, $limit );
        $next = $cursor + count( $items );
        return [
            'repository' => [
                'id' => (int) $repository['id'],
                'owner' => (string) $repository['owner'],
                'name' => (string) $repository['repo'],
                'branch' => (string) $repository['default_branch'],
            ],
            'tree' => [
                'source_sha' => (string) ( $tree['sha'] ?? '' ),
                'prefix' => $prefix,
                'items' => $items,
                'count' => count( $items ),
                'total_matching' => count( $all ),
                'cursor' => $cursor,
                'next_cursor' => $next < count( $all ) ? $next : null,
                'truncated' => ! empty( $tree['truncated'] ) || $next < count( $all ),
            ],
        ];
    }

    private function get_tree_page( array $args ) {
        $result = $this->get_tree( $args );
        if ( is_wp_error( $result ) ) return $result;
        $tree = $result['tree'] ?? [];
        $repository = $result['repository'] ?? [];
        $next_cursor = $tree['next_cursor'] ?? null;
        return [
            'repository' => $repository,
            'source_sha' => (string) ( $tree['source_sha'] ?? '' ),
            'prefix' => (string) ( $tree['prefix'] ?? '' ),
            'cursor' => (int) ( $tree['cursor'] ?? 0 ),
            'limit' => min( 500, max( 1, absint( $args['limit'] ?? 250 ) ) ),
            'items' => array_values( $tree['items'] ?? [] ),
            'count' => (int) ( $tree['count'] ?? 0 ),
            'total_matching' => (int) ( $tree['total_matching'] ?? 0 ),
            'next_cursor' => $next_cursor === null ? null : (int) $next_cursor,
            'has_more' => $next_cursor !== null,
            'truncated_upstream' => ! empty( $tree['truncated'] ) && $next_cursor === null,
            'pagination_hint' => $next_cursor === null ? 'complete' : 'Call get_repository_tree_paginated again with cursor=next_cursor and the same prefix.',
        ];
    }

    private function portfolio_hints_for_path( string $path ): array {
        $p = strtolower( $path );
        $hints = [];
        $patterns = [
            'ai_prompt' => [ 'prompt', 'system_message', 'llm', 'openai', 'gemini', 'anthropic' ],
            'security_logic' => [ 'auth', 'security', 'permission', 'rbac', 'acl', 'policy', 'guard', 'middleware' ],
            'private_endpoint' => [ 'route', 'endpoint', 'api', 'controller', 'webhook' ],
            'business_logic' => [ 'service', 'workflow', 'billing', 'pricing', 'scoring', 'matching', 'recommend' ],
            'internal_schema' => [ 'schema', 'migration', 'model', 'entity', 'tenant', 'session' ],
            'core_ip' => [ 'algorithm', 'engine', 'optimizer', 'ranking', 'classifier', 'proprietary' ],
        ];
        foreach ( $patterns as $category => $needles ) {
            foreach ( $needles as $needle ) {
                if ( str_contains( $p, $needle ) ) { $hints[] = $category; break; }
            }
        }
        return array_values( array_unique( $hints ) );
    }

    private function get_file( array $args, array $connection ) {
        $repository = $this->repositories->find( absint( $args['repository_id'] ?? 0 ) );
        if ( ! $repository ) return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        $path = ltrim( sanitize_text_field( (string) ( $args['path'] ?? '' ) ), '/' );
        if ( $path === '' || str_contains( $path, '..' ) ) return new WP_Error( 'yougitai_invalid_path', __( 'Invalid repository path.', 'yougitai-secure-showcase' ) );
        if ( preg_match( '#(^|/)(\.env(?:\.|$)|id_rsa|id_ed25519|credentials(?:\.|$)|secrets?(?:\.|$)|private[_-]?key)(/|$)#i', $path ) ) {
            return new WP_Error( 'yougitai_sensitive_path', __( 'This path is blocked from connected AI review.', 'yougitai-secure-showcase' ) );
        }
        $file = $this->github->get_file( $repository['owner'], $repository['repo'], $path, $repository['default_branch'] );
        if ( is_wp_error( $file ) ) return $file;
        if ( (string) ( $file['encoding'] ?? '' ) !== 'base64' || empty( $file['content'] ) ) return new WP_Error( 'yougitai_file_unavailable', __( 'Source file could not be read for review.', 'yougitai-secure-showcase' ) );
        $content = base64_decode( preg_replace( '/\s+/', '', (string) $file['content'] ), true );
        if ( ! is_string( $content ) || str_contains( substr( $content, 0, 8000 ), "\0" ) ) return new WP_Error( 'yougitai_binary_file', __( 'Binary files cannot be sent for connected AI review.', 'yougitai-secure-showcase' ) );
        if ( strlen( $content ) > 250000 ) return new WP_Error( 'yougitai_file_too_large', __( 'This file is too large for connected AI review.', 'yougitai-secure-showcase' ) );
        $hits = $this->scanner->scan( $content );
        $safe = $this->scanner->redact( $content, $hits );
        $budget = (int) apply_filters( 'yougitai_ss_connected_ai_source_budget', 5242880 );
        if ( ! $this->tokens->consume_source_bytes( (int) $connection['id'], strlen( $content ), $budget, (string) ( $connection['connection_type'] ?? 'legacy' ) ) ) {
            unset( $content, $safe );
            return new WP_Error( 'yougitai_source_budget_exceeded', __( 'This connected-AI session has reached its source review limit. Create a new scoped connection to continue.', 'yougitai-secure-showcase' ) );
        }
        $this->audit->log( 'connected_ai_source_reviewed', __( 'A connected AI client reviewed a source file.', 'yougitai-secure-showcase' ), (int) $repository['id'], null, [ 'path' => $path, 'secrets_masked' => count( $hits ) ] );
        return [ 'repository_id' => (int) $repository['id'], 'path' => $path, 'sha' => (string) ( $file['sha'] ?? '' ), 'content' => $safe, 'secrets_masked' => count( $hits ) ];
    }

    private function get_file_excerpt( array $args, array $connection ) {
        $result = $this->get_file( $args, $connection );
        if ( is_wp_error( $result ) ) return $result;
        $lines = preg_split( '/\R/', (string) ( $result['content'] ?? '' ) );
        if ( ! is_array( $lines ) ) $lines = [];
        $start = max( 1, absint( $args['start_line'] ?? 1 ) );
        $count = min( 200, max( 1, absint( $args['line_count'] ?? 120 ) ) );
        $slice = array_slice( $lines, $start - 1, $count );
        unset( $result['content'] );
        $result['start_line'] = $start;
        $result['end_line'] = $start + max( 0, count( $slice ) - 1 );
        $result['total_lines'] = count( $lines );
        $result['content_excerpt'] = implode( "\n", $slice );
        $result['truncated'] = $result['end_line'] < count( $lines );
        return $result;
    }

    private function get_files( array $args, array $connection ) {
        $paths = is_array( $args['paths'] ?? null ) ? array_slice( $args['paths'], 0, 10 ) : [];
        if ( empty( $paths ) ) return new WP_Error( 'yougitai_paths_required', __( 'At least one repository path is required.', 'yougitai-secure-showcase' ) );
        $items = [];
        foreach ( $paths as $path ) {
            $result = $this->get_file( [ 'repository_id' => absint( $args['repository_id'] ?? 0 ), 'path' => (string) $path ], $connection );
            if ( is_wp_error( $result ) ) {
                $items[] = [ 'path' => (string) $path, 'error' => $result->get_error_message() ];
            } else {
                $items[] = $result;
            }
        }
        return [ 'files' => $items ];
    }

    private function get_proposals( array $args ): array {
        $repository_id = absint( $args['repository_id'] ?? 0 );
        return [ 'proposals' => array_values( array_filter( array_map( static function ( array $rule ): ?array {
            if ( $rule['source'] !== 'connected_ai' ) return null;
            return [
                'id' => (int) $rule['id'], 'file_path' => (string) $rule['file_path'], 'rule_type' => $rule['rule_type'],
                'target' => (string) $rule['target'], 'action' => $rule['action'], 'replacement' => (string) $rule['replacement'],
                'reason' => (string) ( $rule['notes'] ?? '' ), 'enabled' => (bool) $rule['enabled'], 'created_at' => $rule['created_at'],
            ];
        }, $this->repositories->rules( $repository_id ) ) ) ) ];
    }

    private function create_proposals( array $args, array $connection ) {
        $repository_id = absint( $args['repository_id'] ?? 0 );
        $proposals = is_array( $args['proposals'] ?? null ) ? array_slice( $args['proposals'], 0, 50 ) : [];
        if ( empty( $proposals ) ) return new WP_Error( 'yougitai_proposals_required', __( 'At least one redaction proposal is required.', 'yougitai-secure-showcase' ) );
        $created = [];
        $errors = [];
        foreach ( $proposals as $index => $proposal ) {
            if ( ! is_array( $proposal ) ) continue;
            $proposal['repository_id'] = $repository_id;
            $result = $this->create_proposal( $proposal, $connection );
            if ( is_wp_error( $result ) ) {
                $errors[] = [ 'index' => $index, 'error' => $result->get_error_message() ];
            } else {
                $created[] = $result;
            }
        }
        return [ 'created' => $created, 'errors' => $errors, 'requires_human_review' => true ];
    }

    private function get_findings( array $args ) {
        $repository_id = absint( $args['repository_id'] ?? 0 );
        $snapshot_id = absint( $args['snapshot_id'] ?? 0 );
        $snapshot = $this->repositories->snapshot( $snapshot_id );
        if ( ! $snapshot || (int) $snapshot['repository_id'] !== $repository_id ) return new WP_Error( 'yougitai_snapshot_not_found', __( 'Snapshot not found.', 'yougitai-secure-showcase' ) );
        return [ 'snapshot' => $snapshot, 'findings' => $this->repositories->findings_for_snapshot( $snapshot_id ) ];
    }

    private function create_proposal( array $args, array $connection ) {
        $repository_id = absint( $args['repository_id'] ?? 0 );
        $classification = sanitize_key( (string) ( $args['classification'] ?? '' ) );
        $decision = sanitize_key( (string) ( $args['decision'] ?? '' ) );
        $reason = (string) ( $args['reason'] ?? '' );
        if ( $classification !== '' || $decision !== '' ) {
            $reason = trim( '[portfolio classification=' . ( $classification !== '' ? $classification : 'unspecified' ) . '; decision=' . ( $decision !== '' ? $decision : 'unspecified' ) . '] ' . $reason );
        }
        $replacement = (string) ( $args['replacement'] ?? '[PROTECTED]' );
        if ( $decision === 'abstract' && ( $replacement === '' || $replacement === '[PROTECTED]' ) ) {
            $replacement = '[PROTECTED IMPLEMENTATION]';
        }
        $result = $this->repositories->add_ai_rule_proposal( $repository_id, [
            'file_path' => (string) ( $args['file_path'] ?? '' ),
            'rule_type' => (string) ( $args['rule_type'] ?? '' ),
            'target' => (string) ( $args['target'] ?? '' ),
            'action' => $decision === 'hide' ? 'hide' : (string) ( $args['action'] ?? '' ),
            'replacement' => $replacement,
            'reason' => $reason,
        ] );
        if ( is_wp_error( $result ) ) return $result;
        $this->audit->log( 'connected_ai_redaction_proposed', __( 'A connected AI client created a redaction proposal.', 'yougitai-secure-showcase' ), $repository_id, null, [ 'rule_id' => (int) $result, 'connection_id' => (int) $connection['id'] ] );
        return [ 'rule_id' => (int) $result, 'status' => 'proposal', 'enabled' => false, 'requires_human_review' => true ];
    }

    private function build_snapshot( array $args, array $connection ) {
        $repository_id = absint( $args['repository_id'] ?? 0 );
        $result = $this->repositories->import_snapshot( $repository_id );
        if ( is_wp_error( $result ) ) return $result;
        $this->audit->log( 'connected_ai_snapshot_built', __( 'A connected AI client created a secure draft snapshot.', 'yougitai-secure-showcase' ), $repository_id, (int) $result['snapshot_id'], [ 'connection_id' => (int) $connection['id'] ] );
        $result['published'] = false;
        return $result;
    }

    private function oauth_required_response(): WP_REST_Response {
        $message = __( 'OAuth authorization is required, or the connection token is invalid, expired, or revoked.', 'yougitai-secure-showcase' );
        $response = new WP_REST_Response( [
            'error' => 'invalid_token',
            'error_description' => $message,
        ], 401 );
        $scopes = implode( ' ', array_filter( OAuthServer::supported_scopes(), static fn( string $scope ): bool => $scope !== 'offline_access' ) );
        $challenge = 'Bearer error="invalid_token", error_description="Authorization required", resource_metadata="' . esc_url_raw( OAuthServer::resource_metadata_url() ) . '"';
        if ( $scopes !== '' ) {
            $challenge .= ', scope="' . esc_attr( $scopes ) . '"';
        }
        $response->header( 'WWW-Authenticate', $challenge );
        return $this->secure_response( $response );
    }

    private function rpc_result( $id, $result ): WP_REST_Response {
        $response = new WP_REST_Response( [ 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ], 200 );
        return $this->secure_response( $response );
    }

    private function rpc_error( $id, int $code, string $message, int $status = 200 ): WP_REST_Response {
        $response = new WP_REST_Response( [ 'jsonrpc' => '2.0', 'id' => $id, 'error' => [ 'code' => $code, 'message' => $message ] ], $status );
        return $this->secure_response( $response );
    }

    private function secure_response( WP_REST_Response $response ): WP_REST_Response {
        $response->header( 'Cache-Control', 'no-store, private, max-age=0' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        $response->header( 'Vary', 'Authorization' );
        return $response;
    }
}
