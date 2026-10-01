<?php
/**
 * Проверка логики частей отпуска (savePart / resetPart).
 *
 *   php tests/smoke.php                      (локально)
 *   docker compose exec app php tests/smoke.php
 *
 * Все данные создаются внутри транзакции и в конце откатываются:
 * содержимое БД (включая демо-данные) не меняется.
 */
require __DIR__ . '/../functions.php';

use Illuminate\Database\Capsule\Manager as DB;

$y = 2030; // тестовый год, не зависит от params
$failed = 0; $total = 0;

function check($name, $cond, $extra = '')
{
    global $failed, $total;
    $total++;
    if ($cond) { echo "  ok    $name\n"; }
    else       { echo "  FAIL  $name $extra\n"; $failed++; }
}

// Счётчики занятых дней тестовой должности: ['jan' => 14, 'feb' => 2, ...] (только ненулевые)
function counters($pos)
{
    global $monthsToStr;
    $p = \Models\Position::where('position', $pos)->first();
    $r = [];
    foreach ($monthsToStr as $m) {
        if ($p->{$m . 'Emp'} != 0) { $r[$m] = (int)$p->{$m . 'Emp'}; }
    }
    return $r;
}

function newEmployee($pos, $i)
{
    return \Models\Employees::create(['fam' => "тест$i", 'name' => 'т', 'otch' => 'т', 'position' => $pos]);
}

DB::connection()->beginTransaction();

$pos = 'ТЕСТ-ДОЛЖНОСТЬ';
$months = array_fill_keys(['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'], 100);
\Models\Position::create(array_merge(['position' => $pos], $months));
\Models\Position::where('position', $pos)->update(['maxday' => 28]);

// ---------- Пересечение частей ----------
echo "Пересечение частей:\n";
$e = newEmployee($pos, 1);

$r = savePart($e->id, 1, 'jan', 2, 14, $y);                         // 2-15 янв
check('часть 1: 2 янв + 14 дн сохраняется', $r['ok']);
check('счётчик jan = 14', counters($pos) === ['jan' => 14], json_encode(counters($pos)));

$r = savePart($e->id, 2, 'jan', 12, 5, $y);                        // 12-16 янв: пересекается
check('часть 2: 12-16 янв пересекается с 2-15 янв -> отказ', !$r['ok'] && $r['errors'] === ['Даты пересекаются с другой частью отпуска'], json_encode($r));
check('при отказе счётчики не изменились', counters($pos) === ['jan' => 14]);

$r = savePart($e->id, 2, 'jan', 15, 3, $y);                        // 15-17: пересечение по последнему дню (15 янв)
check('пересечение по одному общему дню (15 янв) -> отказ', !$r['ok']);

$r = savePart($e->id, 2, 'jan', 16, 3, $y);                        // 16-18: впритык, не пересекается
check('часть 2: 16-18 янв (вплотную после 15 янв) сохраняется', $r['ok'], json_encode($r));

$r = savePart($e->id, 3, 'jan', 1, 2, $y);                         // 1-2: пересекается по 2 янв
check('часть 3: 1-2 янв пересекается по 2 янв -> отказ', !$r['ok']);
$r = savePart($e->id, 3, 'jan', 1, 1, $y);                         // 1: впритык перед 2 янв
check('часть 3: 1 янв (вплотную перед 2 янв) сохраняется', $r['ok'], json_encode($r));
check('сумма счётчиков = 14+3+1', counters($pos) === ['jan' => 18], json_encode(counters($pos)));

// ---------- Сброс возвращает дни ----------
echo "Сброс:\n";
$r = resetPart($e->id, 2, $y);
check('сброс части 2 успешен', $r['ok']);
check('счётчик уменьшился на 3', counters($pos) === ['jan' => 15], json_encode(counters($pos)));

// ---------- Пересечение через границу месяца ----------
echo "Граница месяца:\n";
$e2 = newEmployee($pos, 2);
$r = savePart($e2->id, 1, 'jan', 20, 14, $y);                      // 20 янв - 2 фев
check('20 янв + 14 дн -> jan +12, feb +2', $r['ok'] && counters($pos) === ['jan' => 27, 'feb' => 2], json_encode(counters($pos)));
$r = savePart($e2->id, 2, 'feb', 2, 2, $y);                        // 2-3 фев: пересечение по 2 фев
check('пересечение по 2 фев -> отказ', !$r['ok']);
$r = savePart($e2->id, 2, 'feb', 3, 2, $y);
check('3-4 фев (вплотную) сохраняется', $r['ok']);

