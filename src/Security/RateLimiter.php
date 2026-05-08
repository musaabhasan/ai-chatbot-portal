<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use PDO;

final class RateLimiter
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function allow(string $key, int $limitPerMinute): bool
    {
        $window = gmdate('Y-m-d H:i:00');
        $statement = $this->db->prepare(
            "INSERT INTO rate_limits (limit_key, window_start, request_count)
             VALUES (:limit_key, :window_start, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1"
        );
        $statement->execute(['limit_key' => $key, 'window_start' => $window]);

        $read = $this->db->prepare('SELECT request_count FROM rate_limits WHERE limit_key = :limit_key AND window_start = :window_start');
        $read->execute(['limit_key' => $key, 'window_start' => $window]);

        return (int) $read->fetchColumn() <= $limitPerMinute;
    }
}
