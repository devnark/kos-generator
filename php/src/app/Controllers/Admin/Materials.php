<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Services;

class Materials extends BaseController
{
    protected $professionRepo;
    protected $materialRepo;
    protected $ollamaClient; // Добавили свойство

    public function __construct()
    {
        $this->professionRepo = Services::professionRepository();
        $this->materialRepo = Services::materialRepository();
        $this->ollamaClient = Services::ollamaClient(); // Инициализация ИИ
    }

    public function index()
    {
        $data = [
            'title'       => 'Управление материалами ИИ',
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
        $professionId = $this->request->getPost('profession_id');
        $file = $this->request->getFile('schema_file');

        if (!$file->isValid() || $file->getExtension() !== 'pdf') {
            return redirect()->to('/admin/materials')->with('error', 'Пожалуйста, загрузите корректный файл в формате PDF.');
        }

        try {
            $pdfPath = $file->getTempName();

            // 1. Извлекаем обычный текст
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfPath);
            $ordinaryText = $pdf->getText();

            // 2. АВТОМАТИЗАЦИЯ: Вызываем Python-скрипт для поиска и форматирования таблиц
            $pythonScript = APPPATH . 'Libraries/table_parser.py';
            // Выполняем команду внутри контейнера Linux
            $command = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg($pdfPath);
            $tablesText = shell_exec($command);

            // 3. Склеиваем обычный текст и идеально отформатированные таблицы вместе
            $fullContent = $ordinaryText . "\n\n" . $tablesText;

            if (empty(trim($fullContent))) {
                return redirect()->to('/admin/materials')->with('error', 'Не удалось извлечь данные из PDF.');
            }

            // 4. Нарезаем склеенный текст на чанки и отправляем в Ollama
            $splitter = new \App\Libraries\TextSplitter();
            $chunks = $splitter->splitText($fullContent, 1200, 200);

            $savedCount = 0;
            foreach ($chunks as $chunkText) {
                $embedding = $this->ollamaClient->getEmbedding($chunkText);
                if (!empty($embedding)) {
                    $this->materialRepo->saveChunk((int)$professionId, $chunkText, $embedding);
                    $savedCount++;
                }
            }

            return redirect()->to('/admin/materials')->with(
                'success',
                "Документ успешно обработан! Обычный текст извлечен, таблицы автоматически переведены в Markdown-формат. Создано чанков: {$savedCount}."
            );

        } catch (\Exception $e) {
            return redirect()->to('/admin/materials')->with('error', 'Ошибка при обработке PDF: ' . $e->getMessage());
        }
    }
}
