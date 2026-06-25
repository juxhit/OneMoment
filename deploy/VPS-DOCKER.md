# VPS: Docker + домен

Развёртывание OneMoment на VPS с HTTPS (Let's Encrypt через Caddy).

## Требования

- VPS Ubuntu 22.04+ (минимум 1 GB RAM, 20 GB disk)
- Домен с A-записью на IP сервера
- Docker + Docker Compose v2

## Установка

```bash
git clone <repo-url> /opt/onemoment
cd /opt/onemoment
cp .env.example .env
nano .env
```

### Переменные `.env`

| Переменная | Пример | Описание |
|------------|--------|----------|
| DOMAIN | photos.example.com | Домен |
| PUBLIC_PATH | /onemoment/public | URL-префикс |
| ACME_EMAIL | admin@example.com | Email для Let's Encrypt |
| ADMIN_PASSWORD | (сильный пароль) | Вход в админку |
| EVENT_ACCESS_SECRET | (64+ случайных символов) | Подпись cookie гостей |

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f caddy
```

Админка: `https://ваш-домен/onemoment/public/admin/`

Первый вход — пароль из `ADMIN_PASSWORD`.

## Архитектура

```
Internet → Caddy :443 (HTTPS) → app :80 (PHP 8.2 + Apache)
```

- **Caddy** — TLS, reverse proxy, заголовки для SSE
- **app** — PHP, SQLite, volumes для data/uploads/thumbs/exports

`docker/entrypoint.sh` генерирует `config/config.local.php` из переменных окружения.

## Обновление

```bash
cd /opt/onemoment
git pull
docker compose up -d --build
```

## Бэкап

```bash
docker run --rm \
  -v onemoment_onemoment_data:/data \
  -v onemoment_onemoment_uploads:/uploads \
  -v onemoment_onemoment_thumbs:/thumbs \
  -v $(pwd):/backup alpine \
  tar czf /backup/onemoment-backup.tar.gz -C / data uploads thumbs
```

## Firewall

```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

## Проверка после деплоя

1. Открыть админку, сменить название события.
2. «Настройка» — скопировать гостевую ссылку, проверить QR.
3. Загрузить тестовое фото с телефона.
4. Открыть Live Wall на втором экране.
5. `curl -s https://ваш-домен/onemoment/public/api/health.php`