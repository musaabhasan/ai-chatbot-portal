# Contributing

Contributions should improve the portal's security, reliability, documentation, or institutional deployment readiness.

## Development Checks

```bash
php scripts/lint.php
composer validate --strict --no-check-lock
```

## Contribution Areas

- provider adapters,
- RAG extraction and retrieval quality,
- prompt evaluation,
- admin UI flows,
- analytics and cost controls,
- MFA and SSO integrations,
- deployment hardening,
- accessibility.

Do not commit provider keys, real chat transcripts, internal hostnames, or private institutional documents.
