<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        self::$pdo = $pdo;
        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        return self::connect();
    }

    public static function migrate(): void
    {
        $pdo = self::pdo();
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

        if ($version < 1) {
            self::applySchemaV1($pdo);
            $pdo->exec('PRAGMA user_version = 1');
            $version = 1;
        }

        if ($version < 2) {
            self::applySchemaV2($pdo);
            $pdo->exec('PRAGMA user_version = 2');
            $version = 2;
        }

        if ($version < 3) {
            self::applySchemaV3($pdo);
            $pdo->exec('PRAGMA user_version = 3');
        }
    }

    private static function applySchemaV1(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE event (
    id                  INTEGER PRIMARY KEY CHECK (id = 1),
    token               TEXT NOT NULL UNIQUE,
    room_code           TEXT UNIQUE,
    title               TEXT NOT NULL DEFAULT 'OneMoment',
    event_date          TEXT,
    accent_color        TEXT NOT NULL DEFAULT '#6366f1',
    wall_bg             TEXT NOT NULL DEFAULT '#0f0f0f',
    wall_mode           TEXT NOT NULL DEFAULT 'carousel',
    pin_hash            TEXT,
    moderation_mode     INTEGER NOT NULL DEFAULT 0,
    last_known_ip       TEXT,
    quota_bytes         INTEGER,
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL
);

CREATE TABLE media (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    filename            TEXT NOT NULL UNIQUE,
    original_name       TEXT,
    mime_type           TEXT NOT NULL,
    size_bytes          INTEGER NOT NULL DEFAULT 0,
    width               INTEGER,
    height              INTEGER,
    status              TEXT NOT NULL DEFAULT 'approved',
    guest_label         TEXT,
    created_at          TEXT NOT NULL
);

CREATE TABLE admin_session (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    session_token       TEXT NOT NULL UNIQUE,
    expires_at          TEXT NOT NULL,
    created_at          TEXT NOT NULL
);

CREATE INDEX idx_media_status_created ON media(status, created_at DESC);
CREATE INDEX idx_media_created ON media(created_at DESC);
SQL);
    }

    private static function applySchemaV2(PDO $pdo): void
    {
        foreach ($pdo->query('PRAGMA table_info(media)')->fetchAll() as $col) {
            if (($col['name'] ?? '') === 'event_id') {
                return;
            }
        }

        $pdo->exec('BEGIN');
        try {
            $pdo->exec(<<<'SQL'
CREATE TABLE event_new (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    token               TEXT NOT NULL UNIQUE,
    room_code           TEXT UNIQUE,
    title               TEXT NOT NULL DEFAULT 'OneMoment',
    event_date          TEXT,
    accent_color        TEXT NOT NULL DEFAULT '#6366f1',
    wall_bg             TEXT NOT NULL DEFAULT '#0f0f0f',
    wall_mode           TEXT NOT NULL DEFAULT 'carousel',
    pin_hash            TEXT,
    moderation_mode     INTEGER NOT NULL DEFAULT 0,
    last_known_ip       TEXT,
    quota_bytes         INTEGER,
    is_active           INTEGER NOT NULL DEFAULT 0,
    archived_at         TEXT,
    created_at          TEXT NOT NULL,
    updated_at          TEXT NOT NULL
);
SQL);

            $pdo->exec(<<<'SQL'
INSERT INTO event_new (
    id, token, room_code, title, event_date, accent_color, wall_bg, wall_mode,
    pin_hash, moderation_mode, last_known_ip, quota_bytes, is_active, archived_at,
    created_at, updated_at
)
SELECT
    id, token, room_code, title, event_date, accent_color, wall_bg, wall_mode,
    pin_hash, moderation_mode, last_known_ip, quota_bytes, 1, NULL,
    created_at, updated_at
FROM event;
SQL);

            $pdo->exec(<<<'SQL'
CREATE TABLE media_new (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id            INTEGER NOT NULL REFERENCES event_new(id) ON DELETE CASCADE,
    filename            TEXT NOT NULL UNIQUE,
    original_name       TEXT,
    mime_type           TEXT NOT NULL,
    size_bytes          INTEGER NOT NULL DEFAULT 0,
    width               INTEGER,
    height              INTEGER,
    status              TEXT NOT NULL DEFAULT 'approved',
    guest_label         TEXT,
    created_at          TEXT NOT NULL
);
SQL);

            $pdo->exec(<<<'SQL'
INSERT INTO media_new (
    event_id, id, filename, original_name, mime_type, size_bytes, width, height,
    status, guest_label, created_at
)
SELECT 1, id, filename, original_name, mime_type, size_bytes, width, height,
    status, guest_label, created_at
FROM media;
SQL);

            $pdo->exec('DROP TABLE media');
            $pdo->exec('DROP TABLE event');
            $pdo->exec('ALTER TABLE event_new RENAME TO event');
            $pdo->exec('ALTER TABLE media_new RENAME TO media');

            $pdo->exec('CREATE INDEX idx_media_event_status ON media(event_id, status, created_at DESC)');
            $pdo->exec('CREATE INDEX idx_media_event_created ON media(event_id, created_at DESC)');
            $pdo->exec('CREATE INDEX idx_media_status_created ON media(status, created_at DESC)');
            $pdo->exec('CREATE INDEX idx_media_created ON media(created_at DESC)');

            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    private static function applySchemaV3(PDO $pdo): void
    {
        foreach ($pdo->query('PRAGMA table_info(media)')->fetchAll() as $col) {
            if (($col['name'] ?? '') === 'guest_token') {
                return;
            }
        }

        $pdo->exec('ALTER TABLE media ADD COLUMN guest_token TEXT');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_media_event_guest ON media(event_id, guest_token, status, created_at DESC)');
    }
}