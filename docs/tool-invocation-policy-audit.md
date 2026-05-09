# Tool Invocation Policy Audit

Institutional chatbots become materially riskier when they can call tools, create records, send messages, query protected data, or trigger workflows. This audit checks whether each configured tool has least-privilege scope, human approval where needed, logging, data minimization, rollback, emergency stop, and review evidence before production use.

## What The Audit Checks

The auditor reviews each tool policy record for:

- required ownership and environment fields,
- approved production status,
- allowed actions,
- high-impact action detection,
- human approval or dual approval for high-impact actions,
- human review policy reference,
- emergency stop capability,
- rollback or undo instructions,
- logging for sensitive data access,
- data minimization confirmation,
- credential and egress scope,
- required audit event fields,
- execution rate limit,
- escalation owner,
- evidence reference,
- stale review dates.

High-impact actions include record writes, deletions, access changes, external posting, code execution, deployment, workflow triggering, and sensitive-data reads.

## Run The Audit

```bash
php scripts/tool-invocation-policy-audit.php
```

Use a custom policy file:

```bash
php scripts/tool-invocation-policy-audit.php path/to/tool-policy.json
```

Use a fixed reference time for repeatable governance evidence:

```bash
php scripts/tool-invocation-policy-audit.php path/to/tool-policy.json 2026-05-09T00:00:00Z
```

The command returns exit code `0` when the policy is ready and exit code `1` when high-severity findings or a low readiness score should block release.

## Input Shape

```json
{
  "policy_id": "tool-policy-institutional-chatbots",
  "policy_version": "2026.05",
  "reviewed_at": "2026-05-08T12:00:00Z",
  "owner": "ai-governance-board",
  "evidence_package_hash": "3a0e4f1dc5b71f0d3ab3e18d5a65ed8519f0971a80da15e7f2c59f648c3a661a",
  "tools": [
    {
      "tool_id": "tool-advisor-ticket-create",
      "tool_name": "Student support ticket creation",
      "bot_id": "academic-advisor",
      "owner": "student-services-platform-owner",
      "environment": "production",
      "status": "approved",
      "permission_level": "write",
      "risk_level": "high",
      "data_classification": "student",
      "actions": ["write_record", "trigger_workflow"],
      "approval_mode": "human_required",
      "human_review_policy": "HRP-STUDENT-SUPPORT-2026",
      "logging_enabled": true,
      "audit_event_fields": ["conversation_id", "user_id_hash", "tool_id", "action", "approver", "result", "timestamp"],
      "data_minimization_confirmed": true,
      "credential_scope": "ticket:create:student-support",
      "egress_scope": "support-case-api",
      "rollback_plan": "Close mistaken ticket, add correction note, notify support lead, and preserve audit record.",
      "emergency_stop": true,
      "rate_limit": "10 executions per bot per minute",
      "escalation_owner": "student-services-duty-manager",
      "last_reviewed": "2026-05-08T12:00:00Z",
      "evidence_reference": "evidence/tool-policy/advisor-ticket-create-2026-05"
    }
  ]
}
```

## Finding Types

| Finding | Severity | Meaning |
| --- | --- | --- |
| `package_field_missing` | High | Policy package lacks required package metadata |
| `tools_missing` | High | Policy package has no tools |
| `package_hash_missing` | Medium | Policy package lacks evidence package hash |
| `invalid_tool_record` | High | Tool record is not a JSON object |
| `tool_field_missing` | High | Tool record lacks ownership, environment, permission, risk, or classification metadata |
| `actions_missing` | High | Tool record has no allowed actions |
| `environment_unknown` | Medium | Tool environment label is not recognized |
| `production_tool_not_approved` | High | Production tool is not approved or active |
| `human_approval_missing` | High | High-impact tool does not require human approval |
| `human_review_policy_missing` | High | High-impact tool lacks a review policy reference |
| `emergency_stop_missing` | High | High-impact tool lacks emergency stop capability |
| `rollback_plan_missing` | High | High-impact tool lacks rollback or undo instructions |
| `sensitive_logging_disabled` | High | Sensitive-data tool lacks audit logging |
| `data_minimization_missing` | High | Sensitive-data tool lacks minimization confirmation |
| `credential_scope_too_broad` | Medium or High | Credential scope violates least privilege |
| `egress_scope_too_broad` | High | Sensitive or high-impact tool has broad egress |
| `audit_event_fields_missing` | Medium | Logging is enabled without required audit fields |
| `rate_limit_missing` | Medium | Tool lacks execution budget or rate limit |
| `escalation_owner_missing` | Medium | High-impact tool has no escalation owner |
| `evidence_reference_missing` | Medium | Tool has no linked evidence reference |
| `review_date_missing` | Medium | Tool lacks last review date |
| `review_stale_high_impact` | High | High-impact tool review is older than 90 days |
| `review_stale` | Medium | Tool review is older than 180 days |

## Operating Model

Run the audit before:

- enabling a tool for any production bot,
- changing tool permissions or credentials,
- adding a new department or user group to an existing tool,
- allowing a chatbot to write records, send notifications, or trigger workflows,
- closing a security review for tool-enabled chatbot releases,
- producing audit evidence for high-risk or student-facing deployments.

Preserve the JSON output with the release package. It links tool ownership, approval mode, audit fields, credential scope, data classification, rollback readiness, emergency stop capability, and review freshness to a single evidence artifact.
