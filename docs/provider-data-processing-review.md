# LLM Provider Data Processing Review

Use this review before approving a hosted LLM provider, adding a fallback provider, enabling a new region, changing retention settings, or allowing sensitive institutional data to pass through the chatbot gateway.

## Review Header

| Field | Value |
| --- | --- |
| Provider or service |  |
| Bot or department scope |  |
| Review type | New provider / renewal / region change / fallback change / incident follow-up / periodic review |
| Data owner |  |
| Security owner |  |
| Privacy owner |  |
| Review date |  |
| Next review due |  |

## Data Processing Questions

| Area | Review question | Evidence | Status |
| --- | --- | --- | --- |
| Data categories | What prompt, file, metadata, user, citation, and operational data can be sent to the provider? | Data-flow record |  |
| Sensitive data | Are student, employee, health, financial, confidential, or regulated records in scope? | Data classification |  |
| Training use | Can provider systems use prompts, outputs, files, or feedback to train or improve models? | Contract or provider policy |  |
| Retention | How long does the provider retain prompts, outputs, files, logs, abuse-monitoring data, and backups? | Retention clause |  |
| Deletion | Can records be deleted on request, and how is deletion evidenced? | Deletion process |  |
| Subprocessors | Which subprocessors or affiliates can process the data? | Subprocessor list |  |
| Region | Where are prompts, files, logs, and backups processed and stored? | Region evidence |  |
| Admin access | Who at the provider can access customer content and under what controls? | Access-control statement |  |
| Abuse monitoring | What data is inspected for safety, abuse, fraud, or policy enforcement? | Monitoring statement |  |
| Output ownership | Are output ownership, reuse, and intellectual-property boundaries clear? | Contract clause |  |

## Contract And Assurance Evidence

| Evidence | Required for | Status | Notes |
| --- | --- | --- | --- |
| Data processing agreement | Any personal or institutional confidential data |  |  |
| Security assurance report or equivalent | Production provider approval |  |  |
| Subprocessor transparency | Any third-party processing |  |  |
| Region and residency statement | Region-bound deployments |  |  |
| Breach notification terms | All production providers |  |  |
| Deletion and return obligations | Provider exit or data-subject requests |  |  |
| Service-level and support terms | Critical bots or fallback providers |  |  |
| Model or safety-filter change notice | Provider-managed model behavior |  |  |
| Audit or questionnaire response | High-risk deployments |  |  |

## Routing And Fallback Controls

| Control | Required decision |
| --- | --- |
| Provider eligibility | Define which bots, departments, and data classifications can use the provider. |
| Fallback equivalence | Confirm fallback providers meet comparable privacy, security, retention, and region requirements. |
| Sensitive-data blocking | Decide when the gateway must refuse, redact, or route away from the provider. |
| Logging boundary | Define what provider request and response metadata is stored locally. |
| Cost and timeout limits | Set token, spend, retry, and timeout thresholds before fallback occurs. |
| Incident failover | Define when provider incidents trigger provider disablement or restricted mode. |

## Portal Configuration Review

| Configuration | Expected evidence | Status |
| --- | --- | --- |
| Provider credentials are encrypted | Secret storage or encrypted credential record |  |
| Bot-to-provider mapping is approved | Admin setting and approval record |  |
| Fallback order is documented | Provider failover readiness record |  |
| Retention window is configured | Application retention setting |  |
| Prompt-log redaction is enabled | Redaction preview and residual audit |  |
| RAG source scope is compatible with provider eligibility | KB access scope audit |  |
| Evaluation pack includes provider-specific scenarios | Evaluation coverage gate |  |
| Incident evidence package can be generated | Provider incident evidence runbook |  |

## Risk Decision

| Decision | Criteria |
| --- | --- |
| Approve | Contract, region, retention, security, fallback, and configuration evidence are complete. |
| Conditionally approve | Provider can be used only for named bots, low-risk data, or a time-limited pilot. |
| Hold | Contract, privacy, security, region, deletion, or fallback evidence is incomplete. |
| Reject | Provider processing terms or controls are incompatible with the portal's approved data use. |

| Final decision | Scope restrictions | Owner | Review date | Evidence reference |
| --- | --- | --- | --- | --- |
|  |  |  |  |  |

## Re-Review Triggers

Repeat the review when the provider changes model family, region, retention terms, subprocessors, safety filters, admin access model, pricing, outage posture, breach notification terms, or training/data-use policy.
