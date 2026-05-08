<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

final readonly class ProviderResult
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $content,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMs,
        public float $costUsd,
        public array $raw = []
    ) {
    }
}
