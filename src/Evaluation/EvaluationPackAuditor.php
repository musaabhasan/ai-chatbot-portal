<?php

declare(strict_types=1);

namespace ChatbotPortal\Evaluation;

final class EvaluationPackAuditor
{
    private const REQUIRED_TAGS = [
        'prompt_injection',
        'credential_safety',
        'citation_required',
        'policy',
        'research',
    ];

    private const HIGH_IMPACT_TERMS = [
        'admission',
        'scholarship',
        'expulsion',
        'disciplinary',
        'legal',
        'medical',
        'financial',
        'password',
        'mfa',
        'system prompt',
    ];

    /**
     * @return array<string, mixed>
     */
    public function auditFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Evaluation file was not found.');
        }

        $decoded = json_decode(file_get_contents($path) ?: '[]', true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Evaluation file must contain a JSON array.');
        }

        return $this->audit($decoded);
    }

    /**
     * @param array<int, mixed> $scenarios
     * @return array<string, mixed>
     */
    public function audit(array $scenarios): array
    {
        $scenarioRows = [];
        $tags = [];
        $ids = [];
        $findings = [];

        foreach ($scenarios as $index => $scenario) {
            if (!is_array($scenario)) {
                $findings[] = $this->finding('critical', 'invalid_scenario', "Scenario at index {$index} is not an object.");
                continue;
            }

            $id = (string) ($scenario['id'] ?? "scenario-{$index}");
            if (isset($ids[$id])) {
                $findings[] = $this->finding('high', 'duplicate_id', "Scenario id {$id} is duplicated.");
            }
            $ids[$id] = true;

            $riskTags = $this->stringList($scenario['risk_tags'] ?? []);
            foreach ($riskTags as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }

            $minimumEvidence = $this->stringList($scenario['minimum_evidence'] ?? []);
            $riskLevel = $this->riskLevel($scenario, $riskTags);
            if ($riskLevel !== 'normal' && count($minimumEvidence) < 2) {
                $findings[] = $this->finding(
                    'medium',
                    'thin_high_impact_evidence',
                    "Scenario {$id} is {$riskLevel} but has fewer than two minimum evidence expectations."
                );
            }

            if (in_array('citation_required', $riskTags, true) && !$this->mentionsCitation($scenario)) {
                $findings[] = $this->finding('medium', 'citation_gap', "Scenario {$id} requires citations but does not state citation behavior clearly.");
            }

            $scenarioRows[] = [
                'id' => $id,
                'persona' => (string) ($scenario['persona'] ?? ''),
                'risk_level' => $riskLevel,
                'risk_tags' => $riskTags,
                'minimum_evidence_count' => count($minimumEvidence),
            ];
        }

        foreach (self::REQUIRED_TAGS as $tag) {
            if (!isset($tags[$tag])) {
                $findings[] = $this->finding('high', 'missing_required_coverage', "Evaluation pack is missing {$tag} coverage.");
            }
        }

        $score = $this->score($findings);

        return [
            'total_scenarios' => count($scenarioRows),
            'coverage_score' => $score,
            'passed' => $score >= 90 && !$this->hasSeverity($findings, 'high'),
            'required_tags' => self::REQUIRED_TAGS,
            'tag_counts' => $tags,
            'scenarios' => $scenarioRows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<int, string> $riskTags
     */
    private function riskLevel(array $scenario, array $riskTags): string
    {
        $text = strtolower(implode(' ', [
            (string) ($scenario['input'] ?? ''),
            (string) ($scenario['expected_behavior'] ?? ''),
            implode(' ', $riskTags),
        ]));

        foreach (self::HIGH_IMPACT_TERMS as $term) {
            if (str_contains($text, $term)) {
                return 'high';
            }
        }

        return $riskTags === [] ? 'normal' : 'review';
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function mentionsCitation(array $scenario): bool
    {
        $text = strtolower((string) ($scenario['expected_behavior'] ?? '') . ' ' . implode(' ', $this->stringList($scenario['minimum_evidence'] ?? [])));
        return str_contains($text, 'cite') || str_contains($text, 'citation') || str_contains($text, 'source');
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value)));
    }

    /**
     * @param array<int, array<string, string>> $findings
     */
    private function score(array $findings): int
    {
        $score = 100;
        foreach ($findings as $finding) {
            $score -= match ($finding['severity']) {
                'critical' => 35,
                'high' => 20,
                'medium' => 10,
                default => 3,
            };
        }

        return max(0, $score);
    }

    /**
     * @param array<int, array<string, string>> $findings
     */
    private function hasSeverity(array $findings, string $minimum): bool
    {
        $order = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $threshold = $order[$minimum];

        foreach ($findings as $finding) {
            if (($order[$finding['severity']] ?? 0) >= $threshold) {
                return true;
            }
        }

        return false;
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
