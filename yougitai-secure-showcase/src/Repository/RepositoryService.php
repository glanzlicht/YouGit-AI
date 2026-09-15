<?php
namespace YougitAI\SecureShowcase\Repository;

use YougitAI\SecureShowcase\AI\ProviderFactory;
use YougitAI\SecureShowcase\Audit\Logger;
use YougitAI\SecureShowcase\Database\Schema;
use YougitAI\SecureShowcase\GitHub\Client;
use YougitAI\SecureShowcase\Review\FindingService;
use YougitAI\SecureShowcase\Security\SnapshotVerifier;
use YougitAI\SecureShowcase\Security\SecretVault;
use WP_Error;

final class RepositoryService {
    private Client $github;
    private RedactionEngine $redactor;
    private FindingService $findings;
    private Logger $audit;

    public function __construct() {
        $this->github = new Client();
        $this->redactor = new RedactionEngine();
        $this->findings = new FindingService();
        $this->audit = new Logger();
    }

    public function all(): array {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . Schema::table( 'repositories' ) . ' ORDER BY updated_at DESC', ARRAY_A ) ?: [];
    }

    public function find( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'repositories' ) . ' WHERE id = %d', $id ), ARRAY_A );
        return $row ?: null;
    }

    public function find_by_slug( string $slug ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'repositories' ) . ' WHERE slug = %s', $slug ), ARRAY_A );
        return $row ?: null;
    }

    public function create_from_github_url( string $url, string $profile = 'balanced' ) {
        $parts = $this->parse_github_url( $url );
        if ( is_wp_error( $parts ) ) {
            return $parts;
        }
        [ $owner, $repo ] = $parts;
        $meta = $this->github->get_repository( $owner, $repo );
        if ( is_wp_error( $meta ) ) {
            return $meta;
        }

        $allowed_profiles = [ 'portfolio', 'balanced', 'investor', 'maximum' ];
        $profile = in_array( $profile, $allowed_profiles, true ) ? $profile : 'balanced';

        global $wpdb;
        $now = current_time( 'mysql' );
        $slug = sanitize_title( $repo );
        $base = $slug;
        $i = 2;
        while ( $this->find_by_slug( $slug ) ) {
            $slug = $base . '-' . $i++;
        }
        $wpdb->insert( Schema::table( 'repositories' ), [
            'owner' => sanitize_text_field( $owner ),
            'repo' => sanitize_text_field( $repo ),
            'slug' => $slug,
            'title' => sanitize_text_field( $meta['name'] ?? $repo ),
            'description' => sanitize_textarea_field( $meta['description'] ?? '' ),
            'default_branch' => sanitize_text_field( $meta['default_branch'] ?? 'main' ),
            'github_url' => esc_url_raw( $meta['html_url'] ?? $url ),
            'visibility' => 'public',
            'status' => 'draft',
            'protection_profile' => $profile,
            'show_original' => 0,
            'is_private' => ! empty( $meta['private'] ) ? 1 : 0,
            'sync_mode' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ] );
        if ( ! $wpdb->insert_id ) {
            return new WP_Error( 'yougitai_db_error', __( 'The repository could not be saved.', 'yougitai-secure-showcase' ) );
        }
        $id = (int) $wpdb->insert_id;
        $this->audit->log( 'repository_created', __( 'Repository created.', 'yougitai-secure-showcase' ), $id, null, [ 'owner' => $owner, 'repo' => $repo ] );
        return $id;
    }


    /**
     * Refresh repository-level GitHub metadata before a real GitHub sync.
     *
     * Snapshot rebuilds that only apply already-approved protection rules do not
     * call this method and therefore remain fully local/GitHub-free.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function refresh_github_metadata( int $repository_id ) {
        $repository = $this->find( $repository_id );
        if ( ! $repository ) {
            return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        }

        $meta = $this->github->get_repository( (string) $repository['owner'], (string) $repository['repo'] );
        if ( is_wp_error( $meta ) ) {
            return $meta;
        }

        $description = sanitize_textarea_field( (string) ( $meta['description'] ?? '' ) );
        $default_branch = sanitize_text_field( (string) ( $meta['default_branch'] ?? $repository['default_branch'] ?? 'main' ) );
        $github_url = esc_url_raw( (string) ( $meta['html_url'] ?? $repository['github_url'] ?? '' ) );
        $is_private = ! empty( $meta['private'] ) ? 1 : 0;
        $now = current_time( 'mysql' );

        global $wpdb;
        $updated = $wpdb->update( Schema::table( 'repositories' ), [
            'description' => $description,
            'default_branch' => $default_branch,
            'github_url' => $github_url,
            'is_private' => $is_private,
            'updated_at' => $now,
        ], [ 'id' => $repository_id ] );

        if ( $updated === false ) {
            return new WP_Error( 'yougitai_metadata_update_failed', __( 'GitHub repository metadata could not be refreshed.', 'yougitai-secure-showcase' ) );
        }

        $repository['description'] = $description;
        $repository['default_branch'] = $default_branch;
        $repository['github_url'] = $github_url;
        $repository['is_private'] = $is_private;
        $repository['updated_at'] = $now;

        $this->audit->log(
            'repository_metadata_refreshed',
            __( 'GitHub repository metadata refreshed.', 'yougitai-secure-showcase' ),
            $repository_id,
            null,
            [ 'description_updated' => true, 'default_branch' => $default_branch ]
        );

        return $repository;
    }

    public function snapshots( int $repository_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Schema::table( 'snapshots' ) . ' WHERE repository_id=%d ORDER BY id DESC',
            $repository_id
        ), ARRAY_A ) ?: [];
    }

    public function snapshot( int $snapshot_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::table( 'snapshots' ) . ' WHERE id=%d', $snapshot_id ), ARRAY_A );
        return $row ?: null;
    }

    public function import_snapshot( int $repository_id ) {
        $repository = $this->refresh_github_metadata( $repository_id );
        if ( is_wp_error( $repository ) ) {
            return $repository;
        }

        $tree = $this->github->get_tree( $repository['owner'], $repository['repo'], $repository['default_branch'] );
        if ( is_wp_error( $tree ) ) {
            return $tree;
        }

        global $wpdb;
        $now = current_time( 'mysql' );
        $wpdb->insert( Schema::table( 'snapshots' ), [
            'repository_id' => $repository_id,
            'source_ref' => $repository['default_branch'],
            'source_sha' => sanitize_text_field( $tree['sha'] ?? '' ),
            'status' => 'draft',
            'review_status' => 'pending',
            'show_original' => ! empty( $repository['show_original'] ) ? 1 : 0,
            'created_at' => $now,
        ] );
        $snapshot_id = (int) $wpdb->insert_id;
        if ( ! $snapshot_id ) {
            return new WP_Error( 'yougitai_snapshot_error', __( 'Snapshot could not be created.', 'yougitai-secure-showcase' ) );
        }

        $count = 0;
        $finding_count = 0;
        $critical_count = 0;
        $max_files = (int) apply_filters( 'yougitai_ss_import_file_limit', 500 );
        $max_file_size = (int) apply_filters( 'yougitai_ss_import_max_file_size', 300000 );
        $rules = $this->rules( $repository_id );
        $show_original = ! empty( $repository['show_original'] );
        $ai = ProviderFactory::make();
        $ai_enabled = ! $show_original && $ai->is_configured();

        foreach ( $tree['tree'] ?? [] as $node ) {
            if ( $count >= $max_files ) {
                break;
            }
            if ( ( $node['type'] ?? '' ) !== 'blob' ) {
                continue;
            }
            $path = (string) ( $node['path'] ?? '' );
            $size = (int) ( $node['size'] ?? 0 );
            if ( $path === '' || $size > $max_file_size || $this->ignored_path( $path ) ) {
                continue;
            }

            // Secure mode fails closed without fetching known-sensitive files.
            // Original mode is an explicit opt-out: text source is intentionally copied into the public draft.
            if ( ! $show_original && $this->redactor->is_blocked_path( $path ) ) {
                $public = $this->redactor->build_public_content( $path, '', $rules );
                $file_id = $this->insert_public_file( $repository_id, $snapshot_id, $path, $size, $node, $public, $now );
                foreach ( $public['findings'] as $finding ) {
                    $this->findings->add( $repository_id, $snapshot_id, $file_id, $path, $finding );
                    $finding_count++;
                    if ( ( $finding['severity'] ?? '' ) === 'critical' ) {
                        $critical_count++;
                    }
                }
                $count++;
                continue;
            }

            $file = $this->github->get_file( $repository['owner'], $repository['repo'], $path, $repository['default_branch'] );
            if ( is_wp_error( $file ) || ( $file['encoding'] ?? '' ) !== 'base64' ) {
                continue;
            }
            $content = base64_decode( preg_replace( '/\s+/', '', (string) $file['content'] ), true );
            if ( $content === false || $this->looks_binary( $content ) ) {
                continue;
            }

            // SECURITY BOUNDARY: original source only exists in this local variable.
            // It is never inserted into WordPress tables, options, transients, logs, or public APIs.
            $ai_findings = [];
            if ( $ai_enabled && $this->should_ai_review( $path, $content, (string) $repository['protection_profile'] ) ) {
                $analysis = $ai->analyze_file( $path, $content, [
                    'profile' => $repository['protection_profile'],
                    'repository' => $repository['owner'] . '/' . $repository['repo'],
                ] );
                if ( ! is_wp_error( $analysis ) ) {
                    $ai_findings = is_array( $analysis['findings'] ?? null ) ? $analysis['findings'] : [];
                }
            }

            if ( $show_original ) {
                $public = [
                    'content' => $content,
                    'visibility' => 'public',
                    'risk_score' => 0,
                    'notes' => [ __( 'Original source mode: no redaction rules, secret scanning, or AI protection were applied to this file.', 'yougitai-secure-showcase' ) ],
                    'findings' => [],
                ];
            } else {
                $public = $this->redactor->build_public_content( $path, $content, $rules, $ai_findings );
            }
            unset( $content, $file );

            $file_id = $this->insert_public_file( $repository_id, $snapshot_id, $path, $size, $node, $public, $now );
            foreach ( $public['findings'] as $finding ) {
                $this->findings->add( $repository_id, $snapshot_id, $file_id, $path, $finding );
                $finding_count++;
                if ( ( $finding['severity'] ?? '' ) === 'critical' ) {
                    $critical_count++;
                }
            }
            $count++;
        }

        $review_status = $critical_count > 0 || $finding_count > 0 ? 'needs_review' : 'ready';
        $wpdb->update( Schema::table( 'snapshots' ), [
            'file_count' => $count,
            'finding_count' => $finding_count,
            'critical_count' => $critical_count,
            'review_status' => $review_status,
        ], [ 'id' => $snapshot_id ] );
        $wpdb->update( Schema::table( 'repositories' ), [ 'updated_at' => $now ], [ 'id' => $repository_id ] );
        $this->audit->log( 'snapshot_imported', __( 'Secure snapshot imported.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id, [
            'files' => $count,
            'findings' => $finding_count,
            'critical' => $critical_count,
            'ai_enabled' => $ai_enabled,
            'show_original' => $show_original,
        ] );

        return [
            'snapshot_id' => $snapshot_id,
            'files' => $count,
            'findings' => $finding_count,
            'critical' => $critical_count,
            'review_status' => $review_status,
        ];
    }

    /**
     * Build a new protected draft from an already stored safe snapshot.
     * This never contacts GitHub and never reconstructs original source.
     */
    public function rebuild_protected_snapshot( int $repository_id, int $base_snapshot_id = 0 ) {
        $repository = $this->find( $repository_id );
        if ( ! $repository ) {
            return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        }
        if ( ! empty( $repository['show_original'] ) ) {
            return new WP_Error( 'yougitai_original_mode', __( 'Protection rules cannot be applied while Original source mode is active.', 'yougitai-secure-showcase' ) );
        }

        $snapshots = $this->snapshots( $repository_id );
        $base = $base_snapshot_id > 0 ? $this->snapshot( $base_snapshot_id ) : ( $snapshots[0] ?? null );
        if ( ! $base || (int) $base['repository_id'] !== $repository_id ) {
            return new WP_Error( 'yougitai_snapshot_not_found', __( 'No existing draft is available to protect.', 'yougitai-secure-showcase' ) );
        }

        global $wpdb;
        $base_files = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Schema::table( 'files' ) . ' WHERE repository_id=%d AND snapshot_id=%d ORDER BY path ASC',
            $repository_id,
            (int) $base['id']
        ), ARRAY_A ) ?: [];
        if ( ! $base_files ) {
            return new WP_Error( 'yougitai_snapshot_empty', __( 'The selected draft contains no files.', 'yougitai-secure-showcase' ) );
        }

        $now = current_time( 'mysql' );
        $wpdb->insert( Schema::table( 'snapshots' ), [
            'repository_id' => $repository_id,
            'source_ref' => (string) $base['source_ref'],
            'source_sha' => (string) $base['source_sha'],
            'status' => 'draft',
            'review_status' => 'pending',
            'show_original' => 0,
            'created_at' => $now,
        ] );
        $snapshot_id = (int) $wpdb->insert_id;
        if ( ! $snapshot_id ) {
            return new WP_Error( 'yougitai_snapshot_error', __( 'Protected draft could not be created.', 'yougitai-secure-showcase' ) );
        }

        $rules = $this->rules( $repository_id );
        $active_rules = array_values( array_filter( $rules, static fn( array $rule ): bool => ! empty( $rule['enabled'] ) ) );
        if ( ! $active_rules ) {
            $wpdb->delete( Schema::table( 'snapshots' ), [ 'id' => $snapshot_id ] );
            return new WP_Error( 'yougitai_no_active_rules', __( 'There are no active protection rules to apply.', 'yougitai-secure-showcase' ) );
        }
        $rules = $active_rules;
        $count = 0;
        $finding_count = 0;
        $critical_count = 0;

        foreach ( $base_files as $file ) {
            $path = (string) $file['path'];
            // A previously hidden file stays hidden. Rebuilding protection must never expose it again.
            if ( (string) $file['visibility'] === 'hidden' ) {
                $public = [
                    'content' => '',
                    'visibility' => 'hidden',
                    'risk_score' => max( 80, (int) $file['risk_score'] ),
                    'notes' => [ __( 'Already protected in the source draft.', 'yougitai-secure-showcase' ) ],
                    'findings' => [],
                    'redactions' => [],
                ];
            } else {
                // SECURITY: this is already-sanitized snapshot content, never original GitHub source.
                $public = $this->redactor->build_public_content( $path, (string) $file['content'], $rules, [] );
                $existing_manifest = json_decode( (string) ( $file['redaction_manifest'] ?? '' ), true );
                if ( is_array( $existing_manifest ) && $existing_manifest ) {
                    $public['redactions'] = array_merge( $existing_manifest, is_array( $public['redactions'] ?? null ) ? $public['redactions'] : [] );
                    if ( (string) $file['visibility'] === 'redacted' && (string) ( $public['visibility'] ?? 'public' ) === 'public' ) {
                        $public['visibility'] = 'redacted';
                    }
                }
            }
            $node = [ 'sha' => (string) $file['source_sha'] ];
            $file_id = $this->insert_public_file( $repository_id, $snapshot_id, $path, (int) $file['size'], $node, $public, $now );
            foreach ( $public['findings'] as $finding ) {
                $this->findings->add( $repository_id, $snapshot_id, $file_id, $path, $finding );
                $finding_count++;
                if ( ( $finding['severity'] ?? '' ) === 'critical' ) $critical_count++;
            }
            $count++;
        }

        $review_status = $critical_count > 0 || $finding_count > 0 ? 'needs_review' : 'ready';
        $wpdb->update( Schema::table( 'snapshots' ), [
            'file_count' => $count,
            'finding_count' => $finding_count,
            'critical_count' => $critical_count,
            'review_status' => $review_status,
        ], [ 'id' => $snapshot_id ] );
        $wpdb->update( Schema::table( 'repositories' ), [ 'updated_at' => $now ], [ 'id' => $repository_id ] );
        $this->audit->log( 'snapshot_protection_rebuilt', __( 'Active protection rules were applied to an existing safe draft without contacting GitHub.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id, [ 'base_snapshot_id' => (int) $base['id'] ] );

        return [
            'snapshot_id' => $snapshot_id,
            'base_snapshot_id' => (int) $base['id'],
            'files' => $count,
            'findings' => $finding_count,
            'critical' => $critical_count,
            'review_status' => $review_status,
            'github_contacted' => false,
        ];
    }

    public function approve_snapshot_review( int $repository_id, int $snapshot_id ) {
        global $wpdb;
        $snapshot = $this->snapshot( $snapshot_id );
        if ( ! $snapshot || (int) $snapshot['repository_id'] !== $repository_id ) {
            return new WP_Error( 'yougitai_snapshot_not_found', __( 'Snapshot not found.', 'yougitai-secure-showcase' ) );
        }
        $open_critical = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . Schema::table( 'findings' ) . " WHERE snapshot_id=%d AND severity='critical' AND status='open'",
            $snapshot_id
        ) );
        if ( $open_critical > 0 ) {
            return new WP_Error( 'yougitai_review_blocked', __( 'Critical findings must be resolved or explicitly accepted before approval.', 'yougitai-secure-showcase' ) );
        }
        $wpdb->update( Schema::table( 'snapshots' ), [
            'review_status' => 'approved',
            'reviewed_at' => current_time( 'mysql' ),
        ], [ 'id' => $snapshot_id ] );
        $this->audit->log( 'snapshot_review_approved', __( 'Snapshot review approved.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id );
        return true;
    }

    public function publish_snapshot( int $repository_id, int $snapshot_id ) {
        global $wpdb;
        $snapshot = $this->snapshot( $snapshot_id );
        if ( ! $snapshot || (int) $snapshot['repository_id'] !== $repository_id ) {
            return new WP_Error( 'yougitai_snapshot_not_found', __( 'Snapshot not found.', 'yougitai-secure-showcase' ) );
        }
        if ( $snapshot['review_status'] !== 'approved' && $snapshot['review_status'] !== 'ready' ) {
            return new WP_Error( 'yougitai_publish_review_required', __( 'The snapshot must pass review before it can be published.', 'yougitai-secure-showcase' ) );
        }

        $verifier = new SnapshotVerifier();
        $verification = $verifier->verify( $snapshot_id );
        if ( ! $verification['ok'] ) {
            $this->audit->log( 'snapshot_verification_failed', __( 'Final snapshot verification failed.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id, [ 'issues' => $verification['issues'] ] );
            return new WP_Error( 'yougitai_publish_blocked', sprintf(
                __( 'Publishing was blocked by the final leak scan: %s', 'yougitai-secure-showcase' ),
                implode( ' | ', $verification['issues'] )
            ) );
        }

        $now = current_time( 'mysql' );
        $wpdb->update( Schema::table( 'snapshots' ), [
            'status' => 'published',
            'published_at' => $now,
            'verification_hash' => $verification['verification_hash'],
        ], [ 'id' => $snapshot_id ] );
        $wpdb->update( Schema::table( 'repositories' ), [
            'active_snapshot_id' => $snapshot_id,
            'status' => 'published',
            'updated_at' => $now,
        ], [ 'id' => $repository_id ] );
        $this->audit->log( 'snapshot_published', __( 'Verified snapshot published.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id, [ 'verification_hash' => $verification['verification_hash'] ] );
        return true;
    }

    public function findings_for_snapshot( int $snapshot_id ): array {
        return $this->findings->for_snapshot( $snapshot_id );
    }

    public function resolve_finding( int $repository_id, int $snapshot_id, int $finding_id, string $status ) {
        global $wpdb;
        $finding = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . Schema::table( 'findings' ) . ' WHERE id=%d AND repository_id=%d AND snapshot_id=%d',
            $finding_id, $repository_id, $snapshot_id
        ), ARRAY_A );
        if ( ! $finding ) {
            return new WP_Error( 'yougitai_finding_not_found', __( 'Finding not found.', 'yougitai-secure-showcase' ) );
        }
        if ( ! $this->findings->resolve( $finding_id, $status ) ) {
            return new WP_Error( 'yougitai_finding_update_failed', __( 'Finding could not be updated.', 'yougitai-secure-showcase' ) );
        }
        $this->audit->log( 'finding_' . $status, __( 'Security finding reviewed.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id, [ 'finding_id' => $finding_id ] );
        return true;
    }

    public function rules( int $repository_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Schema::table( 'rules' ) . ' WHERE repository_id=%d ORDER BY id ASC',
            $repository_id
        ), ARRAY_A ) ?: [];
    }

    public function add_ai_rule_proposal( int $repository_id, array $data ) {
        if ( ! $this->find( $repository_id ) ) {
            return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        }
        $type = sanitize_key( (string) ( $data['rule_type'] ?? '' ) );
        $action = sanitize_key( (string) ( $data['action'] ?? '' ) );
        if ( ! in_array( $type, [ 'path', 'string', 'regex' ], true ) || ! in_array( $action, [ 'hide', 'redact' ], true ) ) {
            return new WP_Error( 'yougitai_invalid_rule', __( 'Invalid protection rule.', 'yougitai-secure-showcase' ) );
        }
        $target = trim( (string) ( $data['target'] ?? '' ) );
        if ( $target === '' || ( $type === 'regex' && @preg_match( $target, '' ) === false ) ) {
            return new WP_Error( 'yougitai_invalid_rule', __( 'A protection proposal needs a valid target.', 'yougitai-secure-showcase' ) );
        }
        global $wpdb;
        $now = current_time( 'mysql' );
        $reason = sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) );
        $replacement = sanitize_textarea_field( (string) ( $data['replacement'] ?? '[PROTECTED]' ) );
        $payload = $replacement;
        if ( $reason !== '' ) {
            $payload .= "

[AI proposal reason] " . $reason;
        }
        $wpdb->insert( Schema::table( 'rules' ), [
            'repository_id' => $repository_id,
            'file_path' => sanitize_text_field( (string) ( $data['file_path'] ?? '' ) ),
            'rule_type' => $type,
            'target' => $type === 'regex' ? $target : sanitize_textarea_field( $target ),
            'action' => $action,
            'replacement' => $replacement,
            'notes' => $reason,
            'source' => 'connected_ai',
            'confidence' => null,
            'enabled' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ] );
        if ( ! $wpdb->insert_id ) {
            return new WP_Error( 'yougitai_rule_save_failed', __( 'Protection proposal could not be saved.', 'yougitai-secure-showcase' ) );
        }
        return (int) $wpdb->insert_id;
    }

    public function set_rule_enabled( int $repository_id, int $rule_id, bool $enabled ) {
        global $wpdb;
        $rule = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . Schema::table( 'rules' ) . ' WHERE id=%d AND repository_id=%d',
            $rule_id,
            $repository_id
        ), ARRAY_A );
        if ( ! $rule ) {
            return new WP_Error( 'yougitai_rule_not_found', __( 'Protection rule not found.', 'yougitai-secure-showcase' ) );
        }
        if ( $enabled && $rule['source'] === 'connected_ai' ) {
            $this->audit->log( 'connected_ai_redaction_approved', __( 'Connected AI redaction proposal approved by a WordPress administrator.', 'yougitai-secure-showcase' ), $repository_id, null, [ 'rule_id' => $rule_id ] );
        }
        $ok = $wpdb->update( Schema::table( 'rules' ), [ 'enabled' => $enabled ? 1 : 0, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $rule_id, 'repository_id' => $repository_id ] );
        return $ok !== false ? true : new WP_Error( 'yougitai_rule_update_failed', __( 'Protection rule could not be updated.', 'yougitai-secure-showcase' ) );
    }

    public function add_rule( int $repository_id, array $data ) {
        if ( ! $this->find( $repository_id ) ) {
            return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        }
        $type = sanitize_key( (string) ( $data['rule_type'] ?? 'path' ) );
        $action = sanitize_key( (string) ( $data['action'] ?? 'hide' ) );
        if ( ! in_array( $type, [ 'path', 'string', 'regex', 'line_range', 'symbol' ], true ) || ! in_array( $action, [ 'hide', 'redact' ], true ) ) {
            return new WP_Error( 'yougitai_invalid_rule', __( 'Invalid protection rule.', 'yougitai-secure-showcase' ) );
        }
        $target = trim( (string) ( $data['target'] ?? '' ) );
        if ( $target === '' ) {
            return new WP_Error( 'yougitai_invalid_rule', __( 'A protection rule needs a target.', 'yougitai-secure-showcase' ) );
        }
        if ( $type === 'line_range' ) {
            $decoded = json_decode( $target, true );
            if ( ! is_array( $decoded ) || empty( $decoded['start'] ) || empty( $decoded['end'] ) || empty( $decoded['hash'] ) || (int) $decoded['start'] > (int) $decoded['end'] ) {
                return new WP_Error( 'yougitai_invalid_line_rule', __( 'The line protection rule is invalid.', 'yougitai-secure-showcase' ) );
            }
        }
        if ( $type === 'regex' && @preg_match( $target, '' ) === false ) {
            return new WP_Error( 'yougitai_invalid_regex', __( 'The regular expression is invalid.', 'yougitai-secure-showcase' ) );
        }

        global $wpdb;
        $now = current_time( 'mysql' );
        $wpdb->insert( Schema::table( 'rules' ), [
            'repository_id' => $repository_id,
            'file_path' => sanitize_text_field( (string) ( $data['file_path'] ?? '' ) ),
            'rule_type' => $type,
            'target' => in_array( $type, [ 'regex', 'line_range', 'symbol' ], true ) ? $target : sanitize_textarea_field( $target ),
            'action' => $action,
            'replacement' => sanitize_textarea_field( (string) ( $data['replacement'] ?? '[PROTECTED]' ) ),
            'source' => 'manual',
            'confidence' => 100,
            'enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ] );
        if ( ! $wpdb->insert_id ) {
            return new WP_Error( 'yougitai_rule_save_failed', __( 'Protection rule could not be saved.', 'yougitai-secure-showcase' ) );
        }
        $this->audit->log( 'rule_created', __( 'Protection rule created.', 'yougitai-secure-showcase' ), $repository_id, null, [ 'rule_id' => (int) $wpdb->insert_id ] );
        return (int) $wpdb->insert_id;
    }


    public function admin_file( int $repository_id, int $snapshot_id, int $file_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT id,path,filename,language,content,visibility,risk_score,source_sha FROM ' . Schema::table( 'files' ) . ' WHERE id=%d AND repository_id=%d AND snapshot_id=%d',
            $file_id, $repository_id, $snapshot_id
        ), ARRAY_A );
        return $row ?: null;
    }

    public function add_line_rule_from_snapshot( int $repository_id, int $snapshot_id, int $file_id, int $start, int $end, string $replacement ) {
        $file = $this->admin_file( $repository_id, $snapshot_id, $file_id );
        if ( ! $file || $file['visibility'] === 'hidden' || $file['content'] === '' ) {
            return new WP_Error( 'yougitai_file_unavailable', __( 'This file cannot be used for a line protection rule.', 'yougitai-secure-showcase' ) );
        }
        $lines = preg_split( '/\R/', (string) $file['content'] );
        $count = is_array( $lines ) ? count( $lines ) : 0;
        if ( $start < 1 || $end < $start || $end > $count ) {
            return new WP_Error( 'yougitai_invalid_line_range', __( 'Please enter a valid line range.', 'yougitai-secure-showcase' ) );
        }
        $segment = implode( "\n", array_slice( $lines, $start - 1, $end - $start + 1 ) );
        $target = wp_json_encode( [
            'start' => $start,
            'end' => $end,
            'hash' => hash( 'sha256', $segment ),
            'source_sha' => (string) $file['source_sha'],
        ] );
        return $this->add_rule( $repository_id, [
            'rule_type' => 'line_range',
            'file_path' => (string) $file['path'],
            'target' => $target,
            'action' => 'redact',
            'replacement' => $replacement !== '' ? $replacement : '[PROTECTED]',
        ] );
    }

    public function add_symbol_rule_from_snapshot( int $repository_id, int $snapshot_id, int $file_id, string $symbol, string $replacement ) {
        $file = $this->admin_file( $repository_id, $snapshot_id, $file_id );
        if ( ! $file || $file['visibility'] === 'hidden' || $file['content'] === '' ) {
            return new WP_Error( 'yougitai_file_unavailable', __( 'This file cannot be used for a symbol protection rule.', 'yougitai-secure-showcase' ) );
        }
        $locator = new SymbolLocator();
        $located = $locator->locate( (string) $file['path'], (string) $file['content'], $symbol );
        if ( ! $located ) {
            return new WP_Error( 'yougitai_symbol_not_unique', __( 'The symbol could not be located uniquely in this file.', 'yougitai-secure-showcase' ) );
        }
        $target = wp_json_encode( [
            'symbol' => trim( $symbol ),
            'kind' => $located['kind'],
            'signature_hash' => hash( 'sha256', $located['signature'] ),
            'source_sha' => (string) $file['source_sha'],
        ] );
        return $this->add_rule( $repository_id, [
            'rule_type' => 'symbol',
            'file_path' => (string) $file['path'],
            'target' => $target,
            'action' => 'redact',
            'replacement' => $replacement !== '' ? $replacement : '[PROTECTED SYMBOL]',
        ] );
    }

    public function reactivate_published_snapshot( int $repository_id, int $snapshot_id ) {
        global $wpdb;
        $snapshot = $this->snapshot( $snapshot_id );
        if ( ! $snapshot || (int) $snapshot['repository_id'] !== $repository_id ) {
            return new WP_Error( 'yougitai_snapshot_not_found', __( 'Snapshot not found.', 'yougitai-secure-showcase' ) );
        }
        if ( $snapshot['status'] !== 'published' || empty( $snapshot['verification_hash'] ) ) {
            return new WP_Error( 'yougitai_snapshot_not_verified', __( 'Only previously published and verified snapshots can be restored.', 'yougitai-secure-showcase' ) );
        }
        $verifier = new SnapshotVerifier();
        $verification = $verifier->verify( $snapshot_id );
        if ( ! $verification['ok'] ) {
            $this->audit->log( 'snapshot_rollback_blocked', __( 'Snapshot restore was blocked by verification.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id, [ 'issues' => $verification['issues'] ] );
            return new WP_Error( 'yougitai_snapshot_restore_blocked', sprintf(
                __( 'Restore was blocked by the final leak scan: %s', 'yougitai-secure-showcase' ),
                implode( ' | ', $verification['issues'] )
            ) );
        }
        if ( ! hash_equals( (string) $snapshot['verification_hash'], (string) $verification['verification_hash'] ) ) {
            $this->audit->log( 'snapshot_rollback_hash_mismatch', __( 'Snapshot restore was blocked because its verification hash changed.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id );
            return new WP_Error( 'yougitai_snapshot_hash_changed', __( 'Restore was blocked because the stored public snapshot has changed since verification.', 'yougitai-secure-showcase' ) );
        }
        $ok = $wpdb->update( Schema::table( 'repositories' ), [
            'active_snapshot_id' => $snapshot_id,
            'status' => 'published',
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $repository_id ] );
        if ( $ok === false ) {
            return new WP_Error( 'yougitai_snapshot_restore_failed', __( 'The snapshot could not be restored.', 'yougitai-secure-showcase' ) );
        }
        $this->audit->log( 'snapshot_reactivated', __( 'Previously verified snapshot restored.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id, [ 'verification_hash' => $verification['verification_hash'] ] );
        return true;
    }


    public function set_publication_state( int $repository_id, string $status, string $access_mode = 'public', string $password = '' ) {
        $repository = $this->find( $repository_id );
        if ( ! $repository ) {
            return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        }
        $allowed_status = [ 'draft', 'published' ];
        $allowed_access = [ 'public', 'private', 'password' ];
        if ( ! in_array( $status, $allowed_status, true ) || ! in_array( $access_mode, $allowed_access, true ) ) {
            return new WP_Error( 'yougitai_invalid_publication_state', __( 'Invalid publication state.', 'yougitai-secure-showcase' ) );
        }
        if ( $status === 'published' && empty( $repository['active_snapshot_id'] ) ) {
            return new WP_Error( 'yougitai_no_active_snapshot', __( 'Publish a verified snapshot before changing the public access mode.', 'yougitai-secure-showcase' ) );
        }
        $data = [ 'status' => $status, 'access_mode' => $access_mode, 'updated_at' => current_time( 'mysql' ) ];
        if ( $access_mode === 'password' ) {
            if ( $password !== '' ) {
                $data['access_password_hash'] = wp_hash_password( $password );
            } elseif ( empty( $repository['access_password_hash'] ) ) {
                return new WP_Error( 'yougitai_password_required', __( 'Enter a password for password-protected access.', 'yougitai-secure-showcase' ) );
            }
        } else {
            $data['access_password_hash'] = null;
        }
        global $wpdb;
        if ( $wpdb->update( Schema::table( 'repositories' ), $data, [ 'id' => $repository_id ] ) === false ) {
            return new WP_Error( 'yougitai_publication_state_failed', __( 'The showcase visibility could not be updated.', 'yougitai-secure-showcase' ) );
        }
        $this->audit->log( 'showcase_access_changed', __( 'Showcase publication state changed.', 'yougitai-secure-showcase' ), $repository_id, $repository['active_snapshot_id'] ? (int) $repository['active_snapshot_id'] : null, [ 'status' => $status, 'access_mode' => $access_mode ] );
        return true;
    }

    public function set_sync_mode( int $repository_id, string $mode ): bool {
        if ( ! in_array( $mode, [ 'manual', 'webhook', 'hourly', 'daily' ], true ) ) {
            $mode = 'manual';
        }
        global $wpdb;
        return $wpdb->update( Schema::table( 'repositories' ), [ 'sync_mode' => $mode, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $repository_id ] ) !== false;
    }

    public function set_show_original( int $repository_id, bool $show_original ): bool {
        if ( ! $this->find( $repository_id ) ) {
            return false;
        }
        global $wpdb;
        $ok = $wpdb->update( Schema::table( 'repositories' ), [
            'show_original' => $show_original ? 1 : 0,
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $repository_id ] );
        if ( $ok !== false ) {
            $this->audit->log(
                $show_original ? 'original_mode_enabled' : 'secure_mode_enabled',
                $show_original ? __( 'Original source mode enabled.', 'yougitai-secure-showcase' ) : __( 'Secure showcase mode enabled.', 'yougitai-secure-showcase' ),
                $repository_id
            );
        }
        return $ok !== false;
    }

    public function regenerate_webhook_secret( int $repository_id ) {
        if ( ! $this->find( $repository_id ) ) {
            return new WP_Error( 'yougitai_not_found', __( 'Repository not found.', 'yougitai-secure-showcase' ) );
        }
        $secret = SecretVault::random_secret();
        $encrypted = SecretVault::encrypt( $secret );
        if ( $encrypted === '' ) {
            return new WP_Error( 'yougitai_secret_storage_failed', __( 'The webhook secret could not be encrypted on this server.', 'yougitai-secure-showcase' ) );
        }
        global $wpdb;
        $ok = $wpdb->update( Schema::table( 'repositories' ), [
            'webhook_secret' => $encrypted,
            'sync_mode' => 'webhook',
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $repository_id ] );
        if ( $ok === false ) {
            return new WP_Error( 'yougitai_webhook_save_failed', __( 'Webhook settings could not be saved.', 'yougitai-secure-showcase' ) );
        }
        $this->audit->log( 'webhook_secret_rotated', __( 'GitHub webhook secret rotated.', 'yougitai-secure-showcase' ), $repository_id );
        return $secret;
    }

    public function webhook_configured( array $repository ): bool {
        return ! empty( $repository['webhook_secret'] );
    }

    public function files_for_snapshot( int $repository_id, int $snapshot_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT id,path,filename,extension,language,size,visibility,risk_score,redaction_manifest FROM ' . Schema::table( 'files' ) . ' WHERE repository_id=%d AND snapshot_id=%d ORDER BY path ASC',
            $repository_id, $snapshot_id
        ), ARRAY_A ) ?: [];
    }

    public function files_for_public_repository( int $repository_id, int $snapshot_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT f.id,f.path,f.filename,f.extension,f.language,f.size,f.visibility,f.risk_score,f.redaction_manifest,(SELECT COUNT(*) FROM ' . Schema::table( 'findings' ) . ' x WHERE x.file_id=f.id AND x.snapshot_id=f.snapshot_id AND x.recommendation IN (\'redact\',\'hide\')) AS protection_finding_count FROM ' . Schema::table( 'files' ) . ' f WHERE f.repository_id=%d AND f.snapshot_id=%d ORDER BY f.path ASC',
            $repository_id,
            $snapshot_id
        ), ARRAY_A ) ?: [];
    }

    public function public_showcase_profile( int $repository_id, int $snapshot_id ): array {
        global $wpdb;
        $repository = $this->find( $repository_id );
        if ( ! $repository ) return [];
        $files = $wpdb->get_results( $wpdb->prepare(
            'SELECT id,path,filename,extension,language,size,visibility,risk_score,redaction_manifest FROM ' . Schema::table( 'files' ) . ' WHERE repository_id=%d AND snapshot_id=%d ORDER BY path ASC',
            $repository_id, $snapshot_id
        ), ARRAY_A ) ?: [];
        return ( new ShowcaseAnalyzer() )->analyze( $repository, $files );
    }

    public function public_readme( int $repository_id, int $snapshot_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id,path,filename,language,content,visibility,redaction_manifest FROM " . Schema::table( 'files' ) . " WHERE repository_id=%d AND snapshot_id=%d AND visibility != %s AND (LOWER(filename)='readme.md' OR LOWER(filename)='readme') ORDER BY CHAR_LENGTH(path) ASC LIMIT 1",
            $repository_id, $snapshot_id, 'hidden'
        ), ARRAY_A );
        return $row ?: null;
    }

    public function public_file( int $repository_id, int $snapshot_id, int $file_id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id,path,filename,language,CASE WHEN visibility='hidden' THEN '' ELSE content END AS content,visibility,redaction_manifest FROM " . Schema::table( 'files' ) . ' WHERE id=%d AND repository_id=%d AND snapshot_id=%d',
            $file_id, $repository_id, $snapshot_id
        ), ARRAY_A );
        return $row ?: null;
    }

    private function insert_public_file( int $repository_id, int $snapshot_id, string $path, int $size, array $node, array $public, string $now ): int {
        global $wpdb;
        $filename = basename( $path );
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $content = (string) ( $public['content'] ?? '' );
        $wpdb->insert( Schema::table( 'files' ), [
            'repository_id' => $repository_id,
            'snapshot_id' => $snapshot_id,
            'path' => $path,
            'path_hash' => hash( 'sha256', $path ),
            'filename' => $filename,
            'extension' => $extension,
            'language' => $this->language_for_extension( $extension ),
            'size' => strlen( $content ),
            'source_sha' => sanitize_text_field( $node['sha'] ?? '' ),
            'visibility' => $public['visibility'],
            'risk_score' => min( 100, max( 0, (int) $public['risk_score'] ) ),
            'content' => $content,
            'redaction_manifest' => wp_json_encode( array_values( is_array( $public['redactions'] ?? null ) ? $public['redactions'] : [] ) ),
            'content_hash' => hash( 'sha256', $content ),
            'created_at' => $now,
            'updated_at' => $now,
        ] );
        return (int) $wpdb->insert_id;
    }

    private function should_ai_review( string $path, string $content, string $profile ): bool {
        if ( strlen( $content ) < 80 ) {
            return false;
        }
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        $code_extensions = [ 'php', 'js', 'jsx', 'ts', 'tsx', 'py', 'java', 'kt', 'swift', 'dart', 'go', 'rb', 'rs', 'cs', 'sql', 'md', 'json', 'yml', 'yaml' ];
        if ( ! in_array( $extension, $code_extensions, true ) ) {
            return false;
        }
        if ( $profile === 'maximum' || $profile === 'investor' ) {
            return true;
        }
        return (bool) preg_match( '#(^|/)(ai|ml|payment|billing|auth|security|service|engine|core|api|config|database|models?)(/|\.|$)#i', $path );
    }

    private function parse_github_url( string $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        $path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
        if ( ! in_array( strtolower( (string) $host ), [ 'github.com', 'www.github.com' ], true ) ) {
            return new WP_Error( 'yougitai_invalid_url', __( 'Please enter a valid GitHub repository URL.', 'yougitai-secure-showcase' ) );
        }
        $parts = explode( '/', preg_replace( '/\.git$/', '', $path ) );
        if ( count( $parts ) < 2 || ! $parts[0] || ! $parts[1] ) {
            return new WP_Error( 'yougitai_invalid_url', __( 'Please enter a valid GitHub repository URL.', 'yougitai-secure-showcase' ) );
        }
        return [ sanitize_text_field( $parts[0] ), sanitize_text_field( $parts[1] ) ];
    }

    private function ignored_path( string $path ): bool {
        return (bool) preg_match( '#(^|/)(node_modules|vendor|dist|build|coverage|\.git|\.next|\.cache)(/|$)#i', $path );
    }

    private function looks_binary( string $content ): bool {
        return strpos( substr( $content, 0, 8000 ), "\0" ) !== false;
    }

    private function language_for_extension( string $ext ): string {
        $map = [ 'php'=>'PHP','js'=>'JavaScript','jsx'=>'JavaScript','ts'=>'TypeScript','tsx'=>'TypeScript','html'=>'HTML','css'=>'CSS','scss'=>'SCSS','json'=>'JSON','yml'=>'YAML','yaml'=>'YAML','md'=>'Markdown','py'=>'Python','java'=>'Java','kt'=>'Kotlin','swift'=>'Swift','sql'=>'SQL','sh'=>'Shell','dart'=>'Dart','xml'=>'XML','go'=>'Go','rb'=>'Ruby','rs'=>'Rust','cs'=>'C#' ];
        return $map[ $ext ] ?? strtoupper( $ext ?: 'text' );
    }
}
