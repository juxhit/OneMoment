<?php

declare(strict_types=1);

if (!defined('PROJECT_ROOT')) {
    http_response_code(403);
    exit;
}

function admin_header(string $title, string $active = 'dashboard'): void
{
    $pendingCount = class_exists('Media', false) ? Media::countByStatus('pending', Event::getManagedId()) : 0;

    $links = [
        'events' => ['label' => 'Ивенты', 'href' => 'events.php'],
        'dashboard' => ['label' => 'Дашборд', 'href' => 'index.php'],
        'setup' => ['label' => 'Сеть и QR', 'href' => 'setup.php'],
        'settings' => ['label' => 'Ивент', 'href' => 'settings.php'],
        'moderate' => ['label' => 'Модерация', 'href' => 'moderate.php'],
        'export' => ['label' => 'Экспорт', 'href' => 'export.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<div class="wrap">
    <div class="admin-top">
        <h1 class="admin-title"><?= e(APP_NAME) ?> <span class="muted" style="font-size:1rem;font-weight:400;">Админ</span></h1>
        <a class="btn btn-secondary" href="logout.php">Выйти</a>
    </div>
    <nav class="nav">
        <?php foreach ($links as $key => $link): ?>
            <a href="<?= e($link['href']) ?>" class="<?= $key === $active ? 'active' : '' ?>">
                <?= e($link['label']) ?>
                <?php if ($key === 'moderate' && $pendingCount > 0): ?>
                    <span class="nav-badge"><?= (int) $pendingCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php
}

function admin_footer(): void
{
    echo "</div></body></html>";
}