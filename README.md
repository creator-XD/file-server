# File Server API

## О проекте

File Server API - учебный REST API файлового сервера на PHP. Приложение позволяет пользователю авторизоваться по логину и паролю, получить session token и выполнять операции с директориями и файлами через защищенные HTTP-маршруты.

Проект использует PostgreSQL для пользователей, сессий и метаданных файлов. Физическое содержимое файлов хранится отдельно через абстракцию `StorageInterface`: локально в файловой системе или в S3-compatible хранилище. В локальной Docker-среде для S3 используется MinIO.

## Возможности

- Авторизация пользователей по логину и паролю.
- Token-based sessions с передачей токена через `Authorization: Bearer TOKEN`.
- Защищенные маршруты через `AuthMiddleware`.
- Создание, просмотр и удаление директорий пользователя.
- Загрузка, скачивание, переименование, замена и удаление файлов.
- Local Storage и S3-compatible Storage.
- MinIO для локальной разработки с S3 API.
- SHA-256 дедупликация содержимого файлов через таблицу `file_blobs`.
- Doctrine ORM, DBAL и Doctrine Migrations.
- Статистика использования хранилища через `GET /usage`.
- Тарифы `free` и `pro` с лимитом общего объема и лимитом размера одного файла.
- Логирование запросов и операций в dev/prod режимах.
- Unit tests и Integration tests.
- CI через GitHub Actions.

## Технологии

- PHP 8.3 в Docker-образе `php:8.3-cli`.
- Slim Framework 4.
- Doctrine ORM.
- Doctrine DBAL.
- Doctrine Migrations.
- PostgreSQL 16.
- Docker и Docker Compose.
- MinIO.
- AWS SDK for PHP.
- Monolog.
- PHPUnit.
- GitHub Actions.

## Архитектура

Общий поток обработки защищенного API-запроса:

```text
HTTP request
  -> Slim route
  -> AuthMiddleware
  -> service layer
  -> Doctrine ORM / PostgreSQL
  -> StorageInterface
  -> LocalStorage или S3Storage
```

Основные компоненты:

- `LoginService` проверяет логин и пароль, создает `Session` и возвращает token.
- `AuthMiddleware` читает `Authorization: Bearer TOKEN`, ищет активную сессию и добавляет пользователя в request attribute `user`.
- `DirectoryService` управляет директориями пользователя и при удалении директории чистит связанные `FileEntry` и неиспользуемые `FileBlob`.
- `FileService` загружает, скачивает, переименовывает, заменяет и удаляет файлы, а также реализует дедупликацию по SHA-256.
- `UsageService` считает использование хранилища по логическим файлам пользователя и проверяет лимиты при загрузке.
- `StorageInterface` задает контракт для операций с директориями, файлами и blob-объектами.
- `LocalStorage` хранит данные в локальной файловой системе.
- `S3Storage` хранит данные через S3 API.
- `StorageFactory` выбирает реализацию storage по `storage.type` из конфигурации.
- `LoggerFactory` создает Monolog logger для dev/prod окружения.
- `RequestLoggingMiddleware` логирует начало, завершение и ошибки HTTP-запросов.

Doctrine ORM используется для сущностей `User`, `Session`, `FileEntry` и `FileBlob`. PostgreSQL хранит пользователей, сессии, логические файлы и физически уникальные blob-записи.

Основные таблицы:

- `users` - пользователи, bcrypt-хеш пароля, дата создания и тарифный план.
- `sessions` - session token, срок действия и связь с пользователем.
- `files` - логические пользовательские файлы: путь, имя, размер, MIME type и ссылка на blob.
- `file_blobs` - физически уникальное содержимое: SHA-256 hash, storage key, размер, MIME type и `ref_count`.

`files` описывает файл как объект пользователя. `file_blobs` описывает уникальное содержимое файла. Несколько записей `files` могут ссылаться на один `file_blobs`.


## Требования

Для обычного запуска достаточно Docker:

- Docker Desktop для Windows или macOS.
- Docker Engine + Docker Compose для Linux.

