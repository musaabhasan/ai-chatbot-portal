<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

final readonly class FirewallDecision
{
    /**
     * @param array<int, string> $flags
     */
    public function __construct(
        public bool $allowed,
        public string $riskLevel,
        public array $flags,
        public string $message
    ) {
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'risk_level' => $this->riskLevel,
            'flags' => $this->flags,
            'message' => $this->message,
        ];
    }
}
