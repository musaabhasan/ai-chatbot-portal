<?php

declare(strict_types=1);

namespace ChatbotPortal\Http;

final class SecurityHeaders
{
    public static function apply(): void
    {
        foreach (self::headers() as $header) {
            header($header);
        }
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'X-Content-Type-Options: nosniff',
            'X-Frame-Options: DENY',
            'Referrer-Policy: strict-origin-when-cross-origin',
            'Permissions-Policy: camera=(), microphone=(), geolocation=()',
            "Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://unpkg.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self';",
        ];
    }
}
