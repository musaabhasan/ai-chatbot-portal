<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class ProviderModelDeprecationReadinessAuditor
{
    /** @var list<string> */
    private const RETIRING_STATUSES = [
        'deprecated',
        'retiring',
        'retirement-announced',
        'replacement-planned',
    ];

    /** @var list<string> */
    private const KNOWN_STATUSES = [
        'active',
        'preview',
        'deprecated',
        'retiring',
        'retirement-announced',
        'replacement-planned',
    ];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Provider model deprecation input file was not found.');
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Provider model deprecation input must be a JSON object.');
        }

        return $this->audit($payload, $asOf ?? new DateTimeImmutable('today'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, DateTimeImmutable $asOf): array
    {
        $models = $payload['models'] ?? [];
        if (!is_array($models)) {
            throw new RuntimeException('Provider model deprecation input must include a models array.');
        }

        $summary = [
            'models_reviewed' => count($models),
            'ready_models' => 0,
            'warning_models' => 0,
            'blocked_models' => 0,
            'retirements_within_30_days' => 0,
            'retirements_within_90_days' => 0,
            'missing_fallbacks' => 0,
            'compatibility_gaps' => 0,
            'communication_gaps' => 0,
            'stale_tests' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        $findings = $this->auditPackage($payload, $models, $asOf);
        foreach ($findings as $finding) {
            $summary[$finding['severity']]++;
        }

        $rows = [];
        foreach ($models as $index => $model) {
            if (!is_array($model)) {
                $finding = $this->finding('high', 'invalid_model_record', "models[{$index}]", 'Model record must be an object.');
                $findings[] = $finding;
                $summary['high']++;
                $summary['blocked_models']++;
                continue;
            }

            $target = $this->modelTarget($model, $index);
            $modelFindings = $this->auditModel($target, $model, $asOf, $summary);
            $findings = [...$findings, ...$modelFindings];

            foreach ($modelFindings as $finding) {
                $summary[$finding['severity']]++;
            }

            $highestSeverity = $this->highestSeverity($modelFindings);
            if ($highestSeverity === 'high') {
                $summary['blocked_models']++;
            } elseif ($highestSeverity === 'medium') {
                $summary['warning_models']++;
            } else {
                $summary['ready_models']++;
            }

            $rows[] = [
                'target' => $target,
                'provider' => $this->stringValue($model, 'provider'),
                'model' => $this->stringValue($model, 'model'),
                'environment' => $this->stringValue($model, 'environment'),
                'status' => $this->normalizedStatus($model),
                'retirement_date' => $this->stringValue($model, 'retirement_date'),
                'replacement' => trim($this->stringValue($model, 'replacement_provider') . ':' . $this->stringValue($model, 'replacement_model'), ':'),
                'highest_severity' => $highestSeverity,
                'finding_count' => count($modelFindings),
            ];
        }

        $score = $this->score($findings);

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'deprecation_readiness_score' => $score,
            'passed' => $summary['high'] === 0 && $score >= 90,
            'summary' => $summary,
            'models' => $rows,
            'findings' => $findings,
            'review_questions' => [
                'Which production bot instances still point to models with announced retirement dates?',
                'Is the replacement model tested against the same prompt, RAG, safety, latency, and cost expectations?',
                'Can fallback routing preserve safety behavior, citation behavior, tool policy, and user experience during provider changes?',
                'Have administrators prepared user-facing communication for visible answer, latency, or cost changes?',
                'Does the rollback plan define how to pause or revert a model migration if regressions appear?',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $models
     * @return list<array<string, mixed>>
     */
    private function auditPackage(array $payload, array $models, DateTimeImmutable $asOf): array
    {
        $findings = [];
        foreach (['package_id', 'owner', 'reviewed_at', 'evidence_reference'] as $field) {
            if ($this->stringValue($payload, $field) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'package', "Deprecation readiness package is missing {$field}.");
            }
        }

        if ($models === []) {
            $findings[] = $this->finding('high', 'models_missing', 'package', 'Deprecation readiness package has no model records.');
        }

        $daysSinceReview = $this->daysSince($this->stringValue($payload, 'reviewed_at'), $asOf);
        if ($daysSinceReview === null) {
            $findings[] = $this->finding('medium', 'package_review_date_invalid', 'package', 'Package reviewed_at is missing or invalid.');
        } elseif ($daysSinceReview > 90) {
            $findings[] = $this->finding('medium', 'package_review_stale', 'package', 'Package review is older than 90 days.', ['days_since_review' => $daysSinceReview]);
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $model
     * @param array<string, int> $summary
     * @return list<array<string, mixed>>
     */
    private function auditModel(string $target, array $model, DateTimeImmutable $asOf, array &$summary): array
    {
        $findings = [];
        foreach (['provider', 'model', 'environment', 'status', 'owner', 'evidence_reference', 'last_reviewed'] as $field) {
            if ($this->stringValue($model, $field) === '') {
                $findings[] = $this->finding('high', 'model_field_missing', $target, "Model record is missing {$field}.", $model);
            }
        }

        $status = $this->normalizedStatus($model);
        if ($status !== '' && !in_array($status, self::KNOWN_STATUSES, true)) {
            $findings[] = $this->finding('medium', 'unknown_model_status', $target, 'Model status is not in the recognized status list.', $model);
        }

        $isRetiring = in_array($status, self::RETIRING_STATUSES, true);
        $isProduction = $this->isProductionModel($model);
        $daysUntilRetirement = $this->daysUntil($this->stringValue($model, 'retirement_date'), $asOf);
        $daysSinceReview = $this->daysSince($this->stringValue($model, 'last_reviewed'), $asOf);
        $daysSinceFallbackTest = $this->daysSince($this->stringValue($model, 'fallback_tested_at'), $asOf);

        if ($isRetiring && $this->stringValue($model, 'deprecation_date') === '') {
            $findings[] = $this->finding('medium', 'deprecation_date_missing', $target, 'Retiring model lacks a deprecation announcement date.', $model);
        }

        if ($isRetiring && $daysUntilRetirement === null) {
            $findings[] = $this->finding('high', 'retirement_date_missing', $target, 'Retiring model lacks a retirement date.', $model);
        } elseif ($daysUntilRetirement !== null) {
            if ($daysUntilRetirement < 0) {
                $findings[] = $this->finding('high', 'retired_model_still_configured', $target, 'Model retirement date has passed while the model remains configured.', $model, ['days_until_retirement' => $daysUntilRetirement]);
            } elseif ($daysUntilRetirement <= 30) {
                $summary['retirements_within_30_days']++;
                $findings[] = $this->finding('high', 'retirement_within_30_days', $target, 'Model retires within 30 days; complete migration evidence before release.', $model, ['days_until_retirement' => $daysUntilRetirement]);
            } elseif ($daysUntilRetirement <= 90) {
                $summary['retirements_within_90_days']++;
                $findings[] = $this->finding('medium', 'retirement_within_90_days', $target, 'Model retires within 90 days; migration evidence should be refreshed.', $model, ['days_until_retirement' => $daysUntilRetirement]);
            }
        }

        if ($this->dateInPast($this->stringValue($model, 'deprecation_date'), $asOf) && $status === 'active') {
            $findings[] = $this->finding('medium', 'active_model_after_deprecation_notice', $target, 'Model has a past deprecation date but is still marked active.', $model);
        }

        if ($isRetiring && ($this->stringValue($model, 'replacement_provider') === '' || $this->stringValue($model, 'replacement_model') === '')) {
            $findings[] = $this->finding('high', 'replacement_model_missing', $target, 'Retiring model needs an approved replacement provider and model.', $model);
        }

        if (!$this->boolValue($model, 'fallback_configured')) {
            $summary['missing_fallbacks']++;
            $severity = $isProduction || $isRetiring ? 'high' : 'medium';
            $findings[] = $this->finding($severity, 'fallback_not_configured', $target, 'Configure fallback routing or document a fail-closed decision.', $model);
        }

        if ($daysSinceFallbackTest === null) {
            $severity = $isProduction || $isRetiring ? 'high' : 'medium';
            $findings[] = $this->finding($severity, 'fallback_test_missing', $target, 'Record a successful fallback or replacement-path test.', $model);
        } elseif ($daysSinceFallbackTest > 90) {
            $summary['stale_tests']++;
            $severity = $isProduction || $isRetiring ? 'high' : 'medium';
            $findings[] = $this->finding($severity, 'fallback_test_stale', $target, 'Fallback or replacement-path test evidence is older than 90 days.', $model, ['days_since_fallback_test' => $daysSinceFallbackTest]);
        }

        foreach ([
            'prompt_compatibility_review' => 'Prompt compatibility review is missing for the replacement model.',
            'rag_compatibility_review' => 'RAG retrieval and citation compatibility review is missing.',
        ] as $field => $message) {
            if (!$this->boolValue($model, $field)) {
                $summary['compatibility_gaps']++;
                $findings[] = $this->finding('medium', $field . '_missing', $target, $message, $model);
            }
        }

        if (!$this->boolValue($model, 'safety_equivalence_review')) {
            $summary['compatibility_gaps']++;
            $severity = $isProduction || $isRetiring ? 'high' : 'medium';
            $findings[] = $this->finding($severity, 'safety_equivalence_review_missing', $target, 'Safety, refusal, and tool-policy equivalence review is missing.', $model);
        }

        if ($this->stringValue($model, 'evaluation_pack_id') === '') {
            $findings[] = $this->finding('high', 'evaluation_pack_missing', $target, 'Attach the evaluation pack used to approve the replacement or fallback path.', $model);
        }

        $passRate = $this->floatValue($model, 'evaluation_pass_rate');
        if ($passRate === null) {
            $findings[] = $this->finding('high', 'evaluation_pass_rate_missing', $target, 'Record evaluation pass rate for the current or replacement model.', $model);
        } elseif ($passRate < 0.9) {
            $findings[] = $this->finding('high', 'evaluation_pass_rate_low', $target, 'Evaluation pass rate is below 90%.', $model, ['evaluation_pass_rate' => $passRate]);
        } elseif ($passRate < 0.95) {
            $findings[] = $this->finding('medium', 'evaluation_pass_rate_warning', $target, 'Evaluation pass rate is below the 95% readiness target.', $model, ['evaluation_pass_rate' => $passRate]);
        }

        if (!$this->boolValue($model, 'cost_impact_review')) {
            $findings[] = $this->finding('medium', 'cost_impact_review_missing', $target, 'Replacement model cost, context-window, and rate-limit impact review is missing.', $model);
        }

        if (!$this->boolValue($model, 'user_communication_ready')) {
            $summary['communication_gaps']++;
            $severity = ($daysUntilRetirement !== null && $daysUntilRetirement <= 30) ? 'high' : 'medium';
            $findings[] = $this->finding($severity, 'user_communication_missing', $target, 'Prepare user or administrator communication for visible model behavior changes.', $model);
        }

        if ($this->stringValue($model, 'rollback_plan') === '') {
            $severity = $isProduction || $isRetiring ? 'high' : 'medium';
            $findings[] = $this->finding($severity, 'rollback_plan_missing', $target, 'Define rollback, pause, or fail-closed actions for migration issues.', $model);
        }

        if ($daysSinceReview === null) {
            $findings[] = $this->finding('medium', 'model_review_date_invalid', $target, 'Model last_reviewed value is missing or invalid.', $model);
        } elseif ($daysSinceReview > 90) {
            $severity = $isProduction || $isRetiring ? 'high' : 'medium';
            $findings[] = $this->finding($severity, 'model_review_stale', $target, 'Model readiness review is older than 90 days.', $model, ['days_since_review' => $daysSinceReview]);
        }

        if ($findings === []) {
            $findings[] = $this->finding('low', 'model_deprecation_ready', $target, 'Model deprecation and replacement readiness evidence is complete.', $model);
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
                $score -= 15;
            } elseif ($severity === 'medium') {
                $score -= 6;
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
     * @param array<string, mixed> $model
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function finding(
        string $severity,
        string $state,
        string $target,
        string $message,
        array $model = [],
        array $extra = []
    ): array {
        return [
            'severity' => $severity,
            'state' => $state,
            'target' => $target,
            'provider' => $this->stringValue($model, 'provider'),
            'model' => $this->stringValue($model, 'model'),
            'environment' => $this->stringValue($model, 'environment'),
            'message' => $message,
        ] + $extra;
    }

    /**
     * @param array<string, mixed> $model
     */
    private function modelTarget(array $model, int $index): string
    {
        $provider = $this->stringValue($model, 'provider');
        $modelName = $this->stringValue($model, 'model');
        $environment = $this->stringValue($model, 'environment');

        if ($provider === '' && $modelName === '') {
            return "models[{$index}]";
        }

        return trim("{$provider}:{$modelName}:{$environment}", ':');
    }

    /**
     * @param array<string, mixed> $model
     */
    private function normalizedStatus(array $model): string
    {
        return str_replace('_', '-', strtolower($this->stringValue($model, 'status')));
    }

    /**
     * @param array<string, mixed> $model
     */
    private function isProductionModel(array $model): bool
    {
        $environment = strtolower($this->stringValue($model, 'environment'));
        $trafficTier = strtolower($this->stringValue($model, 'traffic_tier'));

        return str_contains($environment, 'prod') || in_array($trafficTier, ['high', 'critical', 'public'], true);
    }

    private function daysSince(string $date, DateTimeImmutable $asOf): ?int
    {
        $parsed = $this->parseDate($date);
        if ($parsed === null) {
            return null;
        }

        return (int) $parsed->diff($asOf)->format('%a');
    }

    private function daysUntil(string $date, DateTimeImmutable $asOf): ?int
    {
        $parsed = $this->parseDate($date);
        if ($parsed === null) {
            return null;
        }

        $seconds = $parsed->setTime(0, 0)->getTimestamp() - $asOf->setTime(0, 0)->getTimestamp();

        return (int) floor($seconds / 86400);
    }

    private function dateInPast(string $date, DateTimeImmutable $asOf): bool
    {
        $daysUntil = $this->daysUntil($date, $asOf);

        return $daysUntil !== null && $daysUntil < 0;
    }

    private function parseDate(string $date): ?DateTimeImmutable
    {
        if ($date === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($date);
        } catch (Throwable) {
            return null;
        }
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
