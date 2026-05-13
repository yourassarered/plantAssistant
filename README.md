# Plant Assistant API

Backend API для сервиса ухода за комнатными растениями. Проект помогает вести личную коллекцию растений, планировать уход, публиковать растения в ленту, получать советы от других пользователей и модерировать контент через админку.

## Возможности

- Регистрация и вход через Laravel Sanctum.
- Роли `user` и `admin`.
- CRUD комнат и растений.
- Несколько фотографий у растения; в ответах растения отдаётся последняя фотография.
- Аватар пользователя с fallback-placeholder.
- Настройки ухода: полив, удобрение, обрезка, поворот.
- Журнал ухода и расчёт задач на сегодня, месяц, ближайшие дни и просрочку.
- Публичная и персональная лента растений.
- Лайки, подписки, советы и рейтинг пользователей.
- Жалобы на растения и советы.
- Админское рассмотрение жалоб; при принятой жалобе на совет рейтинг автора совета уменьшается.

## Стек

- PHP 8.3+
- Laravel 13
- Laravel Sanctum
- PostgreSQL
- Vite/Tailwind для минимального frontend-слоя

## Быстрый старт

1. Установите PHP-зависимости:

```bash
composer install
```

2. Установите frontend-зависимости:

```bash
npm install
```

3. Создайте `.env`:

```bash
cp .env.example .env
```

4. Настройте подключение к PostgreSQL в `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=plantAssistant
DB_USERNAME=postgres
DB_PASSWORD=password
```

5. Сгенерируйте ключ приложения:

```bash
php artisan key:generate
```

6. Примените миграции и сидеры:

```bash
php artisan migrate --seed
```

7. Создайте публичную ссылку на storage:

```bash
php artisan storage:link
```

8. Запустите приложение:

```bash
composer run dev
```

API будет доступен по адресу `http://127.0.0.1:8000/api`.

## Тесты

Тесты настроены на отдельную PostgreSQL-базу `plantAssistant_test`. Создайте её перед первым запуском:

```sql
CREATE DATABASE "plantAssistant_test";
```

Запуск:

```bash
composer test
```

Текущий набор покрывает загрузку/удаление аватаров, изображения растений, доступы к медиа, жалобы и админское рассмотрение жалоб.

## Основные API-разделы

Все приватные маршруты требуют заголовок:

```http
Authorization: Bearer <token>
Accept: application/json
```

### Auth

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `POST /api/auth/refresh`

### Пользователи и аватары

- `GET /api/users`
- `GET /api/users/{id}`
- `PUT /api/users/profile`
- `GET /api/users/{id}/avatar`
- `POST /api/users/profile/avatar`
- `DELETE /api/users/profile/avatar`

Поле файла для аватара: `avatar`. Поддерживаются `jpg`, `jpeg`, `png`, `webp`, размер до 5 MB. Если аватара нет, API отдаёт placeholder `/images/placeholders/avatar-placeholder.png`.

### Растения и изображения

- `GET /api/plants`
- `POST /api/plants`
- `GET /api/plants/{id}`
- `PUT /api/plants/{id}`
- `DELETE /api/plants/{id}`
- `POST /api/plants/{id}/toggle-public`
- `GET /api/plants/{plantId}/images`
- `POST /api/plants/{plantId}/images`
- `GET /api/plant-images/{id}`
- `PUT /api/plant-images/{id}`
- `DELETE /api/plant-images/{id}`

Поле файла для изображения растения: `image`. Поддерживаются `jpg`, `jpeg`, `png`, `webp`, размер до 8 MB. При наличии GD изображения уменьшаются и пересохраняются с заданным качеством; если GD недоступен, файл сохраняется без сжатия.

### Уход

- `GET /api/plants/{plantId}/care-settings`
- `POST /api/plants/{plantId}/care-settings`
- `PUT /api/care-settings/{id}`
- `DELETE /api/care-settings/{id}`
- `POST /api/care-settings/{id}/toggle`
- `GET /api/plants/{plantId}/care-logs`
- `POST /api/plants/{plantId}/care-logs`
- `GET /api/care-schedule/todays-care`
- `GET /api/care-schedule/month`
- `GET /api/care-schedule/upcoming`
- `GET /api/care-schedule/overdue`

### Социальные функции

- `GET /api/feed`
- `GET /api/feed/personal`
- `GET /api/feed/trending`
- `POST /api/plants/{plantId}/like`
- `POST /api/users/{userId}/follow`
- `DELETE /api/users/{userId}/unfollow`
- `POST /api/plants/{plantId}/tips`
- `PUT /api/tips/{id}/status`

### Жалобы и админка

Пользователь может пожаловаться:

- `POST /api/plants/{plantId}/reports`
- `POST /api/tips/{tipId}/reports`

Админские маршруты:

- `GET /api/admin/reports`
- `GET /api/admin/reports/{id}`
- `PUT /api/admin/reports/{id}/review`
- `DELETE /api/users/{id}`
- `PUT /api/users/{id}/role`
- `DELETE /api/users/{id}/avatar`

Пример рассмотрения жалобы:

```json
{
  "status": "accepted",
  "admin_comment": "Контент нарушает правила"
}
```

## Важные замечания

- Не храните реальный `.env` в Git.
- В production обязательно установите `APP_DEBUG=false`.
- Для корректных URL медиа выполните `php artisan storage:link`.
- Placeholder-файлы лежат в `public/images/placeholders/`; их можно заменить настоящими изображениями с теми же именами.
