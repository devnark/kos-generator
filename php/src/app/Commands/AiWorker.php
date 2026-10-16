<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use App\Libraries\OllamaClient;
use App\Repositories\MaterialRepository;
use App\Repositories\FewShotRepository;

class AiWorker extends BaseCommand
{
    protected $group       = 'AI';
    protected $name        = 'ai:worker';
    protected $description = 'Простой линейный воркер для генерации тестов с источниками';

    public function run(array $params)
    {
        // Отключаем ограничение времени выполнения скрипта для CLI
        set_time_limit(0);

        CLI::write("Запуск ИИ-воркера...", "green");

        // 1. Инициализируем всё один раз на самом верху
        $db           = \Config\Database::connect();
        $ollama       = new OllamaClient();
        $materialRepo = new MaterialRepository();
        $fewShotRepo  = new FewShotRepository();

        // 2. Подключаемся к RabbitMQ
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();
        $channel->queue_declare('test_generation_tasks', false, true, false, false);

        CLI::write("Воркер активен и слушает очередь...", "yellow");

        // 3. Главный бесконечный цикл
        while (true) {
            $msg = $channel->basic_get('test_generation_tasks');

            if ($msg === null) {
                sleep(2);
                continue;
            }

            // --- НАЧАЛО ОБРАБОТКИ ЗАДАЧИ ---
            $taskData = json_decode($msg->body, true);
            $taskId   = $taskData['task_id'];

            CLI::write("\n=== Найдена задача ID: {$taskId} ===", "blue");

            // Меняем статус в Postgres
            $db->table('generation_tasks')->where('id', $taskId)->update(['status' => 'processing']);
            $task = $db->table('generation_tasks')->where('id', $taskId)->get()->getRowArray();

            try {
                // Шаг 1: Поиск контекста в методичке (RAG)
                CLI::write("Шаг 1: Семантический поиск (RAG)...", "cyan");
                $themeEmbedding = $ollama->getEmbedding($task['topic']);

                if (empty($themeEmbedding)) {
                    throw new \Exception("Не удалось сгенерировать эмбеддинг для темы. Проверь Ollama.");
                }

                $contextChunks = $materialRepo->findNearestChunks($themeEmbedding, (int)$task['profession_id'], 3);

                if (empty($contextChunks)) {
                    throw new \Exception("В базе знаний не найдено материалов по этой теме.");
                }

                $contextText = "";
                $sourceDocuments = [];

                foreach ($contextChunks as $chunk) {
                    $contextText .= "--- ИСТОЧНИК ЗНАНИЙ: " . $chunk['document_title'] . " ---\n";
                    $contextText .= $chunk['text_chunk'] . "\n\n";

                    if (!in_array($chunk['document_title'], $sourceDocuments)) {
                        $sourceDocuments[] = $chunk['document_title'];
                    }
                }

                $finalSourcesString = implode(', ', $sourceDocuments);

                // Шаг 2: Сбор примеров (Few-Shot)
                CLI::write("Шаг 2: Сбор Few-Shot эталонов...", "cyan");
                $fewShots = $fewShotRepo->getRandomExamples((int)$task['profession_id'], $task['type'], 3);

                $fewShotPrompt = "";
                foreach ($fewShots as $fs) {
                    $fewShotPrompt .= "Вопрос: " . $fs['question'] . "\n";
                    $fewShotPrompt .= "Варианты: " . json_encode($fs['options'], JSON_UNESCAPED_UNICODE) . "\n";
                    $fewShotPrompt .= "Правильный ответ: " . json_encode($fs['correct_answer'], JSON_UNESCAPED_UNICODE) . "\n\n";
                }

                // Шаг 3: Генерация (Актер)
                CLI::write("Шаг 3: ИИ-Генератор (Qwen2.5:7b)...", "cyan");
                $actorPrompt = "Ты — профессиональный методист образования. Составь ОДИН точный учебный тест по теме \"{$task['topic']}\" строго на основе предоставленного методического материала.\n\n"
                    . "МЕТОДИЧЕСКИЙ МАТЕРИАЛ (БАЗА ЗНАНИЙ):\n{$contextText}\n"
                    . "ПРИМЕРЫ ТЕСТОВ:\n{$fewShotPrompt}\n"
                    . "ТИП ТЕСТА: \"{$task['type']}\" (single — один ответ, multi — несколько).\n\n"
                    . "КРИТИЧЕСКОЕ ТРЕБОВАНИЕ:\n"
                    . "В поле \"rationale\" напиши подробное обоснование: почему выбранный ответ является верным, со ссылкой на конкретные правила, цифры или пункты из предоставленного методического материала.\n\n"
                    . "ОТВЕТЬ СТРОГО JSON-ОБЪЕКТОМ (без markdown разметки):\n"
                    . "{\n  \"question\": \"Текст вопроса\",\n  \"options\": [\"Вариант 1\", \"Вариант 2\", \"Вариант 3\", \"Вариант 4\"],\n  \"correct\": [\"Текст правильного ответа\"],\n  \"rationale\": \"Обоснование правильности ответа на основании предоставленного текста\"\n}";

                $generatedJson = $ollama->generateChat([['role' => 'user', 'content' => $actorPrompt]], true);

                // Шаг 4: Проверка (Критик)
                CLI::write("Шаг 4: ИИ-Критик...", "cyan");
                $criticPrompt = "Ты — строгий инспектор качества образования. Проверь созданный тест и его обоснование на основе официальной методички.\n"
                    . "ОФИЦИАЛЬНАЯ МЕТОДИЧКА:\n{$contextText}\n"
                    . "ТЕСТ ДЛЯ ПРОВЕРКИ:\n{$generatedJson}\n\n"
                    . "ЗАДАЧА:\n"
                    . "1. Проверь, соответствует ли вопрос и варианты ответов фактам из методички.\n"
                    . "2. Проверь поле 'rationale' (обоснование). Действительно ли в методичке написаны эти факты?\n"
                    . "3. Если всё верно, верни исходный JSON без изменений. Если есть ошибка — исправь её внутри структуры.\n"
                    . "ОТВЕТЬ СТРОГО JSON-ОБЪЕКТОМ:";

                // ВОТ ТУТ МЫ ЯВНО ОБЪЯВЛЯЕМ $finalJson
                $finalJson = $ollama->generateChat([['role' => 'user', 'content' => $criticPrompt]], true);

                // Валидируем финальный JSON от Критика
                $testArray = json_decode($finalJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("ИИ вернул сломанную JSON структуру: " . $finalJson);
                }

                // Вытаскиваем обоснование ИИ
                $aiRationale = $testArray['rationale'] ?? 'Обоснование не предоставлено ИИ.';

                // Принудительно склеиваем обоснование ИИ с именами документов из БД
                $finalRationaleWithSources = $aiRationale . "<br><br>📌 <b>Официальный источник данных:</b> " . $finalSourcesString;

                // Обновляем структуру массива
                $testArray['rationale'] = $finalRationaleWithSources;
                $finalJsonUpdated = json_encode($testArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                // Сохраняем готовый результат в Postgres
                $db->table('generation_tasks')->where('id', $taskId)->update([
                    'status'        => 'done',
                    'result_json'   => $finalJsonUpdated,
                    'rationale'     => $finalRationaleWithSources,
                    'error_message' => null,
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);

                CLI::write("=== Успех! Задача #{$taskId} выполнена с источником ===", "green");

            } catch (\Exception $e) {
                CLI::write("Ошибка: " . $e->getMessage(), "red");
                $db->table('generation_tasks')->where('id', $taskId)->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);
            }

            // Подтверждаем RabbitMQ, что сообщение обработано
            $channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
    }
}
