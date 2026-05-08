<?php

declare(strict_types=1);

namespace ChatbotPortal\Evaluation;

final class EvaluationRunner
{
    public function runFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Evaluation file was not found.');
        }

        $scenarios = json_decode(file_get_contents($path) ?: '[]', true);
        if (!is_array($scenarios)) {
            throw new \RuntimeException('Evaluation file must contain a JSON array.');
        }

        $results = [];
        foreach ($scenarios as $scenario) {
            if (!is_array($scenario)) {
                continue;
            }
            $results[] = $this->validateScenario($scenario);
        }

        $passed = count(array_filter($results, static fn (array $result): bool => $result['passed']));

        return [
            'total' => count($results),
            'passed' => $passed,
            'score' => count($results) === 0 ? 0.0 : round($passed / count($results), 4),
            'results' => $results,
        ];
    }

    private function validateScenario(array $scenario): array
    {
        $required = ['id', 'persona', 'input', 'expected_behavior', 'risk_tags', 'minimum_evidence'];
        $missing = [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $scenario) || $scenario[$field] === '' || $scenario[$field] === []) {
                $missing[] = $field;
            }
        }

        return [
            'id' => (string) ($scenario['id'] ?? 'unknown'),
            'passed' => $missing === [],
            'missing' => $missing,
            'risk_tags' => $scenario['risk_tags'] ?? [],
        ];
    }
}