// ---------- Переход через год ----------
echo "Конец года:\n";
$e3 = newEmployee($pos, 3);
$r = savePart($e3->id, 1, 'dec', 20, 21, $y);
check('20 дек + 21 дн -> dec +12, jan +9 (как в прежней логике)', $r['ok'] && (counters($pos)['dec'] ?? 0) === 12 && (counters($pos)['jan'] ?? 0) === 36, json_encode(counters($pos)));

// ---------- Подтверждённый отпуск нельзя менять ----------
echo "Подтверждённый отпуск:\n";
$e->update(['isReady' => true]);
$before = counters($pos);
$r = savePart($e->id, 2, 'mar', 1, 3, $y);
check('savePart для подтверждённого -> отказ', !$r['ok']);
$r2 = resetPart($e->id, 1, $y);
check('resetPart для подтверждённого -> отказ', !$r2['ok']);
check('счётчики не изменились', counters($pos) === $before);

// ---------- Некорректный ввод ----------
echo "Некорректный ввод:\n";
$e4 = newEmployee($pos, 4);
check('несуществующий месяц -> отказ', !savePart($e4->id, 1, 'xxx', 5, 14, $y)['ok']);
check('число 25 (в интерфейсе только 1-20) -> отказ', !savePart($e4->id, 1, 'mar', 25, 14, $y)['ok']);
check('число 0 -> отказ', !savePart($e4->id, 1, 'mar', 0, 14, $y)['ok']);
check('часть 1 короче 14 дней -> отказ', !savePart($e4->id, 1, 'mar', 5, 13, $y)['ok']);
check('часть 1 длиннее 21 дня (maxday 28) -> отказ', !savePart($e4->id, 1, 'mar', 5, 22, $y)['ok']);
check('несуществующий сотрудник -> отказ', !savePart(999999999, 1, 'mar', 5, 14, $y)['ok']);
check('год не задан -> отказ', !savePart($e4->id, 1, 'mar', 5, 14, 0)['ok']);

// ---------- ФИО из любого числа слов ----------
echo "ФИО:\n";
check('splitFio: 3 слова как раньше', splitFio('Иванов  Иван   Иванович') === ['иванов', 'иван', 'иванович']);
check('splitFio: 4 слова -> отчество из двух', splitFio('Гаджиев Магомед Али оглы') === ['гаджиев', 'магомед', 'али оглы']);
check('splitFio: 2 слова -> пустое отчество', splitFio('Ким Ён') === ['ким', 'ён', '']);
check('splitFio: неразрывный пробел', splitFio("Иванов\xC2\xA0Иван Иванович") === ['иванов', 'иван', 'иванович']);

// ---------- Ввод отпуска администратором ----------
echo "Ввод администратором:\n";
$ca = \Models\Cur_emp::create(['fio' => 'Тестов Админ Тестович Оглы', 'position' => $pos]);
$base = counters($pos);

$r = adminSavePart($ca, 1, "$y-03-10", 3, $y);        // 3 дня: меньше минимума части (14)
check('часть 1 админа: 3 дня (короче минимума 14) сохраняется', $r['ok'], json_encode($r));
$ea = findEmployeeForCur($ca);
check('сотрудник создан, fio сохранено как в списке', $ea && $ea->fio === 'Тестов Админ Тестович Оглы');
check('часть помечена admin1 и сохранена как 10.03, 3 дня', $ea->admin1 === true && (int)$ea->mon1 === 3 && (int)$ea->day1 === 10 && (int)$ea->lenght1 === 3);
check('дни админа НЕ попали в счётчики', counters($pos) === $base, json_encode(counters($pos)));

$r = adminSavePart($ca, 2, "$y-03-11", 200, $y);      // 200 дней (>maxday 28), пересекается с частью 1, день 11 (>20)
check('часть 2 админа: 200 дней, пересечение и число 11 - сохраняется без ограничений', $r['ok'], json_encode($r));
check('счётчики по-прежнему не изменились', counters($pos) === $base);

