<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

use RuntimeException;

final class ProviderRouter
{
    /**
     * @param array<string, ProviderClient> $clients
     */
    public function __construct(private readonly array $clients)
    {
    }

    public function chat(array $providerOrder, array $messages, array $settings): ProviderResult
    {
        $errors = [];

        foreach ($providerOrder as $provider) {
            $client = $this->clients[$provider] ?? null;
            if (!$client instanceof ProviderClient) {
                continue;
            }

            try {
                return $client->chat($messages, $settings);
            } catch (\Throwable $exception) {
                $errors[$provider] = $exception->getMessage();
            }
        }

        throw new RuntimeException('All configured providers failed: ' . json_encode($errors, JSON_UNESCAPED_SLASHES));
    }

    public function embeddingClient(string $provider): ProviderClient
    {
        $client = $this->clients[$provider] ?? null;
        if (!$client instanceof ProviderClient) {
            throw new RuntimeException('Embedding provider is not configured.');
        }

        return $client;
    }
}
