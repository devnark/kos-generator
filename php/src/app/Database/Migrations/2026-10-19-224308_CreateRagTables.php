<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRagTables extends Migration
{
    public function up()
    {
        // 1. Включаем pgvector (если не был включен ранее)
        $this->db->query('CREATE EXTENSION IF NOT EXISTS vector');

        // ==========================================
        // 2. СОЗДАЕМ ТАБЛИЦУ РОДИТЕЛЬСКИХ ЧАНКОВ
        // ==========================================
        $this->forge->addField([
            'id' => [
                'type' => 'SERIAL',
                'unsigned' => true,
            ],
            'profession_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'document_title' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'full_text' => [
                'type' => 'TEXT',
            ],
            'chunk_summary' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'meta_json' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('profession_id');
        $this->forge->createTable('material_parents', true);

        // ==========================================
        // 3. СОЗДАЕМ ТАБЛИЦУ ДОЧЕРНИХ ЧАНКОВ
        // ==========================================
        $this->forge->addField([
            'id' => [
                'type' => 'SERIAL',
                'unsigned' => true,
            ],
            'parent_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'child_text' => [
                'type' => 'TEXT',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('material_children', true);

        // 4. Добавляем halfvec(4000) только если колонки ещё нет
        $this->db->query('
            ALTER TABLE material_children 
            ADD COLUMN IF NOT EXISTS embedding halfvec(4000)
        ');

        // 5. Связываем внешний ключ (с проверкой, чтобы не дублировать)
        $this->db->query('
            ALTER TABLE material_children 
            DROP CONSTRAINT IF EXISTS fk_material_parent
        ');

        $this->db->query('
            ALTER TABLE material_children 
            ADD CONSTRAINT fk_material_parent 
            FOREIGN KEY (parent_id) 
            REFERENCES material_parents(id) 
            ON DELETE CASCADE
        ');

        // 6. Создаем HNSW индекс под halfvec
        $this->db->query('
            CREATE INDEX IF NOT EXISTS idx_material_children_embedding 
            ON material_children 
            USING hnsw (embedding halfvec_cosine_ops)
        ');
    }

    public function down()
    {
        // Удаляем в обратном порядке из-за зависимостей констреинтов
        $this->forge->dropTable('material_children', true);
        $this->forge->dropTable('material_parents', true);
    }
}
