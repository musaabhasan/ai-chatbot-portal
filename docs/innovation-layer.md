# Innovation Layer

The innovation layer turns the portal from a basic chat interface into an AI operations cockpit. It adds deterministic controls around the generative layer so administrators can understand why a model was selected, whether a request was risky, and how a bot should be evaluated before release.

## Intent-Aware Routing

`IntentClassifier` assigns each request an operational profile:

| Intent | Signals | Behavior |
| --- | --- | --- |
| `policy_question` | policy, deadline, requirement, compliance | require RAG, lower temperature, prefer high-accuracy model |
| `it_support` | login, VPN, access, ticket, system error | require RAG, concise answer, prefer fast support model |
| `research_assistance` | methodology, literature, citation, Scopus | require RAG, allow richer response |
| `technical_build` | code, API, SQL, debug | prefer technical model, lower temperature |
| `operations_query` | cost, tokens, provider, uptime | no RAG required, concise operational answer |

The classifier is deterministic by design. It can be replaced later with a learned classifier, but the deterministic baseline is auditable and easier to defend in regulated environments.

## Prompt Firewall

`PromptFirewall` inspects requests before a provider call. It blocks high-risk patterns such as:

- prompt or hidden-instruction extraction,
- credential and MFA-code requests,
- database or log exfiltration,
- disabling security controls or deleting audit trails.

It also marks review-sensitive requests involving personal data, legal/regulatory issues, or high-impact decisions. These are allowed but tagged so the UI, audit layer, or future reviewer queue can handle them differently.

## Response Intelligence Metadata

Every chat response can include:

```json
{
  "intelligence": {
    "intent": {
      "intent": "policy_question",
      "risk_tier": "medium",
      "recommended_provider": "openai",
      "rag_required": true
    },
    "firewall": {
      "allowed": true,
      "risk_level": "normal",
      "flags": []
    },
    "route": ["openai", "gemini", "deepseek", "demo"]
  }
}
```

This gives administrators traceability without exposing hidden prompts or provider secrets.

## Evaluation Lab

The evaluation lab provides reusable scenario packs for release testing. It is intentionally lightweight so teams can run it in CI before a prompt version is approved.

```bash
php scripts/run-evaluation.php examples/evaluation-pack.json
```

Future extensions can plug in live model calls, rubric scoring, hallucination checks, and human reviewer workflows.
