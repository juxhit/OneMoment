<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (request_method() !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$token = (string) ($_GET['token'] ?? '');
$event = Event::getByToken($token);
if ($event === null) {
    http_response_code(404);
    exit('Not found');
}

@set_time_limit(0);
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    ob_end_flush();
}

$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));
$maxIterations = 90;
$sleepSeconds = 2;

for ($i = 0; $i < $maxIterations; $i++) {
    if (connection_aborted()) {
        break;
    }

    $rows = Media::listApproved($sinceId, 50, (int) $event['id']);
    foreach ($rows as $row) {
        $photo = Media::toPublic($row);
        echo 'event: photo' . "\n";
        echo 'data: ' . json_encode($photo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $sinceId = max($sinceId, (int) $row['id']);
    }

    echo "event: ping\n";
    echo "data: {}\n\n";

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    sleep($sleepSeconds);
}