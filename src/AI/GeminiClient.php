<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

final class GeminiClient extends HttpProviderClient
{
    public function name(): string
    {
        return 'gemini';
    }

    public function chat(array $messages, array $settings): ProviderResult
    {
        $started = microtime(true);
        $model = $settings['model'] ?? $this->model;
        $contents = array_map(static fn (array $message): array => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $message['content']]],
        ], $messages);

        $raw = $this->postJson(
            sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode((string) $model), rawurlencode($this->apiKey)),
            [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $settings['temperature'] ?? 0.2,
                    'topP' => $settings['top_p'] ?? 0.9,
                    'maxOutputTokens' => $settings['max_output_tokens'] ?? 1200,
                ],
            ]
        );

        $content = (string) ($raw['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $input = $this->estimateTokens(json_encode($messages));
        $output = $this->estimateTokens($content);

        return new ProviderResult('gemini', (string) $model, $content, $input, $output, (int) ((microtime(true) - $started) * 1000), 0.0, $raw);
    }

    public function embed(string $text, array $settings): array
    {
        $model = $settings['embedding_model'] ?? 'text-embedding-004';
        $raw = $this->postJson(
            sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:embedContent?key=%s', rawurlencode((string) $model), rawurlencode($this->apiKey)),
            ['content' => ['parts' => [['text' => $text]]]]
        );

        return array_map('floatval', $raw['embedding']['values'] ?? []);
    }
}
