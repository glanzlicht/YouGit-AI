<?php
namespace YougitAI\SecureShowcase\Security;

final class SecretVault {
    private static function key(): string {
        $material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|yougitai-secure-showcase';
        return hash( 'sha256', $material, true );
    }

    public static function encrypt( string $value ): string {
        if ( $value === '' ) {
            return '';
        }
        if ( function_exists( 'sodium_crypto_secretbox' ) ) {
            $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $cipher = sodium_crypto_secretbox( $value, $nonce, self::key() );
            return 'sodium:' . base64_encode( $nonce . $cipher );
        }
        // Fail safely on hosts without libsodium: use authenticated OpenSSL encryption if available.
        if ( function_exists( 'openssl_encrypt' ) ) {
            $iv = random_bytes( 12 );
            $tag = '';
            $cipher = openssl_encrypt( $value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
            if ( $cipher !== false ) {
                return 'openssl:' . base64_encode( $iv . $tag . $cipher );
            }
        }
        return '';
    }

    public static function decrypt( string $payload ): string {
        if ( $payload === '' ) {
            return '';
        }
        if ( str_starts_with( $payload, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
            $raw = base64_decode( substr( $payload, 7 ), true );
            if ( $raw === false || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
                return '';
            }
            $nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $plain = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
            return is_string( $plain ) ? $plain : '';
        }
        if ( str_starts_with( $payload, 'openssl:' ) && function_exists( 'openssl_decrypt' ) ) {
            $raw = base64_decode( substr( $payload, 8 ), true );
            if ( $raw === false || strlen( $raw ) < 29 ) {
                return '';
            }
            $iv = substr( $raw, 0, 12 );
            $tag = substr( $raw, 12, 16 );
            $cipher = substr( $raw, 28 );
            $plain = openssl_decrypt( $cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
            return is_string( $plain ) ? $plain : '';
        }
        return '';
    }

    public static function random_secret( int $bytes = 32 ): string {
        return bin2hex( random_bytes( max( 16, $bytes ) ) );
    }
}
