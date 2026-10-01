<?php
namespace YougitAI\SecureShowcase\Repository;

use YougitAI\SecureShowcase\GitHub\Client;

final class ReadmeAssets {
    public function __construct( private Client $github ) {}

    public function mirror( array $repository, int $snapshot_id, string $readme_path, string $markdown, string $ref, array $rules ): string {
        $rewrite = function ( string $target ) use ( $repository, $snapshot_id, $readme_path, $ref, $rules ): string {
            $target = html_entity_decode( trim( $target ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            if ( $target === '' || str_starts_with( $target, '#' ) || preg_match( '#^(?:https?:|data:|mailto:)#i', $target ) ) {
                return $target;
            }

            $url_parts = wp_parse_url( $target );
            $relative_path = isset( $url_parts['path'] ) ? rawurldecode( (string) $url_parts['path'] ) : '';
            $asset_path = $this->resolve_path( $readme_path, $relative_path );
            if ( $asset_path === '' || $this->is_protected( $asset_path, $rules ) ) {
                return $target;
            }

            $extension = strtolower( pathinfo( $asset_path, PATHINFO_EXTENSION ) );
            if ( ! in_array( $extension, [ 'png', 'jpg', 'jpeg', 'gif', 'webp' ], true ) ) {
                return $target;
            }

            $file = $this->github->get_file(
                (string) $repository['owner'],
                (string) $repository['repo'],
                $asset_path,
                $ref
            );
            if ( is_wp_error( $file ) || ( $file['encoding'] ?? '' ) !== 'base64' ) {
                return $target;
            }

            $bytes = base64_decode( preg_replace( '/\\s+/', '', (string) ( $file['content'] ?? '' ) ), true );
            $max_bytes = (int) apply_filters( 'yougitai_ss_readme_asset_max_bytes', 5 * MB_IN_BYTES );
            if ( $bytes === false || strlen( $bytes ) === 0 || strlen( $bytes ) > $max_bytes ) {
                return $target;
            }

            $image_info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bytes ) : false;
            $mime = is_array( $image_info ) ? (string) ( $image_info['mime'] ?? '' ) : '';
            $mime_to_ext = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            if ( ! isset( $mime_to_ext[ $mime ] ) ) {
                return $target;
            }

            $uploads = wp_upload_dir();
            if ( ! empty( $uploads['error'] ) ) {
                return $target;
            }

            $subdir = 'yougitai-showcase/' . absint( $repository['id'] ) . '/' . absint( $snapshot_id );
            $directory = trailingslashit( $uploads['basedir'] ) . $subdir;
            if ( ! wp_mkdir_p( $directory ) ) {
                return $target;
            }

            $hash = hash( 'sha256', $asset_path . "\n" . hash( 'sha256', $bytes ) );
            $filename = $hash . '.' . $mime_to_ext[ $mime ];
            $destination = trailingslashit( $directory ) . $filename;
            if ( ! file_exists( $destination ) ) {
                $written = @file_put_contents( $destination, $bytes, LOCK_EX );
                if ( $written === false || $written !== strlen( $bytes ) ) {
                    @unlink( $destination );
                    return $target;
                }
            }

            return trailingslashit( $uploads['baseurl'] ) . $subdir . '/' . rawurlencode( $filename );
        };

        $markdown = preg_replace_callback(
            '/!\\[([^\\]]*)\\]\\(([^)\\s]+)(?:\\s+["\\'][^"\\']*["\\'])?\\)/',
            static function ( array $match ) use ( $rewrite ): string {
                return '![' . $match[1] . '](' . $rewrite( (string) $match[2] ) . ')';
            },
            $markdown
        ) ?? $markdown;

        $markdown = preg_replace_callback(
            '/(<img\\b[^>]*?\\bsrc\\s*=\\s*)(["\\'])([^"\\']+)\\2/iu',
            static function ( array $match ) use ( $rewrite ): string {
                return $match[1] . $match[2] . esc_url( $rewrite( (string) $match[3] ) ) . $match[2];
            },
            $markdown
        ) ?? $markdown;

        return $markdown;
    }

    private function resolve_path( string $readme_path, string $relative_path ): string {
        if ( $relative_path === '' ) return '';
        $relative_path = str_replace( '\\', '/', $relative_path );
        $base = str_starts_with( $relative_path, '/' ) ? '' : dirname( $readme_path );
        if ( $base === '.' ) $base = '';

        $candidate = trim( $base . '/' . ltrim( $relative_path, '/' ), '/' );
        $resolved = [];
        foreach ( explode( '/', $candidate ) as $part ) {
            if ( $part === '' || $part === '.' ) continue;
            if ( $part === '..' ) {
                if ( ! $resolved ) return '';
                array_pop( $resolved );
                continue;
            }
            $resolved[] = $part;
        }
        return implode( '/', $resolved );
    }

    private function is_protected( string $asset_path, array $rules ): bool {
        foreach ( $rules as $rule ) {
            if ( empty( $rule['enabled'] ) || (string) ( $rule['action'] ?? '' ) !== 'hide' || (string) ( $rule['rule_type'] ?? '' ) !== 'path' ) {
                continue;
            }
            $target = trim( (string) ( $rule['target'] ?? $rule['file_path'] ?? '' ), '/' );
            if ( $target !== '' && ( $asset_path === $target || str_starts_with( $asset_path, $target . '/' ) ) ) {
                return true;
            }
        }
        return false;
    }
}
