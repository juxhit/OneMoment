<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$event = Event::getByToken($token);

if ($event === null) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><title>Не найдено</title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>"></head>
<body><div class="wrap"><h1>404</h1><p class="muted">Ссылка недействительна.</p></div></body></html>
    <?php
    exit;
}

$accent = $event['accent_color'] ?? '#6366f1';
$pinRequired = GuestAccess::pinEnabled($event);
$hasAccess = GuestAccess::hasAccess($event);
$apiBase = url('api/');
$isSecure = is_https();
GuestIdentity::ensure($event);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php Pwa::renderHead($token, $event); ?>
    <title><?= e($event['title']) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <style>:root { --accent: <?= e($accent) ?>; }</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>
    <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</head>
<body>
<?php
$albumConfig = [
    'token' => $token,
    'apiBase' => $apiBase,
    'pinRequired' => $pinRequired,
    'hasAccess' => $hasAccess,
    'isSecure' => $isSecure,
    'maxFileMb' => MAX_FILE_MB,
];
?>
<div class="wrap" x-data="albumApp(<?= htmlspecialchars(json_encode($albumConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>)" x-init="init()">

    <header class="album-header">
        <h1><?= e($event['title']) ?></h1>
        <p class="muted">Загрузите фото в общий альбом · можно «Добавить на экран»</p>
    </header>

    <template x-if="pinRequired && !hasAccess">
        <div class="card pin-card">
            <h2>PIN альбома</h2>
            <p class="muted">Введите код доступа от организатора</p>
            <form @submit.prevent="verifyPin()">
                <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8"
                       x-model="pin" placeholder="PIN" autocomplete="one-time-code" required>
                <button type="submit" class="btn">Войти</button>
            </form>
            <p class="alert alert-error" x-show="pinError" x-text="pinError"></p>
        </div>
    </template>

    <template x-if="hasAccess">
        <div>
            <div class="card upload-card">
                
                <div class="form-row guest-label-row">
                    <label for="guest_label">Ваше имя (необязательно)</label>
                    <input type="text" id="guest_label" x-model="guestLabel" maxlength="80"
                           placeholder="Анна" autocomplete="name">
                </div>
                <div class="upload-actions">
                    <button type="button" class="btn" @click="openGalleryPicker()" :disabled="uploading">
                        Выбрать из галереи
                    </button>
                    <button type="button" class="btn btn-secondary" @click="openCameraPicker()"
                            x-show="canUseCamera" :disabled="uploading">
                        Снять фото
                    </button>
                </div>
                    На iPhone без HTTPS доступна только загрузка из галереи.
                </p>
                <input type="file" accept="image/*" multiple class="sr-only" x-ref="galleryInput" @change="onFilesSelected($event)">
                <input type="file" accept="image/*" capture="environment" class="sr-only" x-ref="cameraInput" @change="onFilesSelected($event)">

                <div class="progress-wrap" x-show="uploading">
                    <div class="progress-bar"><div class="progress-fill" :style="`width: ${uploadProgress}%`"></div></div>
                    <p class="muted">Загрузка… <span x-text="uploadProgress + '%'"></span></p>
                </div>

                <p class="status" x-show="statusMessage"
                   :class="{ 'status-success': statusType === 'success', 'status-error': statusType === 'error' }"
                   x-text="statusMessage"></p>
            </div>

            <section class="gallery-section">
                <h2>Ваши фото <span class="muted" x-text="'(' + photos.length + ')'"></span></h2>
                <p class="muted" x-show="loadingGallery">Загрузка…</p>
                <p class="muted" x-show="!loadingGallery && photos.length === 0">Вы ещё ничего не загрузили — сфотографируйте или выберите из галереи.</p>
                <div class="gallery-grid">
                    <template x-for="photo in photos" :key="photo.id">
                        <a class="gallery-item" :href="photo.full_url" target="_blank" rel="noopener">
                            <img :src="photo.thumb_url" :alt="photo.guest_label || ('Фото ' + photo.id)" loading="lazy">
                        <span class="gallery-caption" x-show="photo.guest_label" x-text="photo.guest_label"></span>
                        </a>
                    </template>
                </div>
            </section>
        </div>
    </template>
</div>
</body>
</html>
