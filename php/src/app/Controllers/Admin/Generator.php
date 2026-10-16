<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Config\Services;

class Generator extends BaseController
{
    protected $professionRepo;

    public function __construct()
    {
        $this->professionRepo = Services::professionRepository();
    }

    /**
     * Главный экран генератора
     */
    public function index()
    {
        $db = \Config\Database::connect();

        $data = [
            'title'       => 'Генератор тестов ИИ',
            'professions' => $this->professionRepo->getAll(),
            // Выводим последние задачи
            'tasks'       => $db->table('generation_tasks')
                ->select('generation_tasks.*, professions.name as profession_name')
                ->join('professions', 'professions.id = generation_tasks.profession_id')
                ->orderBy('generation_tasks.id', 'DESC')
                ->limit(10)
                ->get()->getResultArray()
        ];

        return view('admin/generator/index', $data);
    }

    /**
     * Создание задачи и отправка в RabbitMQ
     */
    public function create()
    {
        $professionId = $this->request->getPost('profession_id');
        $topic        = trim($this->request->getPost('topic'));
        $type         = $this->request->getPost('type');

        if (empty($topic) || empty($professionId)) {
            return redirect()->to('/admin/generator')->with('error', 'Заполните все поля формы.');
        }

        $db = \Config\Database::connect();

        // 1. Пишем задачу в Postgres со статусом pending
        $db->table('generation_tasks')->insert([
            'profession_id' => (int)$professionId,
            'topic'         => $topic,
            'type'          => $type,
            'status'        => 'pending'
        ]);

        $taskId = $db->insertID();

        try {
            // 2. Публикуем задачу в RabbitMQ
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel = $connection->channel();
            $channel->queue_declare('test_generation_tasks', false, true, false, false);

            $msgBody = json_encode(['task_id' => $taskId]);
            $msg = new AMQPMessage($msgBody, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);

            $channel->basic_publish($msg, '', 'test_generation_tasks');

            $channel->close();
            $connection->close();

            return redirect()->to('/admin/generator')->with('success', 'Задача успешно добавлена в очередь RabbitMQ! ИИ начал работу.');
        } catch (\Exception $e) {
            // Если RabbitMQ упал, помечаем задачу как проваленную
            $db->table('generation_tasks')->where('id', $taskId)->update(['status' => 'failed', 'error_message' => 'RabbitMQ error: ' . $e->getMessage()]);
            return redirect()->to('/admin/generator')->with('error', 'Ошибка RabbitMQ: ' . $e->getMessage());
        }
    }

    /**
     * Эндпоинт для AJAX-проверки статуса задачи
     */
    public function checkStatus(int $id)
    {
        $db = \Config\Database::connect();
        $task = $db->table('generation_tasks')->where('id', $id)->get()->getRowArray();

        if (!$task) {
            return $this->response->setJSON(['error' => 'Task not found']);
        }

        return $this->response->setJSON([
            'status'      => $task['status'],
            'result'      => $task['result_json'] ? json_decode($task['result_json'], true) : null,
            'error_msg'   => $task['error_message']
        ]);
    }
}