$r = adminSavePart($ca, 3, "$y-12-31", 366, $y);      // старт 31 декабря (в интерфейсе сотрудника только 1-20)
check('часть 3 админа: 31 декабря + 366 дней', $r['ok'], json_encode($r));

$ea = findEmployeeForCur($ca);
$ea->update(['isReady' => true]);
$r = adminSavePart($ca, 1, "$y-04-01", 5, $y);
check('админ может менять даже подтверждённый сотрудником отпуск', $r['ok']);

// сотрудник не может трогать части админа
echo "Сотрудник и части админа:\n";
$before = counters($pos);
$ea->update(['isReady' => false]);
$r = savePart($ea->id, 1, 'may', 5, 14, $y);
check('сотрудник не может перезаписать часть админа', !$r['ok'] && strpos($r['errors'][0], 'администратором') !== false, json_encode($r));
$r = resetPart($ea->id, 1, $y);
check('сотрудник не может сбросить часть админа', !$r['ok'] && strpos($r['errors'][0], 'администратором') !== false);
check('счётчики не изменились', counters($pos) === $before);

// ---------- админ поверх части, выбранной сотрудником ----------
echo "Админ поверх части сотрудника:\n";
$cb = \Models\Cur_emp::create(['fio' => 'Тестова Своя Часть', 'position' => $pos]);
$eb = findOrCreateEmployee($cb);
$c0 = counters($pos);
$r = savePart($eb->id, 1, 'jan', 2, 14, $y);
check('сотрудник выбрал 2 янв + 14 (счётчик jan +14)', $r['ok'] && (counters($pos)['jan'] ?? 0) === ($c0['jan'] ?? 0) + 14, json_encode(counters($pos)));
$r = adminSavePart($cb, 1, "$y-02-05", 30, $y);
$c1 = counters($pos);
check('админ заменил её: 14 дней возвращены в jan, февраль не изменился', $r['ok'] && ($c1['jan'] ?? 0) === ($c0['jan'] ?? 0) && ($c1['feb'] ?? 0) === ($c0['feb'] ?? 0), json_encode($c1));
$eb = findOrCreateEmployee($cb);
check('часть теперь помечена как введённая администратором', $eb->admin1 === true && (int)$eb->lenght1 === 30);

$r = adminResetPart($eb->id, 1, $y);
check('сброс части админа: счётчики не меняются (не уходят в минус)', $r['ok'] && counters($pos) === $c1, json_encode(counters($pos)));
$eb = findOrCreateEmployee($cb);
check('после сброса часть пуста и без пометки', (int)$eb->lenght1 === 0 && $eb->admin1 === false);

$savePart = savePart($eb->id, 2, 'apr', 3, 14, $y);
$cX = counters($pos);
$r = adminResetPart($eb->id, 2, $y);
check('админ сбрасывает часть, выбранную сотрудником: дни возвращаются', $r['ok'] && ($cX['apr'] ?? 0) - (counters($pos)['apr'] ?? 0) === 14, json_encode(counters($pos)));

// ---------- удаление сотрудника ----------
echo "Удаление сотрудника:\n";
$cc = \Models\Cur_emp::create(['fio' => 'Тестов Удаляемый', 'position' => $pos]);
$ec = findOrCreateEmployee($cc);
$c0 = counters($pos);
savePart($ec->id, 1, 'jun', 2, 14, $y);          // своя часть: учитывается в счётчиках
adminSavePart($cc, 2, "$y-06-20", 10, $y);        // часть админа: в счётчиках не учитывается
check('до удаления в счётчиках только своя часть (jun +14)', (counters($pos)['jun'] ?? 0) === ($c0['jun'] ?? 0) + 14, json_encode(counters($pos)));
check('deleteEmployee вернул true', deleteEmployee($ec->id, $y) === true);
check('после удаления счётчики вернулись к исходным (не ушли в минус)', counters($pos) === $c0, json_encode(counters($pos)) . ' vs ' . json_encode($c0));
check('запись сотрудника удалена', \Models\Employees::where('id', $ec->id)->doesntExist());
check('удаление несуществующего -> false', deleteEmployee(999999999, $y) === false);

