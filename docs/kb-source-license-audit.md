# Knowledge-Base Source License Audit

The knowledge-base source license audit checks whether RAG sources have the rights and governance approvals needed for ingestion, retrieval, citation, public answer display, and provider interaction. It is intended for institutional chatbot deployments where source material may include policies, handbooks, research, support articles, datasets, internal procedures, user uploads, or licensed third-party documents.

## Why This Matters

RAG quality is not only a retrieval problem. A source can be accurate but still unsuitable for chatbot use if the institution cannot reuse it, cite it, redistribute excerpts, expose it to a provider, or process personal data from it. This audit makes source rights review explicit before documents are indexed or surfaced in answers.

## Audit Scope

| Area | Control |
| --- | --- |
| Source ownership | Every source has a named owner |
| License evidence | License type, URL, or terms reference is recorded |
| RAG approval | Source is approved for ingestion and retrieval |
| Citation approval | Sources expected to support citations are allowed to be cited |
| Public answer rights | Public-facing answers use sources with redistribution rights |
| Provider training limits | Provider training use is disabled for institutional sources |
| Personal data approval | Sources with personal data have documented approval |
| Access scope | Sensitive sources are bound to a bot, department, or role scope |
| Review freshness | Source review dates are current |

## Input Model

The JSON package contains:

- `package_id`
- `collection_id`
- `owner`
- `reviewed_at`
- `sources`

Each source should include:

- `source_id`
- `title`
- `source_type`
- `license_type`
- `source_terms_reference` or `license_url`
- `owner`
- `approved_for_rag`
- `approved_for_citation`
- `citation_expected`
- `public_answer_enabled`
- `redistribution_allowed`
- `provider_training_allowed`
- `contains_personal_data`
- `personal_data_approval`
- `data_classification`
- `access_scope`
- `review_due`
- `evidence_reference`

## Running The Audit

```bash
php scripts/kb-source-license-audit.php examples/kb-source-license-sample.json 2026-05-09
```

The command returns:

- `license_audit_score`
- pass/fail status
- summary counters
- source-level readiness
- actionable findings

## Recognized Low-Risk License Labels

| Label | Meaning |
| --- | --- |
| `public-domain` | Public domain source |
| `cc0` | Creative Commons Zero |
| `cc-by` | Reuse allowed with attribution |
| `cc-by-sa` | Reuse allowed with share-alike obligations |
| `government-open-data` | Public-sector open data license |
| `open-access` | Open access publication or record |
| `institutional-owned` | Owned by the deploying institution |
| `licensed-internal` | Licensed for internal institutional use |
| `permission-granted` | Explicit permission recorded |
| `proprietary-approved` | Proprietary source approved for the configured use |

## Release Use

Run this audit before:

- indexing a new knowledge-base collection;
- enabling citations in user-facing answers;
- enabling public chatbot access;
- uploading third-party documents;
- adding research papers, vendor documents, or web pages;
- changing provider data-processing settings;
- retiring, replacing, or republishing RAG sources.

## Closure Criteria

A RAG collection is ready when every source has license evidence, owner, review date, ingestion approval, citation approval where needed, redistribution permission where public answers are enabled, provider-training disabled, personal-data approval where applicable, and a scoped access boundary for sensitive material.
