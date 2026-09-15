<?php
namespace YougitAI\SecureShowcase\Security;

final class SecretScanner {
    /** @return array<int,array{type:string,match:string,severity:string,risk_score:int}> */
    public function scan( string $content ): array {
        $patterns = [
            'private_key' => [ '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/i', 'critical', 100 ],
            'github_token' => [ '/\b(?:gh[pousr]_[A-Za-z0-9_]{20,}|github_pat_[A-Za-z0-9_]{20,})\b/', 'critical', 100 ],
            'aws_access_key' => [ '/\bAKIA[0-9A-Z]{16}\b/', 'critical', 100 ],
            'google_api_key' => [ '/\bAIza[0-9A-Za-z_-]{30,}\b/', 'high', 95 ],
            'stripe_secret' => [ '/\bsk_(?:live|test)_[0-9A-Za-z]{16,}\b/', 'critical', 100 ],
            'slack_token' => [ '/\bxox[baprs]-[0-9A-Za-z-]{10,}\b/', 'high', 95 ],
            'jwt' => [ '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', 'high', 90 ],
            'database_url' => [ '#\b(?:mysql|postgres(?:ql)?|mongodb(?:\+srv)?|redis)://[^\s"\']+#i', 'critical', 100 ],
            'basic_auth_url' => [ '#https?://[^\s/:]+:[^\s/@]+@[^\s]+#i', 'critical', 100 ],
            'generic_secret' => [ '/(?i)(api[_-]?key|client[_-]?secret|secret|token|password|passwd|pwd)\s*[:=]\s*["\'][^"\']{8,}["\']/', 'high', 90 ],
            'authorization_bearer' => [ '/(?i)authorization\s*[:=]\s*["\']?bearer\s+[A-Za-z0-9._~-]{16,}/', 'high', 95 ],
        ];
        $hits = [];
        foreach ( $patterns as $type => [ $pattern, $severity, $risk_score ] ) {
            if ( preg_match_all( $pattern, $content, $matches ) ) {
                foreach ( $matches[0] as $match ) {
                    $hits[] = [
                        'type' => $type,
                        'match' => (string) $match,
                        'severity' => $severity,
                        'risk_score' => $risk_score,
                    ];
                }
            }
        }
        return $hits;
    }

    public function redact( string $content, array $hits ): string {
        foreach ( $hits as $hit ) {
            if ( empty( $hit['match'] ) ) {
                continue;
            }
            $content = str_replace( (string) $hit['match'], '[REDACTED]', $content );
        }
        return $content;
    }
}
