<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use DateTimeImmutable;
use RuntimeException;

final class KnowledgeBaseAccessScopeAuditor
{
    private const SENSITIVE_CLASSIFICATIONS = ['confidential', 'regulated', 'restricted', 'student', 'personal'];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Knowledge-base access scope file was not found.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Knowledge-base access scope file must be valid JSON.');
        }

        return $this->audit($decoded, $asOf ?? new DateTimeImmutable('today'));
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function audit(array $config, DateTimeImmutable $asOf): array
    {
        $botId = (string) ($config['bot_id'] ?? 'unknown-bot');
        $allowedDepartments = $this->listOfStrings($config['allowed_departments'] ?? []);
        $approvedClassifications = $this->listOfStrings($config['approved_classifications'] ?? ['public', 'internal']);
        $collections = is_array($config['knowledge_collections'] ?? null) ? $config['knowledge_collections'] : [];

        $summary = [
            'bot_id' => $botId,
            'collections_reviewed' => count($collections),
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'cross_department_gaps' => 0,
            'classification_gaps' => 0,
            'scope_gaps' => 0,
            'citation_gaps' => 0,
            'approval_gaps' => 0,
            'review_gaps' => 0,
        ];
        $rows = [];
        $findings = [];

        foreach ($collections as $index => $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $collectionId = (string) ($collection['collection_id'] ?? 'collection-' . ($index + 1));
            $classification = strtolower((string) ($collection['data_classification'] ?? ''));
            $department = strtolower((string) ($collection['owner_department'] ?? ''));
            $scope = strtolower((string) ($collection['retrieval_scope'] ?? ''));
            $status = strtolower((string) ($collection['approval_status'] ?? ''));
            $rowFindings = [];

            if ($department === '' || !in_array($department, $allowedDepartments, true)) {
                if (!$this->hasApprovedSharingJustification($collection)) {
                    $summary['cross_department_gaps']++;
                    $rowFindings[] = $this->finding(
                        'high',
                        'cross_department_scope_gap',
                        "{$collectionId} is outside the approved department boundary."
                    );
                }
            }

            if ($classification !== '' && !in_array($classification, $approvedClassifications, true)) {
                $summary['classification_gaps']++;
                $rowFindings[] = $this->finding(
                    in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true) ? 'high' : 'medium',
                    'classification_scope_gap',
                    "{$collectionId} classification is not approved for this bot."
                );
            }

            if (in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true) && $scope === 'global') {
                $summary['scope_gaps']++;
                $rowFindings[] = $this->finding(
                    'high',
                    'sensitive_global_scope',
                    "{$collectionId} exposes sensitive content with global retrieval scope."
                );
            }

            if (in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true) && !$this->truthy($collection['citation_required'] ?? false)) {
                $summary['citation_gaps']++;
                $rowFindings[] = $this->finding(
                    'medium',
                    'citation_required_gap',
                    "{$collectionId} uses sensitive content without a citation requirement."
                );
            }

            if ($this->truthy($collection['pii_allowed'] ?? false) && !in_array($status, ['approved', 'approved_with_controls'], true)) {
                $summary['approval_gaps']++;
                $rowFindings[] = $this->finding(
                    'high',
                    'pii_without_approval',
                    "{$collectionId} allows personal data without approved controls."
                );
            }

            $reviewDue = $this->parseDate((string) ($collection['review_due'] ?? ''));
            if ($reviewDue === null) {
                $summary['review_gaps']++;
                $rowFindings[] = $this->finding('medium', 'missing_review_due', "{$collectionId} has no review due date.");
            } elseif ($reviewDue < $asOf) {
                $summary['review_gaps']++;
                $rowFindings[] = $this->finding('high', 'review_overdue', "{$collectionId} is overdue for access-scope review.");
            }

            foreach ($rowFindings as $finding) {
                $summary[$finding['severity']]++;
            }

            $findings = [...$findings, ...$rowFindings];
            $rows[] = [
                'collection_id' => $collectionId,
                'owner_department' => $department,
                'data_classification' => $classification,
                'retrieval_scope' => $scope,
                'approval_status' => $status,
                'highest_severity' => $this->highestSeverity($rowFindings),
                'finding_count' => count($rowFindings),
            ];
        }

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'passed' => $summary['high'] === 0,
            'summary' => $summary,
            'collections' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function listOfStrings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $value
        )));
    }

    /**
     * @param array<string, mixed> $collection
     */
    private function hasApprovedSharingJustification(array $collection): bool
    {
        $justification = trim((string) ($collection['sharing_justification'] ?? ''));
        $approval = strtolower((string) ($collection['sharing_approval'] ?? ''));

        return $justification !== '' && in_array($approval, ['approved', 'approved_with_controls'], true);
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        return $date instanceof DateTimeImmutable ? $date : null;
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
     * @return array<string, string>
     */
    private function finding(string $severity, string $type, string $message): array
    {
        return [
            'severity' => $severity,
            'type' => $type,
            'message' => $message,
        ];
    }
}
