<?php
namespace YougitAI\SecureShowcase\Api;

use YougitAI\SecureShowcase\Audit\Logger;
use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Security\SecretVault;
use YougitAI\SecureShowcase\Sync\SyncManager;
use WP_REST_Request;
use WP_REST_Response;

final class GitHubWebhookRoutes {
    public function __construct( private RepositoryService $repositories, private SyncManager $sync ) {}

    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
    }

    public function routes(): void {
        register_rest_route( 'yougitai-secure-showcase/v1', '/github/webhook/(?P<repository_id>\d+)', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle' ],
            'permission_callback' => '__return_true',
            'args' => [ 'repository_id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );
    }

    public function handle( WP_REST_Request $request ): WP_REST_Response {
        $repository_id = absint( $request['repository_id'] );
        $repository = $this->repositories->find( $repository_id );
        if ( ! $repository || $repository['sync_mode'] !== 'webhook' || empty( $repository['webhook_secret'] ) ) {
            return new WP_REST_Response( [ 'message' => __( 'Webhook is not configured.', 'yougitai-secure-showcase' ) ], 404 );
        }
        $secret = SecretVault::decrypt( (string) $repository['webhook_secret'] );
        if ( $secret === '' ) {
            return new WP_REST_Response( [ 'message' => __( 'Webhook secret could not be read.', 'yougitai-secure-showcase' ) ], 500 );
        }
        $raw = $request->get_body();
        $signature = (string) $request->get_header( 'x-hub-signature-256' );
        $expected = 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
        if ( $signature === '' || ! hash_equals( $expected, $signature ) ) {
            return new WP_REST_Response( [ 'message' => __( 'Invalid webhook signature.', 'yougitai-secure-showcase' ) ], 401 );
        }

        $event = sanitize_key( (string) $request->get_header( 'x-github-event' ) );
        if ( $event === 'ping' ) {
            return new WP_REST_Response( [ 'ok' => true ], 200 );
        }
        if ( $event !== 'push' ) {
            return new WP_REST_Response( [ 'ok' => true, 'ignored' => true ], 202 );
        }

        $payload = json_decode( $raw, true );
        $full_name = (string) ( $payload['repository']['full_name'] ?? '' );
        $ref = (string) ( $payload['ref'] ?? '' );
        $expected_name = $repository['owner'] . '/' . $repository['repo'];
        $expected_ref = 'refs/heads/' . $repository['default_branch'];
        if ( ! hash_equals( strtolower( $expected_name ), strtolower( $full_name ) ) || $ref !== $expected_ref ) {
            return new WP_REST_Response( [ 'ok' => true, 'ignored' => true ], 202 );
        }

        $this->sync->enqueue( $repository_id );
        ( new Logger() )->log( 'webhook_sync_queued', __( 'GitHub push queued for secure import.', 'yougitai-secure-showcase' ), $repository_id );
        return new WP_REST_Response( [ 'ok' => true, 'queued' => true ], 202 );
    }
}
