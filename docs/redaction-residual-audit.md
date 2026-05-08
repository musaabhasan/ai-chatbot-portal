# Redaction Residual Audit

Conversation audit packages are useful only when they preserve evidence without spreading secrets or direct identifiers. The redaction residual audit provides a second-pass quality gate after export and before evidence is shared with reviewers, auditors, vendors, or incident handlers.

## What It Detects

`src/Security/RedactionResidualAuditor.php` scans nested package data for:

- unredacted secret-named fields,
- bearer tokens,
- API-key values,
- JWT-like tokens,
- long secret-like strings,
- email addresses,
- direct identifiers such as student, employee, passport, or national ID values.

Hash and integrity fields are excluded from long-secret checks so package SHA-256 evidence does not create false positives.

## CLI Usage

Run the sample residual audit:

```bash
php scripts/redaction-residual-audit.php examples/redaction-residual-package.json
```

The command prints JSON and exits with a non-zero status when high-severity residuals remain. This makes it suitable for an incident-response checklist, evidence-sharing gate, or CI validation of sample export packages.

## Reviewer Workflow

1. Build the conversation audit package.
2. Store the original package in restricted evidence storage.
3. Run the redaction residual audit on the shareable copy.
4. Resolve high findings before sending the package outside the primary response team.
5. Keep the residual audit report with the case record and package hash.

## Interpreting Findings

| Severity | Meaning | Action |
| --- | --- | --- |
| High | Secret, token, API key, JWT-like value, long secret-like value, or secret-named field remains | Block sharing until redacted |
| Medium | Direct identifier or email remains | Review whether the value is necessary and approved for the review purpose |
| Low | Reserved for future weak-signal checks | Keep as reviewer context |

## Evidence To Retain

- original package hash,
- residual audit JSON output,
- reviewer decision,
- redaction correction notes,
- approval for any intentionally retained identifier.

This control complements the conversation audit exporter. The exporter performs the primary redaction; this audit checks whether sensitive values still remain in the exported evidence.
