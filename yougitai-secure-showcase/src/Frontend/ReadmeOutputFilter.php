<?php
namespace YougitAI\SecureShowcase\Frontend;

use YougitAI\SecureShowcase\Repository\RepositoryService;

final class ReadmeOutputFilter {
    public function __construct( private RepositoryService $repositories ) {}

    public function register(): void {
        add_action( 'template_redirect', [ $this, 'begin' ], 1 );
    }

    public function begin(): void {
        if ( empty( get_query_var( 'yougitai_showcase' ) ) || ! empty( $_GET['yougitai_readme_asset'] ) ) {
            return;
        }
        ob_start( [ $this, 'rewrite' ] );
    }

    public function rewrite( string $html ): string {
        $slug = sanitize_title( (string) get_query_var( 'yougitai_showcase' ) );
        $repository = $slug !== '' ? $this->repositories->find_by_slug( $slug ) : null;
        if ( ! $repository || empty( $repository['active_snapshot_id'] ) ) {
            return $html;
        }

        $readme = $this->repositories->public_readme(
            (int) $repository['id'],
            (int) $repository['active_snapshot_id']
        );
        if ( ! $readme || empty( $readme['content'] ) ) {
            return $html;
        }

        $renderer = new Readme( $this->repositories );
        $rendered = $renderer->render( $repository, $readme );
        $style = '<style id="yougitai-readme-rendered-css">'
            . '.yougitai-readme-content{line-height:1.65;overflow-wrap:anywhere}'
            . '.yougitai-readme-content>:first-child{margin-top:0}'
            . '.yougitai-readme-content>:last-child{margin-bottom:0}'
            . '.yougitai-readme-content h1,.yougitai-readme-content h2,.yougitai-readme-content h3,.yougitai-readme-content h4{margin:1.35em 0 .55em;line-height:1.25}'
            . '.yougitai-readme-content p,.yougitai-readme-content ul,.yougitai-readme-content ol,.yougitai-readme-content blockquote,.yougitai-readme-content pre{margin:0 0 1em}'
            . '.yougitai-readme-content img{display:block;max-width:100%;height:auto;margin:1rem auto;border-radius:12px}'
            . '.yougitai-readme-content pre{overflow-x:auto;padding:1rem;border-radius:10px}'
            . '.yougitai-readme-content blockquote{padding-left:1rem;border-left:3px solid currentColor;opacity:.85}'
            . '</style>';
        $replacement = $style . '<div class="yougitai-readme-content">' . $rendered . '</div>';

        $pattern = '#(<div class="yougitai-readme">\s*<h3>.*?</h3>\s*)<pre>.*?</pre>#si';
        $updated = preg_replace( $pattern, '$1' . $replacement, $html, 1 );
        return is_string( $updated ) ? $updated : $html;
    }
}
