<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Services;

class Materials extends BaseController
{
    protected $professionRepo;
    protected $materialRepo;

    public function __construct()
    {
        $this->professionRepo = Services::professionRepository();
        $this->materialRepo = Services::materialRepository();
    }

    /**
     * Главная страница: список профессий и формы
     */
    public function index()
    {
        $data = [
            'title'       => 'Управление материалами ИИ',
            'professions' => $this->professionRepo->getAll()
        ];

        return view('admin/materials/index', $data);
    }

    /**
     * Обработка создания профессии
     */
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
     * Обработка загрузки методички (пока сохраняем как один сырой текст для проверки)
     */
    /**
     * Обработка загрузки методички в формате PDF
     */
    public function uploadFile()
    {
        $professionId = $this->request->getPost('profession_id');
        $file = $this->request->getFile('schema_file');

        if (!$file->isValid() || $file->getExtension() !== 'pdf') {
            return redirect()->to('/admin/materials')->with('error', 'Пожалуйста, загрузите корректный файл в формате PDF.');
        }

        try {
            // Инициализируем установленный парсер PDF документов
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($file->getTempName());

            // Извлекаем чистый текст со всех страниц
            $textContent = $pdf->getText();

            if (empty(trim($textContent))) {
                return redirect()->to('/admin/materials')->with('error', 'Не удалось извлечь текст из PDF (возможно, файл защищен или содержит только сканы-картинки).');
            }

            // Временная заглушка пустого вектора (1024 нуля для модели bge-m3)
            $this->materialRepo->saveChunk((int)$professionId, $textContent, array_fill(0, 1024, 0));

            return redirect()->to('/admin/materials')->with('success', 'Текст из PDF успешно извлечен и сохранен в Postgres!');

        } catch (\Exception $e) {
            return redirect()->to('/admin/materials')->with('error', 'Ошибка при обработке PDF: ' . $e->getMessage());
        }
    }
}
