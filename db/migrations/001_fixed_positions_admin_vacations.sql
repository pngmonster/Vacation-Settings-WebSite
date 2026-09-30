-- =====================================================================
-- Миграция 001 для СУЩЕСТВУЮЩЕЙ (боевой) БД.
-- На новой БД не нужна: там всё уже есть в schema.sql.
--
--   1. Фиксированный список должностей и maxday
--   2. employees: полное ФИО (fio) и флаги "введено администратором" (admin1..3)
--   3. cur_emp: теперь ФИО + должность (старая таблица сохраняется как cur_emp_old)
--
-- ПЕРЕД ЗАПУСКОМ СДЕЛАЙТЕ БЭКАП:  pg_dump -Fc <db> > backup.dump
-- Запуск:  psql -U <user> -d <db> -v ON_ERROR_STOP=1 -f db/migrations/001_fixed_positions_admin_vacations.sql
-- Скрипт можно запускать повторно.
-- =====================================================================
BEGIN;

-- 1. Должности. Для уже существующих обновляется ТОЛЬКО maxday, лимиты по месяцам и
--    счётчики не трогаются. Отсутствующие добавляются с нулевыми лимитами.
--    Должности, которых нет в списке, НЕ удаляются (см. запрос проверки в конце).
UPDATE positions p SET maxday = v.maxday
FROM (VALUES
    ('Врач СМП', 42),
    ('Врач АиР', 48),
    ('Врач педиатр', 42),
    ('Врач психиатр', 65),
    ('Фельдшер СМП', 42),
    ('Фельдшер ВБ(диализ)', 42),
    ('Фельдшер АиР', 48),
    ('Фельдшер психиатр.', 65),
    ('Фельдшер ППВ', 42),
    ('Медсестра ВБ', 42),
    ('Медсестра АиР', 48),
    ('Санитар', 65),
    ('Уборщик', 28),
    ('Подсобный рабочий', 28),
    ('Старший врач', 42),
    ('Старший фельдшер', 42),
    ('Зав.хозяйством', 33),
    ('Дезинфектор', 33)
) AS v(position, maxday)
WHERE p.position = v.position;

INSERT INTO positions (position, maxday, jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,dec,"janEmp","febEmp","marEmp","aprEmp","mayEmp","junEmp","julEmp","augEmp","sepEmp","octEmp","novEmp","decEmp")
SELECT v.position, v.maxday, 0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0
FROM (VALUES
    ('Врач СМП', 42),
    ('Врач АиР', 48),
    ('Врач педиатр', 42),
    ('Врач психиатр', 65),
    ('Фельдшер СМП', 42),
    ('Фельдшер ВБ(диализ)', 42),
    ('Фельдшер АиР', 48),
    ('Фельдшер психиатр.', 65),
    ('Фельдшер ППВ', 42),
    ('Медсестра ВБ', 42),
    ('Медсестра АиР', 48),
    ('Санитар', 65),
    ('Уборщик', 28),
    ('Подсобный рабочий', 28),
    ('Старший врач', 42),
    ('Старший фельдшер', 42),
    ('Зав.хозяйством', 33),
    ('Дезинфектор', 33)
) AS v(position, maxday)
WHERE NOT EXISTS (SELECT 1 FROM positions p WHERE p.position = v.position);

-- 2. employees
ALTER TABLE employees ADD COLUMN IF NOT EXISTS fio    text;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS admin1 boolean NOT NULL DEFAULT false;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS admin2 boolean NOT NULL DEFAULT false;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS admin3 boolean NOT NULL DEFAULT false;

-- 3. cur_emp: если ещё нет колонки position - старая таблица переименовывается, создаётся новая
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_schema = current_schema() AND table_name = 'cur_emp' AND column_name = 'position') THEN
        IF EXISTS (SELECT 1 FROM information_schema.tables
                   WHERE table_schema = current_schema() AND table_name = 'cur_emp') THEN
            ALTER TABLE cur_emp RENAME TO cur_emp_old;
        END IF;
        CREATE TABLE cur_emp (
            id       bigserial PRIMARY KEY,
            fio      text NOT NULL,
            position text NOT NULL,
            UNIQUE (fio, position)
        );
    END IF;
END $$;

COMMIT;

-- Проверка: должности в БД, которых нет в фиксированном списке (обычно пусто).
-- Они останутся в БД и будут видны на странице "Должности" - решите, что с ними делать.
SELECT position AS "лишняя должность (нет в списке)" FROM positions
WHERE position NOT IN ('Врач СМП', 'Врач АиР', 'Врач педиатр', 'Врач психиатр', 'Фельдшер СМП', 'Фельдшер ВБ(диализ)', 'Фельдшер АиР', 'Фельдшер психиатр.', 'Фельдшер ППВ', 'Медсестра ВБ', 'Медсестра АиР', 'Санитар', 'Уборщик', 'Подсобный рабочий', 'Старший врач', 'Старший фельдшер', 'Зав.хозяйством', 'Дезинфектор');

-- После миграции загрузите список сотрудников из Excel в админке (страница "Сотрудники").
