# Docker запуск

Проект запускается из трех контейнеров:

- `backend` - Laravel API на PHP 8.4 через Laravel Octane и RoadRunner.
- `frontend` - собранный Vue/Vite, который раздает Nginx.
- `db` - PostgreSQL 16.

## Как это работает

Backend-образ ставит PHP-расширения, Composer-зависимости и скачивает Linux-бинарь RoadRunner командой `vendor/bin/rr get-binary`. При старте entrypoint создает Laravel-директории, делает `storage:link`, применяет миграции через `php artisan migrate --force`, затем запускает:

```bash
php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000
```

RoadRunner держит Laravel в памяти как долгоживущие worker-процессы. Поэтому приложение не загружается заново на каждый HTTP-запрос, как при обычном `php artisan serve`.

Frontend собирается командой `npm run build` и раздается Nginx. Nginx проксирует `/api/*`, `/storage/*`, `/docs`, `/openapi.json` и `/up` в backend-контейнер, а остальные пути отдает в `index.html` для Vue Router.

## Env-файлы

Для Docker используется отдельный файл:

```text
.env.docker
```

Он добавлен в `.gitignore`, потому что там могут быть пароли. Для переноса на другой компьютер есть шаблон:

```text
.env.docker.example
```

На новом компьютере можно сделать так:

```powershell
Copy-Item .env.docker.example .env.docker
```

Запускать compose лучше явно с этим файлом:

```powershell
docker compose --env-file .env.docker up --build
```

Важно: Laravel `.env` и Docker `.env.docker` - разные файлы. Локальный Laravel-запуск использует `.env`, Docker-запуск использует переменные из `.env.docker`.

## Обычный запуск

Из корня проекта:

```powershell
docker compose --env-file .env.docker up --build
```

После запуска:

- frontend: <http://localhost:8080>
- backend API напрямую: <http://localhost:8000/api>
- Swagger UI через frontend-прокси: <http://localhost:8080/docs>
- PostgreSQL с хоста: `localhost:5433`

Остановить:

```powershell
docker compose --env-file .env.docker down
```

Удалить контейнеры и volumes, включая базу и загруженные файлы:

```powershell
docker compose --env-file .env.docker down -v
```

## Миграции и сиды

Backend сам применяет миграции при старте, если в `.env.docker` стоит:

```env
RUN_MIGRATIONS=true
```

Выполнить миграции вручную:

```powershell
docker compose --env-file .env.docker exec backend php artisan migrate
```

Заполнить базу сидами:

```powershell
docker compose --env-file .env.docker exec backend php artisan db:seed --force
```

Полностью пересоздать базу и заполнить:

```powershell
docker compose --env-file .env.docker exec backend php artisan migrate:fresh --seed
```

Можно включить сиды прямо при старте:

```env
RUN_SEEDERS=true
```

## Порты и IP

Основные настройки в `.env.docker`:

```env
BIND_ADDRESS=0.0.0.0
FRONTEND_PORT=8080
BACKEND_PORT=8000
POSTGRES_BIND_ADDRESS=127.0.0.1
POSTGRES_PORT=5433
```

`BIND_ADDRESS=0.0.0.0` означает, что frontend/backend доступны не только с этого компьютера, но и из локальной сети, если Windows Firewall пропускает порт.

Если IP компьютера в сети `192.168.1.50`, приложение будет доступно с других устройств по:

```text
http://192.168.1.50:8080
```

PostgreSQL по умолчанию привязан к `127.0.0.1`, то есть снаружи не открыт. Это сделано специально.

## Если PostgreSQL уже есть

Есть два нормальных сценария.

### PostgreSQL проброшен на Windows-хост

Проверь контейнеры:

```powershell
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Networks}}"
```

Если у PostgreSQL в колонке `PORTS` есть что-то вроде `0.0.0.0:5432->5432/tcp` или `127.0.0.1:5432->5432/tcp`, backend может подключиться через Windows-хост.

В `.env.docker` укажи:

```env
DOCKER_DB_HOST=host.docker.internal
DOCKER_DB_PORT=5432
DOCKER_DB_DATABASE=plantAssistant
DOCKER_DB_USERNAME=postgres
DOCKER_DB_PASSWORD=password
```

Запускай только backend и frontend, чтобы compose не поднимал свой `db`:

```powershell
docker compose --env-file .env.docker up --build backend frontend
```

### PostgreSQL в другом контейнере без проброшенного порта

Тогда backend нужно подключить к Docker-сети того контейнера.

Узнай имя контейнера и сеть:

```powershell
docker ps --format "table {{.Names}}\t{{.Networks}}"
```

В `.env.docker` укажи:

```env
DOCKER_DB_HOST=postgres-container-name
DOCKER_DB_PORT=5432
DOCKER_DB_DATABASE=plantAssistant
DOCKER_DB_USERNAME=postgres
DOCKER_DB_PASSWORD=password
EXTERNAL_POSTGRES_NETWORK=existing_network_name
```

Запускай с override-файлом:

```powershell
docker compose --env-file .env.docker -f docker-compose.yml -f docker-compose.external-db.yml up --build backend frontend
```

Если база `plantAssistant` в существующем PostgreSQL еще не создана, создай ее заранее или укажи в `DOCKER_DB_DATABASE` уже существующую базу. Laravel-миграции создают таблицы, но не создают саму базу данных.

## Полезные команды

```powershell
docker compose --env-file .env.docker logs -f backend
docker compose --env-file .env.docker logs -f frontend
docker compose --env-file .env.docker ps
```

Для production нужно заменить `DOCKER_APP_KEY`, выключить debug-настройки в compose и хранить секреты вне репозитория.
