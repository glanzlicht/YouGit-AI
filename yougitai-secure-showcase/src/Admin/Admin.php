<?php
namespace YougitAI\SecureShowcase\Admin;

use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Audit\Logger;
use YougitAI\SecureShowcase\Security\SecretVault;
use YougitAI\SecureShowcase\Security\ConnectionTokenService;
use YougitAI\SecureShowcase\Sync\SyncManager;
use YougitAI\SecureShowcase\GitHub\OAuth as GitHubOAuth;
use YougitAI\SecureShowcase\GitHub\Client as GitHubClient;
use YougitAI\SecureShowcase\Support\SystemStatus;
use YougitAI\SecureShowcase\AI\ProviderFactory;

final class Admin {
    public function __construct( private RepositoryService $repositories, private SyncManager $sync ) {}

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_post_yougitai_ss_add_repository', [ $this, 'add_repository' ] );
        add_action( 'admin_post_yougitai_ss_import_selected_repositories', [ $this, 'import_selected_repositories' ] );
        add_action( 'admin_post_yougitai_ss_refresh_github_repositories', [ $this, 'refresh_github_repositories' ] );
        add_action( 'admin_post_yougitai_ss_import_snapshot', [ $this, 'import_snapshot' ] );
        add_action( 'admin_post_yougitai_ss_apply_protection_rules', [ $this, 'apply_protection_rules' ] );
        add_action( 'admin_post_yougitai_ss_approve_snapshot', [ $this, 'approve_snapshot' ] );
        add_action( 'admin_post_yougitai_ss_publish_snapshot', [ $this, 'publish_snapshot' ] );
        add_action( 'admin_post_yougitai_ss_set_publication_state', [ $this, 'set_publication_state' ] );
        add_action( 'admin_post_yougitai_ss_resolve_finding', [ $this, 'resolve_finding' ] );
        add_action( 'admin_post_yougitai_ss_bulk_findings', [ $this, 'bulk_findings' ] );
        add_action( 'admin_post_yougitai_ss_bulk_files', [ $this, 'bulk_files' ] );
        add_action( 'admin_post_yougitai_ss_ai_protect_snapshot', [ $this, 'ai_protect_snapshot' ] );
        add_action( 'admin_post_yougitai_ss_add_rule', [ $this, 'add_rule' ] );
        add_action( 'admin_post_yougitai_ss_add_line_rule', [ $this, 'add_line_rule' ] );
        add_action( 'admin_post_yougitai_ss_add_symbol_rule', [ $this, 'add_symbol_rule' ] );
        add_action( 'admin_post_yougitai_ss_restore_snapshot', [ $this, 'restore_snapshot' ] );
        add_action( 'admin_post_yougitai_ss_queue_sync', [ $this, 'queue_sync' ] );
        add_action( 'admin_post_yougitai_ss_rotate_webhook', [ $this, 'rotate_webhook' ] );
        add_action( 'admin_post_yougitai_ss_set_sync_mode', [ $this, 'set_sync_mode' ] );
        add_action( 'admin_post_yougitai_ss_set_display_mode', [ $this, 'set_display_mode' ] );
        add_action( 'admin_post_yougitai_ss_save_settings', [ $this, 'save_settings' ] );
        add_action( 'admin_post_yougitai_ss_create_connection', [ $this, 'create_connection' ] );
        add_action( 'admin_post_yougitai_ss_revoke_connection', [ $this, 'revoke_connection' ] );
        add_action( 'admin_post_yougitai_ss_revoke_oauth_connection', [ $this, 'revoke_oauth_connection' ] );
        add_action( 'admin_post_yougitai_ss_toggle_rule', [ $this, 'toggle_rule' ] );
        add_action( 'admin_post_yougitai_ss_bulk_approve_rules', [ $this, 'bulk_approve_rules' ] );
        add_action( 'admin_post_yougitai_ss_github_oauth_start', [ $this, 'github_oauth_start' ] );
        add_action( 'admin_post_yougitai_ss_github_oauth_callback', [ $this, 'github_oauth_callback' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function menu(): void {
        add_menu_page(
            __( 'YougitAI Showcases', 'yougitai-secure-showcase' ),
            __( 'YougitAI', 'yougitai-secure-showcase' ),
            'manage_options',
            'yougitai-secure-showcase',
            [ $this, 'screen' ],
            'dashicons-lock',
            58
        );
        add_submenu_page(
            'yougitai-secure-showcase',
            __( 'Repositories', 'yougitai-secure-showcase' ),
            __( 'Repositories', 'yougitai-secure-showcase' ),
            'manage_options',
            'yougitai-secure-showcase',
            [ $this, 'screen' ]
        );
        add_submenu_page(
            'yougitai-secure-showcase',
            __( 'Settings', 'yougitai-secure-showcase' ),
            __( 'Settings', 'yougitai-secure-showcase' ),
            'manage_options',
            'yougitai-secure-showcase-settings',
            [ $this, 'settings_screen' ]
        );
    }

    public function assets( string $hook ): void {
        if ( ! str_contains( $hook, 'yougitai-secure-showcase' ) ) {
            return;
        }
        wp_enqueue_style( 'yougitai-ss-admin', YOUGITAI_SS_URL . 'assets/css/admin.css', [], YOUGITAI_SS_VERSION );
        wp_enqueue_script( 'yougitai-ss-admin', YOUGITAI_SS_URL . 'assets/js/admin.js', [], YOUGITAI_SS_VERSION, true );
    }

    public function screen(): void {
        $this->guard();
        $repository_id = isset( $_GET['repository_id'] ) ? absint( $_GET['repository_id'] ) : 0;
        $message = isset( $_GET['yougitai_message'] ) ? sanitize_text_field( wp_unslash( $_GET['yougitai_message'] ) ) : '';
        $error = isset( $_GET['yougitai_error'] ) ? sanitize_text_field( wp_unslash( $_GET['yougitai_error'] ) ) : '';

        if ( $repository_id ) {
            $repository = $this->repositories->find( $repository_id );
            if ( ! $repository ) {
                wp_die( esc_html__( 'Repository not found.', 'yougitai-secure-showcase' ) );
            }
            $snapshots = $this->repositories->snapshots( $repository_id );
            $rules = $this->repositories->rules( $repository_id );
            $selected_snapshot_id = isset( $_GET['snapshot_id'] ) ? absint( $_GET['snapshot_id'] ) : (int) ( $snapshots[0]['id'] ?? 0 );
            $selected_snapshot = $selected_snapshot_id ? $this->repositories->snapshot( $selected_snapshot_id ) : null;
            $findings = $selected_snapshot_id ? $this->repositories->findings_for_snapshot( $selected_snapshot_id ) : [];
            $files = $selected_snapshot_id ? $this->repositories->files_for_snapshot( $repository_id, $selected_snapshot_id ) : [];
            $selected_file_id = isset( $_GET['file_id'] ) ? absint( $_GET['file_id'] ) : 0;
            $selected_file = ( $selected_snapshot_id && $selected_file_id ) ? $this->repositories->admin_file( $repository_id, $selected_snapshot_id, $selected_file_id ) : null;
            $audit_events = ( new Logger() )->recent_for_repository( $repository_id, 30 );
            $webhook_url = rest_url( 'yougitai-secure-showcase/v1/github/webhook/' . $repository_id );
            $ai_provider = sanitize_key( (string) get_option( 'yougitai_ss_ai_provider', 'openai' ) );
            $ai_external_enabled = (bool) get_option( 'yougitai_ss_external_ai_enabled', false );
            $ai_runtime = ProviderFactory::make();
            $ai_configured = $ai_runtime->is_configured();
            $webhook_secret_once = get_transient( 'yougitai_ss_webhook_secret_' . get_current_user_id() . '_' . $repository_id );
            if ( $webhook_secret_once ) {
                delete_transient( 'yougitai_ss_webhook_secret_' . get_current_user_id() . '_' . $repository_id );
            }
            include YOUGITAI_SS_DIR . 'templates/admin-repository.php';
            return;
        }

        $repositories = $this->repositories->all();
        $github_repositories = [];
        $github_repository_error = '';
        $github_connected = SecretVault::decrypt( (string) get_option( 'yougitai_ss_github_token', '' ) ) !== '';
        if ( $github_connected ) {
            $github_repositories_result = ( new GitHubClient() )->list_repositories();
            if ( is_wp_error( $github_repositories_result ) ) {
                $github_repository_error = $github_repositories_result->get_error_message();
            } else {
                $github_repositories = $github_repositories_result;
            }
        }
        $imported_github_repositories = [];
        foreach ( $repositories as $existing_repository ) {
            $imported_github_repositories[ strtolower( (string) $existing_repository['owner'] . '/' . (string) $existing_repository['repo'] ) ] = (int) $existing_repository['id'];
        }
        include YOUGITAI_SS_DIR . 'templates/admin-repositories.php';
    }

    public function settings_screen(): void {
        $this->guard();
        $message = isset( $_GET['yougitai_message'] ) ? sanitize_text_field( wp_unslash( $_GET['yougitai_message'] ) ) : '';
        $error = isset( $_GET['yougitai_error'] ) ? sanitize_text_field( wp_unslash( $_GET['yougitai_error'] ) ) : '';
        $connection_tokens = new ConnectionTokenService();
        $connections = $connection_tokens->active();
        $oauth_connections = $connection_tokens->active_oauth();
        $connection_token_once = get_transient( 'yougitai_ss_connection_token_' . get_current_user_id() );
        if ( $connection_token_once ) { delete_transient( 'yougitai_ss_connection_token_' . get_current_user_id() ); }
        $connected_ai_mcp_url = rest_url( 'yougitai-secure-showcase/v1/connected-ai/mcp' );
        $available_repositories = $this->repositories->all();
        $system_checks = ( new SystemStatus() )->checks();
        $settings = [
            'showcase_slug' => (string) get_option( 'yougitai_ss_showcase_slug', 'repositories' ),
            'index_title_de' => (string) get_option( 'yougitai_ss_index_title_de', '' ),
            'index_description_de' => (string) get_option( 'yougitai_ss_index_description_de', '' ),
            'index_title_en' => (string) get_option( 'yougitai_ss_index_title_en', '' ),
            'index_description_en' => (string) get_option( 'yougitai_ss_index_description_en', '' ),
            'external_ai_enabled' => (bool) get_option( 'yougitai_ss_external_ai_enabled', false ),
            'ai_provider' => (string) get_option( 'yougitai_ss_ai_provider', 'openai' ),
            'openai_model' => (string) get_option( 'yougitai_ss_openai_model', 'gpt-5.6-luna' ),
            'anthropic_model' => (string) get_option( 'yougitai_ss_anthropic_model', 'claude-sonnet-5' ),
            'gemini_model' => (string) get_option( 'yougitai_ss_gemini_model', 'gemini-3.8-flash' ),
            'has_openai_key' => $this->stored_secret_present( 'yougitai_ss_openai_api_key' ),
            'has_anthropic_key' => $this->stored_secret_present( 'yougitai_ss_anthropic_api_key' ),
            'has_gemini_key' => $this->stored_secret_present( 'yougitai_ss_gemini_api_key' ),
            'has_github_token' => SecretVault::decrypt( (string) get_option( 'yougitai_ss_github_token', '' ) ) !== '',
            'github_auth_method' => (string) get_option( 'yougitai_ss_github_auth_method', 'token' ),
            'github_oauth_client_id' => (string) get_option( 'yougitai_ss_github_oauth_client_id', '' ),
            'has_github_oauth_secret' => $this->stored_secret_present( 'yougitai_ss_github_oauth_client_secret' ),
            'github_oauth_callback' => ( new GitHubOAuth() )->callback_url(),
            'delete_data_on_uninstall' => (bool) get_option( 'yougitai_ss_delete_data_on_uninstall', false ),
            'color_surface' => (string) get_option( 'yougitai_ss_color_surface', '#0d1117' ),
            'color_panel' => (string) get_option( 'yougitai_ss_color_panel', '#161b22' ),
            'color_code' => (string) get_option( 'yougitai_ss_color_code', '#0d1117' ),
            'color_border' => (string) get_option( 'yougitai_ss_color_border', '#30363d' ),
            'color_text' => (string) get_option( 'yougitai_ss_color_text', '#c9d1d9' ),
            'color_muted' => (string) get_option( 'yougitai_ss_color_muted', '#8b949e' ),
            'color_accent' => (string) get_option( 'yougitai_ss_color_accent', '#58a6ff' ),
            'color_accent_hover' => (string) get_option( 'yougitai_ss_color_accent_hover', '#79c0ff' ),
            'color_selected' => (string) get_option( 'yougitai_ss_color_selected', '#1f2d3d' ),
        ];
        include YOUGITAI_SS_DIR . 'templates/admin-settings.php';
    }

    public function add_repository(): void {
        $this->guard();
        check_admin_referer( 'yougitai_ss_add_repository' );
        $url = isset( $_POST['github_url'] ) ? esc_url_raw( wp_unslash( $_POST['github_url'] ) ) : '';
        $profile = isset( $_POST['protection_profile'] ) ? sanitize_key( wp_unslash( $_POST['protection_profile'] ) ) : 'balanced';
        $result = $this->repositories->create_from_github_url( $url, $profile );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message() );
        }
        $this->redirect_repository( (int) $result, __( 'Repository added.', 'yougitai-secure-showcase' ) );
    }

