<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

final class DeepSeekClient extends HttpProviderClient
{
    public function name(): string
    {
        return 'deepseek';
    }

    public function chat(array $messages, array $settings): ProviderResult
    {
        $started = microtime(true);
        $model = $settings['model'] ?? $this->model;
        $raw = $this->postJson(
            'https://api.deepseek.com/chat/completions',
            [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $settings['temperature'] ?? 0.2,
                'top_p' => $settings['top_p'] ?? 0.9,
                'max_tokens' => $settings['max_output_tokens'] ?? 1200,
            ],
            ['Authorization: Bearer ' . $this->apiKey]
        );

        $content = (string) ($raw['choices'][0]['message']['content'] ?? '');
        $input = (int) ($raw['usage']['prompt_tokens'] ?? $this->estimateTokens(json_encode($messages)));
        $output = (int) ($raw['usage']['completion_tokens'] ?? $this->estimateTokens($content));

        return new ProviderResult('deepseek', (string) $model, $content, $input, $output, (int) ((microtime(true) - $started) * 1000), 0.0, $raw);
    }

    public function embed(string $text, array $settings): array
    {
        return array_fill(0, 384, 0.0);
    }
}
