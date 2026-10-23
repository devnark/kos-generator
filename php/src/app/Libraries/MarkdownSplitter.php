<?php

namespace App\Libraries;

class MarkdownSplitter
{
    public function splitIntoParents(string $markdownText, int $maxParentLength = 3500): array
    {
        // УМНЫЙ ХАК: Если Tabula склеила разные таблицы в один монолит,
        // принудительно разделяем их двойным переносом перед парсингом, ориентируясь на повторение шапки
        $markdownText = preg_replace('/(\|\s*Последовательность\s*\|)/iu', "\n\n$1", $markdownText);
        $markdownText = preg_replace('/(\|\s*Содержание\s*технологического\s*\|)/iu', "\n\n$1", $markdownText);

        // Безопасно делим текст на абзацы (теперь склеенные таблицы гарантированно разобьются)
        $paragraphs = preg_split('/(\r?\n){2,}/', $markdownText);
        $parents = [];

        $currentChunk = "";
        $currentHeaders = [];

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

            // 2. Если САМ ПО СЕБЕ абзац/таблица больше лимита, жестко режем его по строкам
            if (strlen($paragraph) > $maxParentLength) {

                // Если в буфере уже что-то скопилось, закрываем текущий чанк
                if (!empty(trim($currentChunk))) {
                    $parents[] = [
                        'text' => trim($currentChunk),
                        'metadata' => ['headers' => array_values($currentHeaders)]
                    ];
                    $currentChunk = "";
                }

                $subLines = explode("\n", $paragraph);
                foreach ($subLines as $subLine) {
                    // Строгая проверка лимита сборки строки
                    if (strlen($currentChunk . "\n" . $subLine) > $maxParentLength) {
                        if (!empty(trim($currentChunk))) {
                            $parents[] = [
                                'text' => trim($currentChunk),
                                'metadata' => ['headers' => array_values($currentHeaders)]
                            ];
                        }
                        $currentChunk = $subLine;
                    } else {
                        $currentChunk .= (empty($currentChunk) ? "" : "\n") . $subLine;
                    }
                }

                // Важно: закрываем чанк после окончания гигантской таблицы, чтобы она не перетекла в следующий текст
                if (!empty(trim($currentChunk))) {
                    $parents[] = [
                        'text' => trim($currentChunk),
                        'metadata' => ['headers' => array_values($currentHeaders)]
                    ];
                    $currentChunk = "";
                }
                continue;
            }

            // 3. Стандартная логика для абзацев нормального размера
            if (strlen($currentChunk . "\n\n" . $paragraph) > $maxParentLength) {
                if (!empty(trim($currentChunk))) {
                    $parents[] = [
                        'text' => trim($currentChunk),
                        'metadata' => ['headers' => array_values($currentHeaders)]
                    ];
                }
                $currentChunk = $paragraph;
            } else {
                $currentChunk .= (empty($currentChunk) ? "" : "\n\n") . $paragraph;
            }
        }

