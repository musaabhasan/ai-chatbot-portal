<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

final class PromptLogRedactor
{
    /**
     * @var array<string, string>
     */
    private const PATTERNS = [
        'emails' => '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
        'bearer_tokens' => '/Bearer\s+[A-Za-z0-9._~+\-\/]+=*/i',
        'api_keys' => '/api[_-]?key[=:]\s?[A-Za-z0-9._~+\-\/]{16,}/i',
        'jwt_like_tokens' => '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/',
        'direct_identifiers' => '/\b(student|employee|passport|national)[ _-]?(id|number)(?:\s*(?:is|=|:|#))?\s?[A-Za-z0-9\-]{4,}\b/i',
        'phone_numbers' => '/(?<!\d)(?:\+?\d[\d .()\-]{7,}\d)(?!\d)/',
        'long_secret_like_values' => '/\b[A-Za-z0-9_\-]{40,}\b/',
    ];

    /**
     * @return array<string, mixed>
     */
    public function preview(string $text): array
    {
        $counts = array_fill_keys(array_keys(self::PATTERNS), 0);
        $redacted = $text;

        foreach (self::PATTERNS as $type => $pattern) {
            $redacted = preg_replace_callback(
                $pattern,
                static function (array $matches) use (&$counts, $type): string {
                    $counts[$type]++;
                    return '[' . strtoupper($type) . '_REDACTED]';
                },
                $redacted
            ) ?? $redacted;
        }

        $total = array_sum($counts);

        return [
            'redacted_text' => $redacted,
            'original_length' => strlen($text),
            'redacted_length' => strlen($redacted),
            'redaction_count' => $total,
            'redaction_counts' => $counts,
            'changed' => $total > 0,
        ];
    }

    public function redact(string $text): string
    {
        return (string) $this->preview($text)['redacted_text'];
    }
}
