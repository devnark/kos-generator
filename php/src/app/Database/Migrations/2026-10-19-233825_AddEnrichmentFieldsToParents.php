<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEnrichmentFieldsToParents extends Migration
{
    public function up()
    {
        // Добавляем стандартные текстовые и булевые поля
        $fields = [
            'test_topic' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'after'      => 'meta_json' // Размещаем после meta_json для порядка
            ],
            'is_testable' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
                'null'       => false,
                'after'      => 'test_topic'
            ],
            'complexity' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'medium',
                'null'       => false,
                'after'      => 'is_testable'
            ],
        ];

        $this->forge->addColumn('material_parents', $fields);

        // Поле JSONB для ключевых слов добавляем через прямой запрос,
        // так как Forge в CI4 по умолчанию не всегда корректно маппит JSONB при изменении таблиц Postgres
        $this->db->query('ALTER TABLE material_parents ADD COLUMN keywords JSONB NULL');

        // Создаем индекс для поля is_testable, так как мы будем постоянно фильтровать по нему оглавление тестов
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_parents_is_testable ON material_parents(is_testable)');
    }

    public function down()
    {
        // Откат изменений в обратном порядке
        $this->db->query('DROP INDEX IF EXISTS idx_parents_is_testable');
        $this->forge->dropColumn('material_parents', 'keywords');
        $this->forge->dropColumn('material_parents', 'complexity');
        $this->forge->dropColumn('material_parents', 'is_testable');
        $this->forge->dropColumn('material_parents', 'test_topic');
    }
}
