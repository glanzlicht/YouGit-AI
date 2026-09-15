<?php
namespace YougitAI\SecureShowcase\I18n;

final class Loader {
    public function register(): void {
        load_plugin_textdomain(
            'yougitai-secure-showcase',
            false,
            dirname( YOUGITAI_SS_BASENAME ) . '/languages'
        );
    }
}
