<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

final class VectorMath
{
    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    public static function cosine(array $a, array $b): float
    {
        $count = min(count($a), count($b));
        if ($count === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $dot += $a[$index] * $b[$index];
            $normA += $a[$index] ** 2;
            $normB += $b[$index] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
