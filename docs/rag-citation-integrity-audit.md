# RAG Citation Integrity Audit

RAG answer quality depends on more than retrieving a source. Claims should cite sources that were actually retrieved, are approved for use, are current, meet a minimum retrieval score, and are allowed to be cited for the user group.

This audit reviews answer evidence before prompt releases, index refreshes, incident reviews, or evaluation pack sign-off.

## Run The Audit

```bash
php scripts/rag-citation-integrity-audit.php examples/rag-citation-integrity-sample.json 2026-05-09 0.72
```

The command exits with `1` when high-risk citation findings are present.

## What It Checks

- Claims without citation identifiers.
- Citation identifiers that were not retrieved for the answer.
- Sources with retrieval scores below the configured threshold.
- Sources not approved or only in draft review.
- Sources past their review due date.
- Sensitive sources not approved for citation.

## Input Shape

Each answer should include:

- `answer_id`
- `claims`
- `retrieved_sources`

Each claim should include:

- `claim`
- `citation_ids`

Each retrieved source should include:

- `source_id`
- `title`
- `score`
- `review_status`
- `review_due`
- `data_classification`
- `citation_allowed`

## Release Guidance

Treat the following as release blockers:

- claim has no citation,
- citation points to a source that was not retrieved,
- cited source review is overdue,
- sensitive source is not approved for citation.

Medium findings should be resolved before the next prompt release or evaluation cycle, especially when low retrieval scores or draft sources appear in repeated test cases.

Pair this audit with the freshness and access-scope audits: freshness checks source currency, access scope checks retrieval permission, and citation integrity checks whether the answer cited the right evidence.
