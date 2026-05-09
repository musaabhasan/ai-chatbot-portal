# Conversation Retention Policy Audit

The conversation retention policy audit is an executable governance check for chatbot data stores that hold prompts, outputs, conversation logs, RAG excerpts, embeddings, tool-call records, audit exports, telemetry, attachments, and provider payloads. It helps administrators prove that retained data has an owner, purpose, review date, retention limit, redaction path, deletion support, legal-hold handling, and least-privilege access.

## Why This Matters

Chatbot platforms often retain more data than teams expect: raw prompts, generated outputs, retrieved passages, citations, tool-call traces, review exports, cost telemetry, provider payloads, and support packages. These records are useful for quality assurance and incident review, but they can also contain personal data, sensitive institutional data, credentials, or third-party information. The audit prevents retention settings from becoming an unreviewed shadow archive.

## Audit Scope

| Store Type | Examples |
| --- | --- |
| `conversation` | Chat transcripts, message metadata, moderation labels |
| `prompt` | System prompts, prompt inputs, prompt variables |
| `output` | Provider responses, citations, refusal reasons |
| `prompt_output` | Paired prompt and response records used for evaluation |
| `rag_source` | Approved documents and source excerpts |
| `rag_chunk` | Indexed chunks used for retrieval |
| `embedding` | Vector records and metadata |
| `tool_call` | Tool invocation request, approval, result, rollback evidence |
| `audit_log` | Administrative and system audit records |
| `audit_export` | Redacted case packages for review or compliance |
| `evaluation` | Regression examples and test outputs |
| `telemetry` | Cost, latency, provider health, and usage metrics |
| `attachment` | Uploaded files, generated files, support attachments |
| `provider_payload` | Provider request or response payloads retained for troubleshooting |

## Required Policy Fields

The package must include:

- `policy_id`
- `policy_version`
- `owner`
- `reviewed_at`
- `evidence_reference`
- `stores`

Each store record should include:

- `store_id`, `store_type`, `data_classification`, `owner`, `purpose`, and `access_role`
- `retention_days` and `retention_justification` where retention exceeds one year
- `contains_personal_data` and `contains_credentials`
- `redaction_required` and `redaction_status`
- `deletion_supported`, `legal_hold_supported`, and `auto_delete_enabled`
- `encryption_at_rest`
- `provider_training_allowed`
- `last_reviewed`
- `evidence_reference`

## Controls Evaluated

| Control | Finding Behavior |
| --- | --- |
| Missing package or store metadata | High severity for required governance fields |
| Unsupported or missing retention days | High severity |
| Retention longer than recommended threshold | High for sensitive stores, medium otherwise |
| Credentials retained in a store | High severity |
| Sensitive store without encryption at rest | High severity |
| Personal-data store without deletion support | High severity |
| Conversation, tool-call, audit, or personal store without legal hold | High severity |
| Missing automatic deletion or review queue | Medium severity |
| Personal or redaction-required store without ready redaction status | High severity |
| Broad access roles such as `all`, `public`, or `global` | High for sensitive stores |
| Provider training enabled for prompts, outputs, or personal data | High severity |
| Stale review dates | High for sensitive stores older than 90 days, medium for general stores older than 180 days |

## Recommended Retention Limits

| Store Type | Recommended Maximum |
| --- | --- |
| Provider payload | 30 days |
| Prompt, output, prompt-output, conversation, attachment | 180 days |
| Tool-call log, audit export, evaluation, telemetry | 365 days |
| RAG chunk, embedding | 365 days |
| RAG source, audit log | 730 days |
| Personal-data stores | Capped at 180 days unless a stricter classification applies |
| Restricted, regulated, or credential-classified stores | Capped at 90 days |

These limits are deliberately conservative. Institutions may extend them when law, contract, academic records policy, or audit requirements justify longer retention, but the justification should be recorded and approved.

## Running The Audit

```bash
php scripts/conversation-retention-policy-audit.php examples/conversation-retention-policy-sample.json 2026-05-09T00:00:00Z
```

The command returns a JSON report with:

- `retention_policy_score`
- `passed`
- summary counts
- per-store status
- actionable findings

## Example Finding

```json
{
  "severity": "high",
  "type": "redaction_not_ready",
  "target": "conversation-logs",
  "message": "Personal or redaction-required store is not marked with a ready redaction status."
}
```

## Operational Use

Run this audit before:

- enabling conversation logging for a new bot;
- approving a new prompt or provider integration;
- adding RAG sources with personal or sensitive data;
- enabling tool calls that create audit records;
- sharing conversation review packages;
- closing provider incidents or redaction reviews;
- deleting chatbot data at the end of a retention period.

## Closure Criteria

A retention policy is ready when:

- all stores have explicit owner, purpose, evidence reference, and review date;
- sensitive stores confirm encryption, least-privilege access, deletion support, and legal hold;
- prompt, output, and personal-data stores have ready redaction controls;
- provider training is disabled for prompts, outputs, and personal data;
- retention days fit the recommended limits or have an approved justification;
- auto-delete or review queues enforce the retention schedule.