Локальные PHP и Composer на хосте не обязательны: зависимости устанавливаются внутри Docker-образа, а команды выполняются через контейнер `app`.

## Быстрый запуск

1. Клонировать репозиторий и перейти в директорию проекта:

```bash
git clone https://github.com/creator-XD/file-server.git
cd file-server
```

2. Создать `.env` из `.env.example`.

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Linux/macOS:

```bash
cp .env.example .env
```

3. Заполнить secrets в `.env`. Для локального запуска MinIO значения `S3_ACCESS_KEY`, `S3_SECRET_KEY`, `MINIO_ROOT_USER` и `MINIO_ROOT_PASSWORD` должны совпадать с учетными данными MinIO.

Пример локальных значений:

```dotenv
APP_ENV=dev
DB_HOST=db
DB_PORT=5432
DB_NAME=file_server
DB_USER=file_user
DB_PASSWORD=file_password
S3_ENDPOINT=http://minio:9000
S3_REGION=us-east-1
S3_BUCKET=file-server
S3_ACCESS_KEY=minioadmin
S3_SECRET_KEY=minioadmin
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=minioadmin
```

4. Запустить контейнеры:

```bash
docker compose up -d --build
```

5. Применить миграции:

```bash
docker compose exec app vendor/bin/doctrine-migrations migrations:migrate --configuration=migrations.php --db-configuration=migrations-db.php --no-interaction
```

6. Создать пользователя:

```bash
docker compose exec app php scripts/create_user.php admin 123456
```

7. Получить token:

```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"login":"admin","password":"123456"}'
```

8. Использовать token в защищенных запросах:

```http
Authorization: Bearer TOKEN
```

Адреса локальных сервисов:

- API: `http://localhost:8000`
- MinIO Console: `http://localhost:9001`
- PostgreSQL на хосте: `localhost:5432`

Проверка API без авторизации:

```bash
curl http://localhost:8000/ping
```

Ответ:

```json
{
  "status": "ok"
}
```

Остановка контейнеров:

```bash
docker compose down
```

Остановка с удалением Docker volumes PostgreSQL, MinIO и `vendor`:

```bash
docker compose down -v
```

## Переменные окружения

`AppConfig::load()` читает `APP_ENV` и загружает файл `config/{APP_ENV}.php`. При `APP_ENV=dev` используется `config/dev.php`, при `APP_ENV=prod` - `config/prod.php`.

Переменные из `.env.example` и `docker-compose.yml`:

| Переменная | Назначение |
| --- | --- |
| `APP_ENV` | Выбор конфигурации приложения: `dev` или `prod`. |
| `DB_HOST` | Host PostgreSQL внутри Docker-сети, обычно `db`. |
| `DB_PORT` | Порт PostgreSQL, по умолчанию `5432`. |
| `DB_NAME` | Имя базы данных. |
| `DB_USER` | Пользователь PostgreSQL. |
| `DB_PASSWORD` | Пароль PostgreSQL. |
| `S3_ENDPOINT` | Endpoint S3-compatible storage, для Docker MinIO: `http://minio:9000`. |
| `S3_REGION` | Регион S3 API. |
| `S3_BUCKET` | Bucket для blob-объектов. |
| `S3_ACCESS_KEY` | Access key для S3/MinIO. |
| `S3_SECRET_KEY` | Secret key для S3/MinIO. |
| `MINIO_ROOT_USER` | Root user для контейнера MinIO. |
| `MINIO_ROOT_PASSWORD` | Root password для контейнера MinIO. |

Дополнительно `config/prod.php` умеет читать `LOCAL_STORAGE_PATH` и `LOG_PATH`, но текущий `docker-compose.yml` не передает эти переменные в контейнер `app`. Если они нужны в Docker-запуске, compose-конфигурацию нужно расширять отдельно.

Важно: тип хранилища не переключается отдельной env-переменной. Он задан в конфигурационных файлах:

- `config/dev.php`: `storage.type = s3`.
- `config/prod.php`: `storage.type = local`.

