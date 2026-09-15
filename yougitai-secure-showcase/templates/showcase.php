<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<section class="yougitai-showcase" data-yougitai-showcase data-theme="<?php echo esc_attr( $theme ); ?>">
    <header class="yougitai-header">
        <div>
            <div class="yougitai-repo-path"><?php echo esc_html( $repository['owner'] ); ?> / <strong><?php echo esc_html( $repository['repo'] ); ?></strong></div>
            <?php if ( $repository['description'] ) : ?><p><?php echo esc_html( $repository['description'] ); ?></p><?php endif; ?>
        </div>
        <span class="yougitai-badge"><?php esc_html_e( 'Verified secure showcase', 'yougitai-secure-showcase' ); ?></span>
    </header>

    <nav class="yougitai-tabs" aria-label="<?php esc_attr_e( 'Showcase sections', 'yougitai-secure-showcase' ); ?>">
        <a href="#yougitai-overview"><?php esc_html_e( 'Overview', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-code"><?php esc_html_e( 'Code', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-architecture"><?php esc_html_e( 'Architecture', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-stack"><?php esc_html_e( 'Tech stack', 'yougitai-secure-showcase' ); ?></a>
    </nav>

    <div class="yougitai-stats" aria-label="<?php esc_attr_e( 'Repository statistics', 'yougitai-secure-showcase' ); ?>">
        <span><strong><?php echo esc_html( number_format_i18n( $stats['files'] ) ); ?></strong> <?php esc_html_e( 'Files', 'yougitai-secure-showcase' ); ?></span>
        <span><strong><?php echo esc_html( size_format( $stats['size'] ) ); ?></strong> <?php esc_html_e( 'Public size', 'yougitai-secure-showcase' ); ?></span>
        <span><strong><?php echo esc_html( number_format_i18n( $stats['redacted'] ) ); ?></strong> <?php esc_html_e( 'Redacted', 'yougitai-secure-showcase' ); ?></span>
        <?php if ( $stats['languages'] ) : ?><span class="yougitai-languages"><?php echo esc_html( implode( ' · ', array_keys( $stats['languages'] ) ) ); ?></span><?php endif; ?>
    </div>

    <section id="yougitai-overview" class="yougitai-overview yougitai-section">
        <div class="yougitai-overview-main">
            <h2><?php esc_html_e( 'Project overview', 'yougitai-secure-showcase' ); ?></h2>
            <p class="yougitai-lead"><?php echo esc_html( (string) ( $profile['summary'] ?? $repository['description'] ) ); ?></p>
            <?php if ( $readme && ! empty( $readme['content'] ) ) : ?>
                <div class="yougitai-readme">
                    <h3><?php esc_html_e( 'README', 'yougitai-secure-showcase' ); ?></h3>
                    <pre><?php echo esc_html( mb_substr( (string) $readme['content'], 0, 12000 ) ); ?></pre>
                </div>
            <?php endif; ?>
        </div>
        <aside class="yougitai-overview-side">
            <h3><?php esc_html_e( 'At a glance', 'yougitai-secure-showcase' ); ?></h3>
            <dl>
                <div><dt><?php esc_html_e( 'Branch', 'yougitai-secure-showcase' ); ?></dt><dd><?php echo esc_html( $repository['default_branch'] ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Modules', 'yougitai-secure-showcase' ); ?></dt><dd><?php echo esc_html( number_format_i18n( count( $profile['modules'] ?? [] ) ) ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Protected files', 'yougitai-secure-showcase' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) ( $profile['redacted'] ?? 0 ) ) ); ?></dd></div>
            </dl>
            <?php if ( ! empty( $profile['modules'] ) ) : ?><div class="yougitai-chips"><?php foreach ( $profile['modules'] as $module ) : ?><span><?php echo esc_html( $module ); ?></span><?php endforeach; ?></div><?php endif; ?>
        </aside>
    </section>

    <section id="yougitai-code" class="yougitai-section yougitai-code-section">
        <div class="yougitai-grid">
            <aside class="yougitai-tree">
                <h3><?php esc_html_e( 'Repository browser', 'yougitai-secure-showcase' ); ?></h3>
                <?php if ( ! $files ) : ?><p><?php esc_html_e( 'No public files are available.', 'yougitai-secure-showcase' ); ?></p><?php endif; ?>
                <?php
                $tree_summary = static function ( array $nodes ) use ( &$tree_summary ): array {
                    $total = 0;
                    $protected = 0;
                    foreach ( $nodes['__files'] ?? [] as $node_file ) {
                        $total++;
                        if ( (string) ( $node_file['visibility'] ?? '' ) === 'hidden' ) $protected++;
                    }
                    foreach ( $nodes as $key => $children ) {
                        if ( $key === '__files' || ! is_array( $children ) ) continue;
                        $child_summary = $tree_summary( $children );
                        $total += $child_summary['total'];
                        $protected += $child_summary['protected'];
                    }
                    return [ 'total' => $total, 'protected' => $protected ];
                };
                $render_tree = static function ( array $nodes, string $prefix = '' ) use ( &$render_tree, $tree_summary, $selected ): void {
                    $files_here = $nodes['__files'] ?? [];
                    unset( $nodes['__files'] );
                    ksort( $nodes, SORT_NATURAL | SORT_FLAG_CASE );
                    if ( $nodes ) {
                        foreach ( $nodes as $name => $children ) {
                            $folder_path = ltrim( $prefix . '/' . $name, '/' );
                            $contains_selected = $selected && str_starts_with( (string) $selected['path'], $folder_path . '/' );
                            $folder_summary = $tree_summary( $children );
                            $folder_fully_protected = $folder_summary['total'] > 0 && $folder_summary['protected'] === $folder_summary['total'];
                            $folder_partially_protected = $folder_summary['protected'] > 0 && ! $folder_fully_protected;
                            $folder_class = $folder_fully_protected ? ' is-protected' : ( $folder_partially_protected ? ' is-partially-protected' : '' );
                            echo '<li class="yougitai-folder' . ( $contains_selected ? ' is-open' : '' ) . $folder_class . '">';
                            echo '<button type="button" class="yougitai-folder-toggle" aria-expanded="' . ( $contains_selected ? 'true' : 'false' ) . '"><span class="yougitai-chevron" aria-hidden="true">›</span><span class="yougitai-folder-icon" aria-hidden="true">📁</span><span>' . esc_html( $name ) . '</span>';
                            if ( $folder_fully_protected ) {
                                echo '<span class="yougitai-protected-indicator" title="' . esc_attr__( 'Protected folder', 'yougitai-secure-showcase' ) . '" aria-label="' . esc_attr__( 'Protected folder', 'yougitai-secure-showcase' ) . '">🔒</span>';
                            } elseif ( $folder_partially_protected ) {
                                echo '<span class="yougitai-protected-indicator is-partial" title="' . esc_attr__( 'Contains protected files', 'yougitai-secure-showcase' ) . '" aria-label="' . esc_attr__( 'Contains protected files', 'yougitai-secure-showcase' ) . '">🛡️</span>';
                            }
                            echo '</button>';
                            echo '<ul class="yougitai-folder-children">';
                            $render_tree( $children, $folder_path );
                            echo '</ul></li>';
                        }
                    }
                    usort( $files_here, static fn( array $a, array $b ): int => strnatcasecmp( (string) $a['filename'], (string) $b['filename'] ) );
                    foreach ( $files_here as $file ) {
                        $active = $selected && (int) $selected['id'] === (int) $file['id'];
                        echo '<li class="yougitai-file">';
                        echo '<a' . ( $active ? ' class="is-active" aria-current="true"' : '' ) . ' href="' . esc_url( add_query_arg( 'yga_file', (int) $file['id'] ) . '#yougitai-code' ) . '">';
                        $file_protected = (string) ( $file['visibility'] ?? '' ) === 'hidden';
                        echo '<span class="yougitai-file-icon" aria-hidden="true">' . ( $file_protected ? '🔒' : '📄' ) . '</span><span class="yougitai-file-name">' . esc_html( $file['filename'] ) . '</span>';
                        if ( $file_protected ) {
                            echo '<span class="yougitai-protected-label">' . esc_html__( 'Protected', 'yougitai-secure-showcase' ) . '</span>';
                        } elseif ( (int) ( $file['redaction_count'] ?? 0 ) > 0 ) {
                            $redaction_count = (int) $file['redaction_count'];
                            $redaction_label = sprintf(
                                _n( '%s redaction', '%s redactions', $redaction_count, 'yougitai-secure-showcase' ),
                                number_format_i18n( $redaction_count )
                            );
                            echo '<span class="yougitai-redaction-badge" title="' . esc_attr__( 'This file contains protected redactions.', 'yougitai-secure-showcase' ) . '">' . esc_html( $redaction_label ) . '</span>';
                        }
                        echo '</a></li>';
                    }
                };
                ?>
                <div class="yougitai-tree-actions"><button type="button" data-yougitai-tree-expand><?php esc_html_e( 'Expand all', 'yougitai-secure-showcase' ); ?></button><button type="button" data-yougitai-tree-collapse><?php esc_html_e( 'Collapse all', 'yougitai-secure-showcase' ); ?></button></div>
                <div class="yougitai-redaction-legend"><span><strong><?php esc_html_e( 'Redactions', 'yougitai-secure-showcase' ); ?>:</strong> <?php esc_html_e( 'A badge such as “3 redactions” marks a partially protected file. Open that file to see the redaction markers in the code.', 'yougitai-secure-showcase' ); ?></span><span>🔒 <?php esc_html_e( 'Fully protected files contain no public code.', 'yougitai-secure-showcase' ); ?></span></div>
                <ul class="yougitai-file-tree"><?php $render_tree( $file_tree ); ?></ul>
            </aside>
            <article class="yougitai-code-panel">
                <?php if ( $selected ) : ?>
                    <div class="yougitai-code-head"><strong><?php echo esc_html( $selected['path'] ); ?></strong><span><?php echo esc_html( $selected['language'] ); ?></span></div>
                    <?php if ( $selected['visibility'] === 'hidden' ) : ?>
                        <div class="yougitai-protected-file-view" role="status">
                            <div class="yougitai-protected-file-icon" aria-hidden="true">🔒</div>
                            <div>
                                <h3><?php esc_html_e( 'Content protected', 'yougitai-secure-showcase' ); ?></h3>
                                <p><?php esc_html_e( 'The contents of this file are not publicly displayed because they may contain sensitive implementation details, internal security logic, or proprietary business logic.', 'yougitai-secure-showcase' ); ?></p>
                                <p class="yougitai-protected-file-note"><?php esc_html_e( 'The file remains visible so the project structure and architecture can still be understood.', 'yougitai-secure-showcase' ); ?></p>
                            </div>
                        </div>
                    <?php else : ?>
                        <?php if ( $selected['visibility'] === 'redacted' ) : ?>
                            <div class="yougitai-security-note">
                                <strong><?php esc_html_e( 'Protected before publication', 'yougitai-secure-showcase' ); ?></strong>
                                <span><?php printf( esc_html__( '%1$s protected passages across %2$s lines are visibly marked below.', 'yougitai-secure-showcase' ), esc_html( number_format_i18n( (int) $selected['redaction_count'] ) ), esc_html( number_format_i18n( (int) $selected['redacted_lines'] ) ) ); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php
                    $code_lines = preg_split( '/\R/', (string) $selected['content'] );
                    $redactions_by_line = [];
                    foreach ( $selected['redactions'] ?? [] as $redaction ) {
                        $line_start = max( 1, (int) ( $redaction['line_start'] ?? 1 ) );
                        $redactions_by_line[ $line_start ][] = $redaction;
                    }
                    ?>
                    <div class="yougitai-code-view" role="region" aria-label="<?php esc_attr_e( 'Public source code with visible protected passages', 'yougitai-secure-showcase' ); ?>">
                        <?php foreach ( is_array( $code_lines ) ? $code_lines : [] as $index => $line ) : $line_no = $index + 1; ?>
                            <div class="yougitai-code-line<?php echo ! empty( $redactions_by_line[ $line_no ] ) ? ' has-redaction' : ''; ?>">
                                <span class="yougitai-line-no" aria-hidden="true"><?php echo esc_html( (string) $line_no ); ?></span>
                                <code><?php echo esc_html( $line ); ?></code>
                                <?php if ( ! empty( $redactions_by_line[ $line_no ] ) ) : ?>
                                    <span class="yougitai-blackout-layer" aria-label="<?php esc_attr_e( 'Protected code was removed here.', 'yougitai-secure-showcase' ); ?>">
                                        <?php foreach ( $redactions_by_line[ $line_no ] as $redaction ) :
                                            $width = max( 6, min( 46, (int) ceil( (int) ( $redaction['original_length'] ?? 8 ) / max( 1, ( (int) ( $redaction['line_end'] ?? $line_no ) - (int) ( $redaction['line_start'] ?? $line_no ) + 1 ) ) ) ) );
                                            $left = max( 0, min( 70, (int) ( $redaction['column_start'] ?? 1 ) - 1 ) );
                                        ?><?php $protected_lines = max( 1, (int) ( $redaction['line_end'] ?? $line_no ) - (int) ( $redaction['line_start'] ?? $line_no ) + 1 ); ?><span class="yougitai-blackout" tabindex="0" style="--yg-blackout-width:<?php echo esc_attr( (string) $width ); ?>ch;--yg-blackout-left:<?php echo esc_attr( (string) $left ); ?>ch" data-yougitai-tooltip="<?php esc_attr_e( 'This area was redacted to protect sensitive or proprietary implementation details.', 'yougitai-secure-showcase' ); ?>" title="<?php esc_attr_e( 'This area was redacted to protect sensitive or proprietary implementation details.', 'yougitai-secure-showcase' ); ?>" aria-label="<?php esc_attr_e( 'This area was redacted to protect sensitive or proprietary implementation details.', 'yougitai-secure-showcase' ); ?>"></span><?php if ( $protected_lines > 1 ) : ?><span class="yougitai-blackout-count" style="--yg-blackout-left:<?php echo esc_attr( (string) $left ); ?>ch"><?php printf( esc_html__( '%s lines protected', 'yougitai-secure-showcase' ), esc_html( number_format_i18n( $protected_lines ) ) ); ?></span><?php endif; ?><?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="yougitai-empty"><h3><?php esc_html_e( 'Secure code browser', 'yougitai-secure-showcase' ); ?></h3><p><?php esc_html_e( 'Select a file to inspect the approved public snapshot. The original repository is never served to visitors.', 'yougitai-secure-showcase' ); ?></p></div>
                <?php endif; ?>
            </article>
        </div>
    </section>

    <section id="yougitai-architecture" class="yougitai-section yougitai-padded">
        <h2><?php esc_html_e( 'Architecture', 'yougitai-secure-showcase' ); ?></h2>
        <p><?php esc_html_e( 'High-level modules detected from the verified public snapshot. Protected implementation details are intentionally omitted.', 'yougitai-secure-showcase' ); ?></p>
        <div class="yougitai-architecture">
            <?php foreach ( $profile['architecture'] ?? [] as $layer ) : ?><div class="yougitai-architecture-node"><strong><?php echo esc_html( $layer['label'] ); ?></strong><small><?php echo esc_html( $layer['type'] ); ?></small></div><?php endforeach; ?>
            <?php if ( empty( $profile['architecture'] ) ) : ?><p><?php esc_html_e( 'No architecture modules were detected in the public snapshot.', 'yougitai-secure-showcase' ); ?></p><?php endif; ?>
        </div>
    </section>

    <section id="yougitai-stack" class="yougitai-section yougitai-padded">
        <h2><?php esc_html_e( 'Technology stack', 'yougitai-secure-showcase' ); ?></h2>
        <?php if ( ! empty( $profile['tech_stack'] ) ) : ?><div class="yougitai-chips yougitai-stack-chips"><?php foreach ( $profile['tech_stack'] as $tech ) : ?><span><?php echo esc_html( $tech ); ?></span><?php endforeach; ?></div><?php endif; ?>
        <?php if ( ! empty( $profile['languages'] ) ) : ?><div class="yougitai-language-bars"><?php foreach ( $profile['languages'] as $language ) : ?><div><div class="yougitai-language-label"><span><?php echo esc_html( $language['name'] ); ?></span><strong><?php echo esc_html( number_format_i18n( (float) $language['percent'], 1 ) ); ?>%</strong></div><progress max="100" value="<?php echo esc_attr( (string) $language['percent'] ); ?>"></progress></div><?php endforeach; ?></div><?php endif; ?>
    </section>

    <footer class="yougitai-showcase-footer"><span>🔒</span> <?php esc_html_e( 'This showcase is rendered exclusively from a verified, redacted public snapshot.', 'yougitai-secure-showcase' ); ?></footer>
</section>
