# Plant Assistant API

Backend API для сервиса ухода за комнатными растениями на Laravel 13.

## Возможности

- Аутентификация через Laravel Sanctum (`register`, `login`, `logout`, `me`, `refresh`).
- Роли `user` и `admin`, policy-based авторизация.
- CRUD для пользователей, комнат, растений, настроек и логов ухода.
- Лента (`feed`), дашборд (`dashboard`), лайки, подписки, советы.
- Загрузка аватаров и изображений растений (с компрессией через GD, если доступно).
- Жалобы на растения/советы, модерация жалоб, аудит-лог модераторских действий.
- Rate limit для админских действий.
- Swagger UI + OpenAPI с автогенерацией.

## Технологии

- PHP 8.3+
- Laravel 13
- PostgreSQL
- Laravel Sanctum
- Vite (для frontend-части документации/заготовки)

## Быстрый старт

1. Установить зависимости:

```bash
composer install
npm install
```

2. Подготовить окружение:

```bash
cp .env.example .env
php artisan key:generate
```

3. Настроить PostgreSQL в `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=plantAssistant
DB_USERNAME=postgres
DB_PASSWORD=password
```

4. Применить миграции и заполнить БД:

```bash
php artisan migrate --seed
```

5. Подключить публичное хранилище:

```bash
php artisan storage:link
```

6. Запустить проект:

```bash
composer run dev
```

API будет доступен по `http://127.0.0.1:8000/api`.

## Swagger / OpenAPI

- UI: `http://127.0.0.1:8000/docs`
- Спецификация: `public/openapi.json`
- Генерация: `php artisan openapi:generate`

OpenAPI генерируется автоматически в:
- `composer setup`
- `composer test`

То есть спецификация всегда обновляется перед тестами.

## Тесты

Запуск:

```bash
composer test
```

Также можно отдельно:

```bash
php artisan test
vendor/bin/pint --test
```

В проекте есть контрактные тесты OpenAPI:
- проверка валидности структуры,
- проверка, что все `/api`-роуты задокументированы,
- проверка детализированных контрактов для ключевых endpoint.

## Сидеры

Сидеры обновлены под актуальные модели и связи:

- роли создаются через `firstOrCreate`,
- пользователи создаются с привязкой к роли по имени (`user`, `admin`),
- комнаты/растения/настройки/логи/советы/лайки/подписки создаются согласованно,
- подписки (`follows`) гарантированно создаются для пользователей.

Полная пересборка БД с проверкой сидов:

```bash
php artisan migrate:fresh --seed
```

## Основные команды

```bash
php artisan openapi:generate
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint
```

## Важные заметки

- Для web-роутов нужен корректный `APP_KEY` в `.env`.
- Если используешь `SESSION_DRIVER=database`, таблица `sessions` должна быть в БД (в проекте есть миграция).
- Не хранить реальный `.env` в репозитории.
- На production: `APP_DEBUG=false`.

