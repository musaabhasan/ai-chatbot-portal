<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

use DateTimeImmutable;
use RuntimeException;

final class ProviderFailoverReadinessAuditor
{
    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path, ?DateTimeImmutable $asOf = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Provider failover input file not found: {$path}");
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Provider failover input must be a JSON object.');
        }

        return $this->audit($payload, $asOf);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, ?DateTimeImmutable $asOf = null): array
    {
        $asOf ??= new DateTimeImmutable('now');
        $bots = $payload['bots'] ?? [];
        if (!is_array($bots)) {
            throw new RuntimeException('Provider failover input must include a bots array.');
        }

        $findings = [];
        foreach ($bots as $index => $bot) {
            if (!is_array($bot)) {
                $findings[] = $this->finding('high', 'invalid_bot_record', "bots[{$index}]", 'Bot record must be an object.');
                continue;
            }

            array_push($findings, ...$this->auditBot($bot, $index, $asOf));
        }

        $summary = $this->summary($findings, count($bots));

        return [
            'passed' => $summary['high'] === 0,
            'summary' => $summary,
            'findings' => $findings,
            'review_questions' => [
                'Can the chatbot continue service when the primary provider is unavailable or degraded?',
                'Does fallback routing preserve data residency, retention, safety policy, and logging requirements?',
                'Are retry and timeout settings narrow enough to avoid cost spikes and poor user experience?',
                'Was the fallback path tested recently with evidence linked to the bot owner?',
                'Does the operations runbook explain when to fail open, fail closed, or pause the bot?',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $bot
     * @return list<array<string, mixed>>
     */
    private function auditBot(array $bot, int $index, DateTimeImmutable $asOf): array
    {
        $id = $this->stringValue($bot, 'bot_id') ?: "bots[{$index}]";
        $primaryProvider = strtolower($this->stringValue($bot, 'primary_provider'));
        $fallbackProviders = $this->stringList($bot['fallback_providers'] ?? []);
        $timeoutBudget = $this->intValue($bot, 'timeout_budget_ms');
        $retryLimit = $this->intValue($bot, 'retry_limit');
        $findings = [];

        if ($this->stringValue($bot, 'owner') === '') {
            $findings[] = $this->finding('high', 'owner_missing', $id, 'Assign a business and technical owner before enabling fallback routing.', $bot);
        }

        if ($primaryProvider === '') {
            $findings[] = $this->finding('high', 'primary_provider_missing', $id, 'Set the primary provider for the bot instance.', $bot);
        }

        if ($fallbackProviders === []) {
            $findings[] = $this->finding('high', 'fallback_provider_missing', $id, 'Configure at least one approved fallback provider or document a fail-closed decision.', $bot);
        }

        if ($primaryProvider !== '' && in_array($primaryProvider, $fallbackProviders, true)) {
            $findings[] = $this->finding('medium', 'primary_repeated_in_fallback', $id, 'Fallback provider list repeats the primary provider.', $bot);
        }

        if (!in_array(strtolower($this->stringValue($bot, 'health_check_status')), ['passing', 'healthy', 'ok'], true)) {
            $findings[] = $this->finding('medium', 'health_check_not_passing', $id, 'Provider health check status is not passing.', $bot);
        }

        $daysSinceTest = $this->daysSince($this->stringValue($bot, 'last_failover_tested_at'), $asOf);
        if ($daysSinceTest === null) {
            $findings[] = $this->finding('high', 'failover_test_missing', $id, 'Record the last successful failover test date.', $bot);
        } elseif ($daysSinceTest > 90) {
            $findings[] = $this->finding('high', 'failover_test_stale', $id, 'Failover test evidence is older than 90 days.', $bot, ['days_since_test' => $daysSinceTest]);
        }

        if ($timeoutBudget <= 0) {
            $findings[] = $this->finding('high', 'timeout_budget_missing', $id, 'Set a positive timeout budget for provider failover.', $bot);
        } elseif ($timeoutBudget > 30000) {
            $findings[] = $this->finding('medium', 'timeout_budget_too_large', $id, 'Timeout budget above 30 seconds can degrade user experience and increase spend.', $bot);
        }

        if ($retryLimit < 0) {
            $findings[] = $this->finding('high', 'retry_limit_invalid', $id, 'Retry limit must not be negative.', $bot);
        } elseif ($retryLimit > 3) {
            $findings[] = $this->finding('medium', 'retry_limit_high', $id, 'Retry limit above three can amplify provider incidents and cost spikes.', $bot);
        }

        foreach (['data_residency_equivalent', 'safety_policy_equivalent'] as $key) {
            if (!$this->boolValue($bot, $key)) {
                $findings[] = $this->finding('high', $key . '_missing', $id, "Confirm {$key} before routing users to fallback providers.", $bot);
            }
        }

        foreach (['logging_enabled', 'cost_budget_link', 'fallback_runbook', 'evidence_reference'] as $key) {
            if ($key === 'logging_enabled' && !$this->boolValue($bot, $key)) {
                $findings[] = $this->finding('medium', 'logging_disabled', $id, 'Enable routing and provider-event logging for fallback decisions.', $bot);
                continue;
            }

            if ($key !== 'logging_enabled' && $this->stringValue($bot, $key) === '') {
                $findings[] = $this->finding('medium', $key . '_missing', $id, "Attach {$key} for fallback governance evidence.", $bot);
            }
        }

        if ($findings === []) {
            $findings[] = $this->finding('low', 'failover_ready', $id, 'Provider failover readiness evidence is complete.', $bot);
        }

        return $findings;
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @return array<string, int>
     */
    private function summary(array $findings, int $botCount): array
    {
        $summary = [
            'bots' => $botCount,
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
     * @param array<string, mixed> $bot
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function finding(
        string $severity,
        string $state,
        string $target,
        string $message,
        array $bot = [],
        array $extra = []
    ): array {
        return [
            'severity' => $severity,
            'state' => $state,
            'target' => $target,
            'department' => $this->stringValue($bot, 'department'),
            'primary_provider' => $this->stringValue($bot, 'primary_provider'),
            'message' => $message,
        ] + $extra;
    }

    private function daysSince(string $date, DateTimeImmutable $asOf): ?int
    {
        if ($date === '') {
            return null;
        }

        try {
            $testedAt = new DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }

        return (int) $testedAt->diff($asOf)->format('%a');
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
    private function intValue(array $value, string $key): int
    {
        $raw = $value[$key] ?? 0;
        if (is_int($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function boolValue(array $value, string $key): bool
    {
        return filter_var($value[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => strtolower(trim((string) $item)), $value),
            static fn (string $item): bool => $item !== ''
        ));
    }
}
