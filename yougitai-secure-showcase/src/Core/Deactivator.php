<?php
namespace YougitAI\SecureShowcase\Core;

final class Deactivator {
    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'yougitai_ss_sync_repository' );
        wp_clear_scheduled_hook( 'yougitai_ss_daily_cleanup' );
        flush_rewrite_rules();
    }
}
