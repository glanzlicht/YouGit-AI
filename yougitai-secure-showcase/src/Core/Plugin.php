<?php
namespace YougitAI\SecureShowcase\Core;

use YougitAI\SecureShowcase\Admin\Admin;
use YougitAI\SecureShowcase\Api\PublicRoutes;
use YougitAI\SecureShowcase\Api\GitHubWebhookRoutes;
use YougitAI\SecureShowcase\Api\ConnectedAIRoutes;
use YougitAI\SecureShowcase\Database\Schema;
use YougitAI\SecureShowcase\Elementor\Integration as ElementorIntegration;
use YougitAI\SecureShowcase\Frontend\Renderer;
use YougitAI\SecureShowcase\I18n\Loader;
use YougitAI\SecureShowcase\Maintenance\Cleanup;
use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Security\Storage;
use YougitAI\SecureShowcase\Security\SecretVault;
use YougitAI\SecureShowcase\Security\OAuthServer;
use YougitAI\SecureShowcase\Sync\SyncManager;

final class Plugin {
    private const REWRITE_VERSION = 2;

    public function boot(): void {
        ( new Loader() )->register();
        $this->maybe_upgrade();
        Storage::ensure_directories();

        $repositories = new RepositoryService();
        $sync = new SyncManager();
        $sync->register();
        ( new Cleanup() )->register();
        ( new Admin( $repositories, $sync ) )->register();
        ( new Renderer( $repositories ) )->register();
        ( new PublicRoutes( $repositories ) )->register();
        ( new GitHubWebhookRoutes( $repositories, $sync ) )->register();
        ( new OAuthServer() )->register();
        ( new ConnectedAIRoutes( $repositories ) )->register();
        ( new ElementorIntegration( $repositories ) )->register();

        // Rewrite rules must be flushed only after Renderer has registered the
        // current root + detail routes on init. Flushing during upgrade before
        // init would persist an incomplete rule set.
        add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 99 );
    }

    public function maybe_flush_rewrite_rules(): void {
        $stored_version = (int) get_option( 'yougitai_ss_rewrite_version', 0 );
        $pending = (int) get_option( 'yougitai_ss_rewrite_flush_needed', 0 );
        if ( ! $pending && $stored_version === self::REWRITE_VERSION ) {
            return;
        }

        // At priority 99 Renderer::rewrite() has already added the current
        // /repositories/ index and /repositories/{slug}/ detail rules.
        flush_rewrite_rules( false );
        update_option( 'yougitai_ss_rewrite_version', self::REWRITE_VERSION, false );
        delete_option( 'yougitai_ss_rewrite_flush_needed' );
    }

    private function maybe_upgrade(): void {
        if ( get_option( 'yougitai_ss_version' ) === YOUGITAI_SS_VERSION ) {
            return;
        }
        Schema::install();
        foreach ( [ 'yougitai_ss_openai_api_key', 'yougitai_ss_anthropic_api_key', 'yougitai_ss_gemini_api_key' ] as $secret_option ) {
            $legacy_ai_key = trim( (string) get_option( $secret_option, '' ) );
            if ( $legacy_ai_key !== '' && ! str_starts_with( $legacy_ai_key, 'sodium:' ) && ! str_starts_with( $legacy_ai_key, 'openssl:' ) ) {
                $encrypted = SecretVault::encrypt( $legacy_ai_key );
                if ( $encrypted !== '' ) {
                    update_option( $secret_option, $encrypted, false );
                }
            }
        }
        update_option( 'yougitai_ss_version', YOUGITAI_SS_VERSION );
        update_option( 'yougitai_ss_rewrite_flush_needed', 1, false );
    }
}
