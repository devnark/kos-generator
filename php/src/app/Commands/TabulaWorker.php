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

                // 1. Вызов Tabula CLI
                $tabulaCmd = "java -jar " . escapeshellarg($jarPath) . " --guess -p all " . escapeshellarg($pdfPath) . " > " . escapeshellarg($csvPath);
                shell_exec($tabulaCmd);

                // 2. Конвертируем CSV в Markdown через библиотеку
                $splitter = new MarkdownSplitter();
                $markdownTable = $splitter->convertCsvToMarkdown($csvPath);
                if (file_exists($csvPath)) @unlink($csvPath);

                // 3. Извлекаем обычный текст через pdftotext
                $txtPath = $pdfPath . '.txt';
                shell_exec("pdftotext -layout " . escapeshellarg($pdfPath) . " " . escapeshellarg($txtPath));

                $fullText = "";
                if (file_exists($txtPath)) {
                    $fullText = file_get_contents($txtPath);
                    @unlink($txtPath);
                }

                // Удаляем исходный PDF из общего volume
                @unlink($pdfPath);

                // 4. Публикуем результат в следующую очередь для обогащения ИИ
                $data['markdown_table'] = $markdownTable;
                $data['full_text'] = $fullText;

                $responseMsg = new AMQPMessage(json_encode($data), ['delivery_mode' => 2]);
                $channel->basic_publish($responseMsg, '', 'document_enrichment_tasks');

                CLI::write("[+] Результат успешно передан в очередь enrichment", 'green');
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
