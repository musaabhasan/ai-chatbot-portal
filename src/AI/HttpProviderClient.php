<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

use RuntimeException;

abstract class HttpProviderClient implements ProviderClient
{
    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $model,
        protected readonly int $timeoutSeconds = 45
    ) {
    }

    protected function postJson(string $url, array $payload, array $headers = []): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Provider request failed with status %d: %s', $status, $error ?: substr((string) $response, 0, 300)));
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Provider returned invalid JSON.');
        }

        return $decoded;
    }

    protected function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }
}
