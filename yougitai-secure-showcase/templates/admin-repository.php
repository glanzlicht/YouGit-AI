<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$yougitai_status_labels = [
    'draft' => __( 'Draft', 'yougitai-secure-showcase' ),
    'published' => __( 'Published', 'yougitai-secure-showcase' ),
    'pending' => __( 'Pending', 'yougitai-secure-showcase' ),
    'needs_review' => __( 'Needs review', 'yougitai-secure-showcase' ),
    'ready' => __( 'Ready', 'yougitai-secure-showcase' ),
    'approved' => __( 'Approved', 'yougitai-secure-showcase' ),
    'public' => __( 'Public', 'yougitai-secure-showcase' ),
    'redacted' => __( 'Redacted', 'yougitai-secure-showcase' ),
    'hidden' => __( 'Hidden', 'yougitai-secure-showcase' ),
    'open' => __( 'Open', 'yougitai-secure-showcase' ),
    'resolved' => __( 'Resolved', 'yougitai-secure-showcase' ),
    'accepted' => __( 'Risk accepted', 'yougitai-secure-showcase' ),
    'ignored' => __( 'Ignored', 'yougitai-secure-showcase' ),
];
$yougitai_profile_labels = [
    'portfolio' => __( 'Portfolio', 'yougitai-secure-showcase' ),
    'balanced' => __( 'Balanced', 'yougitai-secure-showcase' ),
    'investor' => __( 'Investor', 'yougitai-secure-showcase' ),
    'maximum' => __( 'Maximum IP protection', 'yougitai-secure-showcase' ),
];
$yougitai_severity_labels = [
    'low' => __( 'Low', 'yougitai-secure-showcase' ),
    'medium' => __( 'Medium', 'yougitai-secure-showcase' ),
    'high' => __( 'High', 'yougitai-secure-showcase' ),
    'critical' => __( 'Critical', 'yougitai-secure-showcase' ),
];
$yougitai_source_labels = [
    'scanner' => __( 'Scanner', 'yougitai-secure-showcase' ),
    'ai' => __( 'AI', 'yougitai-secure-showcase' ),
    'manual' => __( 'Manual', 'yougitai-secure-showcase' ),
    'global' => __( 'Global', 'yougitai-secure-showcase' ),
];
$yougitai_recommendation_labels = [
    'review' => __( 'Review', 'yougitai-secure-showcase' ),
    'redact' => __( 'Redact', 'yougitai-secure-showcase' ),
    'hide' => __( 'Hide', 'yougitai-secure-showcase' ),
];
$yougitai_latest_snapshot = ! empty( $snapshots ) ? $snapshots[0] : null;
$yougitai_active_snapshot = null;
foreach ( $snapshots as $yougitai_snapshot_item ) {
    if ( (int) $repository['active_snapshot_id'] === (int) $yougitai_snapshot_item['id'] ) {
        $yougitai_active_snapshot = $yougitai_snapshot_item;
        break;
    }
}
$yougitai_active_rules = array_values( array_filter( $rules, static fn( $rule ) => ! empty( $rule['enabled'] ) ) );
$yougitai_pending_proposals = array_values( array_filter( $rules, static fn( $rule ) => empty( $rule['enabled'] ) && (string) ( $rule['source'] ?? '' ) === 'connected_ai' ) );
$yougitai_latest_rule_time = 0;
foreach ( $yougitai_active_rules as $yougitai_rule_item ) {
    $yougitai_latest_rule_time = max( $yougitai_latest_rule_time, strtotime( (string) ( $yougitai_rule_item['updated_at'] ?? $yougitai_rule_item['created_at'] ?? '' ) ) ?: 0 );
}
$yougitai_latest_snapshot_time = $yougitai_latest_snapshot ? ( strtotime( (string) $yougitai_latest_snapshot['created_at'] ) ?: 0 ) : 0;
$yougitai_protection_needs_apply = $yougitai_latest_snapshot && $yougitai_active_rules && $yougitai_latest_rule_time > $yougitai_latest_snapshot_time;
$yougitai_files_by_path = [];
$yougitai_protected_file_count = 0;
foreach ( $files as $yougitai_file_item ) {
    $yougitai_files_by_path[ (string) $yougitai_file_item['path'] ] = $yougitai_file_item;
    if ( in_array( (string) $yougitai_file_item['visibility'], [ 'redacted', 'hidden' ], true ) ) $yougitai_protected_file_count++;
}
$yougitai_finding_groups = [];
$yougitai_open_finding_count = 0;
$yougitai_critical_open_count = 0;
foreach ( $findings as $yougitai_finding_item ) {
    $yougitai_path = (string) $yougitai_finding_item['file_path'];
    if ( ! isset( $yougitai_finding_groups[ $yougitai_path ] ) ) {
        $yougitai_finding_groups[ $yougitai_path ] = [ 'items' => [], 'open_ids' => [], 'max_risk' => 0, 'severity' => 'low' ];
    }
    $yougitai_finding_groups[ $yougitai_path ]['items'][] = $yougitai_finding_item;
    $yougitai_finding_groups[ $yougitai_path ]['max_risk'] = max( $yougitai_finding_groups[ $yougitai_path ]['max_risk'], (int) $yougitai_finding_item['risk_score'] );
    if ( $yougitai_finding_item['status'] === 'open' ) {
        $yougitai_finding_groups[ $yougitai_path ]['open_ids'][] = (int) $yougitai_finding_item['id'];
        $yougitai_open_finding_count++;
        if ( $yougitai_finding_item['severity'] === 'critical' ) $yougitai_critical_open_count++;
    }
    $yougitai_rank = [ 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4 ];
    if ( ( $yougitai_rank[ $yougitai_finding_item['severity'] ] ?? 0 ) > ( $yougitai_rank[ $yougitai_finding_groups[ $yougitai_path ]['severity'] ] ?? 0 ) ) {
        $yougitai_finding_groups[ $yougitai_path ]['severity'] = $yougitai_finding_item['severity'];
    }
}
$yougitai_ai_labels = [
    'openai' => 'OpenAI / ChatGPT',
    'anthropic' => 'Anthropic / Claude',
    'gemini' => 'Google / Gemini',
    'direct' => 'ChatGPT (DIRECT)',
];
?>
<div class="wrap yougitai-admin">
    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=yougitai-secure-showcase' ) ); ?>">&larr; <?php esc_html_e( 'Repositories', 'yougitai-secure-showcase' ); ?></a></p>
    <div class="yougitai-page-title">
        <div>
            <h1><?php echo esc_html( $repository['title'] ); ?></h1>
            <code><?php echo esc_html( $repository['owner'] . '/' . $repository['repo'] ); ?></code>
        </div>
        <span class="yougitai-status yougitai-status-<?php echo esc_attr( $repository['status'] ); ?>"><?php echo esc_html( $repository['status'] === 'published' ? __( 'Published', 'yougitai-secure-showcase' ) : __( 'Draft', 'yougitai-secure-showcase' ) ); ?></span>
    </div>

    <?php if ( $message ) : ?><div class="notice notice-success"><p><?php echo esc_html( urldecode( $message ) ); ?></p></div><?php endif; ?>
    <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( urldecode( $error ) ); ?></p></div><?php endif; ?>

    <section class="yougitai-card yougitai-guided-workflow" aria-labelledby="yougitai-next-steps-title">
        <div class="yougitai-card-header">
            <div>
                <h2 id="yougitai-next-steps-title"><?php esc_html_e( 'What do I do next?', 'yougitai-secure-showcase' ); ?></h2>
                <p class="yougitai-muted"><?php esc_html_e( 'Follow these steps in order. You only need GitHub again when the repository itself has changed.', 'yougitai-secure-showcase' ); ?></p>
            </div>
        </div>
        <div class="yougitai-stepper">
            <div class="yougitai-step <?php echo $yougitai_latest_snapshot ? 'is-done' : 'is-current'; ?>">
                <span class="yougitai-step-number">1</span>
                <div><strong><?php esc_html_e( 'Bring in the repository once', 'yougitai-secure-showcase' ); ?></strong><p><?php echo $yougitai_latest_snapshot ? esc_html( sprintf( __( 'Done — snapshot #%d is available.', 'yougitai-secure-showcase' ), (int) $yougitai_latest_snapshot['id'] ) ) : esc_html__( 'Import the repository from GitHub to create the first safe draft.', 'yougitai-secure-showcase' ); ?></p></div>
            </div>
            <div class="yougitai-step <?php echo $yougitai_pending_proposals ? 'is-current' : ( $yougitai_active_rules ? 'is-done' : '' ); ?>">
                <span class="yougitai-step-number">2</span>
                <div><strong><?php esc_html_e( 'Review protection suggestions', 'yougitai-secure-showcase' ); ?></strong><p><?php printf( esc_html__( '%1$d active rules · %2$d suggestions still waiting for your decision.', 'yougitai-secure-showcase' ), count( $yougitai_active_rules ), count( $yougitai_pending_proposals ) ); ?></p><a class="button" href="#yougitai-rules"><?php esc_html_e( 'Review suggestions', 'yougitai-secure-showcase' ); ?></a></div>
            </div>
            <div class="yougitai-step <?php echo $yougitai_protection_needs_apply ? 'is-current' : ( $yougitai_latest_snapshot && $yougitai_active_rules ? 'is-done' : '' ); ?>">
                <span class="yougitai-step-number">3</span>
                <div>
                    <strong><?php esc_html_e( 'Apply approved protection', 'yougitai-secure-showcase' ); ?></strong>
                    <p><?php esc_html_e( 'This uses the existing safe draft and creates a new protected review draft. GitHub is not contacted and nothing is published.', 'yougitai-secure-showcase' ); ?></p>
                    <?php if ( $yougitai_latest_snapshot && $yougitai_active_rules && empty( $repository['show_original'] ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-inline-form">
                            <input type="hidden" name="action" value="yougitai_ss_apply_protection_rules">
                            <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                            <input type="hidden" name="base_snapshot_id" value="<?php echo esc_attr( $yougitai_latest_snapshot['id'] ); ?>">
                            <?php wp_nonce_field( 'yougitai_ss_apply_protection_rules_' . $repository['id'] ); ?>
                            <?php submit_button( __( 'Apply protection now', 'yougitai-secure-showcase' ), $yougitai_protection_needs_apply ? 'primary' : 'secondary', 'submit', false ); ?>
                        </form>
                    <?php elseif ( ! $yougitai_latest_snapshot ) : ?>
                        <small><?php esc_html_e( 'Create the first draft in step 1 before applying protection.', 'yougitai-secure-showcase' ); ?></small>
                    <?php elseif ( ! $yougitai_active_rules ) : ?>
                        <small><?php esc_html_e( 'Approve at least one protection rule first.', 'yougitai-secure-showcase' ); ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="yougitai-step <?php echo $yougitai_latest_snapshot ? 'is-current' : ''; ?>">
                <span class="yougitai-step-number">4</span>
                <div><strong><?php esc_html_e( 'Check the result and publish only when ready', 'yougitai-secure-showcase' ); ?></strong><p><?php esc_html_e( 'Open the newest review draft, inspect the visible code and redactions, then explicitly publish it. Your current live showcase stays unchanged until then.', 'yougitai-secure-showcase' ); ?></p><?php if ( $yougitai_latest_snapshot ) : ?><a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'page'=>'yougitai-secure-showcase', 'repository_id'=>(int) $repository['id'], 'snapshot_id'=>(int) $yougitai_latest_snapshot['id'] ], admin_url( 'admin.php' ) ) . '#yougitai-snapshots' ); ?>"><?php esc_html_e( 'Open newest review draft', 'yougitai-secure-showcase' ); ?></a><?php endif; ?></div>
            </div>
        </div>
        <div class="yougitai-info-box"><strong><?php esc_html_e( 'When do I use GitHub sync?', 'yougitai-secure-showcase' ); ?></strong><p><?php esc_html_e( 'Only when the source repository has changed and you want to bring those new GitHub changes into YougitAI. Approving or applying protection does not require another GitHub import.', 'yougitai-secure-showcase' ); ?></p></div>
    </section>

    <nav class="yougitai-section-nav" aria-label="<?php echo esc_attr__( 'Repository sections', 'yougitai-secure-showcase' ); ?>">
        <a href="#yougitai-overview"><?php esc_html_e( 'Overview', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-snapshots"><?php esc_html_e( 'Snapshots', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-protection"><?php esc_html_e( 'Code & redaction', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-rules"><?php esc_html_e( 'Protection rules', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-sync"><?php esc_html_e( 'GitHub sync', 'yougitai-secure-showcase' ); ?></a>
        <a href="#yougitai-audit"><?php esc_html_e( 'Audit history', 'yougitai-secure-showcase' ); ?></a>
    </nav>

    <section class="yougitai-workflow-status" id="yougitai-overview">
        <div class="yougitai-workflow-item is-live">
            <span><?php esc_html_e( 'Live showcase', 'yougitai-secure-showcase' ); ?></span>
            <strong><?php echo $yougitai_active_snapshot ? '#' . esc_html( $yougitai_active_snapshot['id'] ) : esc_html__( 'Not published yet', 'yougitai-secure-showcase' ); ?></strong>
            <small><?php esc_html_e( 'This is what visitors currently see.', 'yougitai-secure-showcase' ); ?></small>
        </div>
        <div class="yougitai-workflow-item">
            <span><?php esc_html_e( 'Latest review draft', 'yougitai-secure-showcase' ); ?></span>
            <strong><?php echo $yougitai_latest_snapshot ? '#' . esc_html( $yougitai_latest_snapshot['id'] ) : esc_html__( 'No snapshot yet', 'yougitai-secure-showcase' ); ?></strong>
            <small><?php esc_html_e( 'This is your newest working draft. Protection can be applied again without contacting GitHub.', 'yougitai-secure-showcase' ); ?></small>
        </div>
        <div class="yougitai-workflow-item">
            <span><?php esc_html_e( 'Active protection rules', 'yougitai-secure-showcase' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( count( array_filter( $rules, static fn( $rule ) => ! empty( $rule['enabled'] ) ) ) ) ); ?></strong>
            <small><?php echo ! empty( $repository['show_original'] ) ? esc_html__( 'Rules are retained but bypassed while Original source mode is active.', 'yougitai-secure-showcase' ) : esc_html__( 'Approved rules are re-applied to every new draft.', 'yougitai-secure-showcase' ); ?></small>
        </div>
        <div class="yougitai-workflow-item <?php echo ! empty( $repository['show_original'] ) ? 'is-original' : ''; ?>">
            <span><?php esc_html_e( 'Display mode', 'yougitai-secure-showcase' ); ?></span>
            <strong><?php echo ! empty( $repository['show_original'] ) ? esc_html__( 'Original source', 'yougitai-secure-showcase' ) : esc_html__( 'Secure showcase', 'yougitai-secure-showcase' ); ?></strong>
            <small><?php echo ! empty( $repository['show_original'] ) ? esc_html__( 'New snapshots contain unredacted text source.', 'yougitai-secure-showcase' ) : esc_html__( 'New snapshots are processed through protection and review.', 'yougitai-secure-showcase' ); ?></small>
        </div>
    </section>

    <section class="yougitai-card yougitai-publication-card">
        <div class="yougitai-card-header"><div><h2><?php esc_html_e( 'Publication & access', 'yougitai-secure-showcase' ); ?></h2><p class="yougitai-muted"><?php esc_html_e( 'Control whether the verified showcase is public, password-protected, private, or taken offline as a draft. Changing this does not delete snapshots.', 'yougitai-secure-showcase' ); ?></p></div></div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-publication-form">
            <input type="hidden" name="action" value="yougitai_ss_set_publication_state">
            <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
            <?php wp_nonce_field( 'yougitai_ss_set_publication_state_' . $repository['id'] ); ?>
            <label><span><?php esc_html_e( 'Publication state', 'yougitai-secure-showcase' ); ?></span><select name="publication_status"><option value="published" <?php selected( $repository['status'], 'published' ); ?>><?php esc_html_e( 'Published', 'yougitai-secure-showcase' ); ?></option><option value="draft" <?php selected( $repository['status'], 'draft' ); ?>><?php esc_html_e( 'Draft / offline', 'yougitai-secure-showcase' ); ?></option></select></label>
            <label><span><?php esc_html_e( 'Visitor access', 'yougitai-secure-showcase' ); ?></span><select name="access_mode"><option value="public" <?php selected( $repository['access_mode'] ?? 'public', 'public' ); ?>><?php esc_html_e( 'Public', 'yougitai-secure-showcase' ); ?></option><option value="password" <?php selected( $repository['access_mode'] ?? 'public', 'password' ); ?>><?php esc_html_e( 'Password protected', 'yougitai-secure-showcase' ); ?></option><option value="private" <?php selected( $repository['access_mode'] ?? 'public', 'private' ); ?>><?php esc_html_e( 'Private (admins only)', 'yougitai-secure-showcase' ); ?></option></select></label>
            <label><span><?php esc_html_e( 'Password', 'yougitai-secure-showcase' ); ?></span><input type="password" name="access_password" autocomplete="new-password" placeholder="<?php echo esc_attr__( 'Leave blank to keep the current password', 'yougitai-secure-showcase' ); ?>"></label>
            <div class="yougitai-actions"><?php submit_button( __( 'Save publication settings', 'yougitai-secure-showcase' ), 'primary', 'submit', false ); ?></div>
        </form>
        <p class="description"><?php esc_html_e( 'Draft/offline removes the showcase from public access immediately but keeps the verified snapshot so you can publish it again later.', 'yougitai-secure-showcase' ); ?></p>
    </section>

    <section class="yougitai-card yougitai-display-mode-card <?php echo ! empty( $repository['show_original'] ) ? 'is-original' : 'is-secure'; ?>">
        <div class="yougitai-card-header"><div><h2><?php esc_html_e( 'What should visitors see?', 'yougitai-secure-showcase' ); ?></h2><p class="yougitai-muted"><?php esc_html_e( 'Choose whether new snapshots are protected or intentionally mirror the repository source without redaction.', 'yougitai-secure-showcase' ); ?></p></div></div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-display-mode-form">
            <input type="hidden" name="action" value="yougitai_ss_set_display_mode">
            <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
            <?php wp_nonce_field( 'yougitai_ss_set_display_mode_' . $repository['id'] ); ?>
            <label class="yougitai-mode-option">
                <input type="radio" name="display_mode" value="secure" <?php checked( empty( $repository['show_original'] ) ); ?>>
                <span><strong><?php esc_html_e( 'Secure showcase (recommended)', 'yougitai-secure-showcase' ); ?></strong><small><?php esc_html_e( 'Secret scanner, manual rules and optional AI protection are applied. Syncs remain drafts until you publish them.', 'yougitai-secure-showcase' ); ?></small></span>
            </label>
            <label class="yougitai-mode-option yougitai-mode-original">
                <input type="radio" name="display_mode" value="original" <?php checked( ! empty( $repository['show_original'] ) ); ?>>
                <span><strong><?php esc_html_e( 'Show original source', 'yougitai-secure-showcase' ); ?></strong><small><?php esc_html_e( 'For repositories that may be shown completely. New snapshots bypass redaction rules, secret scanning and AI protection.', 'yougitai-secure-showcase' ); ?></small></span>
            </label>
            <div class="yougitai-original-confirm">
                <label><input type="checkbox" name="confirm_original" value="1"> <span><?php esc_html_e( 'I understand that enabling Original source mode can make the complete text source of this repository public after I publish a new snapshot.', 'yougitai-secure-showcase' ); ?></span></label>
            </div>
            <div class="yougitai-actions">
                <?php submit_button( __( 'Save display mode', 'yougitai-secure-showcase' ), 'primary', 'submit', false ); ?>
            </div>
        </form>
        <p class="description"><?php esc_html_e( 'Changing this setting never changes the currently published snapshot. Import a new snapshot and publish it explicitly before visitors see the new mode.', 'yougitai-secure-showcase' ); ?></p>
    </section>

    <section class="yougitai-card yougitai-quick-protection" id="yougitai-protection">
        <div class="yougitai-card-header"><div><h2><?php esc_html_e( 'Code & redaction', 'yougitai-secure-showcase' ); ?></h2><p class="yougitai-muted"><?php echo ! empty( $repository['show_original'] ) ? esc_html__( 'Original source mode is active. Existing rules are kept but are not applied to new snapshots until secure mode is enabled again.', 'yougitai-secure-showcase' ) : esc_html__( 'Manual protection is always available. AI is optional.', 'yougitai-secure-showcase' ); ?></p></div></div>
        <div class="yougitai-quick-steps">
            <div class="yougitai-quick-step"><strong><?php esc_html_e( '1. Existing safe draft', 'yougitai-secure-showcase' ); ?></strong><span><?php esc_html_e( 'Your imported draft is the working copy for protection decisions.', 'yougitai-secure-showcase' ); ?></span></div>
            <div class="yougitai-quick-step"><strong><?php esc_html_e( '2. Select a file', 'yougitai-secure-showcase' ); ?></strong><span><?php esc_html_e( 'Open a snapshot, choose a file, then hide the entire file or select code lines to redact.', 'yougitai-secure-showcase' ); ?></span></div>
            <div class="yougitai-quick-step"><strong><?php esc_html_e( '3. Apply, review & publish', 'yougitai-secure-showcase' ); ?></strong><span><?php esc_html_e( 'Apply approved rules to the existing draft, inspect the result, then publish explicitly.', 'yougitai-secure-showcase' ); ?></span></div>
        </div>
        <div class="yougitai-actions">
            <?php if ( $yougitai_latest_snapshot ) : ?><a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'page'=>'yougitai-secure-showcase', 'repository_id'=>(int) $repository['id'], 'snapshot_id'=>(int) $yougitai_latest_snapshot['id'] ], admin_url( 'admin.php' ) ) . '#yougitai-protection' ); ?>"><?php esc_html_e( 'Open latest draft for redaction', 'yougitai-secure-showcase' ); ?></a><?php endif; ?>
            <a class="button" href="#yougitai-rules"><?php esc_html_e( 'Manage protection rules', 'yougitai-secure-showcase' ); ?></a>
        </div>
    </section>

    <div class="yougitai-dashboard-grid">
        <section class="yougitai-card" id="yougitai-import">
            <h2><?php esc_html_e( 'Update source from GitHub', 'yougitai-secure-showcase' ); ?></h2>
            <p><?php esc_html_e( 'Use this only when the repository on GitHub has changed. You do not need this after approving protection suggestions.', 'yougitai-secure-showcase' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="yougitai_ss_import_snapshot">
                <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                <?php wp_nonce_field( 'yougitai_ss_import_snapshot_' . $repository['id'] ); ?>
                <?php submit_button( __( 'Fetch current GitHub version', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?>
            </form>
        </section>
        <section class="yougitai-card">
            <h2><?php esc_html_e( 'Protection', 'yougitai-secure-showcase' ); ?></h2>
            <p><strong><?php esc_html_e( 'Profile:', 'yougitai-secure-showcase' ); ?></strong> <?php echo esc_html( $yougitai_profile_labels[ $repository['protection_profile'] ] ?? $repository['protection_profile'] ); ?></p>
            <?php if ( ! empty( $repository['show_original'] ) ) : ?>
                <div class="yougitai-warning"><strong><?php esc_html_e( 'Original source mode is active.', 'yougitai-secure-showcase' ); ?></strong><p><?php esc_html_e( 'New snapshots are intentionally imported without redaction. Protection rules remain stored but are bypassed.', 'yougitai-secure-showcase' ); ?></p></div>
            <?php else : ?>
                <p><?php esc_html_e( 'Built-in secret scanning always runs. External AI runs only when explicitly enabled in settings.', 'yougitai-secure-showcase' ); ?></p>
            <?php endif; ?>
        </section>
        <section class="yougitai-card" id="yougitai-sync">
            <h2><?php esc_html_e( 'GitHub sync', 'yougitai-secure-showcase' ); ?></h2>
            <p><strong><?php esc_html_e( 'Mode:', 'yougitai-secure-showcase' ); ?></strong> <?php $sync_labels = [ 'manual' => __( 'Manual', 'yougitai-secure-showcase' ), 'webhook' => __( 'Webhook', 'yougitai-secure-showcase' ), 'hourly' => __( 'Hourly', 'yougitai-secure-showcase' ), 'daily' => __( 'Daily', 'yougitai-secure-showcase' ) ]; echo esc_html( $sync_labels[ $repository['sync_mode'] ] ?? __( 'Manual', 'yougitai-secure-showcase' ) ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-inline-form">
                <input type="hidden" name="action" value="yougitai_ss_queue_sync">
                <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                <?php wp_nonce_field( 'yougitai_ss_queue_sync_' . $repository['id'] ); ?>
                <?php submit_button( __( 'Queue secure sync', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?>
            </form>
            <hr>
            <p><strong><?php esc_html_e( 'Webhook URL', 'yougitai-secure-showcase' ); ?></strong><br><code class="yougitai-break"><?php echo esc_html( $webhook_url ); ?></code></p>
            <?php if ( $webhook_secret_once ) : ?>
                <div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Copy this webhook secret now. It will not be shown again:', 'yougitai-secure-showcase' ); ?></strong><br><code class="yougitai-break"><?php echo esc_html( $webhook_secret_once ); ?></code></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-inline-form">
                <input type="hidden" name="action" value="yougitai_ss_rotate_webhook">
                <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                <?php wp_nonce_field( 'yougitai_ss_rotate_webhook_' . $repository['id'] ); ?>
                <?php submit_button( $repository['webhook_secret'] ? __( 'Rotate webhook secret', 'yougitai-secure-showcase' ) : __( 'Generate webhook secret', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-rule-form">
                <input type="hidden" name="action" value="yougitai_ss_set_sync_mode">
                <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                <?php wp_nonce_field( 'yougitai_ss_set_sync_mode_' . $repository['id'] ); ?>
                <label><span><?php esc_html_e( 'Sync mode', 'yougitai-secure-showcase' ); ?></span><select name="sync_mode"><option value="manual" <?php selected( $repository['sync_mode'], 'manual' ); ?>><?php esc_html_e( 'Manual', 'yougitai-secure-showcase' ); ?></option><option value="webhook" <?php selected( $repository['sync_mode'], 'webhook' ); ?>><?php esc_html_e( 'Webhook', 'yougitai-secure-showcase' ); ?></option><option value="hourly" <?php selected( $repository['sync_mode'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'yougitai-secure-showcase' ); ?></option><option value="daily" <?php selected( $repository['sync_mode'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'yougitai-secure-showcase' ); ?></option></select></label>
                <div><?php submit_button( __( 'Save sync mode', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?></div>
            </form>
            <div class="yougitai-safety-note">
                <strong><?php echo ! empty( $repository['show_original'] ) ? esc_html__( 'Sync safety: the live snapshot still never changes automatically.', 'yougitai-secure-showcase' ) : esc_html__( 'Safe sync: existing redactions stay protected.', 'yougitai-secure-showcase' ); ?></strong>
                <p><?php echo ! empty( $repository['show_original'] ) ? esc_html__( 'Every sync creates a new draft containing the unredacted text source because Original source mode is active. Your currently published showcase remains unchanged until you explicitly publish that draft.', 'yougitai-secure-showcase' ) : esc_html__( 'Every sync creates a new draft and reapplies all active protection rules. Your currently published showcase stays unchanged until you review and publish a new snapshot. If a fingerprinted line or symbol can no longer be matched safely, YougitAI hides the file fail-closed instead of exposing new source code.', 'yougitai-secure-showcase' ); ?></p>
            </div>
        </section>
    </div>

    <section class="yougitai-card" id="yougitai-snapshots">
        <h2><?php esc_html_e( 'Snapshots', 'yougitai-secure-showcase' ); ?></h2>
        <?php if ( ! $snapshots ) : ?><div class="yougitai-empty-state"><strong><?php esc_html_e( 'No snapshots yet.', 'yougitai-secure-showcase' ); ?></strong><p><?php esc_html_e( 'Import the first secure draft before selecting files or creating visual redactions.', 'yougitai-secure-showcase' ); ?></p><a class="button button-primary" href="#yougitai-import"><?php esc_html_e( 'Go to import', 'yougitai-secure-showcase' ); ?></a></div><?php endif; ?>
        <?php if ( $snapshots ) : ?>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e( 'Snapshot', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Commit', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Files', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Findings', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Review', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Status', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Actions', 'yougitai-secure-showcase' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $snapshots as $snapshot ) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url( add_query_arg( [ 'page'=>'yougitai-secure-showcase', 'repository_id'=>(int) $repository['id'], 'snapshot_id'=>(int) $snapshot['id'] ], admin_url( 'admin.php' ) ) ); ?>">#<?php echo esc_html( $snapshot['id'] ); ?></a></td>
                            <td><code><?php echo esc_html( substr( (string) $snapshot['source_sha'], 0, 10 ) ); ?></code></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $snapshot['file_count'] ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( (int) $snapshot['finding_count'] ) ); ?><?php if ( (int) $snapshot['critical_count'] > 0 ) : ?> <strong class="yougitai-critical">(<?php echo esc_html( $snapshot['critical_count'] ); ?> <?php esc_html_e( 'critical', 'yougitai-secure-showcase' ); ?>)</strong><?php endif; ?></td>
                            <td><?php echo esc_html( $yougitai_status_labels[ $snapshot['review_status'] ] ?? $snapshot['review_status'] ); ?></td>
                            <td><?php echo esc_html( $yougitai_status_labels[ $snapshot['status'] ] ?? $snapshot['status'] ); ?><?php if ( (int) $repository['active_snapshot_id'] === (int) $snapshot['id'] ) : ?> <strong>· <?php esc_html_e( 'Active', 'yougitai-secure-showcase' ); ?></strong><?php endif; ?></td>
                            <td>
                                <?php if ( $snapshot['status'] === 'published' && ! empty( $snapshot['verification_hash'] ) && (int) $repository['active_snapshot_id'] !== (int) $snapshot['id'] ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="yougitai_ss_restore_snapshot">
                                        <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                                        <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $snapshot['id'] ); ?>">
                                        <?php wp_nonce_field( 'yougitai_ss_restore_snapshot_' . $snapshot['id'] ); ?>
                                        <button class="button button-small" type="submit"><?php esc_html_e( 'Restore verified snapshot', 'yougitai-secure-showcase' ); ?></button>
                                    </form>
                                <?php else : ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if ( $selected_snapshot ) : ?>
        <section class="yougitai-review-hero">
            <div>
                <p class="yougitai-eyebrow"><?php echo esc_html( sprintf( __( 'Snapshot #%d', 'yougitai-secure-showcase' ), (int) $selected_snapshot['id'] ) ); ?></p>
                <h2><?php esc_html_e( 'Review & protect this draft', 'yougitai-secure-showcase' ); ?></h2>
                <p><?php esc_html_e( 'This page shows only the sanitized draft stored by YougitAI. Scanner removals are already applied. Nothing here changes the currently live showcase until you explicitly publish.', 'yougitai-secure-showcase' ); ?></p>
            </div>
            <div class="yougitai-actions">
                <?php if ( ! in_array( $selected_snapshot['review_status'], [ 'approved', 'ready' ], true ) ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="yougitai_ss_approve_snapshot">
                        <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                        <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $selected_snapshot['id'] ); ?>">
                        <?php wp_nonce_field( 'yougitai_ss_approve_snapshot_' . $selected_snapshot['id'] ); ?>
                        <?php submit_button( __( 'Mark review complete', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="yougitai_ss_publish_snapshot">
                    <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                    <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $selected_snapshot['id'] ); ?>">
                    <?php wp_nonce_field( 'yougitai_ss_publish_snapshot_' . $selected_snapshot['id'] ); ?>
                    <?php submit_button( __( 'Verify & publish', 'yougitai-secure-showcase' ), 'primary', 'submit', false ); ?>
                </form>
            </div>
        </section>

        <section class="yougitai-review-summary" aria-label="<?php echo esc_attr__( 'Protection summary', 'yougitai-secure-showcase' ); ?>">
            <div class="yougitai-summary-stat is-protected"><strong><?php echo esc_html( number_format_i18n( $yougitai_protected_file_count ) ); ?></strong><span><?php esc_html_e( 'files already redacted or hidden', 'yougitai-secure-showcase' ); ?></span></div>
            <div class="yougitai-summary-stat <?php echo $yougitai_open_finding_count ? 'has-warning' : 'is-ok'; ?>"><strong><?php echo esc_html( number_format_i18n( $yougitai_open_finding_count ) ); ?></strong><span><?php esc_html_e( 'open review notes', 'yougitai-secure-showcase' ); ?></span></div>
            <div class="yougitai-summary-stat <?php echo $yougitai_critical_open_count ? 'has-danger' : 'is-ok'; ?>"><strong><?php echo esc_html( number_format_i18n( $yougitai_critical_open_count ) ); ?></strong><span><?php esc_html_e( 'open critical notes', 'yougitai-secure-showcase' ); ?></span></div>
            <div class="yougitai-summary-stat"><strong><?php echo esc_html( number_format_i18n( count( $files ) ) ); ?></strong><span><?php esc_html_e( 'files in this draft', 'yougitai-secure-showcase' ); ?></span></div>
        </section>

        <section class="yougitai-card yougitai-ai-review-card">
            <div class="yougitai-card-header">
                <div>
                    <h3><?php esc_html_e( 'AI protection', 'yougitai-secure-showcase' ); ?></h3>
                    <?php if ( $ai_provider === 'direct' ) : ?>
                        <p class="yougitai-muted"><?php esc_html_e( 'ChatGPT (DIRECT) is selected. The review is controlled from an authorized ChatGPT conversation, so WordPress does not need an AI API key.', 'yougitai-secure-showcase' ); ?></p>
                    <?php elseif ( $ai_configured ) : ?>
                        <p class="yougitai-muted"><?php echo esc_html( sprintf( __( '%s is configured. You can create a fresh draft with AI-assisted protection using the current repository profile.', 'yougitai-secure-showcase' ), $yougitai_ai_labels[ $ai_provider ] ?? $ai_provider ) ); ?></p>
                    <?php else : ?>
                        <p class="yougitai-muted"><?php esc_html_e( 'AI is optional and is not currently configured. The deterministic scanner and all manual protection tools still work without AI.', 'yougitai-secure-showcase' ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="yougitai-actions">
                    <?php if ( $ai_provider === 'direct' ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=yougitai-secure-showcase-settings' ) ); ?>"><?php esc_html_e( 'Open ChatGPT DIRECT setup', 'yougitai-secure-showcase' ); ?></a>
                    <?php elseif ( $ai_configured && empty( $repository['show_original'] ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="yougitai_ss_ai_protect_snapshot">
                            <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                            <?php wp_nonce_field( 'yougitai_ss_ai_protect_snapshot_' . $repository['id'] ); ?>
                            <button class="button button-primary" type="submit"><?php esc_html_e( 'Protect with AI', 'yougitai-secure-showcase' ); ?></button>
                        </form>
                    <?php else : ?>
                        <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=yougitai-secure-showcase-settings' ) ); ?>"><?php esc_html_e( 'Configure AI', 'yougitai-secure-showcase' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <p class="yougitai-safety-inline"><?php esc_html_e( '“Protect with AI” creates a new draft. It never rewrites or publishes the current live showcase automatically.', 'yougitai-secure-showcase' ); ?></p>
        </section>

        <section class="yougitai-card" id="yougitai-findings">
            <div class="yougitai-card-header">
                <div><h3><?php esc_html_e( 'Security review', 'yougitai-secure-showcase' ); ?></h3><p class="yougitai-muted"><?php esc_html_e( 'Findings are grouped by file. A scanner finding marked “already protected” means the suspicious value has already been removed from this draft.', 'yougitai-secure-showcase' ); ?></p></div>
            </div>
            <div class="yougitai-review-help">
                <div><strong><?php esc_html_e( 'Mark reviewed', 'yougitai-secure-showcase' ); ?></strong><span><?php esc_html_e( 'You checked the result and the current protection is correct.', 'yougitai-secure-showcase' ); ?></span></div>
                <div><strong><?php esc_html_e( 'Accept risk', 'yougitai-secure-showcase' ); ?></strong><span><?php esc_html_e( 'You knowingly accept the remaining risk. This does not restore removed source.', 'yougitai-secure-showcase' ); ?></span></div>
                <div><strong><?php esc_html_e( 'Ignore as false positive', 'yougitai-secure-showcase' ); ?></strong><span><?php esc_html_e( 'The note is not relevant. This also does not undo an automatic redaction in the current draft.', 'yougitai-secure-showcase' ); ?></span></div>
            </div>
            <?php if ( ! $yougitai_finding_groups ) : ?>
                <div class="yougitai-empty-state"><strong><?php esc_html_e( 'No security findings', 'yougitai-secure-showcase' ); ?></strong><p><?php esc_html_e( 'No scanner or AI review notes were detected for this snapshot.', 'yougitai-secure-showcase' ); ?></p></div>
            <?php else : ?>
                <form id="yougitai-bulk-findings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-bulk-toolbar">
                    <input type="hidden" name="action" value="yougitai_ss_bulk_findings">
                    <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                    <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $selected_snapshot['id'] ); ?>">
                    <?php wp_nonce_field( 'yougitai_ss_bulk_findings_' . $selected_snapshot['id'] ); ?>
                    <label><input type="checkbox" data-yougitai-select-all="finding-group"> <span><?php esc_html_e( 'Select all open groups', 'yougitai-secure-showcase' ); ?></span></label>
                    <select name="finding_status">
                        <option value="resolved"><?php esc_html_e( 'Mark selected as reviewed', 'yougitai-secure-showcase' ); ?></option>
                        <option value="accepted"><?php esc_html_e( 'Accept risk for selected', 'yougitai-secure-showcase' ); ?></option>
                        <option value="ignored"><?php esc_html_e( 'Ignore selected as false positives', 'yougitai-secure-showcase' ); ?></option>
                    </select>
                    <button class="button button-secondary" type="submit"><?php esc_html_e( 'Apply to selected', 'yougitai-secure-showcase' ); ?></button>
                </form>
                <?php foreach ( $yougitai_finding_groups as $yougitai_path => $yougitai_group ) : ?>
                    <?php
                    $yougitai_related_file = $yougitai_files_by_path[ $yougitai_path ] ?? null;
                    $yougitai_already_protected = $yougitai_related_file && in_array( (string) $yougitai_related_file['visibility'], [ 'redacted', 'hidden' ], true );
                    $yougitai_unique_titles = array_values( array_unique( array_map( static fn( $item ) => (string) $item['title'], $yougitai_group['items'] ) ) );
                    $yougitai_open_csv = implode( ',', $yougitai_group['open_ids'] );
                    ?>
                    <article class="yougitai-finding-group yougitai-severity-<?php echo esc_attr( $yougitai_group['severity'] ); ?>">
                        <div class="yougitai-finding-group-select">
                            <?php if ( $yougitai_open_csv !== '' ) : ?><input type="checkbox" form="yougitai-bulk-findings" name="finding_groups[]" value="<?php echo esc_attr( $yougitai_open_csv ); ?>" data-yougitai-select-item="finding-group" aria-label="<?php echo esc_attr__( 'Select this finding group', 'yougitai-secure-showcase' ); ?>"><?php endif; ?>
                        </div>
                        <div class="yougitai-finding-main">
                            <div class="yougitai-finding-title-row">
                                <strong><?php echo esc_html( $yougitai_path ); ?></strong>
                                <?php if ( $yougitai_already_protected ) : ?><span class="yougitai-badge is-success"><?php esc_html_e( 'Already protected in this draft', 'yougitai-secure-showcase' ); ?></span><?php endif; ?>
                            </div>
                            <p><?php echo esc_html( sprintf( _n( '%d review note in this file', '%d review notes in this file', count( $yougitai_group['items'] ), 'yougitai-secure-showcase' ), count( $yougitai_group['items'] ) ) ); ?> · <?php echo esc_html( sprintf( __( 'highest risk %d/100', 'yougitai-secure-showcase' ), $yougitai_group['max_risk'] ) ); ?></p>
                            <ul class="yougitai-finding-reasons">
                                <?php foreach ( $yougitai_unique_titles as $yougitai_title ) : ?><li><?php echo esc_html( $yougitai_title ); ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                        <?php if ( $yougitai_open_csv !== '' ) : ?>
                            <div class="yougitai-actions yougitai-group-actions">
                                <?php foreach ( [ 'resolved' => __( 'Mark reviewed', 'yougitai-secure-showcase' ), 'accepted' => __( 'Accept risk', 'yougitai-secure-showcase' ), 'ignored' => __( 'Ignore', 'yougitai-secure-showcase' ) ] as $yougitai_group_status => $yougitai_group_label ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="yougitai_ss_bulk_findings"><input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>"><input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $selected_snapshot['id'] ); ?>"><input type="hidden" name="finding_groups[]" value="<?php echo esc_attr( $yougitai_open_csv ); ?>"><input type="hidden" name="finding_status" value="<?php echo esc_attr( $yougitai_group_status ); ?>">
                                        <?php wp_nonce_field( 'yougitai_ss_bulk_findings_' . $selected_snapshot['id'] ); ?>
                                        <button class="button button-small" type="submit"><?php echo esc_html( $yougitai_group_label ); ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="yougitai-card" id="yougitai-files">
            <div class="yougitai-card-header"><div><h3><?php esc_html_e( 'Files & manual redaction', 'yougitai-secure-showcase' ); ?></h3><p class="yougitai-muted"><?php esc_html_e( 'Select one file to open the visual editor, or select several files and hide them together on the next import.', 'yougitai-secure-showcase' ); ?></p></div></div>
            <form id="yougitai-bulk-files" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-bulk-toolbar">
                <input type="hidden" name="action" value="yougitai_ss_bulk_files"><input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>"><input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $selected_snapshot['id'] ); ?>">
                <?php wp_nonce_field( 'yougitai_ss_bulk_files_' . $selected_snapshot['id'] ); ?>
                <label><input type="checkbox" data-yougitai-select-all="file"> <span><?php esc_html_e( 'Select all files', 'yougitai-secure-showcase' ); ?></span></label>
                <button class="button button-secondary yougitai-danger-soft" type="submit"><?php esc_html_e( 'Hide selected files on next import', 'yougitai-secure-showcase' ); ?></button>
            </form>
            <div class="yougitai-file-list yougitai-file-list-checkable">
                <?php foreach ( $files as $file ) : ?>
                    <?php $yougitai_risk_text = (int) $file['risk_score'] === 0 ? __( 'No risk detected', 'yougitai-secure-showcase' ) : sprintf( __( 'Risk %d/100', 'yougitai-secure-showcase' ), (int) $file['risk_score'] ); ?>
                    <div class="<?php echo $selected_file && (int) $selected_file['id'] === (int) $file['id'] ? 'is-current' : ''; ?>">
                        <input type="checkbox" form="yougitai-bulk-files" name="file_ids[]" value="<?php echo esc_attr( $file['id'] ); ?>" data-yougitai-select-item="file" aria-label="<?php echo esc_attr__( 'Select file', 'yougitai-secure-showcase' ); ?>">
                        <a href="<?php echo esc_url( add_query_arg( [ 'page'=>'yougitai-secure-showcase', 'repository_id'=>(int) $repository['id'], 'snapshot_id'=>(int) $selected_snapshot['id'], 'file_id'=>(int) $file['id'] ], admin_url( 'admin.php' ) ) . '#yougitai-redaction-editor' ); ?>"><code><?php echo esc_html( $file['path'] ); ?></code></a>
                        <span class="yougitai-status yougitai-status-<?php echo esc_attr( $file['visibility'] ); ?>"><?php echo esc_html( $yougitai_status_labels[ $file['visibility'] ] ?? $file['visibility'] ); ?> · <?php echo esc_html( $yougitai_risk_text ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ( $selected_file ) : ?>
            <section class="yougitai-card" id="yougitai-redaction-editor">
                <div class="yougitai-card-header"><div><h3><?php esc_html_e( 'Visual redaction editor', 'yougitai-secure-showcase' ); ?></h3><p><code><?php echo esc_html( $selected_file['path'] ); ?></code></p></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="yougitai_ss_add_rule"><input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>"><input type="hidden" name="rule_type" value="path"><input type="hidden" name="file_path" value=""><input type="hidden" name="target" value="<?php echo esc_attr( $selected_file['path'] ); ?>"><input type="hidden" name="rule_action" value="hide"><input type="hidden" name="replacement" value="[PROTECTED]"><?php wp_nonce_field( 'yougitai_ss_add_rule_' . $repository['id'] ); ?><button class="button button-secondary yougitai-danger-soft" type="submit"><?php esc_html_e( 'Hide entire file on next import', 'yougitai-secure-showcase' ); ?></button></form></div>
                <?php if ( $selected_file['visibility'] === 'hidden' || $selected_file['content'] === '' ) : ?>
                    <p><?php esc_html_e( 'This file is hidden and has no persisted source content to display.', 'yougitai-secure-showcase' ); ?></p>
                <?php else : ?>
                    <div class="yougitai-code-editor" aria-label="<?php echo esc_attr__( 'Protected code preview', 'yougitai-secure-showcase' ); ?>">
                        <?php foreach ( preg_split( '/\R/', (string) $selected_file['content'] ) as $yougitai_line_number => $yougitai_line ) : ?>
                            <div class="yougitai-code-line" data-line="<?php echo esc_attr( $yougitai_line_number + 1 ); ?>"><span><?php echo esc_html( $yougitai_line_number + 1 ); ?></span><code><?php echo esc_html( $yougitai_line ); ?></code></div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-rule-form">
                        <input type="hidden" name="action" value="yougitai_ss_add_line_rule">
                        <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                        <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $selected_snapshot['id'] ); ?>">
                        <input type="hidden" name="file_id" value="<?php echo esc_attr( $selected_file['id'] ); ?>">
                        <?php wp_nonce_field( 'yougitai_ss_add_line_rule_' . $selected_file['id'] ); ?>
                        <label><span><?php esc_html_e( 'Start line', 'yougitai-secure-showcase' ); ?></span><input type="number" min="1" name="line_start" required></label>
                        <label><span><?php esc_html_e( 'End line', 'yougitai-secure-showcase' ); ?></span><input type="number" min="1" name="line_end" required></label>
                        <label><span><?php esc_html_e( 'Replacement', 'yougitai-secure-showcase' ); ?></span><input type="text" name="replacement" value="[PROTECTED]"></label>
                        <div><?php submit_button( __( 'Protect selected line range', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?></div>
                    </form>
                    <p class="description"><?php esc_html_e( 'The selected range is fingerprinted. If those lines change upstream, the next import hides the entire file fail-closed and creates a critical review finding.', 'yougitai-secure-showcase' ); ?></p>
                    <hr>
                    <h4><?php esc_html_e( 'Protect class or function', 'yougitai-secure-showcase' ); ?></h4>
                    <p><?php esc_html_e( 'Symbol rules follow a named class, function, or method across line-number changes. If the symbol is ambiguous, missing, or its signature changes unexpectedly, the file is hidden fail-closed.', 'yougitai-secure-showcase' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-rule-form">
                        <input type="hidden" name="action" value="yougitai_ss_add_symbol_rule">
                        <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                        <input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $selected_snapshot['id'] ); ?>">
                        <input type="hidden" name="file_id" value="<?php echo esc_attr( $selected_file['id'] ); ?>">
                        <?php wp_nonce_field( 'yougitai_ss_add_symbol_rule_' . $selected_file['id'] ); ?>
                        <label><span><?php esc_html_e( 'Symbol name', 'yougitai-secure-showcase' ); ?></span><input type="text" name="symbol" required placeholder="RecommendationEngine"></label>
                        <label><span><?php esc_html_e( 'Replacement', 'yougitai-secure-showcase' ); ?></span><input type="text" name="replacement" value="[PROTECTED SYMBOL]"></label>
                        <div><?php submit_button( __( 'Protect symbol', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?></div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <section class="yougitai-card" id="yougitai-rules">
        <h2><?php esc_html_e( 'Protection rules', 'yougitai-secure-showcase' ); ?></h2>
        <p><?php echo ! empty( $repository['show_original'] ) ? esc_html__( 'Protection rules are retained but currently bypassed because Original source mode is active. Switch back to Secure showcase mode before applying protection.', 'yougitai-secure-showcase' ) : esc_html__( 'Approved rules can be applied directly to the existing safe draft. Path rules can hide complete files; string and regular-expression rules redact matching content.', 'yougitai-secure-showcase' ); ?></p>
        <?php if ( $rules ) : ?>
            <?php $pending_ai_rules = array_values( array_filter( $rules, static fn( array $rule ): bool => $rule['source'] === 'connected_ai' && ! (int) $rule['enabled'] ) ); ?>
            <?php if ( $pending_ai_rules ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-bulk-rule-form" data-yougitai-bulk-rule-form>
                    <input type="hidden" name="action" value="yougitai_ss_bulk_approve_rules">
                    <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
                    <?php wp_nonce_field( 'yougitai_ss_bulk_approve_rules_' . $repository['id'] ); ?>
                    <div class="yougitai-rule-filters" data-yougitai-rule-filters>
                        <label><span><?php esc_html_e( 'Decision', 'yougitai-secure-showcase' ); ?></span>
                            <select data-yougitai-rule-filter="decision">
                                <option value=""><?php esc_html_e( 'All decisions', 'yougitai-secure-showcase' ); ?></option>
                                <option value="hide">HIDE</option><option value="redact">REDACT</option><option value="abstract">ABSTRACT</option><option value="show">SHOW</option>
                            </select>
                        </label>
                        <label><span><?php esc_html_e( 'Source', 'yougitai-secure-showcase' ); ?></span>
                            <select data-yougitai-rule-filter="source">
                                <option value=""><?php esc_html_e( 'All sources', 'yougitai-secure-showcase' ); ?></option>
                                <option value="scanner"><?php esc_html_e( 'Scanner', 'yougitai-secure-showcase' ); ?></option>
                                <option value="ai"><?php esc_html_e( 'AI', 'yougitai-secure-showcase' ); ?></option>
                                <option value="manual"><?php esc_html_e( 'Manual', 'yougitai-secure-showcase' ); ?></option>
                            </select>
                        </label>
                        <button class="button" type="button" data-yougitai-rule-filter-reset><?php esc_html_e( 'Reset filters', 'yougitai-secure-showcase' ); ?></button>
                        <span class="description" data-yougitai-rule-filter-count></span>
                    </div>
                    <div class="yougitai-bulk-toolbar">
                        <label><input type="checkbox" data-yougitai-select-all="rule-proposal"> <span><?php esc_html_e( 'Select all visible', 'yougitai-secure-showcase' ); ?></span></label>
                        <button class="button button-primary" type="submit" data-yougitai-confirm="<?php echo esc_attr__( 'Approve the selected protection proposals? After approval, use “Apply protection now” to create a new review draft.', 'yougitai-secure-showcase' ); ?>"><?php esc_html_e( 'Approve selected', 'yougitai-secure-showcase' ); ?></button>
                        <button class="button" type="button" data-yougitai-approve-all-rules data-confirm="<?php echo esc_attr__( 'Approve all visible protection proposals? After approval, use “Apply protection now” to create a new review draft.', 'yougitai-secure-showcase' ); ?>"><?php esc_html_e( 'Approve all visible', 'yougitai-secure-showcase' ); ?></button>
                    </div>
                    <table class="widefat striped"><thead><tr><th class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'yougitai-secure-showcase' ); ?></span></th><th><?php esc_html_e( 'Type', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'File', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Target', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Action', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Source', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Status', 'yougitai-secure-showcase' ); ?></th></tr></thead><tbody>
                    <?php foreach ( $rules as $rule ) : ?>
                        <?php
                        $yougitai_decision = $rule['action'] === 'hide' ? 'hide' : ( $rule['action'] === 'redact' ? 'redact' : '' );
                        if ( ! empty( $rule['notes'] ) && preg_match( '/\bdecision=(show|abstract|redact|hide)\b/i', (string) $rule['notes'], $yougitai_decision_match ) ) {
                            $yougitai_decision = strtolower( $yougitai_decision_match[1] );
                        }
                        $yougitai_source = in_array( $rule['source'], [ 'connected_ai', 'ai' ], true ) ? 'ai' : ( $rule['source'] === 'scanner' ? 'scanner' : 'manual' );
                        ?>
                        <tr data-yougitai-rule-row data-rule-decision="<?php echo esc_attr( $yougitai_decision ); ?>" data-rule-source="<?php echo esc_attr( $yougitai_source ); ?>">
                            <th class="check-column"><?php if ( $rule['source'] === 'connected_ai' && ! (int) $rule['enabled'] ) : ?><input type="checkbox" name="rule_ids[]" value="<?php echo esc_attr( $rule['id'] ); ?>" data-yougitai-select-item="rule-proposal"><?php endif; ?></th>
                            <td><?php echo esc_html( [ 'path'=>__( 'Path pattern', 'yougitai-secure-showcase' ), 'string'=>__( 'Exact string', 'yougitai-secure-showcase' ), 'regex'=>__( 'Regular expression', 'yougitai-secure-showcase' ), 'line_range'=>__( 'Line range', 'yougitai-secure-showcase' ), 'symbol'=>__( 'Symbol', 'yougitai-secure-showcase' ) ][ $rule['rule_type'] ] ?? $rule['rule_type'] ); ?></td>
                            <td><code><?php echo esc_html( $rule['file_path'] ?: '—' ); ?></code></td>
                            <td><code><?php echo esc_html( $rule['target'] ); ?></code></td>
                            <td><?php echo esc_html( [ 'hide'=>__( 'Hide file', 'yougitai-secure-showcase' ), 'redact'=>__( 'Redact match', 'yougitai-secure-showcase' ) ][ $rule['action'] ] ?? $rule['action'] ); ?></td>
                            <td><?php echo esc_html( $rule['source'] === 'connected_ai' ? __( 'Connected AI proposal', 'yougitai-secure-showcase' ) : __( 'Manual', 'yougitai-secure-showcase' ) ); ?></td>
                            <td><?php if ( $rule['source'] === 'connected_ai' && ! empty( $rule['notes'] ) ) : ?><span class="description"><?php echo esc_html( $rule['notes'] ); ?></span><br><?php endif; ?><?php if ( $rule['source'] === 'connected_ai' && ! (int) $rule['enabled'] ) : ?><button class="button button-small" type="submit" name="single_rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>" formnovalidate><?php esc_html_e( 'Approve proposal', 'yougitai-secure-showcase' ); ?></button><?php else : ?><?php echo esc_html( (int) $rule['enabled'] ? __( 'Active', 'yougitai-secure-showcase' ) : __( 'Disabled', 'yougitai-secure-showcase' ) ); ?><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </form>
            <?php else : ?>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Type', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'File', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Target', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Action', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Source', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Status', 'yougitai-secure-showcase' ); ?></th></tr></thead><tbody>
                <?php foreach ( $rules as $rule ) : ?><tr><td><?php echo esc_html( [ 'path'=>__( 'Path pattern', 'yougitai-secure-showcase' ), 'string'=>__( 'Exact string', 'yougitai-secure-showcase' ), 'regex'=>__( 'Regular expression', 'yougitai-secure-showcase' ), 'line_range'=>__( 'Line range', 'yougitai-secure-showcase' ), 'symbol'=>__( 'Symbol', 'yougitai-secure-showcase' ) ][ $rule['rule_type'] ] ?? $rule['rule_type'] ); ?></td><td><code><?php echo esc_html( $rule['file_path'] ?: '—' ); ?></code></td><td><code><?php echo esc_html( $rule['target'] ); ?></code></td><td><?php echo esc_html( [ 'hide'=>__( 'Hide file', 'yougitai-secure-showcase' ), 'redact'=>__( 'Redact match', 'yougitai-secure-showcase' ) ][ $rule['action'] ] ?? $rule['action'] ); ?></td><td><?php echo esc_html( $rule['source'] === 'connected_ai' ? __( 'Connected AI proposal', 'yougitai-secure-showcase' ) : __( 'Manual', 'yougitai-secure-showcase' ) ); ?></td><td><?php echo esc_html( (int) $rule['enabled'] ? __( 'Active', 'yougitai-secure-showcase' ) : __( 'Disabled', 'yougitai-secure-showcase' ) ); ?></td></tr><?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yougitai-rule-form">
            <input type="hidden" name="action" value="yougitai_ss_add_rule">
            <input type="hidden" name="repository_id" value="<?php echo esc_attr( $repository['id'] ); ?>">
            <?php wp_nonce_field( 'yougitai_ss_add_rule_' . $repository['id'] ); ?>
            <label><span><?php esc_html_e( 'Rule type', 'yougitai-secure-showcase' ); ?></span><select name="rule_type"><option value="path"><?php esc_html_e( 'Path pattern', 'yougitai-secure-showcase' ); ?></option><option value="string"><?php esc_html_e( 'Exact string', 'yougitai-secure-showcase' ); ?></option><option value="regex"><?php esc_html_e( 'Regular expression', 'yougitai-secure-showcase' ); ?></option></select></label>
            <label><span><?php esc_html_e( 'File path (optional)', 'yougitai-secure-showcase' ); ?></span><input type="text" name="file_path" placeholder="src/service.php"></label>
            <label><span><?php esc_html_e( 'Target', 'yougitai-secure-showcase' ); ?></span><input type="text" name="target" required placeholder="src/private/*"></label>
            <label><span><?php esc_html_e( 'Action', 'yougitai-secure-showcase' ); ?></span><select name="rule_action"><option value="hide"><?php esc_html_e( 'Hide file', 'yougitai-secure-showcase' ); ?></option><option value="redact"><?php esc_html_e( 'Redact match', 'yougitai-secure-showcase' ); ?></option></select></label>
            <label><span><?php esc_html_e( 'Replacement', 'yougitai-secure-showcase' ); ?></span><input type="text" name="replacement" value="[PROTECTED]"></label>
            <div><?php submit_button( __( 'Add protection rule', 'yougitai-secure-showcase' ), 'secondary', 'submit', false ); ?></div>
        </form>
    </section>
    <section class="yougitai-card" id="yougitai-audit">
        <h2><?php esc_html_e( 'Audit history', 'yougitai-secure-showcase' ); ?></h2>
        <p><?php esc_html_e( 'Security-relevant repository actions are recorded here for review and troubleshooting.', 'yougitai-secure-showcase' ); ?></p>
        <?php if ( empty( $audit_events ) ) : ?>
            <p><?php esc_html_e( 'No audit events yet.', 'yougitai-secure-showcase' ); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e( 'Time', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Event', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Snapshot', 'yougitai-secure-showcase' ); ?></th><th><?php esc_html_e( 'Message', 'yougitai-secure-showcase' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $audit_events as $event ) : ?>
                        <tr>
                            <td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event['created_at'] ) ); ?></td>
                            <td><code><?php echo esc_html( $event['event_type'] ); ?></code></td>
                            <td><?php echo $event['snapshot_id'] ? '#' . esc_html( $event['snapshot_id'] ) : '—'; ?></td>
                            <td><?php echo esc_html( $event['message'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</div>
