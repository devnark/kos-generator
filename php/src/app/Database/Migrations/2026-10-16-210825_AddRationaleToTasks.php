<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRationaleToTasks extends Migration
{
    public function up()
    {
        // Добавляем текстовое поле rationale в таблицу задач генерации
        $this->db->query("
            ALTER TABLE generation_tasks 
            ADD COLUMN IF NOT EXISTS rationale TEXT DEFAULT NULL;
        ");
    }

    public function down()
    {
        // Откат миграции: удаляем колонку, если она существует
        $this->db->query("
            ALTER TABLE generation_tasks 
            DROP COLUMN IF EXISTS rationale;
        ");
    }
}
