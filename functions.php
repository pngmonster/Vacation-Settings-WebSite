<?php
require "config.php"; //Подключение к БД

$year = (\Models\Params::find(1)->toArray())['year'];

//Экранирование значения для вывода в HTML (защита от XSS)
function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

//Обнуление всех дней отпуска для всех должностей
function clearPositionsData() {

    // Получаем объект со всеми записями из таблицы
    $positions = \Models\Position::all();

    if ($positions->isNotEmpty())
    {

        // Обновляем все записи
        \Models\Position::query()->update([
            'jan' => '0',
            'feb' => '0',
            'mar' => '0',
            'apr' => '0',
            'may' => '0',
            'jun' => '0',
            'jul' => '0',
            'aug' => '0',
            'sep' => '0',
            'oct' => '0',
            'nov' => '0',
            'dec' => '0'
        ]);
    }
}

function clearPositionsEmpData() {

    // Получаем объект со всеми записями из таблицы
    $positions = \Models\Position::all();

    if ($positions->isNotEmpty())
    {

        // Обновляем все записи
        \Models\Position::query()->update([
            'janEmp' => '0',
            'febEmp' => '0',
            'marEmp' => '0',
            'aprEmp' => '0',
            'mayEmp' => '0',
            'junEmp' => '0',
            'julEmp' => '0',
            'augEmp' => '0',
            'sepEmp' => '0',
            'octEmp' => '0',
            'novEmp' => '0',
            'decEmp' => '0'
        ]);
    }
}

function deleteAllEmployees() {
    \Models\Employees::truncate();
}

//Разделение строки на массив (Фамилия, Имя, Отчество)
function textToFio($text)
{
    $text = mb_strtolower($text); //Маленький регистр
    $text = trim($text); //Убирает лишние пробелы в начале и конце
    $text = preg_replace('/\s+/', ' ', $text);//Убирает повторяющиеся пробелы в середине

    $fio = explode(" ", $text); //Разделение по пробелу

    if (count($fio) === 3)
    {
        return $fio;
    }
    else
    {
        return 0;
    }
}

//Делает первую букву заглавной (uppfl - upper first letter)
function upfl($text)
{
    $firstLetter = mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8')); //Извлекаем первую букву и увеличиваем
    $rest = mb_substr($text, 1, mb_strlen($text, 'UTF-8'), 'UTF-8'); //Извлекаем остаток слова
    $result = $firstLetter . $rest; //Соединяем залавную букву и остаток

    return $result;
}

//Высчитываем дату начала и конца отпуска и количество дней потраченых в первом и послед месяце
function dateCalc($day, $month, $year, $lenght)
{
    $day = str_pad($day, 2, '0', STR_PAD_LEFT);// Добавляем ведущий ноль
    $startDate = new DateTime("$year-$month-$day");
    $endDate = new DateTime("$year-$month-$day");
    if ($lenght != 0)
    {
        $lenght--;
        $endDate->add(new DateInterval("P{$lenght}D"));
        $lenght++;
    }

    $arr = ['start'=>$startDate, 'end'=>$endDate]; //Формируем 2 даты

    $month1 = $startDate->format('n');
    $month2 = $endDate->format('n');

    if($month1 != $month2) //Считаем, сколько дней птрачено в первом и след месяце
    {
        $day2 = $endDate->format('j');
        $day1 = $lenght - $day2;

        array_push($arr, ['this'=>$day1, 'next'=>$day2]);
        return $arr;
    }
    else
    {
        array_push($arr, ['this'=>$lenght, 'next'=>0]);
        return $arr;
    }

    return $arr;
}

function convertMonth($mon) //Конвертируем числ в месяц для БД
{
    if($mon > 0 && $mon < 13)
    {
        $x = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dec',
        13 => 'jan'
        ];

        $months = ['this' => $x[$mon], 'thisEmp' => $x[$mon] . 'Emp',
                'next' => $x[$mon+1], 'nextEmp' => $x[$mon+1] . 'Emp'
                ];
            
        return $months;
    }
    else
    {
        return 0;
    }
    

}

$monthsToInt = [
    'jan' => 1,
    'feb' => 2,
    'mar' => 3,
    'apr' => 4,
    'may' => 5,
    'jun' => 6,
    'jul' => 7,
    'aug' => 8,
    'sep' => 9,
    'oct' => 10,
    'nov' => 11,
    'dec' => 12
];

