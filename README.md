# LiteWMS Chat

Отдельный сервис обмена сообщениями для LiteWMS и любых других сайтов. Живёт на своём поддомене
(`chat.litewms.ru`), имеет собственный REST API со Swagger (годится и для будущего мобильного приложения)
и подключается к любому месту любого сайта одной строкой `<script>`.

Построен на идеях [ahilespelid/messenger](https://github.com/ahilespelid/messenger), но это отдельный проект:
многоарендный (много сайтов — один чат), без собственных паролей пользователей, с привязкой диалогов к сущностям сайта.

## Что внутри

- **Laravel 13** (PHP 8.4+), Octane/FrankenPHP, **Reverb** для WebSocket, Sanctum Bearer-токены, MySQL/Percona, Redis.
- **REST API `/api/v1`** и Swagger UI на `/docs` (спецификация генерируется из кода: `/docs/openapi.json`).
- Веб-клиент на Alpine + Tailwind: страница `/app`, режим для iframe `/embed`.
- Виджет `public/embed.js`: плавающая кнопка, веб-компонент `<lw-chat>`, JS API.

Возможности: личные, групповые и «поддержка» диалоги; контекст диалога (заявка, товар, документ);
голосовые, файлы, ответы, правка и удаление (оператор видит удалённое); прочитано ✓✓, непрочитанное,
«печатает…», присутствие; браузерные push.

## Как это устроено

```
 сайт-хозяин (ЛК)                     chat.litewms.ru
 ┌────────────────┐  JWT (подпись)   ┌──────────────────────────┐
 │ бэкенд сайта   │ ───────────────► │ POST /api/v1/auth/exchange│ → Bearer-токен участника
 │ (секрет app)   │  server token    │ /api/v1/server/*          │ ← диалоги, участники, системные сообщения
 └──────┬─────────┘                  │ REST /api/v1 + Swagger    │
        │ <script embed.js>          │ Reverb  wss://…/app/*     │
        ▼                            └──────────────────────────┘
   iframe chat.litewms.ru/embed  ◄── postMessage (проверка origin)
```

Чат **не знает паролей** сайта. Сайт сам решает, кто может войти, и подписывает короткоживущий токен
общим секретом приложения. Куки у чата нет — только Bearer-токен в `Authorization`.

## Подключение сайта — по шагам

**1. Создать приложение чата** (один раз на сайт, на сервере чата):

```bash
docker compose exec app php artisan chat:app "ЛК LiteWMS" \
  --origin=https://litewms.ru --origin=https://test.litewms.ru \
  --login-url=https://test.litewms.ru/lk2/chat/login
```

`--login-url` включает кнопку «Войти через LiteWMS» на самостоятельной странице чата (см. ниже), `--guests` разрешает анонимных гостей.

Команда печатает три значения, **секреты показываются один раз**: `app key` (`lw_pub_…`, публичный),
`secret` (`lws_…`, подпись токенов входа) и `server token` (`lcs_…`, серверный API). Секреты хранятся только на
бэкенде сайта. Потеряли — `php artisan chat:app:rotate`. Операторов поддержки задаёт сайт флагом `operator` в токене
или командой `chat:operator`.

**2. Эндпоинт токена на сайте.** Бэкенд сайта для уже вошедшего пользователя отдаёт JWT HS256:

| claim | значение |
|---|---|
| `iss` | `app key` |
| `sub` | id пользователя на сайте |
| `exp` | не позже чем через 10 минут от `iat` (рекомендуется 2 минуты) |
| `jti` | уникальная строка — токен принимается один раз |
| `name`, `email`, `avatar`, `role` | профиль, необязательно |
| `operator` | `true` — человек видит все обращения в поддержку |
| `meta` | произвольный объект |

```php
// Laravel: GET /chat/token, под auth-middleware сайта
Route::get('/chat/token', function () {
    $u = auth()->user(); $now = time();
    return ['token' => \Firebase\JWT\JWT::encode([
        'iss' => config('services.chat.key'), 'sub' => (string) $u->id,
        'iat' => $now, 'exp' => $now + 120, 'jti' => (string) \Illuminate\Support\Str::uuid(),
        'name' => $u->name, 'role' => 'Продавец', 'operator' => $u->isWarehouse(),
    ], config('services.chat.secret'), 'HS256')];
})->middleware('auth');
```

```js
// Node (jsonwebtoken)
jwt.sign({ sub: String(user.id), name: user.name, jti: crypto.randomUUID() }, SECRET,
         { algorithm: 'HS256', issuer: APP_KEY, expiresIn: 120 });
```

```python
# Python (PyJWT)
jwt.encode({"iss": APP_KEY, "sub": str(user.id), "name": user.name, "iat": now, "exp": now + 120,
            "jti": str(uuid.uuid4())}, SECRET, algorithm="HS256")
```

**2а. Отдельный вход в `chat.litewms.ru/app`** (чат без страницы сайта вокруг). Страница показывает кнопку «Войти через LiteWMS» и
отправляет человека на `login_url` приложения с параметром `return=https://chat.litewms.ru/app?app=…`. Сайт входит в свой аккаунт
(гостя просит войти), подписывает тот же токен, что и для виджета, и возвращает человека на `return` с токеном во **фрагменте**:
`https://chat.litewms.ru/app?app=…#token=<JWT>`. Страница меняет токен на Bearer, убирает его из адреса и запоминает вход в браузере.
Сайт обязан принимать `return` только с адреса самого чата, иначе токен можно увести на чужой сайт. Без `?app=` страница берёт приложение
само, если оно единственное активное со входом через сайт. Без `login_url` страница как раньше просит открыть чат из кабинета.
Личные ссылки операторов `/l/{secret}` (команда `chat:operator`) работают без сайта вовсе.

**3. Вставить на страницу.** Плавающая кнопка с счётчиком непрочитанных — в шаблон сайта:

```html
<script src="https://chat.litewms.ru/embed.js" data-app="lw_pub_…" data-token-url="/chat/token"></script>
```

Встроить в раздел страницы (например раздел «Чат» личного кабинета) или в карточку сущности:

```html
<lw-chat app="lw_pub_…" token-url="/chat/token" style="height:640px"></lw-chat>

<!-- чат по конкретной заявке: найдёт существующий диалог или заведёт обращение -->
<lw-chat app="lw_pub_…" token-url="/chat/token" view="thread"
         context="request:123" context-title="Заявка №123" context-url="https://litewms.ru/lk2/requests/123"
         style="height:520px"></lw-chat>
```

JS API: `LWChat.init({app, tokenUrl | tokenProvider, accent, position, label})`, `LWChat.open([{context}])`,
`LWChat.close()`, `LWChat.on('unread' | 'open' | 'close' | 'ready', fn)`, `LWChat.unread`, `LWChat.destroy()`.
Пример — кнопка «Написать складу» в таблице заявок:
`LWChat.open({ context: { type: 'request', id: '123', title: 'Заявка №123' } })`.

Чат грузится в sandbox-iframe; показывать его могут только сайты из `--origin` (заголовок CSP `frame-ancestors`),
обмен с родительской страницей — `postMessage` с проверкой origin в обе стороны.

## Серверный API (для бэкенда сайта)

`Authorization: Bearer lcs_…`. Это единственный способ, которым сайт определяет, кто с кем общается:

| метод | назначение |
|---|---|
| `PUT /api/v1/server/participants/{external_id}` | создать/обновить человека (имя, роль, оператор) |
| `POST …/participants/{id}/block` · `/unblock` | заблокировать (токены отзываются) / вернуть |
| `GET …/participants/{id}/unread` | число непрочитанного — для бейджа в меню при серверном рендере |
| `POST /api/v1/server/conversations` | создать диалог идемпотентно (`unique_key`), `members`, `context`, первое `message` |
| `POST …/conversations/{id}/members` · `DELETE …/members/{external_id}` | состав участников |
| `POST …/conversations/{id}/messages` | сообщение от участника (`from`) или системное («Заявка принята») |
| `POST …/conversations/{id}/archive` | убрать из списков, данные остаются |

```bash
curl -X POST https://chat.litewms.ru/api/v1/server/conversations \
  -H "Authorization: Bearer lcs_…" -H "Content-Type: application/json" -d '{
    "type": "group", "title": "Заявка №123", "unique_key": "request:123:seller:45",
    "members": [{"external_id": "45", "name": "Анна", "role": "Продавец"}, {"external_id": "wh-1", "name": "Склад"}],
    "context": {"type": "request", "id": "123", "title": "Заявка №123", "url": "https://litewms.ru/lk2/requests/123"},
    "message": {"body": "Заявка принята"}
}'
```

## Клиентский API (веб и мобильное приложение)

Swagger: **https://chat.litewms.ru/docs**. Схемы авторизации: `participant` (Bearer, получают обменом JWT на
`POST /api/v1/auth/exchange`; живёт 7 дней, не больше 10 активных на человека) и `server`.
Мобильное приложение получает handoff-токен от бэкенда сайта так же, как веб-страница, и дальше работает только с этим API.

