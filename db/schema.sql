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

-- Должности: фиксированный список (ниже), новые должности добавлять нельзя.
-- Администратор может менять только maxday (страница "Должности").
-- Лимиты дней по месяцам (jan..dec) и уже занятые дни ("janEmp"..).
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
    -- ФИО делится на слова: fam - первое, name - второе, otch - все остальные (может быть пустой строкой)
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
    comment   text,
    -- Полное ФИО как в загруженном списке (любое число слов, регистр как в файле).
    -- У записей, созданных до появления колонки, NULL - тогда ФИО собирается из fam/name/otch.
    fio       text,
    -- Часть введена администратором (без ограничений; дни НЕ входят в счётчики должности)
    admin1    boolean NOT NULL DEFAULT false,
    admin2    boolean NOT NULL DEFAULT false,
    admin3    boolean NOT NULL DEFAULT false
);
CREATE INDEX IF NOT EXISTS employees_position_idx ON employees (position);

-- Один человек (ФИО + должность) - одна запись. Приложение и так создаёт запись под блокировкой
-- (findOrCreateEmployee), индекс - последний рубеж защиты от дублей на уровне БД.
CREATE UNIQUE INDEX IF NOT EXISTS employees_person_uniq ON employees (fam, name, otch, position);

-- Список сотрудников (ФИО + должность). Загружается администратором из Excel и
-- ПОЛНОСТЬЮ заменяется при каждой загрузке. Должность - всегда из таблицы positions.
CREATE TABLE IF NOT EXISTS cur_emp (
    id       bigserial PRIMARY KEY,
    fio      text NOT NULL,
    position text NOT NULL,
    UNIQUE (fio, position)
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

-- Журнал попыток входа админа: защита от перебора пароля (см. security.php) и след «кто и когда входил».
CREATE TABLE IF NOT EXISTS login_attempts (
    id       bigserial PRIMARY KEY,
    username text        NOT NULL,
    ip       text        NOT NULL,
    success  boolean     NOT NULL,
    at       timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS login_attempts_user_ip_idx ON login_attempts (username, ip, at);
CREATE INDEX IF NOT EXISTS login_attempts_ip_idx ON login_attempts (ip, at);

-- Обязательная строка параметров. ГОД - ПРЕДПОЛОЖЕНИЕ (следующий календарный):
-- измените в админке (Настройки -> Год) или командой:
--   UPDATE params SET year = 2027 WHERE id = 1;
INSERT INTO params (id, year)
VALUES (1, EXTRACT(YEAR FROM CURRENT_DATE)::int + 1)
ON CONFLICT (id) DO NOTHING;

-- Фиксированный список должностей и максимальное число дней отпуска (maxday).
-- Лимиты по месяцам изначально 0 - администратор задаёт их на странице "Настройки".
INSERT INTO positions (position, maxday) VALUES
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
ON CONFLICT (position) DO NOTHING;
