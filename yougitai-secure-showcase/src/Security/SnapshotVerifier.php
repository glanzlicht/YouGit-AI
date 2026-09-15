<?php
namespace YougitAI\SecureShowcase\Security;

use YougitAI\SecureShowcase\Database\Schema;

final class SnapshotVerifier {
    public function verify( int $snapshot_id ): array {
        global $wpdb;
        $scanner = new SecretScanner();
        $snapshot_meta = $wpdb->get_row( $wpdb->prepare( 'SELECT repository_id,show_original FROM ' . Schema::table( 'snapshots' ) . ' WHERE id=%d', $snapshot_id ), ARRAY_A );
        $show_original = ! empty( $snapshot_meta['show_original'] );
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT id,path,visibility,content,risk_score FROM ' . Schema::table( 'files' ) . ' WHERE snapshot_id=%d ORDER BY path_hash ASC',
            $snapshot_id
        ), ARRAY_A ) ?: [];

        $issues = [];
        $hash_context = hash_init( 'sha256' );
        foreach ( $rows as $row ) {
            $visibility = (string) $row['visibility'];
            $content = (string) ( $row['content'] ?? '' );
            if ( $visibility === 'hidden' ) {
                if ( $content !== '' ) {
                    $issues[] = sprintf( __( 'Hidden file still contains persisted content: %s', 'yougitai-secure-showcase' ), $row['path'] );
                }
                continue;
            }
            $hits = $show_original ? [] : $scanner->scan( $content );
            if ( $hits ) {
                $issues[] = sprintf( __( 'Potential secret remains in public snapshot: %s', 'yougitai-secure-showcase' ), $row['path'] );
            }
            if ( ! $show_original && (int) $row['risk_score'] >= 100 ) {
                $issues[] = sprintf( __( 'Unresolved critical-risk file: %s', 'yougitai-secure-showcase' ), $row['path'] );
            }
            hash_update( $hash_context, $row['path'] . "\0" . $visibility . "\0" . hash( 'sha256', $content ) . "\n" );
        }

        return [
            'ok' => empty( $issues ),
            'issues' => $issues,
            'verification_hash' => hash_final( $hash_context ),
            'files' => count( $rows ),
            'show_original' => $show_original,
        ];
    }
}
