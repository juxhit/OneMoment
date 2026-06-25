<?php

declare(strict_types=1);

final class ZipExporter
{
    public static function createApprovedArchive(?int $eventId = null, bool $deleteOriginals = false): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Расширение ZIP не включено в PHP');
        }

        self::cleanupOldExports();

        $eventId = $eventId ?? Event::getManagedId();
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM media WHERE event_id = :event_id AND status = 'approved' ORDER BY id ASC"
        );
        $stmt->execute([':event_id' => $eventId]);
        $rows = $stmt->fetchAll();

        if ($rows === []) {
            throw new RuntimeException('Нет одобренных фото для экспорта');
        }

        $zipName = 'onemoment_' . gmdate('Ymd_His') . '.zip';
        $zipPath = EXPORTS_DIR . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать ZIP');
        }

        $added = 0;
        $exportedIds = [];
        foreach ($rows as $row) {
            $path = UPLOADS_DIR . '/' . $row['filename'];
            if (!is_file($path)) {
                continue;
            }
            $name = self::zipEntryName($row, $added);
            $zip->addFile($path, $name);
            $exportedIds[] = (int) $row['id'];
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            throw new RuntimeException('Файлы на диске не найдены');
        }

        $deletedOriginals = 0;
        if ($deleteOriginals) {
            $deletedOriginals = Media::deleteOriginalsForApproved($exportedIds, $eventId);
        }

        return [
            'path' => $zipPath,
            'filename' => $zipName,
            'count' => $added,
            'deleted_originals' => $deletedOriginals,
        ];
    }

    public static function cleanupOldExports(int $maxAgeSeconds = 3600): void
    {
        if (!is_dir(EXPORTS_DIR)) {
            return;
        }
        $now = time();
        foreach (glob(EXPORTS_DIR . '/*.zip') ?: [] as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAgeSeconds) {
                @unlink($file);
            }
        }
    }

    private static function zipEntryName(array $row, int $index): string
    {
        $original = (string) ($row['original_name'] ?? '');
        $filename = (string) ($row['filename'] ?? 'photo.jpg');
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $original);
        if ($safe === '' || $safe === '_') {
            $safe = $filename;
        }

        $label = sanitize_guest_label((string) ($row['guest_label'] ?? ''));
        if ($label !== null) {
            $labelSafe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $label);
            if ($labelSafe !== '' && $labelSafe !== '_') {
                $safe = $labelSafe . '_' . $safe;
            }
        }

        return str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT) . '_' . $safe;
    }
}