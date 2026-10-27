#!/usr/bin/env bash
# Дамп БД + файлы (голосовые и вложения) в ./backups/daily/<время>, хранится 14 копий.
set -euo pipefail
cd "$(dirname "$0")/.."

dir="backups/daily/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$dir"

docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' | gzip > "$dir/db.sql.gz"
docker run --rm -v litewms_chat_storage:/s:ro -v "$PWD/$dir":/b alpine tar czf /b/storage.tgz -C /s .

ls -1dt backups/daily/* | tail -n +15 | xargs -r rm -rf
echo "backup: $dir"