    public function import_selected_repositories(): void {
        $this->guard();
        check_admin_referer( 'yougitai_ss_import_selected_repositories' );
        $selected_ids = isset( $_POST['github_repository_ids'] ) && is_array( $_POST['github_repository_ids'] )
            ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['github_repository_ids'] ) ) ) ) )
            : [];
        if ( empty( $selected_ids ) ) {
            $this->redirect_error( __( 'Select at least one GitHub repository to import.', 'yougitai-secure-showcase' ) );
        }
        $profile = isset( $_POST['protection_profile'] ) ? sanitize_key( wp_unslash( $_POST['protection_profile'] ) ) : 'balanced';
        $client = new GitHubClient();
        $available = $client->list_repositories();
        if ( is_wp_error( $available ) ) {
            $this->redirect_error( $available->get_error_message() );
        }
        $available_by_id = [];
        foreach ( $available as $repository ) {
            $available_by_id[ (int) $repository['id'] ] = $repository;
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $existing = [];
        foreach ( $this->repositories->all() as $repository ) {
            $existing[ strtolower( (string) $repository['owner'] . '/' . (string) $repository['repo'] ) ] = true;
        }

        foreach ( $selected_ids as $github_id ) {
            if ( empty( $available_by_id[ $github_id ] ) ) {
                $errors[] = __( 'A selected repository is no longer available through the connected GitHub account.', 'yougitai-secure-showcase' );
                continue;
            }
            $repository = $available_by_id[ $github_id ];
            $key = strtolower( (string) $repository['full_name'] );
            if ( isset( $existing[ $key ] ) ) {
                $skipped++;
                continue;
            }
            $result = $this->repositories->create_from_github_url( (string) $repository['html_url'], $profile );
            if ( is_wp_error( $result ) ) {
                $errors[] = sprintf( '%s: %s', (string) $repository['full_name'], $result->get_error_message() );
                continue;
            }
            $existing[ $key ] = true;
            $imported++;
        }

        if ( $imported === 0 && ! empty( $errors ) ) {
            $this->redirect_error( implode( ' | ', array_slice( $errors, 0, 3 ) ) );
        }
        $message = sprintf(
            __( '%1$d GitHub repositories imported. %2$d already existed and were skipped.', 'yougitai-secure-showcase' ),
            $imported,
            $skipped
        );
        if ( ! empty( $errors ) ) {
            $message .= ' ' . sprintf( __( '%d repositories could not be imported.', 'yougitai-secure-showcase' ), count( $errors ) );
        }
        wp_safe_redirect( add_query_arg( [ 'page' => 'yougitai-secure-showcase', 'yougitai_message' => rawurlencode( $message ) ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function refresh_github_repositories(): void {
        $this->guard();
        check_admin_referer( 'yougitai_ss_refresh_github_repositories' );
        ( new GitHubClient() )->clear_repository_cache();
        wp_safe_redirect( add_query_arg( [ 'page' => 'yougitai-secure-showcase', 'yougitai_message' => rawurlencode( __( 'GitHub repository list refreshed.', 'yougitai-secure-showcase' ) ) ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function import_snapshot(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_import_snapshot_' . $repository_id );
        $result = $this->repositories->import_snapshot( $repository_id );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id );
        }
        $message = sprintf(
            __( 'Snapshot imported: %1$d files processed, %2$d findings detected.', 'yougitai-secure-showcase' ),
            (int) $result['files'],
            (int) $result['findings']
        );
        $this->redirect_repository( $repository_id, $message, (int) $result['snapshot_id'] );
    }

    public function apply_protection_rules(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $base_snapshot_id = isset( $_POST['base_snapshot_id'] ) ? absint( $_POST['base_snapshot_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_apply_protection_rules_' . $repository_id );
        $result = $this->repositories->rebuild_protected_snapshot( $repository_id, $base_snapshot_id );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id, $base_snapshot_id );
        }
        $this->redirect_repository(
            $repository_id,
            __( 'Protection applied. A new review draft was created from the existing safe snapshot; GitHub was not contacted.', 'yougitai-secure-showcase' ),
            (int) $result['snapshot_id']
        );
    }

    public function approve_snapshot(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_approve_snapshot_' . $snapshot_id );
        $result = $this->repositories->approve_snapshot_review( $repository_id, $snapshot_id );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id, $snapshot_id );
        }
        $this->redirect_repository( $repository_id, __( 'Snapshot review approved.', 'yougitai-secure-showcase' ), $snapshot_id );
    }

    public function publish_snapshot(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_publish_snapshot_' . $snapshot_id );
        $snapshot = $this->repositories->snapshot( $snapshot_id );
        if ( $snapshot && ! in_array( (string) $snapshot['review_status'], [ 'approved', 'ready' ], true ) ) {
            $approval = $this->repositories->approve_snapshot_review( $repository_id, $snapshot_id );
            if ( is_wp_error( $approval ) ) {
                $this->redirect_error( $approval->get_error_message(), $repository_id, $snapshot_id );
            }
        }
        $result = $this->repositories->publish_snapshot( $repository_id, $snapshot_id );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id, $snapshot_id );
        }
        $this->redirect_repository( $repository_id, __( 'Verified snapshot published.', 'yougitai-secure-showcase' ), $snapshot_id );
    }

    public function set_publication_state(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_set_publication_state_' . $repository_id );
        $status = isset( $_POST['publication_status'] ) ? sanitize_key( wp_unslash( $_POST['publication_status'] ) ) : 'draft';
        $access_mode = isset( $_POST['access_mode'] ) ? sanitize_key( wp_unslash( $_POST['access_mode'] ) ) : 'public';
        $password = isset( $_POST['access_password'] ) ? (string) wp_unslash( $_POST['access_password'] ) : '';
        $result = $this->repositories->set_publication_state( $repository_id, $status, $access_mode, $password );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id );
        }
        $this->redirect_repository( $repository_id, __( 'Showcase visibility updated.', 'yougitai-secure-showcase' ) );
    }

    public function resolve_finding(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        $finding_id = isset( $_POST['finding_id'] ) ? absint( $_POST['finding_id'] ) : 0;
        $status = isset( $_POST['finding_status'] ) ? sanitize_key( wp_unslash( $_POST['finding_status'] ) ) : 'resolved';
        check_admin_referer( 'yougitai_ss_finding_' . $finding_id );
        $result = $this->repositories->resolve_finding( $repository_id, $snapshot_id, $finding_id, $status );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id, $snapshot_id );
        }
        $this->redirect_repository( $repository_id, __( 'Finding updated.', 'yougitai-secure-showcase' ), $snapshot_id );
    }

    public function bulk_findings(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_bulk_findings_' . $snapshot_id );
        $status = isset( $_POST['finding_status'] ) ? sanitize_key( wp_unslash( $_POST['finding_status'] ) ) : 'resolved';
        if ( ! in_array( $status, [ 'resolved', 'accepted', 'ignored' ], true ) ) {
            $status = 'resolved';
        }
        $groups = isset( $_POST['finding_groups'] ) && is_array( $_POST['finding_groups'] ) ? wp_unslash( $_POST['finding_groups'] ) : [];
        $ids = [];
        foreach ( $groups as $group ) {
            foreach ( explode( ',', (string) $group ) as $id ) {
                $id = absint( $id );
                if ( $id ) $ids[ $id ] = $id;
            }
        }
        if ( empty( $ids ) ) {
            $this->redirect_error( __( 'Select at least one finding group first.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id );
        }
        $updated = 0;
        foreach ( $ids as $finding_id ) {
            $result = $this->repositories->resolve_finding( $repository_id, $snapshot_id, $finding_id, $status );
            if ( ! is_wp_error( $result ) ) $updated++;
        }
        $this->redirect_repository( $repository_id, sprintf( __( '%d findings updated.', 'yougitai-secure-showcase' ), $updated ), $snapshot_id );
    }

    public function bulk_files(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_bulk_files_' . $snapshot_id );
        $file_ids = isset( $_POST['file_ids'] ) && is_array( $_POST['file_ids'] ) ? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['file_ids'] ) ) ) ) : [];
        if ( empty( $file_ids ) ) {
            $this->redirect_error( __( 'Select at least one file first.', 'yougitai-secure-showcase' ), $repository_id, $snapshot_id );
        }
        $added = 0;
        foreach ( $file_ids as $file_id ) {
            $file = $this->repositories->admin_file( $repository_id, $snapshot_id, $file_id );
            if ( ! $file ) continue;
            $result = $this->repositories->add_rule( $repository_id, [
                'rule_type' => 'path',
                'file_path' => '',
                'target' => (string) $file['path'],
                'action' => 'hide',
                'replacement' => '[PROTECTED]',
            ] );
            if ( ! is_wp_error( $result ) ) $added++;
        }
        $this->redirect_repository( $repository_id, sprintf( __( '%d files will be hidden on the next import.', 'yougitai-secure-showcase' ), $added ), $snapshot_id );
    }

    public function ai_protect_snapshot(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_ai_protect_snapshot_' . $repository_id );
        $repository = $this->repositories->find( $repository_id );
        if ( ! $repository ) {
            $this->redirect_error( __( 'Repository not found.', 'yougitai-secure-showcase' ), $repository_id );
        }
        if ( ! empty( $repository['show_original'] ) ) {
            $this->redirect_error( __( 'AI protection is disabled while Original source mode is active.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $provider = sanitize_key( (string) get_option( 'yougitai_ss_ai_provider', 'openai' ) );
        if ( $provider === 'direct' ) {
            $this->redirect_error( __( 'ChatGPT (DIRECT) runs from a connected ChatGPT conversation. Open Settings for the DIRECT connection instructions.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $ai = ProviderFactory::make();
        if ( ! $ai->is_configured() ) {
            $this->redirect_error( __( 'Configure and enable an AI provider in Settings before starting AI protection.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $result = $this->repositories->import_snapshot( $repository_id );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id );
        }
        $this->redirect_repository( $repository_id, __( 'A new secure draft was created with AI-assisted review enabled. Review its findings before publishing.', 'yougitai-secure-showcase' ), (int) $result['snapshot_id'] );
    }

    public function add_rule(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_add_rule_' . $repository_id );
        $result = $this->repositories->add_rule( $repository_id, [
            'rule_type' => isset( $_POST['rule_type'] ) ? sanitize_key( wp_unslash( $_POST['rule_type'] ) ) : 'path',
            'file_path' => isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : '',
            'target' => isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '',
            'action' => isset( $_POST['rule_action'] ) ? sanitize_key( wp_unslash( $_POST['rule_action'] ) ) : 'hide',
            'replacement' => isset( $_POST['replacement'] ) ? wp_unslash( $_POST['replacement'] ) : '[PROTECTED]',
        ] );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id );
        }
        $this->redirect_repository( $repository_id, __( 'Protection rule added. Import a new snapshot to apply it.', 'yougitai-secure-showcase' ) );
    }


    public function add_line_rule(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        $file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_add_line_rule_' . $file_id );
        $start = isset( $_POST['line_start'] ) ? absint( $_POST['line_start'] ) : 0;
        $end = isset( $_POST['line_end'] ) ? absint( $_POST['line_end'] ) : 0;
        $replacement = isset( $_POST['replacement'] ) ? sanitize_textarea_field( wp_unslash( $_POST['replacement'] ) ) : '[PROTECTED]';
        $result = $this->repositories->add_line_rule_from_snapshot( $repository_id, $snapshot_id, $file_id, $start, $end, $replacement );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id, $snapshot_id );
        }
        $this->redirect_repository( $repository_id, __( 'Line protection rule added. Import a new snapshot to apply it safely.', 'yougitai-secure-showcase' ), $snapshot_id, $file_id );
    }

    public function add_symbol_rule(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        $file_id = isset( $_POST['file_id'] ) ? absint( $_POST['file_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_add_symbol_rule_' . $file_id );
        $symbol = isset( $_POST['symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['symbol'] ) ) : '';
        $replacement = isset( $_POST['replacement'] ) ? sanitize_textarea_field( wp_unslash( $_POST['replacement'] ) ) : '[PROTECTED SYMBOL]';
        $result = $this->repositories->add_symbol_rule_from_snapshot( $repository_id, $snapshot_id, $file_id, $symbol, $replacement );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id, $snapshot_id );
        }
        $this->redirect_repository( $repository_id, __( 'Symbol protection rule added. Import a new snapshot to apply it safely.', 'yougitai-secure-showcase' ), $snapshot_id, $file_id );
    }

    public function restore_snapshot(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $snapshot_id = isset( $_POST['snapshot_id'] ) ? absint( $_POST['snapshot_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_restore_snapshot_' . $snapshot_id );
        $result = $this->repositories->reactivate_published_snapshot( $repository_id, $snapshot_id );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id, $snapshot_id );
        }
        $this->redirect_repository( $repository_id, __( 'Previously verified snapshot restored.', 'yougitai-secure-showcase' ), $snapshot_id );
    }

    public function queue_sync(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_queue_sync_' . $repository_id );
        if ( ! $this->repositories->find( $repository_id ) || ! $this->sync->enqueue( $repository_id ) ) {
            $this->redirect_error( __( 'The secure sync could not be queued.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $this->redirect_repository( $repository_id, __( 'Secure sync queued. It will create a draft snapshot and will not publish automatically.', 'yougitai-secure-showcase' ) );
    }

    public function rotate_webhook(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_rotate_webhook_' . $repository_id );
        $secret = $this->repositories->regenerate_webhook_secret( $repository_id );
        if ( is_wp_error( $secret ) ) {
            $this->redirect_error( $secret->get_error_message(), $repository_id );
        }
        set_transient( 'yougitai_ss_webhook_secret_' . get_current_user_id() . '_' . $repository_id, $secret, 120 );
        $this->redirect_repository( $repository_id, __( 'Webhook secret generated. Copy it now; it will only be shown once.', 'yougitai-secure-showcase' ) );
    }

    public function set_sync_mode(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_set_sync_mode_' . $repository_id );
        $mode = isset( $_POST['sync_mode'] ) ? sanitize_key( wp_unslash( $_POST['sync_mode'] ) ) : 'manual';
        if ( ! $this->repositories->set_sync_mode( $repository_id, $mode ) ) {
            $this->redirect_error( __( 'Sync mode could not be saved.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $this->sync->configure_schedule( $repository_id, $mode );
        $this->redirect_repository( $repository_id, __( 'Sync mode saved.', 'yougitai-secure-showcase' ) );
    }

    public function set_display_mode(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_set_display_mode_' . $repository_id );
        $mode = isset( $_POST['display_mode'] ) ? sanitize_key( wp_unslash( $_POST['display_mode'] ) ) : 'secure';
        $show_original = $mode === 'original';
        if ( $show_original && empty( $_POST['confirm_original'] ) ) {
            $this->redirect_error( __( 'Confirm that you understand the full repository source will be stored in the public snapshot and can be viewed by visitors.', 'yougitai-secure-showcase' ), $repository_id );
        }
        if ( ! $this->repositories->set_show_original( $repository_id, $show_original ) ) {
            $this->redirect_error( __( 'Display mode could not be saved.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $this->redirect_repository( $repository_id, $show_original
            ? __( 'Original source mode enabled. Import a new snapshot before publishing the unredacted version.', 'yougitai-secure-showcase' )
            : __( 'Secure showcase mode enabled. Protection rules will be applied to the next imported snapshot.', 'yougitai-secure-showcase' )
        );
    }


    public function create_connection(): void {
        $this->guard();
        check_admin_referer( 'yougitai_ss_create_connection' );
        $label = isset( $_POST['connection_label'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_label'] ) ) : '';
        $ttl = isset( $_POST['connection_ttl'] ) ? absint( $_POST['connection_ttl'] ) : DAY_IN_SECONDS;
        if ( ! in_array( $ttl, [ HOUR_IN_SECONDS, DAY_IN_SECONDS, 7 * DAY_IN_SECONDS ], true ) ) {
            $ttl = DAY_IN_SECONDS;
        }
        $all_repositories = ! empty( $_POST['connection_all_repositories'] );
        $repository_ids = isset( $_POST['connection_repository_ids'] ) && is_array( $_POST['connection_repository_ids'] )
            ? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['connection_repository_ids'] ) ) ) )
            : [];
        $valid_repository_ids = array_map( static fn( array $row ): int => (int) $row['id'], $this->repositories->all() );
        $repository_ids = array_values( array_intersect( $repository_ids, $valid_repository_ids ) );
        if ( ! $all_repositories && ! empty( $valid_repository_ids ) && empty( $repository_ids ) ) {
            $this->redirect_settings_error( __( 'Select at least one repository or explicitly allow all repositories for this connection.', 'yougitai-secure-showcase' ) );
        }
        if ( $all_repositories ) {
            $repository_ids = [];
        }
        $service = new ConnectionTokenService();
        $created = $service->create( get_current_user_id(), $label, $ttl, $repository_ids );
        if ( ! $created ) {
            $this->redirect_settings_error( __( 'Connected AI token could not be created.', 'yougitai-secure-showcase' ) );
        }
        set_transient( 'yougitai_ss_connection_token_' . get_current_user_id(), $created, 120 );
        wp_safe_redirect( add_query_arg( [ 'page' => 'yougitai-secure-showcase-settings', 'yougitai_message' => rawurlencode( __( 'Connected AI token created. Copy it now; it will only be shown once.', 'yougitai-secure-showcase' ) ) ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function revoke_connection(): void {
        $this->guard();
        $id = isset( $_POST['connection_id'] ) ? absint( $_POST['connection_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_revoke_connection_' . $id );
        ( new ConnectionTokenService() )->revoke( $id );
        wp_safe_redirect( add_query_arg( [ 'page' => 'yougitai-secure-showcase-settings', 'yougitai_message' => rawurlencode( __( 'Connected AI token revoked.', 'yougitai-secure-showcase' ) ) ], admin_url( 'admin.php' ) ) );
        exit;
    }


    public function revoke_oauth_connection(): void {
        $this->guard();
        $id = isset( $_POST['connection_id'] ) ? absint( $_POST['connection_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_revoke_oauth_connection_' . $id );
        if ( $id <= 0 || ! ( new ConnectionTokenService() )->revoke_oauth( $id ) ) {
            $this->redirect_settings_error( __( 'OAuth connection could not be revoked.', 'yougitai-secure-showcase' ) );
        }
        wp_safe_redirect( add_query_arg( [ 'page' => 'yougitai-secure-showcase-settings', 'yougitai_message' => rawurlencode( __( 'OAuth connection revoked.', 'yougitai-secure-showcase' ) ) ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function toggle_rule(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        $rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_toggle_rule_' . $rule_id );
        $enabled = ! empty( $_POST['enabled'] );
        $result = $this->repositories->set_rule_enabled( $repository_id, $rule_id, $enabled );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $result->get_error_message(), $repository_id );
        }
        $this->redirect_repository( $repository_id, $enabled ? __( 'Protection proposal approved. Use “Apply protection now” to create a new protected review draft without re-importing GitHub.', 'yougitai-secure-showcase' ) : __( 'Protection rule disabled.', 'yougitai-secure-showcase' ) );
    }

    public function bulk_approve_rules(): void {
        $this->guard();
        $repository_id = isset( $_POST['repository_id'] ) ? absint( $_POST['repository_id'] ) : 0;
        check_admin_referer( 'yougitai_ss_bulk_approve_rules_' . $repository_id );
        $single_rule_id = isset( $_POST['single_rule_id'] ) ? absint( $_POST['single_rule_id'] ) : 0;
        $rule_ids = $single_rule_id > 0
            ? [ $single_rule_id ]
            : ( isset( $_POST['rule_ids'] ) && is_array( $_POST['rule_ids'] )
                ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['rule_ids'] ) ) ) ) )
                : [] );
        if ( empty( $rule_ids ) ) {
            $this->redirect_error( __( 'Select at least one protection proposal.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $approved = 0;
        foreach ( $rule_ids as $rule_id ) {
            $result = $this->repositories->set_rule_enabled( $repository_id, $rule_id, true );
            if ( ! is_wp_error( $result ) ) {
                $approved++;
            }
        }
        if ( $approved === 0 ) {
            $this->redirect_error( __( 'No protection proposals could be approved.', 'yougitai-secure-showcase' ), $repository_id );
        }
        $this->redirect_repository( $repository_id, sprintf( _n( '%d protection proposal approved. Apply protection now to create a new review draft.', '%d protection proposals approved. Apply protection now to create a new review draft.', $approved, 'yougitai-secure-showcase' ), $approved ) );
    }

    public function github_oauth_start(): void {
        $this->guard();
        check_admin_referer( 'yougitai_ss_github_oauth_start' );
        $url = ( new GitHubOAuth() )->authorization_url( get_current_user_id() );
        if ( is_wp_error( $url ) ) $this->redirect_settings_error( $url->get_error_message() );
        wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }

    public function github_oauth_callback(): void {
        $this->guard();
        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        if ( $code === '' ) $this->redirect_settings_error( __( 'GitHub did not return an authorization code.', 'yougitai-secure-showcase' ) );
        $result = ( new GitHubOAuth() )->exchange( get_current_user_id(), $code, $state );
        if ( is_wp_error( $result ) ) $this->redirect_settings_error( $result->get_error_message() );
        ( new GitHubClient() )->clear_repository_cache();
        wp_safe_redirect( add_query_arg( [ 'page' => 'yougitai-secure-showcase', 'yougitai_message' => rawurlencode( __( 'GitHub OAuth connected successfully. Select the repositories you want to import below.', 'yougitai-secure-showcase' ) ) ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function save_settings(): void {
        $this->guard();
        check_admin_referer( 'yougitai_ss_save_settings' );
        $old_slug = (string) get_option( 'yougitai_ss_showcase_slug', 'repositories' );
        $slug = isset( $_POST['showcase_slug'] ) ? sanitize_title( wp_unslash( $_POST['showcase_slug'] ) ) : 'repositories';
        if ( $slug === '' ) {
            $slug = 'repositories';
        }
        update_option( 'yougitai_ss_showcase_slug', $slug );
        update_option( 'yougitai_ss_index_title_de', isset( $_POST['index_title_de'] ) ? sanitize_text_field( wp_unslash( $_POST['index_title_de'] ) ) : '', false );
        update_option( 'yougitai_ss_index_description_de', isset( $_POST['index_description_de'] ) ? sanitize_textarea_field( wp_unslash( $_POST['index_description_de'] ) ) : '', false );
        update_option( 'yougitai_ss_index_title_en', isset( $_POST['index_title_en'] ) ? sanitize_text_field( wp_unslash( $_POST['index_title_en'] ) ) : '', false );
        update_option( 'yougitai_ss_index_description_en', isset( $_POST['index_description_en'] ) ? sanitize_textarea_field( wp_unslash( $_POST['index_description_en'] ) ) : '', false );
        update_option( 'yougitai_ss_delete_data_on_uninstall', isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0, false );

        $color_defaults = [
            'color_surface' => '#0d1117',
            'color_panel' => '#161b22',
            'color_code' => '#0d1117',
            'color_border' => '#30363d',
            'color_text' => '#c9d1d9',
            'color_muted' => '#8b949e',
            'color_accent' => '#58a6ff',
            'color_accent_hover' => '#79c0ff',
            'color_selected' => '#1f2d3d',
        ];
        foreach ( $color_defaults as $field => $default_color ) {
            $raw_color = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : $default_color;
            $color = sanitize_hex_color( $raw_color );
            update_option( 'yougitai_ss_' . $field, $color ?: $default_color, false );
        }
        $provider = isset( $_POST['ai_provider'] ) ? sanitize_key( wp_unslash( $_POST['ai_provider'] ) ) : 'openai';
        if ( ! in_array( $provider, [ 'direct', 'openai', 'anthropic', 'gemini' ], true ) ) {
            $provider = 'openai';
        }
        update_option( 'yougitai_ss_ai_provider', $provider );
        update_option( 'yougitai_ss_external_ai_enabled', $provider !== 'direct' && isset( $_POST['external_ai_enabled'] ) ? 1 : 0 );

        $provider_settings = [
            'openai' => [ 'model' => 'gpt-5.6-luna', 'option' => 'yougitai_ss_openai_api_key' ],
            'anthropic' => [ 'model' => 'claude-sonnet-5', 'option' => 'yougitai_ss_anthropic_api_key' ],
            'gemini' => [ 'model' => 'gemini-3.8-flash', 'option' => 'yougitai_ss_gemini_api_key' ],
        ];
        foreach ( $provider_settings as $slug => $config ) {
            $model_field = $slug . '_model';
            $key_field = $slug . '_api_key';
            $remove_field = 'remove_' . $slug . '_api_key';
            $model = isset( $_POST[ $model_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $model_field ] ) ) : $config['model'];
            update_option( 'yougitai_ss_' . $slug . '_model', $model );
            $key = isset( $_POST[ $key_field ] ) ? trim( (string) wp_unslash( $_POST[ $key_field ] ) ) : '';
            if ( $key !== '' ) {
                $encrypted_key = SecretVault::encrypt( $key );
                if ( $encrypted_key === '' ) {
                    $this->redirect_settings_error( __( 'AI API key could not be encrypted on this server.', 'yougitai-secure-showcase' ) );
                }
                update_option( $config['option'], $encrypted_key, false );
            }
            if ( isset( $_POST[ $remove_field ] ) ) {
                delete_option( $config['option'] );
            }
        }
        $github_oauth_client_id = isset( $_POST['github_oauth_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['github_oauth_client_id'] ) ) : '';
        update_option( 'yougitai_ss_github_oauth_client_id', $github_oauth_client_id, false );
        $github_oauth_secret = isset( $_POST['github_oauth_client_secret'] ) ? trim( (string) wp_unslash( $_POST['github_oauth_client_secret'] ) ) : '';
        if ( $github_oauth_secret !== '' ) {
            $encrypted_oauth_secret = SecretVault::encrypt( $github_oauth_secret );
            if ( $encrypted_oauth_secret === '' ) $this->redirect_settings_error( __( 'GitHub OAuth client secret could not be encrypted on this server.', 'yougitai-secure-showcase' ) );
            update_option( 'yougitai_ss_github_oauth_client_secret', $encrypted_oauth_secret, false );
        }
        if ( isset( $_POST['remove_github_oauth_client_secret'] ) ) delete_option( 'yougitai_ss_github_oauth_client_secret' );

        $github_token = isset( $_POST['github_token'] ) ? trim( (string) wp_unslash( $_POST['github_token'] ) ) : '';
        if ( $github_token !== '' ) {
            $encrypted = SecretVault::encrypt( $github_token );
            if ( $encrypted === '' ) {
                $this->redirect_settings_error( __( 'GitHub token could not be encrypted on this server.', 'yougitai-secure-showcase' ) );
            }
            update_option( 'yougitai_ss_github_token', $encrypted, false );
        }
        if ( isset( $_POST['remove_github_token'] ) ) {
            delete_option( 'yougitai_ss_github_token' );
        }
        if ( $old_slug !== $slug ) {
            // Renderer registered the old slug earlier in this request. Defer
            // flushing until the next init so the new slug is registered first.
            update_option( 'yougitai_ss_rewrite_flush_needed', 1, false );
            update_option( 'yougitai_ss_rewrite_version', 0, false );
        }
        wp_safe_redirect( add_query_arg( [
            'page' => 'yougitai-secure-showcase-settings',
            'yougitai_message' => rawurlencode( __( 'Settings saved.', 'yougitai-secure-showcase' ) ),
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function stored_secret_present( string $option ): bool {
        $stored = trim( (string) get_option( $option, '' ) );
        if ( $stored === '' ) {
            return false;
        }
        if ( str_starts_with( $stored, 'sodium:' ) || str_starts_with( $stored, 'openssl:' ) ) {
            return SecretVault::decrypt( $stored ) !== '';
        }
        return true;
    }

    private function guard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'yougitai-secure-showcase' ) );
        }
    }

    private function redirect_repository( int $repository_id, string $message, int $snapshot_id = 0, int $file_id = 0 ): void {
        $args = [
            'page' => 'yougitai-secure-showcase',
            'repository_id' => $repository_id,
            'yougitai_message' => rawurlencode( $message ),
        ];
        if ( $snapshot_id ) {
            $args['snapshot_id'] = $snapshot_id;
        }
        if ( $file_id ) {
            $args['file_id'] = $file_id;
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function redirect_settings_error( string $message ): void {
        wp_safe_redirect( add_query_arg( [
            'page' => 'yougitai-secure-showcase-settings',
            'yougitai_error' => rawurlencode( $message ),
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function redirect_error( string $message, int $repository_id = 0, int $snapshot_id = 0 ): void {
        $args = [
            'page' => 'yougitai-secure-showcase',
            'yougitai_error' => rawurlencode( $message ),
        ];
        if ( $repository_id ) {
            $args['repository_id'] = $repository_id;
        }
        if ( $snapshot_id ) {
            $args['snapshot_id'] = $snapshot_id;
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