$monthsToStr = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dec'
        ];

$monthsToLongStr = [
        1 => 'Январь',
        2 => 'Февраль',
        3 => 'Март',
        4 => 'Апрель',
        5 => 'Май',
        6 => 'Июнь',
        7 => 'Июль',
        8 => 'Август',
        9 => 'Сентябрь',
        10 => 'Октябрь',
        11 => 'Ноябрь',
        12 => 'Декабрь'
        ];

function plusLen($position, $day, $mon, $year, $lenght) //Прибавление к количеству использованных дней струдниками
{
    if($mon > 0 && $mon < 13 && $lenght != 0)
    {
        $dateArr = dateCalc($day, $mon, $year, $lenght);

        $textMon = convertMonth($mon);

        \Models\Position::where('position', $position)
        ->increment($textMon['thisEmp'], $dateArr[0]['this']);
        \Models\Position::where('position', $position)
        ->increment($textMon['nextEmp'], $dateArr[0]['next']);
    }
}

function minusLen($position, $day, $mon, $year, $lenght) //Вычитание из количества использованных дней струдниками
{
    if($mon > 0 && $mon < 13 && $lenght != 0)
    {
        $dateArr = dateCalc($day, $mon, $year, $lenght);

        $textMon = convertMonth($mon);

        \Models\Position::where('position', $position)
        ->decrement($textMon['thisEmp'], $dateArr[0]['this']);
        \Models\Position::where('position', $position)
        ->decrement($textMon['nextEmp'], $dateArr[0]['next']);
    }
}

// =====================================================================
// ФИО и должности
// =====================================================================

//Схлопывает любые пробелы (в т.ч. неразрывные) в один и обрезает края. Регистр не меняется.
function collapseSpaces($text)
{
    $text = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', ' ', (string)$text);
    return trim($text);
}

//Ключ для сравнения должностей: без учёта регистра, ё = е, лишних пробелов
function positionKey($text)
{
    return mb_strtolower(str_replace(['ё', 'Ё'], 'е', collapseSpaces($text)));
}

//Делит ФИО на части для колонок fam / name / otch (в нижнем регистре, как хранятся записи).
//fam - первое слово, name - второе, otch - ВСЕ остальные слова (может быть пустой строкой).
//Количество слов не ограничено. Для 3 слов результат тот же, что давал прежний textToFio().
function splitFio($fio)
{
    $words = explode(' ', mb_strtolower(collapseSpaces($fio)));
    return [
        $words[0] ?? '',
        $words[1] ?? '',
        implode(' ', array_slice($words, 2)),
    ];
}

//ФИО для показа: как в загруженном файле (employees.fio), а для старых записей - из fam/name/otch
function displayFio($employee)
{
    if (!empty($employee->fio)) {
        return $employee->fio;
    }
    $parts = array_filter([upfl($employee->fam), upfl($employee->name), upfl($employee->otch)], 'strlen');
    return implode(' ', $parts);
}

//Находит сотрудника по записи списка (cur_emp) или создаёт, если он ещё не заходил
function findOrCreateEmployee($curEmp)
{
    list($fam, $name, $otch) = splitFio($curEmp->fio);

    $employee = \Models\Employees::where([
        ['fam', '=', $fam],
        ['name', '=', $name],
        ['otch', '=', $otch],
        ['position', '=', $curEmp->position],
    ])->first();

    if (!$employee) {
        return \Models\Employees::create([
            'fam' => $fam, 'name' => $name, 'otch' => $otch,
            'position' => $curEmp->position, 'fio' => $curEmp->fio,
        ]);
    }
    if (empty($employee->fio)) { // запись создана до появления колонки fio
        $employee->update(['fio' => $curEmp->fio]);
    }
    return $employee;
}

//Максимальная длина одной части отпуска по maxday (правило из прежнего кода user.php)
function maxPartDays($maxday)
{
    return ($maxday >= 48) ? 30 : 21;
}

// =====================================================================
// Части отпуска 1..3: сохранение и сброс
// Раньше эта логика была скопирована в user.php три раза и выполнялась без
// транзакции: при одновременных запросах двое могли занять последний слот.
// Теперь строки сотрудника и должности блокируются (SELECT ... FOR UPDATE), а
// проверка остатка, запись и изменение счётчиков выполняются атомарно.
// Правила и тексты сообщений - те же, что были в user.php.
// =====================================================================

