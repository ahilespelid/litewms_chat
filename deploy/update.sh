#!/usr/bin/env bash
#
# Обновление: git pull -> бэкап БД -> сборка -> перезапуск -> проверка. При неудаче откат на прошлый образ.
#   cd /opt/litewms_chat && sudo ./deploy/update.sh
set -euo pipefail
cd "$(dirname "$0")/.."

PREV_IMAGE="$(docker inspect -f '{{.Image}}' litewms_chat 2>/dev/null || true)"
PREV_COMMIT="$(git rev-parse HEAD)"

./scripts/backup.sh

git pull --ff-only
docker tag litewms_chat:latest litewms_chat:previous 2>/dev/null || true

docker compose build
docker compose up -d

for i in $(seq 1 60); do
    st="$(docker inspect -f '{{.State.Health.Status}}' litewms_chat 2>/dev/null || echo none)"
    [[ "$st" == "healthy" ]] && { echo "OK: $(git rev-parse --short HEAD)"; exit 0; }
    sleep 3
done

echo "Новая версия не поднялась — откат на $PREV_COMMIT" >&2
git reset --hard "$PREV_COMMIT"
if docker image inspect litewms_chat:previous >/dev/null 2>&1; then
    IMAGE_TAG=previous docker compose up -d
fi
echo "Миграции не откатываются автоматически: БД можно вернуть из backups/ (scripts/restore.sh)." >&2
exit 1
