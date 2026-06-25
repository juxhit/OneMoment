# 02 — Стек и решения

## Платформа

| Компонент | Выбор | Причина |
|-----------|-------|---------|
| Backend | PHP 8.2 | Простой деплой, GD для изображений, без отдельного Node |
| БД | SQLite (`data/onemoment.db`) | Один файл, бэкап одной командой, достаточно для event-scale |
| Frontend | Alpine.js + vanilla JS | Лёгкий UI без сборки |
| Real-time | SSE (`api/stream.php`) | Проще WebSocket на Apache/Caddy |
| Reverse proxy | Caddy 2 | Авто HTTPS (Let's Encrypt) |
| Контейнеризация | Docker Compose | Единый способ деплоя на VPS |

## Загрузка файлов

- Клиент: `browser-image-compression` только если файл > `MAX_FILE_MB` (20)
- Сервер: `ImageProcessor` — EXIF-поворот JPEG, превью, PNG/WebP без лишней перекодировки
- Квоты: лимит файлов, занятость диска (`QuotaService`, `api/health.php`)

## Доступ гостей

- Ссылка с неугадываемым `token` в URL
- Опциональный PIN → cookie `om_access` (HMAC, httpOnly)
- Идентификатор гостя → cookie `om_guest` (`GuestIdentity`) для личной галереи

## Модерация

| Режим | `moderation_mode` | Поведение |
|-------|-------------------|-----------|
| G1 | 0 | `approved` сразу после upload |
| G2 | 1 | `pending` → approve/reject в админке |

На Live Wall и в SSE только `status = approved`.

## Live Wall

Режим хранится в `event.wall_mode`. Устаревшие значения (`grid`, `tiles`, `mosaic`, `collage`) автоматически маппятся в `normalize_wall_mode()`.

Клиент: `EventSource` + reconnect; fallback — polling `media.php?wall=1` каждые 2 сек.

## QR-коды

Генерация на сервере: `public/api/qr.php` + библиотека `vendor/phpqrcode`.

## PWA

- `manifest.php` — динамический start_url по token события
- `sw.js` — кэш только статики (`/assets/`), API не кэшируется

## Секреты (production)

Задаются в `.env` → `docker/entrypoint.sh` → `config/config.local.php`:

- `ADMIN_PASSWORD`
- `EVENT_ACCESS_SECRET`
- `PUBLIC_BASE_URL` (полный URL для гостевых ссылок и QR)

Не коммитить `.env` и `config.local.php`.