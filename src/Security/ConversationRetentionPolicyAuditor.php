<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use DateTimeImmutable;
use Throwable;

final class ConversationRetentionPolicyAuditor
{
    /** @var list<string> */
    private const SENSITIVE_CLASSIFICATIONS = [
        'confidential',
        'restricted',
        'regulated',
        'student',
        'health',
        'financial',
        'personal',
        'credential',
    ];

    /** @var list<string> */
    private const BROAD_ACCESS_ROLES = [
        '*',
        'all',
        'any',
        'everyone',
        'public',
        'global',
        'unrestricted',
    ];

    /** @var list<string> */
    private const REDACTION_READY_STATUSES = [
        'enabled',
        'completed',
        'automated',
        'manual-ready',
        'not_required',
    ];

    /** @var list<string> */
    private const KNOWN_STORE_TYPES = [
        'conversation',
        'prompt',
        'output',
        'prompt_output',
        'rag_source',
        'rag_chunk',
        'embedding',
        'tool_call',
        'audit_log',
        'audit_export',
        'evaluation',
        'telemetry',
        'attachment',
        'provider_payload',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, ?DateTimeImmutable $referenceTime = null): array
    {
        $referenceTime ??= new DateTimeImmutable('now');
        $stores = $payload['stores'] ?? [];
        if (!is_array($stores) || !array_is_list($stores)) {
            $stores = [];
        }

        $findings = [
            ...$this->auditPackage($payload, $stores, $referenceTime),
        ];

        $rows = [];
        foreach ($stores as $index => $store) {
            if (!is_array($store)) {
                $findings[] = $this->finding('high', 'invalid_store_record', 'stores[' . $index . ']', 'Retention store record must be an object.');
                continue;
            }

            $storeId = $this->storeTarget($store, $index);
            $storeFindings = $this->auditStore($storeId, $store, $referenceTime);
            array_push($findings, ...$storeFindings);

            $retentionDays = $this->intValue($store, 'retention_days');
            $maxDays = $this->maxRetentionDays(
                strtolower($this->stringValue($store, 'store_type')),
                strtolower($this->stringValue($store, 'data_classification')),
                $this->boolValue($store, 'contains_personal_data')
            );

            $rows[] = [
                'store_id' => $storeId,
                'store_type' => strtolower($this->stringValue($store, 'store_type')),
                'data_classification' => strtolower($this->stringValue($store, 'data_classification')),
                'retention_days' => $retentionDays,
                'max_recommended_days' => $maxDays,
                'contains_personal_data' => $this->boolValue($store, 'contains_personal_data'),
                'contains_credentials' => $this->boolValue($store, 'contains_credentials'),
                'redaction_status' => strtolower($this->stringValue($store, 'redaction_status')),
                'finding_count' => count($storeFindings),
                'highest_severity' => $this->highestSeverity($storeFindings),
            ];
        }

        $summary = [
            'total_stores' => count($stores),
            'ready_stores' => 0,
            'warning_stores' => 0,
            'blocked_stores' => 0,
            'high_findings' => 0,
            'medium_findings' => 0,
            'low_findings' => 0,
            'personal_data_stores' => 0,
            'credential_stores' => 0,
            'long_retention_stores' => 0,
            'auto_delete_ready_stores' => 0,
            'legal_hold_ready_stores' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['highest_severity'] === 'high') {
                $summary['blocked_stores']++;
            } elseif ($row['highest_severity'] === 'medium') {
                $summary['warning_stores']++;
            } else {
                $summary['ready_stores']++;
            }

            if ($row['contains_personal_data']) {
                $summary['personal_data_stores']++;
            }
            if ($row['contains_credentials']) {
                $summary['credential_stores']++;
            }
            if ($row['retention_days'] !== null && $row['retention_days'] > $row['max_recommended_days']) {
                $summary['long_retention_stores']++;
            }
        }

        foreach ($stores as $store) {
            if (!is_array($store)) {
                continue;
            }
            if ($this->boolValue($store, 'auto_delete_enabled')) {
                $summary['auto_delete_ready_stores']++;
            }
            if ($this->boolValue($store, 'legal_hold_supported')) {
                $summary['legal_hold_ready_stores']++;
            }
        }

        foreach ($findings as $finding) {
            $summary[$finding['severity'] . '_findings']++;
        }

        $score = $this->score($findings);

