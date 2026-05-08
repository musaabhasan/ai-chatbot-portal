<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use PDO;

final class MySqlVectorStore
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<int, float> $embedding
     */
    public function addChunk(int $documentId, int $chunkIndex, string $content, string $embeddingModel, array $embedding, array $metadata = []): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO knowledge_chunks (document_id, chunk_index, content, token_estimate, embedding_model, embedding_vector, metadata)
             VALUES (:document_id, :chunk_index, :content, :token_estimate, :embedding_model, :embedding_vector, :metadata)"
        );
        $statement->execute([
            'document_id' => $documentId,
            'chunk_index' => $chunkIndex,
            'content' => $content,
            'token_estimate' => max(1, (int) ceil(mb_strlen($content) / 4)),
            'embedding_model' => $embeddingModel,
            'embedding_vector' => json_encode(array_values($embedding), JSON_THROW_ON_ERROR),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param array<int, float> $queryEmbedding
     * @return array<int, array<string, mixed>>
     */
    public function search(int $botId, array $queryEmbedding, int $topK): array
    {
        $statement = $this->db->prepare(
            "SELECT kc.id,
                    kc.chunk_index,
                    kc.content,
                    kc.embedding_vector,
                    kd.title AS document_title
             FROM knowledge_chunks kc
             INNER JOIN knowledge_documents kd ON kd.id = kc.document_id
             WHERE kd.bot_id = :bot_id
               AND kd.status = 'indexed'
             ORDER BY kc.id DESC
             LIMIT 500"
        );
        $statement->execute(['bot_id' => $botId]);

        $scored = [];
        foreach ($statement->fetchAll() as $row) {
            $embedding = json_decode((string) $row['embedding_vector'], true);
            if (!is_array($embedding)) {
                continue;
            }

            $row['score'] = VectorMath::cosine($queryEmbedding, array_map('floatval', $embedding));
            unset($row['embedding_vector']);
            $scored[] = $row;
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }
}
