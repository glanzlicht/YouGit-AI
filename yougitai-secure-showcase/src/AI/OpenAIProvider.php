<?php
namespace YougitAI\SecureShowcase\AI;

use YougitAI\SecureShowcase\Security\SecretVault;
use WP_Error;

final class OpenAIProvider implements ProviderInterface {
    public function is_configured(): bool {
        return $this->api_key() !== '';
    }

    private function api_key(): string {
        $stored = trim( (string) get_option( 'yougitai_ss_openai_api_key', '' ) );
        if ( $stored === '' ) {
            return '';
        }
        if ( str_starts_with( $stored, 'sodium:' ) || str_starts_with( $stored, 'openssl:' ) ) {
            return SecretVault::decrypt( $stored );
        }
        // Backward compatibility for pre-0.3 installs; saving settings migrates to encrypted storage.
        return $stored;
    }

    public function analyze_file( string $path, string $content, array $context = [] ) {
        $api_key = $this->api_key();
        if ( $api_key === '' ) {
            return new WP_Error( 'yougitai_ai_not_configured', __( 'The AI provider is not configured.', 'yougitai-secure-showcase' ) );
        }

        $model = sanitize_text_field( (string) get_option( 'yougitai_ss_openai_model', 'gpt-5.6-luna' ) );
        $payload_content = mb_substr( $content, 0, ReviewSchema::max_chars() );
        $profile = sanitize_key( (string) ( $context['profile'] ?? 'balanced' ) );

        $system = ReviewSchema::system_prompt();
        $user = ReviewSchema::user_prompt( $path, $payload_content, $profile );

        $response = wp_remote_post( 'https://api.openai.com/v1/responses', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model' => $model,
                'input' => [
                    [ 'role' => 'system', 'content' => $system ],
                    [ 'role' => 'user', 'content' => $user ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'showcase_security_review',
                        'strict' => true,
                        'schema' => ReviewSchema::json_schema(),
                    ],
                ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 ) {
            $message = $body['error']['message'] ?? __( 'AI analysis failed.', 'yougitai-secure-showcase' );
            return new WP_Error( 'yougitai_ai_error', sanitize_text_field( $message ), [ 'status' => $code ] );
        }

        $text = '';
        foreach ( (array) ( $body['output'] ?? [] ) as $item ) {
            foreach ( (array) ( $item['content'] ?? [] ) as $part ) {
                if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
                    $text .= $part['text'];
                }
            }
        }
        $parsed = json_decode( $text, true );
        $normalized = ReviewSchema::normalize( $parsed );
        return $normalized ?? new WP_Error( 'yougitai_ai_invalid_response', __( 'The AI provider returned an invalid review response.', 'yougitai-secure-showcase' ) );
    }
}
