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
            // 1. Извлекаем весь текст из PDF
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file->getTempName());
            $fullText = $pdf->getText();

            // Забираем название документа из POST
            $documentTitle = trim($this->request->getPost('document_title') ?? '');

            // АВТОМАТИЗАЦИЯ: Если поле пустое, берем оригинальное имя загруженного PDF-файла
            if (empty($documentTitle)) {
                $documentTitle = $file->getClientName(); // Вернет, например, "metodichka_manikyur.pdf"
            }

            if (empty(trim($fullText))) {
                return redirect()->to('/admin/materials')->with('error', 'Не удалось извлечь текст из PDF.');
            }

            // 2. Нарезаем текст на умные куски (чанки)
            $splitter = new \App\Libraries\TextSplitter();
            $chunks = $splitter->splitText($fullText, 1200, 200);

            $savedCount = 0;

            // 3. Циклом отправляем каждый чанк в Ollama и сохраняем в Postgres
            foreach ($chunks as $chunkText) {
                // Запрос к модели bge-m3 на Windows-сервере
                $embedding = $this->ollamaClient->getEmbedding($chunkText);

                if (!empty($embedding)) {
                    // Сохраняем кусок текста и его персональный вектор в Postgres
                    $this->materialRepo->saveChunk((int)$professionId, $chunkText, $embedding, $documentTitle);
                    $savedCount++;
                }
            }

            return redirect()->to('/admin/materials')->with(
                'success',
                "Документ успешно обработан! Разбит на {$savedCount} чанков. Для каждого сгенерирован реальный эмбеддинг!"
            );

        } catch (\Exception $e) {
            return redirect()->to('/admin/materials')->with('error', 'Ошибка при обработке PDF: ' . $e->getMessage());
        }
    }
}
