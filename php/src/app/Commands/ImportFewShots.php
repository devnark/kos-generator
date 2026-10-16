<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ImportFewShots extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:import-fewshots';
    protected $description = 'Импортирует эталонные тесты из старой базы MySQL в Postgres';

    public function run(array $params)
    {
        $dbPostgres = \Config\Database::connect('default');
        $dbMysql    = \Config\Database::connect('old_mysql');

        $professionId = CLI::prompt('Введите ID профессии в Postgres, для которой загружаются тесты:', null, 'required|is_natural_no_zero');

        CLI::write('Читаем данные из MySQL...', 'yellow');

        $query = $dbMysql->query("
            SELECT id, data 
            FROM igvs_courses_modules_contents 
            WHERE course_id = 1842
            AND template_id in (1,2)
        ");

        $rows = $query->getResultArray();
        $importedCount = 0;
        $skippedCount  = 0;

        foreach ($rows as $row) {
            $data = json_decode($row['data'], true);

            // Если JSON пустой или в нём нет секции с вопросами — пропускаем строку
            if (empty($data) || !isset($data['questions']) || !is_array($data['questions'])) {
                $skippedCount++;
                continue;
            }

            foreach ($data['questions'] as $q) {
                // Защита: если нет самого текста вопроса или блока ответов — пропускаем этот вопрос
                if (!isset($q['task']) || !isset($q['answers']) || !is_array($q['answers'])) {
                    $skippedCount++;
                    continue;
                }

                $questionText = trim($q['task']);
                $options = [];
                $correctAnswers = [];

                foreach ($q['answers'] as $ans) {
                    // Защита от пустых строк ответов
                    if (!isset($ans['question'])) {
                        continue;
                    }

                    $answerText = trim($ans['question']);
                    $options[] = $answerText;

                    if (isset($ans['correct']) && ($ans['correct'] === 'true' || $ans['correct'] === true || $ans['correct'] === '1' || $ans['correct'] === 1)) {
                        $correctAnswers[] = $answerText;
                    }
                }

                // Если не нашлось ни одного правильного ответа, тест некорректен — пропускаем
                if (empty($correctAnswers)) {
                    $skippedCount++;
                    continue;
                }

                $type = (count($correctAnswers) > 1) ? 'multi' : 'single';

                $dbPostgres->table('few_shots')->insert([
                    'profession_id'  => (int)$professionId,
                    'type'           => $type,
                    'question'       => $questionText,
                    'options'        => json_encode($options, JSON_UNESCAPED_UNICODE),
                    'correct_answer' => json_encode($correctAnswers, JSON_UNESCAPED_UNICODE),
                ]);

                $importedCount++;
            }
        }

        CLI::write("Импорт завершен успешно!", 'green');
        CLI::write("Перенесено валидных вопросов: {$importedCount}", 'green');
        CLI::write("Пропущено пустых/некорректных блоков: {$skippedCount}", 'light_gray');
    }
}
