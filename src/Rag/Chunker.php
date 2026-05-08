<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

final class Chunker
{
    /**
     * @return array<int, string>
     */
    public function chunks(string $text, int $targetTokens = 650, int $overlapTokens = 90): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $targetWords = max(80, (int) floor($targetTokens * 0.75));
        $overlapWords = max(0, (int) floor($overlapTokens * 0.75));
        $step = max(1, $targetWords - $overlapWords);
        $chunks = [];

        for ($offset = 0; $offset < count($words); $offset += $step) {
            $slice = array_slice($words, $offset, $targetWords);
            if ($slice === []) {
                break;
            }

            $chunks[] = implode(' ', $slice);
            if ($offset + $targetWords >= count($words)) {
                break;
            }
        }

        return $chunks;
    }
}
