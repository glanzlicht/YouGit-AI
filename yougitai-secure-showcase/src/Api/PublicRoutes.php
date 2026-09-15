<?php
namespace YougitAI\SecureShowcase\Api;

use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Security\ShowcaseAccess;
use WP_REST_Request;
use WP_REST_Response;

final class PublicRoutes {
    private ShowcaseAccess $access;

    public function __construct( private RepositoryService $repositories ) {
        $this->access = new ShowcaseAccess();
    }

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
    }

    public function routes(): void {
        register_rest_route( 'yougitai-secure-showcase/v1', '/showcases/(?P<slug>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'showcase' ],
            'permission_callback' => '__return_true',
            'args' => [ 'slug' => [ 'sanitize_callback' => 'sanitize_title' ] ],
        ] );
        register_rest_route( 'yougitai-secure-showcase/v1', '/showcases/(?P<slug>[a-z0-9-]+)/files/(?P<file_id>\d+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'file' ],
            'permission_callback' => '__return_true',
            'args' => [
                'slug' => [ 'sanitize_callback' => 'sanitize_title' ],
                'file_id' => [ 'sanitize_callback' => 'absint' ],
            ],
        ] );
    }

    public function showcase( WP_REST_Request $request ): WP_REST_Response {
        $repository = $this->repositories->find_by_slug( (string) $request['slug'] );
        if ( ! $repository || ! $this->access->is_allowed( $repository ) ) {
            return new WP_REST_Response( [ 'message' => __( 'This showcase is not available.', 'yougitai-secure-showcase' ) ], 404 );
        }
        $files = $this->repositories->files_for_public_repository( (int) $repository['id'], (int) $repository['active_snapshot_id'] );
        $profile = $this->repositories->public_showcase_profile( (int) $repository['id'], (int) $repository['active_snapshot_id'] );
        return new WP_REST_Response( [
            'id' => (int) $repository['id'],
            'slug' => $repository['slug'],
            'title' => $repository['title'],
            'description' => $repository['description'],
            'owner' => $repository['owner'],
            'repository' => $repository['repo'],
            'snapshot_id' => (int) $repository['active_snapshot_id'],
            'profile' => $profile,
            'files' => array_map( static fn( array $file ): array => [
                'id' => (int) $file['id'],
                'path' => $file['path'],
                'filename' => $file['filename'],
                'language' => $file['language'],
                'size' => (int) $file['size'],
                'redacted' => $file['visibility'] === 'redacted',
                'protected' => $file['visibility'] === 'hidden',
                'visibility' => $file['visibility'],
                'redaction_count' => count( is_array( json_decode( (string) ( $file['redaction_manifest'] ?? '' ), true ) ) ? json_decode( (string) $file['redaction_manifest'], true ) : [] ),
            ], $files ),
        ], 200 );
    }

    public function file( WP_REST_Request $request ): WP_REST_Response {
        $repository = $this->repositories->find_by_slug( (string) $request['slug'] );
        if ( ! $repository || ! $this->access->is_allowed( $repository ) ) {
            return new WP_REST_Response( [ 'message' => __( 'This showcase is not available.', 'yougitai-secure-showcase' ) ], 404 );
        }
        $file = $this->repositories->public_file( (int) $repository['id'], (int) $repository['active_snapshot_id'], absint( $request['file_id'] ) );
        if ( ! $file ) {
            return new WP_REST_Response( [ 'message' => __( 'File not found.', 'yougitai-secure-showcase' ) ], 404 );
        }
        return new WP_REST_Response( [
            'id' => (int) $file['id'],
            'path' => $file['path'],
            'filename' => $file['filename'],
            'language' => $file['language'],
            'redacted' => $file['visibility'] === 'redacted',
            'protected' => $file['visibility'] === 'hidden',
            'visibility' => $file['visibility'],
            'redactions' => is_array( json_decode( (string) ( $file['redaction_manifest'] ?? '' ), true ) ) ? json_decode( (string) $file['redaction_manifest'], true ) : [],
            'content' => $file['visibility'] === 'hidden' ? '' : $file['content'],
        ], 200 );
    }
}
