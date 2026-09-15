<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap yougitai-admin">
    <h1><?php esc_html_e( 'YougitAI Secure Showcases', 'yougitai-secure-showcase' ); ?></h1>
    <p><?php esc_html_e( 'Original repository data and public showcase snapshots are strictly separated. Public visitors only receive approved snapshot data.', 'yougitai-secure-showcase' ); ?></p>

    <?php if ( $message ) : ?><div class="notice notice-success"><p><?php echo esc_html( urldecode( $message ) ); ?></p></div><?php endif; ?>
    <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( urldecode( $error ) ); ?></p></div><?php endif; ?>

    <?php if ( $github_connected ) : ?>
        <div class="yougitai-card">
            <div class="yougitai-card-heading-row">
                <div>
                    <h2><?php esc_html_e( 'Import from your connected GitHub account', 'yougitai-secure-showcase' ); ?></h2>
                    <p><?php esc_html_e( 'Select one or more repositories below. Private repositories stay private on GitHub; YougitAI only creates sanitized showcase snapshots.', 'yougitai-secure-showcase' ); ?></p>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="yougitai_ss_refresh_github_repositories">
                    <?php wp_nonce_field( 'yougitai_ss_refresh_github_repositories' ); ?>
                    <?php submit_button( __( 'Refresh repositories', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?>
                </form>
            </div>

            <?php if ( $github_repository_error ) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html( $github_repository_error ); ?></p></div>
            <?php elseif ( empty( $github_repositories ) ) : ?>
                <p><?php esc_html_e( 'No repositories were returned by GitHub for this account.', 'yougitai-secure-showcase' ); ?></p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="yougitai_ss_import_selected_repositories">
                    <?php wp_nonce_field( 'yougitai_ss_import_selected_repositories' ); ?>
                    <div class="yougitai-picker-toolbar">
                        <button type="button" class="button" data-yougitai-repo-select="all"><?php esc_html_e( 'Select all', 'yougitai-secure-showcase' ); ?></button>
                        <button type="button" class="button" data-yougitai-repo-select="private"><?php esc_html_e( 'Select private', 'yougitai-secure-showcase' ); ?></button>
                        <button type="button" class="button" data-yougitai-repo-select="none"><?php esc_html_e( 'Clear selection', 'yougitai-secure-showcase' ); ?></button>
                    </div>
                    <div class="yougitai-repository-picker">
                        <?php foreach ( $github_repositories as $github_repository ) : ?>
                            <?php
                            $full_name = (string) $github_repository['full_name'];
                            $already_imported = isset( $imported_github_repositories[ strtolower( $full_name ) ] );
                            ?>
                            <label class="yougitai-repository-choice <?php echo $already_imported ? 'is-imported' : ''; ?>" data-yougitai-visibility="<?php echo $github_repository['private'] ? 'private' : 'public'; ?>">
                                <input type="checkbox" name="github_repository_ids[]" value="<?php echo esc_attr( $github_repository['id'] ); ?>" <?php disabled( $already_imported ); ?>>
                                <span class="yougitai-repository-choice-main">
                                    <strong><?php echo esc_html( $full_name ); ?></strong>
                                    <?php if ( ! empty( $github_repository['description'] ) ) : ?><small><?php echo esc_html( $github_repository['description'] ); ?></small><?php endif; ?>
                                </span>
                                <span class="yougitai-repository-choice-meta">
                                    <span class="yougitai-badge"><?php echo esc_html( $github_repository['private'] ? __( 'Private', 'yougitai-secure-showcase' ) : __( 'Public', 'yougitai-secure-showcase' ) ); ?></span>
                                    <?php if ( $already_imported ) : ?><span class="yougitai-badge is-success"><?php esc_html_e( 'Already imported', 'yougitai-secure-showcase' ); ?></span><?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="yougitai-picker-footer">
                        <label>
                            <span><?php esc_html_e( 'Protection profile', 'yougitai-secure-showcase' ); ?></span>
                            <select name="protection_profile">
                                <option value="portfolio"><?php esc_html_e( 'Portfolio', 'yougitai-secure-showcase' ); ?></option>
                                <option value="balanced" selected><?php esc_html_e( 'Balanced', 'yougitai-secure-showcase' ); ?></option>
                                <option value="investor"><?php esc_html_e( 'Investor', 'yougitai-secure-showcase' ); ?></option>
                                <option value="maximum"><?php esc_html_e( 'Maximum IP protection', 'yougitai-secure-showcase' ); ?></option>
                            </select>
                        </label>
                        <?php submit_button( __( 'Import selected repositories', 'yougitai-secure-showcase' ), 'primary', 'submit', false ); ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="yougitai-card">
            <h2><?php esc_html_e( 'Connect GitHub to choose repositories', 'yougitai-secure-showcase' ); ?></h2>
            <p><?php esc_html_e( 'Connect GitHub in Settings first. After authorization, YougitAI will show your accessible public and private repositories here with checkboxes.', 'yougitai-secure-showcase' ); ?></p>
            <a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'page' => 'yougitai-secure-showcase-settings' ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open GitHub connection settings', 'yougitai-secure-showcase' ); ?></a>
        </div>
    <?php endif; ?>

    <details class="yougitai-card yougitai-manual-import">
        <summary><strong><?php esc_html_e( 'Manual repository URL (fallback)', 'yougitai-secure-showcase' ); ?></strong></summary>
        <p><?php esc_html_e( 'Use this only if a repository does not appear in the GitHub picker above.', 'yougitai-secure-showcase' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-form-grid">
            <input type="hidden" name="action" value="yougitai_ss_add_repository">
            <?php wp_nonce_field( 'yougitai_ss_add_repository' ); ?>
            <label>
                <span><?php esc_html_e( 'GitHub repository URL', 'yougitai-secure-showcase' ); ?></span>
                <input type="url" class="regular-text" required name="github_url" placeholder="https://github.com/owner/repository">
            </label>
            <label>
                <span><?php esc_html_e( 'Protection profile', 'yougitai-secure-showcase' ); ?></span>
                <select name="protection_profile">
                    <option value="portfolio"><?php esc_html_e( 'Portfolio', 'yougitai-secure-showcase' ); ?></option>
                    <option value="balanced" selected><?php esc_html_e( 'Balanced', 'yougitai-secure-showcase' ); ?></option>
                    <option value="investor"><?php esc_html_e( 'Investor', 'yougitai-secure-showcase' ); ?></option>
                    <option value="maximum"><?php esc_html_e( 'Maximum IP protection', 'yougitai-secure-showcase' ); ?></option>
                </select>
            </label>
            <div><?php submit_button( __( 'Add repository', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?></div>
        </form>
    </details>

    <h2><?php esc_html_e( 'Repositories', 'yougitai-secure-showcase' ); ?></h2>
    <?php if ( ! $repositories ) : ?>
        <p><?php esc_html_e( 'No repositories have been added yet.', 'yougitai-secure-showcase' ); ?></p>
    <?php else : ?>
        <div class="yougitai-repo-grid">
            <?php foreach ( $repositories as $repo ) : ?>
                <article class="yougitai-card yougitai-repo-card">
                    <div>
                        <h3><?php echo esc_html( $repo['title'] ); ?></h3>
                        <code><?php echo esc_html( $repo['owner'] . '/' . $repo['repo'] ); ?></code>
                    </div>
                    <div class="yougitai-meta-row">
                        <span><?php echo esc_html( [ 'portfolio'=>__( 'Portfolio', 'yougitai-secure-showcase' ), 'balanced'=>__( 'Balanced', 'yougitai-secure-showcase' ), 'investor'=>__( 'Investor', 'yougitai-secure-showcase' ), 'maximum'=>__( 'Maximum IP protection', 'yougitai-secure-showcase' ) ][ $repo['protection_profile'] ] ?? $repo['protection_profile'] ); ?></span>
                        <span class="yougitai-status yougitai-status-<?php echo esc_attr( $repo['status'] ); ?>"><?php echo esc_html( $repo['status'] === 'published' ? __( 'Published', 'yougitai-secure-showcase' ) : __( 'Draft', 'yougitai-secure-showcase' ) ); ?></span>
                    </div>
                    <div class="yougitai-actions">
                        <a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'page'=>'yougitai-secure-showcase', 'repository_id'=>(int) $repo['id'] ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open repository', 'yougitai-secure-showcase' ); ?></a>
                        <?php if ( $repo['status'] === 'published' ) : ?>
                            <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( home_url( '/' . get_option( 'yougitai_ss_showcase_slug', 'repositories' ) . '/' . $repo['slug'] . '/' ) ); ?>"><?php esc_html_e( 'Open showcase', 'yougitai-secure-showcase' ); ?></a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="yougitai-card yougitai-info">
        <h2><?php esc_html_e( 'Elementor / Elementor Pro', 'yougitai-secure-showcase' ); ?></h2>
        <p><?php esc_html_e( 'The plugin works standalone in WordPress and adds a native Elementor widget when Elementor is active. Elementor Pro is supported but never required.', 'yougitai-secure-showcase' ); ?></p>
        <p><code>[yougitai_showcase id="123"]</code> · <code>[yougitai_showcase slug="my-project"]</code></p>
    </div>
</div>
