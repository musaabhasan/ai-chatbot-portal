<?php

declare(strict_types=1);

namespace ChatbotPortal\Analytics;

use PDO;

final class UsageRecorder
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function record(array $usage): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO provider_usage (
                provider, model, bot_id, user_id, conversation_id, input_tokens, output_tokens,
                cost_usd, latency_ms, successful, error_code
             ) VALUES (
                :provider, :model, :bot_id, :user_id, :conversation_id, :input_tokens, :output_tokens,
                :cost_usd, :latency_ms, :successful, :error_code
             )"
        );
        $statement->execute([
            'provider' => $usage['provider'],
            'model' => $usage['model'],
            'bot_id' => $usage['bot_id'] ?? null,
            'user_id' => $usage['user_id'] ?? null,
            'conversation_id' => $usage['conversation_id'] ?? null,
            'input_tokens' => $usage['input_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
            'cost_usd' => $usage['cost_usd'] ?? 0,
            'latency_ms' => $usage['latency_ms'] ?? 0,
            'successful' => !empty($usage['successful']) ? 1 : 0,
            'error_code' => $usage['error_code'] ?? null,
        ]);
    }

    public function dashboard(): array
    {
        $rows = $this->db->query(
            "SELECT provider,
                    COUNT(*) AS requests,
                    SUM(input_tokens) AS input_tokens,
                    SUM(output_tokens) AS output_tokens,
                    SUM(cost_usd) AS cost_usd,
                    AVG(latency_ms) AS average_latency_ms,
                    SUM(CASE WHEN successful = 0 THEN 1 ELSE 0 END) AS failures
             FROM provider_usage
             WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
             GROUP BY provider
             ORDER BY cost_usd DESC"
        )->fetchAll();

        return ['providers' => $rows];
    }
}