## Работа с базой данных и миграциями

Схема базы создается и обновляется через Doctrine Migrations. Конфигурация миграций находится в `migrations.php`, подключение к базе для CLI - в `migrations-db.php`.

Применить миграции:

```bash
docker compose exec app vendor/bin/doctrine-migrations migrations:migrate --configuration=migrations.php --db-configuration=migrations-db.php --no-interaction
```

Посмотреть статус миграций:

```bash
docker compose exec app vendor/bin/doctrine-migrations migrations:status --configuration=migrations.php --db-configuration=migrations-db.php
```

Посмотреть список миграций:

```bash
docker compose exec app vendor/bin/doctrine-migrations migrations:list --configuration=migrations.php --db-configuration=migrations-db.php
```

Миграции проекта:

- `Version202607011723` создает таблицы `users`, `sessions`, `file_blobs`, `files` и индексы.
- `Version20260707114517` добавляет колонку `users.plan` со значением по умолчанию `free`.

## Создание пользователя

Публичной регистрации пользователей через API нет. Пользователь создается CLI-скриптом:

```bash
docker compose exec app php scripts/create_user.php admin 123456
```

Аргументы скрипта:

```text
php scripts/create_user.php <login> <password>
```

Успешный вывод:

```text
User created successfully
```

Если пользователь уже существует:

```text
User already exists
```

Пароль сохраняется как bcrypt-хеш через `password_hash(..., PASSWORD_BCRYPT)`.

## Авторизация

Схема авторизации:

```text
POST /login
  -> проверка login/password
  -> создание Session с token
  -> ответ { "token": "..." }
  -> Authorization: Bearer TOKEN
  -> AuthMiddleware на защищенных маршрутах
```

`LoginService` создает token через `bin2hex(random_bytes(32))`. Срок действия сессии в текущем коде - 1 час.

Тело запроса:

```json
{
  "login": "admin",
  "password": "123456"
}
```

Успешный ответ `200 OK`:

```json
{
  "token": "generated_token"
}
```

Неверные учетные данные возвращают `401 Unauthorized`:

```json
{
  "error": "Invalid credentials"
}
```

Защищенные маршруты требуют заголовок:

```http
Authorization: Bearer generated_token
```

Если заголовка нет, формат не `Bearer ...`, token не найден или сессия истекла, `AuthMiddleware` возвращает `401 Unauthorized`:

```json
{
  "error": "Unauthorized"
}
```

Служебный защищенный маршрут:

```http
GET /protected-test
```

Успешный ответ:

```json
{
  "message": "Access granted",
  "user_id": 1,
  "login": "admin"
}
```

## API

Базовый URL для локального запуска: `http://localhost:8000`.

### Авторизация

#### POST /login

Авторизация не нужна.

JSON body:

```json
{
  "login": "admin",
  "password": "123456"
}
```

Пример запроса:

```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"login":"admin","password":"123456"}'
```

Успешный ответ `200 OK`:

```json
{
  "token": "generated_token"
}
```

Основная ошибка:

- `401 Unauthorized` с `{"error":"Invalid credentials"}`.

### Директории

Все маршруты директорий требуют `Authorization: Bearer TOKEN`.

#### GET /directories

Возвращает список директорий текущего пользователя.

Query parameters: нет.

Пример запроса:

```bash
curl http://localhost:8000/directories \
  -H "Authorization: Bearer generated_token"
```

Успешный ответ `200 OK`:

```json
[
  "docs",
  "docs/reports"
]
```

Если директорий нет:

```json
[]
```

Основная ошибка:

- `401 Unauthorized` без корректного token.

#### POST /directories

Создает директорию пользователя.

JSON body:

```json
{
  "name": "docs"
}
```

`name` может быть вложенным путем, например `docs/reports`. Путь нормализуется; пустые сегменты, `.` и `..` запрещены.

Пример запроса:

```bash
curl -X POST http://localhost:8000/directories \
  -H "Authorization: Bearer generated_token" \
  -H "Content-Type: application/json" \
  -d '{"name":"docs"}'
```

