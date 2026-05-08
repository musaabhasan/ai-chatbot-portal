<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use PDO;

final class AuditLogger
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function record(?int $actorId, string $action, string $entityType, ?string $entityId, ?array $before, ?array $after): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, ip_address, user_agent, before_json, after_json)
             VALUES (:actor_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :before_json, :after_json)"
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_SLASHES),
            'after_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_SLASHES),
        ]);
    }
}
