<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

final class ProviderIncidentEvidenceExporter
{
    private const REDACTION_LABEL = '[REDACTED]';

    /**
     * @param array<string, mixed> $incident
     * @param list<array<string, mixed>> $providerEvents
     * @param list<array<string, mixed>> $routingDecisions
     * @param list<array<string, mixed>> $auditEvents
     * @return array<string, mixed>
     */
    public function buildPackage(
        array $incident,
        array $providerEvents,
        array $routingDecisions,
        array $auditEvents,
        ?DateTimeImmutable $exportedAt = null
    ): array {
        $exportedAt ??= new DateTimeImmutable('now');

        $package = [
            'package_version' => '1.0',
            'exported_at' => $exportedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'incident' => $this->redactValue($incident),
            'provider_events' => $this->redactValue($providerEvents),
            'routing_decisions' => $this->redactValue($routingDecisions),
            'audit_events' => $this->redactValue($auditEvents),
            'timeline' => $this->timeline($providerEvents, $routingDecisions, $auditEvents),
            'review_questions' => $this->reviewQuestions($incident, $providerEvents, $routingDecisions),
            'redaction_policy' => [
                'emails',
                'bearer_tokens',
                'api_keys',
                'jwt_like_tokens',
                'long_secret_like_values',
                'secret_named_fields',
                'authorization_headers',
            ],
            'integrity' => [
                'hash_algorithm' => 'sha256',
                'provider_event_count' => count($providerEvents),
                'routing_decision_count' => count($routingDecisions),
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

    /**
     * @param list<array<string, mixed>> $providerEvents
     * @param list<array<string, mixed>> $routingDecisions
     * @param list<array<string, mixed>> $auditEvents
     * @return list<array<string, string>>
     */
    private function timeline(array $providerEvents, array $routingDecisions, array $auditEvents): array
    {
        $items = [];

        foreach ($providerEvents as $event) {
            $items[] = $this->timelineItem($event, 'provider_event', $this->stringValue($event, 'event_type'));
        }
        foreach ($routingDecisions as $decision) {
            $items[] = $this->timelineItem($decision, 'routing_decision', $this->stringValue($decision, 'decision'));
        }
        foreach ($auditEvents as $event) {
            $items[] = $this->timelineItem($event, 'audit_event', $this->stringValue($event, 'action'));
        }

        usort($items, static fn (array $left, array $right): int => [$left['occurred_at'], $left['category'], $left['label']]
            <=> [$right['occurred_at'], $right['category'], $right['label']]);

        return $items;
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, string>
     */
    private function timelineItem(array $value, string $category, string $label): array
    {
        return [
            'occurred_at' => $this->stringValue($value, 'occurred_at') ?: $this->stringValue($value, 'created_at'),
            'category' => $category,
            'label' => $this->redactText($label),
            'provider' => $this->redactText($this->stringValue($value, 'provider')),
            'evidence' => $this->redactText($this->stringValue($value, 'evidence') ?: $this->stringValue($value, 'metadata')),
        ];
    }

    /**
     * @param array<string, mixed> $incident
     * @param list<array<string, mixed>> $providerEvents
     * @param list<array<string, mixed>> $routingDecisions
     * @return list<string>
     */
    private function reviewQuestions(array $incident, array $providerEvents, array $routingDecisions): array
    {
        $questions = [
            'Was the incident detected by provider health checks, user reports, cost anomaly monitoring, or safety review?',
            'Was fallback routing consistent with the approved provider order and bot policy?',
            'Did any fallback provider change data residency, retention, safety-filter, or contractual risk?',
            'Were user-facing answers, citations, and refusal behavior materially different during the incident window?',
            'Were provider credentials, API keys, or authorization headers excluded from the evidence package?',
        ];

        if ($this->hasEventType($providerEvents, 'cost_spike')) {
            $questions[] = 'Was the token/cost spike caused by retries, longer context, degraded model behavior, or an unintended route?';
        }
        if ($this->hasEventType($providerEvents, 'safety_filter_change')) {
            $questions[] = 'Was a safety-filter or moderation-policy change approved before routing traffic to the provider?';
        }
        if ($this->stringValue($incident, 'severity') === 'high') {
            $questions[] = 'Was the high-severity incident escalated to the system owner and risk owner within the required SLA?';
        }
        if ($routingDecisions !== []) {
            $questions[] = 'Do routing decisions include the reason, provider order, model, latency, and failure evidence needed for replay?';
        }

        return $questions;
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function hasEventType(array $events, string $eventType): bool
    {
        foreach ($events as $event) {
            if ($this->stringValue($event, 'event_type') === $eventType) {
                return true;
            }
        }

        return false;
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
            '/(api[_-]?key|provider[_-]?key|token|secret|password|credential)[=:]\s?[A-Za-z0-9._~+\-\/]{8,}/i',
            '/authorization:\s?[A-Za-z0-9._~+\-\/\s=]+/i',
            '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/',
            '/\b[A-Za-z0-9_\-]{32,}\b/',
        ];

        return preg_replace($patterns, self::REDACTION_LABEL, $value) ?? $value;
    }

    private function isSecretField(string $fieldName): bool
    {
        return preg_match('/(token|secret|password|api[_-]?key|authorization|credential|provider_key)/i', $fieldName) === 1;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stringValue(array $value, string $key): string
    {
        if (!array_key_exists($key, $value)) {
            return '';
        }

        $raw = $value[$key];
        if (is_scalar($raw) || $raw === null) {
            return trim((string) $raw);
        }

        try {
            return json_encode($this->sortKeys($raw), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '';
        }
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
            throw new RuntimeException('Unable to encode provider incident evidence package.', 0, $exception);
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
