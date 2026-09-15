<?php
namespace YougitAI\SecureShowcase\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use YougitAI\SecureShowcase\Frontend\Renderer;
use YougitAI\SecureShowcase\Repository\RepositoryService;

final class RepositoryGridWidget extends Widget_Base {
    public function __construct( private RepositoryService $repositories, $data = [], $args = null ) { parent::__construct( $data, $args ); }
    public function get_name(): string { return 'yougitai_secure_showcase_grid'; }
    public function get_title(): string { return __( 'Secure Repository Grid', 'yougitai-secure-showcase' ); }
    public function get_icon(): string { return 'eicon-gallery-grid'; }
    public function get_categories(): array { return [ 'yougitai' ]; }
    public function get_keywords(): array { return [ 'github', 'repository', 'portfolio', 'grid', 'projects' ]; }
    public function get_style_depends(): array { return [ 'yougitai-ss-frontend' ]; }
    public function get_script_depends(): array { return [ 'yougitai-ss-frontend' ]; }
    protected function register_controls(): void {
        $this->start_controls_section( 'content', [ 'label' => __( 'Content', 'yougitai-secure-showcase' ) ] );
        $this->add_control( 'columns', [ 'label' => __( 'Columns', 'yougitai-secure-showcase' ), 'type' => Controls_Manager::SELECT, 'options' => [ '1'=>'1','2'=>'2','3'=>'3','4'=>'4' ], 'default' => '3' ] );
        $this->add_control( 'theme', [ 'label' => __( 'Theme', 'yougitai-secure-showcase' ), 'type' => Controls_Manager::SELECT, 'options' => [ 'auto'=>__( 'Automatic','yougitai-secure-showcase'),'light'=>__( 'Light','yougitai-secure-showcase'),'dark'=>__( 'Dark','yougitai-secure-showcase') ], 'default'=>'auto' ] );
        $this->end_controls_section();
    }
    protected function render(): void {
        $settings = $this->get_settings_for_display();
        echo ( new Renderer( $this->repositories ) )->render_grid( sanitize_key( (string) ( $settings['theme'] ?? 'auto' ) ), absint( $settings['columns'] ?? 3 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
