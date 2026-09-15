<?php
namespace YougitAI\SecureShowcase\Repository;

use YougitAI\SecureShowcase\Security\SecretScanner;

final class RedactionEngine {
    private SecretScanner $scanner;
    private SymbolLocator $symbols;

    public function __construct() {
        $this->scanner = new SecretScanner();
        $this->symbols = new SymbolLocator();
    }

    /**
     * Source content enters this method only in memory. The returned value is safe-zone data.
     *
     * @return array{content:string,visibility:string,risk_score:int,notes:array<int,string>,findings:array<int,array<string,mixed>>,redactions:array<int,array<string,mixed>>}
     */
    public function build_public_content( string $path, string $content, array $rules = [], array $ai_findings = [] ): array {
        $notes = [];
        $findings = [];
        $risk = 0;
        $visibility = 'public';
        $redactions = [];

        if ( $this->is_blocked_path( $path ) ) {
            return [
                'content' => '',
                'visibility' => 'hidden',
                'risk_score' => 100,
                'notes' => [ __( 'Blocked by a built-in security rule.', 'yougitai-secure-showcase' ) ],
                'redactions' => [],
                'findings' => [ [
                    'type' => 'blocked_path',
                    'severity' => 'critical',
                    'risk_score' => 100,
                    'source' => 'scanner',
                    'title' => __( 'Sensitive path blocked automatically', 'yougitai-secure-showcase' ),
                    'recommendation' => 'hide',
                ] ],
            ];
        }

        foreach ( $rules as $rule ) {
            if ( empty( $rule['enabled'] ) || ! $this->rule_matches_file( $rule, $path ) ) {
                continue;
            }
            $action = (string) ( $rule['action'] ?? '' );
            if ( $action === 'hide' ) {
                return [
                    'content' => '',
                    'visibility' => 'hidden',
                    'risk_score' => max( 80, (int) ( $rule['risk_score'] ?? 80 ) ),
                    'notes' => [ __( 'Hidden by a repository protection rule.', 'yougitai-secure-showcase' ) ],
                    'findings' => [],
                    'redactions' => [],
                ];
            }
        }

        $hits = $this->scanner->scan( $content );
        if ( $hits ) {
            $risk = max( array_map( static fn( array $hit ): int => (int) $hit['risk_score'], $hits ) );
            $visibility = 'redacted';
            foreach ( $hits as $hit ) {
                $redactions = array_merge( $redactions, $this->literal_redactions( $content, (string) ( $hit['match'] ?? '' ), 'secret', '[REDACTED]' ) );
            }
            $content = $this->scanner->redact( $content, $hits );
            foreach ( $hits as $hit ) {
                $findings[] = [
                    'type' => $hit['type'],
                    'severity' => $hit['severity'],
                    'risk_score' => $hit['risk_score'],
                    'source' => 'scanner',
                    'title' => __( 'Potential secret removed automatically', 'yougitai-secure-showcase' ),
                    'recommendation' => 'redact',
                ];
            }
            $notes[] = __( 'Potential secrets were automatically redacted.', 'yougitai-secure-showcase' );
        }

        foreach ( $rules as $rule ) {
            if ( empty( $rule['enabled'] ) || ! $this->rule_matches_file( $rule, $path ) ) {
                continue;
            }
            $type = (string) ( $rule['rule_type'] ?? '' );
            $action = (string) ( $rule['action'] ?? '' );
            $target = (string) ( $rule['target'] ?? '' );
            $replacement = (string) ( $rule['replacement'] ?? '[PROTECTED]' );

            if ( $action !== 'redact' || $target === '' ) {
                continue;
            }
            if ( $type === 'symbol' ) {
                $decoded = json_decode( $target, true );
                $symbol = is_array( $decoded ) ? (string) ( $decoded['symbol'] ?? '' ) : '';
                $located = $this->symbols->locate( $path, $content, $symbol );
                if ( ! $located ) {
                    return $this->stale_symbol_result();
                }
                $expected_signature = is_array( $decoded ) ? (string) ( $decoded['signature_hash'] ?? '' ) : '';
                if ( $expected_signature !== '' && ! hash_equals( $expected_signature, hash( 'sha256', $located['signature'] ) ) ) {
                    return $this->stale_symbol_result();
                }
                $redactions[] = $this->range_redaction( $content, (int) $located['start'], (int) $located['end'], 'implementation', $replacement );
                $content = substr( $content, 0, $located['start'] ) . $replacement . substr( $content, $located['end'] );
                $visibility = 'redacted';
                $risk = max( $risk, 80 );
                continue;
            }
            if ( $type === 'line_range' ) {
                $decoded = json_decode( $target, true );
                $lines = preg_split( '/\R/', $content );
                $start = is_array( $decoded ) ? (int) ( $decoded['start'] ?? 0 ) : 0;
                $end = is_array( $decoded ) ? (int) ( $decoded['end'] ?? 0 ) : 0;
                if ( ! is_array( $decoded ) || ! is_array( $lines ) || $start < 1 || $end < $start || $end > count( $lines ) ) {
                    return [
                        'content' => '',
                        'visibility' => 'hidden',
                        'risk_score' => 100,
                        'notes' => [ __( 'A line protection rule no longer matches and the file was hidden fail-closed.', 'yougitai-secure-showcase' ) ],
                        'redactions' => [],
                        'findings' => [ [
                            'type' => 'stale_line_rule',
                            'severity' => 'critical',
                            'risk_score' => 100,
                            'source' => 'manual',
                            'title' => __( 'Line protection rule requires review', 'yougitai-secure-showcase' ),
                            'recommendation' => 'hide',
                        ] ],
                    ];
                }
                $segment = implode( "\n", array_slice( $lines, $start - 1, $end - $start + 1 ) );
                if ( ! hash_equals( (string) ( $decoded['hash'] ?? '' ), hash( 'sha256', $segment ) ) ) {
                    return [
                        'content' => '',
                        'visibility' => 'hidden',
                        'risk_score' => 100,
                        'notes' => [ __( 'A line protection rule changed upstream and the file was hidden fail-closed.', 'yougitai-secure-showcase' ) ],
                        'redactions' => [],
                        'findings' => [ [
                            'type' => 'stale_line_rule',
                            'severity' => 'critical',
                            'risk_score' => 100,
                            'source' => 'manual',
                            'title' => __( 'Line protection rule changed upstream', 'yougitai-secure-showcase' ),
                            'recommendation' => 'hide',
                        ] ],
                    ];
                }
                $redactions[] = [
                    'kind' => 'implementation',
                    'line_start' => $start,
                    'line_end' => $end,
                    'column_start' => 1,
                    'original_length' => strlen( $segment ),
                    'replacement' => $replacement,
                ];
                $replacement_lines = preg_split( '/\R/', $replacement );
                array_splice( $lines, $start - 1, $end - $start + 1, is_array( $replacement_lines ) ? $replacement_lines : [ $replacement ] );
                $content = implode( "\n", $lines );
                $visibility = 'redacted';
                $risk = max( $risk, 70 );
                continue;
            }
            if ( $type === 'string' ) {
                $redactions = array_merge( $redactions, $this->literal_redactions( $content, $target, 'manual', $replacement ) );
                $content = str_replace( $target, $replacement, $content );
                $visibility = 'redacted';
                $risk = max( $risk, 60 );
            } elseif ( $type === 'regex' ) {
                $matches = [];
                if ( @preg_match_all( $target, $content, $matches, PREG_OFFSET_CAPTURE ) && ! empty( $matches[0] ) ) {
                    foreach ( $matches[0] as $match ) {
                        $redactions[] = $this->range_redaction( $content, (int) $match[1], (int) $match[1] + strlen( (string) $match[0] ), 'manual', $replacement );
                    }
                }
                $result = @preg_replace( $target, $replacement, $content );
                if ( is_string( $result ) ) {
                    $content = $result;
                    $visibility = 'redacted';
                    $risk = max( $risk, 60 );
                }
            }
        }

        foreach ( $ai_findings as $finding ) {
            $score = min( 100, max( 0, (int) ( $finding['risk_score'] ?? 0 ) ) );
            $risk = max( $risk, $score );
            $recommendation = (string) ( $finding['recommendation'] ?? 'review' );
            $findings[] = array_merge( $finding, [ 'source' => 'ai' ] );

            if ( $recommendation === 'hide' && $score >= 70 ) {
                $visibility = 'hidden';
                $content = '';
                continue;
            }
            if ( $recommendation === 'redact' && $score >= 70 && $visibility !== 'hidden' ) {
                $visibility = 'redacted';
                $redactions[] = [
                    'kind' => 'ai',
                    'line_start' => 1,
                    'line_end' => max( 1, substr_count( $content, "\n" ) + 1 ),
                    'column_start' => 1,
                    'original_length' => strlen( $content ),
                    'replacement' => '[PROTECTED BY SECURITY REVIEW]',
                ];
                $content = '[PROTECTED BY SECURITY REVIEW]';
                $notes[] = __( 'File content was withheld by the AI-assisted security review.', 'yougitai-secure-showcase' );
            }
        }

        return [
            'content' => $content,
            'visibility' => $visibility,
            'risk_score' => $risk,
            'notes' => $notes,
            'findings' => $findings,
            'redactions' => $redactions,
        ];
    }

