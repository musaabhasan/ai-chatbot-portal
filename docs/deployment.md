# Deployment Guide

## Production Requirements

- PHP 8.3+
- MySQL 8.0+
- HTTPS termination
- Secret manager for `APP_KEY` and provider keys
- Private object storage for uploaded documents
- Scheduled jobs for retention and re-indexing
- Centralized logs with access controls

## Hardening Checklist

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Generate a strong `APP_KEY` and keep it outside the repository.
- Enforce MFA for all administrative users.
- Restrict admin routes by IP or SSO group where possible.
- Run MySQL with encrypted storage and private network access.
- Ensure backups remain in the required residency region.
- Rotate provider keys and test fallback quarterly.
- Review audit logs and provider spend weekly.

## Docker Compose

The bundled Compose stack is intended for development and demonstrations:

```bash
cp .env.example .env
docker compose up --build
```

For production, run the app behind a managed reverse proxy and use managed MySQL or a hardened database host.

## Retention Jobs

Recommended scheduled jobs:

- delete expired chat transcripts,
- archive old audit logs after export,
- re-index stale documents after embedding model changes,
- test provider health every five minutes,
- summarize daily token and cost usage.
