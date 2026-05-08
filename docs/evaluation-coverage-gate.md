# Evaluation Coverage Gate

The evaluation coverage gate audits scenario packs before a prompt, model, provider, or RAG configuration is released. It complements the evaluation runner by checking whether the test pack covers the main institutional AI risk areas, not only whether each scenario is syntactically complete.

## What It Checks

| Check | Purpose |
| --- | --- |
| Required coverage tags | Confirms the pack covers prompt injection, credential safety, citation-required policy answers, policy guidance, and research support |
| Duplicate scenario IDs | Prevents overwritten or ambiguous scenario evidence |
| High-impact evidence depth | Flags sensitive scenarios that do not define enough minimum evidence expectations |
| Citation behavior | Ensures citation-required scenarios explicitly mention source or citation behavior |
| Coverage score | Gives release reviewers a compact pass/fail signal |

## Run The Gate

```bash
php scripts/evaluation-coverage-gate.php examples/evaluation-pack.json
```

The command returns JSON with:

- `coverage_score`
- `passed`
- `required_tags`
- `tag_counts`
- per-scenario risk summaries
- findings with severity, type, and message

## Release Use

Use the gate before approving:

- new system prompts
- provider changes
- RAG retriever changes
- high-impact personas
- policy assistant deployments
- major UI or workflow changes that alter user expectations

## Recommended Minimum

- Coverage score of at least 90
- No high or critical findings
- At least one scenario for every required risk tag
- At least two minimum evidence expectations for high-impact scenarios
