<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use ChatbotPortal\AI\ProviderRouter;
use ChatbotPortal\Support\Env;

final class Retriever
{
    public function __construct(
        private readonly ProviderRouter $providers,
        private readonly MySqlVectorStore $vectorStore
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function retrieve(int $botId, string $query): array
    {
        $provider = Env::get('EMBEDDING_PROVIDER', 'openai') ?? 'openai';
        $model = Env::get('EMBEDDING_MODEL', 'text-embedding-3-small') ?? 'text-embedding-3-small';
        $topK = Env::int('RAG_TOP_K', 6);
        $embedding = $this->providers->embeddingClient($provider)->embed($query, ['embedding_model' => $model]);

        if ($embedding === []) {
            return [];
        }

        return $this->vectorStore->search($botId, $embedding, $topK);
    }
}
