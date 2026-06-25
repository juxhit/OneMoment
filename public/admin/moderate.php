<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require __DIR__ . '/_layout.php';

AdminAuth::requireLogin();

$event = Event::get();
$message = '';
$error = '';

if (request_method() === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $ok = $action === 'approve' ? Media::approve($id) : Media::reject($id);
        if ($ok) {
            $message = $action === 'approve' ? 'Фото одобрено.' : 'Фото отклонено и удалено.';
        } else {
            $error = 'Не удалось обработать фото (возможно, уже обработано).';
        }
    } else {
        $error = 'Некорректный запрос.';
    }
}

$moderationOn = (int) ($event['moderation_mode'] ?? 0) === 1;
$pending = Media::listPending(100);

admin_header('Модерация', 'moderate');
?>
<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Очередь модерации</h2>
    <?php if (!$moderationOn): ?>
        <p class="muted">Модерация выключена (режим G1). Новые фото публикуются сразу.</p>
        <p><a href="settings.php">Включить G2 в настройках ивента</a></p>
    <?php elseif ($pending === []): ?>
        <p class="muted">Очередь пуста — ожидаем загрузки от гостей.</p>
    <?php else: ?>
        <p class="muted"><?= count($pending) ?> фото ожидают решения</p>
        <div class="moderate-grid">
            <?php foreach ($pending as $row): ?>
                <?php $photo = Media::toPublic($row); ?>
                <div class="moderate-item card">
                    <a href="<?= e($photo['large_url']) ?>" target="_blank" rel="noopener">
                        <img src="<?= e($photo['thumb_url']) ?>" alt="Фото #<?= (int) $row['id'] ?>" loading="lazy">
                    </a>
                    <p class="muted" style="font-size:0.85rem;margin:0.5rem 0;">
                        #<?= (int) $row['id'] ?>
                        <?php if (!empty($row['guest_label'])): ?>
                            <br>от <?= e($row['guest_label']) ?>
                        <?php endif; ?>
                        <?php if (!empty($row['original_name'])): ?>
                            · <?= e($row['original_name']) ?>
                        <?php endif; ?>
                    </p>
                    <div class="moderate-actions">
                        <form method="post">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn">Одобрить</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Отклонить и удалить файл?');">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger">Отклонить</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php admin_footer();