<?php
namespace YougitAI\SecureShowcase\Elementor;

use YougitAI\SecureShowcase\Repository\RepositoryService;

final class Integration {
    public function __construct( private RepositoryService $repositories ) {}

    public function register(): void {
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
    }

    public function register_category( $elements_manager ): void {
        if ( method_exists( $elements_manager, 'add_category' ) ) {
            $elements_manager->add_category( 'yougitai', [
                'title' => __( 'YougitAI', 'yougitai-secure-showcase' ),
                'icon' => 'fa fa-code',
            ] );
        }
    }

    public function register_widgets( $widgets_manager ): void {
        if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
            return;
        }
        require_once YOUGITAI_SS_DIR . 'src/Elementor/ShowcaseWidget.php';
        require_once YOUGITAI_SS_DIR . 'src/Elementor/RepositoryGridWidget.php';
        $widgets_manager->register( new ShowcaseWidget( $this->repositories ) );
        $widgets_manager->register( new RepositoryGridWidget( $this->repositories ) );
    }
}
