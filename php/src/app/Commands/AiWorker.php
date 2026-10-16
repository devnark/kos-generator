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
    protected $description = 'Простой линейный воркер для генерации тестов';

    public function run(array $params)
    {
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

        // 3. Главный бесконечный цикл — никакой магии и колбэков
        while (true) {
            // Пытаемся взять ОДНУ задачу из очереди (без блокировки процесса)
            $msg = $channel->basic_get('test_generation_tasks');

            // Если задачи в очереди нет — спим 2 секунды и проверяем снова
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
                $contextChunks  = $materialRepo->findNearestChunks($themeEmbedding, (int)$task['profession_id'], 3);

                $contextText = "";
                foreach ($contextChunks as $chunk) {
                    $contextText .= $chunk['text_chunk'] . "\n\n";
                }

                // Шаг 2: Сбор примеров (Few-Shot)
                CLI::write("Шаг 2: Сбор Few-Shot эталонов...", "cyan");
                $fewShots = $fewShotRepo->getRandomExamples((int)$task['profession_id'], $task['type'], 3);

                $fewShotPrompt = "";
                foreach ($fewShots as $fs) {
                    $fewShotPrompt .= "Вопрос: " . $fs['question'] . "\n";
                    $fewShotPrompt .= "Варианты: " . $fs['options'] . "\n";
                    $fewShotPrompt .= "Правильный ответ: " . $fs['correct_answer'] . "\n\n";
                }

/*

// Шаг 3: Генерация (Актер)
CLI::write("Шаг 3: ИИ-Генератор (Qwen2.5:7b)...", "cyan");
$actorPrompt = "Ты — строгий методист образования. Твоя цель — составить ОДИН учебный тест для профессии '{$task['profession_name']}' по теме '{$task['topic']}'.\n\n"
             . "КРИТИЧЕСКИ ВАЖНЫЕ ПРАВИЛА:\n"
             . "1. Составляй вопрос и варианты ответов СТРОГО на основе предоставленной методички.\n"
             . "2. Тебе ЗАПРЕЩЕНО использовать любые внешние знания, факты или придумывать правила, которых нет в тексте методички.\n"
             . "3. Если в предоставленном тексте методички нет информации по теме '{$task['topic']}', верни JSON с текстом ошибки в вопросе.\n\n"
             . "МЕТОДИЧКА ДЛЯ СОСТАВЛЕНИЯ ТЕСТА:\n{$contextText}\n"
             . "ПРИМЕРЫ ЭТАЛОННЫХ ТЕСТОВ ДЛЯ ЭТОЙ ПРОФЕССИИ:\n{$fewShotPrompt}\n"
             . "ТИП ТЕСТА: '{$task['type']}' (single — один ответ, multi — несколько).\n"
             . "ОТВЕТЬ СТРОГО JSON-ОБЪЕКТОМ:";


*/

                // Шаг 3: Генерация (Актер)
                CLI::write("Шаг 3: ИИ-Генератор (Qwen2.5:7b)...", "cyan");
                $actorPrompt = "Ты — методист образования. Составь ОДИН учебный тест по теме '{$task['topic']}' строго на основе методички.\n\n"
                    . "МЕТОДИЧКА:\n{$contextText}\n"
                    . "ПРИМЕРЫ ТЕСТОВ:\n{$fewShotPrompt}\n"
                    . "ТИП ТЕСТА: '{$task['type']}' (single — один ответ, multi — несколько).\n"
                    . "ОТВЕТЬ СТРОГО JSON-ОБЪЕКТОМ:\n"
                    . "{\n  \"question\": \"Текст\",\n  \"options\": [\"1\", \"2\", \"3\", \"4\"],\n  \"correct\": [\"Ответ\"] \n}";

                $generatedJson = $ollama->generateChat([['role' => 'user', 'content' => $actorPrompt]], true);

                // Шаг 4: Проверка (Критик)
                CLI::write("Шаг 4: ИИ-Критик...", "cyan");
                $criticPrompt = "Ты — инспектор качества. Проверь созданный тест на основе методички.\n"
                    . "МЕТОДИЧКА:\n{$contextText}\n"
                    . "ТЕСТ:\n{$generatedJson}\n\n"
                    . "Если всё верно, верни исходный JSON. Если есть фактическая ошибка — исправь её внутри структуры.\n"
                    . "ОТВЕТЬ СТРОГО JSON-ОБЪЕКТОМ:";

                $finalJson = $ollama->generateChat([['role' => 'user', 'content' => $criticPrompt]], true);

                // Валидируем финальный JSON
                json_decode($finalJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("ИИ вернул сломанную JSON структуру: " . $finalJson);
                }

                // Сохраняем готовый результат в Postgres
                $db->table('generation_tasks')->where('id', $taskId)->update([
                    'status'        => 'done',
                    'result_json'   => $finalJson,
                    'error_message' => null,
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);

                CLI::write("=== Успех! Задача #{$taskId} выполнена ===", "green");

            } catch (\Exception $e) {
                CLI::write("Ошибка: " . $e->getMessage(), "red");
                $db->table('generation_tasks')->where('id', $taskId)->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);
            }

            // Подтверждаем RabbitMQ, что сообщение обработано и его можно удалить из очереди
            $channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
    }
}
