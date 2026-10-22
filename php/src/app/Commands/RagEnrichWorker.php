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

                if (empty($taskData) || !isset($taskData['parent_text'])) {
                    CLI::write("[-] Некорректный формат задачи. Пропускаем.", "red");
                    $channel->basic_ack();
                    $msg->getDeliveryTag();
                    continue;
                }

                $parentText = $taskData['parent_text'];
                $metadata = $taskData['metadata'] ?? [];
                $professionId = (int)$taskData['profession_id'];
                $documentTitle = $taskData['document_title'];

                $time = self::stop('Общая подготовка прошла');
                CLI::write("Воркер активен. Слушает очередь 'document_enrichment_tasks' $time...", "yellow");
                // 1. Извлекаем хлебные крошки Markdown в качестве контекстного «якоря»
                $markdownBreadcrumbs = !empty($metadata['headers'])
                    ? implode(' -> ', $metadata['headers'])
                    : 'Общий раздел';

                CLI::write("[+] Анализируем чанк для: $documentTitle $markdownBreadcrumbs", "cyan");

                // 2. Строим жесткий JSON-промпт для локальной Qwen2.5:7b
                $prompt = "Ты — эксперт по стандартизации ГОСТ, СНиП и регламентам Таможенного союза.\n"
                    . "Проанализируй текст и составить метаданные для генерации будущих учебных тестов.\n"
                    . "Контекст расположения текста в документе: " . $markdownBreadcrumbs . "\n\n"
                    . "Инструкция по заполнению JSON:\n"
                    . "1. 'topic': Сформулируй понятную, конкретную тему для теста. Обязательно интегрируй контекст расположения (например, вместо 'Испытания' напиши 'Методы испытаний бетона на прочность').\n"
                    . "2. 'testable': Установи true, если текст содержит конкретные требования, цифры, допуски, формулы или правила, по которым можно составить вопрос. Установи false, если это вводные слова, область применения или список литературы.\n"
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

                // Запрос к бесплатной локальной Qwen2.5
                $aiResponse = $ollama->generate($prompt);

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
                $isTestable = true; // Сохраняем доступность для RAG поиска
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

// 6. Генерация дочерних чанков (Child) и их эмбеддингов
            $childTexts = $splitter->splitIntoChildren($parentText, 500, 100);
            foreach ($childTexts as $childText) {
                $embedding = $ollama->getEmbedding($childText);
                if (!empty($embedding)) {
                    // Усекаем вектор до 4000 элементов под ограничение HNSW halfvec в Postgres
                    $truncated = array_slice($embedding, 0, 4000);
                    $materialRepo->saveChild($parentId, $childText, $truncated);
                }
            }

// Успешное подтверждение задачи в RabbitMQ
            $channel->basic_ack($msg->getDeliveryTag());
            CLI::write("[+] Индексация чанка #{$parentId} завершена успешно.\n", "green");
        }

        $channel->close();
        $connection->close();
    }


    // Инициализация при первом обращении (или ручной сброс)
    private static function start() {
        self::$lastTime = microtime(true);
    }

    // Фиксирует время с МOМЕНТА ПОСЛЕДНЕГО ЗАМЕРА и сдвигает точку отсчета
    private static function stop(string $label) :string {
        // Если старт еще ни разу не вызывался, инициализируем его сейчас
        if (self::$lastTime === null) {
            self::start();
        }

        $currentTime = microtime(true);
        $duration = $currentTime - self::$lastTime;

        // Сдвигаем глобальную точку отсчета на текущее время для следующего шага
        self::$lastTime = $currentTime;

        // Красивое форматирование
        if ($duration < 1) {
            $result = round($duration * 1000, 2) . " мс";
        } else {
            $result = round($duration, 3) . " сек";
        }

        return "⏱️ Шаг [$label] занял: $result";
    }

}
