<?php
namespace YougitAI\SecureShowcase\Core;

use YougitAI\SecureShowcase\Database\Schema;
use YougitAI\SecureShowcase\Security\Storage;

final class Activator {
    public static function activate(): void {
        Schema::install();
        Storage::ensure_directories();
        update_option( 'yougitai_ss_version', YOUGITAI_SS_VERSION );
        add_option( 'yougitai_ss_showcase_slug', 'repositories' );
        add_option( 'yougitai_ss_external_ai_enabled', false );
        add_option( 'yougitai_ss_ai_provider', 'openai' );
        add_option( 'yougitai_ss_openai_model', 'gpt-5.6-luna' );
        add_option( 'yougitai_ss_anthropic_model', 'claude-sonnet-5' );
        add_option( 'yougitai_ss_gemini_model', 'gemini-3.8-flash' );
        add_option( 'yougitai_ss_delete_data_on_uninstall', false );
        if ( ! wp_next_scheduled( 'yougitai_ss_daily_cleanup' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'yougitai_ss_daily_cleanup' );
        }
        add_option( 'yougitai_ss_github_token', '', '', false );

        // Register the standalone showcase rule before flushing. Activation hooks run
        // before the normal init hook where Renderer registers this rule, so flushing
        // without adding it here would persist rewrite rules without /repositories/... .
        $slug = sanitize_title( (string) get_option( 'yougitai_ss_showcase_slug', 'repositories' ) );
        add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?yougitai_showcase_index=1', 'top' );
        add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/([^/]+)/?$', 'index.php?yougitai_showcase=$matches[1]', 'top' );
        flush_rewrite_rules();
        update_option( 'yougitai_ss_rewrite_version', 2, false );
        delete_option( 'yougitai_ss_rewrite_flush_needed' );
    }
}
