<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class ModelMigrationCanaryReadinessAuditor
{
    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Model migration canary input file was not found.');
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Model migration canary input must be a JSON object.');
        }

        return $this->audit($payload, $asOf ?? new DateTimeImmutable('today'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, DateTimeImmutable $asOf): array
    {
        $canaries = $payload['canaries'] ?? [];
        if (!is_array($canaries)) {
            throw new RuntimeException('Model migration canary input must include a canaries array.');
        }

        $summary = [
            'canaries_reviewed' => count($canaries),
            'ready_canaries' => 0,
            'warning_canaries' => 0,
            'blocked_canaries' => 0,
            'traffic_limit_gaps' => 0,
            'rollback_gaps' => 0,
            'monitoring_gaps' => 0,
            'regression_gaps' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        $findings = $this->auditPackage($payload, $canaries, $asOf);
        foreach ($findings as $finding) {
            $summary[$finding['severity']]++;
        }

        $rows = [];
        foreach ($canaries as $index => $canary) {
            if (!is_array($canary)) {
                $finding = $this->finding('high', 'invalid_canary_record', "canaries[{$index}]", 'Canary record must be an object.');
                $findings[] = $finding;
                $summary['high']++;
                $summary['blocked_canaries']++;
                continue;
            }

            $target = $this->canaryTarget($canary, $index);
            $canaryFindings = $this->auditCanary($target, $canary, $asOf, $summary);
            $findings = [...$findings, ...$canaryFindings];

            foreach ($canaryFindings as $finding) {
                $summary[$finding['severity']]++;
            }

            $highestSeverity = $this->highestSeverity($canaryFindings);
            if ($highestSeverity === 'high') {
                $summary['blocked_canaries']++;
            } elseif ($highestSeverity === 'medium') {
                $summary['warning_canaries']++;
            } else {
                $summary['ready_canaries']++;
            }

            $rows[] = [
                'target' => $target,
                'bot_id' => $this->stringValue($canary, 'bot_id'),
                'source_model' => $this->stringValue($canary, 'source_model'),
                'target_model' => $this->stringValue($canary, 'target_model'),
                'canary_percent' => $this->floatValue($canary, 'canary_percent'),
                'highest_severity' => $highestSeverity,
                'finding_count' => count($canaryFindings),
            ];
        }

        $score = $this->score($findings);

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'canary_readiness_score' => $score,
            'passed' => $summary['high'] === 0 && $score >= 90,
            'summary' => $summary,
            'canaries' => $rows,
            'findings' => $findings,
            'review_questions' => [
                'Is the model migration limited to a measurable user or session percentage?',
                'Do shadow and canary evaluations compare source and target model behavior on the same scenario set?',
                'Are safety, citation, tool-use, latency, and cost thresholds tied to automatic rollback decisions?',
                'Who owns live monitoring during the canary window and after expansion?',
                'Can the team pause, roll back, or fail closed without changing unrelated bot configuration?',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $canaries
     * @return list<array<string, mixed>>
     */
    private function auditPackage(array $payload, array $canaries, DateTimeImmutable $asOf): array
    {
        $findings = [];
        foreach (['package_id', 'owner', 'reviewed_at', 'evidence_reference'] as $field) {
            if ($this->stringValue($payload, $field) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'package', "Canary readiness package is missing {$field}.");
            }
        }

        if ($canaries === []) {
            $findings[] = $this->finding('high', 'canaries_missing', 'package', 'Canary readiness package has no canary records.');
        }

        $daysSinceReview = $this->daysSince($this->stringValue($payload, 'reviewed_at'), $asOf);
        if ($daysSinceReview === null) {
            $findings[] = $this->finding('medium', 'package_review_date_invalid', 'package', 'Package reviewed_at is missing or invalid.');
        } elseif ($daysSinceReview > 30) {
            $findings[] = $this->finding('medium', 'package_review_stale', 'package', 'Package review is older than 30 days for a canary rollout.', [], ['days_since_review' => $daysSinceReview]);
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $canary
     * @param array<string, int> $summary
     * @return list<array<string, mixed>>
     */
    private function auditCanary(string $target, array $canary, DateTimeImmutable $asOf, array &$summary): array
    {
        $findings = [];
        foreach (['bot_id', 'department', 'source_model', 'target_model', 'owner', 'monitoring_owner', 'evaluation_pack_id', 'evidence_reference', 'last_tested_at'] as $field) {
            if ($this->stringValue($canary, $field) === '') {
                $findings[] = $this->finding('high', 'canary_field_missing', $target, "Canary record is missing {$field}.", $canary);
            }
        }

        $canaryPercent = $this->floatValue($canary, 'canary_percent');
        if ($canaryPercent === null || $canaryPercent <= 0) {
            $summary['traffic_limit_gaps']++;
            $findings[] = $this->finding('high', 'canary_percent_missing', $target, 'Set a positive canary traffic percentage.', $canary);
        } elseif ($canaryPercent > 10) {
            $summary['traffic_limit_gaps']++;
            $findings[] = $this->finding('medium', 'canary_percent_high', $target, 'Canary traffic above 10% should be explicitly justified.', $canary, ['canary_percent' => $canaryPercent]);
        }

        if (!$this->boolValue($canary, 'shadow_evaluation_complete')) {
            $summary['regression_gaps']++;
            $findings[] = $this->finding('high', 'shadow_evaluation_missing', $target, 'Complete shadow evaluation before routing live traffic to the target model.', $canary);
        }

        if (!$this->boolValue($canary, 'baseline_locked')) {
            $summary['regression_gaps']++;
            $findings[] = $this->finding('high', 'baseline_not_locked', $target, 'Lock source-model baseline metrics before canary launch.', $canary);
        }

        $daysSinceTest = $this->daysSince($this->stringValue($canary, 'last_tested_at'), $asOf);
        if ($daysSinceTest === null) {
            $findings[] = $this->finding('high', 'canary_test_date_missing', $target, 'Record the most recent canary or shadow test date.', $canary);
        } elseif ($daysSinceTest > 30) {
            $findings[] = $this->finding('high', 'canary_test_stale', $target, 'Canary test evidence is older than 30 days.', $canary, ['days_since_test' => $daysSinceTest]);
        }

        $passRate = $this->floatValue($canary, 'evaluation_pass_rate');
        if ($passRate === null) {
            $findings[] = $this->finding('high', 'evaluation_pass_rate_missing', $target, 'Record evaluation pass rate for the target model.', $canary);
        } elseif ($passRate < 0.95) {
            $summary['regression_gaps']++;
            $findings[] = $this->finding('high', 'evaluation_pass_rate_low', $target, 'Evaluation pass rate is below the 95% canary readiness threshold.', $canary, ['evaluation_pass_rate' => $passRate]);
        }

        foreach ([
            'safety_regression_threshold' => 'Safety regression threshold is missing.',
            'citation_regression_threshold' => 'Citation regression threshold is missing.',
            'tool_policy_regression_threshold' => 'Tool-policy regression threshold is missing.',
            'latency_regression_threshold_ms' => 'Latency regression threshold is missing.',
            'cost_delta_threshold_percent' => 'Cost delta threshold is missing.',
        ] as $field => $message) {
            if ($this->stringValue($canary, $field) === '') {
                $summary['rollback_gaps']++;
                $findings[] = $this->finding('medium', $field . '_missing', $target, $message, $canary);
            }
        }

        foreach ([
            'automatic_rollback_enabled' => 'Enable automatic or operator-approved rollback when thresholds breach.',
            'rollback_runbook' => 'Attach rollback runbook evidence.',
            'monitoring_dashboard' => 'Attach monitoring dashboard or query reference.',
            'user_impact_plan' => 'Document user impact handling and support escalation.',
            'cost_owner_approved' => 'Record cost owner approval for the canary target model.',
        ] as $field => $message) {
            if ($field === 'automatic_rollback_enabled' || $field === 'cost_owner_approved') {
                if (!$this->boolValue($canary, $field)) {
                    $summary[$field === 'cost_owner_approved' ? 'monitoring_gaps' : 'rollback_gaps']++;
                    $findings[] = $this->finding('high', $field . '_missing', $target, $message, $canary);
                }
                continue;
            }

            if ($this->stringValue($canary, $field) === '') {
                $summary[$field === 'rollback_runbook' ? 'rollback_gaps' : 'monitoring_gaps']++;
                $findings[] = $this->finding('medium', $field . '_missing', $target, $message, $canary);
            }
        }

        foreach (['safety_monitoring_enabled', 'citation_monitoring_enabled', 'tool_call_monitoring_enabled', 'latency_monitoring_enabled', 'cost_monitoring_enabled'] as $field) {
            if (!$this->boolValue($canary, $field)) {
                $summary['monitoring_gaps']++;
                $findings[] = $this->finding('medium', $field . '_disabled', $target, "Enable {$field} before canary launch.", $canary);
            }
        }

        if ($findings === []) {
            $findings[] = $this->finding('low', 'canary_ready', $target, 'Model migration canary readiness evidence is complete.', $canary);
        }

        return $findings;
    }

    /**
     * @param list<array<string, mixed>> $findings
     */
    private function score(array $findings): int
    {
        $score = 100;
        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? 'low';
            if ($severity === 'high') {
                $score -= 14;
            } elseif ($severity === 'medium') {
                $score -= 5;
            }
        }

        return max(0, $score);
    }

    /**
     * @param list<array<string, mixed>> $findings
     */
    private function highestSeverity(array $findings): string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        $highest = 'low';
        foreach ($findings as $finding) {
            $severity = is_string($finding['severity'] ?? null) ? $finding['severity'] : 'low';
            if (($rank[$severity] ?? 0) > ($rank[$highest] ?? 0)) {
                $highest = $severity;
            }
        }

        return $highest;
    }

    /**
     * @param array<string, mixed> $canary
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function finding(
        string $severity,
        string $state,
        string $target,
        string $message,
        array $canary = [],
        array $extra = []
    ): array {
        return [
            'severity' => $severity,
            'state' => $state,
            'target' => $target,
            'bot_id' => $this->stringValue($canary, 'bot_id'),
            'department' => $this->stringValue($canary, 'department'),
            'source_model' => $this->stringValue($canary, 'source_model'),
            'target_model' => $this->stringValue($canary, 'target_model'),
            'message' => $message,
        ] + $extra;
    }

    /**
     * @param array<string, mixed> $canary
     */
    private function canaryTarget(array $canary, int $index): string
    {
        $bot = $this->stringValue($canary, 'bot_id');
        $targetModel = $this->stringValue($canary, 'target_model');

        if ($bot === '' && $targetModel === '') {
            return "canaries[{$index}]";
        }

        return trim("{$bot}:{$targetModel}", ':');
    }

    private function daysSince(string $date, DateTimeImmutable $asOf): ?int
    {
        if ($date === '') {
            return null;
        }

        try {
            $parsed = new DateTimeImmutable($date);
        } catch (Throwable) {
            return null;
        }

        return (int) $parsed->diff($asOf)->format('%a');
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stringValue(array $value, string $key): string
    {
        $raw = $value[$key] ?? '';

        return is_scalar($raw) ? trim((string) $raw) : '';
    }

    /**
     * @param array<string, mixed> $value
     */
    private function boolValue(array $value, string $key): bool
    {
        return filter_var($value[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function floatValue(array $value, string $key): ?float
    {
        $raw = $value[$key] ?? null;
        if (is_int($raw) || is_float($raw) || is_string($raw)) {
            return is_numeric($raw) ? (float) $raw : null;
        }

        return null;
    }
}
