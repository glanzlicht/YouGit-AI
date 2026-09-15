<?php
namespace YougitAI\SecureShowcase\Security;

final class Storage {
    public static function base_dir(): string {
        $uploads = wp_upload_dir();
        return trailingslashit( $uploads['basedir'] ) . 'yougitai-secure-showcase';
    }

    public static function ensure_directories(): void {
        $dir = self::base_dir();
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // The plugin does not persist original repository source files. This directory is
        // reserved for future non-source artifacts and denied by default as defense in depth.
        $deny = "# YougitAI Secure Showcase\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
        if ( is_dir( $dir ) ) {
            @file_put_contents( trailingslashit( $dir ) . '.htaccess', $deny );
            @file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php\nhttp_response_code(404);\nexit;\n" );
            @file_put_contents( trailingslashit( $dir ) . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>" );
        }
    }
}
