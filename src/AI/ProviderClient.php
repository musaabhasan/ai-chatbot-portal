<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

interface ProviderClient
{
    public function name(): string;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages, array $settings): ProviderResult;

    /**
     * @return array<int, float>
     */
    public function embed(string $text, array $settings): array;
}
