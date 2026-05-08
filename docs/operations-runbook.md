# Operations Runbook

## Provider Outage

1. Confirm `/api/health`.
2. Review `provider_usage` for failures by provider and model.
3. Disable the failing provider or open its circuit.
4. Confirm fallback provider responses.
5. Record the event in the administrative audit log.

## Unexpected Cost Increase

1. Review token usage by bot, provider, and user group.
2. Check recent prompt version changes.
3. Lower `max_output_tokens` or disable high-cost models.
4. Add department-level budget limits.
5. Review abnormal retry patterns.

## Incorrect RAG Answer

1. Inspect citations returned to the user.
2. Review retrieved chunk scores and source diversity.
3. Verify document freshness and indexing status.
4. Update source documents or prompt instructions.
5. Re-run an evaluation set before re-approving the prompt.

## Prompt Incident

1. Roll back to the previous approved prompt version.
2. Preserve affected conversation ids.
3. Export a redacted conversation audit package for affected conversations.
4. Export audit logs for prompt changes.
5. Record the package SHA-256 hash in the incident record.
6. Add test cases to the prompt evaluation set.
7. Approve a corrected prompt version through the normal workflow.

## Conversation Evidence Export

1. Identify the conversation, message, provider usage, citation, and audit-log rows in scope.
2. Build the package with `ConversationAuditExporter`.
3. Confirm package verification passes before sharing.
4. Store the package in restricted evidence storage.
5. Keep reviewer notes and remediation decisions separate from the original package.