// ---------- ввод администратора: технические проверки ----------
echo "Ввод администратора - ошибки ввода:\n";
check('дата не в году отпусков -> отказ', !adminSavePart($ca, 1, ($y + 1) . '-01-10', 10, $y)['ok']);
check('несуществующая дата 30 февраля -> отказ', !adminSavePart($ca, 1, "$y-02-30", 10, $y)['ok']);
check('пустая дата -> отказ', !adminSavePart($ca, 1, '', 10, $y)['ok']);
check('мусор вместо даты -> отказ', !adminSavePart($ca, 1, 'abc', 10, $y)['ok']);
check('0 дней -> отказ', !adminSavePart($ca, 1, "$y-05-01", 0, $y)['ok']);
check('367 дней -> отказ', !adminSavePart($ca, 1, "$y-05-01", 367, $y)['ok']);
check('часть 4 -> отказ', !adminSavePart($ca, 4, "$y-05-01", 5, $y)['ok']);

// ---------- Контроль заполнения ----------
echo "Контроль заполнения:\n";
function emp($l1, $l2, $l3, $ready) { return (object)['lenght1' => $l1, 'lenght2' => $l2, 'lenght3' => $l3, 'isReady' => $ready]; }

$st = vacationStatus(emp(14, 0, 0, false), 42);
check('14 из 42, не подтверждён -> не заполнено, обе причины', !$st['complete'] && count($st['reasons']) === 2 && $st['started'], json_encode($st, JSON_UNESCAPED_UNICODE));
$st = vacationStatus(emp(21, 21, 0, false), 42);
check('все 42 дня выбраны, но не нажал «Сохранить» -> не заполнено (только «не подтверждён»)', !$st['complete'] && $st['reasons'] === ['не подтверждён'], json_encode($st, JSON_UNESCAPED_UNICODE));
$st = vacationStatus(emp(21, 21, 0, true), 42);
check('42 из 42 и подтверждён -> заполнено', $st['complete'] && $st['label'] === 'Заполнено');
$st = vacationStatus(emp(21, 14, 0, true), 42);
check('подтверждён, но дней 35 из 42 (после правки админом/maxday) -> не заполнено', !$st['complete'] && $st['reasons'] === ['выбрано 35 из 42 дн.'], json_encode($st, JSON_UNESCAPED_UNICODE));
$st = vacationStatus(emp(30, 30, 20, true), 42);
check('админ поставил 80 из 42 (больше maxday) и подтверждён -> заполнено (меньше - нет)', $st['complete']);
$st = vacationStatus(emp(0, 0, 0, false), 42);
check('ничего не выбрано -> не заполнено и НЕ начал', !$st['complete'] && !$st['started']);
$st = vacationStatus(emp(10, 0, 0, false), 0);
check('неизвестный maxday (0): причиной остаётся только «не подтверждён»', $st['reasons'] === ['не подтверждён']);
check('fioMatches: подстрока, регистр и ё/е не важны', fioMatches('Фёдоров Пётр Семёнович', 'ФЕДОРОВ пет') && fioMatches('Иванов Иван', '') && !fioMatches('Иванов Иван', 'сидоров'));

// Группы «не приступили» / «не до конца» (в тестовой должности)
echo "Группы контроля:\n";
$g1 = \Models\Cur_emp::create(['fio' => 'Группов Первый Никогда', 'position' => $pos]);       // в списке, записи нет
$g2 = \Models\Cur_emp::create(['fio' => 'Группов Второй Зашёл', 'position' => $pos]);        // запись есть, дней нет
$g3 = \Models\Cur_emp::create(['fio' => 'Группов Третий Начал', 'position' => $pos]);        // выбрано 14 из 28
$g4 = \Models\Cur_emp::create(['fio' => 'Группов Четвёртый Готов', 'position' => $pos]);     // 28 из 28 подтверждён
$g5 = \Models\Cur_emp::create(['fio' => 'Группов Пятый Админ', 'position' => $pos]);         // введён админом 10 дней, не подтверждён
$g6 = \Models\Cur_emp::create(['fio' => 'Группов Шестой Неподтв', 'position' => $pos]);      // 28 из 28, не подтвердил
findOrCreateEmployee($g2);
$e3 = findOrCreateEmployee($g3); $e3->update(['lenght1' => 14, 'mon1' => 1, 'day1' => 5]);
$e4 = findOrCreateEmployee($g4); $e4->update(['lenght1' => 14, 'lenght2' => 14, 'mon1' => 1, 'day1' => 2, 'mon2' => 5, 'day2' => 2, 'isReady' => true]);
adminSavePart($g5, 1, "$y-06-10", 10, $y);
$e6 = findOrCreateEmployee($g6); $e6->update(['lenght1' => 14, 'lenght2' => 14, 'mon1' => 2, 'day1' => 2, 'mon2' => 6, 'day2' => 2]);

