<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use App\Libraries\OllamaClient;
use App\Repositories\MaterialRepository;
use App\Libraries\MarkdownSplitter;

class RagEnrichWorker extends BaseCommand
{
    protected $group = 'RAG';
    protected $name = 'rag:run-enrich-worker';
    protected $description = 'Фоновый воркер для гибридного обогащения документов (Markdown + Qwen2.5) и индексации в БД.';

    // Храним время последнего замера глобально в рамках процесса
    private static $lastTime = null;

    public function run(array $params)
    {
        self::start();
        CLI::write("=== Запуск RAG-воркера обогащения документов ===", "green");

        $db = \Config\Database::connect();
        $ollama = new OllamaClient();
        $materialRepo = new MaterialRepository();
        $splitter = new MarkdownSplitter();

        // Подключение к RabbitMQ
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();

        // Регистрируем новую выделенную очередь для обогащения
        $channel->queue_declare('document_enrichment_tasks', false, true, false, false);

        CLI::write("Воркер активен. Слушает очередь 'document_enrichment_tasks'...", "yellow");

        while (true) {
            $msg = $channel->basic_get('document_enrichment_tasks');

            if ($msg === null) {
                sleep(2);
                continue;
            }

            try {
                $taskData = json_decode($msg->body, true);

                if (empty($taskData)) {
                    CLI::write("[-] Некорректный формат задачи. Пропускаем.", "red");
                    $channel->basic_ack($msg->getDeliveryTag());
                    continue;
                }

                $professionId = (int)$taskData['profession_id'];
                $documentTitle = $taskData['document_title'];
                $markdownTable = $taskData['markdown_table'] ?? '';
                $fullText = $taskData['full_text'] ?? '';

                // --- НАЧАЛО БЛОКА МУЛЬТИ-ТАБЛИЧНОЙ ДЕДУПЛИКАЦИИ ---
                $contentText = $this->multiTableDeduplicate($markdownTable, $fullText);
                // --- КОНЕЦ БЛОКА МУЛЬТИ-ТАБЛИЧНОЙ ДЕДУПЛИКАЦИИ ---

                if (empty(trim($contentText))) {
                    CLI::write("[-] Получен пустой текст документа. Пропускаем.", "red");
                    $channel->basic_ack($msg->getDeliveryTag());
                    continue;
                }

                $time = self::stop('Общая подготовка прошла');
                CLI::write("Воркер активен. Слушает очередь 'document_enrichment_tasks' $time...", "yellow");

                // НАШЕ ИСПРАВЛЕНИЕ АНОМАЛИИ РАЗМЕРА: Теперь мы нарезаем объединенный и очищенный текст
                // на адекватные родительские чанки прямо здесь, перед отправкой в ИИ
                $parentChunks = $splitter->splitIntoParents($contentText, 3500);

                foreach ($parentChunks as $parentData) {
                    $this->getOllamaDataAndSave($parentData, $documentTitle, $ollama, $professionId, $materialRepo, $splitter);
                }

                // Успешное подтверждение всей задачи в RabbitMQ после обработки всех чанков документа
                $channel->basic_ack($msg->getDeliveryTag());
                CLI::write("[===>] Полная обработка документа '{$documentTitle}' завершена.\n", "green");
            } catch (\Exception $e) {
                CLI::write("[-] Критическая ошибка воркера: " . $e->getMessage(), "red");
                $channel->basic_nack($msg->getDeliveryTag(), false, true);
            }
        }
        $channel->close();
        $connection->close();
    }


