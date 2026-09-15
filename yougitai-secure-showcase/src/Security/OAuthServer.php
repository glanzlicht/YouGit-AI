<?php
namespace YougitAI\SecureShowcase\Security;

use YougitAI\SecureShowcase\Audit\Logger;
use YougitAI\SecureShowcase\Database\Schema;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class OAuthServer {
    public const ACCESS_TTL = HOUR_IN_SECONDS;
    public const REFRESH_TTL = 30 * DAY_IN_SECONDS;
    public const CODE_TTL = 10 * MINUTE_IN_SECONDS;

    public function register(): void {
        // Serve standards metadata before WordPress resolves a front-end 404/canonical request.
        // Some hosts/themes can short-circuit dot-prefixed /.well-known routes later in the request.
        add_action( 'parse_request', [ $this, 'maybe_serve_discovery_early' ], 0 );
        add_action( 'template_redirect', [ $this, 'maybe_serve_discovery_or_authorize' ], 0 );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    public function register_rest_routes(): void {
        register_rest_route( 'yougitai-secure-showcase/v1', '/connected-ai/oauth/register', [
            'methods' => 'POST',
            'callback' => [ $this, 'register_client' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'yougitai-secure-showcase/v1', '/connected-ai/oauth/token', [
            'methods' => 'POST',
            'callback' => [ $this, 'token' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function issuer(): string {
        return untrailingslashit( home_url( '/' ) );
    }

    public static function authorization_endpoint(): string {
        return home_url( '/yougitai-oauth/authorize' );
    }

    public static function token_endpoint(): string {
        return rest_url( 'yougitai-secure-showcase/v1/connected-ai/oauth/token' );
    }

    public static function registration_endpoint(): string {
        return rest_url( 'yougitai-secure-showcase/v1/connected-ai/oauth/register' );
    }

    public static function resource_metadata_url(): string {
        $resource_path = wp_parse_url( self::resource_url(), PHP_URL_PATH );
        $resource_path = is_string( $resource_path ) ? '/' . ltrim( $resource_path, '/' ) : '';
        return home_url( '/.well-known/oauth-protected-resource' . $resource_path );
    }

    public static function root_resource_metadata_url(): string {
        return home_url( '/.well-known/oauth-protected-resource' );
    }

    public static function authorization_metadata_url(): string {
        return home_url( '/.well-known/oauth-authorization-server' );
    }

    public static function resource_url(): string {
        return rest_url( 'yougitai-secure-showcase/v1/connected-ai/mcp' );
    }

    public static function supported_scopes(): array {
        return array_merge( ConnectionTokenService::DEFAULT_SCOPES, [ 'offline_access' ] );
    }

    /**
     * Serve OAuth discovery before normal front-end routing. This deliberately
     * does not depend on rewrite rules, pretty permalinks, or the active theme.
     */
    public function maybe_serve_discovery_early(): void {
        $this->maybe_serve_discovery();
    }

    private function maybe_serve_discovery(): void {
        $path = wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        $path = is_string( $path ) ? untrailingslashit( $path ) : '';

        $resource_path = wp_parse_url( self::resource_url(), PHP_URL_PATH );
        $resource_path = is_string( $resource_path ) ? '/' . ltrim( $resource_path, '/' ) : '';
        $resource_metadata_path = '/.well-known/oauth-protected-resource' . $resource_path;

        if ( $path === '/.well-known/oauth-protected-resource' || $path === untrailingslashit( $resource_metadata_path ) ) {
            $this->send_json( [
                'resource' => self::resource_url(),
                'authorization_servers' => [ self::issuer() ],
                'scopes_supported' => self::supported_scopes(),
                'bearer_methods_supported' => [ 'header' ],
                'resource_name' => 'YougitAI Secure Showcase',
            ] );
        }

        // RFC 8414 authorization-server metadata. Also expose the common
        // OpenID discovery alias because some OAuth clients probe it as a fallback.
        if ( $path === '/.well-known/oauth-authorization-server' || $path === '/.well-known/openid-configuration' ) {
            $this->send_json( [
                'issuer' => self::issuer(),
                'authorization_endpoint' => self::authorization_endpoint(),
                'token_endpoint' => self::token_endpoint(),
                'registration_endpoint' => self::registration_endpoint(),
                'scopes_supported' => self::supported_scopes(),
                'response_types_supported' => [ 'code' ],
                'grant_types_supported' => [ 'authorization_code', 'refresh_token' ],
                'token_endpoint_auth_methods_supported' => [ 'none' ],
                'code_challenge_methods_supported' => [ 'S256' ],
                'client_id_metadata_document_supported' => true,
                'authorization_response_iss_parameter_supported' => true,
            ] );
        }
    }

    public function maybe_serve_discovery_or_authorize(): void {
        // Keep the late hook as a compatibility fallback, but discovery is
        // normally served from parse_request above.
        $this->maybe_serve_discovery();

        $path = wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        $path = is_string( $path ) ? untrailingslashit( $path ) : '';
        if ( $path === '/yougitai-oauth/authorize' ) {
            $this->authorize();
        }
    }

    public function register_client( WP_REST_Request $request ): WP_REST_Response {
        if ( ! RateLimiter::allow( 'oauth-register:' . $this->request_ip_hash(), 20, HOUR_IN_SECONDS ) ) {
            return $this->json_error( 'rate_limited', __( 'Too many OAuth client registration attempts.', 'yougitai-secure-showcase' ), 429 );
        }

        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $body = $request->get_params();
        }
        $redirect_uris = $this->normalize_redirect_uris( $body['redirect_uris'] ?? [] );
        if ( empty( $redirect_uris ) ) {
            return $this->json_error( 'invalid_redirect_uri', __( 'At least one valid HTTPS redirect URI is required.', 'yougitai-secure-showcase' ), 400 );
        }

        $auth_method = sanitize_key( (string) ( $body['token_endpoint_auth_method'] ?? 'none' ) );
        if ( $auth_method !== 'none' ) {
            return $this->json_error( 'invalid_client_metadata', __( 'YougitAI supports public OAuth clients with PKCE only.', 'yougitai-secure-showcase' ), 400 );
        }

        global $wpdb;
        $client_id = 'ygc_' . bin2hex( random_bytes( 24 ) );
        $client_name = sanitize_text_field( (string) ( $body['client_name'] ?? 'ChatGPT Custom App' ) );
        $now = current_time( 'mysql', true );
        $ok = $wpdb->insert( Schema::table( 'oauth_clients' ), [
            'client_id' => $client_id,
            'client_name' => $client_name !== '' ? $client_name : 'ChatGPT Custom App',
            'redirect_uris' => wp_json_encode( $redirect_uris ),
            'created_at' => $now,
        ] );
        if ( ! $ok ) {
            return $this->json_error( 'server_error', __( 'OAuth client registration failed.', 'yougitai-secure-showcase' ), 500 );
        }

        $response = new WP_REST_Response( [
            'client_id' => $client_id,
            'client_id_issued_at' => time(),
            'client_name' => $client_name,
            'redirect_uris' => $redirect_uris,
            'grant_types' => [ 'authorization_code', 'refresh_token' ],
            'response_types' => [ 'code' ],
            'token_endpoint_auth_method' => 'none',
        ], 201 );
        return $this->secure_response( $response );
    }

    public function token( WP_REST_Request $request ): WP_REST_Response {
        if ( ! RateLimiter::allow( 'oauth-token:' . $this->request_ip_hash(), 120, HOUR_IN_SECONDS ) ) {
            return $this->json_error( 'temporarily_unavailable', __( 'Too many OAuth token requests.', 'yougitai-secure-showcase' ), 429 );
        }

        $grant_type = sanitize_key( (string) $request->get_param( 'grant_type' ) );
        if ( $grant_type === 'authorization_code' ) {
            return $this->exchange_authorization_code( $request );
        }
        if ( $grant_type === 'refresh_token' ) {
            return $this->exchange_refresh_token( $request );
        }
        return $this->json_error( 'unsupported_grant_type', __( 'Unsupported OAuth grant type.', 'yougitai-secure-showcase' ), 400 );
    }

    private function authorize(): void {
        $params = wp_unslash( $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET );
        $client_id = sanitize_text_field( (string) ( $params['client_id'] ?? '' ) );
        $redirect_uri = esc_url_raw( (string) ( $params['redirect_uri'] ?? '' ) );
        $response_type = sanitize_key( (string) ( $params['response_type'] ?? '' ) );
        $state = sanitize_text_field( (string) ( $params['state'] ?? '' ) );
        $code_challenge = sanitize_text_field( (string) ( $params['code_challenge'] ?? '' ) );
        $code_challenge_method = strtoupper( sanitize_text_field( (string) ( $params['code_challenge_method'] ?? '' ) ) );
        $scope = $this->normalize_scope_string( (string) ( $params['scope'] ?? '' ) );

        $client = $this->resolve_client( $client_id );
        if ( ! $client || ! $this->client_allows_redirect( $client, $redirect_uri ) ) {
            status_header( 400 );
            wp_die( esc_html__( 'Invalid OAuth client or redirect URI.', 'yougitai-secure-showcase' ), esc_html__( 'OAuth authorization failed', 'yougitai-secure-showcase' ), [ 'response' => 400 ] );
        }
        if ( $response_type !== 'code' || $code_challenge === '' || $code_challenge_method !== 'S256' ) {
            $this->redirect_oauth_error( $redirect_uri, $state, 'invalid_request', __( 'OAuth Authorization Code with PKCE S256 is required.', 'yougitai-secure-showcase' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( $this->current_url() ) );
            exit;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            status_header( 403 );
            wp_die( esc_html__( 'Only a WordPress administrator can authorize a ChatGPT connection.', 'yougitai-secure-showcase' ), esc_html__( 'Authorization denied', 'yougitai-secure-showcase' ), [ 'response' => 403 ] );
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            check_admin_referer( 'yougitai_ss_oauth_authorize_' . $client_id );
            if ( isset( $_POST['deny'] ) ) {
                $this->redirect_oauth_error( $redirect_uri, $state, 'access_denied', __( 'The WordPress administrator denied access.', 'yougitai-secure-showcase' ) );
            }

            $repository_ids = isset( $_POST['repository_ids'] ) && is_array( $_POST['repository_ids'] )
                ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['repository_ids'] ) ) ) ) )
                : [];
            $repository_ids = $this->filter_existing_repository_ids( $repository_ids );
            if ( empty( $repository_ids ) ) {
                $this->render_consent( $client, $params, $scope, __( 'Select at least one repository that ChatGPT may review.', 'yougitai-secure-showcase' ) );
            }

            $code = 'ygac_' . bin2hex( random_bytes( 32 ) );
            global $wpdb;
            $ok = $wpdb->insert( Schema::table( 'oauth_codes' ), [
                'code_hash' => hash( 'sha256', $code ),
                'client_id' => $client_id,
                'user_id' => get_current_user_id(),
                'redirect_uri' => $redirect_uri,
                'scopes' => wp_json_encode( $scope ),
                'repository_ids' => wp_json_encode( $repository_ids ),
                'code_challenge' => $code_challenge,
                'created_at' => current_time( 'mysql', true ),
                'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::CODE_TTL ),
            ] );
            if ( ! $ok ) {
                $this->redirect_oauth_error( $redirect_uri, $state, 'server_error', __( 'Could not create an OAuth authorization code.', 'yougitai-secure-showcase' ) );
            }
            ( new Logger() )->log( 'connected_ai_oauth_authorized', __( 'A WordPress administrator authorized a ChatGPT OAuth connection.', 'yougitai-secure-showcase' ), null, null, [ 'client_id' => $client_id, 'repository_ids' => $repository_ids ] );
            $target = add_query_arg( array_filter( [ 'code' => $code, 'state' => $state, 'iss' => self::issuer() ], static fn( $value ) => $value !== '' ), $redirect_uri );
            wp_redirect( $target, 302, 'YougitAI' );
            exit;
        }

        $this->render_consent( $client, $params, $scope );
    }

    private function exchange_authorization_code( WP_REST_Request $request ): WP_REST_Response {
        $code = (string) $request->get_param( 'code' );
        $client_id = sanitize_text_field( (string) $request->get_param( 'client_id' ) );
        $redirect_uri = esc_url_raw( (string) $request->get_param( 'redirect_uri' ) );
        $verifier = (string) $request->get_param( 'code_verifier' );

        if ( $code === '' || $client_id === '' || $redirect_uri === '' || $verifier === '' ) {
            return $this->json_error( 'invalid_request', __( 'Missing OAuth authorization-code parameters.', 'yougitai-secure-showcase' ), 400 );
        }

        global $wpdb;
        $table = Schema::table( 'oauth_codes' );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE code_hash=%s LIMIT 1', hash( 'sha256', $code ) ), ARRAY_A );
        if ( ! $row || ! empty( $row['used_at'] ) || strtotime( (string) $row['expires_at'] . ' UTC' ) <= time() ) {
            return $this->json_error( 'invalid_grant', __( 'Authorization code is invalid, expired, or already used.', 'yougitai-secure-showcase' ), 400 );
        }
        if ( ! hash_equals( (string) $row['client_id'], $client_id ) || ! hash_equals( (string) $row['redirect_uri'], $redirect_uri ) ) {
            return $this->json_error( 'invalid_grant', __( 'Authorization code does not match this OAuth client.', 'yougitai-secure-showcase' ), 400 );
        }
        $expected = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
        if ( ! hash_equals( (string) $row['code_challenge'], $expected ) ) {
            return $this->json_error( 'invalid_grant', __( 'PKCE verification failed.', 'yougitai-secure-showcase' ), 400 );
        }

        $wpdb->update( $table, [ 'used_at' => current_time( 'mysql', true ) ], [ 'id' => (int) $row['id'] ] );
        return $this->issue_tokens( $row );
    }

    private function exchange_refresh_token( WP_REST_Request $request ): WP_REST_Response {
        $refresh_token = (string) $request->get_param( 'refresh_token' );
        $client_id = sanitize_text_field( (string) $request->get_param( 'client_id' ) );
        if ( $refresh_token === '' || $client_id === '' ) {
            return $this->json_error( 'invalid_request', __( 'Refresh token and client ID are required.', 'yougitai-secure-showcase' ), 400 );
        }

        global $wpdb;
        $table = Schema::table( 'oauth_tokens' );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE refresh_token_hash=%s LIMIT 1', hash( 'sha256', $refresh_token ) ), ARRAY_A );
        if ( ! $row || ! empty( $row['revoked_at'] ) || ! hash_equals( (string) $row['client_id'], $client_id ) || strtotime( (string) $row['refresh_expires_at'] . ' UTC' ) <= time() ) {
            return $this->json_error( 'invalid_grant', __( 'Refresh token is invalid, expired, or revoked.', 'yougitai-secure-showcase' ), 400 );
        }

        $access = 'ygao_' . bin2hex( random_bytes( 32 ) );
        $refresh = 'ygar_' . bin2hex( random_bytes( 32 ) );
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + self::ACCESS_TTL );
        $refresh_expires_at = gmdate( 'Y-m-d H:i:s', time() + self::REFRESH_TTL );
        $wpdb->update( $table, [
            'access_token_hash' => hash( 'sha256', $access ),
            'refresh_token_hash' => hash( 'sha256', $refresh ),
            'expires_at' => $expires_at,
            'refresh_expires_at' => $refresh_expires_at,
            'last_used_at' => current_time( 'mysql', true ),
        ], [ 'id' => (int) $row['id'] ] );

        return $this->token_response( $access, $refresh, $row['scopes'] );
    }

    private function issue_tokens( array $authorization ): WP_REST_Response {
        global $wpdb;
        $access = 'ygao_' . bin2hex( random_bytes( 32 ) );
        $refresh = 'ygar_' . bin2hex( random_bytes( 32 ) );
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + self::ACCESS_TTL );
        $refresh_expires_at = gmdate( 'Y-m-d H:i:s', time() + self::REFRESH_TTL );
        $scopes = $this->decode_string_array( (string) $authorization['scopes'] );
        $repository_ids = $this->decode_int_array( (string) $authorization['repository_ids'] );
        $client = $this->resolve_client( (string) $authorization['client_id'] );
        $label = $client ? (string) $client['client_name'] : 'ChatGPT Custom App';

        $ok = $wpdb->insert( Schema::table( 'oauth_tokens' ), [
            'client_id' => (string) $authorization['client_id'],
            'user_id' => (int) $authorization['user_id'],
            'label' => $label,
            'access_token_hash' => hash( 'sha256', $access ),
            'refresh_token_hash' => hash( 'sha256', $refresh ),
            'scopes' => wp_json_encode( $scopes ),
            'repository_ids' => wp_json_encode( $repository_ids ),
            'created_at' => current_time( 'mysql', true ),
            'expires_at' => $expires_at,
            'refresh_expires_at' => $refresh_expires_at,
        ] );
        if ( ! $ok ) {
            return $this->json_error( 'server_error', __( 'Could not issue OAuth tokens.', 'yougitai-secure-showcase' ), 500 );
        }

        return $this->token_response( $access, $refresh, wp_json_encode( $scopes ) );
    }

    private function token_response( string $access, string $refresh, string $scopes_json ): WP_REST_Response {
        $scopes = $this->decode_string_array( $scopes_json );
        $response = new WP_REST_Response( [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL,
            'refresh_token' => $refresh,
            'scope' => implode( ' ', $scopes ),
        ], 200 );
        return $this->secure_response( $response );
    }

    private function render_consent( array $client, array $params, array $scope, string $error = '' ): void {
        global $wpdb;
        $repositories = $wpdb->get_results( 'SELECT id,title,owner,repo,is_private FROM ' . Schema::table( 'repositories' ) . ' ORDER BY title ASC', ARRAY_A ) ?: [];
        nocache_headers();
        status_header( 200 );
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
        $action = esc_url( self::authorization_endpoint() );
        $scope_labels = $this->scope_labels();
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php esc_html_e( 'Authorize ChatGPT', 'yougitai-secure-showcase' ); ?></title>
<style>
body{margin:0;background:#f6f7f7;color:#1d2327;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.yg-oauth{max-width:760px;margin:48px auto;padding:0 20px}.yg-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.04)}h1{margin:0 0 8px;font-size:28px}.muted{color:#646970}.scope,.repo{display:flex;gap:10px;align-items:flex-start;padding:11px 0;border-bottom:1px solid #f0f0f1}.repo input{margin-top:5px}.badge{display:inline-block;padding:2px 7px;border-radius:999px;background:#eef4ff;font-size:12px}.actions{display:flex;gap:12px;margin-top:24px}.button{appearance:none;border:1px solid #2271b1;border-radius:7px;padding:10px 16px;font-weight:600;cursor:pointer}.primary{background:#2271b1;color:#fff}.secondary{background:#fff;color:#1d2327;border-color:#8c8f94}.warning{background:#fff8e5;border-left:4px solid #dba617;padding:12px;margin:18px 0}.error{background:#fcf0f1;border-left:4px solid #d63638;padding:12px;margin:18px 0}
</style></head><body><main class="yg-oauth"><div class="yg-card">
<h1><?php esc_html_e( 'Allow ChatGPT to connect to YougitAI?', 'yougitai-secure-showcase' ); ?></h1>
<p class="muted"><?php printf( esc_html__( '%s wants to review selected repositories through this WordPress site.', 'yougitai-secure-showcase' ), esc_html( (string) $client['client_name'] ) ); ?></p>
<div class="warning"><strong><?php esc_html_e( 'Publishing is not permitted.', 'yougitai-secure-showcase' ); ?></strong> <?php esc_html_e( 'ChatGPT may review source and propose protections, but it cannot publish or restore a public snapshot.', 'yougitai-secure-showcase' ); ?></div>
<?php if ( $error !== '' ) : ?><div class="error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
<h2><?php esc_html_e( 'Requested permissions', 'yougitai-secure-showcase' ); ?></h2>
<?php foreach ( $scope as $item ) : if ( $item === 'offline_access' ) continue; ?><div class="scope"><span>✓</span><div><strong><?php echo esc_html( $scope_labels[ $item ] ?? $item ); ?></strong><br><small class="muted"><?php echo esc_html( $item ); ?></small></div></div><?php endforeach; ?>
<h2><?php esc_html_e( 'Choose repositories', 'yougitai-secure-showcase' ); ?></h2>
<p class="muted"><?php esc_html_e( 'Only repositories selected here will be visible to this ChatGPT connection.', 'yougitai-secure-showcase' ); ?></p>
<form method="post" action="<?php echo $action; ?>">
<?php foreach ( [ 'client_id','redirect_uri','response_type','state','code_challenge','code_challenge_method','scope','resource' ] as $key ) : if ( isset( $params[ $key ] ) ) : ?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( is_array( $params[ $key ] ) ? '' : (string) $params[ $key ] ); ?>"><?php endif; endforeach; ?>
<?php wp_nonce_field( 'yougitai_ss_oauth_authorize_' . (string) $client['client_id'] ); ?>
<?php if ( empty( $repositories ) ) : ?><p><?php esc_html_e( 'No YougitAI repositories are available yet.', 'yougitai-secure-showcase' ); ?></p><?php else : foreach ( $repositories as $repo ) : ?><label class="repo"><input type="checkbox" name="repository_ids[]" value="<?php echo esc_attr( (int) $repo['id'] ); ?>"><span><strong><?php echo esc_html( (string) $repo['title'] ); ?></strong> <?php if ( ! empty( $repo['is_private'] ) ) : ?><span class="badge"><?php esc_html_e( 'Private GitHub repository', 'yougitai-secure-showcase' ); ?></span><?php endif; ?><br><small class="muted"><?php echo esc_html( (string) $repo['owner'] . '/' . (string) $repo['repo'] ); ?></small></span></label><?php endforeach; endif; ?>
<div class="actions"><button class="button primary" type="submit" name="approve" value="1"><?php esc_html_e( 'Allow access', 'yougitai-secure-showcase' ); ?></button><button class="button secondary" type="submit" name="deny" value="1"><?php esc_html_e( 'Deny', 'yougitai-secure-showcase' ); ?></button></div>
</form></div></main></body></html><?php
        exit;
    }

    private function scope_labels(): array {
        return [
            'repositories:read' => __( 'Read repository metadata and file trees', 'yougitai-secure-showcase' ),
            'source:review' => __( 'Read masked source code for security review', 'yougitai-secure-showcase' ),
            'findings:read' => __( 'Read security findings', 'yougitai-secure-showcase' ),
            'redactions:propose' => __( 'Create disabled redaction proposals', 'yougitai-secure-showcase' ),
            'snapshots:create' => __( 'Create draft snapshots', 'yougitai-secure-showcase' ),
            'offline_access' => __( 'Keep the connection signed in with refresh tokens', 'yougitai-secure-showcase' ),
        ];
    }

    private function normalize_scope_string( string $scope ): array {
        $requested = preg_split( '/\s+/', trim( $scope ) ) ?: [];
        if ( empty( $requested ) ) {
            $requested = self::supported_scopes();
        }
        $supported = self::supported_scopes();
        return array_values( array_unique( array_intersect( $requested, $supported ) ) );
    }

    private function normalize_redirect_uris( $value ): array {
        if ( ! is_array( $value ) ) return [];
        $out = [];
        foreach ( array_slice( $value, 0, 20 ) as $uri ) {
            $uri = esc_url_raw( (string) $uri );
            if ( $uri === '' || str_contains( $uri, '*' ) || str_contains( $uri, '#' ) ) continue;
            $parts = wp_parse_url( $uri );
            $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
            $host = strtolower( (string) ( $parts['host'] ?? '' ) );
            $loopback = in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true );
            if ( $scheme !== 'https' && ! ( $scheme === 'http' && $loopback ) ) continue;
            $out[] = $uri;
        }
        return array_values( array_unique( $out ) );
    }

    private function resolve_client( string $client_id ): ?array {
        $registered = $this->find_client( $client_id );
        if ( $registered ) {
            return $registered;
        }

        if ( ! str_starts_with( strtolower( $client_id ), 'https://' ) ) {
            return null;
        }

        $parts = wp_parse_url( $client_id );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['path'] ) || $parts['path'] === '/' || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || isset( $parts['query'] ) ) {
            return null;
        }

        $cache_key = 'yougitai_ss_cimd_' . hash( 'sha256', $client_id );
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_safe_remote_get( $client_id, [
            'timeout' => 8,
            'redirection' => 2,
            'headers' => [ 'Accept' => 'application/json' ],
            'limit_response_size' => 32768,
            'user-agent' => 'YougitAI-Secure-Showcase/' . ( defined( 'YOUGITAI_SS_VERSION' ) ? YOUGITAI_SS_VERSION : '1.0' ),
        ] );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $metadata = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $metadata ) || ! isset( $metadata['client_id'] ) || ! is_string( $metadata['client_id'] ) || ! hash_equals( $client_id, $metadata['client_id'] ) ) {
            return null;
        }

        $redirect_uris = $this->normalize_redirect_uris( $metadata['redirect_uris'] ?? [] );
        if ( empty( $redirect_uris ) ) {
            return null;
        }

        $auth_method = (string) ( $metadata['token_endpoint_auth_method'] ?? 'none' );
        if ( $auth_method !== 'none' ) {
            return null;
        }

        $client = [
            'client_id' => $client_id,
            'client_name' => sanitize_text_field( (string) ( $metadata['client_name'] ?? $metadata['name'] ?? 'ChatGPT Custom App' ) ),
            'redirect_uris' => wp_json_encode( $redirect_uris ),
            'cimd' => true,
        ];
        set_transient( $cache_key, $client, 10 * MINUTE_IN_SECONDS );
        return $client;
    }

    private function find_client( string $client_id ): ?array {
        global $wpdb;
        if ( $client_id === '' ) return null;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'oauth_clients' ) . ' WHERE client_id=%s AND revoked_at IS NULL LIMIT 1', $client_id ), ARRAY_A );
        return $row ?: null;
    }

    private function client_allows_redirect( array $client, string $redirect_uri ): bool {
        $uris = json_decode( (string) $client['redirect_uris'], true );
        if ( ! is_array( $uris ) ) return false;
        foreach ( $uris as $uri ) {
            if ( is_string( $uri ) && hash_equals( $uri, $redirect_uri ) ) return true;
        }
        return false;
    }

    private function filter_existing_repository_ids( array $ids ): array {
        if ( empty( $ids ) ) return [];
        global $wpdb;
        $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = $wpdb->prepare( 'SELECT id FROM ' . Schema::table( 'repositories' ) . ' WHERE id IN (' . $placeholders . ')', ...$ids );
        return array_map( 'intval', $wpdb->get_col( $sql ) ?: [] );
    }

    private function redirect_oauth_error( string $redirect_uri, string $state, string $error, string $description ): void {
        $url = add_query_arg( array_filter( [ 'error' => $error, 'error_description' => $description, 'state' => $state, 'iss' => self::issuer() ], static fn( $value ) => $value !== '' ), $redirect_uri );
        wp_redirect( $url, 302, 'YougitAI' );
        exit;
    }

    private function current_url(): string {
        return home_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/yougitai-oauth/authorize' ) );
    }

    private function request_ip_hash(): string {
        $ip = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
        return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 24 );
    }

    private function decode_string_array( string $json ): array {
        $value = json_decode( $json, true );
        return is_array( $value ) ? array_values( array_filter( array_map( 'strval', $value ) ) ) : [];
    }

    private function decode_int_array( string $json ): array {
        $value = json_decode( $json, true );
        return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : [];
    }

    private function send_json( array $data ): void {
        nocache_headers();
        status_header( 200 );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
        header( 'X-Content-Type-Options: nosniff' );
        echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
        exit;
    }

    private function json_error( string $error, string $description, int $status ): WP_REST_Response {
        $response = new WP_REST_Response( [ 'error' => $error, 'error_description' => $description ], $status );
        return $this->secure_response( $response );
    }

    private function secure_response( WP_REST_Response $response ): WP_REST_Response {
        $response->header( 'Cache-Control', 'no-store, private, max-age=0' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        return $response;
    }
}