$sets = controlSets();
$ns = array_map(function ($r) { return $r['fio']; }, array_filter($sets['notStarted'], function ($r) use ($pos) { return $r['position'] === $pos && strpos($r['fio'], 'Группов') === 0; }));
$ic = array_map('displayFio', array_filter($sets['incomplete'], function ($e) use ($pos) { return $e->position === $pos && strpos($e->fam, 'группов') === 0; }));
sort($ns); sort($ic);
check('«Не приступили»: не заходил + зашёл, но ничего не выбрал', $ns === ['Группов Второй Зашёл', 'Группов Первый Никогда'], json_encode($ns, JSON_UNESCAPED_UNICODE));
check('«Не до конца»: начал (14/28), админский (10/28, не подтверждён), 28/28 без подтверждения', $ic === ['Группов Пятый Админ', 'Группов Третий Начал', 'Группов Шестой Неподтв'], json_encode($ic, JSON_UNESCAPED_UNICODE));
check('полностью заполненный (28/28, подтверждён) нигде не числится', !in_array('Группов Четвёртый Готов', array_merge($ns, $ic)));
check('группы не пересекаются', count(array_intersect($ns, $ic)) === 0);

// ---------- Автоподтверждение при вводе администратором ----------
echo "Автоподтверждение админом:\n";
$ready = function ($cur) { return (bool)findEmployeeForCur($cur)->isReady; };
$mk = function ($fio) use ($pos) { return \Models\Cur_emp::create(['fio' => $fio, 'position' => $pos]); }; // maxday тестовой должности = 28

$c1 = $mk('Автов Меньше Нормы');
adminSavePart($c1, 1, "$y-02-02", 27, $y);
check('админ: 27 дней при maxday 28 -> НЕ подтверждён (не заполнено)', !$ready($c1) && !vacationStatus(findEmployeeForCur($c1), 28)['complete']);

adminSavePart($c1, 2, "$y-06-02", 1, $y);
check('добавил 1 день -> ровно 28 из 28 -> подтверждён автоматически', $ready($c1) && vacationStatus(findEmployeeForCur($c1), 28)['complete']);

adminResetPart(findEmployeeForCur($c1)->id, 2, $y);
check('сбросил часть -> 27 из 28 -> подтверждение снимается', !$ready($c1));

adminSavePart($c1, 3, "$y-09-02", 100, $y);
check('ещё 100 дней (больше maxday) -> подтверждён', $ready($c1));

$c2 = $mk('Автов Свои Части');
$e2 = findOrCreateEmployee($c2);
savePart($e2->id, 1, 'jan', 2, 14, $y);                       // сотрудник сам: 14 дней, не подтверждает
check('у сотрудника только свои части -> без подтверждения', !$ready($c2));
adminSavePart($c2, 2, "$y-07-02", 14, $y);                    // админ добавляет 14 -> суммарно 28
check('свои 14 + админские 14 = 28 из 28 -> подтверждён автоматически', $ready($c2));
$e2 = findEmployeeForCur($c2);
adminResetPart($e2->id, 2, $y);                               // админских частей не осталось
check('после сброса админской части осталось 14 из 28 -> подтверждение снято, сотрудник может дозаполнить сам', $ready($c2) === false);
check('и действительно может: savePart проходит', savePart($e2->id, 2, 'jul', 2, 14, $y)['ok']);

