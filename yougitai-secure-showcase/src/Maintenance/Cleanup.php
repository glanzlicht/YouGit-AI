<?php
namespace YougitAI\SecureShowcase\Maintenance;

use YougitAI\SecureShowcase\Database\Schema;

final class Cleanup {
    public const HOOK = 'yougitai_ss_daily_cleanup';

    public function register(): void {
        add_action( self::HOOK, [ $this, 'run' ] );
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
        }
    }

    public function run(): void {
        global $wpdb;
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
        $wpdb->query( $wpdb->prepare(
            'DELETE FROM ' . Schema::table( 'connections' ) . ' WHERE expires_at < %s OR (revoked_at IS NOT NULL AND revoked_at < %s)',
            $cutoff,
            $cutoff
        ) );
        $wpdb->query( $wpdb->prepare(
            'DELETE FROM ' . Schema::table( 'oauth_codes' ) . ' WHERE expires_at < %s OR used_at IS NOT NULL',
            gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
        ) );
        $wpdb->query( $wpdb->prepare(
            'DELETE FROM ' . Schema::table( 'oauth_tokens' ) . ' WHERE refresh_expires_at < %s OR (revoked_at IS NOT NULL AND revoked_at < %s)',
            $cutoff,
            $cutoff
        ) );
    }
}
