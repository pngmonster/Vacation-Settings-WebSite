-- =====================================================================
-- Миграция 003 для СУЩЕСТВУЮЩЕЙ (боевой) БД: защита входа администратора от перебора пароля.
-- На новой БД не нужна (таблица уже есть в schema.sql).
--
--   - login_attempts: журнал попыток входа (блокировка после серии неудач + след «кто и когда входил»)
--
-- ПЕРЕД ЗАПУСКОМ СДЕЛАЙТЕ БЭКАП:  pg_dump -Fc <db> > backup.dump
-- Запуск:  psql -U <user> -d <db> -v ON_ERROR_STOP=1 -f db/migrations/003_admin_security.sql
-- Скрипт можно запускать повторно. Таблица пользователей (users) не меняется.
-- =====================================================================
BEGIN;

CREATE TABLE IF NOT EXISTS login_attempts (
    id       bigserial PRIMARY KEY,
    username text        NOT NULL,
    ip       text        NOT NULL,
    success  boolean     NOT NULL,
    at       timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS login_attempts_user_ip_idx ON login_attempts (username, ip, at);
CREATE INDEX IF NOT EXISTS login_attempts_ip_idx ON login_attempts (ip, at);

COMMIT;
