<?php
require_once 'auth.php';
require "functions.php"; //Функции PHP

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// 1. Очистка буфера
ob_end_clean();

$allEmployees = \Models\Employees::orderBy('position', 'asc')->orderBy('fam', 'asc')->get(); // по должности, затем по фамилии
$maxdays = \Models\Position::pluck('maxday', 'position')->all();

// Сначала полностью заполненные, затем «не заполнено до конца» (дней меньше maxday ИЛИ не подтверждён).
// Внутри каждой группы порядок по должности и фамилии сохраняется. Сотрудники, у которых
// вообще нет записи (ни разу не заходили на сайт), в таблицу не попадают.
$complete = [];
$incomplete = [];
foreach ($allEmployees as $e) {
    $status = vacationStatus($e, $maxdays[$e->position] ?? 0);
    if ($status['complete']) {
        $complete[] = [$e, $status];
    } else {
        $incomplete[] = [$e, $status];
    }
}
$employees = array_merge($complete, $incomplete);

// Цвет части, введённой администратором (тот же, что в отчёте на сайте)
const ADMIN_FILL = 'FFF2CC';
const ADMIN_MARK = 'Введено администратором';
// Цвет «не заполнено до конца» (тот же, что в отчёте на сайте)
const INCOMPLETE_FILL = 'FDE8E6';

// Раскладка столбцов: A Должность, B ФИО; затем для каждой части 4 столбца
// (Начало, Конец, Длительность, Пометка): C-F, G-J, K-N; O - комментарий
$partFirstCol = [1 => 3, 2 => 7, 3 => 11];
$commentCol = 15;
$statusCol = 16;
$lastCol = Coordinate::stringFromColumnIndex($statusCol); // P

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 2. Шапка
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D0D0D0']],
]);

$sheet->mergeCells('A1:B1');
$sheet->setCellValue('A1', 'Сотрудники');
$sheet->getColumnDimension('A')->setWidth(22);
$sheet->setCellValue('A2', 'Должность');
$sheet->getColumnDimension('B')->setWidth(32);
$sheet->setCellValue('B2', 'ФИО');

foreach ($partFirstCol as $n => $c) {
    $L = function ($offset) use ($c) { return Coordinate::stringFromColumnIndex($c + $offset); };
    $sheet->mergeCells($L(0) . '1:' . $L(3) . '1');
    $sheet->setCellValue($L(0) . '1', "$n Часть");
    $sheet->setCellValue($L(0) . '2', 'Начало');
    $sheet->setCellValue($L(1) . '2', 'Конец');
    $sheet->setCellValue($L(2) . '2', 'Длительность');
    $sheet->setCellValue($L(3) . '2', 'Пометка');
    for ($k = 0; $k < 3; $k++) {
        $sheet->getColumnDimension($L($k))->setWidth(12);
    }
    $sheet->getColumnDimension($L(3))->setWidth(18);
}
$commentL = Coordinate::stringFromColumnIndex($commentCol); // O
$statusL = Coordinate::stringFromColumnIndex($statusCol);   // P
$sheet->mergeCells($commentL . '1:' . $statusL . '1');
$sheet->setCellValue($commentL . '1', 'Доп. Информация');
$sheet->getColumnDimension($commentL)->setWidth(20);
$sheet->setCellValue($commentL . '2', 'Комментарий');
$sheet->getColumnDimension($statusL)->setWidth(34);
$sheet->setCellValue($statusL . '2', 'Статус заполнения');

// 3. Данные
$row = 3;
foreach ($employees as list($employee, $status)) {
    $sheet->setCellValue('A' . $row, $employee->position);
    $sheet->setCellValue('B' . $row, displayFio($employee));

    foreach ($partFirstCol as $n => $c) {
        $len = (int)$employee->{"lenght$n"};
        $isAdmin = (bool)$employee->{"admin$n"} && $len !== 0;
        $cells = ['-', '-', '-', ''];

        if ($len !== 0) {
            $dates = dateCalc($employee->{"day$n"}, $employee->{"mon$n"}, $year, $len);
            $cells = [
                $dates['start']->format('d.m.Y'),
                $dates['end']->format('d.m.Y'),
                $len,
                $isAdmin ? ADMIN_MARK : '',
            ];
        }
        foreach ($cells as $k => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c + $k) . $row, $value);
        }

        if ($isAdmin) { // выделяем всю часть цветом
            $range = Coordinate::stringFromColumnIndex($c) . $row . ':' . Coordinate::stringFromColumnIndex($c + 3) . $row;
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(ADMIN_FILL);
        }
    }

    $sheet->setCellValue($commentL . $row, $employee->comment === null ? 'Нет комментария' : $employee->comment);
    $sheet->setCellValue($statusL . $row, $status['label']);

    if (!$status['complete']) { // выделяем ФИО, должность и статус; части (в т.ч. жёлтые от администратора) не трогаем
        foreach (['A' . $row . ':B' . $row, $statusL . $row] as $range) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(INCOMPLETE_FILL);
        }
    }
    $row++;
}
$lastDataRow = $row - 1;

// Пометка и статус переносятся по словам, чтобы не раздувать таблицу
$sheet->getStyle($statusL . '3:' . $statusL . max(3, $lastDataRow))->getAlignment()->setWrapText(true);
foreach ($partFirstCol as $c) {
    $col = Coordinate::stringFromColumnIndex($c + 3);
    $sheet->getStyle($col . '3:' . $col . max(3, $lastDataRow))->getAlignment()->setWrapText(true);
}

$sheet->getStyle('A2:' . $lastCol . max(2, $lastDataRow))->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
]);

// 4. Легенда
$legend = $lastDataRow + 2;
$sheet->setCellValue('A' . $legend, ADMIN_MARK);
$sheet->getStyle('A' . $legend)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(ADMIN_FILL);
$sheet->getStyle('A' . $legend)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->setCellValue('B' . $legend, '— часть отпуска введена администратором (без ограничений, дни не учитываются в лимитах должности)');

$legend2 = $legend + 1;
$sheet->setCellValue('A' . $legend2, 'Не заполнено');
$sheet->getStyle('A' . $legend2)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(INCOMPLETE_FILL);
$sheet->getStyle('A' . $legend2)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->setCellValue('B' . $legend2, '— выбрано меньше максимума дней по должности или отпуск не подтверждён сотрудником (такие сотрудники в конце таблицы)');

// 5. Настраиваем скачивание
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Отпуска_'. $year . "_сохранено_" . date('d-m-Y') . '.xlsx"');
header('Cache-Control: max-age=0');

// 6. Отправляем файл
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
