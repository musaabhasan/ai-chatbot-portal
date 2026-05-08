# RAG Pipeline

The retrieval pipeline is designed for institutional documents where answers must be traceable to source material.

## Ingestion Flow

1. Validate file type and size.
2. Compute SHA-256 for deduplication.
3. Extract text from TXT, PDF, or DOCX.
4. Normalize whitespace and remove empty sections.
5. Split into overlapping chunks.
6. Generate embeddings through the configured embedding provider.
7. Store chunk text, embedding vector, model name, and metadata in MySQL.
8. Mark the document as indexed.

## Retrieval Flow

1. Embed the user query.
2. Filter chunks by bot instance and active document status.
3. Score chunks by cosine similarity.
4. Apply source diversity so one long document cannot dominate all context.
5. Return top chunks with document title, chunk index, and score.
6. Insert citations into the answer payload.

## Chunking Guidance

Recommended defaults:

- `RAG_CHUNK_TOKENS=650`
- `RAG_CHUNK_OVERLAP=90`
- `RAG_TOP_K=6`

For policy manuals, use larger chunks. For FAQ or service-desk material, use smaller chunks with lower overlap.

## Quality Controls

- Show citations in the UI.
- Track RAG hit rate per bot.
- Record retrieval score distribution for quality monitoring.
- Re-index documents when embedding model changes.
- Keep document status values: `uploaded`, `indexed`, `failed`, `archived`.
