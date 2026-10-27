#!/usr/bin/env bash
# Восстановление БД и файлов: ./scripts/restore.sh backups/daily/<время>
set -euo pipefail
cd "$(dirname "$0")/.."
dir="${1:?укажите каталог бэкапа}"
read -r -p "Заменить БД и файлы данными из $dir? [yes/N] " a
[[ "$a" == "yes" ]] || exit 1

gunzip -c "$dir/db.sql.gz" | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
docker run --rm -v litewms_chat_storage:/s -v "$PWD/$dir":/b alpine sh -c 'rm -rf /s/* && tar xzf /b/storage.tgz -C /s'
echo "restored"
