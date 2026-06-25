<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (request_method() !== 'POST') {
    json_error('method_not_allowed', 405);
}

$token = (string) ($_POST['token'] ?? '');
$event = Event::getByToken($token);
if ($event === null) {
    json_error('invalid_token', 404);
}

GuestAccess::requireAccess($event);

if (empty($_FILES['file'])) {
    json_error('file_required', 400);
}

$guestToken = GuestIdentity::requireToken($event);

$guestLabel = sanitize_guest_label((string) ($_POST['guest_label'] ?? ''));

try {
    $processed = ImageProcessor::processUploadedFile($_FILES['file'], (int) $event['id']);
    $status = Media::uploadStatusForEvent($event);
    $media = Media::create([
        'event_id' => (int) $event['id'],
        'filename' => $processed['filename'],
        'original_name' => $processed['original_name'],
        'mime_type' => $processed['mime_type'],
        'size_bytes' => $processed['size_bytes'],
        'width' => $processed['width'],
        'height' => $processed['height'],
        'status' => $status,
        'guest_label' => $guestLabel,
        'guest_token' => $guestToken,
    ]);

    $public = Media::toPublic($media);
    json_response([
        'id' => $public['id'],
        'thumb_url' => $public['thumb_url'],
        'full_url' => $public['full_url'],
        'guest_label' => $public['guest_label'],
        'status' => $public['status'],
        'message' => $status === 'pending'
            ? 'Фото отправлено на модерацию'
            : 'Фото добавлено в альбом',
    ]);
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 507);
}