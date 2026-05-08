<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use DateTimeImmutable;
use RuntimeException;

final class KnowledgeBaseFreshnessAuditor
{
    private const REQUIRED_COLUMNS = [
        'document_id',
        'title',
        'owner',
        'source_type',
        'data_classification',
        'indexed_at',
        'last_reviewed_at',
        'next_review_due',
        'citation_required',
        'status',
        'notes',
    ];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Knowledge-base inventory file was not found.');
        }

        return $this->audit($this->loadCsv($path), $asOf ?? new DateTimeImmutable('today'));
    }

    /**
     * @param list<array<string, string>> $documents
     * @return array<string, mixed>
     */
    public function audit(array $documents, DateTimeImmutable $asOf): array
    {
        $rows = [];
        $findings = [];
        $summary = [
            'total_documents' => count($documents),
            'active_documents' => 0,
            'overdue_review' => 0,
            'due_soon' => 0,
            'missing_owner' => 0,
            'missing_review_date' => 0,
            'citation_gap' => 0,
            'stale_index' => 0,
        ];

        foreach ($documents as $index => $document) {
            $documentId = $document['document_id'] !== '' ? $document['document_id'] : 'row-' . ($index + 2);
            $status = strtolower($document['status'] ?? '');
            $isActive = !in_array($status, ['retired', 'archived', 'disabled'], true);
            if ($isActive) {
                $summary['active_documents']++;
            }

            $rowFindings = [];
            if (trim($document['owner'] ?? '') === '') {
                $summary['missing_owner']++;
                $rowFindings[] = $this->finding('high', 'missing_owner', "{$documentId} has no accountable content owner.");
            }

            $nextReview = $this->parseDate($document['next_review_due'] ?? '');
            if ($isActive && $nextReview === null) {
                $summary['missing_review_date']++;
                $rowFindings[] = $this->finding('high', 'missing_review_date', "{$documentId} has no next review due date.");
            } elseif ($isActive && $nextReview !== null) {
                $daysToReview = (int) $asOf->diff($nextReview)->format('%r%a');
                if ($daysToReview < 0) {
                    $summary['overdue_review']++;
                    $rowFindings[] = $this->finding('high', 'overdue_review', "{$documentId} is overdue for source review.");
                } elseif ($daysToReview <= 30) {
                    $summary['due_soon']++;
                    $rowFindings[] = $this->finding('medium', 'review_due_soon', "{$documentId} is due for review within 30 days.");
                }
            }

            $lastReviewed = $this->parseDate($document['last_reviewed_at'] ?? '');
            $indexedAt = $this->parseDate($document['indexed_at'] ?? '');
            if ($isActive && $lastReviewed !== null && $indexedAt !== null && $indexedAt < $lastReviewed) {
                $summary['stale_index']++;
                $rowFindings[] = $this->finding('medium', 'stale_index', "{$documentId} was reviewed after it was indexed.");
            }

            $classification = strtolower($document['data_classification'] ?? '');
            if ($isActive && in_array($classification, ['regulated', 'confidential', 'restricted'], true) && !$this->isTruthy($document['citation_required'] ?? '')) {
                $summary['citation_gap']++;
                $rowFindings[] = $this->finding('medium', 'citation_gap', "{$documentId} uses sensitive data but does not require citations.");
            }

            $findings = [...$findings, ...$rowFindings];
            $rows[] = [
                'document_id' => $documentId,
                'title' => $document['title'] ?? '',
                'owner' => $document['owner'] ?? '',
                'source_type' => $document['source_type'] ?? '',
                'data_classification' => $document['data_classification'] ?? '',
                'status' => $document['status'] ?? '',
                'finding_count' => count($rowFindings),
                'highest_severity' => $this->highestSeverity($rowFindings),
            ];
        }

        $score = $this->score($findings);

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'freshness_score' => $score,
            'passed' => $score >= 90 && !$this->hasHighFinding($findings),
            'summary' => $summary,
            'documents' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function loadCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read knowledge-base inventory.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            throw new RuntimeException('Knowledge-base inventory is empty.');
        }
        $header = array_map(static fn (mixed $value): string => trim((string) $value), $header);
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $header));
        if ($missing !== []) {
            throw new RuntimeException('Knowledge-base inventory missing columns: ' . implode(', ', $missing));
        }

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = trim((string) ($values[$index] ?? ''));
            }
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
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
                'medium' => 10,
                default => 3,
            };
        }

        return max(0, $score);
    }

    /**
     * @param list<array<string, string>> $findings
     */
    private function hasHighFinding(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding['severity'] === 'high') {
                return true;
            }
        }

        return false;
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
