<?php
namespace YougitAI\SecureShowcase\Audit;

use YougitAI\SecureShowcase\Database\Schema;

final class Logger {
    public function recent_for_repository( int $repository_id, int $limit = 30 ): array {
        global $wpdb;
        $limit = max( 1, min( 100, $limit ) );
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT id,snapshot_id,user_id,event_type,message,metadata,created_at FROM ' . Schema::table( 'audit_log' ) . ' WHERE repository_id=%d ORDER BY id DESC LIMIT %d',
            $repository_id,
            $limit
        ), ARRAY_A ) ?: [];
    }

    public function log( string $event_type, string $message, ?int $repository_id = null, ?int $snapshot_id = null, array $metadata = [] ): void {
        global $wpdb;
        $wpdb->insert( Schema::table( 'audit_log' ), [
            'repository_id' => $repository_id ?: null,
            'snapshot_id' => $snapshot_id ?: null,
            'user_id' => get_current_user_id() ?: null,
            'event_type' => sanitize_key( $event_type ),
            'message' => sanitize_textarea_field( $message ),
            'metadata' => $metadata ? wp_json_encode( $metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : null,
            'created_at' => current_time( 'mysql' ),
        ] );
    }
}
