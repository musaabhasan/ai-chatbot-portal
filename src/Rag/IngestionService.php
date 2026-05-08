<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use ChatbotPortal\AI\ProviderRouter;
use ChatbotPortal\Support\Env;
use PDO;

final class IngestionService
{
    public function __construct(
        private readonly PDO $db,
        private readonly DocumentExtractor $extractor,
        private readonly Chunker $chunker,
        private readonly ProviderRouter $providers,
        private readonly MySqlVectorStore $vectorStore
    ) {
    }

    public function ingest(int $botId, string $path, string $filename, string $mimeType, ?int $userId = null): int
    {
        $sha256 = hash_file('sha256', $path);
        $statement = $this->db->prepare(
            "INSERT INTO knowledge_documents (bot_id, title, source_filename, mime_type, sha256, uploaded_by)
             VALUES (:bot_id, :title, :source_filename, :mime_type, :sha256, :uploaded_by)"
        );
        $statement->execute([
            'bot_id' => $botId,
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'source_filename' => $filename,
            'mime_type' => $mimeType,
            'sha256' => $sha256,
            'uploaded_by' => $userId,
        ]);

        $documentId = (int) $this->db->lastInsertId();
        $text = $this->extractor->extract($path, $mimeType);
        $embeddingProvider = Env::get('EMBEDDING_PROVIDER', 'openai') ?? 'openai';
        $embeddingModel = Env::get('EMBEDDING_MODEL', 'text-embedding-3-small') ?? 'text-embedding-3-small';
        $client = $this->providers->embeddingClient($embeddingProvider);

        foreach ($this->chunker->chunks($text, Env::int('RAG_CHUNK_TOKENS', 650), Env::int('RAG_CHUNK_OVERLAP', 90)) as $index => $chunk) {
            $this->vectorStore->addChunk($documentId, $index, $chunk, $embeddingModel, $client->embed($chunk, ['embedding_model' => $embeddingModel]));
        }

        $this->db->prepare("UPDATE knowledge_documents SET status = 'indexed', indexed_at = UTC_TIMESTAMP() WHERE id = :id")
            ->execute(['id' => $documentId]);

        return $documentId;
    }
}
