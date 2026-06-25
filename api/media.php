<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (request_method() !== 'GET') {
    json_error('method_not_allowed', 405);
}

$token = (string) ($_GET['token'] ?? '');
$event = Event::getByToken($token);
if ($event === null) {
    json_error('invalid_token', 404);
}

$isWall = isset($_GET['wall']) && (string) $_GET['wall'] === '1';
if (!$isWall) {
    GuestAccess::requireAccess($event);
}

$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

$guestToken = null;
if (!$isWall) {
    $guestToken = GuestIdentity::requireToken($event);
}

$items = Media::listGallery($sinceId, $limit, (int) $event['id'], $guestToken);

json_response([
    'items' => $items,
    'since_id' => $sinceId,
    'count' => count($items),
]);