Успешный ответ `201 Created`:

```json
{
  "success": true
}
```

Основные ошибки:

- `400 Bad Request` с `{"error":"Invalid JSON"}`.
- `400 Bad Request` с `{"error":"Directory path is empty"}`.
- `400 Bad Request` с `{"error":"Invalid path"}`.
- `409 Conflict` с `{"error":"Directory already exists"}`.
- `401 Unauthorized` без корректного token.

#### DELETE /directories

Удаляет директорию пользователя и связанные логические файлы внутри нее. При удалении уменьшается `ref_count` связанных blob-объектов, неиспользуемые blob-объекты удаляются из хранилища и базы.

Query parameters:

| Параметр | Назначение |
| --- | --- |
| `path` | Путь удаляемой директории. |

Пример запроса:

```bash
curl -X DELETE "http://localhost:8000/directories?path=docs" \
  -H "Authorization: Bearer generated_token"
```

Успешный ответ `200 OK`:

```json
{
  "message": "Directory deleted"
}
```

Основные ошибки:

- `400 Bad Request` с `{"error":"Cannot delete root directory"}`.
- `400 Bad Request` с `{"error":"Invalid path"}`.
- `400 Bad Request` с `{"error":"Directory not found"}`.
- `401 Unauthorized` без корректного token.

### Файлы

Все маршруты файлов требуют `Authorization: Bearer TOKEN`.

#### POST /files/upload

Загружает новый файл в указанную директорию. Директория должна существовать, если поле `directory` не пустое.

Form-data fields:

| Поле | Тип | Назначение |
| --- | --- | --- |
| `directory` | text | Директория назначения. Может быть пустой строкой для корня пользователя. |
| `file` | file | Загружаемый файл. |

Пример запроса:

```bash
curl -X POST http://localhost:8000/files/upload \
  -H "Authorization: Bearer generated_token" \
  -F "directory=docs" \
  -F "file=@example.txt"
```

Успешный ответ `201 Created`:

```json
{
  "message": "File uploaded",
  "file": "example.txt"
}
```

Основные ошибки:

- `400 Bad Request` с `{"error":"File is required"}`.
- `400 Bad Request` с `{"error":"Directory does not exist"}`.
- `400 Bad Request` с `{"error":"File already exists"}`.
- `400 Bad Request` с `{"error":"Invalid file name"}`.
- `400 Bad Request` с `{"error":"File exceeds maximum allowed size for current plan"}`.
- `400 Bad Request` с `{"error":"Storage limit exceeded"}`.
- `401 Unauthorized` без корректного token.

#### GET /files/download

Скачивает файл по логическому пути пользователя.

Query parameters:

| Параметр | Назначение |
| --- | --- |
| `path` | Путь файла, например `docs/example.txt`. |

Пример запроса:

```bash
curl -L "http://localhost:8000/files/download?path=docs/example.txt" \
  -H "Authorization: Bearer generated_token" \
  -o example.txt
```

Успешный ответ `200 OK`:

```text
Content-Type: application/octet-stream
Content-Disposition: attachment; filename="example.txt"
```

Тело ответа - содержимое файла.

Основные ошибки:

- `404 Not Found` с `{"error":"File path is empty"}`.
- `404 Not Found` с `{"error":"File not found"}`.
- `404 Not Found` с `{"error":"Blob not found"}`.
- `401 Unauthorized` без корректного token.

#### PUT /files/rename

Переименовывает логическую запись файла. Физический blob при этом не меняется.

JSON body:

```json
{
  "old_path": "docs/example.txt",
  "new_name": "renamed.txt"
}
```

`new_name` - только новое имя файла, не путь. Директория остается прежней.

Пример запроса:

```bash
curl -X PUT http://localhost:8000/files/rename \
  -H "Authorization: Bearer generated_token" \
  -H "Content-Type: application/json" \
  -d '{"old_path":"docs/example.txt","new_name":"renamed.txt"}'
```

Успешный ответ `200 OK`:

```json
{
  "message": "File renamed"
}
```

