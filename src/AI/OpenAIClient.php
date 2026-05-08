<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

final class OpenAIClient extends HttpProviderClient
{
    public function name(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $settings): ProviderResult
    {
        $started = microtime(true);
        $model = $settings['model'] ?? $this->model;
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $settings['temperature'] ?? 0.2,
            'top_p' => $settings['top_p'] ?? 0.9,
            'max_tokens' => $settings['max_output_tokens'] ?? 1200,
        ];

        $raw = $this->postJson('https://api.openai.com/v1/chat/completions', $payload, ['Authorization: Bearer ' . $this->apiKey]);
        $content = (string) ($raw['choices'][0]['message']['content'] ?? '');
        $input = (int) ($raw['usage']['prompt_tokens'] ?? $this->estimateTokens(json_encode($messages)));
        $output = (int) ($raw['usage']['completion_tokens'] ?? $this->estimateTokens($content));

        return new ProviderResult('openai', (string) $model, $content, $input, $output, (int) ((microtime(true) - $started) * 1000), 0.0, $raw);
    }

    public function embed(string $text, array $settings): array
    {
        $model = $settings['embedding_model'] ?? 'text-embedding-3-small';
        $raw = $this->postJson('https://api.openai.com/v1/embeddings', ['model' => $model, 'input' => $text], ['Authorization: Bearer ' . $this->apiKey]);

        return array_map('floatval', $raw['data'][0]['embedding'] ?? []);
    }
}