        // Сохраняем финальный остаток текста
        if (!empty(trim($currentChunk))) {
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

    /**
     * Конвертирует CSV файл от Tabula в валидную Markdown-таблицу
     */
    public function convertCsvToMarkdown(string $csvPath): string
    {
        $markdownLines = [];
        $isFirstRow = true;

        if (!file_exists($csvPath) || filesize($csvPath) === 0) {
            return "";
        }

        if (($handle = fopen($csvPath, "r")) !== false) {
            while (($row = fgetcsv($handle, 0, ",")) !== false) {
                // Очищаем ячейки от случайных внутренних переносов строк
                $cleanRow = array_map(function($cell) {
                    return str_replace(["\r", "\n"], " ", trim($cell));
                }, $row);

                $markdownLines[] = "| " . implode(" | ", $cleanRow) . " |";

                if ($isFirstRow) {
                    $markdownLines[] = "|" . str_repeat(" --- |", count($cleanRow));
                    $isFirstRow = false;
                }
            }
            fclose($handle);
        }
        return implode("\n", $markdownLines);
    }



    public function convertTextTablesToMarkdown(string $text): string
    {
        $lines = explode("\n", $text);
        $processedLines = [];

        foreach ($lines as $line) {
            // Если строка пустая, оставляем как есть
            if (trim($line) === '') {
                $processedLines[] = '';
                continue;
            }

            // Ищем места, где идет 3 или более пробелов подряд — это разделители колонок в pdftotext
            // Заменяем их на " | "
            $cleanedLine = preg_replace('/\s{3,}/', ' | ', trim($line));

            // Если в строке образовались колонки (есть хотя бы один разделитель '|')
            if (strpos($cleanedLine, '|') !== false) {
                // Оборачиваем строку в пайпы для валидного Markdown: | колонка1 | колонка2 |
                $processedLines[] = '| ' . $cleanedLine . ' |';
            } else {
                $processedLines[] = $line;
            }
        }

        // Собираем текст обратно
        $markdownText = implode("\n", $processedLines);

        // Добавляем разделитель шапки (---), если нашли первую строку таблицы
        // Это нужно, чтобы ваш splitIntoParents корректно распознал таблицу по условию наличия знака '-'
        $markdownLines = explode("\n", $markdownText);
        $finalLines = [];
        $tableStarted = false;

        foreach ($markdownLines as $index => $line) {
            $finalLines[] = $line;

            // Если это первая строка таблицы (содержит |) и следующая тоже содержит |
            if (!$tableStarted && strpos($line, '|') !== false && isset($markdownLines[$index + 1]) && strpos($markdownLines[$index + 1], '|') !== false) {
                // Считаем количество колонок по разделителям
                $colCount = substr_count($line, '|');
                if ($colCount > 0) {
                    // Генерируем строку вида | --- | --- | --- |
                    $separator = '|' . str_repeat(' --- |', $colCount - 1);
                    $finalLines[] = $separator;
                    $tableStarted = true;
                }
            }

            if ($tableStarted && strpos($line, '|') === false) {
                $tableStarted = false; // Таблица закончилась
            }
        }

        return implode("\n", $finalLines);
    }

    public function convertGostTableToMarkdown(string $text): string
    {
        $lines = explode("\n", $text);
        $tableRows = [];
        $currentRow = null;
        $insideTable = false;

        // Массив для хранения динамических координат колонок
        $colPositions = [
            'step' => 0,
            'process' => null,
            'materials' => null,
            'tools' => null
        ];

        $headers = ["Последовательность действий", "Содержание технологического процесса", "Препараты/материалы", "Инструменты/оборудование"];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;

            // 1. Фильтрация системного мусора ГОСТ / ГАРАНТ
            if (
                preg_match('/(Система ГАРАНТ|Национальный стандарт|ГОСТ Р|Карта \d+|\b\d+\/\d+\b)/iu', $trimmed) ||
                preg_match('/^/u', $line)
            ) {
                continue;
            }

            // 2. ДИНАМИЧЕСКИЙ ПОИСК КООРДИНАТ КОЛОНОК ПО ШАПКЕ ТАБЛИЦЫ
            if (strpos($trimmed, 'Последовательность') !== false && strpos($trimmed, 'технологического') !== false) {

                // Находим символьные индексы начала каждой колонки в текущей строке шапки
                $posProcess = mb_strpos($line, 'Содержание');
                if ($posProcess === false) $posProcess = mb_strpos($line, 'технологического');

                $posMaterials = mb_strpos($line, 'Препараты');

                $posTools = mb_strpos($line, 'Инструменты');
                if ($posTools === false) $posTools = mb_strpos($line, 'обору');

                // Запоминаем найденные координаты (если они успешно определились)
                $colPositions['process'] = $posProcess !== false ? $posProcess : 23;
                $colPositions['materials'] = $posMaterials !== false ? $posMaterials : 58;
                $colPositions['tools'] = $posTools !== false ? $posTools : 83;

                $insideTable = true;
                continue; // Пропускаем строку шапки, так как мы сгенерируем ее сами
            }

            if (!$insideTable) {
                $tableRows[] = ['type' => 'text', 'content' => $line];
                continue;
            }

            // Вычисляем длину шагов для безопасной нарезки через mb_substr
            $lenCol1 = $colPositions['process'] - $colPositions['step'];
            $lenCol2 = $colPositions['materials'] - $colPositions['process'];
            $lenCol3 = $colPositions['tools'] - $colPositions['materials'];

            // Дополняем строку пробелами справа, чтобы mb_substr не вернул false на коротких строках
            $paddedLine = str_pad($line, 150, " ", STR_PAD_RIGHT);

            // 3. Динамическая нарезка строки на основе вычисленных координат
            $col1 = trim(mb_substr($paddedLine, $colPositions['step'], $lenCol1));
            $col2 = trim(mb_substr($paddedLine, $colPositions['process'], $lenCol2));
            $col3 = trim(mb_substr($paddedLine, $colPositions['materials'], $lenCol3));
            $col4 = trim(mb_substr($paddedLine, $colPositions['tools']));

            // Проверяем, начинается ли первая колонка с цифры (новый шаг таблицы)
            if (preg_match('/^\d+/', $col1)) {
                if ($currentRow !== null) {
                    $tableRows[] = ['type' => 'row', 'content' => $currentRow];
                }
                $currentRow = [
                    'step' => $col1,
                    'process' => $col2,
                    'materials' => $col3 ?: '-',
                    'tools' => $col4 ?: '-'
                ];
            } else {
                // Если это многострочное продолжение предыдущей ячейки
                if ($currentRow !== null) {
                    if (!empty($col1)) $currentRow['step'] .= ' ' . $col1;
                    if (!empty($col2)) $currentRow['process'] .= ' ' . $col2;

                    if (!empty($col3) && $col3 !== '-') {
                        $currentRow['materials'] = ($currentRow['materials'] === '-') ? $col3 : $currentRow['materials'] . ' ' . $col3;
                    }
                    if (!empty($col4) && $col4 !== '-') {
                        $currentRow['tools'] = ($currentRow['tools'] === '-') ? $col4 : $currentRow['tools'] . ' ' . $col4;
                    }
                } else {
                    $tableRows[] = ['type' => 'text', 'content' => $line];
                }
            }
        }

        if ($currentRow !== null) {
            $tableRows[] = ['type' => 'row', 'content' => $currentRow];
        }

        // 4. Генерация валидного Markdown
        $output = [];
        $tableOpened = false;

        foreach ($tableRows as $item) {
            if ($item['type'] === 'text') {
                if ($tableOpened) {
                    $tableOpened = false;
                    $output[] = "";
                }
                $output[] = $item['content'];
            }

            if ($item['type'] === 'row') {
                if (!$tableOpened) {
                    $output[] = "";
                    $output[] = "| " . implode(" | ", $headers) . " |";
                    $output[] = "| " . implode(" | ", array_fill(0, count($headers), "---")) . " |";
                    $tableOpened = true;
                }

                $row = $item['content'];
                $output[] = sprintf("| %s | %s | %s | %s |", $row['step'], $row['process'], $row['materials'], $row['tools']);
            }
        }

        return implode("\n", $output);
    }

}
