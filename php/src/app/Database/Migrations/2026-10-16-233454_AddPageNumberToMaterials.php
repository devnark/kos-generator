<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPageNumberToMaterials extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE materials ADD COLUMN IF NOT EXISTS page_number INT DEFAULT NULL;");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE materials DROP COLUMN IF EXISTS page_number;");
    }
}
