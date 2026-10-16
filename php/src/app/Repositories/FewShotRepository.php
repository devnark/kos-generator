<?php

namespace App\Repositories;

use App\Models\FewShotModel;

class FewShotRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = new FewShotModel();
    }

    /**
     * Вытащить случайные примеры тестов для Few-Shot обучения ИИ
     */
    public function getRandomExamples(int $professionId, string $type, int $limit = 3): array
    {
        return $this->model->where('profession_id', $professionId)
            ->where('type', $type)
            ->orderBy('RANDOM()') // Специфика синтаксиса Postgres для случайной выборки
            ->findAll($limit);
    }
}
