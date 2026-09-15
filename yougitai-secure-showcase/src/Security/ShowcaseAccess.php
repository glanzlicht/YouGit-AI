<?php
namespace YougitAI\SecureShowcase\Security;

final class ShowcaseAccess {
    public function is_allowed( array $repository ): bool {
        if ( (string) ( $repository['status'] ?? '' ) !== 'published' || empty( $repository['active_snapshot_id'] ) ) {
            return false;
        }
        $mode = (string) ( $repository['access_mode'] ?? 'public' );
        if ( $mode === 'public' ) {
            return true;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        if ( $mode === 'private' ) {
            return false;
        }
        if ( $mode !== 'password' || empty( $repository['access_password_hash'] ) ) {
            return false;
        }
        $cookie = isset( $_COOKIE[ $this->cookie_name( (int) $repository['id'] ) ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $this->cookie_name( (int) $repository['id'] ) ] ) ) : '';
        return $cookie !== '' && hash_equals( $this->cookie_value( $repository ), $cookie );
    }

    public function maybe_authenticate( array $repository ): bool {
        if ( (string) ( $repository['access_mode'] ?? 'public' ) !== 'password' || empty( $repository['access_password_hash'] ) ) {
            return false;
        }
        if ( strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) !== 'POST' || empty( $_POST['yougitai_showcase_password_submit'] ) ) {
            return false;
        }
        $nonce = isset( $_POST['yougitai_showcase_password_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['yougitai_showcase_password_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'yougitai_showcase_password_' . (int) $repository['id'] ) ) {
            return false;
        }
        $password = isset( $_POST['yougitai_showcase_password'] ) ? (string) wp_unslash( $_POST['yougitai_showcase_password'] ) : '';
        if ( ! wp_check_password( $password, (string) $repository['access_password_hash'] ) ) {
            return false;
        }
        setcookie( $this->cookie_name( (int) $repository['id'] ), $this->cookie_value( $repository ), [
            'expires' => time() + DAY_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
        $_COOKIE[ $this->cookie_name( (int) $repository['id'] ) ] = $this->cookie_value( $repository );
        return true;
    }

    public function password_form( array $repository, bool $failed = false ): string {
        ob_start(); ?>
        <div class="yougitai-access-gate">
            <h2><?php esc_html_e( 'Password-protected showcase', 'yougitai-secure-showcase' ); ?></h2>
            <p><?php esc_html_e( 'Enter the showcase password to continue.', 'yougitai-secure-showcase' ); ?></p>
            <?php if ( $failed ) : ?><div class="yougitai-notice"><?php esc_html_e( 'The password was not accepted.', 'yougitai-secure-showcase' ); ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="yougitai_showcase_password_submit" value="1">
                <input type="hidden" name="yougitai_showcase_password_nonce" value="<?php echo esc_attr( wp_create_nonce( 'yougitai_showcase_password_' . (int) $repository['id'] ) ); ?>">
                <label><span class="screen-reader-text"><?php esc_html_e( 'Password', 'yougitai-secure-showcase' ); ?></span><input type="password" name="yougitai_showcase_password" autocomplete="current-password" required></label>
                <button type="submit" class="yougitai-button"><?php esc_html_e( 'Open showcase', 'yougitai-secure-showcase' ); ?></button>
            </form>
        </div>
        <?php return (string) ob_get_clean();
    }

    private function cookie_name( int $repository_id ): string {
        return 'yougitai_showcase_' . $repository_id;
    }

    private function cookie_value( array $repository ): string {
        return hash_hmac( 'sha256', (int) $repository['id'] . '|' . (string) $repository['access_password_hash'], wp_salt( 'auth' ) );
    }
}
