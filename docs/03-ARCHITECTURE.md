# 03 — Архитектура

## Схема (production)

```
Internet
    │
    ▼
Caddy :443  ── TLS (Let's Encrypt)
    │  reverse_proxy + SSE headers
    ▼
app :80  ── PHP 8.2 + Apache
    │
    ├── SQLite  data/onemoment.db
    ├── uploads/   оригиналы
    ├── thumbs/    превью 400 / 1200
    └── exports/   ZIP-архивы (временные)
```

`PUBLIC_PATH` по умолчанию: `/onemoment/public` — Apache Alias в контейнере.

## Структура каталогов

```
onemoment/
├── api/                 # логика API (используется из public/api/)
├── config/
│   ├── config.php       # константы, дефолты
│   └── config.local.php # генерируется в Docker (gitignore)
├── data/
│   ├── schema.sql       # справочная DDL
│   └── onemoment.db     # runtime (gitignore)
├── deploy/
│   └── VPS-DOCKER.md
├── docker/
│   ├── Caddyfile
│   └── entrypoint.sh
├── public/              # document root
│   ├── admin/           # панель организатора
│   ├── api/             # тонкие обёртки → ../api/
│   ├── assets/
│   ├── e.php            # гостевая галерея + upload
│   ├── wall.php         # Live Wall
│   ├── join.php         # редирект по room_code
│   ├── serve.php        # раздача uploads/thumbs
│   ├── manifest.php
│   └── sw.js
├── src/                 # PHP-классы и bootstrap
├── vendor/phpqrcode/    # QR PNG
├── Dockerfile
└── docker-compose.yml
```

## Маршруты (публичные)

| URL | Файл | Назначение |
|-----|------|------------|
| `/e/{token}` | `e.php` | Гость: upload + галерея |
| `/wall/{token}` | `wall.php` | Live Wall |
| `/join/{code}` | `join.php` | Переход к событию по 6-значному коду |
| `/i/u/{file}` | `serve.php` | Оригинал |
| `/i/t/{w}/{file}` | `serve.php` | Превью |

## API

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `api/upload.php` | POST | Загрузка фото |
| `api/media.php` | GET | Список медиа (гость / wall) |
| `api/stream.php` | GET | SSE-поток новых approved |
| `api/pin-verify.php` | POST | Проверка PIN |
| `api/health.php` | GET | Диск, квоты, счётчики |
| `api/qr.php` | GET | PNG QR-код |

## Админка

| Путь | Назначение |
|------|------------|
| `/admin/` | Дашборд |
| `/admin/settings.php` | Название, оформление, модерация, PIN |
| `/admin/setup.php` | Ссылки и QR для гостей |
| `/admin/moderate.php` | Очередь G2 |
| `/admin/events.php` | Список событий, архив, удаление |
| `/admin/export.php` | ZIP-экспорт |

Авторизация: `AdminAuth` — сессия PHP + таблица `admin_session`.

## Поток upload

1. `e.php` → Alpine `guestApp` → `FormData` → `api/upload.php`
2. Проверка MIME, квоты, moderation_mode
3. `ImageProcessor::process()` → `uploads/` + `thumbs/`
4. Запись в `media` с `event_id`, `guest_token`
5. SSE уведомляет wall о новом `id`

## Мульти-события

- Активное событие: `event.is_active = 1` (одно)
- `Event::archiveAndCreateNew()` — архивирует текущее, создаёт новое с теми же настройками
- `Event::deleteArchived()` — удаляет архивное событие и все его файлы