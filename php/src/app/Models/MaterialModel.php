<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialModel extends Model
{
    protected $table            = 'materials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = ['profession_id', 'text_chunk', 'embedding', 'document_title', 'page_number'];

    protected $useTimestamps    = false;
}
