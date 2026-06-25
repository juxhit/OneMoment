<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require __DIR__ . '/_layout.php';

AdminAuth::requireLogin();

$message = '';
$error = '';

if (request_method() === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'manage' && $id > 0) {
        if (Event::setManagedId($id)) {
            redirect('admin/');
        }
        $error = 'Ивент не найден.';
    } elseif ($action === 'delete' && $id > 0) {
        if (empty($_POST['confirm_delete'])) {
            $error = 'Подтвердите удаление архивного ивента.';
        } else {
            $result = Event::deleteArchived($id);
            if ($result === true) {
                $message = 'Архивный ивент и все его фото удалены.';
            } elseif ($result === 'active') {
                $error = 'Нельзя удалить активный ивент.';
            } elseif ($result === 'last') {
                $error = 'Нельзя удалить последний оставшийся ивент.';
            } elseif ($result === 'not_found') {
                $error = 'Ивент не найден.';
            } else {
                $error = 'Не удалось удалить ивент.';
            }
        }
    } elseif ($action === 'new') {
        if (empty($_POST['confirm_new'])) {
            $error = 'Подтвердите создание нового ивента.';
        } else {
            Event::archiveAndCreateNew();
            $message = 'Новый активный ивент создан. Старый архивирован — фото сохранены.';
            redirect('admin/setup.php');
        }
    } else {
        $error = 'Некорректное действие.';
    }
}

$events = Event::listAll();
$managedId = Event::getManagedId();

admin_header('Ивенты', 'events');
?>
<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Все ивенты</h2>
    <p class="muted">Активный ивент — тот, для которого вы печатаете QR. Старые ивенты остаются доступны по своим ссылкам.</p>
    <?php if ($events === []): ?>
        <p>Нет ивентов.</p>
    <?php else: ?>
        <div class="table-scroll"><table class="data-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Фото</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $row): ?>
                    <tr class="<?= (int) $row['id'] === $managedId ? 'row-managed' : '' ?>">
                        <td>
                            <strong><?= e($row['title']) ?></strong>
                            <?php if (!empty($row['event_date'])): ?>
                                <br><span class="muted"><?= e($row['event_date']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) ($row['media_count'] ?? 0) ?></td>
                        <td>
                            <?php if ((int) ($row['is_active'] ?? 0) === 1): ?>
                                <span class="badge badge-active">активный</span>
                            <?php else: ?>
                                <span class="muted">архив</span>
                            <?php endif; ?>
                            <?php if ((int) $row['id'] === $managedId): ?>
                                <span class="badge">в админке</span>
                            <?php endif; ?>
                        </td>
                        <td class="table-actions">
                            <?php if ((int) $row['id'] !== $managedId): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="action" value="manage">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-secondary">Управлять</button>
                                </form>
                            <?php endif; ?>
                                                        <?php if ((int) ($row['is_active'] ?? 0) === 0): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Удалить архивный ивент «<?= e($row['title']) ?>» и все <?= (int) ($row['media_count'] ?? 0) ?> фото? Это необратимо.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="confirm_delete" value="1">
                                    <button type="submit" class="btn btn-danger">Удалить</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-secondary" href="<?= e(url('e/' . $row['token'])) ?>" target="_blank" rel="noopener">Альбом</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<div class="card card-danger-zone">
    <h2>Новый ивент</h2>
    <p class="muted">Текущий активный ивент уйдёт в архив (фото не удаляются). Будут новые ссылка и код комнаты.</p>
    <form method="post" onsubmit="return confirm('Создать новый ивент? Старый останется в архиве.');">
        <input type="hidden" name="action" value="new">
        <div class="form-row form-check">
            <label>
                <input type="checkbox" name="confirm_new" value="1" required>
                Понимаю: QR и код комнаты изменятся
            </label>
        </div>
        <button type="submit" class="btn btn-danger">Создать новый ивент</button>
    </form>
</div>
<?php admin_footer();