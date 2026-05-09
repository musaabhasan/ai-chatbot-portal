# Human Review Queue Audit

Human review is a control boundary for institutional chatbots. It is most useful when it is measurable: high-risk conversations need routing evidence, assigned reviewers, service-level targets, redaction, version context, escalation ownership, and review decisions that can be reconstructed later.

This audit checks whether routed conversations are ready for review, escalation, release, or closure.

## What The Audit Checks

The auditor reviews each queue item for:

- valid workflow status,
- reviewer assignment for open items,
- SLA due date and overdue status,
- route reason for high-risk conversations,
- redaction completion for sensitive conversations,
- escalation owner when escalation is required,
- hash evidence for the review package,
- model and prompt version context,
- knowledge-base version context when RAG was used,
- decision, resolution notes, and completion timestamp for closed items,
- user or operational impact notes.

## Run The Audit

```bash
php scripts/human-review-queue-audit.php
```

Use a custom queue file:

```bash
php scripts/human-review-queue-audit.php path/to/human-review-queue.json
```

Use a fixed reference time for repeatable governance evidence:

```bash
php scripts/human-review-queue-audit.php path/to/human-review-queue.json 2026-06-01T12:00:00Z
```

The command returns exit code `0` when the review queue is ready and exit code `1` when high-severity findings or a low readiness score should block release or closure.

## Queue Item Shape

```json
{
  "conversation_id": "conv-academic-advisor-2026-001",
  "bot_id": "academic-advisor",
  "department": "learning-services",
  "risk_level": "high",
  "data_classification": "student",
  "route_reason": "Student-facing answer included conflicting academic policy interpretation.",
  "status": "in_review",
  "assigned_reviewer": "learning-policy-reviewer",
  "sla_due_at": "2026-06-02T12:00:00Z",
  "created_at": "2026-06-01T08:10:00Z",
  "reviewed_at": "",
  "escalation_required": "yes",
  "escalation_owner": "academic-governance-lead",
  "redaction_status": "completed",
  "evidence_package_hash": "0bbf08d5a02d7e7a4d34c2f1f97d9e36e6b82a8e6d3de81655f23e2d7be7c121",
  "model_version": "gpt-4.1-mini-2026-04",
  "prompt_version": "advisor-v12",
  "rag_used": "yes",
  "knowledge_base_version": "kb-policy-2026-05-28",
  "reviewer_decision": "",
  "resolution_notes": "",
  "user_impact": "Potential policy confusion for one student; no automated decision was taken."
}
```

## Finding Types

| Finding | Severity | Meaning |
| --- | --- | --- |
| `invalid_status` | High | Queue item uses an unknown workflow status |
| `reviewer_assignment_gap` | Medium or High | Open item lacks an assigned reviewer |
| `sla_missing` | Medium | Open item has no SLA due date |
| `sla_overdue` | Medium or High | Open item is past its SLA due date |
| `route_reason_gap` | High | High-risk item lacks routing evidence |
| `redaction_gap` | High | Sensitive item lacks completed redaction |
| `escalation_owner_gap` | High | Escalated item lacks an accountable owner |
| `evidence_hash_gap` | Medium | Review package has no hash evidence |
| `version_context_gap` | Medium | Model or prompt version is missing |
| `rag_context_gap` | Medium | RAG-assisted review lacks knowledge-base version |
| `decision_gap` | High | Closed item has no reviewer decision |
| `resolution_notes_gap` | Medium | Closed item lacks a concise resolution note |
| `review_timestamp_gap` | Medium | Closed item has no completion timestamp |
| `user_impact_gap` | Low | Impact statement is missing |

## Operating Model

Use the audit before:

- releasing a blocked answer back to the user,
- closing a high-risk conversation review,
- submitting evidence to compliance or internal audit,
- changing reviewer routing rules,
- reporting service-level performance for safety reviews.

For regulated, student-facing, health, or confidential workflows, preserve the JSON report with the conversation evidence package. This links the reviewer, trigger, model version, prompt version, knowledge-base version, decision, and closure notes to a single auditable record.