//Результат с ошибками для savePart/resetPart
function partFail(...$errors)
{
    return ['ok' => false, 'errors' => $errors];
}

//Даты начала и конца части отпуска (['start' => DateTime, 'end' => DateTime])
function partRange($day, $mon, $year, $len)
{
    $r = dateCalc($day, $mon, $year, $len);
    return ['start' => $r['start'], 'end' => $r['end']];
}

//Сохранение части отпуска $n (1..3). $monthKey - 'jan'..'dec'
function savePart($employeeId, $n, $monthKey, $day, $len, $year)
{
    global $monthsToInt;

    if (!$year) {
        return partFail('Год не найден');
    }
    if (!isset($monthsToInt[$monthKey]) || $day < 1 || $day > 20) {
        return partFail('Некорректная дата');
    }

    $monthsMap = $monthsToInt;

    return \Illuminate\Database\Capsule\Manager::connection()->transaction(
        function () use ($employeeId, $n, $monthKey, $day, $len, $year, $monthsMap) {

            // Блокируем сотрудника и его должность до конца транзакции
            $employee = \Models\Employees::where('id', $employeeId)->lockForUpdate()->first();
            if (!$employee) {
                return partFail('Сотрудник не найден');
            }
            if ($employee->isReady) {
                return partFail('Отпуск уже подтверждён, изменить его нельзя');
            }
            if ($employee->{"admin$n"}) {
                return partFail('Эта часть установлена администратором, изменить её нельзя');
            }

            $position = \Models\Position::where('position', $employee->position)->lockForUpdate()->first();
            if (!$position) {
                return partFail('Позиция не найдена');
            }

            $maxday = $position->maxday;
            $maxPartDay = maxPartDays($maxday);
            $minPartDay = ($n === 1) ? 14 : 1;

            if ($len < $minPartDay || $len > $maxPartDay) {
                return partFail('Запрещенная длина отпуска');
            }

            $conMon = convertMonth($monthsMap[$monthKey]);
            $thisAvalibleDays = $position->{$conMon['this']} - $position->{$conMon['thisEmp']};
            $nextAvalibleDays = $position->{$conMon['next']} - $position->{$conMon['nextEmp']};

            $dateArr = dateCalc($day, $monthKey, $year, $len);

            $errors = [];
            if ($thisAvalibleDays - $dateArr[0]['this'] < 0) {
                $errors[] = 'Не хватает в этом месяце';
            }
            if ($nextAvalibleDays - $dateArr[0]['next'] < 0) {
                $errors[] = 'Не хватает в след месяце';
            }
            if ($errors) {
                return partFail(...$errors);
            }

            // Остальные части этого сотрудника: не пересекаются ли даты, и не превышена ли сумма дней
            $otherDays = 0;
            foreach ([1, 2, 3] as $k) {
                if ($k === $n) {
                    continue;
                }
                $otherLen = (int)$employee->{"lenght$k"};
                if ($otherLen === 0) {
                    continue;
                }
                $otherDays += $otherLen;

                $other = partRange($employee->{"day$k"}, $employee->{"mon$k"}, $year, $otherLen);
                if ($dateArr['start'] <= $other['end'] && $dateArr['end'] >= $other['start']) {
                    return partFail('Даты пересекаются с другой частью отпуска');
                }
            }

            if ($len + $otherDays > $maxday) {
                return partFail('Количество всех дней отпуска не должно привышать максимальное значение');
            }

            $mon = (int)$dateArr['start']->format('n'); //Месяц без ведущего нуля

            if ((int)$employee->{"lenght$n"} !== 0) {
                minusLen($employee->position, $employee->{"day$n"}, $employee->{"mon$n"}, $year, $employee->{"lenght$n"});
            }

            $employee->update([
                "mon$n"    => $mon,
                "lenght$n" => $len,
                "day$n"    => $day
            ]);

            plusLen($employee->position, $day, $mon, $year, $len);

            return ['ok' => true, 'errors' => []];
        },
        3 // повтор при взаимной блокировке (deadlock)
    );
}

