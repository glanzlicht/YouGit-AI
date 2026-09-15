<?php
/**
 * Plugin Name: YougitAI Secure Showcase
 * Description: Secure GitHub repository showcases for WordPress with strict source/public separation, standalone rendering, Elementor compatibility, and multilingual support.
 * Version: 1.0.28
 * Author: YougitAI
 * Text Domain: yougitai-secure-showcase
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'YOUGITAI_SS_VERSION', '1.0.28' );
define( 'YOUGITAI_SS_FILE', __FILE__ );
define( 'YOUGITAI_SS_DIR', plugin_dir_path( __FILE__ ) );
define( 'YOUGITAI_SS_URL', plugin_dir_url( __FILE__ ) );
define( 'YOUGITAI_SS_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register( static function ( string $class ): void {
    $prefix = 'YougitAI\\SecureShowcase\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
    $file = YOUGITAI_SS_DIR . 'src/' . $relative . '.php';
    if ( is_readable( $file ) ) {
        require_once $file;
    }
} );

register_activation_hook( __FILE__, [ 'YougitAI\\SecureShowcase\\Core\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'YougitAI\\SecureShowcase\\Core\\Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', static function (): void {
    $plugin = new YougitAI\SecureShowcase\Core\Plugin();
    $plugin->boot();
} );
