<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require __DIR__ . '/_layout.php';

AdminAuth::requireLogin();

$event = Event::get();

$albumUrl = guest_url('e/' . $event['token']);
$wallUrl = guest_url('wall/' . $event['token']);
$joinUrl = guest_url('join/' . $event['room_code']);

$qrLinks = [
    ['id' => 'qr-album', 'label' => 'Альбом (загрузка)', 'url' => $albumUrl],
    ['id' => 'qr-join', 'label' => 'Вход по коду', 'url' => $joinUrl],
    ['id' => 'qr-wall', 'label' => 'Live Wall', 'url' => $wallUrl],
];

$deploy = DeployCheck::run();

admin_header('Ссылки и QR', 'setup');
?>

<div class="card">
    <h2>Публичный адрес</h2>
    <?php if ($deploy['public_url']): ?>
        <div class="url-box"><?= e($deploy['public_url']) ?></div>
    <?php else: ?>
        <p class="alert alert-error">PUBLIC_BASE_URL не задан. Проверьте <code>.env</code> и перезапустите контейнер.</p>
    <?php endif; ?>
    <p class="muted">Код комнаты: <strong><?= e($event['room_code']) ?></strong></p>
</div>

<div class="card">
    <h2>Ссылки для гостей</h2>
    <p class="muted">Альбом</p>
    <div class="url-box"><?= e($albumUrl) ?></div>
    <p class="muted">Live Wall</p>
    <div class="url-box"><?= e($wallUrl) ?></div>
    <p class="muted">Вход по коду</p>
    <div class="url-box"><?= e($joinUrl) ?></div>
</div>

<div class="card qr-section">
    <h2>QR-коды</h2>
    <p class="muted">Для печати или показа на экране у входа.</p>
    <p class="btn-row" style="margin-bottom:1rem;">
        <button type="button" class="btn btn-secondary" onclick="window.print()">Печать всех QR</button>
    </p>
    <div class="qr-grid">
        <?php foreach ($qrLinks as $item): ?>
            <div class="qr-card">
                <div class="qr-frame">
                    <img src="<?= e(qr_image_url($item['url'])) ?>" alt="<?= e($item['label']) ?>" width="200" height="200" loading="lazy" class="qr-img">
                </div>
                <p class="qr-label"><?= e($item['label']) ?></p>
                <div class="url-box qr-url"><?= e($item['url']) ?></div>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary btn-sm" data-copy="<?= e($item['url']) ?>">Копировать ссылку</button>
                    <a class="btn btn-secondary btn-sm" href="<?= e(qr_image_url($item['url'], 10)) ?>" download="onemoment-<?= e($item['id']) ?>.png">Скачать PNG</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="muted qr-copy-status" id="qr-copy-status" hidden></p>
</div>

<div class="card">
    <h2>Состояние сервера</h2>
    <ul class="checklist">
        <li class="check-<?= DeployCheck::statusLabel($deploy['https_active']) ?>">HTTPS активен</li>
        <li class="check-<?= DeployCheck::statusLabel($deploy['public_url_set']) ?>">PUBLIC_BASE_URL задан</li>
        <li class="check-<?= DeployCheck::statusLabel($deploy['gd']) ?>">PHP GD</li>
        <li class="check-<?= DeployCheck::statusLabel($deploy['zip']) ?>">PHP ZIP</li>
        <li class="check-<?= DeployCheck::statusLabel($deploy['sqlite']) ?>">SQLite</li>
        <li class="check-<?= DeployCheck::statusLabel($deploy['writable_data']) ?>">Каталог data/ доступен для записи</li>
        <li class="check-<?= DeployCheck::statusLabel($deploy['writable_uploads']) ?>">Каталог uploads/ доступен для записи</li>
    </ul>
    <p class="muted">Деплой: <code>deploy/VPS-DOCKER.md</code></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var status = document.getElementById('qr-copy-status');
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy') || '';
            if (!text) return;
            var done = function () {
                if (status) {
                    status.hidden = false;
                    status.textContent = 'Ссылка скопирована';
                    setTimeout(function () { status.hidden = true; }, 2000);
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () {
                    window.prompt('Скопируйте ссылку:', text);
                });
            } else {
                window.prompt('Скопируйте ссылку:', text);
            }
        });
    });
});
</script>
<?php admin_footer();