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

DB::connection()->rollBack();

echo "\n" . ($failed ? "ПРОВАЛЕНО: $failed из $total\n" : "Все проверки пройдены ($total)\n");
exit($failed ? 1 : 0);
