<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTestFieldsToMaterialParents extends Migration
{
    public function up()
    {
        // Описываем добавляемые поля в соответствии со схемой PostgreSQL
        $fields = [
            'test_topic' => [
                'type'       => 'TEXT',
                'null'       => false,
                'default'    => '',
            ],
            'is_testable' => [
                'type'       => 'BOOLEAN',
                'null'       => false,
                'default'    => false,
            ],
            'complexity' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
                'default'    => null,
            ],
            'keywords' => [
                'type'       => 'JSONB', // Используем родной JSONB для PostgreSQL
                'null'       => true,
                'default'    => null,
            ],
        ];

        // Добавляем колонки в таблицу
        $this->forge->addColumn('material_parents', $fields);

        // Создаем индекс для поля is_testable, так как по нему будет частая выборка при генерации тестов
        $this->db->query('CREATE INDEX idx_material_parents_is_testable ON material_parents (is_testable);');
    }

    public function down()
    {
        // Удаляем индекс при откате миграции
        $this->db->query('DROP INDEX IF EXISTS idx_material_parents_is_testable;');

        // Удаляем колонки
        $this->forge->dropColumn('material_parents', ['test_topic', 'is_testable', 'complexity', 'keywords']);
    }
}
