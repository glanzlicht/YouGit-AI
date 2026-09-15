<?php
namespace YougitAI\SecureShowcase\Security;

final class RateLimiter {
    public static function allow( string $key, int $limit = 120, int $window = 60 ): bool {
        $limit = max( 1, $limit );
        $window = max( 1, $window );
        $bucket = (int) floor( time() / $window );
        $transient = 'yougitai_ss_rl_' . hash( 'sha256', $key . '|' . $bucket );
        $count = (int) get_transient( $transient );
        if ( $count >= $limit ) {
            return false;
        }
        set_transient( $transient, $count + 1, $window + 5 );
        return true;
    }
}
