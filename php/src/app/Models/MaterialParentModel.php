<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialParentModel extends Model
{
    protected $table            = 'material_parents';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    // Включаем новые поля для ИИ-метаданных
    protected $allowedFields = [
        'profession_id',
        'document_title',
        'full_text',
        'chunk_summary',
        'meta_json',
        'test_topic',
        'is_testable',
        'complexity',
        'keywords'
    ];

    protected $useTimestamps    = false;
}
