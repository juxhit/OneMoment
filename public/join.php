<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$code = (string) ($_GET['code'] ?? '');
if (!preg_match('/^\d{6}$/', $code)) {
    http_response_code(404);
    echo 'Invalid code';
    exit;
}

$event = Event::getByRoomCode($code);
if ($event === null) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><title>Код не найден</title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>"></head>
<body><div class="wrap"><h1>Код не найден</h1><p class="muted">Проверьте цифры и попробуйте снова.</p></div></body></html>
    <?php
    exit;
}

redirect('e/' . $event['token']);