    private function literal_redactions( string $content, string $needle, string $kind, string $replacement ): array {
        if ( $needle === '' ) return [];
        $items = [];
        $offset = 0;
        while ( ( $pos = strpos( $content, $needle, $offset ) ) !== false ) {
            $items[] = $this->range_redaction( $content, $pos, $pos + strlen( $needle ), $kind, $replacement );
            $offset = $pos + max( 1, strlen( $needle ) );
        }
        return $items;
    }

    private function range_redaction( string $content, int $start, int $end, string $kind, string $replacement ): array {
        $prefix = substr( $content, 0, max( 0, $start ) );
        $line_start = substr_count( $prefix, "\n" ) + 1;
        $line_prefix_pos = strrpos( $prefix, "\n" );
        $column_start = $line_prefix_pos === false ? strlen( $prefix ) + 1 : strlen( $prefix ) - $line_prefix_pos;
        $segment = substr( $content, max( 0, $start ), max( 0, $end - $start ) );
        return [
            'kind' => $kind,
            'line_start' => $line_start,
            'line_end' => $line_start + substr_count( $segment, "\n" ),
            'column_start' => max( 1, $column_start ),
            'original_length' => strlen( $segment ),
            'replacement' => $replacement,
        ];
    }

    public function is_blocked_path( string $path ): bool {
        $normalized = ltrim( str_replace( '\\', '/', strtolower( $path ) ), '/' );
        $basename = basename( $normalized );
        $blocked_names = [
            '.env', '.env.local', '.env.production', '.env.development', '.env.staging',
            'credentials.json', 'secrets.json', 'id_rsa', 'id_ed25519', '.npmrc', '.pypirc',
            'wp-config.php', 'service-account.json', 'firebase-adminsdk.json',
        ];
        if ( in_array( $basename, $blocked_names, true ) ) return true;
        if ( preg_match( '/\.(?:pem|p12|pfx|key|keystore|jks)$/i', $basename ) ) return true;
        return (bool) preg_match( '/(^|\/)(private|secrets?|credentials|certificates?)(\/|$)/i', $normalized );
    }

    private function stale_symbol_result(): array {
        return [
            'content' => '',
            'visibility' => 'hidden',
            'risk_score' => 100,
            'notes' => [ __( 'A symbol protection rule could not be matched safely and the file was hidden fail-closed.', 'yougitai-secure-showcase' ) ],
            'redactions' => [],
            'findings' => [ [
                'type' => 'stale_symbol_rule',
                'severity' => 'critical',
                'risk_score' => 100,
                'source' => 'manual',
                'title' => __( 'Symbol protection rule requires review', 'yougitai-secure-showcase' ),
                'recommendation' => 'hide',
            ] ],
        ];
    }

    private function rule_matches_file( array $rule, string $path ): bool {
        $file_path = trim( (string) ( $rule['file_path'] ?? '' ) );
        if ( $file_path !== '' && $file_path !== $path ) return false;
        if ( ( $rule['rule_type'] ?? '' ) === 'path' ) {
            $target = (string) ( $rule['target'] ?? '' );
            if ( $target === '' ) return false;
            return fnmatch( $target, $path, FNM_PATHNAME );
        }
        return true;
    }
}
