<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$type = (string) ($_GET['type'] ?? '');
$file = basename((string) ($_GET['file'] ?? ''));

if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(404);
    exit('Not found');
}

if ($type === 'upload') {
    $path = UPLOADS_DIR . '/' . $file;
    $media = Media::findByFilename($file);
} elseif ($type === 'thumb') {
    $path = THUMBS_DIR . '/' . $file;
    $base = preg_replace('/_(400|1200)\.jpg$/', '', $file);
    $media = Media::findByFilename($base . '.jpg')
        ?? Media::findByFilename($base . '.png')
        ?? Media::findByFilename($base . '.webp');
} else {
    http_response_code(404);
    exit('Not found');
}

if ($media === null || $media['status'] === 'rejected') {
    http_response_code(404);
    exit('Not found');
}

if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;