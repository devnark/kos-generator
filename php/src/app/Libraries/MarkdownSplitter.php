<?php

namespace App\Libraries;

class MarkdownSplitter
{
    /**
     * Разделяет текст на крупные родительские чанки, не разрывая абзацы и предложения
     */
    public function splitIntoParents(string $markdownText, int $maxParentLength = 3500): array
    {
        // Сначала делим текст на логические абзацы
        $paragraphs = preg_split('/(\r?\n){2,}/', $markdownText);
        $parents = [];

        $currentChunk = "";
        $currentHeaders = [];

        $tableHeaderLines = [];
        $insideTable = false;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;

            // 1. Отслеживаем заголовки Markdown для метаданных (#, ##, ###)
            if (preg_match('/^(#{1,3})\s+(.+)$/m', $paragraph, $matches)) {
                $level = strlen($matches[1]);
                $currentHeaders[$level] = trim($matches[2]);
                for ($i = $level + 1; $i <= 3; $i++) {
                    unset($currentHeaders[$i]);
                }
            }

            // 2. Проверяем, является ли абзац таблицей (находим шапку)
            $lines = explode("\n", $paragraph);
            foreach ($lines as $index => $line) {
                $trimmedLine = trim($line);
                if (strpos($trimmedLine, '|') !== false && isset($lines[$index + 1]) && strpos($lines[$index + 1], '|') !== false && strpos($lines[$index + 1], '-') !== false) {
                    if (!$insideTable) {
                        $insideTable = true;
                        $tableHeaderLines = [$line, $lines[$index + 1]];
                    }
                }
            }

            // 3. Если добавление ВСЕГО абзаца превысит лимит, обрабатываем его аккуратно
            if (strlen($currentChunk . "\n\n" . $paragraph) > $maxParentLength && !empty($currentChunk)) {

                // Если сам по себе абзац гигантский, бьем его по предложениям, а не пихаем целиком
                if (strlen($paragraph) > $maxParentLength) {
                    // Разделяем абзац на предложения (учитываем точки, знаки ! и ?, после которых идет пробел)
                    $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);

                    foreach ($sentences as $sentence) {
                        if (strlen($currentChunk . " " . $sentence) > $maxParentLength && !empty($currentChunk)) {
                            // Закрываем чанк
                            $parents[] = [
                                'text' => trim($currentChunk),
                                'metadata' => ['headers' => array_values($currentHeaders)]
                            ];
                            // Начинаем новый чанк (с шапкой таблицы, если мы внутри нее)
                            $currentChunk = ($insideTable && !empty($tableHeaderLines))
                                ? implode("\n", $tableHeaderLines) . "\n" . $sentence
                                : $sentence;
                        } else {
                            $currentChunk .= (empty($currentChunk) ? "" : " ") . $sentence;
                        }
                    }
                } else {
                    // Если абзац нормального размера, просто закрываем текущий чанк и переносим абзац на следующий
                    $parents[] = [
                        'text' => trim($currentChunk),
                        'metadata' => ['headers' => array_values($currentHeaders)]
                    ];

                    $currentChunk = ($insideTable && !empty($tableHeaderLines) && strpos($paragraph, '|') !== false && strpos($paragraph, $tableHeaderLines[0]) === false)
                        ? implode("\n", $tableHeaderLines) . "\n" . $paragraph
                        : $paragraph;
                }
            } else {
                // Если лимит не превышен, спокойно добавляем весь абзац
                $currentChunk .= (empty($currentChunk) ? "" : "\n\n") . $paragraph;
            }

            // Сброс флага таблицы, если в текущем абзаце нет признаков таблицы
            if ($insideTable && strpos($paragraph, '|') === false) {
                $insideTable = false;
                $tableHeaderLines = [];
            }
        }

        if (!empty($currentChunk)) {
            $parents[] = [
                'text' => trim($currentChunk),
                'metadata' => ['headers' => array_values($currentHeaders)]
            ];
        }

        return $parents;
    }

    /**
     * Нарезает родительский чанк на мелкие дочерние чанки строго по предложениям
     */
    public function splitIntoChildren(string $text, int $maxChildLength = 600, int $overlap = 150): array
    {
        // Разбиваем текст родителя на предложения
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $chunks = [];

        $currentChild = "";
        $history = []; // Очередь предложений для формирования оверлапа (перекрытия)

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;

            // Если добавление предложения взрывает лимит дочернего чанка
            if (strlen($currentChild . " " . $sentence) > $maxChildLength && !empty($currentChild)) {
                $chunks[] = trim($currentChild);

                // Формируем оверлап: собираем последние предложения из истории, пока они укладываются в лимит overlap
                $overlapText = "";
                $revHistory = array_reverse($history);
                $overlapSentences = [];

                foreach ($revHistory as $hSent) {
                    if (strlen($hSent . " " . $overlapText) < $overlap) {
                        array_unshift($overlapSentences, $hSent);
                        $overlapText = implode(" ", $overlapSentences);
                    } else {
                        break;
                    }
                }

                $currentChild = $overlapText . (empty($overlapText) ? "" : " ") . $sentence;
                $history = array_merge($overlapSentences, [$sentence]);
            } else {
                $currentChild .= (empty($currentChild) ? "" : " ") . $sentence;
                $history[] = $sentence;
            }
        }

        if (!empty($currentChild)) {
            $chunks[] = trim($currentChild);
        }

        return $chunks;
    }
}
