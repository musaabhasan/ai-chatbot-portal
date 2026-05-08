# Provider Incident Evidence Package

Provider incidents in multi-LLM portals can involve latency degradation, fallback routing, safety-filter differences, cost spikes, failed requests, changed model behavior, or provider recovery. The evidence package preserves those facts in a redacted, hashable artifact for operations review, governance review, and audit evidence.

## Package Goals

- Preserve incident summary, provider events, routing decisions, and related audit events.
- Redact emails, bearer tokens, API keys, JWT-like values, authorization headers, and secret-named fields.
- Create an ordered timeline across provider events, routing decisions, and audit events.
- Generate review questions for fallback, cost, safety, residency, and escalation analysis.
- Produce a package-level SHA-256 hash so later changes are detectable.

## Backend Component

`src/Security/ProviderIncidentEvidenceExporter.php` provides:

- deterministic evidence package creation,
- recursive redaction,
- event timeline generation,
- incident-specific review questions,
- package integrity verification.

## CLI Demo

```bash
php scripts/provider-incident-evidence.php examples/provider-incident-evidence.json
```

The demo input intentionally includes emails, bearer-token-like values, and provider-key-like values so the redaction policy can be verified.

## Evidence To Include

| Evidence | Purpose |
| --- | --- |
| Incident id, severity, title, detector, bot id, and risk owner | Defines incident scope and accountability. |
| Provider events | Shows latency, error, cost, safety, or availability changes by provider and model. |
| Routing decisions | Shows which provider/model was selected, why, and whether fallback matched policy. |
| Audit events | Shows operator actions, restoration, escalation, and configuration changes. |
| Timeline | Supports replay and reviewer reconstruction without querying live systems. |
| Review questions | Guides governance review and post-incident follow-up. |

## Review Workflow

1. Export the incident window after containment or provider restoration.
2. Verify that credentials and user identifiers are redacted.
3. Store the package in restricted evidence storage.
4. Record the package hash in the incident record.
5. Review routing decisions against approved provider order, data handling rules, and fallback policy.
6. Attach remediation notes separately so the original evidence package remains unchanged.

## Review Triggers

Export this package when:

- fallback routing activates unexpectedly,
- provider latency or errors breach service thresholds,
- token cost exceeds the configured anomaly threshold,
- a provider safety-filter or moderation behavior changes,
- provider/model changes affect a high-risk bot,
- a user or auditor challenges an answer generated during a degraded provider window.

## Governance Notes

Fallback is not automatically low risk. A fallback provider can change retention, residency, safety behavior, model quality, cost, and contractual posture. Treat provider incidents as AI governance events when they alter the approved behavior of a bot instance.
