<?php
/**
 * Рабочий процесс стенда гонок (запускается из run.php, вручную не нужен).
 * Каждый процесс ждёт общего момента старта (барьер), чтобы запросы сталкивались максимально плотно.
 */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../../functions.php';

$dbName = getenv('DB_DATABASE') ?: '';
if (!preg_match('/race|test/i', $dbName) || getenv('RACE_TESTS') !== 'yes') {
    fwrite(STDERR, "Отказ: стенд гонок разрушает данные. Нужна БД с 'race' или 'test' в названии и RACE_TESTS=yes.\n");
    exit(3);
}

$mode = $argv[1];
$startAt = (float)$argv[2];
$args = array_slice($argv, 3);
while (microtime(true) < $startAt) { usleep(200); }

// Подтверждение отпуска: новая функция, если она есть в коде, иначе прежняя логика confirmUser.php
function doConfirm($id)
{
    if (function_exists('confirmEmployee')) {
        return confirmEmployee($id);
    }
    $employee = \Models\Employees::find($id);
    if (!$employee) { return 'notfound'; }
    if (($employee->lenght1 + $employee->lenght2 + $employee->lenght3) === $employee->position()->first()->maxday) {
        $employee->update(['isReady' => true]);
        return 'ok';
    }
    return 'short';
}

// Очистка БД: новая функция, если есть, иначе прежняя последовательность из clear.php
function doClear()
{
    if (function_exists('clearAllData')) { clearAllData(); return; }
    clearPositionsData();
    clearPositionsEmpData();
    deleteAllEmployees();
}

switch ($mode) {
    case 'create':      // findOrCreateEmployee для одного человека
        $cur = \Models\Cur_emp::find((int)$args[0]);
        echo findOrCreateEmployee($cur)->id, "\n";
        break;

    case 'grab':        // занять слот: часть 1, 5 января, 14 дней
        $r = savePart((int)$args[0], 1, 'jan', 5, 14, (int)$year);
        echo $r['ok'] ? "ok\n" : "fail\n";
        break;

    case 'confirm':
        echo doConfirm((int)$args[0]), "\n";
        break;

    case 'replace':     // загрузка списка сотрудников: replace <метка> <сколько строк>
        $rows = [];
        for ($i = 1; $i <= (int)$args[1]; $i++) { $rows[] = ['line' => $i, 'fio' => "Список{$args[0]} Номер{$i} Тестович", 'position' => 'Санитар']; }
        replaceEmployeeList($rows);
        echo "replaced\n";
        break;

    case 'reset':
        $r = resetPart((int)$args[0], (int)$args[1], (int)$year);
        echo $r['ok'] ? "ok\n" : "fail\n";
        break;

    case 'clear':
        doClear();
        echo "cleared\n";
        break;

    case 'loop':        // случайные операции до истечения времени: loop <seed> <секунд> <cur_id,cur_id,...>
        mt_srand((int)$args[0]);
        $until = microtime(true) + (float)$args[1];
        $curIds = array_map('intval', explode(',', $args[2]));
        $stats = ['ops' => 0, 'errors' => 0, 'deadlocks' => 0];
        $errorSamples = [];
        $monthKeys = array_keys($monthsToInt);

        while (microtime(true) < $until) {
            $cur = \Models\Cur_emp::find($curIds[array_rand($curIds)]);
            if (!$cur) { continue; }
            $op = mt_rand(1, 100);
            try {
                $emp = findEmployeeForCur($cur) ?: findOrCreateEmployee($cur);
                $n = mt_rand(1, 3);
                if ($op <= 35) {        // сотрудник выбирает часть
                    savePart($emp->id, $n, $monthKeys[array_rand($monthKeys)], mt_rand(1, 20), $n === 1 ? mt_rand(14, 21) : mt_rand(1, 14), (int)$year);
                } elseif ($op <= 50) {  // сотрудник сбрасывает часть
                    resetPart($emp->id, $n, (int)$year);
                } elseif ($op <= 65) {  // админ ставит часть
                    adminSavePart($cur, $n, sprintf('%d-%02d-%02d', $year, mt_rand(1, 12), mt_rand(1, 28)), mt_rand(1, 60), (int)$year);
                } elseif ($op <= 75) {  // админ сбрасывает часть
                    adminResetPart($emp->id, $n, (int)$year);
                } elseif ($op <= 90) {  // сотрудник подтверждает
                    doConfirm($emp->id);
                } elseif ($op <= 96) {  // админ удаляет сотрудника
                    deleteEmployee($emp->id, (int)$year);
                } else {                // повторный вход (создание записи)
                    findOrCreateEmployee($cur);
                }
                $stats['ops']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                if (stripos($e->getMessage(), 'deadlock') !== false) { $stats['deadlocks']++; }
                if (count($errorSamples) < 2) { $errorSamples[] = substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160); }
            }
        }
        echo json_encode($stats + ['samples' => $errorSamples], JSON_UNESCAPED_UNICODE), "\n";
        break;
}