Основные ошибки:

- `400 Bad Request` с `{"error":"Old path is empty"}`.
- `400 Bad Request` с `{"error":"File not found"}`.
- `400 Bad Request` с `{"error":"Invalid file name"}`.
- `400 Bad Request` с `{"error":"File with this name already exists"}`.
- `401 Unauthorized` без корректного token.

#### POST /files/replace

Заменяет содержимое существующего файла новым загруженным файлом. Путь логического файла не меняется.

Form-data fields:

| Поле | Тип | Назначение |
| --- | --- | --- |
| `path` | text | Путь существующего файла, например `docs/renamed.txt`. |
| `file` | file | Новый файл. |

Пример запроса:

```bash
curl -X POST http://localhost:8000/files/replace \
  -H "Authorization: Bearer generated_token" \
  -F "path=docs/renamed.txt" \
  -F "file=@new-content.txt"
```

Успешный ответ `200 OK`:

```json
{
  "message": "File replaced"
}
```

Основные ошибки:

- `400 Bad Request` с `{"error":"File is required"}`.
- `400 Bad Request` с `{"error":"File path is empty"}`.
- `400 Bad Request` с `{"error":"File not found"}`.
- `400 Bad Request` с `{"error":"Invalid path"}`.
- `401 Unauthorized` без корректного token.

#### DELETE /files

Удаляет логический файл пользователя. Если связанный blob больше никем не используется, он удаляется из физического хранилища и из таблицы `file_blobs`.

Query parameters:

| Параметр | Назначение |
| --- | --- |
| `path` | Путь файла, например `docs/renamed.txt`. |

Пример запроса:

```bash
curl -X DELETE "http://localhost:8000/files?path=docs/renamed.txt" \
  -H "Authorization: Bearer generated_token"
```

Успешный ответ `200 OK`:

```json
{
  "message": "File deleted"
}
```

Основные ошибки:

- `400 Bad Request` с `{"error":"File path is empty"}`.
- `400 Bad Request` с `{"error":"File not found"}`.
- `400 Bad Request` с `{"error":"Invalid path"}`.
- `401 Unauthorized` без корректного token.

### Статистика использования

#### GET /usage

Возвращает usage текущего пользователя и лимиты его тарифного плана.

Авторизация нужна.

Query parameters: нет.

Пример запроса:

```bash
curl http://localhost:8000/usage \
  -H "Authorization: Bearer generated_token"
```

Пример успешного ответа `200 OK` для нового пользователя на тарифе `free`:

```json
{
  "plan": "free",
  "used_bytes": 0,
  "storage_limit_bytes": 104857600,
  "remaining_bytes": 104857600,
  "max_file_size_bytes": 20971520,
  "usage_percent": 0
}
```

Поля ответа:

- `plan` - текущий тариф пользователя.
- `used_bytes` - сумма размеров логических файлов пользователя из таблицы `files`.
- `storage_limit_bytes` - общий лимит хранилища для тарифа.
- `remaining_bytes` - оставшийся лимит.
- `max_file_size_bytes` - максимальный размер одного загружаемого файла.
- `usage_percent` - процент использования лимита, округленный до двух знаков.

Основные ошибки:

- `400 Bad Request` с `{"error":"..."}` при ошибке расчета.
- `401 Unauthorized` без корректного token.

## Хранилища

### Local Storage

`LocalStorage` хранит директории пользователя и blob-объекты в локальной файловой системе.

В `config/dev.php` local base path задан как:

```text
storage/
```

В `config/prod.php` local base path берется из `LOCAL_STORAGE_PATH`, если переменная доступна, иначе используется:

```text
/var/www/html/storage
```

Для blob-объектов используется ключ вида:

```text
blobs/{first-two-hash-chars}/{next-two-hash-chars}/{sha256-hash}
```

Пример:

```text
blobs/ab/cd/abcdef1234567890
```

### S3-compatible Storage и MinIO

`S3Storage` использует AWS SDK for PHP и S3 API. В локальной Docker-среде S3-compatible storage представлен сервисом `minio`.

