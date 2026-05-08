<?php

declare(strict_types=1);

namespace ChatbotPortal\Analytics;

use JsonException;
use RuntimeException;

final class CostBudgetAuditor
{
    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Cost budget input file not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Cost budget input must be a JSON object.');
        }

        return $this->audit($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload): array
    {
        $budgets = $payload['budgets'] ?? [];
        if (!is_array($budgets)) {
            throw new RuntimeException('Cost budget input must include a budgets array.');
        }

        $findings = [];
        foreach ($budgets as $index => $budget) {
            if (!is_array($budget)) {
                $findings[] = $this->finding('high', 'invalid_budget_record', "budgets[{$index}]", 'Budget record must be an object.');
                continue;
            }

            array_push($findings, ...$this->auditBudget($budget, $index));
        }

        $summary = $this->summary($findings, count($budgets));

        return [
            'passed' => $summary['high'] === 0,
            'summary' => $summary,
            'findings' => $findings,
            'review_questions' => [
                'Are projected monthly costs aligned with approved department budgets?',
                'Do all high-spend bot instances have an accountable owner and review evidence?',
                'Are hard-stop thresholds enforced before provider spend exceeds approved limits?',
                'Were cost spikes caused by retries, larger context windows, model-route changes, or abusive usage?',
                'Do alerts route to both the technical owner and department budget owner?',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $budget
     * @return list<array<string, mixed>>
     */
    private function auditBudget(array $budget, int $index): array
    {
        $id = $this->stringValue($budget, 'bot_id') ?: "budgets[{$index}]";
        $monthlyBudget = $this->numberValue($budget, 'monthly_budget_usd');
        $spendToDate = $this->numberValue($budget, 'spend_to_date_usd');
        $projectedSpend = $this->numberValue($budget, 'projected_monthly_spend_usd');
        $alertThreshold = $this->numberValue($budget, 'alert_threshold_percent') ?: 80.0;
        $hardStop = $this->numberValue($budget, 'hard_stop_percent') ?: 100.0;
        $owner = $this->stringValue($budget, 'owner');
        $reviewStatus = strtolower($this->stringValue($budget, 'review_status'));
        $evidence = $this->stringValue($budget, 'evidence_reference');
        $findings = [];

        if ($monthlyBudget <= 0.0) {
            $findings[] = $this->finding('high', 'budget_missing', $id, 'Monthly budget must be greater than zero.');
            return $findings;
        }

        $projectedPercent = ($projectedSpend / $monthlyBudget) * 100;
        $spendPercent = ($spendToDate / $monthlyBudget) * 100;

        if ($owner === '') {
            $findings[] = $this->finding('high', 'owner_missing', $id, 'Assign a budget owner before enabling or renewing this bot instance.', $budget);
        }
        if ($projectedPercent >= $hardStop) {
            $findings[] = $this->finding('high', 'hard_stop_exceeded', $id, 'Projected monthly spend exceeds the hard-stop threshold.', $budget, $projectedPercent);
        } elseif ($projectedPercent >= $alertThreshold) {
            $findings[] = $this->finding('medium', 'alert_threshold_exceeded', $id, 'Projected monthly spend exceeds the alert threshold.', $budget, $projectedPercent);
        }
        if ($spendPercent >= $alertThreshold && $projectedPercent < $alertThreshold) {
            $findings[] = $this->finding('medium', 'spend_to_date_threshold_exceeded', $id, 'Spend to date already exceeds the alert threshold.', $budget, $spendPercent);
        }
        if (!in_array($reviewStatus, ['approved', 'reviewed', 'accepted'], true)) {
            $findings[] = $this->finding('medium', 'budget_review_missing', $id, 'Record budget review and approval status.', $budget);
        }
        if ($evidence === '') {
            $findings[] = $this->finding('medium', 'evidence_missing', $id, 'Attach budget approval, cost dashboard, or owner review evidence.', $budget);
        }

        if ($findings === []) {
            $findings[] = $this->finding('low', 'budget_current', $id, 'Budget is within threshold and has owner review evidence.', $budget, $projectedPercent);
        }

        return $findings;
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @return array<string, int>
     */
    private function summary(array $findings, int $budgetCount): array
    {
        $summary = [
            'budgets' => $budgetCount,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($findings as $finding) {
            $severity = is_string($finding['severity'] ?? null) ? $finding['severity'] : 'low';
            if (array_key_exists($severity, $summary)) {
                $summary[$severity]++;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $budget
     * @return array<string, mixed>
     */
    private function finding(
        string $severity,
        string $state,
        string $target,
        string $message,
        array $budget = [],
        ?float $thresholdPercent = null
    ): array {
        return [
            'severity' => $severity,
            'state' => $state,
            'target' => $target,
            'department' => $this->stringValue($budget, 'department'),
            'provider' => $this->stringValue($budget, 'provider'),
            'threshold_percent' => $thresholdPercent === null ? null : round($thresholdPercent, 1),
            'message' => $message,
        ];
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
    private function numberValue(array $value, string $key): float
    {
        $raw = $value[$key] ?? 0;
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        if (is_string($raw) && is_numeric($raw)) {
            return (float) $raw;
        }

        return 0.0;
    }
}
