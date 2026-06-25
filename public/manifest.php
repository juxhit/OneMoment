<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$event = Event::getByToken($token);

if ($event === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'not_found']);
    exit;
}

header('Content-Type: application/manifest+json; charset=utf-8');

$manifest = [
    'name' => $event['title'] . ' — ' . APP_NAME,
    'short_name' => APP_NAME,
    'description' => 'Загрузка фото на мероприятие',
    'start_url' => url('e/' . $token),
    'scope' => url(''),
    'display' => 'standalone',
    'background_color' => $event['wall_bg'] ?? '#0f0f0f',
    'theme_color' => $event['accent_color'] ?? '#6366f1',
    'orientation' => 'portrait-primary',
    'icons' => [
        [
            'src' => url('assets/img/icons/icon-192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => url('assets/img/icons/icon-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);