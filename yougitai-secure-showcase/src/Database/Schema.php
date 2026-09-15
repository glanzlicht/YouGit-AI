<?php
namespace YougitAI\SecureShowcase\Database;

final class Schema {
    public static function table( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . 'yougitai_ss_' . $name;
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $repositories = self::table( 'repositories' );
        $snapshots = self::table( 'snapshots' );
        $files = self::table( 'files' );
        $rules = self::table( 'rules' );
        $findings = self::table( 'findings' );
        $audit = self::table( 'audit_log' );
        $connections = self::table( 'connections' );
        $oauth_clients = self::table( 'oauth_clients' );
        $oauth_codes = self::table( 'oauth_codes' );
        $oauth_tokens = self::table( 'oauth_tokens' );

        dbDelta( "CREATE TABLE {$repositories} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            owner VARCHAR(191) NOT NULL,
            repo VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            title VARCHAR(191) NOT NULL,
            description TEXT NULL,
            default_branch VARCHAR(191) NOT NULL DEFAULT 'main',
            github_url TEXT NOT NULL,
            visibility VARCHAR(32) NOT NULL DEFAULT 'public',
            access_mode VARCHAR(32) NOT NULL DEFAULT 'public',
            access_password_hash LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            protection_profile VARCHAR(32) NOT NULL DEFAULT 'balanced',
            show_original TINYINT(1) NOT NULL DEFAULT 0,
            is_private TINYINT(1) NOT NULL DEFAULT 0,
            sync_mode VARCHAR(32) NOT NULL DEFAULT 'manual',
            webhook_secret LONGTEXT NULL,
            last_webhook_at DATETIME NULL,
            active_snapshot_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY owner_repo (owner, repo)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$snapshots} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            repository_id BIGINT UNSIGNED NOT NULL,
            source_ref VARCHAR(191) NULL,
            source_sha VARCHAR(64) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            review_status VARCHAR(32) NOT NULL DEFAULT 'pending',
            show_original TINYINT(1) NOT NULL DEFAULT 0,
            file_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            finding_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            critical_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            verification_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            published_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY repository_id (repository_id),
            KEY status (status),
            KEY review_status (review_status)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$files} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            repository_id BIGINT UNSIGNED NOT NULL,
            snapshot_id BIGINT UNSIGNED NOT NULL,
            path TEXT NOT NULL,
            path_hash CHAR(64) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            extension VARCHAR(32) NULL,
            language VARCHAR(64) NULL,
            size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_sha VARCHAR(64) NULL,
            visibility VARCHAR(32) NOT NULL DEFAULT 'public',
            risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            content LONGTEXT NULL,
            redaction_manifest LONGTEXT NULL,
            content_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY snapshot_path (snapshot_id, path_hash),
            KEY repository_snapshot (repository_id, snapshot_id),
            KEY visibility (visibility),
            KEY risk_score (risk_score)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$rules} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            repository_id BIGINT UNSIGNED NOT NULL,
            file_path TEXT NULL,
            rule_type VARCHAR(32) NOT NULL,
            target TEXT NOT NULL,
            action VARCHAR(32) NOT NULL,
            replacement TEXT NULL,
            notes TEXT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'manual',
            confidence DECIMAL(5,2) NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY repository_id (repository_id),
            KEY rule_type (rule_type),
            KEY enabled (enabled)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$findings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            repository_id BIGINT UNSIGNED NOT NULL,
            snapshot_id BIGINT UNSIGNED NOT NULL,
            file_id BIGINT UNSIGNED NULL,
            file_path TEXT NOT NULL,
            finding_type VARCHAR(64) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'medium',
            risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(32) NOT NULL DEFAULT 'scanner',
            title VARCHAR(255) NOT NULL,
            details TEXT NULL,
            recommendation VARCHAR(32) NOT NULL DEFAULT 'review',
            status VARCHAR(32) NOT NULL DEFAULT 'open',
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY repository_snapshot (repository_id, snapshot_id),
            KEY file_id (file_id),
            KEY severity (severity),
            KEY status (status)
        ) {$charset};" );



        dbDelta( "CREATE TABLE {$connections} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(191) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            scopes TEXT NOT NULL,
            repository_ids TEXT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            request_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            revoked_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            KEY expires_at (expires_at),
            KEY revoked_at (revoked_at)
        ) {$charset};" );


        dbDelta( "CREATE TABLE {$oauth_clients} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id VARCHAR(191) NOT NULL,
            client_name VARCHAR(191) NOT NULL,
            redirect_uris LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY client_id (client_id),
            KEY revoked_at (revoked_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$oauth_codes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code_hash CHAR(64) NOT NULL,
            client_id VARCHAR(191) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            redirect_uri TEXT NOT NULL,
            scopes LONGTEXT NOT NULL,
            repository_ids LONGTEXT NOT NULL,
            code_challenge VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code_hash (code_hash),
            KEY client_id (client_id),
            KEY expires_at (expires_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$oauth_tokens} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id VARCHAR(191) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(191) NOT NULL,
            access_token_hash CHAR(64) NOT NULL,
            refresh_token_hash CHAR(64) NOT NULL,
            scopes LONGTEXT NOT NULL,
            repository_ids LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            refresh_expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            request_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            revoked_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY access_token_hash (access_token_hash),
            UNIQUE KEY refresh_token_hash (refresh_token_hash),
            KEY client_id (client_id),
            KEY user_id (user_id),
            KEY expires_at (expires_at),
            KEY revoked_at (revoked_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            repository_id BIGINT UNSIGNED NULL,
            snapshot_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(64) NOT NULL,
            message TEXT NOT NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY repository_id (repository_id),
            KEY snapshot_id (snapshot_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset};" );
    }
}
