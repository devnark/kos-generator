<?php

namespace App\Libraries;

class TextSplitter
{
    /**
     * Разбивает текст на куски (чанки) с нахлестом
     */
    public function splitText(string $text, int $chunkSize = 1200, int $chunkOverlap = 200): array
    {
        // Очищаем текст от множественных пробелов и странных символов
        $text = preg_replace('/\s+/', ' ', $text);
        $textLength = mb_strlen($text);
        $chunks = [];
        $start = 0;

        while ($start < $textLength) {
            // Берем примерный кусок текста
            $chunk = mb_substr($text, $start, $chunkSize);

            // Если мы не в самом конце текста, попытаемся красиво обрезать по точке или пробелу,
            // чтобы не рвать слова на середине
            if ($start + $chunkSize < $textLength) {
                $lastDot = mb_strrpos($chunk, '. ');
                $lastSpace = mb_strrpos($chunk, ' ');

                // Ориентируемся на точку, если она близко к концу чанка, иначе на пробел
                if ($lastDot !== false && $lastDot > $chunkSize * 0.8) {
                    $chunk = mb_substr($chunk, 0, $lastDot + 1);
                } elseif ($lastSpace !== false) {
                    $chunk = mb_substr($chunk, 0, $lastSpace);
                }
            }

            $currentChunkText = trim($chunk);
            if (!empty($currentChunkText)) {
                $chunks[] = $currentChunkText;
            }

            // Сдвигаем указатель вперед с учетом нахлеста (overlap)
            $start += mb_strlen($chunk) - $chunkOverlap;

            // Защита от бесконечного цикла, если шаг сдвига стал равен нулю
            if (mb_strlen($chunk) <= $chunkOverlap) {
                $start += $chunkSize;
            }

        }

        return $chunks;
    }
}
