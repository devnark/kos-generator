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
                    $parentId = $materialRepo->saveParent([
                        'profession_id' => $professionId,
                        'document_title' => $documentTitle,
                        'full_text' => $parentText,
                        'chunk_summary' => $testTopic,
                        'test_topic' => $testTopic,
                        'is_testable' => $isTestable,
                        'complexity' => $complexity,
                        'keywords' => $keywords,
                        'meta_json' => json_encode($metadata)
                    ]);

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