# Answer Provenance Drift Audit

The answer provenance drift audit checks whether current chatbot answers still rely on approved, fresh, citation-allowed, high-confidence sources after a prompt, model, RAG index, embedding, or document update. It complements citation integrity checks by comparing current answer provenance against an expected baseline instead of reviewing a single run in isolation.

## Why This Matters

A chatbot can keep producing plausible answers while quietly changing which sources support its claims. That drift can happen after a document refresh, embedding change, chunking change, prompt release, provider switch, or source retirement. The audit helps release owners detect when current answers lose expected citations, cite stale or unapproved sources, or show material retrieval-score drops before a release reaches users.

## Audit Scope

| Review Area | What Is Checked |
| --- | --- |
| Claim coverage | Each answer claim has current citations |
| Expected source continuity | Sources that should support a claim are still cited |
| Citation existence | Current citations appear in the retrieved source set |
| Source approval | Current sources are approved or approved with controls |
| Source freshness | Current source review dates are not overdue |
| Citation permission | Sensitive sources are explicitly allowed for citation |
| Retrieval-score drift | Current source scores have not dropped materially from baseline |
| Citation-set drift | Current citations have not changed unexpectedly from the expected set |

## Input Model

The JSON package includes:

- `package_id`
- `bot_id`
- `baseline_run_id`
- `current_run_id`
- `owner`
- `answers`

Each answer includes:

- `answer_id`
- `question`
- `baseline_sources`
- `current_sources`
- `claims`

Each claim includes:

- `claim_id`
- `claim`
- `expected_source_ids`
- `current_citation_ids`

## Running The Audit

```bash
php scripts/answer-provenance-drift-audit.php examples/answer-provenance-drift-sample.json 2026-05-09 0.74 0.15
```

Arguments:

| Argument | Meaning |
| --- | --- |
| JSON path | Provenance drift package |
| As-of date | Date used for source review freshness checks |
| Minimum score | Required current retrieval score |
| Maximum score drop | Tolerated score drop compared with baseline |

## Finding Types

| Finding | Severity | Meaning |
| --- | --- | --- |
| `missing_claim_citations` | High | A claim has no current citations |
| `unknown_current_citation` | High | A cited source was not retrieved for the current answer |
| `current_source_review_overdue` | High | A current cited source is past its review date |
| `sensitive_current_source_not_citation_allowed` | High | Sensitive source material is not approved for citation |
| `expected_source_not_cited` | Medium | A baseline-expected source is missing from the current claim |
| `low_current_retrieval_score` | Medium | Current source score is below threshold |
| `retrieval_score_drop` | Medium | Current score dropped beyond tolerance |
| `current_source_not_approved` | Medium | Current source is not approved |
| `citation_set_changed` | Low | Current citation set differs from the expected set |

## Release Use

Run the audit before:

- publishing a new RAG index;
- changing chunking or embedding settings;
- approving a prompt release;
- switching provider or model family;
- retiring or replacing a knowledge-base source;
- enabling answers for regulated, student, confidential, or policy-sensitive topics.

## Closure Criteria

A release is ready when:

- every answer claim has current citations;
- expected sources remain cited or an approved change record explains the difference;
- current sources are approved and not overdue for review;
- sensitive sources are allowed for citation;
- retrieval scores remain above threshold and do not drop materially from baseline;
- any citation-set drift has an owner-reviewed explanation.