Реальное время — Reverb (протокол Pusher): авторизация каналов `POST /api/v1/broadcasting/auth` тем же Bearer-токеном,
личный канал `private-participant.{id}` (события `message.created|updated|read`, `conversation.updated|removed`),
presence-канал `presence-conversation.{id}` (`typing`, кто в диалоге). Подробности — в описании в Swagger.

## Локальная разработка

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate   # DB_HOST=127.0.0.1, SESSION_DRIVER=array, CACHE_STORE=array, QUEUE_CONNECTION=sync
php artisan migrate && npm run build
php artisan serve & php artisan reverb:start       # http://127.0.0.1:8000, /docs
php artisan test                                   # нужна MySQL/MariaDB с базой litewms_chat_test (см. phpunit.xml)
```

## Деплой на chat.litewms.ru

Стек (`docker-compose.yml`): `litewms_chat` (приложение), `litewms_chat_reverb`, `litewms_chat_worker` (очередь),
`litewms_chat_scheduler`, `litewms_chat_percona`, `litewms_chat_redis`; сеть `litewms_chat_net`, порты наружу не публикуются.
TLS выдаёт общий Caddy сервера.

1. DNS: A-запись `chat.litewms.ru` → IP сервера.
2. `git clone` в `/opt/litewms_chat` и `sudo ./deploy/install.sh` — создаёт `.env` с секретами, собирает образ,
   поднимает стек, выпускает VAPID-ключи.
3. Caddy: подключить его контейнер к сети (`docker network connect litewms_chat_net <caddy>`), положить
   `deploy/litewms_chat.caddy` в `sites.d` и перечитать конфиг.
4. `php artisan chat:app …` (см. выше) — получить ключи приложения.
5. Обновления: `sudo ./deploy/update.sh` (бэкап → pull → сборка → проверка, при неудаче откат образа).
   Бэкап вручную: `./scripts/backup.sh`, восстановление: `./scripts/restore.sh backups/daily/<время>`.

## Безопасность в двух словах

JWT одноразовые и короткие, подпись проверяется по секрету приложения (`alg=none` отклоняется); данные приложений
изолированы по `app_id` (404 на чужие диалоги); секреты приложения шифруются, серверный токен хранится как sha256;
медиа отдаётся по подписанным временным ссылкам с `nosniff` и `sandbox`, svg/html/exe не принимаются;
куки и CSRF нет; лимиты запросов на вход, отправку и серверный API.

## Известные ограничения

Нет интерфейса создания групп (только через API); отправка push на мобильные устройства не реализована
(токены устройств принимаются и хранятся); CORS не включён — браузеры работают через iframe с того же origin;
удалённые вложения нельзя «показать» оператору.
