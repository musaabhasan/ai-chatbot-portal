<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use DateTimeImmutable;
use RuntimeException;

final class RagCitationIntegrityAuditor
{
    private const SENSITIVE_CLASSIFICATIONS = ['confidential', 'regulated', 'restricted', 'student', 'personal'];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null, float $minimumScore = 0.72): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('RAG citation integrity file was not found.');
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            throw new RuntimeException('RAG citation integrity file must be valid JSON.');
        }

        return $this->audit($payload, $asOf ?? new DateTimeImmutable('today'), $minimumScore);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, DateTimeImmutable $asOf, float $minimumScore = 0.72): array
    {
        $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
        $summary = [
            'answers_reviewed' => count($answers),
            'claims_reviewed' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'missing_citations' => 0,
            'unknown_citations' => 0,
            'stale_sources' => 0,
            'low_score_sources' => 0,
            'unapproved_sources' => 0,
            'sensitive_source_gaps' => 0,
        ];
        $rows = [];
        $findings = [];

        foreach ($answers as $answerIndex => $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $answerId = (string) ($answer['answer_id'] ?? 'answer-' . ($answerIndex + 1));
            $sourceIndex = $this->sourceIndex(is_array($answer['retrieved_sources'] ?? null) ? $answer['retrieved_sources'] : []);
            $claims = is_array($answer['claims'] ?? null) ? $answer['claims'] : [];
            $answerFindings = [];
            $summary['claims_reviewed'] += count($claims);

            foreach ($claims as $claimIndex => $claim) {
                if (!is_array($claim)) {
                    continue;
                }
                array_push(
                    $answerFindings,
                    ...$this->auditClaim($answerId, $claim, $claimIndex, $sourceIndex, $asOf, $minimumScore, $summary)
                );
            }

            foreach ($answerFindings as $finding) {
                $summary[$finding['severity']]++;
            }

            $findings = [...$findings, ...$answerFindings];
            $rows[] = [
                'answer_id' => $answerId,
                'claims' => count($claims),
                'sources' => count($sourceIndex),
                'finding_count' => count($answerFindings),
                'highest_severity' => $this->highestSeverity($answerFindings),
            ];
        }

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'minimum_score' => $minimumScore,
            'passed' => $summary['high'] === 0,
            'summary' => $summary,
            'answers' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $claim
     * @param array<string, array<string, mixed>> $sources
     * @param array<string, int> $summary
     * @return list<array<string, string>>
     */
    private function auditClaim(
        string $answerId,
        array $claim,
        int $claimIndex,
        array $sources,
        DateTimeImmutable $asOf,
        float $minimumScore,
        array &$summary
    ): array {
        $target = $answerId . '.claim[' . ($claimIndex + 1) . ']';
        $citationIds = $this->listOfStrings($claim['citation_ids'] ?? []);
        $findings = [];

        if ($citationIds === []) {
            $summary['missing_citations']++;
            return [$this->finding('high', 'missing_citation', $target, 'Claim has no citation identifiers.')];
        }

        foreach ($citationIds as $citationId) {
            if (!array_key_exists($citationId, $sources)) {
                $summary['unknown_citations']++;
                $findings[] = $this->finding('high', 'unknown_citation', $target, "Citation {$citationId} was not retrieved for this answer.");
                continue;
            }

            $source = $sources[$citationId];
            $score = (float) ($source['score'] ?? 0);
            if ($score < $minimumScore) {
                $summary['low_score_sources']++;
                $findings[] = $this->finding('medium', 'low_retrieval_score', $target, "Citation {$citationId} has retrieval score below threshold.");
            }

            $status = strtolower((string) ($source['review_status'] ?? ''));
            if (!in_array($status, ['approved', 'approved_with_controls'], true)) {
                $summary['unapproved_sources']++;
                $findings[] = $this->finding('medium', 'source_not_approved', $target, "Citation {$citationId} source is not approved.");
            }

            $reviewDue = $this->parseDate((string) ($source['review_due'] ?? ''));
            if ($reviewDue !== null && $reviewDue < $asOf) {
                $summary['stale_sources']++;
                $findings[] = $this->finding('high', 'source_review_overdue', $target, "Citation {$citationId} source review is overdue.");
            }

            $classification = strtolower((string) ($source['data_classification'] ?? ''));
            if (in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true) && !$this->truthy($source['citation_allowed'] ?? false)) {
                $summary['sensitive_source_gaps']++;
                $findings[] = $this->finding('high', 'sensitive_source_not_citation_allowed', $target, "Citation {$citationId} uses sensitive source material not approved for citation.");
            }
        }

        return $findings;
    }

    /**
     * @param list<mixed> $sources
     * @return array<string, array<string, mixed>>
     */
    private function sourceIndex(array $sources): array
    {
        $index = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceId = (string) ($source['source_id'] ?? '');
            if ($sourceId !== '') {
                $index[$sourceId] = $source;
            }
        }

        return $index;
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

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value)));
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

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
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
