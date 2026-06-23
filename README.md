# File Server API

Файловый сервер на PHP с REST API для работы с папками и файлами.

Он умеет авторизовать пользователя по логину и паролю, выдавать токен, хранить сессии в PostgreSQL и выполнять основные операции с файлами: создать папку, посмотреть список папок, загрузить файл, скачать его, переименовать или заменить.

## Стек

- PHP 8.3
- Slim Framework
- Doctrine ORM
- PostgreSQL
- Docker
- Composer

## Возможности

- вход по логину и паролю
- хранение пользователей в базе
- хранение сессий и токенов в базе
- срок действия токена
- создание папок пользователя
- просмотр папок пользователя
- загрузка файлов
- скачивание файлов
- переименование файлов
- замена файлов
- отдельное хранилище для каждого пользователя
- запуск через Docker

## Структура проекта

```text
file-server/
├── public/
│   └── index.php
├── scripts/
│   └── create_user.php
├── src/
│   ├── Auth/
│   │   └── LoginService.php
│   ├── Config/
│   │   └── doctrine.php
│   ├── Entity/
│   │   ├── User.php
│   │   └── Session.php
│   ├── Middleware/
│   │   └── AuthMiddleware.php
│   └── Service/
│       ├── DirectoryService.php
│       └── FileService.php
├── storage/
│   └── .gitkeep
├── composer.json
├── composer.lock
├── create_schema.php
├── Dockerfile
├── docker-compose.yml
├── .dockerignore
├── .gitignore
└── README.md
```

## Хранение файлов

Файлы лежат в `storage/{user_id}/`.

Пример:

```text
storage/1/docs/example.txt
```

Пользователь работает только со своей папкой. Пользователь с `id = 1` не получает доступ к файлам пользователя с `id = 2`.

## Запуск

Склонируйте репозиторий:

```bash
git clone https://github.com/creator-XD/file-server.git
cd file-server
```

Запустите контейнеры:

```bash
docker compose up --build
```

После запуска API будет доступен по адресу:

```text
http://localhost:8000
```

## Таблицы в базе

После первого запуска создайте таблицы:

```bash
docker compose exec app php create_schema.php
```

Ожидаемый вывод:

```text
Database schema created successfully
```

Скрипт создает две таблицы:

```text
users
sessions
```

## Создание пользователя

Регистрации через API нет. Пользователь добавляется CLI-скриптом:

```bash
docker compose exec app php scripts/create_user.php admin 123456
```

Если все прошло нормально:

```text
User created successfully
```

Если такой логин уже есть:

```text
User already exists
```

Пароль хранится не открытым текстом, а bcrypt-хешем.

## Проверка сервера

```http
GET /ping
```

Пример:

```bash
curl http://localhost:8000/ping
```

Ответ:

```json
{
  "status": "ok"
}
```

## Авторизация

```http
POST /login
```

Тело запроса в JSON:

```json
{
  "login": "admin",
  "password": "123456"
}
```

Пример ответа:

```json
{
  "token": "generated_token"
}
```

Этот токен нужно передавать во все защищенные запросы:

```http
Authorization: Bearer generated_token
```

## Защищенный маршрут

```http
GET /protected-test
```

Без токена сервер вернет:

```json
{
  "error": "Unauthorized"
}
```

С токеном:

```json
{
  "message": "Access granted",
  "user_id": 1,
  "login": "admin"
}
```

## API папок

Для всех запросов нужен заголовок:

```http
Authorization: Bearer generated_token
```

### Просмотр папок

```http
GET /directories
```

Пример ответа:

```json
[
  "docs"
]
```

Если папок нет:

```json
[]
```

### Создание папки

```http
POST /directories
```

Тело запроса в JSON:

```json
{
  "name": "docs"
}
```

Ответ:

```json
{
  "success": true
}
```

### Удаление папки

```http
DELETE /directories
```

Тело запроса в JSON:

```json
{
  "name": "docs"
}
```

Ответ:

```json
{
  "success": true
}
```

Удаление папок добавлено отдельно. В задании обязательными были просмотр и создание папок.

## API файлов

Для всех запросов нужен заголовок:

```http
Authorization: Bearer generated_token
```

### Загрузка файла

```http
POST /files/upload
```

Тело запроса в `form-data`:

```text
directory = docs
file      = selected_file
```

Поле `file` должно быть типа `File`, не `Text`.

Ответ:

```json
{
  "message": "File uploaded",
  "file": "example.txt"
}
```

Файл сохранится сюда:

```text
storage/{user_id}/docs/example.txt
```

### Скачивание файла

```http
GET /files/download?path=docs/example.txt
```

Ответом будет сам файл.

В Postman удобнее проверять через `Send and Download`.

### Переименование файла

```http
PUT /files/rename
```

Тело запроса в JSON:

```json
{
  "old_path": "docs/example.txt",
  "new_name": "renamed.txt"
}
```

Ответ:

```json
{
  "message": "File renamed"
}
```

После переименования файл будет доступен по пути:

```text
docs/renamed.txt
```

### Замена файла

```http
POST /files/replace
```

Тело запроса в `form-data`:

```text
path = docs/renamed.txt
file = selected_new_file
```

Поле `file` должно быть типа `File`.

Ответ:

```json
{
  "message": "File replaced"
}
```

Существующий файл будет заменен новым загруженным файлом.

## Проверка базы данных

Зайти в PostgreSQL:

```bash
docker compose exec db psql -U file_user -d file_server
```

Список таблиц:

```sql
\dt
```

Пользователи:

```sql
SELECT * FROM users;
```

Сессии:

```sql
SELECT * FROM sessions;
```

Структура таблицы пользователей:

```sql
\d users
```

Структура таблицы сессий:

```sql
\d sessions
```

Выход из PostgreSQL:

```sql
\q
```

## Основные таблицы

### users

Поля:

```text
id
login
password_hash
created_at
```

### sessions

Поля:

```text
id
token
expires_at
created_at
user_id
```

## Примеры curl

### Login

```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d "{\"login\":\"admin\",\"password\":\"123456\"}"
```

### Создание папки

```bash
curl -X POST http://localhost:8000/directories \
  -H "Authorization: Bearer generated_token" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"docs\"}"
```

### Просмотр папок

```bash
curl -X GET http://localhost:8000/directories \
  -H "Authorization: Bearer generated_token"
```

## Остановка

Остановить контейнеры:

```bash
docker compose down
```

Остановить контейнеры и удалить данные базы:

```bash
docker compose down -v
```

`docker compose down -v` удаляет volume PostgreSQL. Используйте эту команду только если данные базы больше не нужны.

## Безопасность

В проекте есть базовые проверки:

- пароли хранятся bcrypt-хешем
- файловые операции доступны только с токеном
- токены хранятся в базе
- токены имеют срок действия
- каждый пользователь работает только со своей папкой
- пути с `..`, `/` и `\` в имени файла запрещены
- загруженные файлы не попадают в Git


## Статус проекта

Сейчас это минимальный файловый сервер API для учебного задания. Реализованы работа с файлами и папками, авторизация по токену, хранение пользователей и сессий в PostgreSQL, изоляция файлов по пользователям и запуск через Docker.
