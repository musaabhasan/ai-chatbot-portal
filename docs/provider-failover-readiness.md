# Provider Failover Readiness Audit

Provider fallback should be treated as a governance control, not only a routing convenience. A chatbot can behave differently when it falls back to another model or vendor: data handling, safety filters, latency, logging, cost, and answer quality may all change.

## What The Audit Checks

| Check | Purpose |
| --- | --- |
| Owner | Confirms accountable business or technical ownership for fallback decisions. |
| Primary and fallback providers | Ensures the bot has an explicit primary provider and at least one approved fallback or a documented fail-closed decision. |
| Health status | Confirms provider health checks are current before routing users. |
| Failover test date | Flags missing or stale failover-test evidence. |
| Timeout budget | Prevents long provider waits from damaging user experience and cost controls. |
| Retry limit | Prevents retry storms during provider degradation. |
| Data residency equivalence | Confirms fallback does not silently move data into an unapproved handling path. |
| Safety policy equivalence | Confirms fallback output policy is appropriate for the bot's user group and domain. |
| Logging | Ensures fallback decisions are reconstructable for incidents and audits. |
| Budget, runbook, and evidence links | Connects fallback operations to approved cost, operational, and review records. |

## Demo Command

```bash
php scripts/provider-failover-readiness.php examples/provider-failover-readiness.json
```

The command exits with status `1` when high-risk findings are present, making it suitable for CI gates or pre-release approval checks.

## Operational Use

Run the audit before:

- enabling a new bot,
- changing provider order,
- changing default models,
- renewing provider contracts,
- routing traffic during an outage,
- approving a high-impact departmental assistant.

High-risk findings should block fallback release until the owner records evidence or documents a formal fail-closed decision.
