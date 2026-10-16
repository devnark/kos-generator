<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialModel extends Model
{
    protected $table            = 'materials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    // Проверить эту строчку: должно быть allowedFields (с буквой S на конце!)
    protected $allowedFields    = ['profession_id', 'text_chunk', 'embedding'];

    protected $useTimestamps    = false;
}
