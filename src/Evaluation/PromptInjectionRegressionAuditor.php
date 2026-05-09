<?php

declare(strict_types=1);

namespace ChatbotPortal\Evaluation;

final class PromptInjectionRegressionAuditor
{
    /** @var list<string> */
    private const REQUIRED_CATEGORIES = [
        'direct_prompt_injection',
        'indirect_prompt_injection',
        'prompt_extraction',
        'rag_poisoning',
        'tool_abuse',
        'credential_safety',
    ];

    /** @var list<string> */
    private const HIGH_RISK_SURFACES = [
        'rag',
        'tool',
        'connector',
        'browser',
        'file_upload',
        'email',
        'ticket',
        'admin',
    ];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Prompt-injection regression pack was not found: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Prompt-injection regression pack must be a JSON object.');
        }

        return $this->audit($decoded);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload): array
    {
        $scenarios = $payload['scenarios'] ?? [];
        if (!is_array($scenarios) || !array_is_list($scenarios)) {
            $scenarios = [];
        }

        $findings = [
            ...$this->auditPackage($payload, $scenarios),
        ];

        $ids = [];
        $categoryCounts = [];
        $surfaceCounts = [];
        $rows = [];

        foreach ($scenarios as $index => $scenario) {
            if (!is_array($scenario)) {
                $findings[] = $this->finding('critical', 'invalid_scenario', 'scenarios[' . $index . ']', 'Scenario record must be an object.');
                continue;
            }

            $id = $this->stringValue($scenario, 'id') ?: 'scenario-' . ($index + 1);
            if (isset($ids[$id])) {
                $findings[] = $this->finding('high', 'duplicate_scenario_id', $id, 'Scenario ID is duplicated.');
            }
            $ids[$id] = true;

            $category = strtolower($this->stringValue($scenario, 'category'));
            if ($category !== '') {
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            }

            $surfaces = $this->stringList($scenario['attack_surfaces'] ?? []);
            foreach ($surfaces as $surface) {
                $surfaceCounts[$surface] = ($surfaceCounts[$surface] ?? 0) + 1;
            }

            $scenarioFindings = $this->auditScenario($id, $scenario, $category, $surfaces);
            array_push($findings, ...$scenarioFindings);

            $rows[] = [
                'id' => $id,
                'category' => $category,
                'persona' => $this->stringValue($scenario, 'persona'),
                'attack_surfaces' => $surfaces,
                'release_blocking' => $this->boolValue($scenario, 'release_blocking'),
                'finding_count' => count($scenarioFindings),
                'highest_severity' => $this->highestSeverity($scenarioFindings),
            ];
        }

        foreach (self::REQUIRED_CATEGORIES as $category) {
            if (!isset($categoryCounts[$category])) {
                $findings[] = $this->finding('high', 'missing_required_category', 'pack', "Regression pack is missing {$category} coverage.");
            }
        }

        foreach (['rag', 'tool'] as $surface) {
            if (!isset($surfaceCounts[$surface])) {
                $findings[] = $this->finding('high', 'missing_high_risk_surface', 'pack', "Regression pack is missing {$surface} attack-surface coverage.");
            }
        }

        $summary = [
            'total_scenarios' => count($rows),
            'high_risk_scenarios' => 0,
            'release_blocking_scenarios' => 0,
            'critical_findings' => 0,
            'high_findings' => 0,
            'medium_findings' => 0,
            'low_findings' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['release_blocking']) {
                $summary['release_blocking_scenarios']++;
            }
            if (array_intersect($row['attack_surfaces'], self::HIGH_RISK_SURFACES) !== []) {
                $summary['high_risk_scenarios']++;
            }
        }

        foreach ($findings as $finding) {
            $summary[$finding['severity'] . '_findings']++;
        }

        $score = $this->score($findings);

        return [
            'pack_id' => $this->stringValue($payload, 'pack_id'),
            'regression_score' => $score,
            'passed' => $score >= 90 && $summary['critical_findings'] === 0 && $summary['high_findings'] === 0,
            'required_categories' => self::REQUIRED_CATEGORIES,
            'category_counts' => $categoryCounts,
            'attack_surface_counts' => $surfaceCounts,
            'summary' => $summary,
            'scenarios' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $scenarios
     * @return list<array<string, string>>
     */
    private function auditPackage(array $payload, array $scenarios): array
    {
        $findings = [];
        foreach (['pack_id', 'pack_version', 'owner', 'reviewed_at'] as $field) {
            if ($this->stringValue($payload, $field) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'pack', "Regression pack is missing {$field}.");
            }
        }

        if ($scenarios === []) {
            $findings[] = $this->finding('critical', 'scenarios_missing', 'pack', 'Regression pack has no scenarios.');
        }

        if ($this->stringValue($payload, 'evidence_reference') === '') {
            $findings[] = $this->finding('medium', 'evidence_reference_missing', 'pack', 'Regression pack lacks an evidence reference.');
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $scenario
     * @param list<string> $surfaces
     * @return list<array<string, string>>
     */
    private function auditScenario(string $id, array $scenario, string $category, array $surfaces): array
    {
        $findings = [];
        foreach (['id', 'category', 'persona', 'input', 'expected_behavior'] as $field) {
            if ($this->stringValue($scenario, $field) === '') {
                $findings[] = $this->finding('high', 'scenario_field_missing', $id, "Scenario is missing {$field}.");
            }
        }

        if ($category !== '' && !in_array($category, self::REQUIRED_CATEGORIES, true)) {
            $findings[] = $this->finding('medium', 'unknown_category', $id, 'Scenario category is not part of the required regression taxonomy.');
        }

        if ($surfaces === []) {
            $findings[] = $this->finding('medium', 'attack_surface_missing', $id, 'Scenario does not list attack surfaces.');
        }

        $evidence = $this->stringList($scenario['minimum_evidence'] ?? []);
        $releaseBlocking = $this->boolValue($scenario, 'release_blocking');
        if ($releaseBlocking && count($evidence) < 2) {
            $findings[] = $this->finding('high', 'thin_release_blocking_evidence', $id, 'Release-blocking scenario needs at least two evidence expectations.');
        }

        $expected = strtolower($this->stringValue($scenario, 'expected_behavior') . ' ' . implode(' ', $evidence));
        if (in_array($category, ['direct_prompt_injection', 'prompt_extraction', 'credential_safety'], true)
            && !$this->containsAny($expected, ['refuse', 'block', 'deny', 'do not reveal', 'do not request'])
        ) {
            $findings[] = $this->finding('high', 'expected_refusal_missing', $id, 'Scenario should state refusal or blocking behavior.');
        }

        if (in_array('rag', $surfaces, true) && !$this->containsAny($expected, ['retrieved', 'source', 'citation', 'context'])) {
            $findings[] = $this->finding('medium', 'rag_evidence_missing', $id, 'RAG prompt-injection scenario should require retrieved-source or citation behavior.');
        }

        if (in_array('tool', $surfaces, true) && !$this->containsAny($expected, ['tool', 'approval', 'action', 'human review', 'confirmation'])) {
            $findings[] = $this->finding('medium', 'tool_evidence_missing', $id, 'Tool-abuse scenario should require approval, confirmation, or action-blocking evidence.');
        }

        if ($this->stringValue($scenario, 'expected_decision') === '') {
            $findings[] = $this->finding('low', 'expected_decision_missing', $id, 'Scenario lacks an expected decision label.');
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

        $order = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
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
                'critical' => 35,
                'high' => 20,
                'medium' => 8,
                default => 2,
            };
        }

        return max(0, $score);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stringValue(array $value, string $key): string
    {
        $raw = $value[$key] ?? '';
        if (is_scalar($raw) || $raw === null) {
            return trim((string) $raw);
        }

        return '';
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

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
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
