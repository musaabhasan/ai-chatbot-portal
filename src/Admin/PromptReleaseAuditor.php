<?php

declare(strict_types=1);

namespace ChatbotPortal\Admin;

final class PromptReleaseAuditor
{
    private const MINIMUM_PASS_RATE = 0.90;

    /**
     * @param list<array<string, mixed>> $releases
     * @return array<string, mixed>
     */
    public function audit(array $releases): array
    {
        $findings = [];
        $rows = [];
        $summary = [
            'total_releases' => count($releases),
            'passed_releases' => 0,
            'blocked_releases' => 0,
            'warning_releases' => 0,
            'high_findings' => 0,
            'medium_findings' => 0,
            'low_findings' => 0,
        ];

        foreach ($releases as $index => $release) {
            $releaseId = $this->stringValue($release, 'prompt_version') ?: 'row-' . ($index + 1);
            $releaseFindings = $this->auditRelease($releaseId, $release);
            $highestSeverity = $this->highestSeverity($releaseFindings);

            foreach ($releaseFindings as $finding) {
                $findings[] = $finding;
                $summary[$finding['severity'] . '_findings']++;
            }

            if ($highestSeverity === 'high') {
                $summary['blocked_releases']++;
            } elseif ($highestSeverity === 'medium') {
                $summary['warning_releases']++;
            } else {
                $summary['passed_releases']++;
            }

            $rows[] = [
                'prompt_version' => $releaseId,
                'persona' => $this->stringValue($release, 'persona'),
                'change_type' => $this->stringValue($release, 'change_type'),
                'risk_level' => $this->stringValue($release, 'risk_level'),
                'approval_status' => $this->stringValue($release, 'approval_status'),
                'finding_count' => count($releaseFindings),
                'highest_severity' => $highestSeverity,
            ];
        }

        $score = $this->score($findings);

        return [
            'minimum_pass_rate' => self::MINIMUM_PASS_RATE,
            'release_readiness_score' => $score,
            'passed' => $score >= 90 && $summary['high_findings'] === 0,
            'summary' => $summary,
            'releases' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $release
     * @return list<array<string, string>>
     */
    private function auditRelease(string $releaseId, array $release): array
    {
        $findings = [];
        $riskLevel = strtolower($this->stringValue($release, 'risk_level'));
        $dataClassification = strtolower($this->stringValue($release, 'data_classification'));
        $approvalStatus = strtolower($this->stringValue($release, 'approval_status'));
        $passRate = $this->floatValue($release, 'evaluation_pass_rate');
        $usesTools = $this->truthy($release, 'contains_tool_instructions');
        $changesRag = $this->truthy($release, 'rag_sources_changed');
        $requiresHumanReview = $this->truthy($release, 'human_review_required')
            || in_array($dataClassification, ['regulated', 'confidential', 'restricted'], true);
        $highRisk = in_array($riskLevel, ['high', 'critical'], true);

        if ($approvalStatus !== 'approved' || $this->stringValue($release, 'approved_by') === '') {
            $findings[] = $this->finding(
                'high',
                'approval_gap',
                $releaseId,
                'Prompt release is missing final approval evidence.'
            );
        }

        if ($this->stringValue($release, 'evaluation_pack') === '') {
            $findings[] = $this->finding(
                'high',
                'missing_evaluation_pack',
                $releaseId,
                'Prompt release has no linked evaluation pack.'
            );
        }

        if ($passRate === null || $passRate < self::MINIMUM_PASS_RATE) {
            $findings[] = $this->finding(
                'high',
                'evaluation_below_threshold',
                $releaseId,
                'Evaluation pass rate is missing or below the release threshold.'
            );
        }

        if (($highRisk || $usesTools) && strtolower($this->stringValue($release, 'red_team_status')) !== 'passed') {
            $findings[] = $this->finding(
                'high',
                'red_team_gap',
                $releaseId,
                'High-risk or tool-capable prompt release has not passed red-team review.'
            );
        }

        if ($usesTools && !$this->truthy($release, 'tool_policy_reviewed')) {
            $findings[] = $this->finding(
                'high',
                'tool_policy_gap',
                $releaseId,
                'Tool instructions changed without tool-policy review evidence.'
            );
        }

        if ($changesRag && !$this->truthy($release, 'rag_freshness_reviewed')) {
            $findings[] = $this->finding(
                'medium',
                'rag_freshness_gap',
                $releaseId,
                'RAG source changes do not have freshness-review evidence.'
            );
        }

        if ($this->stringValue($release, 'rollback_version') === '') {
            $findings[] = $this->finding(
                $highRisk ? 'high' : 'medium',
                'rollback_gap',
                $releaseId,
                'Prompt release has no rollback version.'
            );
        }

        if ($requiresHumanReview && !$this->truthy($release, 'human_review_completed')) {
            $findings[] = $this->finding(
                'high',
                'human_review_gap',
                $releaseId,
                'Sensitive or human-review-required prompt release lacks completed reviewer evidence.'
            );
        }

        if ($this->stringValue($release, 'release_notes') === '') {
            $findings[] = $this->finding(
                'low',
                'release_notes_gap',
                $releaseId,
                'Prompt release has no concise change note for future review.'
            );
        }

        return $findings;
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
                'medium' => 10,
                default => 3,
            };
        }

        return max(0, $score);
    }

    /**
     * @param array<string, mixed> $release
     */
    private function stringValue(array $release, string $key): string
    {
        $value = $release[$key] ?? '';
        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $release
     */
    private function floatValue(array $release, string $key): ?float
    {
        $value = $this->stringValue($release, $key);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        return $number > 1 ? $number / 100 : $number;
    }

    /**
     * @param array<string, mixed> $release
     */
    private function truthy(array $release, string $key): bool
    {
        return in_array(strtolower($this->stringValue($release, $key)), ['1', 'true', 'yes', 'y', 'complete', 'completed', 'passed'], true);
    }

    /**
     * @return array<string, string>
     */
    private function finding(string $severity, string $type, string $releaseId, string $message): array
    {
        return [
            'severity' => $severity,
            'type' => $type,
            'prompt_version' => $releaseId,
            'message' => $message,
        ];
    }
}

