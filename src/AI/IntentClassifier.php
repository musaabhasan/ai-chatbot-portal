<?php

declare(strict_types=1);

namespace ChatbotPortal\AI;

final class IntentClassifier
{
    public function classify(string $message): IntentProfile
    {
        $text = mb_strtolower($message);
        $signals = [];

        if ($this->matches($text, ['policy', 'procedure', 'deadline', 'requirement', 'regulation', 'compliance', 'handbook'])) {
            $signals[] = 'policy-sensitive';
            return new IntentProfile('policy_question', 'medium', 'openai', true, 0.15, 1100, $signals);
        }

        if ($this->matches($text, ['error', 'login', 'password reset', 'vpn', 'wifi', 'system', 'ticket', 'access'])) {
            $signals[] = 'service-desk';
            return new IntentProfile('it_support', 'medium', 'gemini', true, 0.10, 900, $signals);
        }

        if ($this->matches($text, ['research', 'literature', 'methodology', 'citation', 'scopus', 'survey', 'analysis'])) {
            $signals[] = 'research-workflow';
            return new IntentProfile('research_assistance', 'medium', 'openai', true, 0.25, 1500, $signals);
        }

        if ($this->matches($text, ['code', 'script', 'sql', 'api', 'debug', 'stack trace', 'function'])) {
            $signals[] = 'technical-build';
            return new IntentProfile('technical_build', 'high', 'deepseek', false, 0.10, 1400, $signals);
        }

        if ($this->matches($text, ['cost', 'budget', 'tokens', 'provider', 'latency', 'uptime'])) {
            $signals[] = 'operations';
            return new IntentProfile('operations_query', 'low', null, false, 0.20, 800, $signals);
        }

        return new IntentProfile('general_assistance', 'low', null, true, 0.30, 1000, ['general']);
    }

    /**
     * @param array<int, string> $needles
     */
    private function matches(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
