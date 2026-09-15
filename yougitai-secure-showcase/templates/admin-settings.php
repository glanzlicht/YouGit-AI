<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap yougitai-admin">
    <h1><?php esc_html_e( 'YougitAI Settings', 'yougitai-secure-showcase' ); ?></h1>
    <?php if ( $message ) : ?><div class="notice notice-success"><p><?php echo esc_html( urldecode( $message ) ); ?></p></div><?php endif; ?>
    <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( urldecode( $error ) ); ?></p></div><?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="yougitai_ss_save_settings">
        <?php wp_nonce_field( 'yougitai_ss_save_settings' ); ?>

        <section class="yougitai-card">
            <h2><?php esc_html_e( 'Standalone showcase', 'yougitai-secure-showcase' ); ?></h2>
            <label class="yougitai-field"><span><?php esc_html_e( 'URL slug', 'yougitai-secure-showcase' ); ?></span><input type="text" name="showcase_slug" value="<?php echo esc_attr( $settings['showcase_slug'] ); ?>" readonly></label>
            <p class="description"><?php esc_html_e( 'The repository overview remains available under /repositories/. Individual showcases use /repositories/project-slug/.', 'yougitai-secure-showcase' ); ?></p>
            <hr>
            <h3><?php esc_html_e( 'Repository overview page text', 'yougitai-secure-showcase' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Customize only the heading and introduction shown on the repository overview. Leave a field empty to use the translated default.', 'yougitai-secure-showcase' ); ?></p>
            <div class="yougitai-settings-language-grid">
                <div class="yougitai-subcard">
                    <h4><?php esc_html_e( 'German', 'yougitai-secure-showcase' ); ?></h4>
                    <label class="yougitai-field"><span><?php esc_html_e( 'Heading', 'yougitai-secure-showcase' ); ?></span><input type="text" name="index_title_de" value="<?php echo esc_attr( $settings['index_title_de'] ); ?>" placeholder="<?php echo esc_attr__( 'Repository showcases', 'yougitai-secure-showcase' ); ?>"></label>
                    <label class="yougitai-field"><span><?php esc_html_e( 'Description', 'yougitai-secure-showcase' ); ?></span><textarea name="index_description_de" rows="3" placeholder="<?php echo esc_attr__( 'Selected projects with protected source code, architecture and technical context.', 'yougitai-secure-showcase' ); ?>"><?php echo esc_textarea( $settings['index_description_de'] ); ?></textarea></label>
                </div>
                <div class="yougitai-subcard">
                    <h4><?php esc_html_e( 'English', 'yougitai-secure-showcase' ); ?></h4>
                    <label class="yougitai-field"><span><?php esc_html_e( 'Heading', 'yougitai-secure-showcase' ); ?></span><input type="text" name="index_title_en" value="<?php echo esc_attr( $settings['index_title_en'] ); ?>" placeholder="<?php echo esc_attr__( 'Repository showcases', 'yougitai-secure-showcase' ); ?>"></label>
                    <label class="yougitai-field"><span><?php esc_html_e( 'Description', 'yougitai-secure-showcase' ); ?></span><textarea name="index_description_en" rows="3" placeholder="<?php echo esc_attr__( 'Selected projects with protected source code, architecture and technical context.', 'yougitai-secure-showcase' ); ?>"><?php echo esc_textarea( $settings['index_description_en'] ); ?></textarea></label>
                </div>
            </div>
        </section>

        <section class="yougitai-card">
            <h2><?php esc_html_e( 'GitHub connection', 'yougitai-secure-showcase' ); ?></h2>
            <p><?php esc_html_e( 'A fine-grained GitHub token enables private repositories and raises API limits. Grant only read access to repository contents and metadata.', 'yougitai-secure-showcase' ); ?></p>
            <label class="yougitai-field"><span><?php esc_html_e( 'Fine-grained access token', 'yougitai-secure-showcase' ); ?></span><input type="password" autocomplete="new-password" name="github_token" value="" placeholder="<?php echo esc_attr( $settings['has_github_token'] ? __( 'Token is stored — enter a new token to replace it', 'yougitai-secure-showcase' ) : __( 'Enter GitHub token', 'yougitai-secure-showcase' ) ); ?>"></label>
            <?php if ( $settings['has_github_token'] ) : ?><label class="yougitai-checkbox"><input type="checkbox" name="remove_github_token" value="1"> <span><?php esc_html_e( 'Remove stored GitHub token', 'yougitai-secure-showcase' ); ?></span></label><?php endif; ?>
            <p class="description"><?php esc_html_e( 'The token is encrypted at rest using WordPress installation secrets. It is never exposed to the public frontend.', 'yougitai-secure-showcase' ); ?></p>
            <hr>
            <h3><?php esc_html_e( 'GitHub OAuth', 'yougitai-secure-showcase' ); ?></h3>
            <p><?php esc_html_e( 'For a smoother private-repository connection, register a GitHub OAuth App and connect WordPress without copying a personal access token.', 'yougitai-secure-showcase' ); ?></p>
            <div class="yougitai-info-box yougitai-oauth-guide">
                <h3><?php esc_html_e( 'How to connect private GitHub repositories', 'yougitai-secure-showcase' ); ?></h3>
                <p><?php esc_html_e( 'Your repositories stay private on GitHub. OAuth only authorizes this WordPress installation to read repositories you are allowed to access. The public showcase still contains only reviewed and approved snapshot data.', 'yougitai-secure-showcase' ); ?></p>
                <ol class="yougitai-setup-steps">
                    <li><?php echo wp_kses_post( sprintf( __( 'Open %s and create a new OAuth App.', 'yougitai-secure-showcase' ), '<a href="https://github.com/settings/developers" target="_blank" rel="noopener noreferrer">GitHub &rarr; Settings &rarr; Developer settings &rarr; OAuth Apps</a>' ) ); ?></li>
                    <li><?php esc_html_e( 'Enter any clear application name, for example “YougitAI Secure Showcase”.', 'yougitai-secure-showcase' ); ?></li>
                    <li><?php esc_html_e( 'Copy the Homepage URL and Authorization callback URL shown below into the matching GitHub fields exactly.', 'yougitai-secure-showcase' ); ?></li>
                    <li><?php esc_html_e( 'Create the OAuth App in GitHub. Then copy its Client ID and generate a Client Secret.', 'yougitai-secure-showcase' ); ?></li>
                    <li><?php esc_html_e( 'Paste the Client ID and Client Secret below and save these settings.', 'yougitai-secure-showcase' ); ?></li>
                    <li><?php esc_html_e( 'Click “Connect with GitHub OAuth” and approve access in GitHub. You can then import private repository URLs normally.', 'yougitai-secure-showcase' ); ?></li>
                </ol>
                <label class="yougitai-field"><span><?php esc_html_e( 'GitHub OAuth Homepage URL', 'yougitai-secure-showcase' ); ?></span><input type="text" readonly value="<?php echo esc_attr( home_url( '/' ) ); ?>" onclick="this.select();"></label>
                <label class="yougitai-field"><span><?php esc_html_e( 'GitHub OAuth Authorization callback URL', 'yougitai-secure-showcase' ); ?></span><input type="text" readonly value="<?php echo esc_attr( $settings['github_oauth_callback'] ); ?>" onclick="this.select();"></label>
                <p class="description"><strong><?php esc_html_e( 'Important:', 'yougitai-secure-showcase' ); ?></strong> <?php esc_html_e( 'The callback URL must match exactly. Do not add or remove a slash and do not replace HTTPS with HTTP.', 'yougitai-secure-showcase' ); ?></p>
            </div>
            <label class="yougitai-field"><span><?php esc_html_e( 'OAuth Client ID', 'yougitai-secure-showcase' ); ?></span><input type="text" name="github_oauth_client_id" value="<?php echo esc_attr( $settings['github_oauth_client_id'] ); ?>"></label>
            <label class="yougitai-field"><span><?php esc_html_e( 'OAuth Client Secret', 'yougitai-secure-showcase' ); ?></span><input type="password" autocomplete="new-password" name="github_oauth_client_secret" value="" placeholder="<?php echo esc_attr( $settings['has_github_oauth_secret'] ? __( 'Secret is stored — enter a new secret to replace it', 'yougitai-secure-showcase' ) : __( 'Enter OAuth client secret', 'yougitai-secure-showcase' ) ); ?>"></label>
            <?php if ( $settings['has_github_oauth_secret'] ) : ?><label class="yougitai-checkbox"><input type="checkbox" name="remove_github_oauth_client_secret" value="1"> <span><?php esc_html_e( 'Remove stored OAuth client secret', 'yougitai-secure-showcase' ); ?></span></label><?php endif; ?>
            <?php if ( $settings['github_oauth_client_id'] !== '' && $settings['has_github_oauth_secret'] ) : ?>
                <p><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=yougitai_ss_github_oauth_start' ), 'yougitai_ss_github_oauth_start' ) ); ?>"><?php esc_html_e( 'Connect with GitHub OAuth', 'yougitai-secure-showcase' ); ?></a></p>
            <?php endif; ?>
            <?php if ( $settings['has_github_token'] ) : ?><p class="description"><strong><?php esc_html_e( 'Connected:', 'yougitai-secure-showcase' ); ?></strong> <?php echo esc_html( $settings['github_auth_method'] === 'oauth' ? __( 'GitHub OAuth', 'yougitai-secure-showcase' ) : __( 'GitHub token', 'yougitai-secure-showcase' ) ); ?></p><?php endif; ?>
        </section>

        <section class="yougitai-card">
            <h2><?php esc_html_e( 'AI review mode', 'yougitai-secure-showcase' ); ?></h2>
            <label class="yougitai-field"><span><?php esc_html_e( 'Active AI mode', 'yougitai-secure-showcase' ); ?></span>
                <select name="ai_provider" id="yougitai-ai-provider">
                    <option value="direct" <?php selected( $settings['ai_provider'], 'direct' ); ?>><?php esc_html_e( 'ChatGPT (DIRECT)', 'yougitai-secure-showcase' ); ?></option>
                    <option value="openai" <?php selected( $settings['ai_provider'], 'openai' ); ?>><?php esc_html_e( 'ChatGPT / OpenAI API', 'yougitai-secure-showcase' ); ?></option>
                    <option value="anthropic" <?php selected( $settings['ai_provider'], 'anthropic' ); ?>><?php esc_html_e( 'Claude / Anthropic API', 'yougitai-secure-showcase' ); ?></option>
                    <option value="gemini" <?php selected( $settings['ai_provider'], 'gemini' ); ?>><?php esc_html_e( 'Gemini / Google API', 'yougitai-secure-showcase' ); ?></option>
                </select>
            </label>

            <div class="yougitai-direct-panel" data-yougitai-provider-panel="direct" <?php echo $settings['ai_provider'] === 'direct' ? '' : 'hidden'; ?>>
                <div class="yougitai-info-box yougitai-direct-intro">
                    <h3><?php esc_html_e( 'ChatGPT (DIRECT) — connect without an AI API key', 'yougitai-secure-showcase' ); ?></h3>
                    <p><?php esc_html_e( 'ChatGPT performs the analysis in your ChatGPT session. WordPress only exposes the repositories and review tools you explicitly authorize. Nothing is published through the direct connection.', 'yougitai-secure-showcase' ); ?></p>
                    <div class="yougitai-direct-steps">
                        <div><span>1</span><strong><?php esc_html_e( 'Copy the MCP server URL', 'yougitai-secure-showcase' ); ?></strong><small><?php esc_html_e( 'Paste only the server URL into the custom ChatGPT app.', 'yougitai-secure-showcase' ); ?></small></div>
                        <div><span>2</span><strong><?php esc_html_e( 'Choose OAuth in ChatGPT', 'yougitai-secure-showcase' ); ?></strong><small><?php esc_html_e( 'ChatGPT discovers the authorization endpoints automatically.', 'yougitai-secure-showcase' ); ?></small></div>
                        <div><span>3</span><strong><?php esc_html_e( 'Approve repositories in WordPress', 'yougitai-secure-showcase' ); ?></strong><small><?php esc_html_e( 'You decide exactly which repositories the connection may review.', 'yougitai-secure-showcase' ); ?></small></div>
                    </div>
                    <p class="description"><?php esc_html_e( 'Continue in the “ChatGPT (DIRECT) connection” wizard below. The normal OAuth flow does not require copying any access token.', 'yougitai-secure-showcase' ); ?></p>
                </div>
            </div>

            <div data-yougitai-provider-panel="api" <?php echo $settings['ai_provider'] === 'direct' ? 'hidden' : ''; ?>>
                <div class="yougitai-warning"><strong><?php esc_html_e( 'Privacy boundary:', 'yougitai-secure-showcase' ); ?></strong> <?php esc_html_e( 'When enabled, selected source files can be sent to the configured external AI provider for analysis before the source is discarded. External AI is disabled by default.', 'yougitai-secure-showcase' ); ?></div>
                <label class="yougitai-checkbox"><input type="checkbox" name="external_ai_enabled" value="1" <?php checked( $settings['external_ai_enabled'] ); ?>> <span><?php esc_html_e( 'Enable external AI processing', 'yougitai-secure-showcase' ); ?></span></label>
                <p class="description"><?php esc_html_e( 'Only the active API provider is used for new automatic AI reviews. Keys for the other providers remain encrypted and can be switched back on later.', 'yougitai-secure-showcase' ); ?></p>

                <div class="yougitai-ai-providers">
                    <div class="yougitai-subcard" data-yougitai-api-card="openai">
                        <h3><?php esc_html_e( 'ChatGPT / OpenAI API', 'yougitai-secure-showcase' ); ?></h3>
                        <label class="yougitai-field"><span><?php esc_html_e( 'Model', 'yougitai-secure-showcase' ); ?></span><input type="text" name="openai_model" value="<?php echo esc_attr( $settings['openai_model'] ); ?>"></label>
                        <label class="yougitai-field"><span><?php esc_html_e( 'OpenAI API key', 'yougitai-secure-showcase' ); ?></span><input type="password" autocomplete="new-password" name="openai_api_key" value="" placeholder="<?php echo esc_attr( $settings['has_openai_key'] ? __( 'Key is stored — enter a new key to replace it', 'yougitai-secure-showcase' ) : __( 'Enter API key', 'yougitai-secure-showcase' ) ); ?>"></label>
                        <?php if ( $settings['has_openai_key'] ) : ?><label class="yougitai-checkbox"><input type="checkbox" name="remove_openai_api_key" value="1"> <span><?php esc_html_e( 'Remove stored OpenAI API key', 'yougitai-secure-showcase' ); ?></span></label><?php endif; ?>
                    </div>

                    <div class="yougitai-subcard" data-yougitai-api-card="anthropic">
                        <h3><?php esc_html_e( 'Claude / Anthropic API', 'yougitai-secure-showcase' ); ?></h3>
                        <label class="yougitai-field"><span><?php esc_html_e( 'Model', 'yougitai-secure-showcase' ); ?></span><input type="text" name="anthropic_model" value="<?php echo esc_attr( $settings['anthropic_model'] ); ?>"></label>
                        <label class="yougitai-field"><span><?php esc_html_e( 'Anthropic API key', 'yougitai-secure-showcase' ); ?></span><input type="password" autocomplete="new-password" name="anthropic_api_key" value="" placeholder="<?php echo esc_attr( $settings['has_anthropic_key'] ? __( 'Key is stored — enter a new key to replace it', 'yougitai-secure-showcase' ) : __( 'Enter API key', 'yougitai-secure-showcase' ) ); ?>"></label>
                        <?php if ( $settings['has_anthropic_key'] ) : ?><label class="yougitai-checkbox"><input type="checkbox" name="remove_anthropic_api_key" value="1"> <span><?php esc_html_e( 'Remove stored Anthropic API key', 'yougitai-secure-showcase' ); ?></span></label><?php endif; ?>
                    </div>

                    <div class="yougitai-subcard" data-yougitai-api-card="gemini">
                        <h3><?php esc_html_e( 'Gemini / Google API', 'yougitai-secure-showcase' ); ?></h3>
                        <label class="yougitai-field"><span><?php esc_html_e( 'Model', 'yougitai-secure-showcase' ); ?></span><input type="text" name="gemini_model" value="<?php echo esc_attr( $settings['gemini_model'] ); ?>"></label>
                        <label class="yougitai-field"><span><?php esc_html_e( 'Gemini API key', 'yougitai-secure-showcase' ); ?></span><input type="password" autocomplete="new-password" name="gemini_api_key" value="" placeholder="<?php echo esc_attr( $settings['has_gemini_key'] ? __( 'Key is stored — enter a new key to replace it', 'yougitai-secure-showcase' ) : __( 'Enter API key', 'yougitai-secure-showcase' ) ); ?>"></label>
                        <?php if ( $settings['has_gemini_key'] ) : ?><label class="yougitai-checkbox"><input type="checkbox" name="remove_gemini_api_key" value="1"> <span><?php esc_html_e( 'Remove stored Gemini API key', 'yougitai-secure-showcase' ); ?></span></label><?php endif; ?>
                    </div>
                </div>
                <p class="description"><?php esc_html_e( 'All AI API keys are encrypted at rest using WordPress installation secrets and are never exposed to visitors.', 'yougitai-secure-showcase' ); ?></p>
            </div>
        </section>

        <section class="yougitai-card">
            <h2><?php esc_html_e( 'Showcase colors', 'yougitai-secure-showcase' ); ?></h2>
            <p class="description"><?php esc_html_e( 'These colors control the dark YougitAI interface only. The page background itself continues to come from your active WordPress/Elementor site design.', 'yougitai-secure-showcase' ); ?></p>
            <div class="yougitai-color-grid">
                <?php
                $yougitai_color_fields = [
                    'color_surface' => __( 'Main UI surface', 'yougitai-secure-showcase' ),
                    'color_panel' => __( 'Cards and panels', 'yougitai-secure-showcase' ),
                    'color_code' => __( 'Code / browser surface', 'yougitai-secure-showcase' ),
                    'color_border' => __( 'Borders', 'yougitai-secure-showcase' ),
                    'color_text' => __( 'Primary text', 'yougitai-secure-showcase' ),
                    'color_muted' => __( 'Secondary text', 'yougitai-secure-showcase' ),
                    'color_accent' => __( 'Accent / links', 'yougitai-secure-showcase' ),
                    'color_accent_hover' => __( 'Accent hover', 'yougitai-secure-showcase' ),
                    'color_nav_hover' => __( 'Navigation / tab hover', 'yougitai-secure-showcase' ),
                    'color_nav_underline' => __( 'Navigation / tab underline', 'yougitai-secure-showcase' ),
                    'color_tree_hover_bg' => __( 'Repository browser hover background', 'yougitai-secure-showcase' ),
                    'color_tree_hover_text' => __( 'Repository browser hover text', 'yougitai-secure-showcase' ),
                    'color_tree_active_bg' => __( 'Repository browser active background', 'yougitai-secure-showcase' ),
                    'color_tree_active_marker' => __( 'Repository browser active marker', 'yougitai-secure-showcase' ),
                    'color_focus' => __( 'Keyboard focus ring', 'yougitai-secure-showcase' ),
                    'color_progress' => __( 'Skill / progress bar', 'yougitai-secure-showcase' ),
                    'color_progress_track' => __( 'Skill / progress track', 'yougitai-secure-showcase' ),
                    'color_selected' => __( 'Active selection', 'yougitai-secure-showcase' ),
                    'color_warning_bg' => __( 'Warning / redaction background', 'yougitai-secure-showcase' ),
                    'color_warning_border' => __( 'Warning / redaction border', 'yougitai-secure-showcase' ),
                    'color_warning_text' => __( 'Warning / redaction text', 'yougitai-secure-showcase' ),
                    'color_blackout' => __( 'Redaction marker / blackout', 'yougitai-secure-showcase' ),
                    'color_blackout_edge' => __( 'Redaction marker edge', 'yougitai-secure-showcase' ),
                ];
                ?>
                <?php foreach ( $yougitai_color_fields as $field => $label ) : ?>
                    <label class="yougitai-color-field">
                        <span><?php echo esc_html( $label ); ?></span>
                        <span class="yougitai-color-control">
                            <input type="color" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $settings[ $field ] ); ?>">
                            <code><?php echo esc_html( $settings[ $field ] ); ?></code>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="description"><?php esc_html_e( 'All interactive colors can be configured independently: tabs, repository-browser hover/active states, focus ring, skill/progress bars and redaction markers. Elementor global colors do not control the YougitAI interface.', 'yougitai-secure-showcase' ); ?></p>
        </section>

        <section class="yougitai-card">
            <h2><?php esc_html_e( 'Data retention', 'yougitai-secure-showcase' ); ?></h2>
            <label class="yougitai-checkbox"><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'] ); ?>> <span><?php esc_html_e( 'Delete all YougitAI repository data, snapshots, rules, findings, logs, tokens, and settings when the plugin is uninstalled', 'yougitai-secure-showcase' ); ?></span></label>
            <p class="description"><?php esc_html_e( 'Leave this disabled if you want data preserved when temporarily removing the plugin.', 'yougitai-secure-showcase' ); ?></p>
        </section>

        <?php submit_button( __( 'Save settings', 'yougitai-secure-showcase' ) ); ?>
    </form>

    <section class="yougitai-card">
        <h2><?php esc_html_e( 'System status', 'yougitai-secure-showcase' ); ?></h2>
        <div class="yougitai-status-grid">
            <?php foreach ( $system_checks as $check ) : ?>
                <div class="yougitai-status-item <?php echo $check['ok'] ? 'is-ok' : 'is-warning'; ?>"><strong><?php echo esc_html( $check['ok'] ? '✓' : '!' ); ?> <?php echo esc_html( $check['label'] ); ?></strong><span><?php echo esc_html( $check['detail'] ); ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="yougitai-card yougitai-direct-wizard" id="yougitai-direct-connections" data-yougitai-provider-panel="direct" <?php echo $settings['ai_provider'] === 'direct' ? '' : 'hidden'; ?>>
        <div class="yougitai-card-header">
            <div>
                <p class="yougitai-eyebrow"><?php esc_html_e( 'ChatGPT (DIRECT)', 'yougitai-secure-showcase' ); ?></p>
                <h2><?php esc_html_e( 'Connect ChatGPT with OAuth', 'yougitai-secure-showcase' ); ?></h2>
                <p class="yougitai-muted"><?php esc_html_e( 'No API key and no token copying is required. ChatGPT opens WordPress, you sign in as an administrator, choose the repositories, and approve access.', 'yougitai-secure-showcase' ); ?></p>
            </div>
        </div>

        <div class="yougitai-oauth-flow">
            <div class="yougitai-wizard-step is-current">
                <div class="yougitai-wizard-number">1</div>
                <div class="yougitai-wizard-content">
                    <h3><?php esc_html_e( 'Copy the MCP server URL', 'yougitai-secure-showcase' ); ?></h3>
                    <p><?php esc_html_e( 'This is the only YougitAI value you normally need to paste into ChatGPT.', 'yougitai-secure-showcase' ); ?></p>
                    <div class="yougitai-copy-field"><label><?php esc_html_e( 'Server URL', 'yougitai-secure-showcase' ); ?></label><div><input id="yougitai-mcp-endpoint" type="text" readonly value="<?php echo esc_attr( $connected_ai_mcp_url ); ?>"><button type="button" class="button button-primary" data-yougitai-copy="#yougitai-mcp-endpoint" data-copied-label="<?php echo esc_attr__( 'Copied', 'yougitai-secure-showcase' ); ?>"><?php esc_html_e( 'Copy server URL', 'yougitai-secure-showcase' ); ?></button></div></div>
                    <?php
                    $ygai_diag_mcp = wp_remote_get( $connected_ai_mcp_url, [
                        'timeout' => 8,
                        'redirection' => 0,
                        'headers' => [ 'Accept' => 'application/json' ],
                    ] );
                    $ygai_diag_code = is_wp_error( $ygai_diag_mcp ) ? 0 : (int) wp_remote_retrieve_response_code( $ygai_diag_mcp );
                    $ygai_diag_www = is_wp_error( $ygai_diag_mcp ) ? '' : (string) wp_remote_retrieve_header( $ygai_diag_mcp, 'www-authenticate' );
                    $ygai_diag_resource_url = \YougitAI\SecureShowcase\Security\OAuthServer::resource_metadata_url();
                    $ygai_diag_auth_url = \YougitAI\SecureShowcase\Security\OAuthServer::authorization_metadata_url();
                    $ygai_diag_resource = wp_remote_get( $ygai_diag_resource_url, [ 'timeout' => 8, 'redirection' => 0 ] );
                    $ygai_diag_auth = wp_remote_get( $ygai_diag_auth_url, [ 'timeout' => 8, 'redirection' => 0 ] );
                    $ygai_diag_resource_ok = ! is_wp_error( $ygai_diag_resource ) && (int) wp_remote_retrieve_response_code( $ygai_diag_resource ) === 200;
                    $ygai_diag_auth_ok = ! is_wp_error( $ygai_diag_auth ) && (int) wp_remote_retrieve_response_code( $ygai_diag_auth ) === 200;
                    $ygai_diag_header_ok = stripos( $ygai_diag_www, 'resource_metadata=' ) !== false;
                    ?>
                    <details class="yougitai-info-box yougitai-oauth-diagnostics">
                        <summary><strong><?php esc_html_e( 'OAuth diagnostics', 'yougitai-secure-showcase' ); ?></strong></summary>
                        <p><?php esc_html_e( 'This checks the public responses that an MCP client receives from this WordPress site.', 'yougitai-secure-showcase' ); ?></p>
                        <ul>
                            <li><?php echo esc_html( $ygai_diag_code === 401 ? '✓' : '!' ); ?> <?php esc_html_e( 'MCP endpoint returns HTTP 401 before authorization', 'yougitai-secure-showcase' ); ?> <code><?php echo esc_html( (string) $ygai_diag_code ); ?></code></li>
                            <li><?php echo esc_html( $ygai_diag_header_ok ? '✓' : '!' ); ?> <?php esc_html_e( 'WWW-Authenticate advertises OAuth resource metadata', 'yougitai-secure-showcase' ); ?></li>
                            <li><?php echo esc_html( $ygai_diag_resource_ok ? '✓' : '!' ); ?> <?php esc_html_e( 'Protected Resource Metadata is publicly reachable', 'yougitai-secure-showcase' ); ?></li>
                            <li><?php echo esc_html( $ygai_diag_auth_ok ? '✓' : '!' ); ?> <?php esc_html_e( 'Authorization Server Metadata is publicly reachable', 'yougitai-secure-showcase' ); ?></li>
                            <?php
                            $ygai_schema_url = rest_url( 'yougitai-secure-showcase/v1/connected-ai/tool-schema' );
                            $ygai_schema_response = wp_remote_get( $ygai_schema_url, [ 'timeout' => 8, 'redirection' => 0 ] );
                            $ygai_schema_body = is_wp_error( $ygai_schema_response ) ? [] : json_decode( (string) wp_remote_retrieve_body( $ygai_schema_response ), true );
                            $ygai_schema_params = is_array( $ygai_schema_body['parameter_names'] ?? null ) ? $ygai_schema_body['parameter_names'] : [];
                            $ygai_schema_required = is_array( $ygai_schema_body['required'] ?? null ) ? $ygai_schema_body['required'] : [];
                            $ygai_cursor_exposed = in_array( 'cursor', $ygai_schema_params, true );
                            $ygai_prefix_exposed = in_array( 'prefix', $ygai_schema_params, true );
                            ?>
                            <li><?php echo esc_html( $ygai_cursor_exposed ? '✓' : '!' ); ?> <?php esc_html_e( 'get_repository_tree exposes cursor', 'yougitai-secure-showcase' ); ?> <code><?php echo esc_html( implode( ', ', $ygai_schema_params ) ); ?></code></li>
                            <li><?php echo esc_html( $ygai_prefix_exposed ? '✓' : '!' ); ?> <?php esc_html_e( 'get_repository_tree exposes prefix', 'yougitai-secure-showcase' ); ?> <code><?php echo esc_html( sprintf( 'required: %s', implode( ', ', $ygai_schema_required ) ) ); ?></code></li>
                        </ul>
                        <?php if ( ! $ygai_diag_header_ok ) : ?>
                            <div class="notice notice-error inline"><p><strong><?php esc_html_e( 'OAuth challenge header is missing externally.', 'yougitai-secure-showcase' ); ?></strong> <?php esc_html_e( 'The web server, reverse proxy, or security layer may be stripping WWW-Authenticate. ChatGPT cannot discover OAuth until this header reaches the public internet.', 'yougitai-secure-showcase' ); ?></p></div>
                        <?php endif; ?>
                    </details>
                </div>
            </div>

            <div class="yougitai-wizard-step is-current">
                <div class="yougitai-wizard-number">2</div>
                <div class="yougitai-wizard-content">
                    <h3><?php esc_html_e( 'Create the custom app in ChatGPT', 'yougitai-secure-showcase' ); ?></h3>
                    <ol class="yougitai-chatgpt-steps">
                        <li><?php esc_html_e( 'Open ChatGPT Settings → Apps → Advanced settings and enable Developer Mode if your plan/workspace offers it.', 'yougitai-secure-showcase' ); ?></li>
                        <li><?php esc_html_e( 'Open Apps → Create, choose Server URL, and paste the YougitAI server URL from step 1.', 'yougitai-secure-showcase' ); ?></li>
                        <li><strong><?php esc_html_e( 'Authentication: choose OAuth.', 'yougitai-secure-showcase' ); ?></strong> <?php esc_html_e( 'Do not create or paste a YougitAI bearer token.', 'yougitai-secure-showcase' ); ?></li>
                        <li><?php esc_html_e( 'Start the tool scan. ChatGPT discovers the YougitAI OAuth endpoints automatically and opens the WordPress authorization screen.', 'yougitai-secure-showcase' ); ?></li>
                    </ol>
                    <div class="yougitai-info-box"><strong><?php esc_html_e( 'What happens next?', 'yougitai-secure-showcase' ); ?></strong><p><?php esc_html_e( 'WordPress asks you which repositories ChatGPT may access. Only a logged-in WordPress administrator can approve the connection. YougitAI never grants publish or restore permission.', 'yougitai-secure-showcase' ); ?></p></div>
                </div>
            </div>

            <div class="yougitai-wizard-step is-current">
                <div class="yougitai-wizard-number">3</div>
                <div class="yougitai-wizard-content">
                    <h3><?php esc_html_e( 'Approve access in WordPress', 'yougitai-secure-showcase' ); ?></h3>
                    <p><?php esc_html_e( 'After ChatGPT redirects you back to this site, select only the repositories that the connection may review and click “Allow access”. You can revoke the connection here at any time.', 'yougitai-secure-showcase' ); ?></p>
                    <div class="yougitai-scope-summary">
                        <strong><?php esc_html_e( 'OAuth permissions granted by YougitAI', 'yougitai-secure-showcase' ); ?></strong>
                        <ul>
                            <li><?php esc_html_e( 'Read repository metadata and file trees', 'yougitai-secure-showcase' ); ?></li>
                            <li><?php esc_html_e( 'Request source code with detected secrets masked before it leaves WordPress', 'yougitai-secure-showcase' ); ?></li>
                            <li><?php esc_html_e( 'Read findings and create disabled redaction proposals', 'yougitai-secure-showcase' ); ?></li>
                            <li><?php esc_html_e( 'Create draft snapshots', 'yougitai-secure-showcase' ); ?></li>
                            <li><strong><?php esc_html_e( 'Cannot publish or restore a public snapshot', 'yougitai-secure-showcase' ); ?></strong></li>
                        </ul>
                    </div>
                    <div class="yougitai-copy-field"><label><?php esc_html_e( 'Starter prompt for the new chat', 'yougitai-secure-showcase' ); ?></label><div class="yougitai-copy-textarea"><textarea id="yougitai-direct-starter" rows="6" readonly><?php echo esc_textarea( __( 'Connect to YougitAI, inspect the available repositories and review the selected project for secrets, proprietary business logic, AI prompts, security logic, private endpoints, and reconstructable core IP. Start with metadata and the repository tree, request source only where necessary, then create conservative redaction proposals. Do not publish anything.', 'yougitai-secure-showcase' ) ); ?></textarea><button type="button" class="button" data-yougitai-copy="#yougitai-direct-starter" data-copied-label="<?php echo esc_attr__( 'Copied', 'yougitai-secure-showcase' ); ?>"><?php esc_html_e( 'Copy starter prompt', 'yougitai-secure-showcase' ); ?></button></div></div>
                </div>
            </div>
        </div>

        <?php if ( ! empty( $oauth_connections ) ) : ?>
            <details class="yougitai-existing-connections" open>
                <summary><?php printf( esc_html__( 'Authorized ChatGPT OAuth connections (%d)', 'yougitai-secure-showcase' ), count( $oauth_connections ) ); ?></summary>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Connection', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Repositories', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Access token expires', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Refresh access until', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Last used', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Status', 'yougitai-secure-showcase' ); ?></th><th></th></tr></thead><tbody>
                <?php foreach ( $oauth_connections as $connection ) : ?>
                    <?php $refresh_expired = strtotime( $connection['refresh_expires_at'] . ' UTC' ) <= time(); $revoked = ! empty( $connection['revoked_at'] ); $ids=json_decode((string)($connection['repository_ids']??'[]'),true); $ids=is_array($ids)?array_map('absint',$ids):[]; ?>
                    <tr><td><?php echo esc_html( $connection['label'] ); ?></td><td><?php $names=[];foreach($available_repositories as $repo){if(in_array((int)$repo['id'],$ids,true)){$names[]=$repo['title'];}}echo esc_html(implode(', ',$names)?:__('No repository access','yougitai-secure-showcase')); ?></td><td><?php echo esc_html( $connection['expires_at'] ); ?> UTC</td><td><?php echo esc_html( $connection['refresh_expires_at'] ); ?> UTC</td><td><?php echo esc_html( $connection['last_used_at'] ?: '—' ); ?></td><td><?php echo esc_html( $revoked ? __( 'Revoked', 'yougitai-secure-showcase' ) : ( $refresh_expired ? __( 'Expired', 'yougitai-secure-showcase' ) : __( 'Active', 'yougitai-secure-showcase' ) ) ); ?></td><td><?php if ( ! $revoked && ! $refresh_expired ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="yougitai_ss_revoke_oauth_connection"><input type="hidden" name="connection_id" value="<?php echo esc_attr( $connection['id'] ); ?>"><?php wp_nonce_field( 'yougitai_ss_revoke_oauth_connection_' . $connection['id'] ); ?><?php submit_button( __( 'Revoke', 'yougitai-secure-showcase' ), 'small', 'submit', false ); ?></form><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </details>
        <?php endif; ?>

        <details class="yougitai-existing-connections">
            <summary><?php esc_html_e( 'Advanced fallback: temporary bearer-token connection', 'yougitai-secure-showcase' ); ?></summary>
            <div class="yougitai-token-warning"><strong><?php esc_html_e( 'Use this only with MCP clients that explicitly support bearer-token authentication.', 'yougitai-secure-showcase' ); ?></strong><span><?php esc_html_e( 'ChatGPT currently presents OAuth for this custom app flow, so OAuth above is the recommended setup.', 'yougitai-secure-showcase' ); ?></span></div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-direct-create-form">
                <input type="hidden" name="action" value="yougitai_ss_create_connection"><?php wp_nonce_field( 'yougitai_ss_create_connection' ); ?>
                <div class="yougitai-direct-form-grid"><label><span><?php esc_html_e( 'Connection label', 'yougitai-secure-showcase' ); ?></span><input type="text" name="connection_label" value="MCP Client"></label><label><span><?php esc_html_e( 'Validity', 'yougitai-secure-showcase' ); ?></span><select name="connection_ttl"><option value="3600"><?php esc_html_e( '1 hour', 'yougitai-secure-showcase' ); ?></option><option value="86400" selected><?php esc_html_e( '24 hours', 'yougitai-secure-showcase' ); ?></option><option value="604800"><?php esc_html_e( '7 days', 'yougitai-secure-showcase' ); ?></option></select></label></div>
                <div class="yougitai-direct-repo-list"><strong><?php esc_html_e( 'Allowed repositories', 'yougitai-secure-showcase' ); ?></strong><?php foreach ( $available_repositories as $available_repository ) : ?><label class="yougitai-direct-repo-choice"><input type="checkbox" name="connection_repository_ids[]" value="<?php echo esc_attr( $available_repository['id'] ); ?>"> <span><strong><?php echo esc_html( $available_repository['title'] ); ?></strong><small><?php echo esc_html( $available_repository['owner'] . '/' . $available_repository['repo'] ); ?></small></span></label><?php endforeach; ?></div>
                <?php submit_button( __( 'Create temporary bearer token', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?>
            </form>
            <?php if ( ! empty( $connection_token_once['token'] ) ) : ?><div class="yougitai-copy-field is-secret"><label><?php esc_html_e( 'Temporary Bearer token', 'yougitai-secure-showcase' ); ?></label><div><input id="yougitai-direct-token" type="password" readonly value="<?php echo esc_attr( $connection_token_once['token'] ); ?>"><button type="button" class="button" data-yougitai-reveal="#yougitai-direct-token" data-show-label="<?php echo esc_attr__( 'Show', 'yougitai-secure-showcase' ); ?>" data-hide-label="<?php echo esc_attr__( 'Hide', 'yougitai-secure-showcase' ); ?>"><?php esc_html_e( 'Show', 'yougitai-secure-showcase' ); ?></button><button type="button" class="button" data-yougitai-copy="#yougitai-direct-token" data-copied-label="<?php echo esc_attr__( 'Copied', 'yougitai-secure-showcase' ); ?>"><?php esc_html_e( 'Copy token', 'yougitai-secure-showcase' ); ?></button></div></div><?php endif; ?>
            <?php if ( ! empty( $connections ) ) : ?><p class="description"><?php printf( esc_html__( '%d legacy bearer-token connection(s) exist. They can still be revoked below.', 'yougitai-secure-showcase' ), count( $connections ) ); ?></p><?php endif; ?>
        </details>
    </section>
</div>