//Сброс части отпуска $n (1..3) с возвратом дней в счётчики должности
function resetPart($employeeId, $n, $year)
{
    return \Illuminate\Database\Capsule\Manager::connection()->transaction(
        function () use ($employeeId, $n, $year) {
            $employee = \Models\Employees::where('id', $employeeId)->lockForUpdate()->first();
            if (!$employee) {
                return partFail('Сотрудник не найден');
            }
            if ($employee->isReady) {
                return partFail('Отпуск уже подтверждён, изменить его нельзя');
            }
            if ($employee->{"admin$n"}) {
                return partFail('Эта часть установлена администратором, сбросить её нельзя');
            }

            // Блокируем должность, чтобы счётчики менялись атомарно
            \Models\Position::where('position', $employee->position)->lockForUpdate()->first();

            minusLen($employee->position, $employee->{"day$n"}, $employee->{"mon$n"}, $year, $employee->{"lenght$n"});

            $employee->update([
                "mon$n"    => 0,
                "lenght$n" => 0,
                "day$n"    => 0
            ]);

            return ['ok' => true, 'errors' => []];
        },
        3
    );
}

//Обработка формы части $n на странице user.php: выводит ошибки или перезагружает страницу
function handlePartRequest($employee, $n)
{
    global $year;

    $redirect = function () use ($employee) {
        echo "Данные успешно обновлены!";
        echo '<script>location.href=' . json_encode($_SERVER['PHP_SELF'] . '?id=' . (int)$employee->id, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
        exit;
    };

    $result = null;

    if (!empty($_POST["cancel$n"])) {
        $result = resetPart($employee->id, $n, $year);
    } elseif (!empty($_POST["part$n-month"]) && !empty($_POST["part$n-day"]) && !empty($_POST["part$n-days"])) {
        $result = savePart(
            $employee->id,
            $n,
            (string)$_POST["part$n-month"],
            (int)$_POST["part$n-day"],
            (int)$_POST["part$n-days"],
            $year
        );
    }

    if ($result === null) {
        return;
    }
    if ($result['ok']) {
        $redirect();
    }
    foreach ($result['errors'] as $error) {
        echo '<div class="answ">' . esc($error) . '</div>';
    }
}

// =====================================================================
// Загрузка списка сотрудников (ФИО + должность) из Excel
// =====================================================================

//Допустимые должности: [ключ для сравнения => название из БД]
function allowedPositions()
{
    $map = [];
    foreach (\Models\Position::pluck('position') as $name) {
        $map[positionKey($name)] = $name;
    }
    return $map;
}

/**
 * Разбор и строгая проверка строк листа.
 *
 * $sheetRows - массив строк листа, индекс 0 = строка 1 в Excel; каждая строка - массив ячеек.
 * Формат: столбец A - ФИО, столбец B - должность; первая строка может быть заголовком
 * (если в ней есть ячейка «Должность» - столбцы определяются по заголовкам).
 *
 * Возвращает ['rows' => [['line', 'fio', 'position'], ...], 'errors' => [['line', 'text'], ...]].
 * При ЛЮБОЙ ошибке файл нужно отклонить целиком. Должность сравнивается без учёта регистра и
 * лишних пробелов, но пунктуация должна совпадать со списком; в результат попадает написание из БД.
 */
function validateEmployeeRows(array $sheetRows, array $allowed)
{
    $errors = [];
    $rows = [];
    $fioCol = 0;
    $posCol = 1;
    $headerLine = null;

    // Заголовок: первая непустая строка
    foreach ($sheetRows as $i => $cells) {
        if (implode('', array_map('collapseSpaces', $cells)) === '') {
            continue;
        }
        foreach ($cells as $c => $cell) {
            if (positionKey($cell) === 'должность') {
                $posCol = $c;
                $headerLine = $i + 1;
            }
        }
        if ($headerLine !== null) {
            $fioCol = null;
            foreach ($cells as $c => $cell) {
                $k = positionKey($cell);
                if ($c !== $posCol && ($k === 'фио' || $k === 'ф.и.о.' || $k === 'ф.и.о' || $k === 'сотрудник' || strpos($k, 'фио') === 0)) {
                    $fioCol = $c;
                    break;
                }
            }
            if ($fioCol === null) { // явного «ФИО» нет - берём первый столбец, который не «Должность»
                $fioCol = ($posCol === 0) ? 1 : 0;
            }
        }
        break;
    }

    $seen = [];
    foreach ($sheetRows as $i => $cells) {
        $line = $i + 1;
        if ($line === $headerLine) {
            continue;
        }
        $fio = collapseSpaces($cells[$fioCol] ?? '');
        $pos = collapseSpaces($cells[$posCol] ?? '');
        if ($fio === '' && $pos === '') {
            continue; // пустая строка
        }
        if ($fio === '') {
            $errors[] = ['line' => $line, 'text' => "не указано ФИО (должность: «{$pos}»)"];
            continue;
        }
        if ($pos === '') {
            $errors[] = ['line' => $line, 'text' => "не указана должность (ФИО: {$fio})"];
            continue;
        }

        $canonical = $allowed[positionKey($pos)] ?? null;
        if ($canonical === null) {
            $errors[] = ['line' => $line, 'text' => "должность «{$pos}» отсутствует в списке должностей (ФИО: {$fio})"];
            continue;
        }

        $key = mb_strtolower($fio) . '|' . $canonical;
        if (isset($seen[$key])) {
            $errors[] = ['line' => $line, 'text' => "повтор строки {$seen[$key]}: {$fio} — {$canonical}"];
            continue;
        }
        $seen[$key] = $line;
        $rows[] = ['line' => $line, 'fio' => $fio, 'position' => $canonical];
    }

    if (!$rows && !$errors) {
        $errors[] = ['line' => 0, 'text' => 'в файле нет ни одной строки с данными'];
    }

    return ['rows' => $rows, 'errors' => $errors];
}

//Читает первый лист файла (.xlsx / .xls) в массив строк. При ошибке бросает RuntimeException.
function readSpreadsheetRows($path)
{
    try {
        // Только настоящий Excel: без этого текстовый файл с расширением .xlsx читался бы как CSV
        if (!in_array(\PhpOffice\PhpSpreadsheet\IOFactory::identify($path), ['Xlsx', 'Xls'], true)) {
            throw new \RuntimeException('not excel');
        }
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
    } catch (\Throwable $e) {
        throw new \RuntimeException('не удалось прочитать файл — убедитесь, что это Excel (.xlsx или .xls)');
    }

    $sheet = $spreadsheet->getSheet(0);
    if ($sheet->getHighestDataRow() > 5000) {
        throw new \RuntimeException('в файле больше 5000 строк');
    }

    $rows = $sheet->toArray(null, true, true, false);
    $spreadsheet->disconnectWorksheets();
    return array_map(function ($r) {
        return array_map('strval', $r);
    }, $rows);
}

//Полностью заменяет список сотрудников (cur_emp) в одной транзакции. Возвращает число записей.
function replaceEmployeeList(array $rows)
{
    \Illuminate\Database\Capsule\Manager::connection()->transaction(function () use ($rows) {
        \Models\Cur_emp::query()->delete();
        foreach (array_chunk($rows, 200) as $chunk) {
            \Models\Cur_emp::insert(array_map(function ($r) {
                return ['fio' => $r['fio'], 'position' => $r['position']];
            }, $chunk));
        }
    });
    return count($rows);
}

// =====================================================================
// Ввод отпуска администратором
// Без каких-либо ограничений (лимиты по месяцам, maxday, длина части, пересечения,
// подтверждённость). Дни такой части НЕ входят в счётчики занятых дней должности
// ("janEmp".."decEmp"), поэтому при её сбросе/удалении они не вычитаются.
// =====================================================================

//Разбор даты из <input type="date"> (YYYY-MM-DD). Возвращает DateTime или null.
function parseIsoDate($text)
{
    $d = \DateTime::createFromFormat('!Y-m-d', (string)$text);
    $err = \DateTime::getLastErrors();
    if (!$d || ($err && ($err['warning_count'] || $err['error_count'])) || $d->format('Y-m-d') !== $text) {
        return null;
    }
    return $d;
}

//Часть $n сотрудника из списка $curEmp (Models\Cur_emp). Сотрудник создаётся, если ещё не заходил.
//Технические границы (не бизнес-ограничения): дата - в году отпусков, длительность 1..366 дней.
function adminSavePart($curEmp, $n, $start, $len, $year)
{
    if (!in_array($n, [1, 2, 3], true)) {
        return partFail('Неверный номер части');
    }
    $date = parseIsoDate($start);
    if (!$date) {
        return partFail('Укажите дату начала');
    }
    if ((int)$date->format('Y') !== (int)$year) {
        return partFail("Дата начала должна быть в {$year} году");
    }
    if ($len < 1 || $len > 366) {
        return partFail('Количество дней: целое число от 1 до 366');
    }

    return \Illuminate\Database\Capsule\Manager::connection()->transaction(
        function () use ($curEmp, $n, $date, $len, $year) {
            $employee = findOrCreateEmployee($curEmp);
            $employee = \Models\Employees::where('id', $employee->id)->lockForUpdate()->first();
            \Models\Position::where('position', $employee->position)->lockForUpdate()->first();

            // Если здесь была часть, выбранная самим сотрудником, её дни были учтены в счётчиках - возвращаем
            if ((int)$employee->{"lenght$n"} !== 0 && !$employee->{"admin$n"}) {
                minusLen($employee->position, $employee->{"day$n"}, $employee->{"mon$n"}, $year, $employee->{"lenght$n"});
            }

            $employee->update([
                "mon$n"    => (int)$date->format('n'),
                "day$n"    => (int)$date->format('j'),
                "lenght$n" => $len,
                "admin$n"  => true,
            ]);
            syncAdminConfirmation($employee);

            return ['ok' => true, 'errors' => [], 'employeeId' => $employee->id];
        },
        3
    );
}

//Сброс части $n администратором (любой: и его, и выбранной сотрудником)
function adminResetPart($employeeId, $n, $year)
{
    if (!in_array($n, [1, 2, 3], true)) {
        return partFail('Неверный номер части');
    }
    return \Illuminate\Database\Capsule\Manager::connection()->transaction(
        function () use ($employeeId, $n, $year) {
            $employee = \Models\Employees::where('id', $employeeId)->lockForUpdate()->first();
            if (!$employee) {
                return partFail('Сотрудник не найден');
            }
            \Models\Position::where('position', $employee->position)->lockForUpdate()->first();

            if ((int)$employee->{"lenght$n"} !== 0 && !$employee->{"admin$n"}) {
                minusLen($employee->position, $employee->{"day$n"}, $employee->{"mon$n"}, $year, $employee->{"lenght$n"});
            }

            $employee->update([
                "mon$n"    => 0,
                "day$n"    => 0,
                "lenght$n" => 0,
                "admin$n"  => false,
            ]);
            syncAdminConfirmation($employee);

            return ['ok' => true, 'errors' => []];
        },
        3
    );
}

//Существующая запись сотрудника для строки списка (без создания)
function findEmployeeForCur($curEmp)
{
    list($fam, $name, $otch) = splitFio($curEmp->fio);
    return \Models\Employees::where([
        ['fam', '=', $fam], ['name', '=', $name], ['otch', '=', $otch], ['position', '=', $curEmp->position],
    ])->first();
}

//Удаление сотрудника с возвратом его дней в счётчики должности.
//Дни частей, введённых администратором, в счётчики не входили - их не вычитаем.
function deleteEmployee($employeeId, $year)
{
    return \Illuminate\Database\Capsule\Manager::connection()->transaction(function () use ($employeeId, $year) {
        $employee = \Models\Employees::where('id', $employeeId)->lockForUpdate()->first();
        if (!$employee) {
            return false;
        }
        \Models\Position::where('position', $employee->position)->lockForUpdate()->first();

        foreach ([1, 2, 3] as $n) {
            if (!$employee->{"admin$n"}) {
                minusLen($employee->position, $employee->{"day$n"}, $employee->{"mon$n"}, $year, $employee->{"lenght$n"});
            }
        }
        \Models\Employees::destroy($employeeId);
        return true;
    }, 3);
}

//Проверка id из адреса/формы: только цифры (иначе PostgreSQL падает с ошибкой bigint). Возвращает строку-id или null.
function validId($value)
{
    $value = (string)$value;
    return (ctype_digit($value) && strlen($value) <= 18) ? $value : null;
}

// =====================================================================
// Контроль заполнения
// Единое правило для сайта, Excel и окна админа. Отпуск считается НЕ заполненным до конца,
// если выбрано меньше дней, чем maxday должности, ИЛИ отпуск не подтверждён сотрудником
// (не нажато «Сохранить»). Правило одинаково и для частей, введённых администратором.
// =====================================================================

//Сколько всего дней выбрано (все три части, включая введённые администратором)
function chosenDays($employee)
{
    return (int)$employee->lenght1 + (int)$employee->lenght2 + (int)$employee->lenght3;
}

//Статус заполнения: ['chosen', 'maxday', 'started', 'complete', 'reasons', 'label']
function vacationStatus($employee, $maxday)
{
    $chosen = chosenDays($employee);
    $maxday = (int)$maxday;
    $reasons = [];

    if ($chosen < $maxday) {
        $reasons[] = "выбрано $chosen из $maxday дн.";
    }
    if (!$employee->isReady) {
        $reasons[] = 'не подтверждён';
    }

    if ($reasons) {
        $title = 'Не заполнено';
        $detail = upfl(implode(' · ', $reasons));
    } else {
        $title = 'Заполнено';
        $detail = ($chosen === $maxday ? "$chosen из $maxday дн." : "$chosen дн. (максимум по должности — $maxday)") . ' · подтверждено';
    }

    return [
        'chosen'   => $chosen,
        'maxday'   => $maxday,
        'started'  => $chosen > 0,
        'complete' => !$reasons,
        'reasons'  => $reasons,
        'title'    => $title,    // короткий заголовок для карточки
        'detail'   => $detail,   // подробности второй строкой
        'label'    => $reasons ? 'Не заполнено: ' . implode(', ', $reasons) : 'Заполнено', // для Excel
    ];
}

//Подходит ли ФИО под поисковый запрос (подстрока, без учёта регистра и ё/е)
function fioMatches($fio, $search)
{
    $search = positionKey($search);
    return $search === '' || mb_strpos(positionKey($fio), $search) !== false;
}

/**
 * Две группы для контроля (страница «Отчёт»):
 *  - notStarted: «Не приступили к заполнению» - сотрудник из списка, у которого нет записи
 *    (ни разу не заходил) или в записи не выбрано ни одного дня;
 *  - incomplete: «Заполнили не до конца» - выбрана хотя бы одна часть, но отпуск не заполнен
 *    до конца (см. vacationStatus).
 * Группы не пересекаются.
 * Возвращает ['notStarted' => [['fio','position','employee'|null]], 'incomplete' => [Employees...]].
 */
function controlSets()
{
    $maxdays = \Models\Position::pluck('maxday', 'position')->all();
    $employees = \Models\Employees::orderBy('position', 'asc')->orderBy('fam', 'asc')->get();

    $byKey = [];
    foreach ($employees as $e) {
        $byKey[$e->fam . '|' . $e->name . '|' . $e->otch . '|' . $e->position] = $e;
    }

    $notStarted = [];
    $matched = [];
    foreach (\Models\Cur_emp::orderBy('fio', 'asc')->orderBy('position', 'asc')->get() as $c) {
        list($fam, $name, $otch) = splitFio($c->fio);
        $e = $byKey[$fam . '|' . $name . '|' . $otch . '|' . $c->position] ?? null;
        if ($e) {
            $matched[$e->id] = true;
        }
        if (!$e || chosenDays($e) === 0) {
            $notStarted[] = ['fio' => $c->fio, 'position' => $c->position, 'employee' => $e];
        }
    }
    // Старые записи без пустых частей, которых нет в загруженном списке
    foreach ($employees as $e) {
        if (!isset($matched[$e->id]) && chosenDays($e) === 0) {
            $notStarted[] = ['fio' => displayFio($e), 'position' => $e->position, 'employee' => $e];
        }
    }

    $incomplete = [];
    foreach ($employees as $e) {
        $st = vacationStatus($e, $maxdays[$e->position] ?? 0);
        if ($st['started'] && !$st['complete']) {
            $incomplete[] = $e;
        }
    }

    return ['notStarted' => $notStarted, 'incomplete' => $incomplete];
}

//Подтверждение отпуска после действия администратора:
//  - есть части администратора: подтверждён, если выбрано дней >= maxday должности (автоматически),
//    иначе не подтверждён (считается не заполненным);
//  - частей администратора нет (например, после сброса), а выбрано меньше maxday: подтверждение
//    снимается, чтобы сотрудник мог сам дозаполнить отпуск на сайте;
//  - частей администратора нет, а своих дней достаточно: подтверждение не трогаем.
function syncAdminConfirmation($employee)
{
    $maxday = \Models\Position::where('position', $employee->position)->value('maxday');
    if ($maxday === null) {
        return;
    }
    $hasAdmin = $employee->admin1 || $employee->admin2 || $employee->admin3;
    $enough = chosenDays($employee) >= (int)$maxday;

    if ($hasAdmin) {
        $ready = $enough;
    } elseif (!$enough) {
        $ready = false;
    } else {
        return;
    }
    if ((bool)$employee->isReady !== $ready) {
        $employee->update(['isReady' => $ready]);
    }
}
?>