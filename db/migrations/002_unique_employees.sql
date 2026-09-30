-- =====================================================================
-- Миграция 002 для СУЩЕСТВУЮЩЕЙ (боевой) БД: запрет дублей сотрудников.
-- На новой БД не нужна (индекс уже есть в schema.sql).
--
-- До исправления гонок двойной клик на «Войти» или вход с двух устройств мог создать
-- ДВЕ записи одного человека. Скрипт сначала проверяет, есть ли такие дубли:
--   - дублей нет -> создаётся уникальный индекс;
--   - дубли есть -> скрипт останавливается с сообщением (ничего не меняется),
--     дубли нужно разобрать вручную (см. ниже), потом запустить скрипт снова.
--
-- ПЕРЕД ЗАПУСКОМ СДЕЛАЙТЕ БЭКАП:  pg_dump -Fc <db> > backup.dump
-- Запуск:  psql -U <user> -d <db> -v ON_ERROR_STOP=1 -f db/migrations/002_unique_employees.sql
--
-- Как посмотреть дубли:
--   SELECT fam, name, otch, position, count(*) AS записей, string_agg(id::text, ', ' ORDER BY id) AS id,
--          string_agg((lenght1 + lenght2 + lenght3)::text, ', ' ORDER BY id) AS дней_в_каждой
--   FROM employees GROUP BY fam, name, otch, position HAVING count(*) > 1;
-- Обычно в одной из записей отпуск заполнен, в другой пусто - пустую можно удалить:
--   DELETE FROM employees WHERE id = <id пустой записи>;   -- у пустой записи счётчики должности не менялись
-- Если заполнены обе - решите, какую оставить. Удаляя запись С ДНЯМИ, через админку (Отчёт -> корзина),
-- а не SQL: так дни вернутся в счётчики должности.
-- =====================================================================
DO $$
DECLARE
    dup_count integer;
BEGIN
    SELECT count(*) INTO dup_count FROM (
        SELECT 1 FROM employees GROUP BY fam, name, otch, position HAVING count(*) > 1
    ) d;

    IF dup_count > 0 THEN
        RAISE EXCEPTION 'Найдено дублей сотрудников: % (один человек в нескольких записях). Разберите их (запрос в начале файла) и запустите скрипт снова.', dup_count;
    END IF;

    CREATE UNIQUE INDEX IF NOT EXISTS employees_person_uniq ON employees (fam, name, otch, position);
END $$;
