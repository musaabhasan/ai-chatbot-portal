# Security Model

The portal assumes chatbots can access sensitive institutional data, so it treats configuration, prompts, provider credentials, and knowledge documents as security boundaries.

## Controls

| Risk | Control |
| --- | --- |
| Provider key exposure | Sodium secretbox encryption, environment isolation, no keys returned through APIs |
| Prompt tampering | Versioned prompts, approval workflow, audit logs, rollback |
| Overbroad administration | RBAC roles and per-department scoping |
| Account compromise | MFA for administrators, secure session cookies, idle timeout |
| Excessive usage | Per-user, per-IP, per-bot, and per-provider rate limits |
| Data leakage through RAG | Source provenance, bot-specific document boundaries, retention policy |
| Compliance gaps | 12-month administrative audit retention and configurable chat retention |
| Unsafe answers | Moderation flags, blocked categories, reviewer queue extension point |
| Evidence leakage during review | Redacted conversation audit packages with integrity hashes and restricted storage |

## Roles

| Role | Permissions |
| --- | --- |
| `super_admin` | Full platform configuration, providers, departments, retention, audit export |
| `department_admin` | Manage assigned department bots, prompts, branding, and knowledge documents |
| `reviewer` | Review flagged conversations and prompt performance |
| `end_user` | Use assigned bots and view own conversations |

## MFA

The repository includes a TOTP verifier that can be connected to enrollment screens. Admin accounts should be required to enroll before provider or prompt management is allowed.

## Credential Handling

Provider API keys must be encrypted before database storage. The `APP_KEY` value should be generated with 32 bytes of entropy and stored in a secret manager. Rotating `APP_KEY` requires decrypting and re-encrypting provider secrets through a controlled maintenance command.

## Data Residency

The `DATA_RESIDENCY_REGION` setting records the intended hosting jurisdiction. Deployments that require local residency should place the database, object storage, logs, and backups within the approved region and disable third-party telemetry that exports chat data.

## Logging

Log enough for accountability, but avoid storing unnecessary sensitive content:

- record provider, model, token counts, cost, latency, and errors,
- redact provider keys and authorization headers,
- keep audit logs append-only,
- apply retention jobs to chat transcripts and uploaded documents,
- protect logs with the same access controls as production data.

## Evidence Export

Conversation audit exports should be redacted before they leave the production database boundary. Export packages should include enough metadata to reconstruct the incident, but should remove provider keys, authorization tokens, email addresses, and long secret-like values. Record the export package hash in the case record so later reviewers can detect evidence changes.
