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
    protected $description = 'Промышленный воркер с автоматическим циклом самокоррекции ИИ (до 3 попыток)';
    const AI_WORKER_MAX_ATTEMPTS = 3;

    public function run(array $params)
    {
        /*
// 1. Достаем идеальный структурированный кусок текста из БД
$chunk = $this->materialRepo->getParentChunkById($selectedParentChunkId);

// 2. Формируем задачу для генерации вопросов по этой теме
$testTask = [
    'parent_chunk_id' => $chunk['id'],
    'topic'           => $chunk['test_topic'],
    'complexity'      => $chunk['complexity'],
    'text'            => $chunk['full_text'] // Здесь лежит Markdown-текст с шапкой таблицы!
];

// 3. Публикуем в вашу исходную очередь test_generation_tasks
$channel->basic_publish(new AMQPMessage(json_encode($testTask)), '', 'test_generation_tasks');

        $prompt = "Ты — строгий экзаменатор. Твоя задача — составить учебный тест по теме: '{$task['topic']}'.\n"
        . "Уровень сложности: {$task['complexity']}.\n"
        . "Используй для составления вопросов ТОЛЬКО следующий текст стандарта:\n"
        . "--- НАЧАЛО ТЕКСТА ---\n{$task['text']}\n--- КОНЕЦ ТЕКСТА ---\n\n"
        . "Сгенерируй 3 вопроса. Ответ должен быть строго в формате JSON (массив объектов) без вводных слов:\n"
        . "[\n"
        . "  {\n"
        . "    \"question\": \"Текст вопроса по ГОСТу?\",\n"
        . "    \"options\": [\"Вариант A\", \"Вариант B\", \"Вариант C\", \"Вариант D\"],\n"
        . "    \"correct\": \"Вариант B\"\n"
        . "  }\n"
        . "]\n";

$aiResponse = $ollama->generate($prompt);
// Декодируйте JSON и сохраняйте готовый тест в вашу базу данных для студентов!

         */

        set_time_limit(0);
        CLI::write("Запуск ИИ-воркера с самокоррекцией...", "green");

        $db           = \Config\Database::connect();
        $ollama       = new OllamaClient();
        $materialRepo = new MaterialRepository();
        $fewShotRepo  = new FewShotRepository();

        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
        $channel = $connection->channel();
        $channel->queue_declare('test_generation_tasks', false, true, false, false);

        CLI::write("Воркер активен и слушает очередь...", "yellow");

        while (true) {
            $msg = $channel->basic_get('test_generation_tasks');

            if ($msg === null) {
                sleep(2);
                continue;
            }

            $taskData = json_decode($msg->body, true);
            $taskId   = $taskData['task_id'];

            CLI::write("\n=== Найдена задача ID: {$taskId} ===", "blue");

            $db->table('generation_tasks')->where('id', $taskId)->update(['status' => 'processing']);
            $task = $db->table('generation_tasks')->where('id', $taskId)->get()->getRowArray();

            try {
                // ШАГ 1: Поиск контекста (RAG)
                CLI::write("Шаг 1: Семантический поиск (RAG)...", "cyan");
                $themeEmbedding = $ollama->getEmbedding($task['topic']);
                $contextChunks = $materialRepo->findNearestChunks($themeEmbedding, (int)$task['profession_id'], 3);

                if (empty($contextChunks)) {
                    throw new \Exception("В базе знаний не найдено материалов по этой теме.");
                }

                $contextText = "";
                $sourceMeta = [];
                foreach ($contextChunks as $chunk) {
                    $contextText .= "--- НАЧАЛО БЛОКА ДАННЫХ ---\n";
                    $contextText .= "ИСТОЧНИК: " . $chunk['document_title'] . "\n";
                    $contextText .= "СТРАНИЦА В ДОКУМЕНТЕ: " . $chunk['page_number'] . "\n";
                    $contextText .= "ТЕКСТ МАТЕРИАЛА:\n" . $chunk['text_chunk'] . "\n";
                    $contextText .= "--- КОНЕЦ БЛОКА ДАННЫХ ---\n\n";

                    $metaKey = md5($chunk['document_title'] . '_' . $chunk['page_number']);
                    if (!isset($sourceMeta[$metaKey])) {
                        $sourceMeta[$metaKey] = [
                            'chunk_id'       => (int)$chunk['id'],
                            'document_title' => $chunk['document_title'],
                            'page_number'    => (int)$chunk['page_number']
                        ];
                    }
                }

                $finalSourcesString = implode(', ', array_column($sourceMeta, 'document_title'));

                // ШАГ 2: Сбор примеров (Few-Shot)
                CLI::write("Шаг 2: Сбор Few-Shot эталонов...", "cyan");
                $fewShots = $fewShotRepo->getRandomExamples((int)$task['profession_id'], $task['type'], 3);
                $fewShotPrompt = "";
                foreach ($fewShots as $fs) {
                    $fewShotPrompt .= "Вопрос: " . $fs['question'] . "\n";
                    $fewShotPrompt .= "Варианты: " . json_encode($fs['options'], JSON_UNESCAPED_UNICODE) . "\n";
                    $fewShotPrompt .= "Правильный ответ: " . json_encode($fs['correct_answer'], JSON_UNESCAPED_UNICODE) . "\n\n";
                }

                // =================================================================
                // 🔄 ЗАПУСКАЕМ ЦИКЛ САМОКОРРЕКЦИИ ИИ (ДО 3 ПОПЫТОК)
                // =================================================================
                $maxAttempts = self::AI_WORKER_MAX_ATTEMPTS;
                $attempt = 1;
                $isValid = false;
                $finalJson = "";
                $aiFeedbackMessage = ""; // Сюда пишем ругань для ИИ, если он ошибся

                while ($attempt <= $maxAttempts && !$isValid) {

                    echo "generatedJson>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>0\n";
                    CLI::write("Попытка генерации {$attempt} из {$maxAttempts}...", "yellow");

                    // ШАГ 3: Генератор (Актер) — Полный запрет на числовой шум
                    CLI::write("Шаг 3: Запрос к ИИ-Генератору (Актер)...", "cyan");
//                    $actorPrompt = "Ты — профессиональный методист образования. Составь ОДИН точный учебный тест по теме \"{$task['topic']}\" строго на основе предоставленного методического материала.\n\n"
//                        . "МЕТОДИЧЕСКИЙ МАТЕРИАЛ (БАЗА ЗНАНИЙ):\n{$contextText}\n"
//                        . "ПРИМЕРЫ ТЕСТОВ:\n{$fewShotPrompt}\n\n"
//                        . "КРИТИЧЕСКИЕ ПРАВИЛА ДЛЯ НЕПРАВИЛЬНЫХ ВАРИАНТОВ (OPTIONS):\n"
//                        . "1. Напиши строго 5 вариантов в 'options'.\n"
//                        . "2. ЖЕСТКИЙ ЗАПРЕТ НА ЧИСЛА: Если правильный ответ содержит цифры или время (например, '60-90 минут'), тебе КАТЕГОРИЧЕСКИ ЗАПРЕЩЕНО придумывать другие числа в качестве неправильных вариантов (нельзя писать '50 минут', '120 минут' и т.д.). ИИ путается в математике!\n"
//                        . "3. Вместо чисел делай неправильные варианты СТРОГО ТЕКСТОВЫМИ ДЕЙСТВИЯМИ из совсем других процедур бьюти-сферы (например: 'Нанесение парафиновой маски', 'Срезание кутикулы кусачками', 'Применение размягчителя'). Это сделает тест на 100% честным.\n\n"
//                        . "ЖЕСТКОЕ ТРЕБОВАНИЕ К ТИПУ ТЕСТА:\n"
//                        . "Ты обязан составить тест типа \"{$task['type']}\" (single — 1 ответ, multi — 2 или 3 ответа).\n\n"
//                        . $aiFeedbackMessage
//                        . "ОТВЕТЬ СТРОГО JSON-ОБЪЕКТОМ:\n"
//                        . "{\n  \"question\": \"Текст вопроса\",\n  \"options\": [\"Вариант 1\", \"Вариант 2\", \"Вариант 3\", \"Вариант 4\", \"Вариант 5\"],\n  \"correct\": [\"Правильный\"],\n  \"rationale\": \"Обоснование со ссылкой на конкретную страницу\"\n}";
$actorPrompt = <<<HEAD
Ты — автоматизированный эксперт-методист по оценке профессиональных квалификаций. Твоя задача — составить ровно ОДИН валидный тестовый вопрос для проверки знаний по профессии: " \"{$task['topic']}\" ".

Опирайся СТРОГО на предоставленный ниже фрагмент нормативного документа. Не придумывай факты, которых нет в тексте.

МЕТОДИЧЕСКИЙ МАТЕРИАЛ:
--- НАЧАЛО БЛОКА ДАННЫХ ---

\n{$contextText}\n
--- КОНЕЦ БЛОКА ДАННЫХ ---

ПРАВИЛА И СТРУКТУРА ГЕНЕРАЦИИ:
1. Выбери из предоставленного текста ОДИН ключевой факт, регламент, параметр или действие. На его основе сформулируй четкий вопрос.
2. Сделай тест типа "single" (один правильный вариант ответа). Это гарантирует математическую точность и исключает логические тупики.
3. В поле 'options' укажи ровно 5 вариантов ответа.
4. Правило дистракторов (неправильных вариантов): они должны быть профессионально грамотными, логичными по смыслу, но неверными конкретно для этой ситуации или документа. Если правильный ответ содержит числа/время, создай другие правдоподобные числа/время в дистракторах.
5. Пиши ответ на языке предоставленного материала (русском). Не используй другие языки и посторонние символы вне JSON-структуры.

ОТВЕТЬ СТРОГО В ФОРМАТЕ JSON БЕЗ ЛЮБЫХ ДРУГИХ ПОЯСНЕНИЙ И ВВОДНЫХ СЛОВ:
{
  "question": "Текст вопроса?",
  "options": ["Вариант 1", "Вариант 2", "Вариант 3", "Вариант 4", "Вариант 5"],
  "correct": ["Точный текст правильного варианта из массива options"],
  "rationale": "Обоснование правильного ответа со ссылкой на источник и страницу, если они указаны в блоке данных"
}

HEAD;

                    CLI::write("Текст промпта {$actorPrompt}", "light_green");

                    $rawActorArray = $ollama->getQuestions([['role' => 'user', 'content' => $actorPrompt]]);

                    if (empty($rawActorArray) || empty($rawActorArray['options'])) {
                        $reason = $auditResult['error_reason'] ?? 'Пустой текст теста';
                        CLI::write("❌ Независимый Аудитор ЗАБРАКОВАЛ тест! Причина: {$reason}", "red");
                        $attempt++;
                        continue;
                    }

                    CLI::write("Пришел {$attempt} ...", "yellow");

                    // --- АВТОМАТИЧЕСКИЙ ФИКС ДУБЛИКАТОВ СТРОК ДЛЯ СЛАБЫХ МОДЕЛЕЙ ---
                    if (json_last_error() === JSON_ERROR_NONE && isset($rawActorArray['options'])) {
                        // Жестко удаляем повторяющиеся строки, которые ИИ скопировал дважды
                        $rawActorArray['options'] = array_values(array_unique($rawActorArray['options']));
                    }
                    $generatedJson = json_encode($rawActorArray, JSON_UNESCAPED_UNICODE);


                    // ---------------------------------------------------------------

                    // ШАГ 4: Аудитор — Вправляем мозги в оценке математики
                    CLI::write("Шаг 4: Передача черновика Независимому ИИ-Аудитору...", "cyan");
                    $criticPrompt = "Ты — независимый эксперт-аудитор. Проверь созданный тест на наличие смыслового обмана, синонимов и логических ошибок.\n\n"
                        . "ОФИЦИАЛЬНАЯ МЕТОДИЧКА:\n{$contextText}\n"
                        . "ТЕСТ ДЛЯ ПРОВЕРКИ:\n{$generatedJson}\n\n"
                        . "ПРАВИЛА ИСПЫТАНИЯ ДЛЯ АУДИТОРА:\n"
                        . "1. Проверь варианты в 'options', которые НЕ попали в 'correct'. Среди них не должно быть синонимов правильных ответов.\n"
                        . "2. ВНИМАНИЕ: Разные числа и временные интервалы (например, '60-90 минут' и '120 минут') КАТЕГОРИЧЕСКИ НЕ ЯВЛЯЮТСЯ СИНОНИМАМИ! Это разные значения. Если в тесте указаны разные числа — это НЕ ошибка, пропускай такой тест!\n"
                        . "3. Убедись, что тип теста соответствует \"{$task['type']}\" (в single — 1 ответ, в multi — 2 или 3 ответа).\n\n"
                        . "ФОРМАТ ОТВЕТА СТРОГО JSON:\n"
                        . "{\n  \"valid\": true/false,\n  \"error_reason\": \"Причина забраковки\" \"valid_reason\": \"Пояснение к проверке\" \"valid_not_enough_data\": \"Список данных,которые не передали\"  \n}"
                        . "4. Если тебе не передали достаточно данных для работы - запиши кратко какие именно в секцию valid_not_enough_data внутри структуры json ответа\n"
                        . "5. Возвращай ТОЛЬКО JSON. НЕ передавай данные, которые находятся вне указанной схемы!!!";

                    //. "4. Если есть пояснение к проверке, запиши это В СЕКЦИЮ valid_reason ВНУТРИ СТРУКТУРЫ JSON ответа\n"
                    $auditResult = $ollama->getAudit([['role' => 'user', 'content' => $criticPrompt]]);

                    // Разбираем вердикт Судьи
                    $isAuditPassed = isset($auditResult['valid']) && ($auditResult['valid'] === true || $auditResult['valid'] === "true");

                    if (!$isAuditPassed) {
                        $reason = $auditResult['error_reason'] ?? 'Неизвестная логическая ошибка в дистракторах';
                        CLI::write("❌ Независимый Аудитор ЗАБРАКОВАЛ тест! Причина: {$reason}", "red");
                        $jsonBadRequest = json_encode($auditResult, JSON_UNESCAPED_UNICODE);
                        CLI::write("❌ Пояснения: {$jsonBadRequest}", "white");
                        CLI::write("❌ Тест: {$generatedJson}", "blue");

                        // Формируем жесткий фидбек для Актера на следующий круг
                        $aiFeedbackMessage = "⚠️ ТВОЙ ПРОШЛЫЙ ВАРИАНТ БЫЛ ОТКЛОНЕН АУДИТОРОМ! Ошибка: {$reason}. Напиши тест заново. Сделай ложные варианты (options) очевидно ложными, не используй синонимы и не суй туда верные факты из текста!\n";
                        $attempt++;
                        continue;
                    }

                    // Если Аудитор дал добро, парсим чистый JSON Актера
                    $testArray = json_decode($generatedJson, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $aiFeedbackMessage = "⚠️ ОШИБКА: Сломан синтаксис JSON.\n";
                        $attempt++;
                        continue;
                    }

                    // Выходим из цикла — тест успешно прошел ИИ-госприёмку!
                    $isValid = true;

                }

                // ЕСЛИ ЗА 3 ПОПЫТКИ ИИ ТАК И НЕ СПРАВИЛСЯ С ЛОГИКОЙ
                if (!$isValid) {
                    throw new \Exception("ИИ не смог сгенерировать валидный тест за {$maxAttempts} попыток. Робот саботирует требования к структуре.");
                }

                // =================================================================
                // СВЕРХТОЧНАЯ ОЧИСТКА И ФОРМАТИРОВАНИЕ ВОПРОСА ЧЕРЕЗ REGEX
                // =================================================================

                // Удаляем любые упоминания количества вариантов (выберите один/два/три/несколько/правильных/вариантов/ответа)
                // Регулярка не зависит от регистра букв, наличия точек, запятых или пробелов
                $pattern = '/выберите\s+(один|два|три|четыре|несколько)\s+(правильных\s+)?(варианта|вариантов|ответа)?\s*(ответа)?[\.\!\?\s]*/iu';
                $testArray['question'] = preg_replace($pattern, '', $testArray['question']);

                // Убираем случайные остатки знаков препинания или двоеточий в самом начале, если они остались после вырезания
                $testArray['question'] = ltrim(trim($testArray['question']), ':. ,-');

                // Принудительно подставляем наш чистый, универсальный маркер в начало
                if ($task['type'] === 'single') {
                    $testArray['question'] = "Выберите один правильный вариант ответа. " . $testArray['question'];
                } else {
                    $testArray['question'] = "Выберите несколько правильных вариантов ответа. " . $testArray['question'];
                }

                // Финальная очистка текста от двойных пробелов
                $testArray['question'] = preg_replace('/\s+/', ' ', trim($testArray['question']));
                // =================================================================


                $aiRationale = $testArray['rationale'] ?? 'Обоснование не предоставлено ИИ.';

                // --- УМНАЯ СВЕРКА ИСТОЧНИКОВ С ТЕКСТОМ RATIONALE (СТРОГИЙ REGEX) ---
                $filteredSources = [];
                foreach ($sourceMeta as $meta) {
                    $pageMarker = (int)$meta['page_number'];

                    // Регулярка \b гарантирует, что мы ищем изолированное число.
                    // Число 4 внутри слова "40" больше не сработает!
                    $pattern = '/\b' . $pageMarker . '\b/u';

                    if (preg_match($pattern, $aiRationale)) {
                        $filteredSources[] = $meta;
                    }
                }

                if (empty($filteredSources)) {
                    $testArray['sources'] = array_values($sourceMeta);
                } else {
                    $testArray['sources'] = $filteredSources;
                }
                // -----------------------------------------------------------------

                $finalJsonUpdated = json_encode($testArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                // Сохраняем чистый результат в Postgres
                $db->table('generation_tasks')->where('id', $taskId)->update([
                    'status'        => 'done',
                    'result_json'   => $finalJsonUpdated,
                    'rationale'     => $aiRationale,
                    'error_message' => null,
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);

                CLI::write("=== УСПЕХ! Тест успешно сгенерирован и прошел валидацию ===", "green");
            } catch (\Exception $e) {
                CLI::write("Глобальная ошибка задачи: " . $e->getMessage(), "red");
                $db->table('generation_tasks')->where('id', $taskId)->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at'    => date('Y-m-d H:i:s')
                ]);
            }
            $channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
    }
}