#!/usr/bin/env bash
# Резервная копия БД в ./backups (формат pg_dump -Fc). Старые копии (старше 14 дней) удаляются.
#
#   bash deploy/backup.sh
#   Ежедневно в 03:00 (crontab -e):   0 3 * * * cd /opt/vacation && bash deploy/backup.sh >> backups/backup.log 2>&1
#
# Копии лежат на том же сервере: периодически копируйте их в другое место (scp / rsync / облако).
set -euo pipefail
cd "$(dirname "$0")/.."
export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

KEEP_DAYS="${KEEP_DAYS:-14}"
mkdir -p backups
chmod 700 backups
file="backups/vacation-$(date +%Y%m%d-%H%M%S).dump"
tmp="$file.partial"
# Пишем во временный файл: сбой на середине не затрёт и не испортит уже сделанные копии
trap 'rm -f "$tmp"' EXIT

# имя БД и пользователя берутся из окружения контейнера, пароль не нужен
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -Fc "$POSTGRES_DB"' > "$tmp"

if [ ! -s "$tmp" ]; then
    echo "ОШИБКА: копия пуста" >&2
    exit 1
fi
# дамп должен читаться (не оборван)
if ! docker compose exec -T db pg_restore -l < "$tmp" > /dev/null; then
    echo "ОШИБКА: копия повреждена" >&2
    exit 1
fi
chmod 600 "$tmp"
mv "$tmp" "$file"
find backups -name 'vacation-*.dump' -mtime +"$KEEP_DAYS" -delete
echo "$(date '+%F %T') копия создана: $file ($(du -h "$file" | cut -f1))"
