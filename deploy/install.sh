#!/usr/bin/env bash
#
# Первая установка (и безопасный повторный запуск) litewms_chat на сервере.
#   cd /opt/litewms_chat && sudo ./deploy/install.sh
#
# Что делает: создаёт .env с новыми секретами (существующий не трогает), собирает образ, поднимает стек,
# ждёт healthcheck, генерирует VAPID-ключи, создаёт демо-приложение чата, если приложений ещё нет.
# Что НЕ делает: не трогает Caddy, DNS и другие проекты сервера — это отдельные шаги (см. README, «Деплой»).
set -euo pipefail
cd "$(dirname "$0")/.."

DOMAIN="${CHAT_DOMAIN:-chat.litewms.ru}"
ADMIN_EMAIL="${CHAT_ADMIN_EMAIL:-admin@litewms.ru}"

command -v docker >/dev/null || { echo "Нужен docker" >&2; exit 1; }
docker compose version >/dev/null || { echo "Нужен docker compose v2" >&2; exit 1; }

set_env() { # set_env KEY VALUE — задать, только если пусто
    local key="$1" val="$2"
    if grep -qE "^${key}=.+$" .env; then return; fi
    if grep -qE "^${key}=" .env; then sed -i "s|^${key}=.*|${key}=${val}|" .env; else echo "${key}=${val}" >> .env; fi
}
force_env() { sed -i "s|^$1=.*|$1=$2|" .env; }

if [[ ! -f .env ]]; then
    cp .env.example .env
    echo "==> Создан .env"
fi

set_env APP_KEY "base64:$(openssl rand -base64 32)"
set_env DB_PASSWORD "$(openssl rand -hex 16)"
set_env DB_ROOT_PASSWORD "$(openssl rand -hex 16)"
set_env REVERB_APP_KEY "$(openssl rand -hex 16)"
set_env REVERB_APP_SECRET "$(openssl rand -hex 24)"
force_env APP_URL "https://${DOMAIN}"
force_env REVERB_CLIENT_HOST "${DOMAIN}"
force_env VAPID_SUBJECT "mailto:${ADMIN_EMAIL}"
chmod 600 .env

echo "==> Сборка образа"
docker compose build

echo "==> Запуск"
docker compose up -d

echo "==> Ждём готовности app (миграции идут при старте)"
for i in $(seq 1 60); do
    st="$(docker inspect -f '{{.State.Health.Status}}' litewms_chat 2>/dev/null || echo none)"
    [[ "$st" == "healthy" ]] && break
    sleep 3
done
[[ "$st" == "healthy" ]] || { echo "app не стал healthy: docker compose logs app" >&2; exit 1; }

if ! grep -qE '^VAPID_PUBLIC_KEY=.+' .env; then
    echo "==> VAPID-ключи"
    keys="$(docker compose exec -T app php artisan chat:vapid)"
    force_env VAPID_PUBLIC_KEY "$(echo "$keys" | sed -n 's/^VAPID_PUBLIC_KEY=//p' | tr -d '\r')"
    force_env VAPID_PRIVATE_KEY "$(echo "$keys" | sed -n 's/^VAPID_PRIVATE_KEY=//p' | tr -d '\r')"
    docker compose up -d --force-recreate app reverb worker scheduler
fi

echo
echo "Готово. Контейнеры: litewms_chat, litewms_chat_reverb, litewms_chat_worker, litewms_chat_scheduler, litewms_chat_percona, litewms_chat_redis"
echo "Сеть: litewms_chat_net (подключите к ней Caddy и добавьте deploy/litewms_chat.caddy в sites.d)."
echo "Первое приложение чата:"
echo "  docker compose exec app php artisan chat:app 'ЛК LiteWMS' --origin=https://litewms.ru --origin=https://test.litewms.ru --guests"
