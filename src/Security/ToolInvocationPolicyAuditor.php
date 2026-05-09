<?php

declare(strict_types=1);

namespace ChatbotPortal\Security;

use DateTimeImmutable;
use Throwable;

final class ToolInvocationPolicyAuditor
{
    /** @var list<string> */
    private const HIGH_IMPACT_ACTIONS = [
        'send_email',
        'external_post',
        'write_record',
        'delete_record',
        'change_access',
        'approve_request',
        'run_code',
        'deploy_change',
        'merge_pull_request',
        'read_sensitive_data',
        'trigger_workflow',
    ];

    /** @var list<string> */
    private const SENSITIVE_CLASSIFICATIONS = [
        'confidential',
        'restricted',
        'regulated',
        'student',
        'health',
        'financial',
        'personal',
        'credential',
    ];

    /** @var list<string> */
    private const BROAD_VALUES = ['*', 'all', 'any', 'global', 'internet', 'unrestricted', 'full'];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function audit(array $payload, ?DateTimeImmutable $referenceTime = null): array
    {
        $referenceTime ??= new DateTimeImmutable('now');
        $tools = $payload['tools'] ?? [];
        if (!is_array($tools) || !array_is_list($tools)) {
            $tools = [];
        }

        $findings = [
            ...$this->auditPackage($payload, $tools),
        ];

        $rows = [];
        foreach ($tools as $index => $tool) {
            if (!is_array($tool)) {
                $findings[] = $this->finding('high', 'invalid_tool_record', 'tools[' . $index . ']', 'Tool policy record must be an object.');
                continue;
            }

            $toolId = $this->toolTarget($tool, $index);
            $toolFindings = $this->auditTool($toolId, $tool, $referenceTime);
            array_push($findings, ...$toolFindings);

            $rows[] = [
                'tool_id' => $toolId,
                'tool_name' => $this->stringValue($tool, 'tool_name'),
                'bot_id' => $this->stringValue($tool, 'bot_id'),
                'environment' => strtolower($this->stringValue($tool, 'environment')),
                'permission_level' => strtolower($this->stringValue($tool, 'permission_level')),
                'risk_level' => strtolower($this->stringValue($tool, 'risk_level')),
                'actions' => $this->stringList($tool['actions'] ?? []),
                'finding_count' => count($toolFindings),
                'highest_severity' => $this->highestSeverity($toolFindings),
            ];
        }

        $summary = [
            'total_tools' => count($tools),
            'ready_tools' => 0,
            'warning_tools' => 0,
            'blocked_tools' => 0,
            'high_findings' => 0,
            'medium_findings' => 0,
            'low_findings' => 0,
            'high_impact_tools' => 0,
            'sensitive_data_tools' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['highest_severity'] === 'high') {
                $summary['blocked_tools']++;
            } elseif ($row['highest_severity'] === 'medium') {
                $summary['warning_tools']++;
            } else {
                $summary['ready_tools']++;
            }
        }

        foreach ($tools as $tool) {
            if (!is_array($tool)) {
                continue;
            }
            if ($this->isHighImpact($tool)) {
                $summary['high_impact_tools']++;
            }
            if ($this->isSensitive($tool)) {
                $summary['sensitive_data_tools']++;
            }
        }

        foreach ($findings as $finding) {
            $summary[$finding['severity'] . '_findings']++;
        }

        $score = $this->score($findings);

