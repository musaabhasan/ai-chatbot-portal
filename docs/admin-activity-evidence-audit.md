# Admin Activity Evidence Audit

Administrative changes can alter model behavior, data access, costs, safety posture, and user experience. This audit checks whether exported admin activity evidence is complete enough for governance review, incident investigation, and internal audit.

## What The Audit Checks

The auditor reviews:

- export package identity, period, generator, and package hash,
- event IDs, event types, actors, roles, targets, and timestamps,
- evidence hashes for each event,
- approval references for high-impact admin activity,
- reviewer evidence for high-impact changes,
- before and after hashes for configuration changes,
- redaction completion when exported evidence contains sensitive data,
- concise reason statements for later reconstruction.

High-impact events include provider credential changes, provider enablement, RBAC changes, MFA resets, prompt approvals, prompt rollbacks, RAG source publication, cost-limit changes, audit-retention changes, and bot decommissioning.

## Run The Audit

```bash
php scripts/admin-activity-evidence-audit.php
```

Use a custom export:

```bash
php scripts/admin-activity-evidence-audit.php path/to/admin-activity-export.json
```

The command returns exit code `0` when the export is audit-ready and exit code `1` when high-severity findings or a low evidence-readiness score should block closure.

## Event Shape

```json
{
  "event_id": "adm-2026-0001",
  "event_type": "prompt_approved",
  "actor": "department-admin",
  "actor_role": "department_admin",
  "target": "academic-advisor:advisor-v12",
  "occurred_at": "2026-05-02T08:30:00Z",
  "approval_reference": "APR-2026-044",
  "reviewer": "learning-platform-owner",
  "risk_level": "high",
  "before_hash": "5d41402abc4b2a76b9719d911017c592",
  "after_hash": "7d793037a0760186574b0282f2f435e7",
  "evidence_hash": "f1d2d2f924e986ac86fdf7b36c94bcdf32beec15a815c2496b348f3ff47d1929",
  "contains_sensitive_data": "no",
  "redaction_status": "not_required",
  "reason": "Approved citation tightening for academic policy answers."
}
```

## Finding Types

| Finding | Severity | Meaning |
| --- | --- | --- |
| `package_field_missing` | High | Export package lacks identity, period, generator, or timestamp metadata |
| `events_missing` | High | Export contains no admin activity events |
| `package_hash_missing` | Medium | Export lacks package-level SHA-256 evidence |
| `period_invalid` | High | Export period is logically invalid |
| `invalid_event` | High | Event entry is not a structured object |
| `duplicate_event_id` | Medium | Event ID appears more than once |
| `event_field_missing` | High | Event lacks required actor, target, type, or timestamp context |
| `event_timestamp_invalid` | Medium | Event timestamp cannot be parsed |
| `evidence_hash_missing` | Medium | Event lacks hashable evidence reference |
| `approval_reference_missing` | High | High-impact event lacks approval evidence |
| `reviewer_missing` | Medium | High-impact event lacks reviewer evidence |
| `config_delta_hash_missing` | Medium | Configuration event lacks before/after hash evidence |
| `redaction_missing` | High | Sensitive exported evidence is not redacted |
| `reason_missing` | Low | Event lacks a concise reason |

## Operating Use

Run this audit before:

- closing a monthly governance review,
- investigating prompt, provider, RAG, RBAC, branding, cost, or retention changes,
- exporting evidence to compliance, privacy, or internal audit,
- closing an incident involving administrative configuration,
- approving a high-impact bot release or decommissioning action.

Preserve the audit report with the exported admin activity package, approval records, configuration hashes, reviewer decisions, redaction evidence, and incident or governance references.
