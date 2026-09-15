<?php
namespace YougitAI\SecureShowcase\Support;

use YougitAI\SecureShowcase\AI\ProviderFactory;
use YougitAI\SecureShowcase\Security\SecretVault;

final class SystemStatus {
    public function checks(): array {
        $probe = 'yougitai-' . wp_generate_password( 12, false, false );
        $encrypted = SecretVault::encrypt( $probe );
        $vault_ok = $encrypted !== '' && hash_equals( $probe, SecretVault::decrypt( $encrypted ) );
        $https = str_starts_with( home_url(), 'https://' );
        $ai = ProviderFactory::make();
        return [
            [ 'label' => __( 'PHP 8.1 or newer', 'yougitai-secure-showcase' ), 'ok' => version_compare( PHP_VERSION, '8.1', '>=' ), 'detail' => PHP_VERSION ],
            [ 'label' => __( 'HTTPS site URL', 'yougitai-secure-showcase' ), 'ok' => $https, 'detail' => $https ? __( 'Enabled', 'yougitai-secure-showcase' ) : __( 'Recommended before connecting external services', 'yougitai-secure-showcase' ) ],
            [ 'label' => __( 'Encrypted secret storage', 'yougitai-secure-showcase' ), 'ok' => $vault_ok, 'detail' => $vault_ok ? __( 'Working', 'yougitai-secure-showcase' ) : __( 'Unavailable', 'yougitai-secure-showcase' ) ],
            [ 'label' => __( 'Background queue', 'yougitai-secure-showcase' ), 'ok' => ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON || function_exists( 'as_enqueue_async_action' ), 'detail' => function_exists( 'as_enqueue_async_action' ) ? 'Action Scheduler' : 'WP-Cron' ],
            [ 'label' => __( 'Elementor integration', 'yougitai-secure-showcase' ), 'ok' => did_action( 'elementor/loaded' ) || class_exists( '\\Elementor\\Plugin' ), 'detail' => ( did_action( 'elementor/loaded' ) || class_exists( '\\Elementor\\Plugin' ) ) ? __( 'Available', 'yougitai-secure-showcase' ) : __( 'Standalone mode active', 'yougitai-secure-showcase' ) ],
            [ 'label' => __( 'Active AI mode', 'yougitai-secure-showcase' ), 'ok' => (string) get_option( 'yougitai_ss_ai_provider', 'openai' ) === 'direct' || $ai->is_configured(), 'detail' => (string) get_option( 'yougitai_ss_ai_provider', 'openai' ) ],
        ];
    }
}
