<?php

namespace App\Libraries;

use CodeIgniter\Config\Services;

class OllamaClient
{
    protected $client;
    // Базовый адрес без слова api на конце — собираем пути явно
    protected $ollamaHost = 'http://192.168.3.3:11434';

    public function __construct()
    {
        $this->client = Services::curlrequest([
            'headers'  => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ],
            'verify'   => false, // Отключаем проверку SSL для локальной сети
            'timeout'  => 90
        ]);
    }

    /**
     * Генерация векторного эмбеддинга для RAG
     */
    public function getEmbedding(string $text): array
    {
        try {
            // Передаем строго ПОЛНЫЙ URL-адрес
            $response = $this->client->post($this->ollamaHost . '/api/embeddings', [
                'json' => [
                    'model'  => 'bge-m3',
                    'prompt' => $text
                ],
                'timeout' => 15
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['embedding'] ?? [];

        } catch (\Exception $e) {
            log_message('error', 'Ollama Embedding Error: ' . $e->getMessage());
            echo "<h2>Ошибка Ollama API (Embeddings):</h2><pre>" . $e->getMessage() . "</pre>";
            die();
        }
    }

    /**
     * Диалог с моделью Qwen2.5 (Генератор / Критик)
     */
    public function generateChat(array $messages, bool $jsonMode = true): string
    {
        try {
            $payload = [
                'model'    => 'qwen2.5:7b',
                'messages' => $messages,
                'stream'   => false,
                'options'  => [
                    'temperature' => 0.3
                ]
            ];

            if ($jsonMode) {
                $payload['format'] = 'json';
            }

            // Передаем строго ПОЛНЫЙ URL-адрес
            $response = $this->client->post($this->ollamaHost . '/api/chat', [
                'json'    => $payload,
                'timeout' => 90
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['message']['content'] ?? '';

        } catch (\Exception $e) {
            log_message('error', 'Ollama Chat Error: ' . $e->getMessage());
            return '';
        }
    }
}
