<?php

declare(strict_types=1);

namespace ChatbotPortal\Infrastructure;

use ChatbotPortal\Support\Env;
use PDO;

final class Connection
{
    public static function make(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::int('DB_PORT', 3306),
            Env::get('DB_DATABASE', 'ai_chatbot_portal')
        );

        return new PDO(
            $dsn,
            Env::get('DB_USERNAME', 'chatbot'),
            Env::get('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
