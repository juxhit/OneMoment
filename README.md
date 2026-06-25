# OneMoment

Фото-стена для мероприятий: гости загружают снимки по QR или ссылке, организатор ведёт Live Wall, модерацию и экспорт архива.

**Развёртывание:** VPS через Docker + Caddy (HTTPS автоматически).

## Быстрый старт

```bash
git clone <repo-url> onemoment
cd onemoment
cp .env.example .env
# отредактируйте DOMAIN, ADMIN_PASSWORD, EVENT_ACCESS_SECRET
docker compose up -d --build
```

Админка: `https://ваш-домен/onemoment/public/admin/`

Подробнее: [deploy/VPS-DOCKER.md](deploy/VPS-DOCKER.md) и [docs/README.md](docs/README.md).

## Возможности

- Гостевая галерея и загрузка с телефона (PWA)
- Live Photo Wall в реальном времени (SSE)
- 7 режимов отображения стены
- Модерация (авто / ручная)
- Несколько мероприятий (архив + новое)
- QR-коды, PIN-доступ, экспорт ZIP
- Персональная галерея гостя (только свои фото)

## Стек

PHP 8.2, SQLite, Alpine.js, Server-Sent Events, Docker, Caddy.

Публичный путь: `/onemoment/public`

## Документация

| Файл | Содержание |
|------|------------|
| [docs/01-OVERVIEW.md](docs/01-OVERVIEW.md) | Обзор продукта |
| [docs/02-STACK.md](docs/02-STACK.md) | Технологии и решения |
| [docs/03-ARCHITECTURE.md](docs/03-ARCHITECTURE.md) | Архитектура |
| [docs/04-DATABASE.md](docs/04-DATABASE.md) | Схема БД |
| [docs/05-OPERATIONS.md](docs/05-OPERATIONS.md) | Эксплуатация и риски |
| [deploy/VPS-DOCKER.md](deploy/VPS-DOCKER.md) | Деплой на VPS |

## Локальная разработка (опционально)

Требуется PHP 8.2+ с расширениями `gd`, `pdo_sqlite`, `fileinfo`. Document root — каталог `public/`, базовый URL `/onemoment/public`.

```bash
php -S localhost:8080 -t public
```

Секреты и пароли — только в `config/config.local.php` (не коммитить) или через переменные окружения в Docker.

## Лицензия

Проприетарный проект. Уточните условия использования у владельца репозитория.