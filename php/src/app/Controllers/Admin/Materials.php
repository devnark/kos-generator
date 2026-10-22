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

    /**
     * Загрузка PDF, конвертация в структурированный текст и индексация по схеме Parent-Child
     */
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
            $pdfPath = $file->getTempName();

            // Шаг 1: Чтение PDF с сохранением структуры верстки (таблицы, списки)
            // Попытка использовать системную утилиту pdftotext для идеального сохранения разметки
            $contentText = shell_exec("pdftotext -layout " . escapeshellarg($pdfPath) . " -");

            if (empty(trim($contentText))) {
                // Фолбэк на Smalot PdfParser, если pdftotext недоступен в системе
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdfPath);
                $contentText = $pdf->getText();
            }

            // Шаг 2: Инициализация сплиттера под Markdown-структуру
            $splitter = new MarkdownSplitter();

            // Нарезаем текст на крупные логические родительские чанки (блоки текста)
            $parentChunks = $splitter->splitIntoParents($contentText, 3500);
            $savedChildrenCount = 0;

            foreach ($parentChunks as $parentData) {
                $parentText = $parentData['text'];
                $metadata = $parentData['metadata'];

                // Шаг 3: Metadata Enrichment (Обогащение метаданных через Ollama)
                // Генерируем очень краткую выжимку сути куска текста для повышения точности
                $summary = "";
                try {
                    $prompt = "Сделай краткое описание (1-2 предложения) сути следующего текста. В ответе напиши ТОЛЬКО описание, без вводных фраз:\n\n" . mb_substr($parentText, 0, 1000);
                    $summary = $this->ollamaClient->generate($prompt);
//                    $summary = $this->ollamaClient->getEmbedding($prompt);
                } catch (\Exception $e) {
                    $summary = "Краткое описание временно недоступно." . "[" . $e->getMessage() . "]";
                }

                // Шаг 4: Сохраняем РОДИТЕЛЬСКИЙ блок в базу данных и получаем его ID
                $parentId = $this->materialRepo->saveParent([
                    'profession_id' => (int)$professionId,
                    'document_title' => $documentTitle,
                    'full_text' => $parentText,
                    'chunk_summary' => trim($summary),
                    'meta_json' => json_encode($metadata)
                ]);

                // Шаг 5: Нарезаем текущий родительский блок на мелкие дочерние чанки для векторизации
                $childTexts = $splitter->splitIntoChildren($parentText, 500, 100);

                foreach ($childTexts as $childText) {
                    // Получаем векторное представление мелкого куска (4096 измерений)
                    $embedding = $this->ollamaClient->getEmbedding($childText);

                    if (!empty($embedding)) {
                        // Важно: Усекаем вектор до 4000 измерений под физический лимит HNSW индекса halfvec в Postgres
                        $truncatedEmbedding = array_slice($embedding, 0, 4000);

                        // Сохраняем дочерний чанк, привязывая его к ID родителя

                        $this->materialRepo->saveChild($parentId, $childText, $truncatedEmbedding);
                        $savedChildrenCount++;
                    }
                }

                // Внутри App\Controllers\Admin\Materials::uploadFile() после нарезки на parentChunks:
                $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
                $channel = $connection->channel();
                $channel->queue_declare('document_enrichment_tasks', false, true, false, false);

                foreach ($parentChunks as $parentData) {
                    $task = [
                        'profession_id' => $professionId,
                        'document_title' => $documentTitle,
                        'parent_text' => $parentData['text'],
                        'metadata' => $parentData['metadata']
                    ];

                    $msg = new \PhpAmqpLib\Message\AMQPMessage(json_encode($task), ['delivery_mode' => 2]); // persistent
                    $channel->basic_publish($msg, '', 'document_enrichment_tasks');
                }
                $channel->close();
                $connection->close();

            }

            return redirect()->to('/admin/materials')->with('success', "Документ успешно проиндексирован по продвинутой схеме Parent-Child! Сформировано логических разделов (родителей): " . count($parentChunks) . ", создано поисковых векторов (детей): {$savedChildrenCount}.");

        } catch (\Exception $e) {
            return redirect()->to('/admin/materials')->with('error', 'Ошибка RAG-обработки документа: ' . $e->getMessage());
        }
    }
}
