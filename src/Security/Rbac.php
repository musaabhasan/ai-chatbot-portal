<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

final class Rbac
{
    private const PERMISSIONS = [
        'super_admin' => ['*'],
        'department_admin' => [
            'bot.manage',
            'prompt.manage',
            'knowledge.manage',
            'branding.manage',
            'analytics.view',
        ],
        'reviewer' => [
            'conversation.review',
            'analytics.view',
        ],
        'end_user' => [
            'chat.use',
            'conversation.own.view',
        ],
    ];

    public static function allows(string $role, string $permission): bool
    {
        $permissions = self::PERMISSIONS[$role] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