    private function getOllamaDataAndSave(array $parentData, string $documentTitle, OllamaClient $ollama, $professionId, MaterialRepository $materialRepo, MarkdownSplitter $splitter)
    {
        $parentText = $parentData['text'];
        $metadata = $parentData['metadata'] ?? [];

        // 1. Извлекаем хлебные крошки Markdown в качестве контекстного «якоря»
        $markdownBreadcrumbs = !empty($metadata['headers'])
            ? implode(' -> ', $metadata['headers'])
            : 'Общий раздел';

        CLI::write("[+] Анализируем чанк для: $documentTitle -> $markdownBreadcrumbs", "cyan");

        $messages = [
        [
            'role' => 'system',
            'content' => "Ты — эксперт по стандартизации ГОСТ, СНиП и регламентам Таможенного союза.\n"
                . "Проанализируй текст и составь метаданные для генерации будущих учебных тестов.\n\n"
                . "ГЛАВНОЕ ПРАВИЛО ДЛЯ ФЛАГА 'testable':\n"
                . "- Установи \"testable\": true, если текст содержит ХОТЯ БЫ ОДНО из следующего: конкретные требования, последовательность действий / шаги технологического процесса (например: '1 Выполнить...', '2 Очистить...'), названия инструментов, оборудования, косметических препаратов, цифры, допуски, формулы или правила. Таблицы и технологические карты ВСЕГДА являются testable = true, даже если содержат номера страниц или колонтитулы.\n"
                . "- Установи \"testable\": false ТОЛЬКО если это исключительно список литературы, библиография, перечень законов или оглавление, где нет вообще никаких технических инструкций.\n\n"
                . "Ответь СТРОГО в формате JSON по следующей схеме (без каких-либо оберток вроде ```json):\n"
                . "{\n"
                . "  \"topic\": \"строка (конкретная тема для теста)\",\n"
                . "  \"testable\": true/false,\n"
                . "  \"complexity\": \"easy/medium/hard\",\n"
                . "  \"keywords\": [\"термин1\", \"термин2\"]\n"
                . "}"
        ],
        [
            'role' => 'user',
            'content' => "Контекст расположения текста в документе: " . $markdownBreadcrumbs . "\n\n"
                . "Инструкция по заполнению JSON полей:\n"
                . "1. 'topic': Сформулируй понятную, конкретную тему для теста. Обязательно интегрируй контекст расположения (например, вместо 'Испытания' напиши 'Методы испытаний бетона на прочность').\n"
                . "2. 'complexity': Оцени сложность материала ('easy', 'medium', 'hard').\n"
                . "3. 'keywords': Извлеки 2-3 важных технических термина из текста.\n\n"
                . "ТЕКСТ ДЛЯ АНАЛИЗА:\n" . mb_substr($parentText, 0, 2000)
        ]
    ];


        try {
            // ВТОРОЙ ПАРСИНГ: Декодируем сам JSON с метаданными теста, который сгенерировала модель
            $aiData = $ollama->getDataFoRag($messages);

            // 3. ВЗАИМНЫЙ КОНТРОЛЬ: Проверка качества и структуры ответа от 7B-модели
            if (json_last_error() === JSON_ERROR_NONE && isset($aiData['topic'])) {
                $testTopic = trim($aiData['topic']);
                $isTestable = (bool)$aiData['testable'];
                $complexity = in_array($aiData['complexity'], ['easy', 'medium', 'hard']) ? $aiData['complexity'] : 'medium';
                $keywords = json_encode($aiData['keywords'] ?? [], JSON_UNESCAPED_UNICODE);
                CLI::write("[Qwen УСПЕХ] Тема: '{$testTopic}' [Сложность: {$complexity}]", "green");
            } else {
                throw new \Exception("Модель прислала невалидную структуру полей JSON.");
            }

        } catch (\Exception $e) {
            // 4. ФОЛБЭК: Если ИИ или парсинг дали сбой, данные Markdown-структурирования страхуют систему
            CLI::write("[ФОЛБЭК] Ошибка ИИ ({$e->getMessage()}). Используем структуру Markdown.", "red");

            $testTopic = !empty($metadata['headers']) ? end($metadata['headers']) : "Раздел из " . $documentTitle;
            $isTestable = true;
            $complexity = 'medium';
            $keywords = json_encode([]);
        }

        // Нет смысла в слишком коротких
        if (mb_strlen(str_replace(' ', '', $parentText)) < 150) {
            CLI::write("[! ПРЕДОХРАНИТЕЛЬ] Чанк слишком короткий для теста. Принудительно ставим FALSE.", "yellow");
            $isTestable = false;
        }

        // 5. Запись обогащенного Parent-чанка в базу данных
        $row = [
            'profession_id' => $professionId,
            'document_title' => $documentTitle,
            'full_text' => $parentText,
            'chunk_summary' => $testTopic,
            'test_topic' => $testTopic,
            'is_testable' => $isTestable,
            'complexity' => $complexity,
            'keywords' => $keywords,
            'meta_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ];

        if ($row['is_testable'] == false) {
            CLI::write("Не для тестов.", "red");
            print_r($row);
        }
        // 5. Запись обогащенного Parent-чанка в базу данных (остается как у вас)
        $parentId = $materialRepo->saveParent($row);

        // 6. ГЕНЕРАЦИЯ ДОЧЕРНИХ ЧАНКОВ (CHILD) И ИХ ЭМБЕДДИНГОВ
        $childTexts = [];

        // Проверяем: если ИИ определил тему как табличную или в тексте parent-чанка
        // есть явные следы табличной верстки / каши из pdftotext (много пробелов подряд)
        $hasTableSigns = (strpos($parentText, '   ') !== false || preg_match('/Последовательность|Технологический/ui', $parentText));

        if ($hasTableSigns && !empty($parentData['table_chunks'])) {
            // КАРДИНАЛЬНОЕ РЕШЕНИЕ: Для табличных разделов берем ТОЛЬКО чистые линеаризованные чанки из Tabula
            CLI::write("[!] Обнаружена табличная структура. Используем чистые чанки Tabula.", "yellow");
            $childTexts = $parentData['table_chunks'];
        } else {
            // Для обычного текстового контента (введения, описания, теории) используем стандартный сплиттер
            CLI::write("[!] Обычный текст. Запускаем сплиттер по предложениям.", "cyan");
            $childTexts = $splitter->splitIntoChildren($parentText, 600, 150);
        }

        // Сохраняем чанки и генерируем для них эмбеддинги
        // 6. Генерация дочерних чанков (Child) и их эмбеддингов
        foreach ($childTexts as $childText) {
            if (mb_strlen(str_replace(' ', '', $childText)) < 50) {
                continue;
            }

            // Проверяем: просочилась ли сырая строка с дрейфом колонок (10+ пробелов подряд)
            if (preg_match('/\s{10,}/u', $childText)) {
                CLI::write("[!] Корректируем грязный чанк с дрейфом колонок...", "yellow");

                // Вызываем метод-спасатель вместо continue
                $childText = $this->healDriftedChunk($childText, $documentTitle, $testTopic);
            }

            // Отправляем на векторизацию (либо чистый чанк, либо успешно вылеченный)
            $embedding = $ollama->getEmbedding($childText);

            if (!empty($embedding)) {
                $truncated = array_slice($embedding, 0, 4000);
                $materialRepo->saveChild($parentId, $childText, $truncated);
            }
        }

        CLI::write("[+] Индексация чанка #{$parentId} завершена. Создано Child-записей: " . count($childTexts), "green");
    }

    private function getOllamaDataAndSave2(array $parentData, string $documentTitle, OllamaClient $ollama, $professionId, $materialRepo, $splitter)
    {
        $parentText = $parentData['text'];
        $metadata = $parentData['metadata'] ?? [];

        // 1. Извлекаем хлебные крошки Markdown в качестве контекстного «якоря»
        $markdownBreadcrumbs = !empty($metadata['headers'])
            ? implode(' -> ', $metadata['headers'])
            : 'Общий раздел';

        CLI::write("[+] Анализируем чанк для: $documentTitle -> $markdownBreadcrumbs", "cyan");

        // 2. Строим жесткий JSON-промпт для локальной Qwen2.5:7b
        $prompt = "Ты — эксперт по стандартизации ГОСТ, СНиП и регламентам Таможенного союза.\n"
            . "Проанализируй текст и составить метаданные для генерации будущих учебных тестов.\n"
            . "Контекст расположения текста в документе: " . $markdownBreadcrumbs . "\n\n"
            . "Инструкция по заполнению JSON:\n"
            . "1. 'topic': Сформулируй понятную, конкретную тему для теста. Обязательно интегрируй контекст расположения (например, вместо 'Испытания' напиши 'Методы испытаний бетона на прочность').\n"
            . "2. 'testable': Установи true, if текст содержит конкретные требования, цифры, допуски, формулы или правила, по которым можно составить вопрос. Установи false, если это вводные слова, область применения или список литературы.\n"
            . "3. 'complexity': Оцени сложность материала ('easy', 'medium', 'hard').\n"
            . "4. 'keywords': Извлеки 2-3 важных технических термина из текста.\n\n"
            . "Ответь строго в формате JSON без каких-либо вводных слов и без оберток вроде ```json:\n"
            . "{\n"
            . "  \"topic\": \"строка\",\n"
            . "  \"testable\": true/false,\n"
            . "  \"complexity\": \"easy/medium/hard\",\n"
            . "  \"keywords\": [\"термин1\", \"термин2\"]\n"
            . "}\n\n"
            . "ТЕКСТ ДЛЯ АНАЛИЗА:\n" . mb_substr($parentText, 0, 2000);

        try {
            // Запрос к бесплатной локальной Qwen2.5
            $aiResponse = $ollama->generate($prompt);
//            $aiResponse = $ollama->generateChat($prompt);
            self::stop('$aiResponse = $ollama->generate($prompt)');

            // Самокоррекция: вырезаем JSON из возможных Markdown-тегов
            $cleanJson = trim(preg_replace('/^```json|```$/m', '', $aiResponse));
            $aiData = json_decode($cleanJson, true);

            // 3. ВЗАИМНЫЙ КОНТРОЛЬ: Проверка качества ответа от 7B-модели
            if (json_last_error() === JSON_ERROR_NONE && isset($aiData['topic'])) {
                $testTopic = trim($aiData['topic']);
                $isTestable = (bool)$aiData['testable'];
                $complexity = in_array($aiData['complexity'], ['easy', 'medium', 'hard']) ? $aiData['complexity'] : 'medium';
                $keywords = json_encode($aiData['keywords'] ?? []);
                CLI::write("[Qwen УСПЕХ] Тема: '{$testTopic}' [Сложность: {$complexity}]", "green");
            } else {
                throw new \Exception("Qwen выдала невалидный JSON-формат");
            }

        } catch (\Exception $e) {
            // 4. ФОЛБЭК: Если Qwen дала сбой, данные Markdown-структурирования страхуют систему
            CLI::write("[ФОЛБЭК] Ошибка ИИ ({$e->getMessage()}). Используем структуру Markdown.", "red");

            $testTopic = !empty($metadata['headers']) ? end($metadata['headers']) : "Раздел из " . $documentTitle;
            $isTestable = true;
            $complexity = 'medium';
            $keywords = json_encode([]);
        }

        // 5. Запись обогащенного Parent-чанка в базу данных
        $row = [
            'profession_id' => $professionId,
            'document_title' => $documentTitle,
            'full_text' => $parentText,
            'chunk_summary' => $testTopic,
            'test_topic' => $testTopic,
            'is_testable' => $isTestable,
            'complexity' => $complexity,
            'keywords' => $keywords,
            'meta_json' => json_encode($metadata)
        ];
        $parentId = $materialRepo->saveParent($row);

        // 6. Generation дочерних чанков (Child) и их эмбеддингов
        $childTexts = $splitter->splitIntoChildren($parentText, 500, 100);
        foreach ($childTexts as $childText) {
            $embedding = $ollama->getEmbedding($childText);
            if (!empty($embedding)) {
                $truncated = array_slice($embedding, 0, 4000);
                $materialRepo->saveChild($parentId, $childText, $truncated);
            }
        }
        CLI::write("[+] Индексация чанка #{$parentId} завершена успешно.", "green");

    }

    private function multiTableDeduplicate($markdownTable, $fullText)
    {
        // --- НАЧАЛО БЛОКА МУЛЬТИ-ТАБЛИЧНОЙ ДЕДУПЛИКАЦИИ ---
        // 1. Очищаем системный мусор, колонтитулы и библиографию
        $cleanedFullText = preg_replace('/(Система ГАРАНТ|Национальный стандарт|ГОСТ Р|Карта \d+|\b\d+\/\d+\b|Библиография)/iu', '', $fullText);
        $cleanedFullText = preg_replace('/^/u', '', $cleanedFullText);

        if (!empty(trim($markdownTable))) {
            // Создаем плоский массив очищенных строк из Markdown-таблицы для быстрого поиска
            $markdownRows = explode("\n", $markdownTable);
            $normalizedMarkdownAtlas = [];

            foreach ($markdownRows as $mLine) {
                // Убираем пайпы и лишние пробелы, оставляя только чистую суть строки
                $pureText = preg_replace('/\s+/', '', str_replace('|', '', $mLine));
                if (!empty($pureText) && strpos($pureText, '---') === false) {
                    // Сохраняем первые 30 символов нормализованной строки
                    $normalizedMarkdownAtlas[] = mb_substr($pureText, 0, 30);
                }
            }

            $lines = explode("\n", $cleanedFullText);
            $outsideTableLines = [];
            $insideTableZone = false;

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                // Нормализуем текущую сырую строку для сверки с атласом Табулы
                $normalizedLine = preg_replace('/\s+/', '', $trimmedLine);
                $shortNormalized = mb_substr($normalizedLine, 0, 30);

                if (empty($shortNormalized)) {
                    // Если мы были в режиме таблицы и поймали пустую строку, проверяем, не закончилась ли она
                    if ($insideTableZone && !preg_match('/\s{4,}/', $line)) {
                        $insideTableZone = false;
                    }
                    $outsideTableLines[] = $line;
                    continue;
                }

                // Проверяем, есть ли эта сырая строка в данных, которые уже распарсила Tabula
                $isDuplicate = false;
                foreach ($normalizedMarkdownAtlas as $atlasChunk) {
                    if (strpos($shortNormalized, $atlasChunk) !== false || strpos($atlasChunk, $shortNormalized) !== false) {
                        $isDuplicate = true;
                        break;
                    }
                }

                if ($isDuplicate) {
                    $insideTableZone = true; // Включаем контекст нахождения внутри таблицы
                    continue; // Вырезаем дубликат!
                }

                // Если мы находимся внутри зоны таблицы, но строка не совпала (например, многострочный перенос)
                if ($insideTableZone) {
                    // Проверяем, есть ли признаки структуры таблицы (большие отступы пробелов)
                    if (preg_match('/\s{3,}/', $line) || preg_match('/^\d+\s+/', $trimmedLine)) {
                        continue; // Это всё еще кусок сырой таблицы, удаляем его
                    } else {
                        $insideTableZone = false; // Таблица явно закончилась, пошел текст
                    }
                }

                $outsideTableLines[] = $line;
            }

            // Соединяем: Идеальный Markdown всех найденных таблиц + текстовое окружение без дубликатов
            $contentText = $markdownTable . "\n\n" . implode("\n", $outsideTableLines);
        } else {
            $contentText = $cleanedFullText;
        }

        // Приводим в порядок отступы
        $contentText = preg_replace('/(\n{3,})/', "\n\n", $contentText);
        // --- КОНЕЦ БЛОКА МУЛЬТИ-ТАБЛИЧНОЙ ДЕДУПЛИКАЦИИ ---

        return $contentText;
    }


