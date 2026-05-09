<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use DateTimeImmutable;
use RuntimeException;

final class AnswerProvenanceDriftAuditor
{
    /** @var list<string> */
    private const APPROVED_STATUSES = ['approved', 'approved_with_controls'];

    /** @var list<string> */
    private const SENSITIVE_CLASSIFICATIONS = ['confidential', 'regulated', 'restricted', 'student', 'personal'];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null, float $minimumScore = 0.74, float $maxScoreDrop = 0.15): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Answer provenance drift file was not found.');
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Answer provenance drift file must be valid JSON.');
        }

        return $this->audit($payload, $asOf ?? new DateTimeImmutable('today'), $minimumScore, $maxScoreDrop);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, DateTimeImmutable $asOf, float $minimumScore = 0.74, float $maxScoreDrop = 0.15): array
    {
        $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
        $summary = [
            'answers_reviewed' => count($answers),
            'claims_reviewed' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'missing_claim_citations' => 0,
            'unknown_current_citations' => 0,
            'missing_expected_sources' => 0,
            'stale_current_sources' => 0,
            'unapproved_current_sources' => 0,
            'low_score_current_sources' => 0,
            'retrieval_score_drops' => 0,
            'citation_set_drift' => 0,
            'sensitive_citation_gaps' => 0,
        ];
        $packageFindings = $this->auditPackage($payload, $answers);
        $findings = [...$packageFindings];
        $rows = [];

        foreach ($answers as $answerIndex => $answer) {
            if (!is_array($answer)) {
                $findings[] = $this->finding('high', 'invalid_answer_record', 'answers[' . $answerIndex . ']', 'Answer record must be an object.');
                continue;
            }

            $answerId = (string) ($answer['answer_id'] ?? 'answer-' . ($answerIndex + 1));
            $baselineSources = $this->sourceIndex(is_array($answer['baseline_sources'] ?? null) ? $answer['baseline_sources'] : []);
            $currentSources = $this->sourceIndex(is_array($answer['current_sources'] ?? null) ? $answer['current_sources'] : []);
            $claims = is_array($answer['claims'] ?? null) ? $answer['claims'] : [];
            $summary['claims_reviewed'] += count($claims);
            $answerFindings = [];

            if ($baselineSources === []) {
                $answerFindings[] = $this->finding('medium', 'baseline_sources_missing', $answerId, 'Answer has no baseline source set for drift comparison.');
            }
            if ($currentSources === []) {
                $answerFindings[] = $this->finding('high', 'current_sources_missing', $answerId, 'Answer has no current source set.');
            }

            foreach ($claims as $claimIndex => $claim) {
                if (!is_array($claim)) {
                    $answerFindings[] = $this->finding('high', 'invalid_claim_record', $answerId . '.claim[' . ($claimIndex + 1) . ']', 'Claim record must be an object.');
                    continue;
                }

                array_push(
                    $answerFindings,
                    ...$this->auditClaim(
                        $answerId,
                        $claim,
                        $claimIndex,
                        $baselineSources,
                        $currentSources,
                        $asOf,
                        $minimumScore,
                        $maxScoreDrop,
                        $summary
                    )
                );
            }

            foreach ($answerFindings as $finding) {
                $summary[$finding['severity']]++;
            }
            $findings = [...$findings, ...$answerFindings];
            $rows[] = [
                'answer_id' => $answerId,
                'claims' => count($claims),
                'baseline_sources' => count($baselineSources),
                'current_sources' => count($currentSources),
                'finding_count' => count($answerFindings),
                'highest_severity' => $this->highestSeverity($answerFindings),
            ];
        }

        foreach ($packageFindings as $finding) {
            $summary[$finding['severity']]++;
        }

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'minimum_score' => $minimumScore,
            'max_score_drop' => $maxScoreDrop,
            'provenance_drift_score' => $this->score($findings),
            'passed' => $summary['high'] === 0,
            'summary' => $summary,
            'answers' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $answers
     * @return list<array<string, string>>
     */
    private function auditPackage(array $payload, array $answers): array
    {
        $findings = [];
        foreach (['package_id', 'bot_id', 'baseline_run_id', 'current_run_id', 'owner'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'package', "Provenance drift package is missing {$field}.");
            }
        }
        if ($answers === []) {
            $findings[] = $this->finding('high', 'answers_missing', 'package', 'Provenance drift package has no answers to review.');
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $claim
     * @param array<string, array<string, mixed>> $baselineSources
     * @param array<string, array<string, mixed>> $currentSources
     * @param array<string, int> $summary
     * @return list<array<string, string>>
     */
    private function auditClaim(
        string $answerId,
        array $claim,
        int $claimIndex,
        array $baselineSources,
        array $currentSources,
        DateTimeImmutable $asOf,
        float $minimumScore,
        float $maxScoreDrop,
        array &$summary
    ): array {
        $target = $answerId . '.claim[' . ($claimIndex + 1) . ']';
        $currentCitationIds = $this->listOfStrings($claim['current_citation_ids'] ?? []);
        $expectedSourceIds = $this->listOfStrings($claim['expected_source_ids'] ?? []);
        $findings = [];

        if (trim((string) ($claim['claim'] ?? '')) === '') {
            $findings[] = $this->finding('medium', 'claim_text_missing', $target, 'Claim text is missing.');
        }

        if ($currentCitationIds === []) {
            $summary['missing_claim_citations']++;
            return [$this->finding('high', 'missing_claim_citations', $target, 'Claim has no current citation identifiers.')];
        }

        foreach ($expectedSourceIds as $expectedId) {
            if (!in_array($expectedId, $currentCitationIds, true)) {
                $summary['missing_expected_sources']++;
                $findings[] = $this->finding('medium', 'expected_source_not_cited', $target, "Expected source {$expectedId} is not cited in the current answer.");
            }
        }

        if ($expectedSourceIds !== [] && $this->setsDiffer($expectedSourceIds, $currentCitationIds)) {
            $summary['citation_set_drift']++;
            $findings[] = $this->finding('low', 'citation_set_changed', $target, 'Current citation set differs from expected citation set.');
        }

        foreach ($currentCitationIds as $citationId) {
            if (!array_key_exists($citationId, $currentSources)) {
                $summary['unknown_current_citations']++;
                $findings[] = $this->finding('high', 'unknown_current_citation', $target, "Citation {$citationId} was not retrieved for the current answer.");
                continue;
            }

            $current = $currentSources[$citationId];
            $score = (float) ($current['score'] ?? 0);
            if ($score < $minimumScore) {
                $summary['low_score_current_sources']++;
                $findings[] = $this->finding('medium', 'low_current_retrieval_score', $target, "Citation {$citationId} current retrieval score is below threshold.");
            }

            $baselineScore = array_key_exists($citationId, $baselineSources) ? (float) ($baselineSources[$citationId]['score'] ?? $score) : $score;
            if (($baselineScore - $score) > $maxScoreDrop) {
                $summary['retrieval_score_drops']++;
                $findings[] = $this->finding('medium', 'retrieval_score_drop', $target, "Citation {$citationId} retrieval score dropped beyond tolerance.");
            }

            $status = strtolower((string) ($current['review_status'] ?? ''));
            if (!in_array($status, self::APPROVED_STATUSES, true)) {
                $summary['unapproved_current_sources']++;
                $findings[] = $this->finding('medium', 'current_source_not_approved', $target, "Citation {$citationId} current source is not approved.");
            }

            $reviewDue = $this->parseDate((string) ($current['review_due'] ?? ''));
            if ($reviewDue !== null && $reviewDue < $asOf) {
                $summary['stale_current_sources']++;
                $findings[] = $this->finding('high', 'current_source_review_overdue', $target, "Citation {$citationId} current source review is overdue.");
            }

            $classification = strtolower((string) ($current['data_classification'] ?? ''));
            if (in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true) && !$this->truthy($current['citation_allowed'] ?? false)) {
                $summary['sensitive_citation_gaps']++;
                $findings[] = $this->finding('high', 'sensitive_current_source_not_citation_allowed', $target, "Citation {$citationId} uses sensitive source material not approved for citation.");
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
            $sourceId = trim((string) ($source['source_id'] ?? ''));
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

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function setsDiffer(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left !== $right;
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
