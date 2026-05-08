<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

final class PromptFirewall
{
    private const BLOCK_PATTERNS = [
        'prompt_extraction' => '/(ignore|bypass|override).{0,40}(previous|system|developer|instruction|policy)/iu',
        'secret_request' => '/(api[_ -]?key|token|password|secret|private[_ -]?key|credential|mfa code|recovery key)/iu',
        'data_exfiltration' => '/(dump|export|exfiltrate|leak|reveal).{0,60}(database|users|records|emails|logs|context)/iu',
        'unsafe_execution' => '/(disable security|remove guardrail|turn off logging|delete audit|drop table|wipe logs)/iu',
    ];

    private const REVIEW_PATTERNS = [
        'legal_or_regulatory' => '/(legal advice|regulatory|lawsuit|court|visa|disciplinary|appeal)/iu',
        'personal_data' => '/(student id|passport|national id|medical|financial hardship|disability|grades)/iu',
        'high_impact_decision' => '/(admission decision|scholarship decision|termination|expulsion|eligibility)/iu',
    ];

    public function inspect(string $message): FirewallDecision
    {
        $flags = [];
        foreach (self::BLOCK_PATTERNS as $flag => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                $flags[] = $flag;
            }
        }

        if ($flags !== []) {
            return new FirewallDecision(
                false,
                'blocked',
                $flags,
                'This request was blocked by the prompt firewall because it appears to target protected instructions, credentials, private data, or audit controls.'
            );
        }

        foreach (self::REVIEW_PATTERNS as $flag => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                $flags[] = $flag;
            }
        }

        if ($flags !== []) {
            return new FirewallDecision(true, 'review', $flags, 'Proceed with caution and prefer policy-grounded, non-decisional guidance.');
        }

        return new FirewallDecision(true, 'normal', [], 'Allowed.');
    }
}
