<?php
namespace YougitAI\SecureShowcase\AI;

interface ProviderInterface {
    public function is_configured(): bool;

    /**
     * Analyze source code without persisting it.
     *
     * @return array{findings:array<int,array<string,mixed>>,summary:string}
     */
    public function analyze_file( string $path, string $content, array $context = [] );
}
