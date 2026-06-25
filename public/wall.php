<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$event = Event::getByToken($token);

if ($event === null) {
    http_response_code(404);
    echo '404';
    exit;
}

$bg = $event['wall_bg'] ?? '#0f0f0f';
$accent = $event['accent_color'] ?? '#6366f1';
$wallMode = normalize_wall_mode((string) ($event['wall_mode'] ?? 'carousel'));
$wallLimit = $wallMode === 'global_mosaic' ? 200 : 120;

$photos = Media::listApprovedForWall($wallLimit, (int) $event['id']);
$sinceId = 0;
foreach ($photos as $photo) {
    $sinceId = max($sinceId, (int) $photo['id']);
}

$apiBase = url('api/');

$wallConfig = [
    'token' => $token,
    'apiBase' => $apiBase,
    'wallMode' => $wallMode,
    'initialPhotos' => $photos,
    'sinceId' => $sinceId,
    'title' => $event['title'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Wall - <?= e($event['title']) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <style>
        body.wall-body {
            background: <?= e($bg) ?>;
            margin: 0;
            overflow: hidden;
        }
        :root { --accent: <?= e($accent) ?>; --wall-bg: <?= e($bg) ?>; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</head>
<body class="wall-body" x-data="wallApp(<?= htmlspecialchars(json_encode($wallConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>)" x-init="init()">

    <header class="wall-header">
        <h1 class="wall-title" x-text="title"></h1>
    </header>

    <main class="wall-main" :class="'wall-mode-' + wallMode">
        <template x-if="photos.length === 0">
            <div class="wall-empty wall-empty-center">
                <p>Ждём первое фото</p>
                <p class="muted">Гости загружают через ссылку альбома</p>
            </div>
        </template>

        <template x-if="photos.length > 0 && wallMode === 'carousel'">
            <div class="wall-carousel">
                <div class="wall-slide">
                    <img :src="currentPhoto?.large_url || currentPhoto?.full_url"
                         :key="currentPhoto?.id"
                         alt="Фото"
                         class="wall-slide-img"
                         @load="onImageLoad()">
                    <p class="wall-caption" x-show="currentPhoto?.guest_label" x-text="currentPhoto?.guest_label"></p>
                </div>
            </div>
        </template>

        <template x-if="photos.length > 0 && wallMode === 'masonry'">
            <div class="wall-masonry">
                <template x-for="photo in photos" :key="photo.id">
                    <div class="wall-masonry-item wall-fade-in">
                        <img :src="photo.large_url || photo.thumb_url" :alt="'Фото ' + photo.id" loading="lazy">
                        <p class="wall-grid-caption" x-show="photo.guest_label" x-text="photo.guest_label"></p>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="photos.length > 0 && wallMode === 'dynamic_mosaic'">
            <div class="wall-dynamic">
                <div class="wall-dynamic-hero wall-fade-in" :key="accentPhoto?.id">
                    <img :src="accentPhoto?.large_url || accentPhoto?.full_url" alt="Акцент" loading="lazy">
                    <p class="wall-grid-caption" x-show="accentPhoto?.guest_label" x-text="accentPhoto?.guest_label"></p>
                </div>
                <div class="wall-dynamic-grid">
                    <template x-for="photo in gridPhotos" :key="photo.id">
                        <div class="wall-dynamic-cell wall-fade-in">
                            <img :src="photo.thumb_url || photo.large_url" :alt="'Фото ' + photo.id" loading="lazy">
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="photos.length > 0 && wallMode === 'polaroid'">
            <div class="wall-polaroid" x-ref="polaroidRoot">
                <template x-for="photo in photos" :key="photo.id">
                    <div class="wall-polaroid-item wall-fade-in" :style="polaroidStyle(photo)">
                        <img :src="photo.large_url || photo.thumb_url" :alt="'Фото ' + photo.id" loading="lazy">
                        <p class="wall-polaroid-label" x-show="photo.guest_label" x-text="photo.guest_label"></p>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="photos.length > 0 && wallMode === 'generations'">
            <div class="wall-generations">
                <template x-for="photo in photos" :key="photo.id">
                    <div class="wall-generations-item wall-fade-in" :class="generationClass(photo)">
                        <img :src="photo.thumb_url || photo.large_url" :alt="'Фото ' + photo.id" loading="lazy">
                    </div>
                </template>
            </div>
        </template>

        <template x-if="photos.length > 0 && wallMode === 'global_mosaic'">
            <div class="wall-global-mosaic">
                <template x-for="photo in photos" :key="photo.id">
                    <div class="wall-global-cell wall-fade-in">
                        <img :src="photo.thumb_url || photo.large_url" :alt="'Фото ' + photo.id" loading="lazy">
                    </div>
                </template>
            </div>
        </template>

        <template x-if="photos.length > 0 && wallMode === 'honeycomb'">
            <div class="wall-honeycomb">
                <template x-for="photo in photos" :key="photo.id">
                    <div class="wall-honeycomb-item wall-fade-in">
                        <img :src="photo.thumb_url || photo.large_url" :alt="'Фото ' + photo.id" loading="lazy">
                    </div>
                </template>
            </div>
        </template>
    </main>

    <div class="wall-status" x-show="connectionLabel" x-text="connectionLabel"></div>
</body>
</html>