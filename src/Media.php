<?php

declare(strict_types=1);

final class Media
{
    public static function countAll(?int $eventId = null): int
    {
        $eventId = $eventId ?? Event::getManagedId();
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM media WHERE event_id = :event_id');
        $stmt->execute([':event_id' => $eventId]);
        return (int) $stmt->fetchColumn();
    }

    public static function countByStatus(string $status, ?int $eventId = null): int
    {
        $eventId = $eventId ?? Event::getManagedId();
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM media WHERE event_id = :event_id AND status = :status'
        );
        $stmt->execute([':event_id' => $eventId, ':status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public static function totalBytes(?int $eventId = null): int
    {
        $eventId = $eventId ?? Event::getManagedId();
        $stmt = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(size_bytes), 0) FROM media WHERE event_id = :event_id AND status != 'rejected'"
        );
        $stmt->execute([':event_id' => $eventId]);
        return (int) $stmt->fetchColumn();
    }

    public static function create(array $data): array
    {
        if (empty($data['event_id'])) {
            throw new InvalidArgumentException('event_id required');
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO media (
                event_id, filename, original_name, mime_type, size_bytes, width, height, status, guest_label, guest_token, created_at
            ) VALUES (
                :event_id, :filename, :original_name, :mime_type, :size_bytes, :width, :height, :status, :guest_label, :guest_token, :created_at
            )'
        );
        $createdAt = now_iso();
        $stmt->execute([
            ':event_id' => (int) $data['event_id'],
            ':filename' => $data['filename'],
            ':original_name' => $data['original_name'] ?? null,
            ':mime_type' => $data['mime_type'],
            ':size_bytes' => $data['size_bytes'],
            ':width' => $data['width'] ?? null,
            ':height' => $data['height'] ?? null,
            ':status' => $data['status'],
            ':guest_label' => $data['guest_label'] ?? null,
            ':guest_token' => $data['guest_token'] ?? null,
            ':created_at' => $createdAt,
        ]);

        $id = (int) Database::pdo()->lastInsertId();
        $row = self::findById($id);
        return $row ?? [];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM media WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByFilename(string $filename): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM media WHERE filename = :filename LIMIT 1');
        $stmt->execute([':filename' => $filename]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listPending(int $limit = 100, ?int $eventId = null): array
    {
        $eventId = $eventId ?? Event::getManagedId();
        $limit = max(1, min(200, $limit));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM media WHERE event_id = :event_id AND status = 'pending' ORDER BY created_at ASC LIMIT :limit"
        );
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function listApproved(int $sinceId, int $limit, int $eventId, ?string $guestToken = null): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "SELECT * FROM media
             WHERE event_id = :event_id AND id > :since_id AND status = 'approved'";
        if ($guestToken !== null && $guestToken !== '') {
            $sql .= " AND guest_token = :guest_token";
        }
        $sql .= " ORDER BY id ASC LIMIT :limit";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':since_id', $sinceId, PDO::PARAM_INT);
        if ($guestToken !== null && $guestToken !== '') {
            $stmt->bindValue(':guest_token', $guestToken, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function listApprovedForWall(int $limit, int $eventId): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM media
             WHERE event_id = :event_id AND status = 'approved'
             ORDER BY id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
        return array_map([self::class, 'toPublic'], $rows);
    }

    public static function listGallery(int $sinceId, int $limit, int $eventId, ?string $guestToken = null): array
    {
        $rows = self::listApproved($sinceId, $limit, $eventId, $guestToken);
        return array_map([self::class, 'toPublic'], $rows);
    }

    public static function approve(int $id, ?int $eventId = null): bool
    {
        $row = self::findById($id);
        $eventId = $eventId ?? Event::getManagedId();
        if ($row === null || (int) $row['event_id'] !== $eventId || $row['status'] !== 'pending') {
            return false;
        }

        $stmt = Database::pdo()->prepare("UPDATE media SET status = 'approved' WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return true;
    }

    public static function reject(int $id, ?int $eventId = null): bool
    {
        $row = self::findById($id);
        $eventId = $eventId ?? Event::getManagedId();
        if ($row === null || (int) $row['event_id'] !== $eventId || $row['status'] !== 'pending') {
            return false;
        }

        self::deleteFiles($row);

        $stmt = Database::pdo()->prepare(
            "UPDATE media SET status = 'rejected', size_bytes = 0 WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        return true;
    }

    public static function deleteOriginalFile(array $row): bool
    {
        $filename = (string) ($row['filename'] ?? '');
        if ($filename === '') {
            return false;
        }
        $uploadPath = UPLOADS_DIR . '/' . $filename;
        if (!is_file($uploadPath)) {
            return false;
        }
        return @unlink($uploadPath);
    }

    public static function deleteOriginalsForApproved(array $ids, ?int $eventId = null): int
    {
        $eventId = $eventId ?? Event::getManagedId();
        $deleted = 0;
        foreach ($ids as $id) {
            $row = self::findById((int) $id);
            if ($row === null || (int) $row['event_id'] !== $eventId || $row['status'] !== 'approved') {
                continue;
            }
            if (self::deleteOriginalFile($row)) {
                $deleted++;
            }
        }
        return $deleted;
    }


    public static function purgeEvent(int $eventId): int
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM media WHERE event_id = :event_id');
        $stmt->execute([':event_id' => $eventId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            self::deleteFiles($row);
        }
        $del = Database::pdo()->prepare('DELETE FROM media WHERE event_id = :event_id');
        $del->execute([':event_id' => $eventId]);
        return count($rows);
    }
    public static function deleteFiles(array $row): void
    {
        $filename = $row['filename'] ?? '';
        if ($filename !== '') {
            $uploadPath = UPLOADS_DIR . '/' . $filename;
            if (is_file($uploadPath)) {
                @unlink($uploadPath);
            }
        }

        $basename = ImageProcessor::basenameFromFilename($filename);
        foreach (THUMB_WIDTHS as $width) {
            $thumbPath = THUMBS_DIR . '/' . ImageProcessor::thumbFilename($basename, $width);
            if (is_file($thumbPath)) {
                @unlink($thumbPath);
            }
        }
    }

    public static function hasOriginalFile(array $row): bool
    {
        $filename = (string) ($row['filename'] ?? '');
        return $filename !== '' && is_file(UPLOADS_DIR . '/' . $filename);
    }

    public static function toPublic(array $row): array
    {
        $basename = ImageProcessor::basenameFromFilename($row['filename']);
        $largeUrl = media_file_url(ImageProcessor::thumbFilename($basename, 1200), 'thumb', 1200);
        $fullUrl = self::hasOriginalFile($row)
            ? media_file_url($row['filename'], 'upload')
            : $largeUrl;

        return [
            'id' => (int) $row['id'],
            'thumb_url' => media_file_url(ImageProcessor::thumbFilename($basename, 400), 'thumb', 400),
            'full_url' => $fullUrl,
            'large_url' => $largeUrl,
            'status' => $row['status'],
            'guest_label' => $row['guest_label'] ?? null,
            'created_at' => $row['created_at'],
        ];
    }

    public static function uploadStatusForEvent(array $event): string
    {
        return (int) ($event['moderation_mode'] ?? 0) === 1 ? 'pending' : 'approved';
    }
}