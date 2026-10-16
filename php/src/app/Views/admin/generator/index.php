<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/bootstrap-icons.min.css') ?>">
</head>
<body class="bg-light">

<!-- Навигация -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="bi bi-cpu-fill text-info me-2"></i>AI Test Generator</a>
        <div class="navbar-nav">
            <a class="nav-link" href="/admin/materials"><i class="bi bi-file-earmark-pdf me-1"></i>База знаний (RAG)</a>
            <a class="nav-link active" href="/admin/generator"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Генератор тестов</a>
        </div>
    </div>
</nav>

<div class="container">
    <h1 class="mb-4"><i class="bi bi-magic text-warning me-2"></i><?= $title ?></h1>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Форма постановки задачи -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white font-weight-bold">
                    <i class="bi bi-plus-square me-2"></i>Новая генерация
                </div>
                <div class="card-body">
                    <form action="/admin/generator/create" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Профессия / Специальность</label>
                            <select name="profession_id" class="form-select" required>
                                <option value="" disabled selected>-- Выберите должность --</option>
                                <?php foreach ($professions as $prof): ?>
                                    <option value="<?= $prof['id'] ?>"><?= esc($prof['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Тема для теста (Промпт)</label>
                            <input type="text" name="topic" class="form-control" placeholder="Например: Пожарная безопасность" required>
                            <div class="form-text">ИИ найдет по смыслу абзацы в загруженных PDF по этой теме.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Тип создаваемого опросника</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="typeSingle" value="single" checked>
                                <label class="form-check-label" for="typeSingle">Один правильный ответ (single)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="typeMulti" value="multi">
                                <label class="form-check-label" for="typeMulti">Несколько вариантов ответов (multi)</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" <?= empty($professions) ? 'disabled' : '' ?>>
                            <i class="bi bi-play-circle me-1"></i>Запустить ИИ-цепочку
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Список задач генерации -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white font-weight-bold">
                    <i class="bi bi-collection-play me-2"></i>История генераций (Последние 10 задач)
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle m-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Профессия</th>
                            <th>Тема</th>
                            <th>Тип</th>
                            <th>Статус</th>
                            <th>Действие</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Задач пока нет</td></tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr id="task-row-<?= $task['id'] ?>">
                                    <td><strong>#<?= $task['id'] ?></strong></td>
                                    <td><span class="text-secondary"><?= esc($task['profession_name']) ?></span></td>
                                    <td><span class="fw-bold"><?= esc($task['topic']) ?></span></td>
                                    <td><span class="badge bg-light text-dark border"><?= $task['type'] ?></span></td>
                                    <td class="status-cell">
                                        <?php if ($task['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>В очереди</span>
                                        <?php elseif ($task['status'] === 'processing'): ?>
                                            <span class="badge bg-info text-dark"><i class="bi bi-gear-fill spin me-1"></i>ИИ думает...</span>
                                        <?php elseif ($task['status'] === 'done'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Готово</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-octagon me-1"></i>Ошибка</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-dark btn-view-json"
                                                data-id="<?= $task['id'] ?>"
                                            <?= $task['status'] === 'done' ? '' : 'disabled' ?>>
                                            <i class="bi bi-eye me-1"></i>Просмотр JSON
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно просмотра готового теста -->
<div class="modal fade" id="jsonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-patch-check text-success header-icon me-2"></i> Проверка сгенерированного теста</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Сюда JS будет вставлять красивую разметку -->
                <div id="testVisualPreview"></div>

                <hr class="my-4">

                <h6><i class="bi bi-file-code text-secondary me-2"></i>Сырой JSON ответ (для экспорта):</h6>
                <pre id="jsonViewer" class="p-3 bg-white border rounded" style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 13px;"></pre>
            </div>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/admin/js/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= base_url('assets/admin/js/bootstrap.bundle.min.js') ?>"></script>

<script>
    $(document).ready(function() {
        // Клик на кнопку просмотра JSON тестов
        $(document).on('click', '.btn-view-json', function() {
            let taskId = $(this).data('id');
            $.getJSON('<?= base_url("admin/generator/status") ?>/' + taskId, function(data) {
                if (data.status === 'done') {
                    let test = data.result;

                    // Генерируем красивый HTML-вид теста
                    let html = '<h4 class="mb-3 text-dark">' + test.question + '</h4>';
                    html += '<div class="list-group mb-4 shadow-sm">';

                    test.options.forEach(function(opt) {
                        // Проверяем, является ли этот вариант правильным
                        let isCorrect = test.correct.includes(opt);
                        let activeClass = isCorrect ? 'list-group-item-success fw-bold' : 'bg-white';
                        let icon = isCorrect ? '<i class="bi bi-check-circle-fill text-success me-2"></i>' : '<i class="bi bi-circle me-2 text-muted"></i>';

                        html += '<div class="list-group-item ' + activeClass + '">' + icon + opt + '</div>';
                    });
                    html += '</div>';

                    // Добавляем блок обоснования основания
                    html += '<div class="card border-info bg-white shadow-sm">';
                    html += '  <div class="card-header bg-info text-white fw-bold"><i class="bi bi-info-square me-2"></i>Основание верности ответа (Контекст методички):</div>';
                    html += '  <div class="card-body text-secondary" style="font-size: 15px; line-height: 1.6;">' + (test.rationale ? test.rationale : "Обоснование отсутствует.") + '</div>';
                    html += '</div>';

                    $('#testVisualPreview').html(html);
                    $('#jsonViewer').text(JSON.stringify(test, null, 4));
                    new bootstrap.Modal($('#jsonModal')).show();
                }
            });
        });

        // AJAX Опросник: Каждые 3 секунды проверяем статус задач
        setInterval(function() {
            $('tr[id^="task-row-"]').each(function() {
                let row = $(this);
                let taskId = row.attr('id').replace('task-row-', '');
                let statusBadge = row.find('.status-cell span');

                // Проверяем задачи, которые еще не завершились на экране
                if (statusBadge.hasClass('bg-warning') || statusBadge.hasClass('bg-info') || statusBadge.text().includes('очереди')) {
                    // Заменили путь на явный через base_url()
                    $.getJSON('<?= base_url("admin/generator/status") ?>/' + taskId, function(data) {
                        if (data.status === 'processing') {
                            row.find('.status-cell').html('<span class="badge bg-info text-dark"><i class="bi bi-gear-fill me-1"></i>ИИ думает...</span>');
                        } else if (data.status === 'done') {
                            row.find('.status-cell').html('<span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Готово</span>');
                            row.find('.btn-view-json').removeAttr('disabled');
                        } else if (data.status === 'failed') {
                            row.find('.status-cell').html('<span class="badge bg-danger" title="'+data.error_msg+'"><i class="bi bi-exclamation-octagon me-1"></i>Ошибка</span>');
                        }
                    });
                }
            });
        }, 3000);
    });
</script>