Текущая dev-конфигурация использует S3:

```php
'storage' => [
    'type' => 's3',
]
```

Внутри Docker endpoint MinIO:

```text
http://minio:9000
```

MinIO Console на хосте:

```text
http://localhost:9001
```

`docker-compose.yml` содержит сервис `minio-init`, который после старта MinIO выполняет:

```text
mc alias set local http://minio:9000 ...
mc mb --ignore-existing local/${S3_BUCKET}
```

То есть bucket создается автоматически, если его еще нет.

Текущий `config/prod.php` выбирает `storage.type = local`. Если в production нужен S3-compatible storage, production-конфигурация должна быть изменена на `storage.type = s3`, а реальные credentials должны передаваться безопасным способом.

## Дедупликация

Дедупликация реализована в `FileService` через SHA-256 hash содержимого файла.

Последовательность при загрузке:

1. Приложение получает содержимое uploaded file.
2. Рассчитывается `hash('sha256', $content)`.
3. В таблице `file_blobs` ищется `FileBlob` с таким `hash`.
4. Если blob не найден, содержимое сохраняется в storage по ключу `blobs/xx/yy/hash`, создается новая запись `FileBlob` с `ref_count = 1`.
5. Если blob уже существует, второй физический файл не создается, у существующего blob увеличивается `ref_count`.
6. Создается запись `FileEntry` в таблице `files` с пользовательским путем, именем, размером и ссылкой на blob.
7. При удалении файла или директории `ref_count` уменьшается.
8. При `ref_count = 0` физический blob удаляется из storage, а запись `FileBlob` удаляется из базы.

Разница между сущностями:

- `FileEntry` - логический файл пользователя: `user`, `path`, `name`, `size`, `mime_type`, ссылка на `FileBlob`.
- `FileBlob` - физически уникальное содержимое: `hash`, `storage_key`, `size`, `mime_type`, `ref_count`.

Переименование файла меняет `path` и `name` у `FileEntry`. Blob при переименовании остается тем же.

Замена файла создает или переиспользует blob для нового содержимого, переключает `FileEntry` на новый blob и уменьшает `ref_count` старого blob.

## Тарифы и лимиты

Тариф пользователя хранится в `users.plan`. По умолчанию миграция добавляет `plan = 'free'`. В `User::setPlan()` разрешены только планы `free` и `pro`.

Лимиты в `config/dev.php` и `config/prod.php` одинаковые:

| План | Общий лимит | Максимальный размер одного файла |
| --- | --- | --- |
| `free` | 100 MB | 20 MB |
| `pro` | 1 GB | 200 MB |

В байтах:

| План | `storage_limit` | `max_file_size` |
| --- | ---: | ---: |
| `free` | `104857600` | `20971520` |
| `pro` | `1073741824` | `209715200` |

`UsageService::getUserUsage()` считает `used_bytes` как сумму `size` из таблицы `files` для пользователя. Это логический объем пользовательских файлов, а не физический объем blob-объектов.

Пример с дедупликацией:

- Пользователь загрузил два одинаковых файла по 10 MB.
- Физически хранится один blob размером 10 MB.
- В `files` есть две логические записи по 10 MB.
- В `GET /usage` будет учтено 20 MB.

Такой расчет нужен, чтобы пользовательский лимит зависел от количества и размера файлов в его аккаунте, а не от того, удалось ли физически дедуплицировать содержимое.

Проверка лимитов в текущем коде вызывается при `POST /files/upload`. В `POST /files/replace` отдельный вызов `UsageService::assertCanUpload()` сейчас не выполняется.

## Логирование

Логирование настраивается в `config/dev.php`, `config/prod.php` и `LoggerFactory`.

Dev:

- Уровень: `debug`.
- Путь: `var/log/app-dev.log`.
- Формат: line formatter.

Prod:

- Уровень: `info`.
- Путь по умолчанию: `/var/www/html/var/log/app-prod.log`.
- Формат: JSON через `JsonFormatter`.

`RequestLoggingMiddleware` пишет:

