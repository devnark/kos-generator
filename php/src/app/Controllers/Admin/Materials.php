<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Services;
use App\Libraries\MarkdownSplitter;

class Materials extends BaseController
{
    protected $professionRepo;
    protected $materialRepo;
    protected $ollamaClient;

    public function __construct()
    {
        $this->professionRepo = Services::professionRepository();
        $this->materialRepo = Services::materialRepository();
        $this->ollamaClient = Services::ollamaClient();
    }

    public function index()
    {
        $data = [
            'title' => 'Управление материалами ИИ',
            'professions' => $this->professionRepo->getAll()
        ];

        return view('admin/materials/index', $data);
    }

    public function createProfession()
    {
        $name = $this->request->getPost('name');

        if (!empty($name)) {
            $this->professionRepo->create(trim($name));
            return redirect()->to('/admin/materials')->with('success', 'Профессия успешно добавлена!');
        }

        return redirect()->to('/admin/materials')->with('error', 'Название не может быть пустым.');
    }


    public function uploadFile()
    {
        set_time_limit(0);
        $professionId = $this->request->getPost('profession_id');
        $file = $this->request->getFile('schema_file');
        $documentTitle = trim($this->request->getPost('document_title') ?? '');

        if (!$file->isValid() || $file->getExtension() !== 'pdf') {
            return redirect()->to('/admin/materials')->with('error', 'Пожалуйста, загрузите корректный файл в формате PDF.');
        }

        if (empty($documentTitle)) {
            $documentTitle = $file->getClientName();
        }

        try {
            // Шаг 1: Генерируем уникальное случайное имя для файла во избежание коллизий
            $tempName = $file->getRandomName();

            // Шаг 2: Перемещаем файл в ОБЩУЮ папку volumes, которую видит контейнер tabula_worker
            // Docker автоматически создал этот volume shared-pdf-data по пути /tmp/pdf_exchange/
            $file->move('/tmp/pdf_exchange/', $tempName);

            // Шаг 3: Формируем компактную задачу для асинхронного конвейера
            $taskData = [
                'profession_id'  => (int)$professionId,
                'file'           => $tempName,
                'document_title' => $documentTitle
            ];

            // Шаг 4: Подключаемся к RabbitMQ и отправляем задачу в ПЕРВУЮ очередь (для Tabula)
            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            // Объявляем устойчивую (durable) очередь pdf_processing_tasks
            $channel->queue_declare('pdf_processing_tasks', false, true, false, false);

            $msg = new \PhpAmqpLib\Message\AMQPMessage(
                json_encode($taskData),
                ['delivery_mode' => 2] // Делаем сообщение persistent (устойчивым к перезапускам брокера)
            );

            // Публикуем строго в первую очередь конвейера
            $channel->basic_publish($msg, '', 'pdf_processing_tasks');

            $channel->close();
            $connection->close();

            return redirect()->to('/admin/materials')->with('success', 'Документ успешно загружен и отправлен в очередь на асинхронный парсинг таблиц и ИИ-обработку!');

        } catch (\Exception $e) {
            return redirect()->to('/admin/materials')->with('error', 'Ошибка инициализации асинхронной RAG-обработки: ' . $e->getMessage());
        }
    }



    /**
     * Динамически собирает абсолютно все метаданные из PDF-файла.
     *
     * @param string $pdfPath Абсолютный путь к PDF-файлу на сервере.
     * @return array Ассоциативный массив со всеми парами "ключ => значение".
     */
    public function extractAllPdfMetadata(string $pdfPath): array
    {
        $metadata = [];
        $safePath = escapeshellarg($pdfPath);
        $output = [];
        $returnVar = 0;

        exec("pdfinfo {$safePath} 2>&1", $output, $returnVar);

        if ($returnVar !== 0 || empty($output)) {
            log_message('error', 'pdfinfo не смог прочитать файл: ' . $pdfPath);
            return [];
        }

        foreach ($output as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);

                $cleanKey = strtolower(trim($key));
                $cleanKey = str_replace(' ', '_', $cleanKey);
                $cleanValue = trim($value);

                $metadata[$cleanKey] = $cleanValue;
            }
        }

        if (isset($metadata['pages'])) {
            $metadata['pages'] = (int)$metadata['pages'];
        }

        return $metadata;
    }
}
