# 04 — База данных

Файл: `data/onemoment.db`  
Движок: SQLite 3, режим WAL.  
Версия схемы: `PRAGMA user_version = 3` (миграции в `src/Database.php`).

## Таблица `event`

| Поле | Тип | Описание |
|------|-----|----------|
| id | INTEGER PK | AUTOINCREMENT (v2+) |
| token | TEXT UNIQUE | Секрет в URL гостей |
| room_code | TEXT UNIQUE | 6 цифр для `/join/` |
| title | TEXT | Название мероприятия |
| event_date | TEXT | Дата (опционально) |
| accent_color | TEXT | Акцент UI |
| wall_bg | TEXT | Фон стены |
| wall_mode | TEXT | Режим Live Wall |
| pin_hash | TEXT | bcrypt PIN или NULL |
| moderation_mode | INTEGER | 0=G1, 1=G2 |
| last_known_ip | TEXT | Устаревшее, не используется в Docker |
| quota_bytes | INTEGER | Лимит на событие или NULL |
| is_active | INTEGER | 1 = текущее событие |
| archived_at | TEXT | Время архивации |
| created_at, updated_at | TEXT | UTC ISO |

При первом запуске `Event::ensureExists()` создаёт одно активное событие.

## Таблица `media`

| Поле | Тип | Описание |
|------|-----|----------|
| id | INTEGER PK | |
| event_id | INTEGER FK | → event.id ON DELETE CASCADE |
| filename | TEXT UNIQUE | Имя на диске (hash.ext) |
| original_name | TEXT | Исходное имя файла |
| mime_type | TEXT | image/jpeg, png, webp |
| size_bytes | INTEGER | |
| width, height | INTEGER | |
| status | TEXT | pending \| approved \| rejected |
| guest_label | TEXT | Подпись гостя |
| guest_token | TEXT | Идентификатор устройства гостя (v3) |
| created_at | TEXT | |

Индексы: по `(event_id, status, created_at)`, `(event_id, guest_token, ...)`.

## Таблица `admin_session`

Токены сессий администратора с `expires_at`.

## Типовые запросы

```sql
-- Активное событие
SELECT * FROM event WHERE is_active = 1 LIMIT 1;

-- Фото для стены (SSE)
SELECT * FROM media
WHERE event_id = ? AND id > ? AND status = 'approved'
ORDER BY id ASC;

-- Личная галерея гостя
SELECT * FROM media
WHERE event_id = ? AND guest_token = ? AND status != 'rejected'
ORDER BY created_at DESC;

-- Очередь модерации
SELECT * FROM media WHERE event_id = ? AND status = 'pending'
ORDER BY created_at ASC;
```

## Сброс данных

Для чистого состояния (как при первом деплое):

1. Остановить контейнер (опционально).
2. Удалить `data/onemoment.db` и файлы в `uploads/`, `thumbs/`, `exports/`.
3. При следующем запросе — миграция и `Event::ensureExists()`.

Справочная DDL: `data/schema.sql` (может отставать от миграций — источник истины: `Database.php`).