# RAG Knowledge-Base Freshness Audit

RAG answers are only as reliable as the documents they retrieve. For institutional assistants, stale policy documents, missing owners, and unreviewed sensitive sources can create incorrect academic, administrative, or support guidance.

This audit converts a knowledge-base inventory into a release gate for source freshness and ownership.

## Inventory Format

Use `examples/rag-document-inventory.csv` as the starting point.

| Column | Purpose |
| --- | --- |
| `document_id` | Stable source identifier used in citations and audit events |
| `title` | Human-readable document name |
| `owner` | Accountable content owner or department |
| `source_type` | Policy, procedure, FAQ, page, handbook, dataset, or other source type |
| `data_classification` | Public, internal, confidential, restricted, or regulated |
| `indexed_at` | Date when the current source was indexed |
| `last_reviewed_at` | Date when the content owner last reviewed the source |
| `next_review_due` | Date by which the source must be reviewed again |
| `citation_required` | Whether answers using the source must cite it |
| `status` | Active, archived, disabled, or retired |
| `notes` | Review notes or remediation context |

## Run The Audit

```bash
php scripts/rag-freshness-audit.php examples/rag-document-inventory.csv 2026-05-08
```

The script returns JSON with:

- freshness score,
- pass/fail decision,
- active document count,
- overdue reviews,
- missing owners,
- missing review dates,
- citation gaps for sensitive sources,
- stale indexes where content was reviewed after indexing,
- document-level severity summaries.

## Release Gate Guidance

| Finding | Why It Matters | Expected Action |
| --- | --- | --- |
| `missing_owner` | No one is accountable for source accuracy | Assign content owner before publishing |
| `missing_review_date` | Source can become stale silently | Add review cadence and due date |
| `overdue_review` | Retrieval may cite outdated policy or support steps | Review source and re-index if needed |
| `stale_index` | The indexed chunk may not match the latest approved content | Re-ingest the reviewed source |
| `citation_gap` | Sensitive or regulated answers need traceability | Require citations and source IDs |

## Operational Use

Run this gate before:

- publishing a new bot instance,
- approving a prompt version that relies on RAG,
- importing a new document batch,
- switching model/provider behavior for high-impact personas,
- renewing institutional policy sources,
- responding to a complaint about incorrect or unsupported chatbot guidance.

The audit should complement retrieval evaluation. Passing freshness checks does not prove answer quality, but failing them means the evaluation set may be testing stale or unmanaged knowledge.
