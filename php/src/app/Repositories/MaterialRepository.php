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
    public function saveChunk(int $professionId, string $textChunk, array $vector): int
    {
        // Превращаем PHP-массив эмбеддинга в строку '[0.11,0.22,...]' для pgvector
        $vectorString = '[' . implode(',', $vector) . ']';

        $data = [
            'profession_id' => $professionId,
            'text_chunk'    => $textChunk,
            'embedding'     => $vectorString
        ];

        $this->materialModel->insert($data);

        return $this->materialModel->getInsertID();
    }

    /**
     * Семантический поиск по векторам (RAG контекст для ИИ)
     */
    public function findNearestChunks(array $vector, int $professionId, int $limit = 3): array
    {
        $vectorString = '[' . implode(',', $vector) . ']';

        // Строим чистый и оптимизированный SQL-запрос
        return $this->materialModel->db->query("
            SELECT id, text_chunk, (embedding <=> :vector:) AS distance
            FROM materials
            WHERE profession_id = :profession_id:
            ORDER BY embedding <=> :vector: ASC
            LIMIT :limit:
        ", [
            'vector'        => $vectorString,
            'profession_id' => $professionId,
            'limit'         => $limit
        ])->getResultArray();
    }
}
