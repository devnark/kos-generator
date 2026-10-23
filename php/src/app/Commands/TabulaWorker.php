<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Libraries\MarkdownSplitter;

class TabulaWorker extends BaseCommand
{
    protected $group       = 'RAG';
    protected $name        = 'rag:run-tabula';
    protected $description = 'Фоновый воркер для извлечения таблиц через Tabula CLI и конвертации в Markdown.';

    public function run(array $params)
    {
        $sharedDir = "/tmp/pdf_exchange/";
        // Обратите внимание на путь — в контейнере tabula_worker jar-ник лежит здесь:
        $jarPath = "/opt/tabula_worker/bin/tabula.jar";

        CLI::write("[*] CI4 Воркер Tabula запущен и ожидает файлы...", 'green');

        try {
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();
            $channel->queue_declare('pdf_processing_tasks', false, true, false, false);
        } catch (\Exception $e) {
            CLI::error("[-] Ошибка подключения к RabbitMQ: " . $e->getMessage());
            return;
        }

        $channel->basic_qos(null, 1, null);

        $callback = function (AMQPMessage $msg) use ($sharedDir, $jarPath, $channel) {
            try {
                $data = json_decode($msg->body, true);
                $filename = $data['file'] ?? '';
                $pdfPath = $sharedDir . $filename;
                $csvPath = $pdfPath . '.csv';

                if (empty($filename) || !file_exists($pdfPath)) {
                    CLI::error("[-] Файл не найден: {$pdfPath}");
                    $msg->ack();
                    return;
                }

                CLI::write("[+] Извлечение таблиц для: {$filename}", 'cyan');

                // 1. Вызов Tabula CLI — получаем CSV
                $tabulaCmd = "java -jar " . escapeshellarg($jarPath) . " --guess -p all " . escapeshellarg($pdfPath) . " > " . escapeshellarg($csvPath);
                shell_exec($tabulaCmd);

                // 2. ВМЕСТО Markdown-сетки генерируем плоские RAG-чанки из CSV
                $splitter = new MarkdownSplitter();
                // Передаем название документа для контекста внутри чанка
                $tableChunks = $splitter->convertCsvToRAGChunks($csvPath, $data['document_title'] ?? $filename);

                // Удаляем временный CSV
                if (file_exists($csvPath)) @unlink($csvPath);

// 3. Извлекаем обычный текст через pdftotext
                $txtPath = $pdfPath . '.txt';
                shell_exec("pdftotext -layout " . escapeshellarg($pdfPath) . " " . escapeshellarg($txtPath));

                $fullText = "";
                if (file_exists($txtPath)) {
                    $lines = file($txtPath);
                    $cleanLines = [];

                    foreach ($lines as $line) {
                        // Если в строке есть длинные цепочки пробелов (признак таблицы в -layout),
                        // мы её пропускаем, так как Tabula вытащит этот контент качественнее
                        if (preg_match('/\s{4,}/u', $line) && (strpos($line, 'Шаг') !== false || preg_match('/\d+/', $line))) {
                            continue;
                        }

                        $cleanLines[] = $line;
                    }

                    $fullText = implode("", $cleanLines);

                    // Базовая очистка метаданных и страниц
                    $fullText = str_replace("\x0C", " ", $fullText);
                    $fullText = preg_replace('/(РФ\s+)?72319-2025\s+"Услуги бытовые\..*?"/ui', '', $fullText);
                    $fullText = preg_replace("/\n{3,}/", "\n\n", $fullText);

                    @unlink($txtPath);
                }

                @unlink($pdfPath); // Удаляем исходный PDF

                // 4. Передаем структурированные данные в следующую очередь
                $data['full_text']    = $fullText;     // Обычный текст (пойдет в Parent)
                $data['table_chunks'] = $tableChunks;  // Массив готовых плоских чанков (пойдет прямо в Child)

                $responseMsg = new AMQPMessage(json_encode($data), ['delivery_mode' => 2]);
                $channel->basic_publish($responseMsg, '', 'document_enrichment_tasks');

                CLI::write("[+] Таблицы успешно линеаризованы (" . count($tableChunks) . " чанков) и переданы дальше", 'green');
                $msg->ack();

            } catch (\Exception $e) {
                CLI::error("[-] Ошибка в процессе обработки: " . $e->getMessage());
                $msg->nack(true);
            }
        };

        $channel->basic_consume('pdf_processing_tasks', '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }
}
