<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;
if ( ! get_option( 'yougitai_ss_delete_data_on_uninstall', false ) ) return;

global $wpdb;
foreach ( [ 'oauth_tokens', 'oauth_codes', 'oauth_clients', 'connections', 'audit_log', 'findings', 'rules', 'files', 'snapshots', 'repositories' ] as $suffix ) {
    $table = $wpdb->prefix . 'yougitai_ss_' . $suffix;
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( [
    'yougitai_ss_version','yougitai_ss_showcase_slug','yougitai_ss_external_ai_enabled','yougitai_ss_ai_provider',
    'yougitai_ss_openai_model','yougitai_ss_openai_api_key','yougitai_ss_anthropic_model','yougitai_ss_anthropic_api_key',
    'yougitai_ss_gemini_model','yougitai_ss_gemini_api_key','yougitai_ss_github_token','yougitai_ss_github_auth_method',
    'yougitai_ss_github_oauth_client_id','yougitai_ss_github_oauth_client_secret','yougitai_ss_delete_data_on_uninstall'
] as $option ) delete_option( $option );
wp_clear_scheduled_hook( 'yougitai_ss_sync_repository' );
wp_clear_scheduled_hook( 'yougitai_ss_daily_cleanup' );
