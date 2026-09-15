<?php
namespace YougitAI\SecureShowcase\GitHub;

use YougitAI\SecureShowcase\Security\SecretVault;
use WP_Error;

final class Client {
    private function token(): string {
        $encrypted = (string) get_option( 'yougitai_ss_github_token', '' );
        return SecretVault::decrypt( $encrypted );
    }

    public function get_repository( string $owner, string $repo ) {
        return $this->request( sprintf( 'https://api.github.com/repos/%s/%s', rawurlencode( $owner ), rawurlencode( $repo ) ) );
    }

    public function list_repositories(): array|WP_Error {
        if ( $this->token() === '' ) {
            return new WP_Error( 'yougitai_github_not_connected', __( 'Connect GitHub first to load your repositories.', 'yougitai-secure-showcase' ) );
        }

        $cached = get_transient( 'yougitai_ss_github_repository_picker' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $repositories = [];
        for ( $page = 1; $page <= 10; $page++ ) {
            $url = add_query_arg( [
                'per_page' => 100,
                'page' => $page,
                'sort' => 'updated',
                'direction' => 'desc',
                'visibility' => 'all',
                'affiliation' => 'owner,collaborator,organization_member',
            ], 'https://api.github.com/user/repos' );
            $batch = $this->request( $url );
            if ( is_wp_error( $batch ) ) {
                return $batch;
            }
            if ( ! is_array( $batch ) ) {
                break;
            }
            foreach ( $batch as $repository ) {
                if ( ! is_array( $repository ) || empty( $repository['id'] ) || empty( $repository['full_name'] ) ) {
                    continue;
                }
                $repositories[] = [
                    'id' => (int) $repository['id'],
                    'name' => sanitize_text_field( (string) ( $repository['name'] ?? '' ) ),
                    'full_name' => sanitize_text_field( (string) $repository['full_name'] ),
                    'owner' => sanitize_text_field( (string) ( $repository['owner']['login'] ?? '' ) ),
                    'html_url' => esc_url_raw( (string) ( $repository['html_url'] ?? '' ) ),
                    'private' => ! empty( $repository['private'] ),
                    'default_branch' => sanitize_text_field( (string) ( $repository['default_branch'] ?? 'main' ) ),
                    'updated_at' => sanitize_text_field( (string) ( $repository['updated_at'] ?? '' ) ),
                    'description' => sanitize_textarea_field( (string) ( $repository['description'] ?? '' ) ),
                ];
            }
            if ( count( $batch ) < 100 ) {
                break;
            }
        }

        set_transient( 'yougitai_ss_github_repository_picker', $repositories, MINUTE_IN_SECONDS );
        return $repositories;
    }

    public function clear_repository_cache(): void {
        delete_transient( 'yougitai_ss_github_repository_picker' );
    }

    public function get_tree( string $owner, string $repo, string $ref = 'HEAD' ) {
        $tree = $this->request( sprintf( 'https://api.github.com/repos/%s/%s/git/trees/%s?recursive=1', rawurlencode( $owner ), rawurlencode( $repo ), rawurlencode( $ref ) ) );
        if ( is_wp_error( $tree ) || empty( $tree['truncated'] ) ) {
            return $tree;
        }
        return $this->get_tree_expanded( $owner, $repo, $ref );
    }

    private function get_tree_expanded( string $owner, string $repo, string $ref ) {
        $root = $this->request( sprintf( 'https://api.github.com/repos/%s/%s/git/trees/%s', rawurlencode( $owner ), rawurlencode( $repo ), rawurlencode( $ref ) ) );
        if ( is_wp_error( $root ) ) return $root;
        $items = [];
        $queue = [ [ 'prefix' => '', 'sha' => (string) ( $root['sha'] ?? $ref ), 'tree' => $root['tree'] ?? [] ] ];
        $limit = (int) apply_filters( 'yougitai_ss_tree_node_limit', 20000 );
        while ( $queue && count( $items ) < $limit ) {
            $current = array_shift( $queue );
            foreach ( $current['tree'] as $node ) {
                $path = $current['prefix'] === '' ? (string) $node['path'] : $current['prefix'] . '/' . (string) $node['path'];
                if ( ( $node['type'] ?? '' ) === 'tree' ) {
                    $child = $this->request( sprintf( 'https://api.github.com/repos/%s/%s/git/trees/%s', rawurlencode( $owner ), rawurlencode( $repo ), rawurlencode( (string) $node['sha'] ) ) );
                    if ( ! is_wp_error( $child ) ) $queue[] = [ 'prefix' => $path, 'sha' => (string) $node['sha'], 'tree' => $child['tree'] ?? [] ];
                    continue;
                }
                $copy = $node;
                $copy['path'] = $path;
                $items[] = $copy;
                if ( count( $items ) >= $limit ) break;
            }
        }
        return [ 'sha' => (string) ( $root['sha'] ?? $ref ), 'tree' => $items, 'truncated' => (bool) $queue ];
    }

    public function get_file( string $owner, string $repo, string $path, string $ref ) {
        return $this->request( sprintf(
            'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
            rawurlencode( $owner ),
            rawurlencode( $repo ),
            implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ),
            rawurlencode( $ref )
        ) );
    }

    private function request( string $url ) {
        $headers = [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'YougitAI-Secure-Showcase/' . YOUGITAI_SS_VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        $token = $this->token();
        if ( $token !== '' ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $response = wp_remote_get( $url, [
            'timeout' => 20,
            'headers' => $headers,
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'yougitai_github_error', isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : __( 'GitHub request failed.', 'yougitai-secure-showcase' ), [ 'status' => $code ] );
        }
        return $body;
    }
}