        return [
            'policy_id' => $this->stringValue($payload, 'policy_id'),
            'reference_time' => $referenceTime->format(DATE_ATOM),
            'tool_policy_score' => $score,
            'passed' => $score >= 90 && $summary['high_findings'] === 0,
            'summary' => $summary,
            'tools' => $rows,
            'findings' => $findings,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<mixed> $tools
     * @return list<array<string, string>>
     */
    private function auditPackage(array $payload, array $tools): array
    {
        $findings = [];
        foreach (['policy_id', 'policy_version', 'reviewed_at', 'owner'] as $field) {
            if ($this->stringValue($payload, $field) === '') {
                $findings[] = $this->finding('high', 'package_field_missing', 'package', "Tool policy package is missing {$field}.");
            }
        }

        if ($tools === []) {
            $findings[] = $this->finding('high', 'tools_missing', 'package', 'Tool policy package has no tool records.');
        }

        if ($this->stringValue($payload, 'evidence_package_hash') === '') {
            $findings[] = $this->finding('medium', 'package_hash_missing', 'package', 'Tool policy package lacks evidence package hash.');
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $tool
     * @return list<array<string, string>>
     */
    private function auditTool(string $toolId, array $tool, DateTimeImmutable $referenceTime): array
    {
        $findings = [];
        $highImpact = $this->isHighImpact($tool);
        $sensitive = $this->isSensitive($tool);
        $environment = strtolower($this->stringValue($tool, 'environment'));
        $approvalMode = strtolower($this->stringValue($tool, 'approval_mode'));
        $status = strtolower($this->stringValue($tool, 'status'));

        foreach (['tool_id', 'tool_name', 'owner', 'bot_id', 'environment', 'permission_level', 'risk_level', 'data_classification'] as $field) {
            if ($this->stringValue($tool, $field) === '') {
                $findings[] = $this->finding('high', 'tool_field_missing', $toolId, "Tool policy record is missing {$field}.");
            }
        }

        if ($this->stringList($tool['actions'] ?? []) === []) {
            $findings[] = $this->finding('high', 'actions_missing', $toolId, 'Tool policy record does not define allowed actions.');
        }

        if ($environment !== '' && !in_array($environment, ['development', 'test', 'staging', 'production'], true)) {
            $findings[] = $this->finding('medium', 'environment_unknown', $toolId, 'Tool policy record uses an unknown environment label.');
        }

        if ($environment === 'production' && !in_array($status, ['approved', 'active'], true)) {
            $findings[] = $this->finding('high', 'production_tool_not_approved', $toolId, 'Production tool is not marked approved or active.');
        }

        if ($highImpact && !$this->requiresHumanApproval($approvalMode)) {
            $findings[] = $this->finding('high', 'human_approval_missing', $toolId, 'High-impact tool actions require human approval or dual approval.');
        }

        if ($highImpact && $this->stringValue($tool, 'human_review_policy') === '') {
            $findings[] = $this->finding('high', 'human_review_policy_missing', $toolId, 'High-impact tool record lacks a human review policy reference.');
        }

        if ($highImpact && !$this->boolValue($tool, 'emergency_stop')) {
            $findings[] = $this->finding('high', 'emergency_stop_missing', $toolId, 'High-impact tool lacks confirmed emergency stop capability.');
        }

        if ($highImpact && $this->stringValue($tool, 'rollback_plan') === '') {
            $findings[] = $this->finding('high', 'rollback_plan_missing', $toolId, 'High-impact tool lacks rollback or undo instructions.');
        }

        if ($sensitive && !$this->boolValue($tool, 'logging_enabled')) {
            $findings[] = $this->finding('high', 'sensitive_logging_disabled', $toolId, 'Sensitive-data tool lacks audit logging.');
        }

        if ($sensitive && !$this->boolValue($tool, 'data_minimization_confirmed')) {
            $findings[] = $this->finding('high', 'data_minimization_missing', $toolId, 'Sensitive-data tool lacks data minimization confirmation.');
        }

        if ($this->hasBroadScope($this->stringValue($tool, 'credential_scope'))) {
            $findings[] = $this->finding($highImpact ? 'high' : 'medium', 'credential_scope_too_broad', $toolId, 'Tool credential scope is too broad for least-privilege operation.');
        }

        if ($this->hasBroadScope($this->stringValue($tool, 'egress_scope')) && ($highImpact || $sensitive)) {
            $findings[] = $this->finding('high', 'egress_scope_too_broad', $toolId, 'High-impact or sensitive tool allows overly broad egress.');
        }

        if ($this->boolValue($tool, 'logging_enabled') && $this->stringList($tool['audit_event_fields'] ?? []) === []) {
            $findings[] = $this->finding('medium', 'audit_event_fields_missing', $toolId, 'Tool logging is enabled but required audit fields are not listed.');
        }

        if ($this->stringValue($tool, 'rate_limit') === '') {
            $findings[] = $this->finding('medium', 'rate_limit_missing', $toolId, 'Tool policy lacks a rate limit or execution budget.');
        }

        if ($highImpact && $this->stringValue($tool, 'escalation_owner') === '') {
            $findings[] = $this->finding('medium', 'escalation_owner_missing', $toolId, 'High-impact tool lacks an escalation owner.');
        }

        if ($this->stringValue($tool, 'evidence_reference') === '') {
            $findings[] = $this->finding('medium', 'evidence_reference_missing', $toolId, 'Tool policy record lacks evidence reference.');
        }

        $daysSinceReview = $this->daysSinceReview($tool, $referenceTime);
        if ($daysSinceReview === null) {
            $findings[] = $this->finding('medium', 'review_date_missing', $toolId, 'Tool policy record lacks last_reviewed evidence.');
        } elseif ($highImpact && $daysSinceReview > 90) {
            $findings[] = $this->finding('high', 'review_stale_high_impact', $toolId, 'High-impact tool review is older than 90 days.');
        } elseif ($daysSinceReview > 180) {
            $findings[] = $this->finding('medium', 'review_stale', $toolId, 'Tool policy review is older than 180 days.');
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function toolTarget(array $tool, int $index): string
    {
        return $this->stringValue($tool, 'tool_id') ?: 'tool-' . ($index + 1);
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function isHighImpact(array $tool): bool
    {
        $riskLevel = strtolower($this->stringValue($tool, 'risk_level'));
        $permissionLevel = strtolower($this->stringValue($tool, 'permission_level'));
        if (in_array($riskLevel, ['high', 'critical'], true)) {
            return true;
        }

        if (in_array($permissionLevel, ['write', 'admin', 'privileged', 'owner', 'delete'], true)) {
            return true;
        }

        foreach ($this->stringList($tool['actions'] ?? []) as $action) {
            if (in_array($action, self::HIGH_IMPACT_ACTIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function isSensitive(array $tool): bool
    {
        $classification = strtolower($this->stringValue($tool, 'data_classification'));
        return in_array($classification, self::SENSITIVE_CLASSIFICATIONS, true);
    }

    private function requiresHumanApproval(string $approvalMode): bool
    {
        return in_array($approvalMode, ['human', 'human_required', 'approval_required', 'dual_approval', 'manual_review'], true);
    }

    private function hasBroadScope(string $scope): bool
    {
        return in_array(strtolower($scope), self::BROAD_VALUES, true);
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function daysSinceReview(array $tool, DateTimeImmutable $referenceTime): ?int
    {
        $lastReviewed = $this->stringValue($tool, 'last_reviewed');
        if ($lastReviewed === '') {
            return null;
        }

        try {
            $reviewedAt = new DateTimeImmutable($lastReviewed);
        } catch (Throwable) {
            return null;
        }

        return (int) $reviewedAt->diff($referenceTime)->format('%a');
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
