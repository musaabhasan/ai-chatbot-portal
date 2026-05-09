<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class AdminActivityEvidenceAuditor
{
    /** @var list<string> */
    private const HIGH_IMPACT_EVENTS = [
        'provider_credentials_updated',
        'provider_enabled',
        'provider_disabled',
        'rbac_role_changed',
        'admin_mfa_reset',
        'mfa_disabled',
        'prompt_approved',
        'prompt_rollback',
        'rag_source_published',
        'rag_source_restricted',
        'cost_limit_changed',
        'bot_decommissioned',
        'audit_retention_changed',
    ];

    /** @var list<string> */
    private const CONFIG_EVENTS = [
        'provider_credentials_updated',
        'provider_enabled',
        'provider_disabled',
        'prompt_approved',
        'prompt_rollback',
        'rag_source_published',
        'rag_source_restricted',
        'branding_updated',
        'cost_limit_changed',
        'audit_retention_changed',
        'bot_decommissioned',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload): array
    {
        $events = $payload['events'] ?? [];
        if (!is_array($events) || !array_is_list($events)) {
            $events = [];
        }

        $findings = [
            ...$this->auditPackage($payload, $events),
            ...$this->auditEvents($events),
        ];

        $summary = [
            'total_events' => count($events),
            'high_findings' => 0,
            'medium_findings' => 0,
            'low_findings' => 0,
            'high_impact_events' => 0,
            'events_with_evidence_hash' => 0,
        ];

        $rows = [];
        foreach ($events as $index => $event) {
            if (!is_array($event)) {
                continue;
            }

            $eventType = strtolower($this->stringValue($event, 'event_type'));
            if (in_array($eventType, self::HIGH_IMPACT_EVENTS, true)) {
                $summary['high_impact_events']++;
            }
            if ($this->stringValue($event, 'evidence_hash') !== '') {
                $summary['events_with_evidence_hash']++;
            }

            $eventFindings = array_values(array_filter(
                $findings,
                fn (array $finding): bool => ($finding['target'] ?? '') === $this->eventTarget($event, $index)
            ));

            $rows[] = [
                'event_id' => $this->eventTarget($event, $index),
                'event_type' => $eventType,
                'actor' => $this->stringValue($event, 'actor'),
                'target' => $this->stringValue($event, 'target'),
                'finding_count' => count($eventFindings),
                'highest_severity' => $this->highestSeverity($eventFindings),
            ];
        }

        foreach ($findings as $finding) {
            $summary[$finding['severity'] . '_findings']++;
        }

        $score = $this->score($findings);

        return [
            'export_id' => $this->stringValue($payload, 'export_id'),
            'evidence_readiness_score' => $score,
            'passed' => $score >= 90 && $summary['high_findings'] === 0,
            'summary' => $summary,
            'events' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $events
     * @return list<array<string, string>>
     */
    private function auditPackage(array $payload, array $events): array
    {
        $findings = [];

        foreach (['export_id', 'period_start', 'period_end', 'generated_at', 'generated_by'] as $field) {
            if ($this->stringValue($payload, $field) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'package', "Admin activity export is missing {$field}.");
            }
        }

        if ($events === []) {
            $findings[] = $this->finding('high', 'events_missing', 'package', 'Admin activity export has no events.');
        }

        if ($this->stringValue($payload, 'package_sha256') === '') {
            $findings[] = $this->finding('medium', 'package_hash_missing', 'package', 'Admin activity export lacks package-level SHA-256 evidence.');
        }

        $start = $this->dateValue($payload, 'period_start');
        $end = $this->dateValue($payload, 'period_end');
        if ($start !== null && $end !== null && $end < $start) {
            $findings[] = $this->finding('high', 'period_invalid', 'package', 'Admin activity export period_end is before period_start.');
        }

        return $findings;
    }

    /**
     * @param list<mixed> $events
     * @return list<array<string, string>>
     */
    private function auditEvents(array $events): array
    {
        $findings = [];
        $seen = [];

        foreach ($events as $index => $event) {
            if (!is_array($event)) {
                $findings[] = $this->finding('high', 'invalid_event', 'events[' . $index . ']', 'Admin activity event must be an object.');
                continue;
            }

            $target = $this->eventTarget($event, $index);
            $eventType = strtolower($this->stringValue($event, 'event_type'));
            $riskLevel = strtolower($this->stringValue($event, 'risk_level'));
            $highImpact = in_array($eventType, self::HIGH_IMPACT_EVENTS, true)
                || in_array($riskLevel, ['high', 'critical'], true);

            if (in_array($target, $seen, true)) {
                $findings[] = $this->finding('medium', 'duplicate_event_id', $target, 'Admin activity event ID appears more than once.');
            }
            $seen[] = $target;

            foreach (['event_id', 'event_type', 'actor', 'actor_role', 'target', 'occurred_at'] as $field) {
                if ($this->stringValue($event, $field) === '') {
                    $findings[] = $this->finding('high', 'event_field_missing', $target, "Admin activity event is missing {$field}.");
                }
            }

            if ($this->dateValue($event, 'occurred_at') === null) {
                $findings[] = $this->finding('medium', 'event_timestamp_invalid', $target, 'Admin activity event timestamp is missing or invalid.');
            }

            if ($this->stringValue($event, 'evidence_hash') === '') {
                $findings[] = $this->finding('medium', 'evidence_hash_missing', $target, 'Admin activity event lacks evidence hash.');
            }

            if ($highImpact && $this->stringValue($event, 'approval_reference') === '') {
                $findings[] = $this->finding('high', 'approval_reference_missing', $target, 'High-impact admin activity lacks approval reference.');
            }

            if ($highImpact && $this->stringValue($event, 'reviewer') === '') {
                $findings[] = $this->finding('medium', 'reviewer_missing', $target, 'High-impact admin activity lacks reviewer evidence.');
            }

            if (in_array($eventType, self::CONFIG_EVENTS, true)) {
                if ($this->stringValue($event, 'before_hash') === '' || $this->stringValue($event, 'after_hash') === '') {
                    $findings[] = $this->finding('medium', 'config_delta_hash_missing', $target, 'Configuration event lacks before and after hash evidence.');
                }
            }

            if ($this->truthy($event, 'contains_sensitive_data') && strtolower($this->stringValue($event, 'redaction_status')) !== 'completed') {
                $findings[] = $this->finding('high', 'redaction_missing', $target, 'Sensitive admin activity evidence has not completed redaction.');
            }

            if ($this->stringValue($event, 'reason') === '') {
                $findings[] = $this->finding('low', 'reason_missing', $target, 'Admin activity event lacks a concise reason.');
            }
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function eventTarget(array $event, int $index): string
    {
        return $this->stringValue($event, 'event_id') ?: 'event-' . ($index + 1);
    }

    /**
     * @param list<array<string, string>> $findings
     */
    private function highestSeverity(array $findings): string
    {
        if ($findings === []) {
            return 'none';
        }

        $order = ['low' => 1, 'medium' => 2, 'high' => 3];
        usort($findings, static fn (array $left, array $right): int => ($order[$right['severity']] ?? 0) <=> ($order[$left['severity']] ?? 0));

        return $findings[0]['severity'];
    }

    /**
     * @param list<array<string, string>> $findings
     */
    private function score(array $findings): int
    {
        $score = 100;
        foreach ($findings as $finding) {
            $score -= match ($finding['severity']) {
                'high' => 20,
                'medium' => 8,
                default => 2,
            };
        }

        return max(0, $score);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stringValue(array $value, string $key): string
    {
        $raw = $value[$key] ?? '';
        if (is_scalar($raw) || $raw === null) {
            return trim((string) $raw);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $value
     */
    private function dateValue(array $value, string $key): ?DateTimeImmutable
    {
        $raw = $this->stringValue($value, $key);
        if ($raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $value
     */
    private function truthy(array $value, string $key): bool
    {
        return in_array(strtolower($this->stringValue($value, $key)), ['1', 'true', 'yes', 'y', 'complete', 'completed'], true);
    }

    /**
     * @return array<string, string>
     */
    private function finding(string $severity, string $type, string $target, string $message): array
    {
        return [
            'severity' => $severity,
            'type' => $type,
            'target' => $target,
            'message' => $message,
        ];
    }
}
