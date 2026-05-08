<?php

declare(strict_types=1);

namespace ChatbotPortal\Admin;

use PDO;
use RuntimeException;

final class PromptRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function activePromptForBot(int $botId): string
    {
        $statement = $this->db->prepare(
            "SELECT system_prompt
             FROM prompt_templates
             WHERE bot_id = :bot_id AND status = 'approved'
             ORDER BY version DESC
             LIMIT 1"
        );
        $statement->execute(['bot_id' => $botId]);
        $prompt = $statement->fetchColumn();

        if (!is_string($prompt) || $prompt === '') {
            throw new RuntimeException('No approved prompt is available for this bot.');
        }

        return $prompt;
    }
}
