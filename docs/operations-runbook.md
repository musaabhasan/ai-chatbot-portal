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
3. Export audit logs for prompt changes.
4. Add test cases to the prompt evaluation set.
5. Approve a corrected prompt version through the normal workflow.
