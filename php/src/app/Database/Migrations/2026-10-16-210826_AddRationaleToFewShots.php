<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRationaleToFewShots extends Migration
{
    public function up()
    {
        // Добавляем текстовое поле rationale в таблицу эталонных тестов (Few-Shot)
        $this->db->query("
            ALTER TABLE few_shots 
            ADD COLUMN IF NOT EXISTS rationale TEXT DEFAULT NULL;
        ");
    }

    public function down()
    {
        // Откат миграции
        $this->db->query("
            ALTER TABLE few_shots 
            DROP COLUMN IF EXISTS rationale;
        ");
    }
}
