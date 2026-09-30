<?php
/**
 * Стенд гонок данных: php tests/race/run.php [сценарии: dup slot clear confirm stress]
 *
 * ВНИМАНИЕ: стенд УДАЛЯЕТ данные. Запускайте только на отдельной тестовой БД (имя содержит 'race' или 'test'):
 *   createdb vac_race && psql -d vac_race -f db/schema.sql
 *   RACE_TESTS=yes DB_DATABASE=vac_race php tests/race/run.php
 * В Docker:  docker compose exec -e RACE_TESTS=yes -e DB_DATABASE=vacation_race app php tests/race/run.php
 */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../../functions.php';

$dbName = getenv('DB_DATABASE') ?: '';
if (!preg_match('/race|test/i', $dbName) || getenv('RACE_TESTS') !== 'yes') {
    fwrite(STDERR, "Отказ: нужна тестовая БД с 'race' или 'test' в названии и RACE_TESTS=yes (стенд удаляет данные).\n");
    exit(3);
}

use Illuminate\Database\Capsule\Manager as DB;

$only = array_slice($argv, 1);
$want = function ($name) use ($only) { return !$only || in_array($name, $only, true); };
$php = PHP_BINARY;
$worker = __DIR__ . '/worker.php';
$checker = __DIR__ . '/../../bin/check-consistency.php';
$failures = 0;

// Запуск процессов одновременно; возвращает вывод каждого
function parallel(array $commands)
{
    $procs = [];
    foreach ($commands as $i => $cmd) {
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[$i] = ['p' => $procs[$i], 'pipes' => $pipes];
    }
    $out = [];
    foreach ($procs as $i => $x) {
        $out[$i] = trim(stream_get_contents($x['pipes'][1]) . stream_get_contents($x['pipes'][2]));
        fclose($x['pipes'][1]); fclose($x['pipes'][2]);
        proc_close($x['p']);
    }
    return $out;
}

function resetData($limit = 100)
{
    DB::connection()->statement('DELETE FROM employees');
    DB::connection()->statement('DELETE FROM cur_emp');
    $cols = [];
    foreach (['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'] as $m) {
        $cols[$m] = $limit; $cols[$m . 'Emp'] = 0;
    }
    \Models\Position::query()->update($cols);
}

function addPeople($count, $position, $prefix)
{
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        $ids[] = \Models\Cur_emp::create(['fio' => "$prefix$i Тест Тестович", 'position' => $position])->id;
    }
    return $ids;
}

function report($title, $ok, $detail)
{
    global $failures;
    echo ($ok ? '  ✅ ' : '  ❌ ') . $title . ($detail ? " — $detail" : '') . "\n";
    if (!$ok) { $failures++; }
}

function consistency()
{
    global $php, $checker;
    $out = []; $code = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($checker) . ' 2>&1', $out, $code);
    return [$code === 0, implode("\n", $out), $code];
}

