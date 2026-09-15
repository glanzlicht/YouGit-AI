<?php
namespace YougitAI\SecureShowcase\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use YougitAI\SecureShowcase\Frontend\Renderer;
use YougitAI\SecureShowcase\Repository\RepositoryService;

final class ShowcaseWidget extends Widget_Base {
    public function __construct( private RepositoryService $repositories, $data = [], $args = null ) {
        parent::__construct( $data, $args );
    }

    public function get_name(): string { return 'yougitai_secure_showcase'; }
    public function get_title(): string { return __( 'Secure Repository Showcase', 'yougitai-secure-showcase' ); }
    public function get_icon(): string { return 'eicon-code'; }
    public function get_categories(): array { return [ 'yougitai' ]; }
    public function get_keywords(): array { return [ 'github', 'repository', 'code', 'showcase', 'portfolio' ]; }
    public function get_style_depends(): array { return [ 'yougitai-ss-frontend' ]; }
    public function get_script_depends(): array { return [ 'yougitai-ss-frontend' ]; }

    protected function register_controls(): void {
        $options = [];
        foreach ( $this->repositories->all() as $repo ) {
            if ( $repo['status'] === 'published' ) {
                $options[ (string) $repo['id'] ] = $repo['title'];
            }
        }
        $this->start_controls_section( 'content', [ 'label' => __( 'Content', 'yougitai-secure-showcase' ) ] );
        $this->add_control( 'repository_id', [
            'label' => __( 'Repository', 'yougitai-secure-showcase' ),
            'type' => Controls_Manager::SELECT,
            'options' => $options,
            'default' => $options ? (string) array_key_first( $options ) : '',
        ] );
        $this->add_control( 'theme', [
            'label' => __( 'Theme', 'yougitai-secure-showcase' ),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'auto' => __( 'Automatic', 'yougitai-secure-showcase' ),
                'light' => __( 'Light', 'yougitai-secure-showcase' ),
                'dark' => __( 'Dark', 'yougitai-secure-showcase' ),
            ],
            'default' => 'auto',
        ] );
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $renderer = new Renderer( $this->repositories );
        echo $renderer->render( [
            'id' => absint( $settings['repository_id'] ?? 0 ),
            'theme' => sanitize_key( (string) ( $settings['theme'] ?? 'auto' ) ),
        ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
