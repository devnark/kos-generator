<?php

namespace App\Libraries;

class OllamaClient
{
    protected string $polzaHost = 'https://polza.ai/api/v1';
    //URL: https://polza.ai/api/v1/chat/completions
    protected string $localOllamaHost = 'http://192.168.3.3:11434';

    const AI_CONFIG_POLZA = 'polza';
    const AI_CONFIG_LOCAL = 'local';

    protected $aiConfig = [
            'polza' => [
                'host' => 'https://polza.ai/api/v1',
                'chatModel' => 'qwen/qwen-2.5-72b-instruct',
                'embModel' => '',
                'embedding' => '',
                'chat' => '/chat/completions',
                'verify' => true,
                'token' => 'Bearer pza_8g7panZleLZUEr-dp4ImGvRVPWBMfOxL',
            ],
            'local' => [
                'host' => 'http://192.168.3.3:11434',
                'chatModel' => 'qwen2.5:7b',
                'embModel' => 'bge-m3',
                'embedding' => '/api/embeddings',
                'chat' => '/api/chat',
                'verify' => false,
                'token' => '',
            ],
        ];

    /**
     * Генерация векторного эмбеддинга для RAG (Локально через Ollama)
     */
    public function getEmbedding(string $text, string $aiConfigKey = self::AI_CONFIG_LOCAL): array
    {
        try {
            $aiConfig = $this->aiConfig[$aiConfigKey];
            $ch = curl_init($aiConfig['host'] . $aiConfig['embedding']);
//            $ch = curl_init($this->localOllamaHost . '/api/embeddings');
            $payload = [
//                'model' => 'bge-m3',
                'model' => $aiConfig['embModel'],
                'prompt' => $text
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            return $data['embedding'] ?? [];

        } catch (\Exception $e) {
            log_message('error', 'Ollama Local Embedding Error: ' . $e->getMessage());
            return [];
        }
    }


    public function getQuestions(array $messages, string $aiConfigKey = self::AI_CONFIG_LOCAL, bool $jsonMode = true): array
    {
        $data = $this->generateChat($messages, $aiConfigKey, $jsonMode);
//        $data = $this->getDataQuest();
        $arrayData = $this->convertStringJsonToArray($data);
        if (!empty($arrayData['choices'][0]['message']['content'])){
            return $arrayData['choices'][0]['message']['content'];
        }

        // Локально так возвращает
        if (!empty($arrayData['message']['content'])){
            return $arrayData['message']['content'];
        }

        return [];
    }

    public function getAudit(array $messages, string $aiConfigKey = self::AI_CONFIG_LOCAL, bool $jsonMode = true): array
    {
        $data = $this->generateChat($messages, $aiConfigKey, $jsonMode);
//        $data = $this->getDataAudit();
        $arrayData = $this->convertStringJsonToArray($data);
        if (!empty($arrayData['choices'][0]['message']['content'])){
            return $arrayData['choices'][0]['message']['content'];
        }

        // Локально так возвращает
        if (!empty($arrayData['message']['content'])){
            return $arrayData['message']['content'];
        }

        return [];
    }

    /**
     * Диалог с моделью Qwen2.5 (Совместимо с Ollama локально и Polza.ai удаленно)
     */
    private function generateChat2($messages, string $aiConfigKey = self::AI_CONFIG_LOCAL, bool $jsonMode = true): string
    {
        $aiConfig = $this->aiConfig[$aiConfigKey];
        $payload = [
            'model'       => $aiConfig['chatModel'],
            'messages'    => $messages,
            'temperature' => 0.3,
            'stream'      => false
        ];

        if ($jsonMode) {
            $payload['extraBody'] = [
                'responseFormat' => [
                    'type' => 'json_object'
                ]
            ];
        }

        $logFile = fopen('/tmp/curl_debug.log', 'a+');
        if ($logFile) {
            $dateTime = date('Y-m-d H:i:s');
            fwrite($logFile, "[$dateTime]=== СТАРТ ЛОГИРОВАНИЯ ЗАПРОСА ===\n");
            fwrite($logFile, ">>> ТЕЛО ЗАПРОСА JSON:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n");
        }
        $ch = curl_init($aiConfig['host'] . $aiConfig['chat']);
        $token = '';
        if (!empty($aiConfig['token'])) {
            $token = 'Authorization: Bearer ' .  $aiConfig['token'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => $aiConfig['verify'],
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                $token
            ]
        ]);

        /** @var string|false $responseBody */
        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);

