# Governance Checklist

Use this checklist before approving a bot instance for production.

| Control | Question | Evidence |
| --- | --- | --- |
| Ownership | Is a business owner and technical owner assigned? | Bot record, department owner |
| Prompt approval | Is the active prompt version approved and tested? | Prompt version record |
| RAG provenance | Are source documents approved and current? | Knowledge document register |
| Access control | Are admin roles scoped by department? | RBAC review |
| MFA | Are all admin accounts enrolled in MFA? | User security report |
| Provider fallback | Has fallback behavior been tested? | Provider test report |
| Cost limits | Are token budgets and alerts configured? | Usage dashboard |
| Retention | Are chat, audit, and upload retention rules documented? | Retention configuration |
| Data residency | Are database, backups, logs, and uploads hosted in the required region? | Deployment record |
| Incident response | Is a rollback and provider outage procedure available? | Operations runbook |
