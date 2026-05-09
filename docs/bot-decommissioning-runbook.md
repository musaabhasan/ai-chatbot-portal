# Bot Decommissioning Runbook

Use this runbook when retiring, replacing, pausing, or materially reducing an AI chatbot instance. Decommissioning should preserve required evidence, remove unnecessary access, protect users from stale advice, and close the operational record cleanly.

## Runbook Header

| Field | Value |
| --- | --- |
| Bot name and ID |  |
| Department or owner |  |
| Decommissioning type | Retire / replace / pause / merge / restrict |
| Reason |  |
| Requested by |  |
| Planned effective date |  |
| Risk owner |  |
| Evidence package reference |  |

## 1. Decision And Communication

| Task | Evidence | Owner | Status |
| --- | --- | --- | --- |
| Confirm business owner approval | Approval ticket or decision log |  |  |
| Confirm risk, privacy, and security review need | Review decision |  |  |
| Identify affected users and integrations | Bot usage report |  |  |
| Publish user-facing retirement or replacement notice | Message copy and publish date |  |  |
| Notify support, helpdesk, and department admins | Notification record |  |  |
| Update documentation, quick links, and embedded launch points | Link inventory |  |  |

## 2. Conversation And Evidence Handling

| Task | Evidence | Owner | Status |
| --- | --- | --- | --- |
| Export required audit evidence before shutdown | Conversation audit package |  |  |
| Apply redaction residual audit to shared evidence | Redaction residual report |  |  |
| Preserve incident or complaint records under the incident retention policy | Incident references |  |  |
| Confirm prompt-log retention and deletion schedule | Retention decision |  |  |
| Record whether training, analytics, or quality-review datasets used bot data | Data-use register |  |  |
| Close unresolved flagged conversations or route them to support | Flag review queue |  |  |

## 3. RAG And Knowledge Base Closure

| Task | Evidence | Owner | Status |
| --- | --- | --- | --- |
| Freeze or disable bot retrieval indexes | Admin setting or migration record |  |  |
| Identify documents that are shared with other bots | KB access scope audit |  |  |
| Retire bot-only sources or transfer ownership | Source owner decision |  |  |
| Delete embeddings that are no longer required | Deletion ticket or database evidence |  |  |
| Confirm citation history remains interpretable for retained conversations | Citation integrity review |  |  |
| Update knowledge-base documentation and source status | Source inventory update |  |  |

## 4. Provider, Prompt, And Configuration Shutdown

| Task | Evidence | Owner | Status |
| --- | --- | --- | --- |
| Disable the bot instance in routing configuration | Admin audit log |  |  |
| Archive final approved prompt version and release notes | Prompt version record |  |  |
| Remove bot-specific provider overrides | Provider setting diff |  |  |
| Remove fallback routing entries no longer needed | Provider failover record |  |  |
| Revoke bot-specific API credentials, webhooks, or service accounts | Credential revocation proof |  |  |
| Rotate shared credentials if the bot had broad access | Rotation ticket |  |  |
| Disable scheduled jobs or ingestion tasks | Job inventory update |  |  |

## 5. Access And Administration

| Task | Evidence | Owner | Status |
| --- | --- | --- | --- |
| Remove department admin permissions specific to the bot | RBAC audit log |  |  |
| Remove reviewer or support assignments no longer needed | Access review record |  |  |
| Disable public or internal launch URLs | Route or reverse-proxy evidence |  |  |
| Remove bot from dashboards or mark it retired | Analytics setting |  |  |
| Confirm no orphaned users, tokens, documents, or jobs remain | Final inventory |  |  |

## 6. Final Verification

| Verification | Expected result | Evidence |
| --- | --- | --- |
| User route | Bot is unavailable or redirects to approved replacement |  |
| Admin route | Bot is retired, read-only, or inaccessible except to approved admins |  |
| Provider calls | No new provider calls are made by the retired bot |  |
| RAG retrieval | Retired bot cannot retrieve from active sources |  |
| Audit logs | Decommissioning actions are captured |  |
| Evidence package | Required evidence is archived and redacted |  |

## Closure Decision

| Decision | Criteria |
| --- | --- |
| Closed | Access, routing, credentials, knowledge base, evidence, and communications are complete. |
| Closed with monitoring | Shutdown is complete, but logs or user redirection require short-term monitoring. |
| Hold | Evidence, access, deletion, or user-impact tasks remain open. |

| Final decision | Owner | Closure date | Open follow-up | Evidence reference |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |

## Post-Decommission Review

Within 30 days, review whether users continued to seek the retired bot, whether support tickets increased, whether replacement routing worked, and whether any retained evidence should be deleted under the approved retention schedule.
