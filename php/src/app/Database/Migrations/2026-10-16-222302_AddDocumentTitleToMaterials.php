<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentTitleToMaterials extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE materials ADD COLUMN IF NOT EXISTS document_title VARCHAR(255) DEFAULT NULL;");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE materials DROP COLUMN IF EXISTS document_title;");
    }
}
