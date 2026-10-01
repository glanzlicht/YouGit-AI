<?php
namespace YougitAI\SecureShowcase\Frontend;

use YougitAI\SecureShowcase\GitHub\Client;
use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Security\ShowcaseAccess;

final class ReadmeProxy {
    private Client $github;
    private ShowcaseAccess $access;

    public function __construct( private RepositoryService $repositories ) {
        $this->github = new Client();
        $this->access = new ShowcaseAccess();
    }

    public function register(): void {
        add_action( 'template_redirect', [ $this, 'serve' ], 0 );
    }

    public function serve(): void {
        if ( empty( $_GET['yougitai_readme_asset'] ) ) return;

        $repository_id = isset( $_GET['repo'] ) ? absint( $_GET['repo'] ) : 0;
        $snapshot_id = isset( $_GET['snapshot'] ) ? absint( $_GET['snapshot'] ) : 0;
        $path = isset( $_GET['path'] ) ? rawurldecode( (string) wp_unslash( $_GET['path'] ) ) : '';
        $repository = $repository_id ? $this->repositories->find( $repository_id ) : null;

        if (
            ! $repository ||
            (string) ( $repository['status'] ?? '' ) !== 'published' ||
            (int) ( $repository['active_snapshot_id'] ?? 0 ) !== $snapshot_id ||
            ! $this->access->is_allowed( $repository ) ||
            ! $this->is_allowed_image( $repository_id, $path ) ||
            ! $this->is_referenced_by_readme( $repository_id, $snapshot_id, $path )
        ) {
            status_header( 404 );
            exit;
        }

        $cache = $this->cache_file( $repository_id, $snapshot_id, $path );
        if ( is_file( $cache ) ) {
            $bytes = @file_get_contents( $cache );
            if ( is_string( $bytes ) && $bytes !== '' ) $this->output( $bytes );
        }

        $file = $this->github->get_file(
            (string) $repository['owner'],
            (string) $repository['repo'],
            $path,
            (string) ( $repository['default_branch'] ?? 'main' )
        );
        if ( is_wp_error( $file ) || ( $file['encoding'] ?? '' ) !== 'base64' ) {
            status_header( 404 );
            exit;
        }

        $bytes = base64_decode( preg_replace( '/\s+/', '', (string) ( $file['content'] ?? '' ) ), true );
        $max = (int) apply_filters( 'yougitai_ss_readme_asset_max_bytes', 5 * MB_IN_BYTES );
        if ( $bytes === false || strlen( $bytes ) < 1 || strlen( $bytes ) > $max || $this->mime( $bytes ) === '' ) {
            status_header( 404 );
            exit;
        }

        if ( wp_mkdir_p( dirname( $cache ) ) ) @file_put_contents( $cache, $bytes, LOCK_EX );
        $this->output( $bytes );
    }

    private function is_referenced_by_readme( int $repository_id, int $snapshot_id, string $path ): bool {
        $readme = $this->repositories->public_readme( $repository_id, $snapshot_id );
        if ( ! $readme || empty( $readme['content'] ) ) return false;

        $base = (string) ( $readme['path'] ?? 'README.md' );
        $content = (string) $readme['content'];
        if ( ! preg_match_all( '/!\[[^\]]*\]\(([^)\s]+)(?:\s+["\'][^"\']*["\'])?\)/', $content, $matches ) ) return false;

        foreach ( $matches[1] as $target ) {
            $target = trim( html_entity_decode( (string) $target, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            if ( $target === '' || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $target ) ) continue;
            if ( $this->resolve_path( $base, $target ) === $path ) return true;
        }
        return false;
    }

    private function is_allowed_image( int $repository_id, string $path ): bool {
        if ( ! in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), [ 'png', 'jpg', 'jpeg', 'gif', 'webp' ], true ) ) return false;

        foreach ( $this->repositories->rules( $repository_id ) as $rule ) {
            if ( empty( $rule['enabled'] ) || (string) ( $rule['action'] ?? '' ) !== 'hide' || (string) ( $rule['rule_type'] ?? '' ) !== 'path' ) continue;
            $target = trim( (string) ( $rule['target'] ?? $rule['file_path'] ?? '' ), '/' );
            if ( $target !== '' && ( $path === $target || str_starts_with( $path, $target . '/' ) ) ) return false;
        }
        return true;
    }

    private function resolve_path( string $readme_path, string $relative ): string {
        $relative = rawurldecode( preg_replace( '/[#?].*$/', '', $relative ) ?? $relative );
        $relative = str_replace( '\\', '/', $relative );
        $base = str_starts_with( $relative, '/' ) ? '' : dirname( $readme_path );
        if ( $base === '.' ) $base = '';

        $out = [];
        foreach ( explode( '/', trim( $base . '/' . ltrim( $relative, '/' ), '/' ) ) as $part ) {
            if ( $part === '' || $part === '.' ) continue;
            if ( $part === '..' ) {
                if ( ! $out ) return '';
                array_pop( $out );
                continue;
            }
            $out[] = $part;
        }
        return implode( '/', $out );
    }

    private function cache_file( int $repository_id, int $snapshot_id, string $path ): string {
        $uploads = wp_upload_dir();
        return trailingslashit( $uploads['basedir'] ) . 'yougitai-showcase-cache/' . $repository_id . '/' . $snapshot_id . '/' . hash( 'sha256', $path ) . '.img';
    }

    private function mime( string $bytes ): string {
        $info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bytes ) : false;
        $mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
        return in_array( $mime, [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ], true ) ? $mime : '';
    }

    private function output( string $bytes ): void {
        $mime = $this->mime( $bytes );
        if ( $mime === '' ) { status_header( 404 ); exit; }
        header( 'Content-Type: ' . $mime );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: inline' );
        header( 'Cache-Control: public, max-age=86400' );
        echo $bytes;
        exit;
    }
}
