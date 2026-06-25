-- Reference schema v3 (source of truth: src/Database.php migrations)
-- PRAGMA user_version = 3

CREATE TABLE event (
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

CREATE TABLE media (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id            INTEGER NOT NULL REFERENCES event(id) ON DELETE CASCADE,
    filename            TEXT NOT NULL UNIQUE,
    original_name       TEXT,
    mime_type           TEXT NOT NULL,
    size_bytes          INTEGER NOT NULL DEFAULT 0,
    width               INTEGER,
    height              INTEGER,
    status              TEXT NOT NULL DEFAULT 'approved',
    guest_label         TEXT,
    guest_token         TEXT,
    created_at          TEXT NOT NULL
);

CREATE TABLE admin_session (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    session_token       TEXT NOT NULL UNIQUE,
    expires_at          TEXT NOT NULL,
    created_at          TEXT NOT NULL
);

CREATE INDEX idx_media_event_status ON media(event_id, status, created_at DESC);
CREATE INDEX idx_media_event_created ON media(event_id, created_at DESC);
CREATE INDEX idx_media_event_guest ON media(event_id, guest_token, status, created_at DESC);