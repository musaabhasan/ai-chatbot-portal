<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class KnowledgeBaseSourceLicenseAuditor
{
    /** @var list<string> */
    private const KNOWN_SOURCE_TYPES = [
        'policy',
        'procedure',
        'handbook',
        'faq',
        'research',
        'webpage',
        'dataset',
        'support_article',
        'training_material',
        'contract',
        'user_upload',
    ];

    /** @var list<string> */
    private const LOW_RISK_LICENSES = [
        'public-domain',
        'cc0',
        'cc-by',
        'cc-by-sa',
        'government-open-data',
        'open-access',
        'institutional-owned',
        'licensed-internal',
        'permission-granted',
        'proprietary-approved',
    ];

    /** @var list<string> */
    private const HIGH_RISK_LICENSES = [
        'unknown',
        'unclear',
        'no-license',
        'all-rights-reserved',
        'restricted',
        'web-scrape-unverified',
        'user-uploaded-unverified',
    ];

    /** @var list<string> */
    private const SENSITIVE_CLASSIFICATIONS = [
        'confidential',
        'restricted',
        'regulated',
        'student',
        'personal',
        'health',
        'financial',
    ];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Knowledge-base source license file was not found.');
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Knowledge-base source license file must be valid JSON.');
        }

        return $this->audit($payload, $asOf ?? new DateTimeImmutable('today'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, DateTimeImmutable $asOf): array
    {
        $sources = is_array($payload['sources'] ?? null) ? $payload['sources'] : [];
        $packageFindings = $this->auditPackage($payload, $sources, $asOf);
        $findings = [...$packageFindings];
        $rows = [];
        $summary = [
            'sources_reviewed' => count($sources),
            'ready_sources' => 0,
            'warning_sources' => 0,
            'blocked_sources' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'unapproved_for_rag' => 0,
            'citation_rights_gaps' => 0,
            'redistribution_gaps' => 0,
            'training_use_gaps' => 0,
            'personal_data_gaps' => 0,
            'license_evidence_gaps' => 0,
            'stale_sources' => 0,
        ];

        foreach ($packageFindings as $finding) {
            $summary[$finding['severity']]++;
        }

        foreach ($sources as $index => $source) {
            if (!is_array($source)) {
                $finding = $this->finding('high', 'invalid_source_record', 'sources[' . $index . ']', 'Source record must be an object.');
                $findings[] = $finding;
                $summary['high']++;
                continue;
            }

            $sourceId = $this->sourceTarget($source, $index);
            $sourceFindings = $this->auditSource($sourceId, $source, $asOf, $summary);
            $findings = [...$findings, ...$sourceFindings];

            foreach ($sourceFindings as $finding) {
                $summary[$finding['severity']]++;
            }

            $highestSeverity = $this->highestSeverity($sourceFindings);
            if ($highestSeverity === 'high') {
                $summary['blocked_sources']++;
            } elseif ($highestSeverity === 'medium') {
                $summary['warning_sources']++;
            } else {
                $summary['ready_sources']++;
            }

            $rows[] = [
                'source_id' => $sourceId,
                'title' => $this->stringValue($source, 'title'),
                'source_type' => strtolower($this->stringValue($source, 'source_type')),
                'license_type' => strtolower($this->stringValue($source, 'license_type')),
                'approved_for_rag' => $this->boolValue($source, 'approved_for_rag'),
                'approved_for_citation' => $this->boolValue($source, 'approved_for_citation'),
                'redistribution_allowed' => $this->boolValue($source, 'redistribution_allowed'),
                'finding_count' => count($sourceFindings),
                'highest_severity' => $highestSeverity,
            ];
        }

        $score = $this->score($findings);

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'license_audit_score' => $score,
            'passed' => $summary['high'] === 0 && $score >= 90,
            'summary' => $summary,
            'sources' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $sources
     * @return list<array<string, string>>
     */
    private function auditPackage(array $payload, array $sources, DateTimeImmutable $asOf): array
    {
        $findings = [];
        foreach (['package_id', 'collection_id', 'owner', 'reviewed_at'] as $field) {
            if ($this->stringValue($payload, $field) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'package', "License audit package is missing {$field}.");
            }
        }

        if ($sources === []) {
            $findings[] = $this->finding('high', 'sources_missing', 'package', 'License audit package has no source records.');
        }

        $reviewedAt = $this->daysSince($this->stringValue($payload, 'reviewed_at'), $asOf);
        if ($reviewedAt === null) {
            $findings[] = $this->finding('medium', 'package_review_date_invalid', 'package', 'Package reviewed_at is missing or invalid.');
        } elseif ($reviewedAt > 180) {
            $findings[] = $this->finding('medium', 'package_review_stale', 'package', 'Package review is older than 180 days.');
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, int> $summary
     * @return list<array<string, string>>
     */
    private function auditSource(string $sourceId, array $source, DateTimeImmutable $asOf, array &$summary): array
    {
        $findings = [];
        foreach (['source_id', 'title', 'source_type', 'license_type', 'owner', 'evidence_reference', 'review_due', 'data_classification'] as $field) {
            if ($this->stringValue($source, $field) === '') {
                $findings[] = $this->finding('high', 'source_field_missing', $sourceId, "Source record is missing {$field}.");
            }
        }

        $sourceType = strtolower($this->stringValue($source, 'source_type'));
        if ($sourceType !== '' && !in_array($sourceType, self::KNOWN_SOURCE_TYPES, true)) {
            $findings[] = $this->finding('medium', 'source_type_unknown', $sourceId, 'Source uses an unknown source_type.');
        }

        $licenseType = strtolower($this->stringValue($source, 'license_type'));
        if ($licenseType === '' || in_array($licenseType, self::HIGH_RISK_LICENSES, true)) {
            $summary['license_evidence_gaps']++;
            $findings[] = $this->finding('high', 'license_unclear_or_restricted', $sourceId, 'Source license is unclear, restricted, or unverified.');
        } elseif (!in_array($licenseType, self::LOW_RISK_LICENSES, true)) {
            $findings[] = $this->finding('medium', 'license_type_not_recognized', $sourceId, 'Source license type is not in the recognized allowlist.');
        }

        if ($this->stringValue($source, 'source_terms_reference') === '' && $this->stringValue($source, 'license_url') === '') {
            $summary['license_evidence_gaps']++;
            $findings[] = $this->finding('medium', 'license_reference_missing', $sourceId, 'Source lacks license URL or terms reference.');
        }

        if (!$this->boolValue($source, 'approved_for_rag')) {
            $summary['unapproved_for_rag']++;
            $findings[] = $this->finding('high', 'source_not_approved_for_rag', $sourceId, 'Source is not approved for RAG ingestion.');
        }

        if ($this->boolValue($source, 'citation_expected') && !$this->boolValue($source, 'approved_for_citation')) {
            $summary['citation_rights_gaps']++;
            $findings[] = $this->finding('high', 'citation_rights_missing', $sourceId, 'Source is expected to be cited but is not approved for citation.');
        }

        if ($this->boolValue($source, 'public_answer_enabled') && !$this->boolValue($source, 'redistribution_allowed')) {
            $summary['redistribution_gaps']++;
            $findings[] = $this->finding('high', 'redistribution_not_allowed', $sourceId, 'Source can appear in public answers but redistribution is not allowed.');
        }

        if ($this->boolValue($source, 'provider_training_allowed')) {
            $summary['training_use_gaps']++;
            $findings[] = $this->finding('high', 'provider_training_allowed', $sourceId, 'Source permits provider training use; disable training for institutional RAG sources.');
        }

        if ($this->boolValue($source, 'contains_personal_data') && !$this->boolValue($source, 'personal_data_approval')) {
            $summary['personal_data_gaps']++;
            $findings[] = $this->finding('high', 'personal_data_approval_missing', $sourceId, 'Source contains personal data without documented approval.');
        }

        $classification = strtolower($this->stringValue($source, 'data_classification'));
        if (in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true) && $this->stringValue($source, 'access_scope') === '') {
            $findings[] = $this->finding('medium', 'sensitive_access_scope_missing', $sourceId, 'Sensitive source lacks an access scope.');
        }

        $reviewDue = $this->parseDate($this->stringValue($source, 'review_due'));
        if ($reviewDue === null) {
            $findings[] = $this->finding('medium', 'review_due_invalid', $sourceId, 'Source review_due is missing or invalid.');
        } elseif ($reviewDue < $asOf) {
            $summary['stale_sources']++;
            $findings[] = $this->finding('high', 'source_review_overdue', $sourceId, 'Source review date is overdue.');
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function sourceTarget(array $source, int $index): string
    {
        return $this->stringValue($source, 'source_id') ?: 'source-' . ($index + 1);
    }

    /**
     * @param array<string, mixed> $source
     */
    private function stringValue(array $source, string $field): string
    {
        $value = $source[$field] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $source
     */
    private function boolValue(array $source, string $field): bool
    {
        $value = $source[$field] ?? false;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'enabled', 'approved'], true);
        }

        return false;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function daysSince(string $date, DateTimeImmutable $asOf): ?int
    {
        $parsed = $this->parseDate($date);
        if ($parsed === null) {
            return null;
        }

        return max(0, (int) $parsed->diff($asOf)->format('%a'));
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
