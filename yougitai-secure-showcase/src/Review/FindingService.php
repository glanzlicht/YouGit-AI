<?php
namespace YougitAI\SecureShowcase\Review;

use YougitAI\SecureShowcase\Database\Schema;

final class FindingService {
    public function add( int $repository_id, int $snapshot_id, ?int $file_id, string $path, array $finding ): int {
        global $wpdb;
        $severity = in_array( $finding['severity'] ?? '', [ 'low', 'medium', 'high', 'critical' ], true ) ? $finding['severity'] : 'medium';
        $recommendation = in_array( $finding['recommendation'] ?? '', [ 'review', 'redact', 'hide' ], true ) ? $finding['recommendation'] : 'review';
        $wpdb->insert( Schema::table( 'findings' ), [
            'repository_id' => $repository_id,
            'snapshot_id' => $snapshot_id,
            'file_id' => $file_id ?: null,
            'file_path' => $path,
            'finding_type' => sanitize_key( (string) ( $finding['type'] ?? 'security_review' ) ),
            'severity' => $severity,
            'risk_score' => min( 100, max( 0, (int) ( $finding['risk_score'] ?? 0 ) ) ),
            'source' => sanitize_key( (string) ( $finding['source'] ?? 'scanner' ) ),
            'title' => sanitize_text_field( (string) ( $finding['title'] ?? __( 'Security review finding', 'yougitai-secure-showcase' ) ) ),
            'details' => sanitize_textarea_field( (string) ( $finding['details'] ?? '' ) ),
            'recommendation' => $recommendation,
            'status' => 'open',
            'metadata' => ! empty( $finding['metadata'] ) ? wp_json_encode( $finding['metadata'] ) : null,
            'created_at' => current_time( 'mysql' ),
        ] );
        return (int) $wpdb->insert_id;
    }

    public function for_snapshot( int $snapshot_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Schema::table( 'findings' ) . ' WHERE snapshot_id=%d ORDER BY risk_score DESC, id ASC',
            $snapshot_id
        ), ARRAY_A ) ?: [];
    }

    public function resolve( int $finding_id, string $status ): bool {
        global $wpdb;
        $allowed = [ 'resolved', 'accepted', 'ignored' ];
        if ( ! in_array( $status, $allowed, true ) ) {
            return false;
        }
        return false !== $wpdb->update( Schema::table( 'findings' ), [
            'status' => $status,
            'resolved_at' => current_time( 'mysql' ),
        ], [ 'id' => $finding_id ] );
    }
}
