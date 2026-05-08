# Security Policy

## Reporting

Please report suspected vulnerabilities privately to the repository owner. Do not publish exploit details in public issues until a fix is available.

## Supported Surface

Security review should focus on:

- provider credential storage and rotation,
- admin authentication and MFA,
- RBAC enforcement,
- prompt and branding change control,
- RAG document boundaries,
- provider fallback and error handling,
- audit log integrity,
- data retention jobs.

## Production Baseline

- Set `APP_ENV=production`.
- Require HTTPS.
- Store `APP_KEY` and provider credentials in a secret manager.
- Enforce MFA for all administrative users.
- Restrict admin APIs to authorized networks or SSO groups.
- Run MySQL on a private network with encrypted storage.
- Keep backups, logs, uploaded documents, and database records inside the approved residency region.