        curl_close($ch);

        if ($logFile) {
            fwrite($logFile, "<<< ТЕЛО ОТВЕТА (RESPONSE BODY):\n" . ($responseBody ?: 'НЕТ ОТВЕТА') . "\n\n");
        }

        if ($responseBody === false || $httpCode !== 200) {
            log_message('error', 'Polza API Error. HTTP: ' . $httpCode . ' Error: ' . $curlError);
            if ($logFile) {
                fwrite($logFile, "<<< code:[$httpCode] ОШИБКА:\n" . ($curlError) .":  =CURL". "\n\n");
                fclose($logFile);
            }
            return '';
        }

        if ($logFile) {
            fwrite($logFile, "=== КОНЕЦ ЛОГИРОВАНИЯ ===\n");
        }

        return $responseBody;
    }

    private function generateChat($messages, string $aiConfigKey = self::AI_CONFIG_LOCAL, bool $jsonMode = true): string
    {
        // 1. Получаем конфигурацию для выбранного ключа
        $aiConfig = $this->aiConfig[$aiConfigKey];

        $payload = [
            'model'       => $aiConfig['chatModel'],
            'messages'    => $messages,
            'temperature' => 0.3,
            'stream'      => false
        ];

        // 2. Правильный формат JSON-режима для OpenAI-совместимых API и Ollama
        if ($jsonMode) {
            $payload['response_format'] = [
                'type' => 'json_object'
            ];
        }

        $logFile = fopen('/tmp/curl_debug.log', 'a+');
        if ($logFile) {
            $dateTime = date('Y-m-d H:i:s');
            fwrite($logFile, "[$dateTime]=== СТАРТ ЛОГИРОВАНИЯ ЗАПРОСА [$aiConfigKey] ===\n");
            fwrite($logFile, ">>> ТЕЛО ЗАПРОСА JSON:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n");
        }

        // 3. ДИНАМИЧЕСКИЙ URL: Собираем хост и эндпоинт из конфига
        $url = rtrim($aiConfig['host'], '/') . $aiConfig['chat'];
        $ch = curl_init($url);

        // 4. Формируем заголовки (без пустых строк)
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!empty($aiConfig['token'])) {
            // Если в конфиге уже написано 'Bearer ...', вставляем как есть, иначе добавляем префикс
            $tokenValue = (strpos($aiConfig['token'], 'Bearer ') === 0) ? $aiConfig['token'] : 'Bearer ' . $aiConfig['token'];
            $headers[] = 'Authorization: ' . $tokenValue;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => $aiConfig['verify'],
            CURLOPT_HTTPHEADER     => $headers
        ]);

        /** @var string|false $responseBody */
        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);

        curl_close($ch);

        if ($logFile) {
            fwrite($logFile, "<<< URL ЗАПРОСА: $url\n");
            fwrite($logFile, "<<< КОД ОТВЕТА HTTP: $httpCode\n");
            fwrite($logFile, "<<< ТЕЛО ОТВЕТА (RESPONSE BODY):\n" . ($responseBody ?: 'НЕТ ОТВЕТА') . "\n\n");
        }

        if ($responseBody === false || $httpCode !== 200) {
            log_message('error', "AI API Error ($aiConfigKey). HTTP: " . $httpCode . ' Error: ' . $curlError);
            if ($logFile) {
                fwrite($logFile, "<<< code:[$httpCode] ОШИБКА cURL:\n" . ($curlError) . "\n\n");
                fclose($logFile);
            }
            return '';
        }

        if ($logFile) {
            fwrite($logFile, "=== КОНЕЦ ЛОГИРОВАНИЯ ===\n");
            fclose($logFile);
        }

        return $responseBody;
    }

    private function convertStringJsonToArray($responseBody) : array {
        $buffer = $responseBody;
        // Вырезаем кусок json и заменяем его на плейсхолдер
        if (preg_match('/```json(.*?)```/s', $responseBody, $matches)) {
            $fullBlock = $matches[0]; // Весь блок: ```json ... ```
            $jsonBlock = $matches[1]; // Только блок: ```json ... ```

            $jsonBlock = $this->clearJson($jsonBlock);

            // Заменяем весь найденный блок в исходном тексте на ###
            $responseBody = str_replace($fullBlock, '#%#%#', $responseBody);
            $responseBody = str_replace('#%#%#', $jsonBlock, $responseBody);
            $responseBody = str_replace('#%#%#', $jsonBlock, $responseBody);
            $responseBody = str_replace('"{', '{', $responseBody);
            $responseBody = str_replace('}"', '}', $responseBody);
        }else {
            $responseBody = $this->clearJson($responseBody);
            $responseBody = str_replace('"{', '{', $responseBody);
            $responseBody = str_replace('}"', '}', $responseBody);
        }

        if (empty(json_decode($responseBody, true))) {
            throw new \Exception("Bad JSON: $buffer");
        }

        return json_decode($responseBody, true);
    }

    private function clearJson($stringJson) :string
    {
        $responseBody = '';
        if (isset($stringJson)){
            $content = str_replace('\"', '"', $stringJson);
            $content = str_replace('\n', '', $content);
            $responseBody = preg_replace('/\s+/u', ' ', $content);
        }
        $responseBody = trim($responseBody, ' "\'');

        return $responseBody;
    }

    private function getDataAudit()
    {
        return
<<<HEAD
{"model":"qwen2.5:7b","created_at":"2026-10-19T19:59:47.8351703Z","message":{"role":"assistant","content":"```json\n{\n  \"valid\": false,\n  \"error_reason\": \"Тест содержит смысловой обман и логическую ошибку.\",\n  \"valid_reason\": \"Варианты '1-2 пары', '5-6 пар', '7-8 пар' и '9-10 пар' являются синонимами '3-4 пары', так как они указывают на близкие значения. В тестах на знание норм и правил обычно используются точные значения, а не интервалы, которые могут быть интерпретированы как синонимы.\",\n  \"valid_not_enough_data\": \"Не было предоставлена дополнительная информация, позволяющая уточнить интервалы запаса постельного белья.\"\n}\n```\n\n### Пояснение к проверке:\n1. **Смысловой обман**: Варианты ответов '1-2 пары', '5-6 пар', '7-8 пар' и '9-10 пар' могут быть интерпретированы как синонимы '3-4 пары', так как они указывают на близкие значения. Это создает путаницу для участников теста.\n2. **Логическая ошибка**: В документе указано именно интервал '3-4 пары', но в тесте не указан конкретный интервал, что может вызвать сомнения.\n\nДля улучшения теста рекомендуется указать точный интервал или использовать другие варианты ответов, которые не будут рассматриваться как синонимы."},"done":true,"done_reason":"stop","total_duration":6899273400,"load_duration":6142700,"prompt_eval_count":1946,"prompt_eval_cached_count":27,"prompt_eval_duration":528879000,"eval_count":348,"eval_duration":6302257000}
HEAD;

    }

    private function getDataQuest()
    {
        return
<<<HEAD
{"model":"qwen2.5:7b","created_at":"2026-10-19T20:55:16.2422039Z","message":{"role":"assistant","content":"{\n  \"question\": \"Какое количество запаса чистого постельного белья необходимо иметь проживающим согласно правилам?\",\n  \"options\": [\n    \"В объеме, позволяющем обеспечить его смену раз в неделю\",\n    \"В объеме, позволяющем обеспечить его смену в соответствии с пунктом 7.19 настоящих правил\",\n    \"Достаточно одного комплекта на одного проживающего\",\n    \"Нет определенных требований к запасу белья\",\n    \"В объеме, позволяющем обеспечить его смену в соответствии с местными обычаями\"\n  ],\n  \"correct\": [\"В объеме, позволяющем обеспечить его смену в соответствии с пунктом 7.19 настоящих правил\"],\n  \"rationale\": \"Обоснование со ссылкой на страницу 40 в документе: \\\"проживающих необходимо иметь запас чистого постельного белья в объеме, позволяющем обеспечить его смену в соответствии спунктом 7.19 настоящих правил. (в ред.Постановления Главного государственного санитарного врача РФ от 20.03.2024 N 2)\\\"\"\n}"},"done":true,"done_reason":"stop","total_duration":5631460900,"load_duration":2452400,"prompt_eval_count":2265,"prompt_eval_cached_count":1549,"prompt_eval_duration":219971000,"eval_count":285,"eval_duration":5183492000}
HEAD;

    }
}
