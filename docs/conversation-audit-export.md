# Conversation Audit Export Package

Conversation review is most useful when reviewers can preserve enough evidence to reconstruct what happened without spreading provider keys, user emails, authorization headers, or sensitive prompt content. This export package defines a redacted, hashable evidence artifact for incident response, governance review, and audit requests.

## Package Goals

- Preserve conversation metadata, messages, safety flags, citations, provider/model details, and related audit events.
- Redact common secrets and identifiers before export.
- Produce a package-level SHA-256 hash so reviewers can detect later changes.
- Keep the package independent from a live database snapshot.
- Support prompt incidents, RAG answer disputes, privacy reviews, and model/provider change investigations.

## Backend Component

`src/Security/ConversationAuditExporter.php` provides:

- deterministic package creation,
- recursive redaction for arrays and nested fields,
- email, bearer-token, API-key, JWT-like, long-token, and secret-field redaction,
- package-level SHA-256 integrity hash,
- verification through constant-time hash comparison.

## Suggested Package Shape

```json
{
  "package_version": "1.0",
  "exported_at": "2026-05-08T12:00:00Z",
  "conversation": {},
  "messages": [],
  "audit_events": [],
  "redaction_policy": [
    "emails",
    "bearer_tokens",
    "api_keys",
    "jwt_like_tokens",
    "long_secret_like_values",
    "secret_named_fields"
  ],
  "integrity": {
    "hash_algorithm": "sha256",
    "message_count": 4,
    "audit_event_count": 3,
    "package_sha256": "..."
  }
}
```

## Evidence To Include

| Evidence | Purpose |
| --- | --- |
| Conversation id, bot id, user id, provider, model, status, timestamps | Reconstructs scope and runtime context. |
| Message role, redacted content, safety flags, citations, latency, token counts | Shows what the user saw and how the model behaved. |
| Prompt template version and release notes | Shows which approved instruction set was active. |
| RAG document ids, chunk ids, scores, and citation metadata | Supports grounded-answer review. |
| Provider usage rows | Supports cost, latency, and fallback review. |
| Audit log events | Shows administrative changes around the incident window. |

## Redaction Rules

The exporter redacts:

- email addresses,
- bearer tokens,
- API-key values in text,
- JWT-like tokens,
- long secret-like values,
- fields named like token, secret, password, API key, authorization, or credential.

Treat the exported package as sensitive even after redaction. It can still contain operational context, institutional metadata, and potentially sensitive user intent.

## Review Workflow

1. Identify the incident or review window.
2. Export the conversation, related messages, provider usage, citations, and audit events.
3. Store the redacted package in restricted evidence storage.
4. Record the package hash in the audit log or case record.
5. Attach reviewer notes and remediation decisions separately so the original package remains unchanged.
6. Re-run package verification before sharing or closing the review.

## When To Export

- suspected prompt injection,
- unsafe or incorrect answer,
- privacy complaint,
- unsupported citation or RAG hallucination,
- unexpected provider fallback,
- abnormal token spend,
- prompt-template rollback,
- reviewer or auditor evidence request.
