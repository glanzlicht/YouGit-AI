<?php
namespace YougitAI\SecureShowcase\AI;

final class ProviderFactory {
    public static function make(): ProviderInterface {
        if ( ! (bool) get_option( 'yougitai_ss_external_ai_enabled', false ) ) {
            return new NullProvider();
        }
        $provider = sanitize_key( (string) get_option( 'yougitai_ss_ai_provider', 'openai' ) );
        return match ( $provider ) {
            'openai' => new OpenAIProvider(),
            'anthropic' => new AnthropicProvider(),
            'gemini' => new GeminiProvider(),
            'direct' => new NullProvider(),
            default => new NullProvider(),
        };
    }
}
