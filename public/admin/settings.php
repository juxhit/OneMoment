<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require __DIR__ . '/_layout.php';

AdminAuth::requireLogin();

$event = Event::get();
$message = '';
$error = '';

function normalize_hex_color(string $value, string $fallback): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
        return strtolower($value);
    }
    return $fallback;
}

if (request_method() === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'new_event') {
        if (empty($_POST['confirm_reset'])) {
            $error = 'Подтвердите сброс ивента.';
        } else {
            Event::archiveAndCreateNew();
            $event = Event::get();
            $message = 'Ивент архивирован, создан новый активный. Фото сохранены.';
        }
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if ($new !== $confirm) {
            $error = 'Новый пароль и подтверждение не совпадают.';
        } else {
            $result = AdminAuth::changePassword($current, $new);
            if ($result === true) {
                $message = 'Пароль изменён. При следующем входе используйте новый пароль.';
            } elseif ($result === 'wrong_current') {
                $error = 'Текущий пароль неверен.';
            } elseif ($result === 'too_short') {
                $error = 'Новый пароль: минимум 8 символов.';
            } else {
                $error = 'Не удалось сменить пароль.';
            }
        }
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $error = 'Укажите название ивента.';
        } else {
            $eventDate = trim((string) ($_POST['event_date'] ?? ''));
            $eventDate = $eventDate !== '' ? $eventDate : null;

            $wallMode = normalize_wall_mode((string) ($_POST['wall_mode'] ?? 'carousel'));

            $fields = [
                'title' => $title,
                'event_date' => $eventDate,
                'accent_color' => normalize_hex_color((string) ($_POST['accent_color'] ?? ''), '#6366f1'),
                'wall_bg' => normalize_hex_color((string) ($_POST['wall_bg'] ?? ''), '#0f0f0f'),
                'wall_mode' => $wallMode,
                'moderation_mode' => isset($_POST['moderation_mode']) ? 1 : 0,
            ];

            if (!empty($_POST['pin_disable'])) {
                $fields['pin_hash'] = null;
            } elseif (!empty($_POST['pin_code'])) {
                $pin = (string) $_POST['pin_code'];
                if (strlen($pin) < 4 || strlen($pin) > 8 || !ctype_digit($pin)) {
                    $error = 'PIN: 4-8 цифр.';
                } else {
                    $fields['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
                }
            }

            if ($error === '') {
                Event::update($fields);
                $event = Event::get();
                $message = 'Настройки сохранены.';
            }
        }
    }
}

$pinEnabled = !empty($event['pin_hash']);

admin_header('Ивент', 'settings');
?>
<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="card">
    <input type="hidden" name="action" value="change_password">
    <h2>Пароль администратора</h2>
    <p class="muted">Минимум 8 символов. Сохраняется в <code>config/config.local.php</code>.</p>
    <div class="form-row">
        <label for="current_password">Текущий пароль</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
    </div>
    <div class="form-row">
        <label for="new_password">Новый пароль</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
    </div>
    <div class="form-row">
        <label for="confirm_password">Подтверждение</label>
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
    </div>
    <button type="submit" class="btn">Сменить пароль</button>
</form>

<form method="post" class="card">
    <input type="hidden" name="action" value="save">
    <h2>Основное</h2>
    <div class="form-row">
        <label for="title">Название ивента</label>
        <input type="text" id="title" name="title" value="<?= e($event['title']) ?>" required>
    </div>
    <div class="form-row">
        <label for="event_date">Дата (необязательно)</label>
        <input type="date" id="event_date" name="event_date" value="<?= e($event['event_date'] ?? '') ?>">
    </div>

    <h2>Оформление стены</h2>
    <div class="form-grid">
        <div class="form-row">
            <label for="accent_color">Цвет акцента</label>
            <input type="color" id="accent_color" name="accent_color" value="<?= e($event['accent_color']) ?>">
        </div>
        <div class="form-row">
            <label for="wall_bg">Фон стены</label>
            <input type="color" id="wall_bg" name="wall_bg" value="<?= e($event['wall_bg']) ?>">
        </div>
    </div>
    <div class="form-row">
        <label for="wall_mode">Режим Live Wall</label>
        <select id="wall_mode" name="wall_mode">
            <?php $currentWallMode = normalize_wall_mode((string) ($event['wall_mode'] ?? 'carousel')); ?>
            <?php foreach (wall_mode_options() as $modeValue => $modeLabel): ?>
                <option value="<?= e($modeValue) ?>" <?= $currentWallMode === $modeValue ? 'selected' : '' ?>><?= e($modeLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <h2>Модерация</h2>
    <div class="form-row form-check">
        <label>
            <input type="checkbox" name="moderation_mode" value="1" <?= (int) ($event['moderation_mode'] ?? 0) === 1 ? 'checked' : '' ?>>
            G2 — на стене только после одобрения
        </label>
        <p class="muted">Если выключено (G1), загрузки сразу появляются в альбоме и на стене.</p>
    </div>

    <h2>PIN для гостей</h2>
    <p class="muted">Сейчас: <?= $pinEnabled ? 'включён' : 'выключен' ?></p>
    <div class="form-row">
        <label for="pin_code">Новый PIN (4-8 цифр)</label>
        <input type="password" id="pin_code" name="pin_code" inputmode="numeric" pattern="[0-9]{4,8}" autocomplete="new-password" placeholder="Оставьте пустым, чтобы не менять">
    </div>
    <?php if ($pinEnabled): ?>
    <div class="form-row form-check">
        <label>
            <input type="checkbox" name="pin_disable" value="1">
            Отключить PIN
        </label>
    </div>
    <?php endif; ?>

    <button type="submit" class="btn">Сохранить</button>
</form>

<div class="card card-danger-zone">
    <h2>Новый ивент</h2>
    <p class="muted">Архивирует текущий ивент и создаст новый активный. Фото не удаляются.</p>
    <form method="post" onsubmit="return confirm('Создать новый ивент (текущий в архив)?');">
        <input type="hidden" name="action" value="new_event">
        <div class="form-row form-check">
            <label>
                <input type="checkbox" name="confirm_reset" value="1" required>
                Я понимаю, что старая ссылка перестанет работать
            </label>
        </div>
        <button type="submit" class="btn btn-danger">Начать новый ивент</button>
        <p class="muted" style="margin-top:0.75rem"><a href="events.php">Все ивенты и архив</a></p>
    </form>
</div>
<?php admin_footer();