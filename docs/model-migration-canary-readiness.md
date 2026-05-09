# Model Migration Canary Readiness Audit

Model migrations should not move all users from a source model to a target model in one step. A canary rollout limits exposure, compares behavior against a locked baseline, and gives the operations team objective rollback thresholds before the migration expands.

## What The Audit Checks

The executable audit reviews each canary plan for:

- Source and target model metadata.
- Business owner, monitoring owner, evidence reference, and recent test date.
- Limited traffic percentage for the canary cohort.
- Completed shadow evaluation and locked baseline metrics.
- Evaluation pack and pass rate for the target model.
- Safety, citation, tool-policy, latency, and cost regression thresholds.
- Rollback runbook, automatic or operator-approved rollback, monitoring dashboard, and user impact plan.
- Safety, citation, tool-call, latency, and cost monitoring switches.

## Run The Audit

```bash
php scripts/model-migration-canary-readiness.php examples/model-migration-canary-readiness.json
```

The script exits with a non-zero status when a canary has high-severity findings or the readiness score falls below 90.

## Readiness Interpretation

| Signal | Meaning |
| --- | --- |
| `passed` | No high findings and score is at least 90 |
| `blocked_canaries` | Canary plans with release-blocking gaps |
| `traffic_limit_gaps` | Missing or excessive canary traffic limits |
| `regression_gaps` | Missing shadow evaluation, locked baseline, or evaluation pass-rate evidence |
| `rollback_gaps` | Missing threshold or rollback evidence |
| `monitoring_gaps` | Missing monitoring owner, dashboards, or metric tracking |

## Operational Use

Use this audit before:

- Migrating a production bot to a new model.
- Expanding from shadow evaluation to live canary traffic.
- Increasing canary traffic above the initial cohort.
- Replacing a model because of provider retirement or incident response.
- Approving a cost, context-window, safety-filter, or tool-behavior change.

## Evidence Expectations

Attach references to evaluation packs, baseline metrics, canary dashboards, safety-review evidence, citation checks, tool-call policy tests, latency and cost comparisons, user-impact plans, and rollback runbooks. The goal is to make the migration decision reversible and evidence-based rather than dependent on informal confidence.
