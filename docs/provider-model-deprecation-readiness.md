# Provider Model Deprecation Readiness Audit

Model retirement is an operational risk for institutional chatbots because a provider change can alter answer style, citation quality, refusal behavior, latency, cost, tool-use decisions, and fallback routing. This audit gives administrators an executable way to review model lifecycle readiness before a provider deadline becomes an outage.

## What The Audit Checks

The auditor reviews each configured provider/model pair against:

- Provider, model, environment, owner, review date, and evidence references.
- Deprecation and retirement dates for announced or retiring models.
- Replacement provider and model coverage.
- Fallback configuration and recent fallback-path testing.
- Prompt compatibility, RAG compatibility, safety equivalence, and evaluation pass rate.
- Cost, context-window, rate-limit, and user-communication readiness.
- Rollback, pause, or fail-closed actions for production migrations.

## Input Format

Use the example file as a baseline:

```bash
php scripts/provider-model-deprecation-readiness.php examples/provider-model-deprecation-readiness.json
```

Each model record should include:

- `provider`, `model`, `environment`, `traffic_tier`, and `status`
- `deprecation_date` and `retirement_date` when a model is announced for retirement
- `replacement_provider` and `replacement_model` for retiring models
- `fallback_configured` and `fallback_tested_at`
- `prompt_compatibility_review`, `rag_compatibility_review`, and `safety_equivalence_review`
- `evaluation_pack_id` and `evaluation_pass_rate`
- `cost_impact_review`, `user_communication_ready`, and `rollback_plan`
- `owner`, `last_reviewed`, and `evidence_reference`

## Readiness Interpretation

| Signal | Meaning |
| --- | --- |
| `passed` | No high-severity findings and readiness score is at least 90 |
| `blocked_models` | Models with release-blocking gaps, such as missing replacement, missing fallback test, or retired model still configured |
| `warning_models` | Models with non-blocking gaps that should be refreshed before a release |
| `retirements_within_30_days` | Models close enough to retirement that migration evidence should be completed immediately |
| `compatibility_gaps` | Missing prompt, RAG, or safety equivalence checks |
| `communication_gaps` | Missing user or administrator communication for visible behavior changes |

## Operational Use

Run this audit during:

- Quarterly model lifecycle review.
- Provider incident follow-up when a fallback route was activated.
- Prompt or RAG release approval for production bots.
- Budget review when replacement models change token price, latency, or context window.
- Change advisory review for user-facing chatbot behavior changes.

## Evidence Expectations

Attach references to evaluation packs, prompt regression results, RAG citation checks, safety equivalence tests, fallback drill logs, cost review notes, and communication drafts. The audit is intentionally evidence-oriented so model migration decisions can be defended after incidents or compliance reviews.