// Нарушение инварианта: подтверждённый отпуск, где дней меньше maxday
function readyButShort()
{
    return DB::connection()->selectOne('SELECT count(*) AS c FROM employees e JOIN positions p ON p.position = e.position
        WHERE e."isReady" AND e.lenght1 + e.lenght2 + e.lenght3 < p.maxday')->c;
}

$POS = 'Уборщик'; // maxday 28

// ---------------------------------------------------------------------------------------------
if ($want('dup')) {
    echo "\n[1] Двойной вход: 8 одновременных входов одного человека создают одну запись?\n";
    $iterations = 25; $withDup = 0;
    resetData();
    for ($it = 1; $it <= $iterations; $it++) {
        $cur = addPeople(1, $POS, "Дубль{$it}_")[0];
        $t = microtime(true) + 0.35;
        $cmds = [];
        for ($k = 0; $k < 8; $k++) { $cmds[] = "$php $worker create $t $cur"; }
        parallel($cmds);
        $cnt = \Models\Employees::where('fam', mb_strtolower("Дубль{$it}_1"))->count();
        if ($cnt !== 1) { $withDup++; }
    }
    report("записей на человека: ровно 1 во всех $iterations попытках", $withDup === 0, $withDup ? "дубли в $withDup из $iterations попыток" : '');
}

// ---------------------------------------------------------------------------------------------
if ($want('slot')) {
    echo "\n[2] Последний слот: 6 сотрудников одновременно берут 14 дней при остатке на 20\n";
    $iterations = 25; $bad = 0;
    for ($it = 1; $it <= $iterations; $it++) {
        resetData(20);
        $cur = addPeople(6, $POS, "Слот{$it}_");
        $ids = array_map(function ($c) { return findOrCreateEmployee(\Models\Cur_emp::find($c))->id; }, $cur);
        $t = microtime(true) + 0.35;
        $cmds = array_map(function ($id) use ($php, $worker, $t) { return "$php $worker grab $t $id"; }, $ids);
        parallel($cmds);
        $booked = \Models\Employees::where('lenght1', 14)->count();
        $jan = \Models\Position::where('position', $POS)->value('janEmp');
        if ($booked !== 1 || (int)$jan !== 14) { $bad++; }
    }
    report("ровно один слот и счётчик 14 во всех $iterations попытках", $bad === 0, $bad ? "нарушений: $bad из $iterations" : '');
}

// ---------------------------------------------------------------------------------------------
if ($want('confirm')) {
    echo "\n[3] Подтверждение против сброса: 2 части по 14 (=maxday 28), одновременно «Сохранить» и сброс части\n";
    $iterations = 150; $violations = 0;
    for ($it = 1; $it <= $iterations; $it++) {
        resetData();
        $cur = addPeople(1, $POS, "Подтв{$it}_")[0];
        $emp = findOrCreateEmployee(\Models\Cur_emp::find($cur));
        savePart($emp->id, 1, 'jan', 2, 14, (int)$year);
        savePart($emp->id, 2, 'mar', 2, 14, (int)$year);
        $t = microtime(true) + 0.3;
        parallel(["$php $worker confirm $t {$emp->id}", "$php $worker reset $t {$emp->id} 2"]);
        if ((int)readyButShort() > 0) { $violations++; }
    }
    report("нет «подтверждённого» отпуска с дней меньше maxday ($iterations попыток)", $violations === 0, $violations ? "нарушений: $violations" : '');
}

// ---------------------------------------------------------------------------------------------
if ($want('clear')) {
    echo "\n[4] Очистка БД во время работы сотрудников: счётчики остаются согласованными?\n";
    $iterations = 8; $bad = 0; $details = '';
    for ($it = 1; $it <= $iterations; $it++) {
        resetData(200);
        $curIds = addPeople(12, $POS, "Очистка{$it}_");
        foreach ($curIds as $c) { findOrCreateEmployee(\Models\Cur_emp::find($c)); }
        $t = microtime(true) + 0.4;
        $cmds = [];
        for ($k = 0; $k < 6; $k++) { $cmds[] = "$php $worker loop $t $k 1.2 " . implode(',', $curIds); }
        // очистка стартует в середине нагрузки
        $cmds[] = "$php $worker clear " . ($t + 0.5);
        parallel($cmds);
        // после очистки новые записи могли появиться (сотрудники продолжали работать) - важно только согласование
        list($ok, $out) = consistency();
        if (!$ok) { $bad++; if (!$details) { $details = trim(preg_replace('/\s+/', ' ', substr($out, 0, 260))); } }
    }
    report("счётчики совпадают с записями после очистки ($iterations попыток)", $bad === 0, $bad ? "рассогласование в $bad из $iterations: $details" : '');
}

// ---------------------------------------------------------------------------------------------
if ($want('stress')) {
    echo "\n[5] Общая нагрузка: 10 процессов × случайные операции 6 секунд (выбор, сброс, админ, подтверждение, удаление, вход)\n";
    resetData(60);
    $curIds = array_merge(addPeople(14, 'Уборщик', 'НагрА'), addPeople(14, 'Санитар', 'НагрБ'), addPeople(14, 'Врач АиР', 'НагрВ'));
    foreach ($curIds as $c) { findOrCreateEmployee(\Models\Cur_emp::find($c)); }
    $t = microtime(true) + 0.5;
    $cmds = [];
    for ($k = 0; $k < 10; $k++) { $cmds[] = "$php $worker loop $t " . (100 + $k) . " 6 " . implode(',', $curIds); }
    $outs = parallel($cmds);
    $ops = 0; $errs = 0; $dead = 0; $samples = [];
    foreach ($outs as $o) {
        $j = json_decode(trim(strrchr("\n" . $o, "\n")), true);
        if ($j) { $ops += $j['ops']; $errs += $j['errors']; $dead += $j['deadlocks']; $samples = array_merge($samples, $j['samples']); }
    }
    echo "  выполнено операций: $ops, необработанных исключений: $errs (из них deadlock: $dead)\n";
    foreach (array_slice($samples, 0, 2) as $s) { echo "    пример: $s\n"; }
    report('нет необработанных исключений (в т.ч. deadlock)', $errs === 0, $errs ? "$errs" : '');
    list($ok, $out) = consistency();
    report('целостность: счётчики, дубли, части', $ok, $ok ? '' : trim(preg_replace('/\s+/', ' ', substr($out, 0, 300))));
    $rs = (int)readyButShort();
    report('нет «подтверждённых» с дней меньше maxday', $rs === 0, $rs ? "нарушений: $rs" : '');
}

// ---------------------------------------------------------------------------------------------
if ($want('upload')) {
    echo "\n[6] Две загрузки списка сотрудников одновременно (два админа или двойной клик): итог - ровно один из списков?\n";
    $iterations = 20; $bad = 0; $crashed = 0;
    for ($it = 1; $it <= $iterations; $it++) {
        resetData();
        $t = microtime(true) + 0.35;
        $outs = parallel(["$php $worker replace $t A 40", "$php $worker replace $t B 40", "$php $worker replace $t A 40"]);
        foreach ($outs as $o) { if (strpos($o, 'replaced') === false) { $crashed++; } }
        $total = \Models\Cur_emp::count();
        $fromA = \Models\Cur_emp::where('fio', 'like', 'СписокA %')->count();
        $fromB = \Models\Cur_emp::where('fio', 'like', 'СписокB %')->count();
        if ($total !== 40 || ($fromA !== 0 && $fromB !== 0)) { $bad++; }
    }
    report("итог - ровно один целый список, без сбоев ($iterations попыток)", $bad === 0 && $crashed === 0,
           ($bad || $crashed) ? "смесь/неверное число строк: $bad, упавших загрузок: $crashed" : '');
}

echo "\n" . ($failures ? "ИТОГО: проблем найдено: $failures\n" : "ИТОГО: все проверки гонок пройдены\n");
exit($failures ? 1 : 0);
