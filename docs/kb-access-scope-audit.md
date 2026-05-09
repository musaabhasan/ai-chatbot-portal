# Knowledge-Base Access Scope Audit

RAG collections are access-control boundaries. A chatbot can be well prompted and still leak institutional data if it retrieves sources outside the approved department, classification, user group, or purpose boundary.

This audit converts a bot-to-knowledge-base scope file into a release gate for:

- cross-department collection exposure,
- unapproved data classifications,
- global retrieval scope on sensitive records,
- missing citation requirements for sensitive sources,
- personal-data access without approved controls,
- overdue or missing access-scope reviews.

## Run The Audit

```bash
php scripts/kb-access-scope-audit.php examples/kb-access-scope-sample.json 2026-05-09
```

The command exits with `1` when high-risk findings are present, so it can be used in a release checklist or CI gate before a bot, prompt, or RAG index is promoted.

## Input Shape

The JSON file should describe one bot or bot group:

- `bot_id`
- `user_group`
- `allowed_departments`
- `approved_classifications`
- `knowledge_collections`

Each collection should include:

- `collection_id`
- `owner_department`
- `data_classification`
- `retrieval_scope`
- `citation_required`
- `pii_allowed`
- `approval_status`
- `review_due`
- `purpose`
- `sharing_approval`
- `sharing_justification`

## Release Guidance

Treat the following as release blockers:

- collection owner is outside the bot's allowed departments without approved sharing justification,
- sensitive or regulated collection classification is not approved for the bot,
- confidential, restricted, student, personal, or regulated records are exposed through global retrieval,
- personal-data collections are enabled without approved controls,
- access-scope review is overdue.

Medium findings should be resolved before the next prompt release, RAG refresh, or department-admin review.

## Operational Pattern

1. Maintain the access-scope JSON next to the bot configuration or release evidence.
2. Run this audit whenever a collection is added, a user group changes, or a RAG index is rebuilt.
3. Keep approved sharing justifications specific to one collection, bot, purpose, and review date.
4. Review sensitive sources with the content owner, privacy owner, and bot owner before enabling retrieval.
5. Pair this audit with `rag-freshness-audit.php`; freshness proves current ownership, while scope proves the bot should be allowed to retrieve the source.
