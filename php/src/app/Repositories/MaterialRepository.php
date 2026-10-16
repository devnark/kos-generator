<?php

namespace App\Repositories;

use App\Models\MaterialModel;

class MaterialRepository implements MaterialRepositoryInterface
{
    protected $materialModel;

    public function __construct()
    {
        $this->materialModel = new MaterialModel();
    }

    /**
     * Сохранение нового куска методички с вектором
     */
    public function saveChunk(int $professionId, string $textChunk, array $vector, string $documentTitle): int
    {
        $vectorString = '[' . implode(',', $vector) . ']';

        $data = [
            'profession_id' => $professionId,
            'text_chunk' => $textChunk,
            'embedding' => $vectorString,
            'document_title' => $documentTitle // Сохраняем имя файла / ГОСТ
        ];

        $this->materialModel->insert($data);
        return $this->materialModel->getInsertID();
    }

    public function findNearestChunks(array $vector, int $professionId, int $limit = 3): array
    {
        $vectorString = '[' . implode(',', $vector) . ']';

        // Добавляем выборку поля document_title в SQL запрос
        return $this->materialModel->db->query("
            SELECT id, text_chunk, document_title, (embedding <=> :vector:) AS distance
            FROM materials
            WHERE profession_id = :profession_id:
            ORDER BY embedding <=> :vector: ASC
            LIMIT :limit:
        ", [
            'vector' => $vectorString,
            'profession_id' => $professionId,
            'limit' => $limit
        ])->getResultArray();
    }
}