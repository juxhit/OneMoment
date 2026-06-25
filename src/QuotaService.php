<?php

declare(strict_types=1);

final class QuotaService
{
    public static function check(int $incomingBytes, ?int $eventId = null): array
    {
        $maxBytes = MAX_FILE_MB * 1024 * 1024;
        if ($incomingBytes > $maxBytes) {
            return self::fail('file_too_large', 'Файл слишком большой (макс. ' . MAX_FILE_MB . ' MB)');
        }

        if (Media::countAll($eventId) >= MAX_TOTAL_FILES) {
            return self::fail('max_files', 'Достигнут лимит количества фото');
        }

        $root = PROJECT_ROOT;
        $free = @disk_free_space($root);
        $total = @disk_total_space($root);

        if ($free !== false && $total !== false && $total > 0) {
            $usedPercent = (int) round((1 - ($free / $total)) * 100);
            if ($usedPercent >= MAX_DISK_PERCENT) {
                return self::fail('disk_full', 'Мало места на диске организатора');
            }
            if ($free < $incomingBytes + 5 * 1024 * 1024) {
                return self::fail('disk_full', 'Недостаточно свободного места на диске');
            }
        }

        $event = $eventId ? Event::getById($eventId) : Event::get();
        if ($event && !empty($event['quota_bytes'])) {
            $quota = (int) $event['quota_bytes'];
            if (Media::totalBytes($eventId) + $incomingBytes > $quota) {
                return self::fail('quota_exceeded', 'Превышена квота альбома');
            }
        }

        return ['ok' => true];
    }

    public static function health(?int $eventId = null): array
    {
        $root = PROJECT_ROOT;
        $free = @disk_free_space($root);
        $total = @disk_total_space($root);
        $usedPercent = null;
        $status = 'green';

        if ($free !== false && $total !== false && $total > 0) {
            $usedPercent = (int) round((1 - ($free / $total)) * 100);
            if ($usedPercent >= MAX_DISK_PERCENT) {
                $status = 'red';
            } elseif ($usedPercent >= MAX_DISK_PERCENT - 10) {
                $status = 'yellow';
            }
        }

        $eventId = $eventId ?? Event::getManagedId();
        $event = Event::getById($eventId);
        $mediaBytes = Media::totalBytes($eventId);
        $quotaBytes = $event['quota_bytes'] ?? null;

        return [
            'disk_free_bytes' => $free,
            'disk_total_bytes' => $total,
            'disk_used_percent' => $usedPercent,
            'disk_status' => $status,
            'media_count' => Media::countAll($eventId),
            'media_bytes' => $mediaBytes,
            'pending_count' => Media::countByStatus('pending', $eventId),
            'approved_count' => Media::countByStatus('approved', $eventId),
            'max_files' => MAX_TOTAL_FILES,
            'max_file_mb' => MAX_FILE_MB,
            'quota_bytes' => $quotaBytes,
        ];
    }

    private static function fail(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message];
    }
}