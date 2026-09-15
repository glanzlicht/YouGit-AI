<?php
namespace YougitAI\SecureShowcase\Security;

use YougitAI\SecureShowcase\Database\Schema;

final class ConnectionTokenService {
    public const DEFAULT_SCOPES = [
        'repositories:read',
        'source:review',
        'findings:read',
        'redactions:propose',
        'snapshots:create',
    ];

    /** @return array{token:string,id:int,expires_at:string,repository_ids:array}|false */
    public function create( int $user_id, string $label, int $ttl_seconds = DAY_IN_SECONDS, array $repository_ids = [] ) {
        global $wpdb;
        $ttl_seconds = max( HOUR_IN_SECONDS, min( 7 * DAY_IN_SECONDS, $ttl_seconds ) );
        $repository_ids = array_values( array_unique( array_filter( array_map( 'absint', $repository_ids ) ) ) );
        $token = 'ygai_' . bin2hex( random_bytes( 32 ) );
        $now = current_time( 'mysql', true );
        $expires = gmdate( 'Y-m-d H:i:s', time() + $ttl_seconds );
        $ok = $wpdb->insert( Schema::table( 'connections' ), [
            'user_id' => $user_id,
            'label' => sanitize_text_field( $label !== '' ? $label : __( 'ChatGPT connection', 'yougitai-secure-showcase' ) ),
            'token_hash' => hash( 'sha256', $token ),
            'scopes' => wp_json_encode( self::DEFAULT_SCOPES ),
            'repository_ids' => wp_json_encode( $repository_ids ),
            'created_at' => $now,
            'expires_at' => $expires,
        ] );
        if ( ! $ok ) {
            return false;
        }
        return [ 'token' => $token, 'id' => (int) $wpdb->insert_id, 'expires_at' => $expires, 'repository_ids' => $repository_ids ];
    }

    public function revoke( int $id ): bool {
        global $wpdb;
        return $wpdb->update( Schema::table( 'connections' ), [ 'revoked_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] ) !== false;
    }

    public function active(): array {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT id,user_id,label,scopes,repository_ids,created_at,expires_at,last_used_at,request_count,source_bytes,revoked_at FROM ' . Schema::table( 'connections' ) . ' ORDER BY id DESC LIMIT 50',
            ARRAY_A
        ) ?: [];
    }

    public function consume_source_bytes( int $id, int $bytes, int $limit = 5242880, string $connection_type = 'legacy' ): bool {
        global $wpdb;
        $bytes = max( 0, $bytes );
        if ( $bytes === 0 ) {
            return true;
        }
        $table = $connection_type === 'oauth' ? Schema::table( 'oauth_tokens' ) : Schema::table( 'connections' );
        $affected = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . $table . ' SET source_bytes=source_bytes+%d WHERE id=%d AND source_bytes+%d<=%d AND revoked_at IS NULL',
            $bytes,
            $id,
            $bytes,
            $limit
        ) );
        return $affected === 1;
    }

    public function active_oauth(): array {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT id,client_id,user_id,label,scopes,repository_ids,created_at,expires_at,refresh_expires_at,last_used_at,request_count,source_bytes,revoked_at FROM ' . Schema::table( 'oauth_tokens' ) . ' ORDER BY id DESC LIMIT 50',
            ARRAY_A
        ) ?: [];
    }

    public function revoke_oauth( int $id ): bool {
        global $wpdb;
        return $wpdb->update( Schema::table( 'oauth_tokens' ), [ 'revoked_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] ) !== false;
    }

    public function authenticate( string $token, string $required_scope = '' ): ?array {
        global $wpdb;
        $connection_type = 'legacy';
        if ( str_starts_with( $token, 'ygao_' ) && strlen( $token ) >= 40 ) {
            $connection_type = 'oauth';
            $row = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . Schema::table( 'oauth_tokens' ) . ' WHERE access_token_hash=%s LIMIT 1',
                hash( 'sha256', $token )
            ), ARRAY_A );
        } elseif ( str_starts_with( $token, 'ygai_' ) && strlen( $token ) >= 40 ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . Schema::table( 'connections' ) . ' WHERE token_hash=%s LIMIT 1',
                hash( 'sha256', $token )
            ), ARRAY_A );
        } else {
            return null;
        }
        if ( ! $row || ! empty( $row['revoked_at'] ) ) {
            return null;
        }
        if ( strtotime( (string) $row['expires_at'] . ' UTC' ) <= time() ) {
            return null;
        }
        $scopes = json_decode( (string) $row['scopes'], true );
        $scopes = is_array( $scopes ) ? array_values( array_filter( array_map( 'strval', $scopes ) ) ) : [];
        if ( $required_scope !== '' && ! in_array( $required_scope, $scopes, true ) ) {
            return null;
        }
        $table = $connection_type === 'oauth' ? Schema::table( 'oauth_tokens' ) : Schema::table( 'connections' );
        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . $table . ' SET last_used_at=%s, request_count=request_count+1 WHERE id=%d',
            current_time( 'mysql', true ),
            (int) $row['id']
        ) );
        $repository_ids = json_decode( (string) ( $row['repository_ids'] ?? '[]' ), true );
        $row['scopes'] = $scopes;
        $row['repository_ids'] = is_array( $repository_ids ) ? array_values( array_filter( array_map( 'absint', $repository_ids ) ) ) : [];
        $row['connection_type'] = $connection_type;
        return $row;
    }
}
