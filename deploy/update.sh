#!/usr/bin/env bash
# Обновление боевого сервера: копия БД -> новый код из git -> пересборка -> миграции.
#
#   bash deploy/update.sh
set -euo pipefail
cd "$(dirname "$0")/.."
export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

echo "== 1/5 резервная копия БД"
bash ./deploy/backup.sh

echo "== 2/5 новый код (git pull)"
git pull --ff-only

echo "== 3/5 пересборка и перезапуск"
docker compose build
docker compose up -d

echo "== 4/5 миграции БД"
for i in $(seq 1 20); do
    docker compose exec -T app php bin/migrate.php && break
    echo "  приложение ещё запускается, повтор через 3 с..."; sleep 3
done

echo "== 5/5 состояние"
docker compose ps
echo "Готово. Откройте сайт и проверьте вход."
