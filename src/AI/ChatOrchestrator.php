<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

use ChatbotPortal\Analytics\UsageRecorder;
use ChatbotPortal\Rag\Retriever;
use ChatbotPortal\Security\PromptFirewall;
use ChatbotPortal\Support\Env;
use ChatbotPortal\Support\Uuid;
use PDO;

final class ChatOrchestrator
{
    public function __construct(
        private readonly PDO $db,
        private readonly ProviderRouter $providers,
        private readonly Retriever $retriever,
        private readonly UsageRecorder $usageRecorder,
        private readonly IntentClassifier $intentClassifier,
        private readonly PromptFirewall $promptFirewall
    ) {
    }

    public function answer(string $botSlug, ?string $conversationId, string $message, ?string $requestedProvider = null): array
    {
        $bot = $this->loadBot($botSlug);
        $conversationId ??= Uuid::v4();
        $intent = $this->intentClassifier->classify($message);
        $firewall = $this->promptFirewall->inspect($message);

        if (!$firewall->allowed) {
            return [
                'conversation_id' => $conversationId,
                'provider' => 'policy-firewall',
                'model' => 'deterministic-guardrail',
                'answer' => $firewall->message,
                'citations' => [],
                'usage' => [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'latency_ms' => 0,
                    'cost_usd' => 0,
                ],
                'intelligence' => [
                    'intent' => $intent->toArray(),
                    'firewall' => $firewall->toArray(),
                    'route' => ['policy-firewall'],
                ],
            ];
        }

        $context = $bot['rag_enabled'] && $intent->ragRequired ? $this->retriever->retrieve((int) $bot['id'], $message) : [];
        $prompt = $this->systemPrompt($bot, $context);
        $providerOrder = $requestedProvider !== null ? [$requestedProvider] : json_decode((string) $bot['fallback_providers'], true);
        $providerOrder = is_array($providerOrder) ? $providerOrder : [$bot['default_provider']];
        if ($requestedProvider === null && $intent->recommendedProvider !== null) {
            array_unshift($providerOrder, $intent->recommendedProvider);
            $providerOrder = array_values(array_unique($providerOrder));
        }
        if (Env::get('APP_ENV', 'local') !== 'production') {
            $providerOrder[] = 'demo';
        }

        $messages = [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $message],
        ];

        $result = $this->providers->chat($providerOrder, $messages, [
            'temperature' => min((float) $bot['temperature'], $intent->temperature),
            'top_p' => (float) $bot['top_p'],
            'max_output_tokens' => min((int) $bot['max_output_tokens'], $intent->maxOutputTokens),
        ]);

        $this->persistConversation($conversationId, (int) $bot['id'], $message, $result, $context);
        $this->usageRecorder->record([
            'provider' => $result->provider,
            'model' => $result->model,
            'bot_id' => (int) $bot['id'],
            'conversation_id' => $conversationId,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cost_usd' => $result->costUsd,
            'latency_ms' => $result->latencyMs,
            'successful' => true,
        ]);

        return [
            'conversation_id' => $conversationId,
            'provider' => $result->provider,
            'model' => $result->model,
            'answer' => $result->content,
            'citations' => array_map(static fn (array $item): array => [
                'document' => $item['document_title'],
                'chunk' => $item['chunk_index'],
                'score' => round((float) $item['score'], 4),
            ], $context),
            'usage' => [
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'latency_ms' => $result->latencyMs,
                'cost_usd' => $result->costUsd,
            ],
            'intelligence' => [
                'intent' => $intent->toArray(),
                'firewall' => $firewall->toArray(),
                'route' => $providerOrder,
            ],
        ];
    }

    private function loadBot(string $slug): array
    {
        $statement = $this->db->prepare('SELECT * FROM bot_instances WHERE slug = :slug AND active = 1 LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $bot = $statement->fetch();
        if (!$bot) {
            throw new \RuntimeException('Bot instance was not found.');
        }

        return $bot;
    }

    private function systemPrompt(array $bot, array $context): string
    {
        $contextText = implode("\n\n", array_map(static fn (array $item): string => sprintf(
            '[%s chunk %d score %.3f] %s',
            $item['document_title'],
            $item['chunk_index'],
            $item['score'],
            $item['content']
        ), $context));

        return trim((string) $bot['persona'] . "\n\nUse the supplied context when relevant. Cite sources when context is used.\n\nContext:\n" . $contextText);
    }

    private function persistConversation(string $conversationId, int $botId, string $message, ProviderResult $result, array $context): void
    {
        $this->db->prepare(
            "INSERT INTO conversations (id, bot_id, provider, model, total_input_tokens, total_output_tokens, total_cost_usd)
             VALUES (:id, :bot_id, :provider, :model, :input_tokens, :output_tokens, :cost)
             ON DUPLICATE KEY UPDATE provider = VALUES(provider), model = VALUES(model), updated_at = UTC_TIMESTAMP(),
                total_input_tokens = total_input_tokens + VALUES(total_input_tokens),
                total_output_tokens = total_output_tokens + VALUES(total_output_tokens),
                total_cost_usd = total_cost_usd + VALUES(total_cost_usd)"
        )->execute([
            'id' => $conversationId,
            'bot_id' => $botId,
            'provider' => $result->provider,
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cost' => $result->costUsd,
        ]);

        $insert = $this->db->prepare(
            "INSERT INTO messages (conversation_id, role, content, provider, model, input_tokens, output_tokens, latency_ms, citations)
             VALUES (:conversation_id, :role, :content, :provider, :model, :input_tokens, :output_tokens, :latency_ms, :citations)"
        );
        $insert->execute([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $message,
            'provider' => null,
            'model' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'latency_ms' => null,
            'citations' => null,
        ]);
        $insert->execute([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $result->content,
            'provider' => $result->provider,
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'latency_ms' => $result->latencyMs,
            'citations' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