    /**
     * Преобразует грязный чанк с дрейфом колонок (из pdftotext) в структурированную псевдотаблицу.
     */
    private function healDriftedChunk(string $childText, string $documentTitle, string $testTopic): string
    {
        // 1. Разбиваем чанк на отдельные строки
        $lines = preg_split('/\r\n|\r|\n/', $childText);
        $healedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Удаляем колонтитулы ГОСТа и маркеры страниц, если они прилипли к строке
            $line = preg_replace('/(РФ\s+)?72319-2025\s+"Услуги бытовые\..*?"/ui', '', $line);
            $line = preg_replace('/\d+\/\s*.*/u', '', $line);

            // Заменяем множественные пробелы (признак колонок в layout) на красивый разделитель
            $healedLine = preg_replace('/\s{3,}/u', ' | ', $line);

            // Схлопываем случайные двойные пробелы внутри самих слов
            $healedLine = trim(preg_replace('/\s+/', ' ', $healedLine));

            if (mb_strlen($healedLine) > 10 && $healedLine !== '|') {
                $healedLines[] = $healedLine;
            }
        }

        // 2. Собираем обратно, склеивая строки через перенос, чтобы сохранить табличный вид шага
        $cleanContent = implode("\n", $healedLines);

        // 3. Возвращаем полностью обогащенный контекстом чанк
        return "Документ: {$documentTitle}\nТема: {$testTopic}\nДанные:\n{$cleanContent}";
    }


    private static function start()
    {
        self::$lastTime = microtime(true);
    }

    private static function stop(string $label)
    {
        if (self::$lastTime === null) {
            self::start();
        }
        $currentTime = microtime(true);
        $duration = $currentTime - self::$lastTime;
        self::$lastTime = $currentTime;
        if ($duration < 1) {
            return round($duration * 1000, 2) . " мс (" . $label . ")";
        } else {
            return round($duration, 2) . " сек (" . $label . ")";
        }
    }
}