# Prompt Log Redaction

Prompt and response logs can become a hidden personal-data store if raw user text is persisted without minimization. The prompt log redaction control masks common identifiers and secret-like values before messages are written to the conversation log.

## Runtime Control

`src/Security/PromptLogRedactor.php` redacts:

- email addresses,
- bearer tokens,
- API-key values,
- JWT-like tokens,
- direct identifiers such as student, employee, passport, or national ID values,
- phone-number-like values,
- long secret-like strings.

`ChatOrchestrator` uses the redactor before persisting user and assistant message content. Provider calls still receive the original user message so answer quality is not reduced, but the durable local log stores a minimized copy.

## CLI Preview

Run the sample preview:

```bash
php scripts/prompt-log-redaction-preview.php examples/prompt-redaction-sample.json
```

The command prints a JSON report showing the redacted text, total redaction count, and counts by redaction type.

## Operational Workflow

1. Enable prompt logging only for approved use cases.
2. Redact before local persistence.
3. Retain raw prompts only in exceptional cases with explicit approval and narrow access.
4. Use conversation audit export for review packages.
5. Run the redaction residual audit before sharing evidence outside the primary response team.

## Review Questions

- Is prompt logging needed for this bot instance, or can aggregate telemetry satisfy the need?
- Are raw prompts excluded from dashboards, exports, and lower environments?
- Are redaction rules tested against expected student, employee, and institutional identifiers?
- Is there a documented process for correcting a missed redaction?
- Are retention periods shorter for prompt logs than for aggregate provider usage metrics?