        return [
            'policy_id' => $this->stringValue($payload, 'policy_id'),
            'reference_time' => $referenceTime->format(DATE_ATOM),
            'retention_policy_score' => $score,
            'passed' => $score >= 90 && $summary['high_findings'] === 0,
            'summary' => $summary,
            'stores' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $stores
     * @return list<array<string, string>>
     */
    private function auditPackage(array $payload, array $stores, DateTimeImmutable $referenceTime): array
    {
        $findings = [];
        foreach (['policy_id', 'policy_version', 'owner', 'reviewed_at', 'evidence_reference'] as $field) {
            if ($this->stringValue($payload, $field) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'package', "Retention policy package is missing {$field}.");
            }
        }

        if ($stores === []) {
            $findings[] = $this->finding('high', 'stores_missing', 'package', 'Retention policy package has no store records.');
        }

        $reviewedAt = $this->daysSince($this->stringValue($payload, 'reviewed_at'), $referenceTime);
        if ($reviewedAt === null) {
            $findings[] = $this->finding('medium', 'package_review_date_invalid', 'package', 'Retention policy package reviewed_at is missing or invalid.');
        } elseif ($reviewedAt > 180) {
            $findings[] = $this->finding('medium', 'package_review_stale', 'package', 'Retention policy package review is older than 180 days.');
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $store
     * @return list<array<string, string>>
     */
    private function auditStore(string $storeId, array $store, DateTimeImmutable $referenceTime): array
    {
        $findings = [];
        $storeType = strtolower($this->stringValue($store, 'store_type'));
        $classification = strtolower($this->stringValue($store, 'data_classification'));
        $personalData = $this->boolValue($store, 'contains_personal_data');
        $credentialData = $this->boolValue($store, 'contains_credentials');
        $sensitive = $personalData || $credentialData || in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true);

        foreach (['store_id', 'store_type', 'data_classification', 'owner', 'purpose', 'access_role', 'evidence_reference', 'last_reviewed'] as $field) {
            if ($this->stringValue($store, $field) === '') {
                $findings[] = $this->finding('high', 'store_field_missing', $storeId, "Retention store record is missing {$field}.");
            }
        }

        if ($storeType !== '' && !in_array($storeType, self::KNOWN_STORE_TYPES, true)) {
            $findings[] = $this->finding('medium', 'store_type_unknown', $storeId, 'Retention store uses an unknown store_type.');
        }

        $retentionDays = $this->intValue($store, 'retention_days');
        if ($retentionDays === null || $retentionDays <= 0) {
            $findings[] = $this->finding('high', 'retention_days_invalid', $storeId, 'Retention store must define a positive retention_days value.');
        } else {
            $maxDays = $this->maxRetentionDays($storeType, $classification, $personalData);
            if ($retentionDays > $maxDays) {
                $findings[] = $this->finding($sensitive ? 'high' : 'medium', 'retention_exceeds_recommendation', $storeId, "Retention of {$retentionDays} days exceeds the recommended {$maxDays}-day limit for this store.");
            }
            if ($retentionDays > 365 && $this->stringValue($store, 'retention_justification') === '') {
                $findings[] = $this->finding('medium', 'retention_justification_missing', $storeId, 'Retention above 365 days requires a recorded justification.');
            }
        }

        if ($credentialData) {
            $findings[] = $this->finding('high', 'credential_data_in_retention_store', $storeId, 'Retention store is marked as containing credentials; rotate, redact, and prevent credential persistence.');
        }

        if ($sensitive && !$this->boolValue($store, 'encryption_at_rest')) {
            $findings[] = $this->finding('high', 'encryption_at_rest_missing', $storeId, 'Sensitive retention store lacks encryption-at-rest confirmation.');
        }

        if ($personalData && !$this->boolValue($store, 'deletion_supported')) {
            $findings[] = $this->finding('high', 'deletion_not_supported', $storeId, 'Personal-data store lacks deletion or anonymization support.');
        } elseif (!$this->boolValue($store, 'deletion_supported')) {
            $findings[] = $this->finding('medium', 'deletion_not_supported', $storeId, 'Retention store lacks deletion or disposal support.');
        }

        if (($personalData || $this->requiresLegalHold($storeType)) && !$this->boolValue($store, 'legal_hold_supported')) {
            $findings[] = $this->finding('high', 'legal_hold_missing', $storeId, 'Retention store lacks legal-hold support for regulated evidence handling.');
        }

        if (!$this->boolValue($store, 'auto_delete_enabled')) {
            $findings[] = $this->finding('medium', 'auto_delete_missing', $storeId, 'Retention store lacks automatic deletion or review queue enforcement.');
        }

        $redactionRequired = $this->boolValue($store, 'redaction_required');
        $redactionStatus = strtolower($this->stringValue($store, 'redaction_status'));
        if (($redactionRequired || $personalData) && !in_array($redactionStatus, ['enabled', 'completed', 'automated', 'manual-ready'], true)) {
            $findings[] = $this->finding('high', 'redaction_not_ready', $storeId, 'Personal or redaction-required store is not marked with a ready redaction status.');
        } elseif ($redactionStatus !== '' && !in_array($redactionStatus, self::REDACTION_READY_STATUSES, true)) {
            $findings[] = $this->finding('medium', 'redaction_status_unknown', $storeId, 'Retention store uses an unknown redaction_status.');
        }

        if ($this->hasBroadAccess($this->stringValue($store, 'access_role'))) {
            $findings[] = $this->finding($sensitive ? 'high' : 'medium', 'access_role_too_broad', $storeId, 'Retention store access role is too broad for least-privilege review.');
        }

        if ($this->boolValue($store, 'provider_training_allowed') && ($personalData || $storeType === 'prompt' || $storeType === 'output' || $storeType === 'prompt_output')) {
            $findings[] = $this->finding('high', 'provider_training_allowed', $storeId, 'Prompt, output, or personal-data retention store allows provider training use.');
        }

        $daysSinceReview = $this->daysSince($this->stringValue($store, 'last_reviewed'), $referenceTime);
        if ($daysSinceReview === null) {
            $findings[] = $this->finding('medium', 'store_review_date_invalid', $storeId, 'Retention store lacks valid last_reviewed evidence.');
        } elseif ($sensitive && $daysSinceReview > 90) {
            $findings[] = $this->finding('high', 'sensitive_store_review_stale', $storeId, 'Sensitive retention store review is older than 90 days.');
        } elseif ($daysSinceReview > 180) {
            $findings[] = $this->finding('medium', 'store_review_stale', $storeId, 'Retention store review is older than 180 days.');
        }

        return $findings;
    }

    private function maxRetentionDays(string $storeType, string $classification, bool $containsPersonalData): int
    {
        $base = match ($storeType) {
            'provider_payload' => 30,
            'prompt', 'output', 'prompt_output', 'conversation', 'attachment' => 180,
            'tool_call', 'audit_export', 'evaluation', 'telemetry' => 365,
            'rag_chunk', 'embedding' => 365,
            'rag_source', 'audit_log' => 730,
            default => 365,
        };

        if ($containsPersonalData) {
            $base = min($base, 180);
        }

        if (in_array($classification, ['restricted', 'regulated', 'credential'], true)) {
            $base = min($base, 90);
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $store
     */
    private function storeTarget(array $store, int $index): string
    {
        return $this->stringValue($store, 'store_id') ?: 'store-' . ($index + 1);
    }

    private function requiresLegalHold(string $storeType): bool
    {
        return in_array($storeType, ['conversation', 'prompt_output', 'tool_call', 'audit_log', 'audit_export'], true);
    }

    private function hasBroadAccess(string $accessRole): bool
    {
        return in_array(strtolower(trim($accessRole)), self::BROAD_ACCESS_ROLES, true);
    }

    /**
     * @param array<string, mixed> $store
     */
    private function stringValue(array $store, string $field): string
    {
        $value = $store[$field] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $store
     */
    private function intValue(array $store, string $field): ?int
    {
        $value = $store[$field] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $store
     */
    private function boolValue(array $store, string $field): bool
    {
        $value = $store[$field] ?? false;
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'enabled'], true);
        }
        if (is_int($value)) {
            return $value === 1;
        }

        return false;
    }

    private function daysSince(string $date, DateTimeImmutable $referenceTime): ?int
    {
        if ($date === '') {
            return null;
        }

        try {
            $parsed = new DateTimeImmutable($date);
        } catch (Throwable) {
            return null;
        }

        return max(0, (int) $parsed->diff($referenceTime)->format('%a'));
    }

    /**
     * @param list<array<string, string>> $findings
     */
    private function highestSeverity(array $findings): string
    {
        $highest = 'none';
        foreach ($findings as $finding) {
            if ($finding['severity'] === 'high') {
                return 'high';
            }
            if ($finding['severity'] === 'medium') {
                $highest = 'medium';
            } elseif ($finding['severity'] === 'low' && $highest === 'none') {
                $highest = 'low';
            }
        }

        return $highest;
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
                'low' => 3,
                default => 0,
            };
        }

        return max(0, $score);
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
