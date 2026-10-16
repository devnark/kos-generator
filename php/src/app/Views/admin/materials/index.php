<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <!-- Локальные стили -->
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/admin/css/bootstrap-icons.min.css') ?>">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-cpu text-primary fs-1 me-3"></i>
        <h1 class="m-0"><?= $title ?></h1>
    </div>

    <!-- Вывод уведомлений -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?= session()->getFlashdata('success') ?></div>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?= session()->getFlashdata('error') ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Левая колонка: Создание профессии и список -->
        <div class="col-md-5">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-briefcase me-2"></i> Добавить профессию
                </div>
                <div class="card-body">
                    <form action="/admin/materials/create-profession" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Название должности / профессии</label>
                            <input type="text" name="name" class="form-control" placeholder="Например: Электрик 3-го разряда" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> Создать
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex align-items-center">
                    <i class="bi bi-list-stars me-2"></i> Существующие профессии
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (empty($professions)): ?>
                        <li class="list-group-item text-muted text-center py-3">Список пока пуст</li>
                    <?php else: ?>
                        <?php foreach ($professions as $prof): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-badge text-secondary me-2"></i><?= esc($prof['name']) ?></span>
                                <span class="badge bg-secondary">ID: <?= $prof['id'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Правая колонка: Загрузка методичек -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white d-flex align-items-center">
                    <i class="bi bi-file-earmark-arrow-up me-2"></i> Загрузить методический материал (RAG)
                </div>
                <div class="card-body">
                    <form action="/admin/materials/upload" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Выбрать профессию</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-layers"></i></span>
                                <select name="profession_id" class="form-select" required>
                                    <option value="" disabled selected>-- Выберите из списка --</option>
                                    <?php foreach ($professions as $prof): ?>
                                        <option value="<?= $prof['id'] ?>"><?= esc($prof['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Файл методички (только .txt или .md)</label>
                            <input type="file" name="schema_file" class="form-control" accept=".txt,.md" required>
                            <div class="form-text text-muted">
                                <i class="bi bi-info-circle me-1"></i> Текст будет автоматически прочитан и сохранен в Postgres.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100" <?= empty($professions) ? 'disabled' : '' ?>>
                            <i class="bi bi-lightning-charge me-1"></i> Проиндексировать материал
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Локальные скрипты -->
<script src="<?= base_url('assets/admin/js/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= base_url('assets/admin/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
