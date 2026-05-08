<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

final readonly class IntentProfile
{
    /**
     * @param array<int, string> $signals
     */
    public function __construct(
        public string $intent,
        public string $riskTier,
        public ?string $recommendedProvider,
        public bool $ragRequired,
        public float $temperature,
        public int $maxOutputTokens,
        public array $signals = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'risk_tier' => $this->riskTier,
            'recommended_provider' => $this->recommendedProvider,
            'rag_required' => $this->ragRequired,
            'temperature' => $this->temperature,
            'max_output_tokens' => $this->maxOutputTokens,
            'signals' => $this->signals,
        ];
    }
}