$c3 = $mk('Автов Свои Без Админа');
$e3 = findOrCreateEmployee($c3);
savePart($e3->id, 1, 'jan', 2, 14, $y); savePart($e3->id, 2, 'may', 2, 14, $y);   // 28 из 28 своих, не нажал «Сохранить»
check('свои 28 из 28 без «Сохранить» и без админа -> не подтверждён', !$ready($c3));
adminSavePart($c3, 3, "$y-11-02", 5, $y);
check('админ добавил 5 -> 33 >= 28 -> подтверждён автоматически', $ready($c3));
adminResetPart($e3->id, 3, $y);
check('админ сбросил свою часть: своих 28 достаточно -> подтверждение не снимается', $ready($c3));

$c6 = $mk('Автов Ошибочный Ввод');
adminSavePart($c6, 1, "$y-03-02", 30, $y);
check('админ ввёл 30 (>= 28) -> подтверждён', $ready($c6));
adminResetPart(findEmployeeForCur($c6)->id, 1, $y);
check('ошибочный ввод сброшен -> 0 из 28: подтверждение снято (сотрудник не заблокирован)', !$ready($c6));
check('сотрудник снова может выбирать дни сам', savePart(findEmployeeForCur($c6)->id, 1, 'jan', 2, 14, $y)['ok']);

$c4 = $mk('Автов Подтверждённый');
$e4 = findOrCreateEmployee($c4);
savePart($e4->id, 1, 'jan', 2, 14, $y); savePart($e4->id, 2, 'may', 2, 14, $y);
$e4->update(['isReady' => true]);
adminSavePart($c4, 3, "$y-11-02", 5, $y);
check('уже подтверждённый сотрудник + админская часть сверх maxday -> остаётся подтверждённым', $ready($c4));
adminResetPart($e4->id, 3, $y);
check('сброс его админской части: 28 из 28 - подтверждение не снимается', $ready($c4));
$pos5 = findEmployeeForCur($c4);
adminSavePart($c4, 3, "$y-11-02", 5, $y);
adminResetPart($pos5->id, 1, $y);   // сбрасываем собственную часть 1 (14 дн.): остаётся 14+5=19 < 28
check('после сброса части суммарно 19 из 28 -> подтверждение снято (есть админская часть)', !$ready($c4));

// статус для карточки: заголовок и подробности
$st = vacationStatus(emp(18, 0, 0, false), 20);
check('карточка: заголовок «Не заполнено», подробности «Выбрано 18 из 20 дн. · не подтверждён»', $st['title'] === 'Не заполнено' && $st['detail'] === 'Выбрано 18 из 20 дн. · не подтверждён', $st['detail']);
$st = vacationStatus(emp(21, 21, 0, true), 42);
check('карточка: «Заполнено» / «42 из 42 дн. · подтверждено»', $st['title'] === 'Заполнено' && $st['detail'] === '42 из 42 дн. · подтверждено', $st['detail']);
$st = vacationStatus(emp(40, 2, 0, true), 5);
check('карточка: выбрано больше максимума -> «42 дн. (максимум по должности — 5) · подтверждено»', $st['detail'] === '42 дн. (максимум по должности — 5) · подтверждено', $st['detail']);

// ---------- Подтверждение сотрудником ----------
echo "Подтверждение сотрудником:\n";
$cq = \Models\Cur_emp::create(['fio' => 'Подтвердов Тест Тестович', 'position' => $pos]);
$eq = findOrCreateEmployee($cq);
check('confirmEmployee: без дней -> short', confirmEmployee($eq->id) === 'short');
savePart($eq->id, 1, 'jan', 2, 14, $y);
check('confirmEmployee: 14 из 28 -> short, не подтверждён', confirmEmployee($eq->id) === 'short' && !\Models\Employees::find($eq->id)->isReady);
savePart($eq->id, 2, 'mar', 2, 14, $y);
check('confirmEmployee: 28 из 28 -> ok, подтверждён', confirmEmployee($eq->id) === 'ok' && \Models\Employees::find($eq->id)->isReady == true);
check('confirmEmployee: повторно -> ok (идемпотентно)', confirmEmployee($eq->id) === 'ok');
check('confirmEmployee: несуществующий -> notfound', confirmEmployee(999999999) === 'notfound');
check('findOrCreateEmployee: повторный вызов возвращает ту же запись', findOrCreateEmployee($cq)->id === $eq->id && \Models\Employees::where('fam', 'подтвердов')->count() === 1);

