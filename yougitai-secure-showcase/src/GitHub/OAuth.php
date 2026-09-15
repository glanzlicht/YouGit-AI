<?php
namespace YougitAI\SecureShowcase\GitHub;

use YougitAI\SecureShowcase\Security\SecretVault;
use WP_Error;

final class OAuth {
    public function configured(): bool {
        return trim( (string) get_option( 'yougitai_ss_github_oauth_client_id', '' ) ) !== ''
            && SecretVault::decrypt( (string) get_option( 'yougitai_ss_github_oauth_client_secret', '' ) ) !== '';
    }

    public function callback_url(): string {
        return admin_url( 'admin-post.php?action=yougitai_ss_github_oauth_callback' );
    }

    public function authorization_url( int $user_id ) {
        if ( ! $this->configured() ) {
            return new WP_Error( 'yougitai_github_oauth_not_configured', __( 'GitHub OAuth is not configured yet.', 'yougitai-secure-showcase' ) );
        }
        $state = wp_generate_password( 48, false, false );
        set_transient( 'yougitai_ss_github_oauth_state_' . $user_id, hash( 'sha256', $state ), 10 * MINUTE_IN_SECONDS );
        return add_query_arg( [
            'client_id' => (string) get_option( 'yougitai_ss_github_oauth_client_id', '' ),
            'redirect_uri' => $this->callback_url(),
            'scope' => 'repo read:user',
            'state' => $state,
            'allow_signup' => 'false',
        ], 'https://github.com/login/oauth/authorize' );
    }

    public function exchange( int $user_id, string $code, string $state ) {
        $expected = (string) get_transient( 'yougitai_ss_github_oauth_state_' . $user_id );
        delete_transient( 'yougitai_ss_github_oauth_state_' . $user_id );
        if ( $expected === '' || $state === '' || ! hash_equals( $expected, hash( 'sha256', $state ) ) ) {
            return new WP_Error( 'yougitai_github_oauth_state', __( 'GitHub OAuth state validation failed. Please start the connection again.', 'yougitai-secure-showcase' ) );
        }
        $secret = SecretVault::decrypt( (string) get_option( 'yougitai_ss_github_oauth_client_secret', '' ) );
        $response = wp_remote_post( 'https://github.com/login/oauth/access_token', [
            'timeout' => 20,
            'headers' => [ 'Accept' => 'application/json', 'User-Agent' => 'YougitAI-Secure-Showcase/' . YOUGITAI_SS_VERSION ],
            'body' => [
                'client_id' => (string) get_option( 'yougitai_ss_github_oauth_client_id', '' ),
                'client_secret' => $secret,
                'code' => $code,
                'redirect_uri' => $this->callback_url(),
            ],
        ] );
        if ( is_wp_error( $response ) ) return $response;
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $token = is_array( $body ) ? trim( (string) ( $body['access_token'] ?? '' ) ) : '';
        if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 || $token === '' ) {
            return new WP_Error( 'yougitai_github_oauth_exchange', isset( $body['error_description'] ) ? sanitize_text_field( $body['error_description'] ) : __( 'GitHub OAuth token exchange failed.', 'yougitai-secure-showcase' ) );
        }
        $encrypted = SecretVault::encrypt( $token );
        if ( $encrypted === '' ) return new WP_Error( 'yougitai_github_oauth_encrypt', __( 'GitHub OAuth token could not be encrypted on this server.', 'yougitai-secure-showcase' ) );
        update_option( 'yougitai_ss_github_token', $encrypted, false );
        update_option( 'yougitai_ss_github_auth_method', 'oauth', false );
        return true;
    }
}
