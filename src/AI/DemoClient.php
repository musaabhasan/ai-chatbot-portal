<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

final class DemoClient implements ProviderClient
{
    public function name(): string
    {
        return 'demo';
    }

    public function chat(array $messages, array $settings): ProviderResult
    {
        $started = microtime(true);
        $last = end($messages);
        $question = is_array($last) ? (string) ($last['content'] ?? '') : '';
        $content = "Demo response: the portal is configured correctly. Connect OpenAI, Gemini, or DeepSeek credentials to answer live requests. User message received: " . $question;

        return new ProviderResult('demo', 'local-demo', $content, 0, max(1, (int) ceil(strlen($content) / 4)), (int) ((microtime(true) - $started) * 1000), 0.0);
    }

    public function embed(string $text, array $settings): array
    {
        $hash = hash('sha256', $text, true);
        $vector = [];
        for ($i = 0; $i < 384; $i++) {
            $vector[] = (ord($hash[$i % strlen($hash)]) - 128) / 128;
        }

        return $vector;
    }
}
