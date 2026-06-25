<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

$data = (string) ($_GET['data'] ?? '');
if ($data === '' || strlen($data) > 2048) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid data';
    exit;
}

if (!extension_loaded('gd')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'GD required';
    exit;
}

$size = isset($_GET['s']) ? (int) $_GET['s'] : 6;
$size = max(3, min(12, $size));
$margin = isset($_GET['m']) ? (int) $_GET['m'] : 2;
$margin = max(1, min(4, $margin));

require_once PROJECT_ROOT . '/vendor/phpqrcode/qrlib.php';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');

QRcode::png($data, false, QR_ECLEVEL_M, $size, $margin);
