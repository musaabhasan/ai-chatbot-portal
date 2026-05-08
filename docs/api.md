# API Reference

The current implementation exposes a compact JSON API that can be expanded into a full admin API.

## Health

`GET /api/health`

Returns application status, environment, and timestamp.

## Branding CSS

`GET /branding.css?bot=institutional-assistant`

Returns CSS variables for the active bot branding profile.

## Chat

`POST /api/chat`

Request:

```json
{
  "bot": "institutional-assistant",
  "conversation_id": null,
  "message": "What support options are available?",
  "provider": null
}
```

Response:

```json
{
  "conversation_id": "uuid",
  "provider": "openai",
  "model": "gpt-4.1-mini",
  "answer": "Answer text",
  "citations": [
    {
      "document": "Student Handbook",
      "chunk": 4,
      "score": 0.82
    }
  ],
  "usage": {
    "input_tokens": 820,
    "output_tokens": 260,
    "latency_ms": 1350,
    "cost_usd": 0.0019
  }
}
```

## Admin Metrics

`GET /api/admin/metrics`

Requires admin permission in production. Returns provider spend, token usage, average latency, and recent failures.

## Error Shape

```json
{
  "error": {
    "code": "provider_unavailable",
    "message": "No configured provider could complete the request.",
    "request_id": "uuid"
  }
}
```
