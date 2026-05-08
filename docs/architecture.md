# Architecture

The portal is organized as a modular PHP application with a front controller, service classes, and a MySQL persistence layer. The design keeps LLM provider integration, retrieval, security, branding, and analytics separated so institutions can replace or extend one layer without rewriting the full system.

## Runtime Layers

| Layer | Responsibility |
| --- | --- |
| HTTP | Route requests, apply secure headers, validate CSRF tokens, serialize JSON responses |
| Security | Sessions, RBAC, MFA, rate limits, credential encryption, audit logs |
| Admin | Provider settings, prompt versions, bot instances, branding profiles |
| Chat | Conversation orchestration, prompt assembly, RAG retrieval, provider fallback |
| AI providers | OpenAI, Gemini, and DeepSeek adapters behind one interface |
| RAG | Document extraction, chunking, embedding, vector retrieval, and citations |
| Analytics | Token usage, cost, latency, error, and uptime records |

## Provider Routing

The provider router receives a `BotRequest` and resolves the provider order from:

1. user-group override,
2. bot instance default,
3. session override,
4. platform default.

Each provider attempt is timed and recorded. Failed providers are skipped while their circuit is open. If all providers fail, the portal returns a controlled error with a request id and writes the full failure chain to `provider_usage`.

## Bot Instance Boundary

Each bot instance owns:

- prompt template versions,
- branding profile,
- provider fallback order,
- sampling settings,
- RAG knowledge base,
- retention policy,
- moderation and audit settings.

This lets a university, department, service desk, or research unit run a tailored assistant while sharing the same platform.

## Data Model Highlights

- Prompt versions are immutable once approved.
- Knowledge documents are deduplicated per bot by SHA-256 hash.
- Conversation and message records keep provider/model metadata for auditability.
- Cost analytics are written per provider call, not only per conversation.
- Audit logs capture before/after payloads for administrative changes.

## Extension Points

- Add a provider by implementing `ProviderClient`.
- Add a vector backend by implementing `VectorStore`.
- Add a document parser by extending `DocumentExtractor`.
- Add an admin route by registering a controller in `public/index.php`.
- Add a policy gate by wrapping `ChatOrchestrator::answer`.
