<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAiTables extends Migration
{
    public function up()
    {
        // 1. Активируем расширение pgvector внутри нашей базы данных
        $this->db->query('CREATE EXTENSION IF NOT EXISTS vector;');

        // 2. Таблица профессий (Справочник)
        $this->db->query("
            CREATE TABLE professions (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 3. Таблица методических материалов (Для RAG)
        // Вектор модели bge-m3 имеет размерность 1024 (mxbai-embed-large — 1536, qwen — 3584)
        // Выберем под модель bge-m3 размерность 1024. Если решишь сменить модель, поменяй цифру.
        $this->db->query("
            CREATE TABLE materials (
                id SERIAL PRIMARY KEY,
                profession_id INT REFERENCES professions(id) ON DELETE CASCADE,
                text_chunk TEXT NOT NULL,
                embedding vector(1024), 
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Создаем специальный индекс для быстрого поиска по векторам (HNSW индекс)
        $this->db->query("
            CREATE INDEX ON materials USING hnsw (embedding vector_cosine_ops);
        ");

        // 4. Таблица эталонных тестов (Для Few-Shot примеров)
        $this->db->query("
            CREATE TABLE few_shots (
                id SERIAL PRIMARY KEY,
                profession_id INT REFERENCES professions(id) ON DELETE CASCADE,
                type VARCHAR(50) NOT NULL, -- single (один ответ) или multi (несколько)
                question TEXT NOT NULL,
                options JSONB NOT NULL,    -- варианты ответов в формате JSON
                correct_answer JSONB NOT NULL, -- правильный(е) ответ(ы)
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function down()
    {
        // Удаляем таблицы в обратном порядке при откате миграции
        $this->db->query('DROP TABLE IF EXISTS few_shots;');
        $this->db->query('DROP TABLE IF EXISTS materials;');
        $this->db->query('DROP TABLE IF EXISTS professions;');
        $this->db->query('DROP EXTENSION IF EXISTS vector;');
    }
}
