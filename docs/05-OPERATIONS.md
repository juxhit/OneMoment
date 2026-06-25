# 05 — Эксплуатация

## Деплой

См. [deploy/VPS-DOCKER.md](../deploy/VPS-DOCKER.md).

Обязательные переменные `.env`:

| Переменная | Назначение |
|------------|------------|
| DOMAIN | Домен для Caddy и сертификата |
| PUBLIC_PATH | Путь приложения (по умолчанию `/onemoment/public`) |
| ACME_EMAIL | Email для Let's Encrypt |
| ADMIN_PASSWORD | Пароль админки |
| EVENT_ACCESS_SECRET | Секрет для cookie гостевого доступа |

После смены пароля или секрета: `docker compose up -d --build`.

## Обновление

```bash
cd /opt/onemoment
git pull
docker compose up -d --build
```

Volumes сохраняют `data`, `uploads`, `thumbs`, `exports`.

## Резервное копирование

```bash
docker run --rm \
  -v onemoment_onemoment_data:/data \
  -v onemoment_onemoment_uploads:/uploads \
  -v onemoment_onemoment_thumbs:/thumbs \
  -v $(pwd):/backup alpine \
  tar czf /backup/onemoment-backup-$(date +%Y%m%d).tar.gz -C / data uploads thumbs
```

Восстановление — распаковать в соответствующие volumes.

## Мониторинг

- `docker compose ps` / `docker compose logs -f caddy`
- `GET /api/health.php` — свободное место, число файлов, предупреждения по квоте

## Квоты и диск

- `MAX_FILE_MB`, `MAX_TOTAL_FILES`, `MAX_DISK_PERCENT` в `config/config.php`
- Upload отклоняется при превышении лимитов
- ZIP в `exports/` — удалять старые вручную или cron (файлы не чистятся автоматически)

## SSE и прокси

Caddy и Apache настроены на отключение буферизации для `stream.php`:

- `Content-Type: text/event-stream`
- `X-Accel-Buffering: no`
- Heartbeat каждые ~2 сек, reconnect на клиенте

При проблемах со стеной — проверить логи Caddy и fallback polling в `app.js`.

## iOS / камера

Safari требует HTTPS для доступа к камере. На production с Let's Encrypt камера работает после разрешения пользователя.

Fallback в UI: выбор из галереи, если `capture=environment` недоступен.

## Безопасность

- Сменить дефолтный пароль `password` до выхода в production
- Длинный случайный `EVENT_ACCESS_SECRET`
- Не публиковать `token` события в открытых каналах без PIN
- Регулярные бэкапы БД и `uploads/`

## Подготовка репозитория

В git не попадают (см. `.gitignore`):

- `.env`, `config/config.local.php`
- `data/*.db`, медиа в `uploads/`, `thumbs/`, `exports/`
- Локальные артефакты ОС/IDE

В репозитории: исходный код, `vendor/phpqrcode`, `.env.example`, пустые `.gitkeep` в data/uploads.