// ---------- Переход из отчёта в «Ввод отпуска» ----------
echo "Ссылка «Редактировать» в отчёте:\n";
$m1 = \Models\Cur_emp::create(['fio' => 'Ссылкин Иван Иванович Оглы', 'position' => $pos]);
$m2 = \Models\Cur_emp::create(['fio' => 'Ссылкин Иван Иванович Оглы', 'position' => 'Санитар']);   // тёзка, другая должность
$me1 = findOrCreateEmployee($m1); $me2 = findOrCreateEmployee($m2);
$map = curIdMap();
check('4 слова ФИО: запись отпуска сопоставляется с записью списка', curIdForEmployee($me1, $map) === (int)$m1->id);
check('тёзки с разными должностями получают РАЗНЫЕ id', curIdForEmployee($me2, $map) === (int)$m2->id && $m1->id !== $m2->id);
$orphan = \Models\Employees::create(['fam' => 'сирота', 'name' => 'без', 'otch' => 'списка', 'position' => $pos]);
check('запись, которой нет в списке, -> null (иконка будет неактивной)', curIdForEmployee($orphan, $map) === null);
check('controlSets: у не приступивших из списка есть id для ссылки', (function () use ($m1, $pos) {
    foreach (controlSets()['notStarted'] as $r) { if ($r['fio'] === 'Ссылкин Иван Иванович Оглы' && $r['position'] === $pos) { return (int)$r['id'] === (int)$m1->id; } }
    return false;
})());

// ---------- Безопасность входа: политика паролей ----------
echo "Пароли:\n";
check('пароль «admin» слабый', weakPasswordReason('admin') !== null);
check('пароль «password1234» (12, распространённое слово) слабый', weakPasswordReason('password1234') !== null);
check('пароль «Qwerty123456» слабый', weakPasswordReason('Qwerty123456') !== null);
check('пароль «aaaaaaaaaaaaaaaa» слабый (мало разных символов)', weakPasswordReason('aaaaaaaaaaaaaaaa') !== null);
check('пароль, содержащий логин, слабый', weakPasswordReason('boss-Kx9#mQ2vLp', 'boss') !== null);
check('пароль короче 12 слабый', weakPasswordReason('Kx9#mQ2vLp') !== null);
check('длинный случайный пароль принимается', weakPasswordReason('Kx9#mQ2vLp-tr7Z!') === null);
check('фраза из слов принимается', weakPasswordReason('синий-трактор-плывёт-2027') === null);

// ---------- Приоритет окружения над .env ----------
echo "Настройки окружения:\n";
$tmp = sys_get_temp_dir() . '/envprio_' . getmypid();
@mkdir($tmp); file_put_contents("$tmp/.env", "APP_ENV=dev\nTEST_ONLY_VALUE=from-dotenv\n");
$code = '$_ENV = []; require "' . __DIR__ . '/../vendor/autoload.php"; require "' . __DIR__ . '/../security.php";'
      . '(Dotenv\Dotenv::createImmutable("' . $tmp . '"))->safeLoad(); echo envValue("APP_ENV"), "|", envValue("TEST_ONLY_VALUE"), "|", var_export(isProduction(), true);';
$out = shell_exec('APP_ENV=production ' . escapeshellarg(PHP_BINARY) . ' -d variables_order=GPCS -r ' . escapeshellarg($code));
check('реальное APP_ENV=production сильнее .env с APP_ENV=dev (веб-режим, $_ENV пуст)', $out === 'production|from-dotenv|true', (string)$out);
$out2 = shell_exec(escapeshellarg(PHP_BINARY) . ' -d variables_order=GPCS -r ' . escapeshellarg($code));
check('без реального APP_ENV значение берётся из .env (dev)', strpos((string)$out2, 'dev|') === 0, (string)$out2);
@unlink("$tmp/.env"); @rmdir($tmp);

DB::connection()->rollBack();

echo "\n" . ($failed ? "ПРОВАЛЕНО: $failed из $total\n" : "Все проверки пройдены ($total)\n");
exit($failed ? 1 : 0);
