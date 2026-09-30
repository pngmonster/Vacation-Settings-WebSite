-- =====================================================================
-- Схема БД проекта Vacation-Settings-WebSite (PostgreSQL 12+)
--
-- ВОССТАНОВЛЕНА ПО КОДУ приложения, а не выгружена из боевой БД.
-- Что здесь выведено из кода, а что предположение - см. README.md, раздел
-- "База данных". Если у вас есть pg_dump --schema-only боевой БД, сверьте
-- его с этим файлом.
--
-- ВАЖНО: Eloquent оборачивает имена колонок в двойные кавычки, поэтому
-- колонки в смешанном регистре ("janEmp", "isReady") ОБЯЗАТЕЛЬНО создаются
-- в кавычках - иначе запросы приложения не найдут их.
-- =====================================================================

-- Должности: лимиты дней по месяцам (jan..dec) и уже занятые дни ("janEmp"..).
-- maxday - сколько дней отпуска положено сотруднику этой должности.
CREATE TABLE IF NOT EXISTS positions (
    position text PRIMARY KEY,
    maxday   integer NOT NULL DEFAULT 0,
    jan      integer NOT NULL DEFAULT 0,
    feb      integer NOT NULL DEFAULT 0,
    mar      integer NOT NULL DEFAULT 0,
    apr      integer NOT NULL DEFAULT 0,
    may      integer NOT NULL DEFAULT 0,
    jun      integer NOT NULL DEFAULT 0,
    jul      integer NOT NULL DEFAULT 0,
    aug      integer NOT NULL DEFAULT 0,
    sep      integer NOT NULL DEFAULT 0,
    oct      integer NOT NULL DEFAULT 0,
    nov      integer NOT NULL DEFAULT 0,
    dec      integer NOT NULL DEFAULT 0,
    "janEmp" integer NOT NULL DEFAULT 0,
    "febEmp" integer NOT NULL DEFAULT 0,
    "marEmp" integer NOT NULL DEFAULT 0,
    "aprEmp" integer NOT NULL DEFAULT 0,
    "mayEmp" integer NOT NULL DEFAULT 0,
    "junEmp" integer NOT NULL DEFAULT 0,
    "julEmp" integer NOT NULL DEFAULT 0,
    "augEmp" integer NOT NULL DEFAULT 0,
    "sepEmp" integer NOT NULL DEFAULT 0,
    "octEmp" integer NOT NULL DEFAULT 0,
    "novEmp" integer NOT NULL DEFAULT 0,
    "decEmp" integer NOT NULL DEFAULT 0
);

-- Сотрудники, начавшие заполнение. Части отпуска 1..3:
-- monN - месяц начала (1..12, 0 = не выбрано), dayN - число, lenghtN - длительность (0 = не выбрано).
-- Название lenght (с опечаткой) - как в коде, менять нельзя.
CREATE TABLE IF NOT EXISTS employees (
    id        bigserial PRIMARY KEY,
    fam       text    NOT NULL,
    name      text    NOT NULL,
    otch      text    NOT NULL,
    position  text    NOT NULL,
    mon1      integer NOT NULL DEFAULT 0,
    day1      integer NOT NULL DEFAULT 0,
    lenght1   integer NOT NULL DEFAULT 0,
    mon2      integer NOT NULL DEFAULT 0,
    day2      integer NOT NULL DEFAULT 0,
    lenght2   integer NOT NULL DEFAULT 0,
    mon3      integer NOT NULL DEFAULT 0,
    day3      integer NOT NULL DEFAULT 0,
    lenght3   integer NOT NULL DEFAULT 0,
    "isReady" boolean NOT NULL DEFAULT false,
    comment   text
);
CREATE INDEX IF NOT EXISTS employees_position_idx ON employees (position);

-- Необязательно (в коде sign.php запись ищется по ФИО+должности через first(),
-- то есть дубликаты не предполагаются). Раскомментируйте, если хотите, чтобы
-- БД сама гарантировала уникальность:
-- CREATE UNIQUE INDEX IF NOT EXISTS employees_person_uniq ON employees (fam, name, otch, position);

-- Список ФИО для выпадающего списка на странице входа ("Фамилия Имя Отчество").
CREATE TABLE IF NOT EXISTS cur_emp (
    fio text PRIMARY KEY
);

-- Параметры. Приложение всегда читает строку с id = 1 (Params::find(1)).
CREATE TABLE IF NOT EXISTS params (
    id   integer PRIMARY KEY,
    year integer NOT NULL
);

-- Администраторы. role = 'admin' даёт доступ к index/report/clear/downloadExcel.
CREATE TABLE IF NOT EXISTS users (
    id       bigserial PRIMARY KEY,
    username text NOT NULL UNIQUE,
    password text NOT NULL,   -- password_hash(), см. bin/create-admin.php
    role     text NOT NULL
);

-- Обязательная строка параметров. ГОД - ПРЕДПОЛОЖЕНИЕ (следующий календарный):
-- измените в админке (Настройки -> Год) или командой:
--   UPDATE params SET year = 2027 WHERE id = 1;
INSERT INTO params (id, year)
VALUES (1, EXTRACT(YEAR FROM CURRENT_DATE)::int + 1)
ON CONFLICT (id) DO NOTHING;
