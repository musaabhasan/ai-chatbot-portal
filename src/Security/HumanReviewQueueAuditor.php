<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class HumanReviewQueueAuditor
{
    /**
     * @param list<array<string, mixed>> $queueItems
     * @return array<string, mixed>
     */
    public function audit(array $queueItems, ?DateTimeImmutable $referenceTime = null): array
    {
        $referenceTime ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $findings = [];
        $rows = [];
        $summary = [
            'total_items' => count($queueItems),
            'ready_items' => 0,
            'blocked_items' => 0,
            'warning_items' => 0,
            'high_findings' => 0,
            'medium_findings' => 0,
            'low_findings' => 0,
        ];

        foreach ($queueItems as $index => $item) {
            $conversationId = $this->stringValue($item, 'conversation_id') ?: 'row-' . ($index + 1);
            $itemFindings = $this->auditItem($conversationId, $item, $referenceTime);
            $highestSeverity = $this->highestSeverity($itemFindings);

            foreach ($itemFindings as $finding) {
                $findings[] = $finding;
                $summary[$finding['severity'] . '_findings']++;
            }

            if ($highestSeverity === 'high') {
                $summary['blocked_items']++;
            } elseif ($highestSeverity === 'medium') {
                $summary['warning_items']++;
            } else {
                $summary['ready_items']++;
            }

            $rows[] = [
                'conversation_id' => $conversationId,
                'bot_id' => $this->stringValue($item, 'bot_id'),
                'department' => $this->stringValue($item, 'department'),
                'risk_level' => $this->stringValue($item, 'risk_level'),
                'status' => $this->stringValue($item, 'status'),
                'assigned_reviewer' => $this->stringValue($item, 'assigned_reviewer'),
                'finding_count' => count($itemFindings),
                'highest_severity' => $highestSeverity,
            ];
        }

        $score = $this->score($findings);

        return [
            'reference_time' => $referenceTime->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'queue_readiness_score' => $score,
            'passed' => $score >= 90 && $summary['high_findings'] === 0,
            'summary' => $summary,
            'items' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array<string, string>>
     */
    private function auditItem(string $conversationId, array $item, DateTimeImmutable $referenceTime): array
    {
        $findings = [];
        $status = strtolower($this->stringValue($item, 'status'));
        $riskLevel = strtolower($this->stringValue($item, 'risk_level'));
        $classification = strtolower($this->stringValue($item, 'data_classification'));
        $reviewerDecision = strtolower($this->stringValue($item, 'reviewer_decision'));
        $highRisk = in_array($riskLevel, ['high', 'critical'], true);
        $sensitive = in_array($classification, ['regulated', 'restricted', 'confidential', 'student', 'health'], true);
        $openStatuses = ['queued', 'assigned', 'in_review', 'escalated'];

        if (!in_array($status, ['queued', 'assigned', 'in_review', 'escalated', 'closed', 'released', 'blocked'], true)) {
            $findings[] = $this->finding(
                'high',
                'invalid_status',
                $conversationId,
                'Review queue item has an unknown workflow status.'
            );
        }

        if (in_array($status, $openStatuses, true) && $this->stringValue($item, 'assigned_reviewer') === '') {
            $findings[] = $this->finding(
                $highRisk ? 'high' : 'medium',
                'reviewer_assignment_gap',
                $conversationId,
                'Open review queue item is missing an assigned reviewer.'
            );
        }

        $slaDueAt = $this->dateValue($item, 'sla_due_at');
        if (in_array($status, $openStatuses, true) && $slaDueAt === null) {
            $findings[] = $this->finding(
                'medium',
                'sla_missing',
                $conversationId,
                'Open review queue item is missing an SLA due date.'
            );
        } elseif (in_array($status, $openStatuses, true) && $slaDueAt < $referenceTime) {
            $findings[] = $this->finding(
                $highRisk ? 'high' : 'medium',
                'sla_overdue',
                $conversationId,
                'Open review queue item is past its SLA due date.'
            );
        }

        if ($highRisk && $this->stringValue($item, 'route_reason') === '') {
            $findings[] = $this->finding(
                'high',
                'route_reason_gap',
                $conversationId,
                'High-risk review queue item is missing the trigger or route reason.'
            );
        }

        if ($sensitive && strtolower($this->stringValue($item, 'redaction_status')) !== 'completed') {
            $findings[] = $this->finding(
                'high',
                'redaction_gap',
                $conversationId,
                'Sensitive review queue item lacks completed redaction before reviewer handling.'
            );
        }

        if ($this->truthy($item, 'escalation_required') && $this->stringValue($item, 'escalation_owner') === '') {
            $findings[] = $this->finding(
                'high',
                'escalation_owner_gap',
                $conversationId,
                'Escalated queue item has no named escalation owner.'
            );
        }

        if ($this->stringValue($item, 'evidence_package_hash') === '') {
            $findings[] = $this->finding(
                'medium',
                'evidence_hash_gap',
                $conversationId,
                'Review record has no evidence package hash for later audit verification.'
            );
        }

        if ($this->stringValue($item, 'model_version') === '' || $this->stringValue($item, 'prompt_version') === '') {
            $findings[] = $this->finding(
                'medium',
                'version_context_gap',
                $conversationId,
                'Review record is missing model or prompt version context.'
            );
        }

        if ($this->truthy($item, 'rag_used') && $this->stringValue($item, 'knowledge_base_version') === '') {
            $findings[] = $this->finding(
                'medium',
                'rag_context_gap',
                $conversationId,
                'RAG-assisted conversation review is missing knowledge-base version context.'
            );
        }

        if (in_array($status, ['closed', 'released', 'blocked'], true)) {
            if ($reviewerDecision === '') {
                $findings[] = $this->finding(
                    'high',
                    'decision_gap',
                    $conversationId,
                    'Closed review queue item has no reviewer decision.'
                );
            }

            if ($this->stringValue($item, 'resolution_notes') === '') {
                $findings[] = $this->finding(
                    'medium',
                    'resolution_notes_gap',
                    $conversationId,
                    'Closed review queue item has no concise resolution note.'
                );
            }

            if ($this->dateValue($item, 'reviewed_at') === null) {
                $findings[] = $this->finding(
                    'medium',
                    'review_timestamp_gap',
                    $conversationId,
                    'Closed review queue item is missing reviewer completion time.'
                );
            }
        }

        if ($this->stringValue($item, 'user_impact') === '') {
            $findings[] = $this->finding(
                'low',
                'user_impact_gap',
                $conversationId,
                'Review queue item does not describe user or operational impact.'
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
                'medium' => 8,
                default => 2,
            };
        }

        return max(0, $score);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function stringValue(array $item, string $key): string
    {
        $value = $item[$key] ?? '';
        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateValue(array $item, string $key): ?DateTimeImmutable
    {
        $value = $this->stringValue($item, $key);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function truthy(array $item, string $key): bool
    {
        return in_array(strtolower($this->stringValue($item, $key)), ['1', 'true', 'yes', 'y', 'required', 'complete', 'completed'], true);
    }

    /**
     * @return array<string, string>
     */
    private function finding(string $severity, string $type, string $conversationId, string $message): array
    {
        return [
            'severity' => $severity,
            'type' => $type,
            'conversation_id' => $conversationId,
            'message' => $message,
        ];
    }
}
