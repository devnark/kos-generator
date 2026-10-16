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
    public function uploadFile()
    {
        $professionId = $this->request->getPost('profession_id');
        $file = $this->request->getFile('schema_file');

        if (!$file->isValid()) {
            return redirect()->to('/admin/materials')->with('error', 'Ошибка при загрузке файла.');
        }

        // Читаем содержимое файла (пока берем TXT / MD)
        $textContent = file_get_contents($file->getTempName());

        if (empty(trim($textContent))) {
            return redirect()->to('/admin/materials')->with('error', 'Файл пустой.');
        }

        // Временное решение: сохраняем весь текст целиком БЕЗ вектора (передаем пустой массив),
        // чтобы проверить запись в Postgres. На следующем шаге мы подключим сюда Ollama.
        $this->materialRepo->saveChunk((int)$professionId, $textContent, array_fill(0, 1024, 0));

        return redirect()->to('/admin/materials')->with('success', 'Методический материал успешно загружен в базу!');
    }
}
