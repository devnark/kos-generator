<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTasksTable extends Migration
{
    public function up()
    {
        $this->db->query("
            CREATE TABLE generation_tasks (
                id SERIAL PRIMARY KEY,
                profession_id INT REFERENCES professions(id) ON DELETE CASCADE,
                topic VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL, -- single / multi
                status VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, processing, done, failed
                result_json JSONB DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS generation_tasks;");
    }
}
