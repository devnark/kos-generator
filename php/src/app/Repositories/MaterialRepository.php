<?php

namespace App\Repositories;

use App\Models\MaterialParentModel;
use App\Models\MaterialChildModel;

class MaterialRepository implements MaterialRepositoryInterface
{
    protected $parentModel;
    protected $childModel;

    public function __construct()
    {
        // Инициализируем новые модели для раздельного хранения контекстов и векторов
        $this->parentModel = new MaterialParentModel();
        $this->childModel = new MaterialChildModel();
    }

    /**
     * Сохранение родительского чанка (содержит полный логический Markdown-текст и метаданные)
     *
     * @param array $data ['profession_id', 'document_title', 'full_text', 'chunk_summary', 'meta_json']
     * @return int ID созданной записи родителя
     */
    public function saveParent(array $data): int
    {
        $this->parentModel->insert([
            'profession_id' => $data['profession_id'],
            'document_title' => $data['document_title'],
            'full_text' => $data['full_text'],
            'chunk_summary' => $data['chunk_summary'] ?? null,
            'test_topic' => $data['test_topic'] ??  '',
            'is_testable' => $data['is_testable'] ??  false,
            'complexity' => $data['complexity'] ??  null,
            'keywords' => $data['keywords'] ??  null,
            'meta_json' => $data['meta_json'] ?? null
        ]);

        return $this->parentModel->getInsertID();
    }

    /**
     * Сохранение дочернего чанка с вектором половинной точности halfvec(4000)
     *
     * @param int $parentId ID родительского контекста
     * @param string $childText Мелкий кусок текста для векторного поиска
     * @param array $vector Массив эмбеддинга (усеченный до 4000 элементов)
     * @return bool
     */
    public function saveChild(int $parentId, string $childText, array $vector): bool
    {
        // Укажите ЖЕСТКУЮ размерность, которая сейчас настроена в вашем Postgres (например, 1024 или 4000)
        $databaseDimension = 4000;
        $currentDimension = count($vector);

        if ($currentDimension < $databaseDimension) {
            log_message('notice', "RAG Notice: Вектор дополнен нулями с {$currentDimension} до {$databaseDimension} измерений.");
            $vector = array_pad($vector, $databaseDimension, 0.0);
        }
            // 1. Защита от полной пустоты (если Ollama упала и вернула пустой массив)
        if (empty($vector)) {
            log_message('error', "RAG Error: Модель вернула пустой вектор для parent_id: {$parentId}");
            return false;
        }

        // Преобразуем массив в строку формата PostgreSQL pgvector: '[0.1,0.2,...]'
        $vectorString = '[' . implode(',', $vector) . ']';

        // Используем прямой SQL-запрос для корректного приведения строки к типу halfvec
        $sql = "INSERT INTO material_children (parent_id, child_text, embedding) VALUES (?, ?, ?::halfvec)";

        return $this->childModel->db->query($sql, [$parentId, $childText, $vectorString]);
    }

    /**
     * Продвинутый поиск ближайших контекстов (Parent-Child Retrieval)
     * Ищет по векторам детей (halfvec), но возвращает уникальные тексты родителей с метаданными.
     *
     * @param array $vector Вектор поискового запроса (должен быть усечен до 4000 элементов)
     * @param int $professionId ID профессии для фильтрации
     * @param int $limit Количество извлекаемых родительских документов
     * @return array
     */
    public function findNearestChunks(array $vector, int $professionId, int $limit = 3): array
    {
        $vectorString = '[' . implode(',', $vector) . ']';

        // Использование DISTINCT ON (p.id) гарантирует, что если несколько мелких чанков
        // совпали внутри одного родительского блока, мы не отправим в LLM дубликаты одного текста.
        $sql = "
            SELECT DISTINCT ON (p.id) 
                p.id, 
                p.full_text, 
                p.chunk_summary, 
                p.document_title, 
                p.meta_json,
                (c.embedding <=> :vector::halfvec) as distance
            FROM material_children c
            JOIN material_parents p ON c.parent_id = p.id
            WHERE p.profession_id = :profession_id:
            ORDER BY p.id, distance ASC
            LIMIT :limit:
        ";

        $results = $this->childModel->db->query($sql, [
            'vector' => $vectorString,
            'profession_id' => $professionId,
            'limit' => $limit
        ])->getResultArray();

        // Оператор DISTINCT ON ломает глобальный порядок сортировки.
        // Пересортируем финальный набор результатов по релевантности (от меньшего расстояния к большему).
        usort($results, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $results;
    }

    public function getTestMap(int $professionId): array
    {
        $sql = "
        SELECT 
            id as parent_chunk_id,
            document_title,
            test_topic,
            complexity,
            meta_json->>'headers' as markdown_breadcrumbs
        FROM material_parents
        WHERE profession_id = ? AND is_testable = TRUE
        ORDER BY id ASC
    ";

        return $this->db->query($sql, [$professionId])->getResultArray();
    }

}
