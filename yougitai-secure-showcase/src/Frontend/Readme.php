<?php
namespace YougitAI\SecureShowcase\Frontend;

use YougitAI\SecureShowcase\GitHub\Client;
use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Security\ShowcaseAccess;

final class Readme {
    private Client $github;
    private ShowcaseAccess $access;

    public function __construct( private RepositoryService $repositories ) {
        $this->github = new Client();
        $this->access = new ShowcaseAccess();
    }

    public function register(): void {
        add_action( 'template_redirect', [ $this, 'serve_asset' ], 0 );
    }

    public function render( array $repository, array $readme ): string {
        $markdown = mb_substr( (string) ( $readme['content'] ?? '' ), 0, 30000 );
        $readme_path = (string) ( $readme['path'] ?? 'README.md' );
        $snapshot_id = (int) ( $repository['active_snapshot_id'] ?? 0 );

        $markdown = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+["\'][^"\']*["\'])?\)/',
            function ( array $match ) use ( $repository, $snapshot_id, $readme_path ): string {
                $target = trim( (string) $match[2] );
                if ( preg_match( '#^https://#i', $target ) ) {
                    return '![' . $match[1] . '](' . esc_url_raw( $target ) . ')';
                }
                if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $target ) ) {
                    return esc_html( $match[0] );
                }
                $path = $this->resolve_path( $readme_path, $target );
                $url = $this->asset_url( $repository, $snapshot_id, $path );
                return $url ? '![' . $match[1] . '](' . $url . ')' : esc_html( $match[0] );
            },
            $markdown
        ) ?? $markdown;

        return $this->markdown_to_html( $markdown );
    }

    public function serve_asset(): void {
        if ( empty( $_GET['yougitai_readme_asset'] ) ) return;

        $repository_id = isset( $_GET['repo'] ) ? absint( $_GET['repo'] ) : 0;
        $snapshot_id = isset( $_GET['snapshot'] ) ? absint( $_GET['snapshot'] ) : 0;
        $path = isset( $_GET['path'] ) ? rawurldecode( (string) wp_unslash( $_GET['path'] ) ) : '';
        $sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
        $repository = $repository_id ? $this->repositories->find( $repository_id ) : null;

        if (
            ! $repository ||
            (string) ( $repository['status'] ?? '' ) !== 'published' ||
            (int) ( $repository['active_snapshot_id'] ?? 0 ) !== $snapshot_id ||
            ! $this->access->is_allowed( $repository ) ||
            $sig === '' ||
            ! hash_equals( $this->signature( $repository_id, $snapshot_id, $path ), $sig ) ||
            ! $this->asset_allowed( $repository_id, $path )
        ) {
            status_header( 404 );
            exit;
        }

        $cache = $this->cache_file( $repository_id, $snapshot_id, $path );
        if ( is_file( $cache ) ) {
            $bytes = @file_get_contents( $cache );
            if ( is_string( $bytes ) && $bytes !== '' ) $this->output_image( $bytes );
        }

        $file = $this->github->get_file(
            (string) $repository['owner'],
            (string) $repository['repo'],
            $path,
            (string) ( $repository['default_branch'] ?? 'main' )
        );
        if ( is_wp_error( $file ) ) {
            status_header( 404 );
            exit;
        }
        if ( ( $file['encoding'] ?? '' ) !== 'base64' || empty( $file['content'] ) ) {
            $sha = sanitize_text_field( (string) ( $file['sha'] ?? '' ) );
            $file = $sha !== '' ? $this->github->get_blob(
                (string) $repository['owner'],
                (string) $repository['repo'],
                $sha
            ) : $file;
        }
        if ( is_wp_error( $file ) || ( $file['encoding'] ?? '' ) !== 'base64' || empty( $file['content'] ) ) {
            status_header( 404 );
            exit;
        }

        $bytes = base64_decode( preg_replace( '/\s+/', '', (string) ( $file['content'] ?? '' ) ), true );
        $max = (int) apply_filters( 'yougitai_ss_readme_asset_max_bytes', 5 * MB_IN_BYTES );
        if ( $bytes === false || strlen( $bytes ) < 1 || strlen( $bytes ) > $max || ! $this->image_mime( $bytes ) ) {
            status_header( 404 );
            exit;
        }

        if ( wp_mkdir_p( dirname( $cache ) ) ) @file_put_contents( $cache, $bytes, LOCK_EX );
        $this->output_image( $bytes );
    }

    private function asset_url( array $repository, int $snapshot_id, string $path ): string {
        $repository_id = (int) ( $repository['id'] ?? 0 );
        if ( $repository_id < 1 || $snapshot_id < 1 || $path === '' || ! $this->asset_allowed( $repository_id, $path ) ) return '';
        return add_query_arg( [
            'yougitai_readme_asset' => 1,
            'repo' => $repository_id,
            'snapshot' => $snapshot_id,
            'path' => rawurlencode( $path ),
            'sig' => $this->signature( $repository_id, $snapshot_id, $path ),
        ], home_url( '/' ) );
    }

    private function signature( int $repository_id, int $snapshot_id, string $path ): string {
        return hash_hmac( 'sha256', $repository_id . '|' . $snapshot_id . '|' . $path, wp_salt( 'auth' ) );
    }

    private function asset_allowed( int $repository_id, string $path ): bool {
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

    private function image_mime( string $bytes ): string {
        $info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bytes ) : false;
        $mime = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
        return in_array( $mime, [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ], true ) ? $mime : '';
    }

    private function output_image( string $bytes ): void {
        $mime = $this->image_mime( $bytes );
        if ( $mime === '' ) { status_header( 404 ); exit; }
        header( 'Content-Type: ' . $mime );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: inline' );
        header( 'Cache-Control: public, max-age=86400' );
        echo $bytes;
        exit;
    }

    private function markdown_to_html( string $markdown ): string {
        $markdown = str_replace( [ "\r\n", "\r" ], "\n", $markdown );
        $lines = explode( "\n", $markdown );
        $html = [];
        $paragraph = [];
        $code = [];
        $in_code = false;
        $list = '';

        $flush = function () use ( &$paragraph, &$html ): void {
            if ( ! $paragraph ) return;
            $html[] = '<p>' . $this->inline( implode( ' ', array_map( 'trim', $paragraph ) ) ) . '</p>';
            $paragraph = [];
        };
        $close_list = function () use ( &$list, &$html ): void {
            if ( $list !== '' ) { $html[] = '</' . $list . '>'; $list = ''; }
        };

        foreach ( $lines as $line ) {
            if ( preg_match( '/^\s*' . chr(96) . chr(96) . chr(96) . '/', $line ) ) {
                $flush(); $close_list();
                if ( $in_code ) {
                    $html[] = '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
                    $code = []; $in_code = false;
                } else {
                    $in_code = true;
                }
                continue;
            }
            if ( $in_code ) { $code[] = $line; continue; }
            if ( trim( $line ) === '' ) { $flush(); $close_list(); continue; }
            if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $m ) ) {
                $flush(); $close_list(); $n = strlen( $m[1] );
                $html[] = '<h' . $n . '>' . $this->inline( $m[2] ) . '</h' . $n . '>'; continue;
            }
            if ( preg_match( '/^\s*[-*+]\s+(.+)$/', $line, $m ) ) {
                $flush(); if ( $list !== 'ul' ) { $close_list(); $html[] = '<ul>'; $list = 'ul'; }
                $html[] = '<li>' . $this->inline( $m[1] ) . '</li>'; continue;
            }
            if ( preg_match( '/^\s*\d+[.)]\s+(.+)$/', $line, $m ) ) {
                $flush(); if ( $list !== 'ol' ) { $close_list(); $html[] = '<ol>'; $list = 'ol'; }
                $html[] = '<li>' . $this->inline( $m[1] ) . '</li>'; continue;
            }
            $paragraph[] = $line;
        }
        $flush(); $close_list();
        if ( $in_code ) $html[] = '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';

        return wp_kses( implode( "\n", $html ), [
            'h1'=>[], 'h2'=>[], 'h3'=>[], 'h4'=>[], 'h5'=>[], 'h6'=>[],
            'p'=>[], 'ul'=>[], 'ol'=>[], 'li'=>[], 'pre'=>[], 'code'=>[], 'strong'=>[], 'em'=>[],
            'a'=>[ 'href'=>true, 'target'=>true, 'rel'=>true ],
            'img'=>[ 'src'=>true, 'alt'=>true, 'loading'=>true, 'decoding'=>true ],
        ] );
    }

    private function inline( string $text ): string {
        $tokens = [];
        $stash = static function ( string $html ) use ( &$tokens ): string {
            $key = '@@YGA' . count( $tokens ) . '@@';
            $tokens[ $key ] = $html;
            return $key;
        };
        $text = preg_replace_callback( '/!\[([^\]]*)\]\((https?:\/\/[^)\s]+)\)/i', static function ( array $m ) use ( $stash ): string {
            $src = esc_url( $m[2], [ 'http', 'https' ] );
            return $src ? $stash( '<img src="' . esc_attr( $src ) . '" alt="' . esc_attr( wp_strip_all_tags( $m[1] ) ) . '" loading="lazy" decoding="async">' ) : '';
        }, $text ) ?? $text;
        $text = preg_replace_callback( '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/i', static function ( array $m ) use ( $stash ): string {
            $href = esc_url( $m[2], [ 'http', 'https' ] );
            return $href ? $stash( '<a href="' . esc_attr( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $m[1] ) . '</a>' ) : '';
        }, $text ) ?? $text;
        $text = esc_html( $text );
        $text = preg_replace( '/\x60([^\x60]+)\x60/', '<code>$1</code>', $text ) ?? $text;
        $text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text ) ?? $text;
        $text = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text ) ?? $text;
        foreach ( $tokens as $key => $html ) $text = str_replace( esc_html( $key ), $html, $text );
        return $text;
    }
}
