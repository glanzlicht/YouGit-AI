<?php
namespace YougitAI\SecureShowcase\AI;

final class NullProvider implements ProviderInterface {
    public function is_configured(): bool { return false; }

    public function analyze_file( string $path, string $content, array $context = [] ): array {
        return [ 'findings' => [], 'summary' => '' ];
    }
}
