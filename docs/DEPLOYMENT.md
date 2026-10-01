# Развёртывание на боевом сервере

Пошаговая инструкция: один VPS, Docker, автоматический HTTPS (Let's Encrypt), резервные копии. В примерах используются
`example.org` (домен) и `203.0.113.10` (IP сервера): замените их своими.

## Что получится

```
Интернет ──443/80──▶ Caddy (HTTPS, сертификат Let's Encrypt)
                       │  внутренняя сеть Docker (наружу не открыта)
                       ▼
                    app (Apache + PHP) ──▶ db (PostgreSQL)
```

Наружу открыты **только порты 80 и 443** (Caddy). Приложение и база доступны лишь внутри сети Docker.
Файл `docker-compose.prod.yml` описывает всю связку; параметры берутся из файла `.env` на сервере.

## Требования

- VPS с Ubuntu 22.04 или 24.04 (подойдёт 1 vCPU, 1–2 ГБ ОЗУ, 10+ ГБ диска) и публичным IPv4.
- Домен, DNS которого вы можете менять.
- Доступ к серверу по SSH с правами `sudo`.

## 1. DNS

В панели управления доменом создайте **A-запись**:

| Тип | Имя | Значение |
|---|---|---|
| A | `@` (сам домен) | `203.0.113.10` |

Дождитесь обновления (обычно минуты, иногда до нескольких часов) и проверьте, что домен указывает на сервер:

```bash
dig +short example.org        # должен вывести IP вашего сервера
```

> Сертификат можно получить только когда DNS уже указывает на сервер, а порты 80 и 443 доступны из интернета.

## 2. Подготовка сервера

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install git ufw

# Межсетевой экран: SSH, HTTP, HTTPS
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp        # HTTP/3
sudo ufw enable
```

Установка Docker (официальный скрипт, включает плагин `docker compose`):

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"   # затем выйдите из SSH и зайдите снова
docker compose version            # проверка
```

> Docker сам открывает опубликованные порты в обход `ufw`. В `docker-compose.prod.yml` опубликованы только 80 и 443,
> порты базы и приложения не публикуются — не добавляйте им `ports:`.

## 3. Код

```bash
sudo mkdir -p /opt/vacation && sudo chown "$USER": /opt/vacation
git clone <адрес-репозитория> /opt/vacation
cd /opt/vacation
```

Для закрытого репозитория используйте deploy-ключ (`ssh-keygen`, затем добавьте публичный ключ в настройки репозитория
как read-only deploy key) или персональный токен доступа.

## 4. Настройка `.env`

```bash
cp .env.example .env
nano .env
```

Раскомментируйте и задайте в разделе «Боевой сервер»:

```ini
DOMAIN=example.org
ACME_EMAIL=admin@example.org
COMPOSE_FILE=docker-compose.prod.yml
```

И **замените** значение уже существующей строки `DB_PASSWORD=` в разделе «База данных» на длинный случайный пароль
(сгенерировать: `openssl rand -base64 24 | tr -d '/+='`). Не добавляйте вторую строку `DB_PASSWORD`. Остальные значения из раздела
«База данных» (`DB_DATABASE`, `DB_USERNAME`) оставьте как есть. Файл `.env` содержит секреты: он в `.gitignore`, не публикуйте его.

```bash
chmod 600 .env
```

## 5. Запуск

```bash
docker compose up -d --build
docker compose ps                  # все три сервиса: Up / healthy
docker compose logs -f caddy       # дождитесь строки "certificate obtained successfully" (Ctrl+C - выйти)
```

При первом запуске создаётся схема БД (`db/schema.sql`). Откройте `https://example.org/login.php`: должна появиться страница входа
с «замочком» в адресной строке. Заходить по `http://` не нужно: Caddy перенаправит на HTTPS.

## 6. Администратор

Демо-учётки на боевом сервере нет. Создайте свою (пароль **от 12 символов**, не логин, не распространённое слово):

```bash
read -rs -p "Пароль: " ADMIN_PASSWORD; echo
docker compose exec -e ADMIN_PASSWORD="$ADMIN_PASSWORD" app php bin/create-admin.php <логин>
unset ADMIN_PASSWORD
```

Такой способ не оставляет пароль в истории команд. Сменить пароль позже можно той же командой.

## 7. Первичная настройка в админке

1. **Настройки**: год отпусков и лимиты дней по месяцам для каждой должности.
2. **Должности**: при необходимости измените максимальное число дней отпуска (`maxday`).
3. **Сотрудники**: загрузите Excel со списком (столбец A — ФИО, столбец B — должность).
4. Сообщите сотрудникам адрес сайта: вход выполняется через `https://example.org/sign.php`.

## 7а. Проверка защиты

После запуска прогоните проверку (она ничего не меняет в данных):

```bash
docker compose exec app php tests/security/http-check.php \
  --base=https://example.org --user=<логин> --pass='<пароль>' --expect-production --check-demo
```

Все проверки должны быть пройдены. Если из контейнера домен недоступен (особенность сети хостинга), запустите ту же команду
с любого компьютера, где есть PHP. Подробнее о защите — [SECURITY.md](SECURITY.md). Целостность данных:

```bash
docker compose exec app php bin/check-consistency.php
```

## 8. Резервные копии

Скрипт `deploy/backup.sh` (запускается через `bash`, бит исполнения не нужен) делает дамп БД в каталог `backups/`, проверяет, что дамп читается, и удаляет копии старше 14 дней. Сбой во время копирования не затрагивает ранее сделанные копии. Добавьте его в cron:

```bash
crontab -e
# ежедневно в 03:00
0 3 * * * cd /opt/vacation && bash deploy/backup.sh >> backups/backup.log 2>&1
```

Копии лежат на том же сервере, поэтому **регулярно копируйте их в другое место** (`scp`, `rsync`, облачное хранилище).
Проверьте работу вручную: `bash deploy/backup.sh && ls -lh backups`.

**Восстановление** (заменяет текущие данные!):

```bash
docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner' < backups/vacation-ГГГГММДД-ЧЧММСС.dump
docker compose exec app php bin/check-consistency.php
```

## 9. Обновление

```bash
cd /opt/vacation
bash deploy/update.sh
```

Скрипт: делает резервную копию → `git pull` → пересобирает образы → перезапускает → применяет новые миграции БД
(`bin/migrate.php`, каждая миграция выполняется один раз). После обновления откройте сайт и проверьте вход.
Если что-то пошло не так, восстановите данные из копии (п. 8) и вернитесь на предыдущую версию: `git checkout <коммит>` и
`docker compose up -d --build`.

## 10. Обслуживание и диагностика

| Задача | Команда |
|---|---|
| Состояние сервисов | `docker compose ps` |
| Логи (все / только приложения / Caddy) | `docker compose logs -f` / `... app` / `... caddy` |
| Перезапуск | `docker compose restart` |
| Журнал входов администратора | `docker compose exec app php bin/admin-security.php logins` |
| Снять блокировку входа (если заблокировали себя паролем) | `docker compose exec app php bin/admin-security.php unlock` |
| Место на диске | `df -h` и `docker system df` |
| Очистка неиспользуемых образов | `docker image prune -f` |

**Не выдаётся сертификат.** Проверьте: DNS указывает на сервер (`dig +short example.org`); порты 80 и 443 открыты в `ufw` и в панели
хостинга; в `docker compose logs caddy` видна причина. У Let's Encrypt есть лимиты на повторные выпуски, поэтому не удаляйте том
`caddy_data` без необходимости.

**Страница входа пишет «Вход возможен только по защищённому соединению».** Приложение не видит HTTPS: обращаются к нему не через Caddy
(например, по IP и порту контейнера) или не передаётся `X-Forwarded-Proto`. Используйте адрес `https://<домен>`.

**Пароль слишком простой.** В боевом режиме принимаются только пароли от 12 символов; задайте новый командой из п. 6.

## 11. Рекомендации по защите сервера

- Вход по SSH только по ключам (`PasswordAuthentication no` в `/etc/ssh/sshd_config`), отдельный пользователь вместо `root`.
- Автоматические обновления безопасности: `sudo apt -y install unattended-upgrades`.
- По желанию `fail2ban` для SSH.
- Не открывайте порт базы данных наружу и не включайте `ports:` у сервисов `db` и `app`.
- Храните резервные копии вне сервера и периодически проверяйте восстановление.

## Перенос данных со старой установки

Если нужно перенести существующую БД: сделайте `pg_dump -Fc` на старом сервере, запустите проект по шагам 1–5 (создаётся пустая БД),
затем восстановите дамп командой из п. 8 и выполните `docker compose exec app php bin/migrate.php`.
Если миграции из `db/migrations/` уже выполнялись вручную, один раз выполните `docker compose exec app php bin/migrate.php --baseline`,
чтобы отметить их применёнными. Затем запустите `bin/check-consistency.php`.
