<?php
namespace YougitAI\SecureShowcase\AI;

use YougitAI\SecureShowcase\Security\SecretVault;
use WP_Error;

final class AnthropicProvider implements ProviderInterface {
    public function is_configured(): bool { return $this->api_key() !== ''; }

    private function api_key(): string {
        $stored = trim( (string) get_option( 'yougitai_ss_anthropic_api_key', '' ) );
        if ( $stored === '' ) return '';
        return ( str_starts_with( $stored, 'sodium:' ) || str_starts_with( $stored, 'openssl:' ) ) ? SecretVault::decrypt( $stored ) : $stored;
    }

    public function analyze_file( string $path, string $content, array $context = [] ) {
        $api_key = $this->api_key();
        if ( $api_key === '' ) return new WP_Error( 'yougitai_ai_not_configured', __( 'The AI provider is not configured.', 'yougitai-secure-showcase' ) );
        $model = sanitize_text_field( (string) get_option( 'yougitai_ss_anthropic_model', 'claude-sonnet-5' ) );
        $profile = sanitize_key( (string) ( $context['profile'] ?? 'balanced' ) );
        $payload_content = mb_substr( $content, 0, ReviewSchema::max_chars() );
        $schema = ReviewSchema::json_schema();
        $prompt = ReviewSchema::user_prompt( $path, $payload_content, $profile ) . "\n\nReturn one JSON object matching this JSON Schema exactly:\n" . wp_json_encode( $schema );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 45,
            'headers' => [ 'x-api-key' => $api_key, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode( [
                'model' => $model,
                'max_tokens' => 1400,
                'system' => ReviewSchema::system_prompt(),
                'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 ) {
            $message = $body['error']['message'] ?? __( 'AI analysis failed.', 'yougitai-secure-showcase' );
            return new WP_Error( 'yougitai_ai_error', sanitize_text_field( $message ), [ 'status' => $code ] );
        }
        $text = '';
        foreach ( (array) ( $body['content'] ?? [] ) as $part ) {
            if ( ( $part['type'] ?? '' ) === 'text' ) $text .= (string) ( $part['text'] ?? '' );
        }
        $text = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', trim( $text ) );
        $normalized = ReviewSchema::normalize( json_decode( $text, true ) );
        return $normalized ?? new WP_Error( 'yougitai_ai_invalid_response', __( 'The AI provider returned an invalid review response.', 'yougitai-secure-showcase' ) );
    }
}
