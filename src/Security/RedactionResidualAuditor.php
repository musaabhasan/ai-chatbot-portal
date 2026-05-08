<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

final class RedactionResidualAuditor
{
    private const REDACTION_LABEL = '[REDACTED]';

    /**
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    public function audit(array $package): array
    {
        $findings = [];
        $this->scanValue($package, '$', $findings);
        $summary = $this->summary($findings);

        return [
            'passed' => $summary['high'] === 0,
            'redaction_score' => $this->score($summary),
            'summary' => $summary,
            'findings' => $findings,
        ];
    }

    /**
     * @param list<array<string, string>> $findings
     */
    private function scanValue(mixed $value, string $path, array &$findings): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $childPath = $path . '.' . (is_string($key) ? $key : (string) $key);
                if (is_string($key) && $this->isSecretField($key) && is_string($item) && $this->hasUnredactedValue($item)) {
                    $findings[] = $this->finding(
                        'high',
                        'secret_named_field_not_redacted',
                        $childPath,
                        'Secret-named field still contains an unredacted value.',
                        $this->sample($item)
                    );
                }
                $this->scanValue($item, $childPath, $findings);
            }
            return;
        }

        if (!is_string($value) || trim($value) === '' || trim($value) === self::REDACTION_LABEL) {
            return;
        }

        foreach ($this->textFindings($value, $path) as $finding) {
            $findings[] = $finding;
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function textFindings(string $value, string $path): array
    {
        $findings = [];
        $patterns = [
            'bearer_token' => ['high', '/Bearer\s+[A-Za-z0-9._~+\-\/]+=*/i'],
            'api_key_value' => ['high', '/api[_-]?key[=:]\s?[A-Za-z0-9._~+\-\/]{16,}/i'],
            'jwt_like_token' => ['high', '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/'],
            'email_address' => ['medium', '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i'],
            'direct_identifier' => ['medium', '/\b(student|employee|passport|national)[ _-]?(id|number)(?:\s*(?:is|=|:|#))?\s?[A-Za-z0-9\-]{4,}\b/i'],
        ];

        foreach ($patterns as $type => [$severity, $pattern]) {
            if (preg_match($pattern, $value, $matches) === 1) {
                $findings[] = $this->finding(
                    $severity,
                    $type,
                    $path,
                    $this->messageFor($type),
                    $this->sample($matches[0])
                );
            }
        }

        if (!$this->isHashPath($path) && preg_match('/\b[A-Za-z0-9_\-]{40,}\b/', $value, $matches) === 1) {
            $findings[] = $this->finding(
                'high',
                'long_secret_like_value',
                $path,
                'A long secret-like value remains after redaction.',
                $this->sample($matches[0])
            );
        }

        return $findings;
    }

    private function isSecretField(string $fieldName): bool
    {
        return preg_match('/(token|secret|password|api[_-]?key|authorization|credential)/i', $fieldName) === 1;
    }

    private function hasUnredactedValue(string $value): bool
    {
        $trimmed = trim($value);
        return $trimmed !== '' && $trimmed !== self::REDACTION_LABEL;
    }

    private function isHashPath(string $path): bool
    {
        return preg_match('/(hash|sha256|digest|checksum|integrity)/i', $path) === 1;
    }

    private function sample(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) <= 12) {
            return str_repeat('*', strlen($trimmed));
        }

        return substr($trimmed, 0, 4) . '...' . substr($trimmed, -4);
    }

    private function messageFor(string $type): string
    {
        return match ($type) {
            'bearer_token' => 'Bearer token remains after redaction.',
            'api_key_value' => 'API key value remains after redaction.',
            'jwt_like_token' => 'JWT-like token remains after redaction.',
            'email_address' => 'Email address remains after redaction.',
            'direct_identifier' => 'Direct identifier remains after redaction.',
            default => 'Residual sensitive value remains after redaction.',
        };
    }

    /**
     * @param list<array<string, string>> $findings
     * @return array{high: int, medium: int, low: int, total: int}
     */
    private function summary(array $findings): array
    {
        $summary = ['high' => 0, 'medium' => 0, 'low' => 0, 'total' => count($findings)];
        foreach ($findings as $finding) {
            $severity = $finding['severity'];
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        return $summary;
    }

    /**
     * @param array{high: int, medium: int, low: int, total: int} $summary
     */
    private function score(array $summary): int
    {
        return max(0, 100 - ($summary['high'] * 25) - ($summary['medium'] * 10) - ($summary['low'] * 3));
    }

    /**
     * @return array<string, string>
     */
    private function finding(string $severity, string $type, string $path, string $message, string $sample): array
    {
        return [
            'severity' => $severity,
            'type' => $type,
            'path' => $path,
            'message' => $message,
            'sample' => $sample,
        ];
    }
}
