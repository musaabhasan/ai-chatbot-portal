# Prompt Release Audit

Prompt and persona changes can alter safety behavior, retrieval behavior, refusal style, tool-use boundaries, and user-facing policy interpretation. The prompt release audit provides a lightweight gate before approved prompt versions move into production.

## What The Audit Checks

The audit reviews each prompt release candidate for:

- final approval evidence,
- linked evaluation pack,
- minimum evaluation pass rate,
- red-team completion for high-risk or tool-capable prompts,
- tool-policy review when tool instructions change,
- RAG freshness review when sources change,
- rollback version,
- human review for sensitive or regulated data,
- concise release notes.

The default minimum pass rate is `0.90`.

## Run The Audit

```bash
php scripts/prompt-release-audit.php
```

Use a custom release file:

```bash
php scripts/prompt-release-audit.php path/to/prompt-release-candidates.json
```

The command returns exit code `0` when the release set is ready and exit code `1` when high-severity findings or a low readiness score should block release.

## Release Candidate Shape

```json
{
  "prompt_version": "advisor-v8",
  "persona": "Academic Advisor",
  "change_type": "RAG citation behavior",
  "risk_level": "medium",
  "approval_status": "approved",
  "approved_by": "learning-platform-owner",
  "evaluation_pack": "examples/evaluation-pack.json",
  "evaluation_pass_rate": 0.94,
  "red_team_status": "passed",
  "rollback_version": "advisor-v7",
  "contains_tool_instructions": "no",
  "tool_policy_reviewed": "yes",
  "rag_sources_changed": "yes",
  "rag_freshness_reviewed": "yes",
  "data_classification": "regulated",
  "human_review_required": "yes",
  "human_review_completed": "yes",
  "release_notes": "Tightens citation and abstention behavior for academic policy answers."
}
```

## Finding Types

| Finding | Severity | Meaning |
| --- | --- | --- |
| `approval_gap` | High | Final approval evidence is missing |
| `missing_evaluation_pack` | High | Release has no linked evaluation pack |
| `evaluation_below_threshold` | High | Pass rate is missing or below `0.90` |
| `red_team_gap` | High | High-risk or tool-capable prompt lacks red-team evidence |
| `tool_policy_gap` | High | Tool instructions changed without policy review |
| `rag_freshness_gap` | Medium | RAG source changes lack freshness review |
| `rollback_gap` | Medium or High | Release has no rollback version |
| `human_review_gap` | High | Sensitive or regulated release lacks completed human review |
| `release_notes_gap` | Low | Release has no concise change note |

## Operational Use

Use this gate before:

- publishing a new persona,
- changing system instructions,
- adding or changing tool-use instructions,
- changing citation or refusal behavior,
- updating a RAG-grounded prompt,
- releasing a prompt for regulated or student-facing workflows.

For high-impact deployments, keep the JSON output with the release record so future investigations can connect prompt version, approval, evaluation, red-team evidence, and rollback decisions.

