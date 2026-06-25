<?php

declare(strict_types=1);

final class Event
{
    public static function ensureExists(): void
    {
        $count = (int) Database::pdo()->query('SELECT COUNT(*) FROM event')->fetchColumn();
        if ($count > 0) {
            if (self::getActive() === null) {
                $pdo = Database::pdo();
                $first = $pdo->query('SELECT id FROM event ORDER BY id ASC LIMIT 1')->fetchColumn();
                if ($first) {
                    $stmt = $pdo->prepare('UPDATE event SET is_active = 1, updated_at = :u WHERE id = :id');
                    $stmt->execute([':id' => (int) $first, ':u' => now_iso()]);
                }
            }
            return;
        }

        $now = now_iso();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO event (
                token, room_code, title, moderation_mode, is_active, created_at, updated_at
            ) VALUES (:token, :room_code, :title, 0, 1, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':token' => random_token(24),
            ':room_code' => self::generateUniqueRoomCode(),
            ':title' => APP_NAME,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public static function getActive(): ?array
    {
        $stmt = Database::pdo()->query('SELECT * FROM event WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getManagedId(): int
    {
        self::ensureExists();
        AdminAuth::startSession();
        $sessionId = (int) ($_SESSION['admin_event_id'] ?? 0);
        if ($sessionId > 0 && self::getById($sessionId) !== null) {
            return $sessionId;
        }
        $active = self::getActive();
        return $active ? (int) $active['id'] : 1;
    }

    public static function setManagedId(int $eventId): bool
    {
        if (self::getById($eventId) === null) {
            return false;
        }
        AdminAuth::startSession();
        $_SESSION['admin_event_id'] = $eventId;
        return true;
    }

    public static function get(): ?array
    {
        return self::getById(self::getManagedId());
    }

    public static function getById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM event WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getByToken(string $token): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM event WHERE token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getByRoomCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM event WHERE room_code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listAll(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT e.*, (
                SELECT COUNT(*) FROM media m WHERE m.event_id = e.id AND m.status != \'rejected\'
            ) AS media_count
            FROM event e
            ORDER BY e.is_active DESC, e.id DESC'
        );
        return $stmt->fetchAll();
    }

    public static function update(array $fields, ?int $eventId = null): void
    {
        $eventId = $eventId ?? self::getManagedId();
        $allowed = [
            'title', 'event_date', 'accent_color', 'wall_bg', 'wall_mode',
            'moderation_mode', 'pin_hash', 'last_known_ip', 'quota_bytes',
            'token', 'room_code', 'is_active', 'archived_at',
        ];

        $sets = [];
        $params = [':updated_at' => now_iso(), ':id' => $eventId];

        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $sets[] = $key . ' = :' . $key;
            $params[':' . $key] = $value;
        }

        if ($sets === []) {
            return;
        }

        $sql = 'UPDATE event SET ' . implode(', ', $sets) . ', updated_at = :updated_at WHERE id = :id';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public static function isModerationEnabled(?int $eventId = null): bool
    {
        $event = $eventId ? self::getById($eventId) : self::get();
        return $event && (int) ($event['moderation_mode'] ?? 0) === 1;
    }

    public static function archiveAndCreateNew(): array
    {
        $pdo = Database::pdo();
        $now = now_iso();
        $source = self::getActive() ?? self::get();

        $pdo->exec("UPDATE event SET is_active = 0, archived_at = " . $pdo->quote($now) . ", updated_at = " . $pdo->quote($now) . " WHERE is_active = 1");

        $title = APP_NAME;
        if ($source && !empty($source['title'])) {
            $title = (string) $source['title'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO event (
                token, room_code, title, event_date, accent_color, wall_bg, wall_mode,
                moderation_mode, pin_hash, last_known_ip, quota_bytes, is_active,
                archived_at, created_at, updated_at
            ) VALUES (
                :token, :room_code, :title, :event_date, :accent_color, :wall_bg, :wall_mode,
                :moderation_mode, :pin_hash, :last_known_ip, :quota_bytes, 1,
                NULL, :created_at, :updated_at
            )'
        );
        $stmt->execute([
            ':token' => random_token(24),
            ':room_code' => self::generateUniqueRoomCode(),
            ':title' => $title,
            ':event_date' => $source['event_date'] ?? null,
            ':accent_color' => $source['accent_color'] ?? '#6366f1',
            ':wall_bg' => $source['wall_bg'] ?? '#0f0f0f',
            ':wall_mode' => $source['wall_mode'] ?? 'carousel',
            ':moderation_mode' => (int) ($source['moderation_mode'] ?? 0),
            ':pin_hash' => $source['pin_hash'] ?? null,
            ':last_known_ip' => $source['last_known_ip'] ?? null,
            ':quota_bytes' => $source['quota_bytes'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $newId = (int) $pdo->lastInsertId();
        self::setManagedId($newId);
        $row = self::getById($newId);
        return $row ?? [];
    }

  /** @deprecated use archiveAndCreateNew() */
    public static function resetForNewEvent(): void
    {
        self::archiveAndCreateNew();
    }


    /** @return true|string error code */
    public static function deleteArchived(int $id)
    {
        $row = self::getById($id);
        if ($row === null) {
            return 'not_found';
        }
        if ((int) ($row['is_active'] ?? 0) === 1) {
            return 'active';
        }

        $total = (int) Database::pdo()->query('SELECT COUNT(*) FROM event')->fetchColumn();
        if ($total <= 1) {
            return 'last';
        }

        Media::purgeEvent($id);

        $stmt = Database::pdo()->prepare('DELETE FROM event WHERE id = :id AND is_active = 0');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            return 'not_found';
        }

        AdminAuth::startSession();
        if ((int) ($_SESSION['admin_event_id'] ?? 0) === $id) {
            unset($_SESSION['admin_event_id']);
            $active = self::getActive();
            if ($active !== null) {
                self::setManagedId((int) $active['id']);
            } else {
                $fallback = Database::pdo()->query('SELECT id FROM event ORDER BY id DESC LIMIT 1')->fetchColumn();
                if ($fallback) {
                    self::setManagedId((int) $fallback);
                }
            }
        }

        return true;
    }
    public static function generateUniqueRoomCode(): string
    {
        for ($i = 0; $i < 50; $i++) {
            $code = random_room_code();
            $stmt = Database::pdo()->prepare('SELECT 1 FROM event WHERE room_code = :code');
            $stmt->execute([':code' => $code]);
            if (!$stmt->fetch()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate unique room code');
    }
}