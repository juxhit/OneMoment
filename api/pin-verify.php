<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (request_method() !== 'POST') {
    json_error('method_not_allowed', 405);
}

$body = $_POST;
if ($body === []) {
    $body = read_json_body();
}

$token = (string) ($body['token'] ?? '');
$pin = (string) ($body['pin'] ?? '');

$event = Event::getByToken($token);
if ($event === null) {
    json_error('invalid_token', 404);
}

if (!GuestAccess::pinEnabled($event)) {
    json_response(['ok' => true, 'pin_required' => false]);
}

if ($pin === '' || !password_verify($pin, (string) $event['pin_hash'])) {
    json_error('invalid_pin', 403);
}

GuestAccess::issueCookie($event['token']);
json_response(['ok' => true, 'pin_required' => true]);