- `Request started` с HTTP method и URI.
- `Request finished` с method, URI, status code и duration.
- `Request failed` с method, URI, текстом ошибки и классом исключения.

Route handlers дополнительно логируют успешные операции и ошибки: login, операции с директориями, файлами и usage.

Текущий код не логирует тела запросов, пароли, session token и содержимое файлов. При этом в логах есть URI, а значит query string может содержать путь файла или директории.

Посмотреть последние строки dev-лога:

```bash
docker compose exec app tail -n 50 var/log/app-dev.log
```

Следить за dev-логом:

```bash
docker compose exec app tail -f var/log/app-dev.log
```

Prod-варианты для текущего пути по умолчанию:

```bash
docker compose exec app tail -n 50 var/log/app-prod.log
docker compose exec app tail -f var/log/app-prod.log
```

## Тестирование

Наборы тестов определены в `phpunit.xml`:

- `Unit` - директория `tests/Unit`.
- `Integration` - директория `tests/Integration`.

Запустить Unit tests:

```bash
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

Запустить Integration tests:

```bash
docker compose exec app vendor/bin/phpunit --testsuite Integration
```

Запустить все тесты:

```bash
docker compose exec app vendor/bin/phpunit
```

Unit tests:

- `LocalStorageTest` проверяет создание и список директорий, вложенные директории, запрет небезопасного пути, сохранение/чтение/удаление blob и ошибку чтения отсутствующего blob.
- `HashStorageKeyTest` проверяет одинаковый SHA-256 для одинакового содержимого, разный SHA-256 для разного содержимого и формат storage key `blobs/ab/cd/hash`.

Integration test `ApiIntegrationTest` работает с API на `http://127.0.0.1:8000` и требует заранее созданного пользователя:

```bash
docker compose exec app php scripts/create_user.php test_api 123456
```

Интеграционный сценарий проверяет:

- login пользователя `test_api`.
- `GET /usage` до операций и тариф `free`.
- создание директории.
- загрузку двух файлов с одинаковым содержимым.
- скачивание файла и совпадение содержимого.
- что две записи `files` ссылаются на один `blob_id`.
- что `file_blobs.ref_count` становится `2`.
- удаление одного файла и уменьшение `ref_count` до `1`.
- удаление директории.
- очистку файлов этой директории из `files`.
- удаление неиспользуемого blob из `file_blobs`.
- возвращение `used_bytes` к исходному значению после cleanup.

## CI через GitHub Actions

Workflow находится в `.github/workflows/ci.yml` и называется `Сборка и тестирование`. Job называется `Сборка и тесты`.

Workflow запускается на:

- `push` в ветки `main` и `master`.
- `pull_request`.

Последовательность CI:

```text
Получение кода
  -> создание файла окружения для CI
  -> сборка Docker-контейнеров
  -> запуск Docker-контейнеров
  -> проверка запущенных контейнеров
  -> ожидание готовности PostgreSQL
  -> применение Doctrine Migrations
  -> создание пользователя test_api / 123456
  -> запуск Unit tests
  -> запуск Integration tests
  -> вывод логов app, db, minio при ошибке
  -> остановка и очистка контейнеров
```

CI не выполняет развертывание. Это автоматическая сборка и тестирование проекта.

## Production notes

- Использовать `APP_ENV=prod`, если запускается production-конфигурация.
- Не хранить реальные secrets в Git и в публичных примерах.
- Передавать production credentials безопасным способом через окружение или секреты инфраструктуры.
- Перед выпуском новой версии применять Doctrine Migrations.
- Не использовать тестовые логины и пароли вроде `admin / 123456` или `test_api / 123456`.
- Контролировать production logs и ротацию логов.
- Учитывать, что текущий `config/prod.php` выбирает Local Storage. Для реального S3-compatible storage нужно явно включить `storage.type = s3` в production-конфигурации и передать S3 credentials.
- Не использовать `docker compose down -v` на окружении, где нужно сохранить данные PostgreSQL, MinIO или `vendor` volume.
