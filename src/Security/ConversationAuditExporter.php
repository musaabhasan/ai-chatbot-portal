<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class ConversationAuditExporter
{
    private const REDACTION_LABEL = '[REDACTED]';

    /**
     * @param array<string, mixed> $conversation
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $auditEvents
     * @return array<string, mixed>
     */
    public function buildPackage(
        array $conversation,
        array $messages,
        array $auditEvents,
        ?DateTimeImmutable $exportedAt = null
    ): array {
        $exportedAt ??= new DateTimeImmutable('now');

        $package = [
            'package_version' => '1.0',
            'exported_at' => $exportedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'conversation' => $this->redactValue($conversation),
            'messages' => $this->redactValue($messages),
            'audit_events' => $this->redactValue($auditEvents),
            'redaction_policy' => [
                'emails',
                'bearer_tokens',
                'api_keys',
                'jwt_like_tokens',
                'long_secret_like_values',
                'secret_named_fields',
            ],
            'integrity' => [
                'hash_algorithm' => 'sha256',
                'message_count' => count($messages),
                'audit_event_count' => count($auditEvents),
            ],
        ];

        $package['integrity']['package_sha256'] = hash('sha256', $this->canonicalJson($package));
        return $package;
    }

    /**
     * @param array<string, mixed> $package
     */
    public function verifyPackage(array $package): bool
    {
        $expected = $package['integrity']['package_sha256'] ?? null;
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $copy = $package;
        unset($copy['integrity']['package_sha256']);

        return hash_equals($expected, hash('sha256', $this->canonicalJson($copy)));
    }

    private function redactValue(mixed $value, ?string $fieldName = null): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $redacted[$key] = $this->redactValue($item, is_string($key) ? $key : null);
            }

            return $redacted;
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($fieldName !== null && $this->isSecretField($fieldName) && trim($value) !== '') {
            return self::REDACTION_LABEL;
        }

        return $this->redactText($value);
    }

    private function redactText(string $value): string
    {
        $patterns = [
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '/Bearer\s+[A-Za-z0-9._~+\-\/]+=*/i',
            '/api[_-]?key[=:]\s?[A-Za-z0-9._~+\-\/]{16,}/i',
            '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/',
            '/\b[A-Za-z0-9_\-]{32,}\b/',
        ];

        return preg_replace($patterns, self::REDACTION_LABEL, $value) ?? $value;
    }

    private function isSecretField(string $fieldName): bool
    {
        return preg_match('/(token|secret|password|api[_-]?key|authorization|credential)/i', $fieldName) === 1;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function canonicalJson(array $value): string
    {
        $normalized = $this->sortKeys($value);

        try {
            return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode conversation audit package.', 0, $exception);
        }
    }

    private function sortKeys(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortKeys($item);
        }

        return $value;
    }
}
