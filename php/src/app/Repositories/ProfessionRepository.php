<?php

namespace App\Repositories;

use App\Models\ProfessionModel;

class ProfessionRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = new ProfessionModel();
    }

    public function getAll(): array
    {
        return $this->model->orderBy('name', 'ASC')->findAll();
    }

    public function create(string $name): int
    {
        $this->model->insert(['name' => $name]);
        return $this->model->getInsertID();
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }
}
