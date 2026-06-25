<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require __DIR__ . '/_layout.php';

AdminAuth::requireLogin();

$event = Event::get();
$error = '';

if (request_method() === 'POST') {
    try {
        $deleteOriginals = !empty($_POST['delete_originals']);
        $result = ZipExporter::createApprovedArchive((int) $event['id'], $deleteOriginals);
        $path = $result['path'];
        $filename = $result['filename'];

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store');
        header('X-OneMoment-Deleted-Originals: ' . (string) ($result['deleted_originals'] ?? 0));
        readfile($path);
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$approvedCount = Media::countByStatus('approved', (int) $event['id']);

admin_header('Экспорт', 'export');
?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <h2>ZIP: <?= e($event['title']) ?></h2>
    <p class="muted">Скачивается архив одобренных фото этого ивента (<?= (int) $approvedCount ?> шт.).</p>
    <?php if ($approvedCount === 0): ?>
        <p>Нет фото для экспорта.</p>
    <?php else: ?>
        <form method="post">
            <div class="form-row form-check">
                <label>
                    <input type="checkbox" name="delete_originals" value="1">
                    Удалить оригиналы с диска после скачивания (превью в альбоме останутся)
                </label>
            </div>
            <button type="submit" class="btn">Скачать ZIP</button>
        </form>
    <?php endif; ?>
    <p class="muted" style="margin-top:1rem;">Другой ивент — в разделе <a href="events.php">Ивенты</a>.</p>
</div>
<?php admin_footer();