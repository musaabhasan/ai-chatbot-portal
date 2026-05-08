# Cost Budget Audit

The cost budget audit checks whether chatbot instances are operating within approved department-level budgets. It is designed for administrators who need a release gate or monthly governance review before LLM usage silently exceeds an agreed spend limit.

## What It Checks

- Missing or zero monthly budget.
- Missing budget owner.
- Projected monthly spend above the alert threshold.
- Projected monthly spend above the hard-stop threshold.
- Spend-to-date already above the alert threshold.
- Missing review or approval status.
- Missing evidence reference for cost dashboard, approval, or owner review.

## Run The Audit

```bash
php scripts/cost-budget-audit.php examples/cost-budget-sample.json
```

The command exits with code `1` when high-severity budget findings exist, which makes it suitable for release gates or scheduled governance jobs.

## Input Shape

```json
{
  "period": "2026-05",
  "budgets": [
    {
      "department": "Academic Advising",
      "bot_id": "advisor-main",
      "provider": "openai",
      "monthly_budget_usd": 1200,
      "spend_to_date_usd": 620,
      "projected_monthly_spend_usd": 980,
      "alert_threshold_percent": 80,
      "hard_stop_percent": 110,
      "tokens_this_month": 8400000,
      "owner": "advising-technology",
      "review_status": "approved",
      "evidence_reference": "COST-2026-05-001"
    }
  ]
}
```

## Operating Guidance

- Treat `hard_stop_exceeded` as a release-blocking or routing-blocking condition.
- Route `alert_threshold_exceeded` to the department owner before the end of the billing period.
- Review whether cost spikes came from larger context windows, fallback routing, retries, prompt changes, RAG retrieval expansion, or misuse.
- Keep cost evidence with prompt release notes and provider incident packages so budget decisions are explainable after the fact.
- Pair budget alerts with token, latency, model route, and provider-fallback telemetry.
