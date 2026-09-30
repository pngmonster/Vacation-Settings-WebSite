<?php
/**
 * Проверка целостности данных (после нагрузки, перед запуском и периодически на проде).
 *
 *   php bin/check-consistency.php            только проверка (ничего не меняет, блокировок нет)
 *   php bin/check-consistency.php --fix      пересчитать и исправить счётчики занятых дней
 *   docker compose exec app php bin/check-consistency.php
 *
 * Что проверяется:
 *   1. Счётчики занятых дней у должностей ("janEmp".."decEmp") совпадают с пересчётом по записям
 *      сотрудников (части, введённые администратором, в счётчики не входят).
 *   2. Нет отрицательных счётчиков.
 *   3. Нет дублей сотрудников (одно ФИО + должность в нескольких записях).
 *   4. У всех сотрудников должность есть в таблице должностей.
 *   5. Части заполнены корректно (месяц 1-12, число 1-31, длительность >= 0, пометка админа только у непустой части).
 * Предупреждения (не ошибки): подтверждённый отпуск, где дней меньше maxday (возможно после смены maxday).
 *
 * Код возврата: 0 - всё хорошо, 1 - найдены ошибки, 2 - сбой запуска.
 * --fix исправляет только счётчики (п.1-2); остальное нужно разбирать вручную.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../functions.php';

use Illuminate\Database\Capsule\Manager as DB;

$fix = in_array('--fix', $argv, true);
$conn = DB::connection();
$months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

// Пересчёт счётчиков по записям сотрудников: [должность => ['janEmp' => n, ...]]
function expectedCounters($employees, $year)
{
    $expected = [];
    foreach ($employees as $e) {
        foreach ([1, 2, 3] as $n) {
            $len = (int)$e->{"lenght$n"};
            $mon = (int)$e->{"mon$n"};
            if ($len <= 0 || $mon < 1 || $mon > 12 || $e->{"admin$n"}) {
                continue;
            }
            $dates = dateCalc($e->{"day$n"}, $mon, $year, $len);
            $t = convertMonth($mon);
            $expected[$e->position][$t['thisEmp']] = ($expected[$e->position][$t['thisEmp']] ?? 0) + $dates[0]['this'];
            $expected[$e->position][$t['nextEmp']] = ($expected[$e->position][$t['nextEmp']] ?? 0) + $dates[0]['next'];
        }
    }
    return $expected;
}

$errors = 0;         // ошибки, которые --fix не исправляет
$counterErrors = 0;  // ошибки счётчиков (их исправляет --fix)
$warnings = 0;

try {
    $conn->beginTransaction();
    if ($fix) {
        // Блокируем запись в employees (чтение остаётся возможным), чтобы пересчёт был точным.
        // Порядок блокировок тот же, что в приложении: сначала employees, затем positions.
        $conn->statement('LOCK TABLE employees IN EXCLUSIVE MODE');
    } else {
        $conn->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); // единый снимок без блокировок
    }

    $employees = \Models\Employees::orderBy('id')->get();
    $positions = \Models\Position::orderBy('position')->get();
    $expected = expectedCounters($employees, $year);

    // 1-2. Счётчики
    echo "== Счётчики занятых дней (год отпусков: $year)\n";
    $badPositions = [];
    foreach ($positions as $p) {
        $diffs = [];
        foreach ($months as $m) {
            $col = $m . 'Emp';
            $have = (int)$p->$col;
            $want = (int)($expected[$p->position][$col] ?? 0);
            if ($have !== $want) {
                $diffs[] = "$col: в БД $have, должно быть $want (разница " . sprintf('%+d', $have - $want) . ')';
            }
            if ($have < 0) {
                echo "  ОШИБКА: «{$p->position}» $col отрицательный ($have)\n";
            }
        }
        if ($diffs) {
            $counterErrors++;
            $badPositions[$p->position] = $expected[$p->position] ?? [];
            echo "  ОШИБКА: «{$p->position}» — расхождение:\n    " . implode("\n    ", $diffs) . "\n";
        }
    }
    if (!$badPositions) {
        echo "  ок: счётчики всех " . count($positions) . " должностей совпадают с пересчётом\n";
    }
    $unknown = array_diff(array_keys($expected), $positions->pluck('position')->all());

    // 3. Дубли сотрудников
    echo "== Дубли сотрудников\n";
    $dups = \Models\Employees::selectRaw('fam, name, otch, position, count(*) as c, string_agg(id::text, \', \' order by id) as ids')
        ->groupBy('fam', 'name', 'otch', 'position')->havingRaw('count(*) > 1')->get();
    foreach ($dups as $d) {
        $errors++;
        echo "  ОШИБКА: «{$d->fam} {$d->name} {$d->otch}» ({$d->position}) — {$d->c} записи, id: {$d->ids}\n";
    }
    if (!count($dups)) {
        echo "  ок: дублей нет\n";
    }

    // 4. Должности
    echo "== Должности сотрудников\n";
    $known = $positions->pluck('position')->all();
    $orphans = $employees->filter(function ($e) use ($known) { return !in_array($e->position, $known, true); });
    foreach ($orphans as $e) {
        $errors++;
        echo "  ОШИБКА: сотрудник id {$e->id} ({$e->fam}): должности «{$e->position}» нет в таблице должностей\n";
    }
    if (!count($orphans)) {
        echo "  ок\n";
    }

    // 5. Корректность частей + предупреждения
    echo "== Части отпусков\n";
    $maxdays = $positions->pluck('maxday', 'position')->all();
    $bad = 0;
    foreach ($employees as $e) {
        foreach ([1, 2, 3] as $n) {
            $len = (int)$e->{"lenght$n"}; $mon = (int)$e->{"mon$n"}; $day = (int)$e->{"day$n"};
            $problem = null;
            if ($len < 0) { $problem = 'отрицательная длительность'; }
            elseif ($len > 0 && ($mon < 1 || $mon > 12 || $day < 1 || $day > 31)) { $problem = "некорректная дата ($day.$mon)"; }
            elseif ($len === 0 && ($mon !== 0 || $day !== 0)) { $problem = 'части нет, но месяц/число заполнены'; }
            elseif ($len === 0 && $e->{"admin$n"}) { $problem = 'пометка «администратор» у пустой части'; }
            if ($problem) {
                $bad++; $errors++;
                echo "  ОШИБКА: сотрудник id {$e->id} ({$e->fam}), часть $n: $problem\n";
            }
        }
        $st = vacationStatus($e, $maxdays[$e->position] ?? 0);
        if ($e->isReady && $st['chosen'] < $st['maxday']) {
            $warnings++;
            echo "  предупреждение: id {$e->id} ({$e->fam}) подтверждён, но выбрано {$st['chosen']} из {$st['maxday']} дн.\n";
        }
    }
    if (!$bad) {
        echo "  ок\n";
    }

    // Исправление счётчиков
    if ($fix && $badPositions) {
        foreach ($badPositions as $position => $exp) {
            $values = [];
            foreach ($months as $m) {
                $values[$m . 'Emp'] = (int)($exp[$m . 'Emp'] ?? 0);
            }
            \Models\Position::where('position', $position)->update($values);
            echo "  исправлено: счётчики «{$position}» пересчитаны\n";
        }
        $counterErrors = 0;
    }

    $conn->commit();
} catch (\Throwable $e) {
    if ($conn->transactionLevel() > 0) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Сбой проверки: ' . $e->getMessage() . "\n");
    exit(2);
}

echo "\n";
$errors += $counterErrors;
if ($errors === 0) {
    echo "ИТОГО: целостность в порядке" . ($warnings ? " (предупреждений: $warnings)" : '') . "\n";
    exit(0);
}
echo "ИТОГО: ошибок: $errors" . ($warnings ? ", предупреждений: $warnings" : '') . ($fix ? " (счётчики после --fix исправлены, остальное — вручную)" : " (счётчики можно исправить: --fix)") . "\n";
exit(1);
