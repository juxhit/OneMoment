<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require __DIR__ . '/_layout.php';

AdminAuth::startSession();
$error = '';

if (request_method() === 'POST' && AdminAuth::check() === false) {
    $password = (string) ($_POST['password'] ?? '');
    if (!AdminAuth::login($password)) {
        $error = 'Неверный пароль.';
    } else {
        redirect('admin/');
    }
}

if (!AdminAuth::check()) {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<div class="wrap" style="max-width:420px;margin-top:4rem;">
    <h1><?= e(APP_NAME) ?></h1>
    <p class="muted">Вход для организатора</p>
    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="card">
        <label for="password">Пароль</label>
        <input type="password" id="password" name="password" required autofocus>
        <button type="submit" class="btn">Войти</button>
    </form>
    <p class="muted">По умолчанию: <code>password</code> — смените в <code>config/config.local.php</code></p>
</div>
</body>
</html>
    <?php
    exit;
}

$event = Event::get();
$albumUrl = guest_url('e/' . $event['token']);
$wallUrl = guest_url('wall/' . $event['token']);
$joinUrl = guest_url('join/' . $event['room_code']);
$healthApiUrl = url('api/health.php');

admin_header('Дашборд', 'dashboard');
?>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<div class="grid-2">
    <div class="card">
        <h2><?= e($event['title']) ?></h2>
        <p class="muted">Ивент активен · код комнаты <strong><?= e($event['room_code']) ?></strong></p>
        <p>Фото: <strong><?= Media::countAll() ?></strong>
            <?php $pending = Media::countByStatus('pending'); if ($pending > 0): ?>
                · в очереди: <strong><?= $pending ?></strong>
            <?php endif; ?>
        </p>
        <p class="muted">Модерация: <?= (int) $event['moderation_mode'] === 1 ? 'включена (G2)' : 'выключена (G1)' ?></p>
    </div>
    <div class="card">
        <h2>Ссылки для гостей</h2>
        <p class="muted">Альбом (загрузка)</p>
        <div class="url-box"><?= e($albumUrl) ?></div>
        <p class="muted">Live Wall</p>
        <div class="url-box"><?= e($wallUrl) ?></div>
        <p class="muted">Вход по коду (без IP в QR)</p>
        <div class="url-box"><?= e($joinUrl) ?></div>
    </div>
</div>

<div class="card">
    <h2>Быстрые действия</h2>
    <p class="btn-row">
        <a class="btn btn-secondary" href="settings.php">Настройки ивента</a>
        <?php if (Media::countByStatus('pending') > 0): ?>
            <a class="btn" href="moderate.php">Модерация (<?= Media::countByStatus('pending') ?>)</a>
        <?php endif; ?>
    </p>
</div>
<div class="card" x-data="adminHealthWidget(<?= json_encode($healthApiUrl, JSON_UNESCAPED_UNICODE) ?>)" x-init="load()">
    <h2>Диск и квоты</h2>
    <p class="muted" x-show="error" x-text="error"></p>
    <template x-if="health">
        <div>
            <p>
                <span class="health-status" :class="'health-' + (health.disk_status || 'green')"></span>
                Свободно: <strong x-text="formatBytes(health.disk_free_bytes)"></strong>
                <span class="muted" x-show="health.disk_used_percent !== null">
                    · занято <span x-text="health.disk_used_percent"></span>%
                </span>
            </p>
            <p>Фото в альбоме: <strong x-text="health.media_count"></strong>
                · объём <strong x-text="formatBytes(health.media_bytes)"></strong>
                · лимит <strong x-text="health.max_files"></strong> шт.</p>
            <p class="muted" x-show="health.pending_count > 0">
                В очереди модерации: <span x-text="health.pending_count"></span>
            </p>
        </div>
    </template>
</div>
<?php admin_footer();