<?php
require "config.php"; //Подключение к БД

$year = (\Models\Params::find(1)->toArray())['year'];

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
?>