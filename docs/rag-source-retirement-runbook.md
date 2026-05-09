# RAG Source Retirement Runbook

Use this runbook when a knowledge-base source must be retired, replaced, restricted, or re-owned. It is designed to prevent stale or unauthorized material from being retrieved while preserving the evidence needed to explain historical citations and audit decisions.

## Runbook Header

| Field | Value |
| --- | --- |
| Source title or ID |  |
| Collection or bot scope |  |
| Retirement type | Retire / replace / restrict / re-own / quarantine |
| Reason | Stale / unauthorized / incorrect / superseded / privacy / copyright / owner change / incident |
| Source owner |  |
| Knowledge manager |  |
| Planned effective date |  |
| Evidence reference |  |

## 1. Impact Assessment

| Question | Evidence | Status |
| --- | --- | --- |
| Which bots, departments, or user groups retrieve from this source? | KB access scope audit |  |
| Is the source cited in retained conversations or audit exports? | Citation integrity review |  |
| Does the source contain personal, confidential, copyrighted, or restricted content? | Data classification |  |
| Is a replacement source available and approved? | Source approval record |  |
| Would removal create answer gaps for critical workflows? | RAG freshness audit |  |
| Is retirement linked to an incident, complaint, or legal request? | Incident or request record |  |

## 2. Retirement Decision

| Decision | Use when | Required action |
| --- | --- | --- |
| Retire | Source should no longer be retrieved or cited | Disable retrieval, delete embeddings if allowed, preserve citation history |
| Replace | A newer approved source supersedes the old source | Load replacement, re-index, and record supersession |
| Restrict | Source remains valid but should be scoped to fewer bots or groups | Update bot, department, classification, or citation permissions |
| Re-own | Source remains valid but ownership or review accountability changes | Update owner, review date, and approval evidence |
| Quarantine | Source may be unsafe or disputed and needs investigation | Disable retrieval immediately and preserve evidence |

| Final decision | Owner | Effective date | Restrictions | Evidence reference |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |

## 3. Pre-Change Evidence

| Evidence | Captured? | Location |
| --- | --- | --- |
| Current source metadata |  |  |
| Current chunk count and embedding version |  |  |
| Current bot and department scope |  |  |
| Current citation permissions |  |  |
| Current review date and owner |  |  |
| Recent retrieval or citation examples |  |  |
| Approval or incident reference |  |  |

## 4. Change Steps

| Step | Action | Owner | Status |
| --- | --- | --- | --- |
| Freeze source | Prevent new ingestion or edits during retirement |  |  |
| Disable retrieval | Remove source from active retrieval scope or mark inactive |  |  |
| Load replacement | Ingest and approve replacement source if applicable |  |  |
| Rebuild index | Re-index affected collection after removal or replacement |  |  |
| Delete embeddings | Delete retired embeddings where retention policy permits |  |  |
| Preserve citation map | Keep enough metadata to interpret historical cited answers |  |  |
| Update source inventory | Mark source retired, restricted, quarantined, or superseded |  |  |
| Notify owners | Inform bot owners, support, reviewers, and affected admins |  |  |

## 5. Validation

| Test | Expected result | Evidence |
| --- | --- | --- |
| Retrieval test for retired source | Source is not returned in new answers |  |
| Retrieval test for replacement source | Replacement source is cited when relevant |  |
| Bot scope test | Unauthorized departments or bots cannot retrieve source |  |
| Citation history test | Historical answers remain explainable in audit exports |  |
| Freshness audit | Affected collection has no overdue replacement review |  |
| Citation integrity audit | New answers cite approved, current, citation-allowed sources |  |

## 6. Privacy, Copyright, And Incident Handling

Escalate before deletion or public reporting when:

- The source contains personal, student, employee, confidential, or regulated data.
- The source was ingested without proper rights or approval.
- The source is involved in an incident, complaint, litigation hold, or audit request.
- Historical citations may reveal sensitive excerpts.
- Deleting embeddings would destroy evidence required for investigation.

## Closure Record

| Item | Status | Evidence reference |
| --- | --- | --- |
| Source inactive or appropriately restricted |  |  |
| Replacement source approved, if applicable |  |  |
| Embedding deletion or retention decision recorded |  |  |
| Historical citation evidence preserved |  |  |
| Affected bot owners notified |  |  |
| Audit logs and inventory updated |  |  |
| Follow-up review scheduled |  |  |

## Re-Review Triggers

Repeat this runbook when the source owner changes, classification changes, review date expires, source is challenged, replacement document changes, citation policy changes, or a bot is approved for a new department or user group.
