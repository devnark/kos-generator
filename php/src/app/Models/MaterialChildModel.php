<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialChildModel extends Model
{
    // Имя таблицы в PostgreSQL
    protected $table            = 'material_children';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    // Разрешенные поля для дочерних чанков
    protected $allowedFields = [
        'parent_id',
        'child_text',
        'embedding'
    ];

    protected $useTimestamps    = false;
}
