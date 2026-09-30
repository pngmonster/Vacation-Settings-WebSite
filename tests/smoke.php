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

DB::connection()->rollBack();

echo "\n" . ($failed ? "ПРОВАЛЕНО: $failed из $total\n" : "Все проверки пройдены ($total)\n");
exit($failed ? 1 : 0);
