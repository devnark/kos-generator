<?php

namespace App\Models;

use CodeIgniter\Model;

class ProfessionModel extends Model
{
    protected $table            = 'professions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    // Проверь эту строчку: должно быть allowedFields (с буквой S на конце!)
    protected $allowedFields    = ['name'];

    protected $useTimestamps    = false;
}
