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

            // Инициализируем парсер
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfPath);

            // Получаем ВСЕ страницы документа по отдельности
            $pages = $pdf->getPages();
            $splitter = new \App\Libraries\TextSplitter();
            $savedCount = 0;

            // Перебираем страницы циклично. Переменная $pageNum — это реальная страница в книге!
            foreach ($pages as $index => $page) {
                $pageNum = $index + 1; // Страницы нумеруются с 1
                $pageText = $page->getText();

                if (empty(trim($pageText))) {
                    continue; // Пропускаем пустые страницы
                }

                // Нарезаем текст ТЕКУЩЕЙ СТРАНИЦЫ на чанки
                $chunks = $splitter->splitText($pageText, 1200, 150);

                foreach ($chunks as $chunkText) {
                    // Генерируем эмбеддинг для чанка
                    $embedding = $this->ollamaClient->getEmbedding($chunkText);

                    if (!empty($embedding)) {
                        // Сохраняем в базу, передавая РЕАЛЬНЫЙ номер страницы $pageNum
                        $this->materialRepo->saveChunk((int)$professionId, $chunkText, $embedding, $documentTitle, $pageNum);
                        $savedCount++;
                    }
                }
            }

            return redirect()->to('/admin/materials')->with('success', "Документ успешно проиндексирован постранично! Создано чанков с точной адресацией: {$savedCount}.");

        } catch (\Exception $e) {
            return redirect()->to('/admin/materials')->with('error', 'Ошибка при обработке PDF: ' . $e->getMessage());
        }
    }

}
