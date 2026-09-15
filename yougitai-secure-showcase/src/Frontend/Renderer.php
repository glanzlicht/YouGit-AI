<?php
namespace YougitAI\SecureShowcase\Frontend;

use YougitAI\SecureShowcase\Repository\RepositoryService;
use YougitAI\SecureShowcase\Security\ShowcaseAccess;

final class Renderer {
    private ShowcaseAccess $access;

    public function __construct( private RepositoryService $repositories ) {
        $this->access = new ShowcaseAccess();
    }

    public function register(): void {
        add_shortcode( 'yougitai_showcase', [ $this, 'shortcode' ] );
        add_shortcode( 'yougitai_showcase_grid', [ $this, 'grid_shortcode' ] );
        add_action( 'init', [ $this, 'rewrite' ] );
        add_filter( 'query_vars', [ $this, 'query_vars' ] );
        add_action( 'template_redirect', [ $this, 'template_redirect' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_content_assets' ], 20 );
        add_filter( 'body_class', [ $this, 'body_class' ] );
    }

    public function body_class( array $classes ): array {
        if ( ! get_query_var( 'yougitai_showcase' ) && ! get_query_var( 'yougitai_showcase_index' ) ) {
            return $classes;
        }

        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            try {
                $kit_id = (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
                if ( $kit_id > 0 ) {
                    $classes[] = 'elementor-kit-' . $kit_id;
                }
            } catch ( \Throwable $e ) {
                // Elementor integration is optional; fall back to the active theme.
            }
        }

        return array_values( array_unique( $classes ) );
    }

    public function register_assets(): void {
        wp_register_style( 'yougitai-ss-frontend', YOUGITAI_SS_URL . 'assets/css/frontend.css', [], YOUGITAI_SS_VERSION );

        $colors = [
            'surface' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_surface', '#0d1117' ) ) ?: '#0d1117',
            'panel' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_panel', '#161b22' ) ) ?: '#161b22',
            'code' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_code', '#0d1117' ) ) ?: '#0d1117',
            'border' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_border', '#30363d' ) ) ?: '#30363d',
            'text' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_text', '#c9d1d9' ) ) ?: '#c9d1d9',
            'muted' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_muted', '#8b949e' ) ) ?: '#8b949e',
            'accent' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_accent', '#58a6ff' ) ) ?: '#58a6ff',
            'accent_hover' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_accent_hover', '#79c0ff' ) ) ?: '#79c0ff',
            'selected' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_selected', '#1f2d3d' ) ) ?: '#1f2d3d',
            'warning_bg' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_warning_bg', '#3b2f0b' ) ) ?: '#3b2f0b',
            'warning_border' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_warning_border', '#9e6a03' ) ) ?: '#9e6a03',
            'warning_text' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_warning_text', '#e3b341' ) ) ?: '#e3b341',
            'blackout' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_blackout', '#020409' ) ) ?: '#020409',
            'blackout_edge' => sanitize_hex_color( (string) get_option( 'yougitai_ss_color_blackout_edge', '#30363d' ) ) ?: '#30363d',
        ];
        $custom_css = sprintf(
            '.yougitai-showcase,.yougitai-portfolio-grid{--yg-bg:%1$s;--yg-panel:%2$s;--yg-code:%3$s;--yg-border:%4$s;--yg-text:%5$s;--yg-muted:%6$s;--yg-link:%7$s;--yg-link-hover:%8$s;--yg-link-active-bg:%9$s;--yg-focus:%7$s;--yg-warning-bg:%10$s;--yg-warning-border:%11$s;--yg-warning-text:%12$s;--yg-blackout:%13$s;--yg-blackout-edge:%14$s}.yougitai-showcase[data-theme="dark"],.yougitai-portfolio-grid[data-theme="dark"]{color:%5$s}.yougitai-repositories-index .yougitai-portfolio-grid[data-theme="dark"]{color:%5$s!important}.yougitai-repositories-index .yougitai-project-card{background:%2$s!important;border-color:%4$s!important;color:%5$s!important}.yougitai-repositories-index .yougitai-project-card h3{color:%5$s!important}.yougitai-repositories-index .yougitai-project-card p,.yougitai-repositories-index .yougitai-project-meta{color:%6$s!important}.yougitai-repositories-index .yougitai-badge,.yougitai-repositories-index .yougitai-chips span{background:%3$s!important;border-color:%4$s!important;color:%5$s!important}.yougitai-repositories-index .yougitai-project-link,.yougitai-repositories-index .yougitai-project-link:visited{color:%7$s!important}.yougitai-repositories-index .yougitai-project-link:hover,.yougitai-repositories-index .yougitai-project-link:focus-visible{color:%8$s!important}.yougitai-showcase[data-theme="dark"] .yougitai-tree a.is-active{background:%9$s!important;color:%5$s!important}.yougitai-showcase[data-theme="dark"] .yougitai-tree a:hover,.yougitai-showcase[data-theme="dark"] .yougitai-tree a:focus-visible{background:%3$s!important;color:%5$s!important}',
            $colors['surface'], $colors['panel'], $colors['code'], $colors['border'], $colors['text'], $colors['muted'], $colors['accent'], $colors['accent_hover'], $colors['selected'], $colors['warning_bg'], $colors['warning_border'], $colors['warning_text'], $colors['blackout'], $colors['blackout_edge']
        );
        wp_add_inline_style( 'yougitai-ss-frontend', $custom_css );
        wp_register_script( 'yougitai-ss-frontend', YOUGITAI_SS_URL . 'assets/js/frontend.js', [], YOUGITAI_SS_VERSION, true );
    }

    public function maybe_enqueue_content_assets(): void {
        if ( ! is_singular() ) {
            return;
        }
        global $post;
        if ( ! $post || ! isset( $post->post_content ) ) {
            return;
        }
        $content = (string) $post->post_content;
        if ( has_shortcode( $content, 'yougitai_showcase' ) || has_shortcode( $content, 'yougitai_showcase_grid' ) ) {
            wp_enqueue_style( 'yougitai-ss-frontend' );
            wp_enqueue_script( 'yougitai-ss-frontend' );
        }
    }

    public function rewrite(): void {
        $slug = sanitize_title( (string) get_option( 'yougitai_ss_showcase_slug', 'repositories' ) );
        add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?yougitai_showcase_index=1', 'top' );
        add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/([^/]+)/?$', 'index.php?yougitai_showcase=$matches[1]', 'top' );
    }

    public function query_vars( array $vars ): array {
        $vars[] = 'yougitai_showcase';
        $vars[] = 'yougitai_showcase_index';
        return $vars;
    }

    public function template_redirect(): void {
        if ( get_query_var( 'yougitai_showcase_index' ) ) {
            status_header( 200 );
            nocache_headers();
            $this->register_assets();
            wp_enqueue_style( 'yougitai-ss-frontend' );
            wp_enqueue_script( 'yougitai-ss-frontend' );
            get_header();
            $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
            $language_key = str_starts_with( strtolower( (string) $locale ), 'de' ) ? 'de' : 'en';
            $default_title = __( 'Repository showcases', 'yougitai-secure-showcase' );
            $default_description = __( 'Selected projects with protected source code, architecture and technical context.', 'yougitai-secure-showcase' );
            $custom_title = trim( (string) get_option( 'yougitai_ss_index_title_' . $language_key, '' ) );
            $custom_description = trim( (string) get_option( 'yougitai_ss_index_description_' . $language_key, '' ) );
            $index_title = $custom_title !== '' ? $custom_title : $default_title;
            $index_description = $custom_description !== '' ? $custom_description : $default_description;
            echo '<main class="yougitai-standalone-wrap yougitai-repositories-index"><header class="yougitai-index-header"><h1>' . esc_html( $index_title ) . '</h1><p>' . esc_html( $index_description ) . '</p></header>' . $this->render_grid( 'dark', 3 ) . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            get_footer();
            exit;
        }
        $slug = get_query_var( 'yougitai_showcase' );
        if ( ! $slug ) {
            return;
        }
        $repository = $this->repositories->find_by_slug( sanitize_title( (string) $slug ) );
        if ( ! $repository || (string) $repository['status'] !== 'published' || empty( $repository['active_snapshot_id'] ) ) {
            global $wp_query;
            if ( isset( $wp_query ) ) $wp_query->set_404();
            status_header( 404 ); nocache_headers(); get_header();
            echo '<main class="yougitai-standalone-wrap"><div class="yougitai-notice">' . esc_html__( 'This showcase is not available.', 'yougitai-secure-showcase' ) . '</div></main>';
            get_footer(); exit;
        }
        $password_failed = false;
        if ( (string) ( $repository['access_mode'] ?? 'public' ) === 'password' && ! $this->access->is_allowed( $repository ) ) {
            $password_failed = ! empty( $_POST['yougitai_showcase_password_submit'] ) && ! $this->access->maybe_authenticate( $repository );
        }
        if ( ! $this->access->is_allowed( $repository ) ) {
            if ( (string) ( $repository['access_mode'] ?? 'public' ) === 'password' ) {
                status_header( 401 ); nocache_headers(); $this->register_assets(); wp_enqueue_style( 'yougitai-ss-frontend' );
                get_header(); echo '<main class="yougitai-standalone-wrap">' . $this->access->password_form( $repository, $password_failed ) . '</main>'; get_footer(); exit;
            }
            global $wp_query; if ( isset( $wp_query ) ) $wp_query->set_404();
            status_header( 404 ); nocache_headers(); get_header(); echo '<main class="yougitai-standalone-wrap"><div class="yougitai-notice">' . esc_html__( 'This showcase is not available.', 'yougitai-secure-showcase' ) . '</div></main>'; get_footer(); exit;
        }
        status_header( 200 );
        nocache_headers();
        $this->register_assets();
        wp_enqueue_style( 'yougitai-ss-frontend' );
        wp_enqueue_script( 'yougitai-ss-frontend' );
        get_header();
        echo '<main class="yougitai-standalone-wrap">' . $this->render( [ 'id' => (int) $repository['id'], 'theme' => 'dark' ] ) . '</main>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        exit;
    }

    public function shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ 'id' => 0, 'slug' => '', 'theme' => 'auto' ], $atts, 'yougitai_showcase' );
        return $this->render( [
            'id' => absint( $atts['id'] ),
            'slug' => sanitize_title( $atts['slug'] ),
            'theme' => $this->sanitize_theme( (string) $atts['theme'] ),
        ] );
    }

    public function grid_shortcode( array $atts = [] ): string {
        $atts = shortcode_atts( [ 'theme' => 'auto', 'columns' => 3 ], $atts, 'yougitai_showcase_grid' );
        return $this->render_grid( $this->sanitize_theme( (string) $atts['theme'] ), min( 4, max( 1, absint( $atts['columns'] ) ) ) );
    }

    public function render_grid( string $theme = 'auto', int $columns = 3 ): string {
        $this->register_assets(); wp_enqueue_style( 'yougitai-ss-frontend' ); wp_enqueue_script( 'yougitai-ss-frontend' );
        $repositories = array_values( array_filter( $this->repositories->all(), static fn( array $repo ): bool => $repo['status'] === 'published' && ( $repo['access_mode'] ?? 'public' ) === 'public' && ! empty( $repo['active_snapshot_id'] ) ) );
        if ( ! $repositories ) return '<div class="yougitai-notice">' . esc_html__( 'No published showcases are available.', 'yougitai-secure-showcase' ) . '</div>';
        $base = sanitize_title( (string) get_option( 'yougitai_ss_showcase_slug', 'repositories' ) );
        ob_start(); ?>
        <div class="yougitai-portfolio-grid" data-theme="<?php echo esc_attr( $theme ); ?>" style="--yougitai-columns:<?php echo esc_attr( (string) $columns ); ?>">
            <?php foreach ( $repositories as $repository ) : $profile = $this->repositories->public_showcase_profile( (int) $repository['id'], (int) $repository['active_snapshot_id'] ); ?>
                <article class="yougitai-project-card">
                    <div class="yougitai-project-card-head"><span class="yougitai-project-icon" aria-hidden="true">&lt;/&gt;</span><span class="yougitai-badge"><?php esc_html_e( 'Verified', 'yougitai-secure-showcase' ); ?></span></div>
                    <h3><?php echo esc_html( $repository['title'] ); ?></h3>
                    <p><?php echo esc_html( $repository['description'] ?: (string) ( $profile['summary'] ?? '' ) ); ?></p>
                    <?php if ( ! empty( $profile['tech_stack'] ) ) : ?><div class="yougitai-chips"><?php foreach ( array_slice( $profile['tech_stack'], 0, 5 ) as $tech ) : ?><span><?php echo esc_html( $tech ); ?></span><?php endforeach; ?></div><?php endif; ?>
                    <div class="yougitai-project-meta"><span><?php printf( esc_html__( '%s files', 'yougitai-secure-showcase' ), esc_html( number_format_i18n( (int) ( $profile['files'] ?? 0 ) ) ) ); ?></span><span><?php printf( esc_html__( '%s protected', 'yougitai-secure-showcase' ), esc_html( number_format_i18n( (int) ( $profile['redacted'] ?? 0 ) ) ) ); ?></span></div>
                    <a class="yougitai-project-link" href="<?php echo esc_url( home_url( '/' . $base . '/' . $repository['slug'] . '/' ) ); ?>"><?php esc_html_e( 'Open secure showcase', 'yougitai-secure-showcase' ); ?> →</a>
                </article>
            <?php endforeach; ?>
        </div><?php
        return (string) ob_get_clean();
    }

    public function render( array $args ): string {
        $repository = ! empty( $args['id'] ) ? $this->repositories->find( (int) $args['id'] ) : $this->repositories->find_by_slug( (string) ( $args['slug'] ?? '' ) );
        if ( ! $repository || (string) $repository['status'] !== 'published' || empty( $repository['active_snapshot_id'] ) ) {
            return '<div class="yougitai-notice">' . esc_html__( 'This showcase is not available.', 'yougitai-secure-showcase' ) . '</div>';
        }
        $password_failed = false;
        if ( (string) ( $repository['access_mode'] ?? 'public' ) === 'password' && ! $this->access->is_allowed( $repository ) ) {
            $password_failed = ! empty( $_POST['yougitai_showcase_password_submit'] ) && ! $this->access->maybe_authenticate( $repository );
        }
        if ( ! $this->access->is_allowed( $repository ) ) {
            return (string) ( (string) ( $repository['access_mode'] ?? 'public' ) === 'password' ? $this->access->password_form( $repository, $password_failed ) : '<div class="yougitai-notice">' . esc_html__( 'This showcase is private.', 'yougitai-secure-showcase' ) . '</div>' );
        }
        $this->register_assets();
        wp_enqueue_style( 'yougitai-ss-frontend' );
        wp_enqueue_script( 'yougitai-ss-frontend' );
        $files = $this->repositories->files_for_public_repository( (int) $repository['id'], (int) $repository['active_snapshot_id'] );
        $files = array_map( [ $this, 'decorate_public_file' ], $files );
        $file_tree = $this->build_file_tree( $files );
        $selected_id = isset( $_GET['yga_file'] ) ? absint( $_GET['yga_file'] ) : 0;
        $selected = $selected_id ? $this->repositories->public_file( (int) $repository['id'], (int) $repository['active_snapshot_id'], $selected_id ) : null;
        if ( $selected ) {
            $selected = $this->decorate_public_file( $selected );
        }
        $theme = $this->sanitize_theme( (string) ( $args['theme'] ?? 'auto' ) );
        $stats = $this->statistics( $files );
        $profile = $this->repositories->public_showcase_profile( (int) $repository['id'], (int) $repository['active_snapshot_id'] );
        $readme = $this->repositories->public_readme( (int) $repository['id'], (int) $repository['active_snapshot_id'] );
        ob_start();
        include YOUGITAI_SS_DIR . 'templates/showcase.php';
        return (string) ob_get_clean();
    }


    public function decorate_public_file( array $file ): array {
        $manifest = [];
        if ( ! empty( $file['redaction_manifest'] ) ) {
            $decoded = json_decode( (string) $file['redaction_manifest'], true );
            if ( is_array( $decoded ) ) {
                $manifest = array_values( array_filter( $decoded, 'is_array' ) );
            }
        }
        if ( ! $manifest && ( $file['visibility'] ?? '' ) === 'redacted' && isset( $file['content'] ) ) {
            $content = (string) $file['content'];
            foreach ( [ '[REDACTED]', '[PROTECTED]', '[PROTECTED BY SECURITY REVIEW]' ] as $token ) {
                $offset = 0;
                while ( ( $pos = strpos( $content, $token, $offset ) ) !== false ) {
                    $prefix = substr( $content, 0, $pos );
                    $manifest[] = [
                        'kind' => 'legacy',
                        'line_start' => substr_count( $prefix, "
" ) + 1,
                        'line_end' => substr_count( $prefix, "
" ) + 1,
                        'column_start' => max( 1, $pos - ( ( strrpos( $prefix, "
" ) === false ) ? -1 : strrpos( $prefix, "
" ) ) ),
                        'original_length' => strlen( $token ),
                        'replacement' => $token,
                    ];
                    $offset = $pos + strlen( $token );
                }
            }
        }
        $lines = [];
        foreach ( $manifest as $entry ) {
            $start = max( 1, (int) ( $entry['line_start'] ?? 1 ) );
            $end = max( $start, (int) ( $entry['line_end'] ?? $start ) );
            for ( $line = $start; $line <= $end; $line++ ) {
                $lines[ $line ] = true;
            }
        }
        $file['redactions'] = $manifest;
        $file['redaction_count'] = $manifest ? count( $manifest ) : ( ( $file['visibility'] ?? '' ) === 'redacted' ? max( 1, (int) ( $file['protection_finding_count'] ?? 0 ) ) : 0 );
        $file['redacted_lines'] = count( $lines );
        return $file;
    }

    private function build_file_tree( array $files ): array {
        $tree = [];
        foreach ( $files as $file ) {
            $parts = array_values( array_filter( explode( '/', (string) $file['path'] ), static fn( string $part ): bool => $part !== '' ) );
            $cursor =& $tree;
            foreach ( $parts as $index => $part ) {
                $is_file = $index === count( $parts ) - 1;
                if ( $is_file ) {
                    $cursor['__files'][] = $file;
                    continue;
                }
                if ( ! isset( $cursor[ $part ] ) || ! is_array( $cursor[ $part ] ) ) {
                    $cursor[ $part ] = [];
                }
                $cursor =& $cursor[ $part ];
            }
            unset( $cursor );
        }
        return $tree;
    }

    private function sanitize_theme( string $theme ): string {
        return in_array( $theme, [ 'auto', 'light', 'dark' ], true ) ? $theme : 'auto';
    }

    private function statistics( array $files ): array {
        $languages = [];
        $size = 0;
        $redacted = 0;
        foreach ( $files as $file ) {
            $language = (string) ( $file['language'] ?: __( 'Other', 'yougitai-secure-showcase' ) );
            $languages[ $language ] = ( $languages[ $language ] ?? 0 ) + 1;
            $size += (int) $file['size'];
            if ( in_array( (string) $file['visibility'], [ 'redacted', 'hidden' ], true ) ) {
                $redacted++;
            }
        }
        arsort( $languages );
        return [ 'files' => count( $files ), 'size' => $size, 'redacted' => $redacted, 'languages' => array_slice( $languages, 0, 6, true ) ];
    }
}
