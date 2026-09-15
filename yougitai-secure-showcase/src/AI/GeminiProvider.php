<?php
namespace YougitAI\SecureShowcase\AI;

use YougitAI\SecureShowcase\Security\SecretVault;
use WP_Error;

final class GeminiProvider implements ProviderInterface {
    public function is_configured(): bool { return $this->api_key() !== ''; }

    private function api_key(): string {
        $stored = trim( (string) get_option( 'yougitai_ss_gemini_api_key', '' ) );
        if ( $stored === '' ) return '';
        return ( str_starts_with( $stored, 'sodium:' ) || str_starts_with( $stored, 'openssl:' ) ) ? SecretVault::decrypt( $stored ) : $stored;
    }

    public function analyze_file( string $path, string $content, array $context = [] ) {
        $api_key = $this->api_key();
        if ( $api_key === '' ) return new WP_Error( 'yougitai_ai_not_configured', __( 'The AI provider is not configured.', 'yougitai-secure-showcase' ) );
        $model = sanitize_text_field( (string) get_option( 'yougitai_ss_gemini_model', 'gemini-3.8-flash' ) );
        $profile = sanitize_key( (string) ( $context['profile'] ?? 'balanced' ) );
        $payload_content = mb_substr( $content, 0, ReviewSchema::max_chars() );
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
        $response = wp_remote_post( $endpoint, [
            'timeout' => 45,
            'headers' => [ 'x-goog-api-key' => $api_key, 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode( [
                'systemInstruction' => [ 'parts' => [ [ 'text' => ReviewSchema::system_prompt() ] ] ],
                'contents' => [ [ 'role' => 'user', 'parts' => [ [ 'text' => ReviewSchema::user_prompt( $path, $payload_content, $profile ) ] ] ] ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => ReviewSchema::json_schema(),
                ],
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
        foreach ( (array) ( $body['candidates'][0]['content']['parts'] ?? [] ) as $part ) {
            if ( isset( $part['text'] ) ) $text .= (string) $part['text'];
        }
        $normalized = ReviewSchema::normalize( json_decode( trim( $text ), true ) );
        return $normalized ?? new WP_Error( 'yougitai_ai_invalid_response', __( 'The AI provider returned an invalid review response.', 'yougitai-secure-showcase' ) );
    }
}
