<?php
require_once 'auth.php';
require "functions.php"; //Функции PHP

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 1. Очистка буфера
ob_end_clean();

$employees = \Models\Employees::where('isReady', true)
               ->orderBy('position', 'asc')  // Сначала сортировка по должности
               ->orderBy('fam', 'asc')       // Затем по фамилии
               ->get();

// 2. Создаем Excel-документ
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 3. Заполняем данными (пример)

$sheet->getStyle('A1:K1')->applyFromArray([
    'font' => ['bold' => true],

    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'] // Черные границы
        ]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'D0D0D0'
        ]
    ]
]);

$sheet->mergeCells('A1:B1');
$sheet->setCellValue('A1', 'Сотрудники');

$sheet->getColumnDimension('A')->setWidth(22); 
$sheet->setCellValue('A2', 'Должность');

$sheet->getColumnDimension('B')->setWidth(32); 
$sheet->setCellValue('B2', 'ФИО');


$sheet->mergeCells('C1:E1');
$sheet->setCellValue('C1', '1 Часть');

$sheet->getColumnDimension('C')->setWidth(12); 
$sheet->setCellValue('C2', 'Начало');

$sheet->getColumnDimension('D')->setWidth(12); 
$sheet->setCellValue('D2', 'Конец');

$sheet->getColumnDimension('E')->setWidth(12); 
$sheet->setCellValue('E2', 'Дительность');

$sheet->mergeCells('F1:H1');
$sheet->setCellValue('F1', '2 Часть');

$sheet->getColumnDimension('F')->setWidth(12); 
$sheet->setCellValue('F2', 'Начало');

$sheet->getColumnDimension('G')->setWidth(12); 
$sheet->setCellValue('G2', 'Конец');

$sheet->getColumnDimension('H')->setWidth(12); 
$sheet->setCellValue('H2', 'Дительность');

$sheet->mergeCells('I1:K1');
$sheet->setCellValue('I1', '3 Часть');

$sheet->getColumnDimension('I')->setWidth(12); 
$sheet->setCellValue('I2', 'Начало');

$sheet->getColumnDimension('J')->setWidth(12); 
$sheet->setCellValue('J2', 'Конец');

$sheet->getColumnDimension('K')->setWidth(12); 
$sheet->setCellValue('K2', 'Дительность');

$i = 2;
$data = [];

foreach ($employees as $employee):
    $i++;
    $day1 = $employee->day1;
    $mon1 = $employee->mon1;
    $len1 = $employee->lenght1;
    $dateArr1 = dateCalc($day1, $mon1, $year, $len1);

    $day2 = $employee->day2;
    $mon2 = $employee->mon2;
    $len2 = $employee->lenght2;
    $dateArr2 = dateCalc($day2, $mon2, $year, $len2);

    $day3 = $employee->day3;
    $mon3 = $employee->mon3;
    $len3 = $employee->lenght3;
    $dateArr3 = dateCalc($day3, $mon3, $year, $len3);

    $data[] = [
        $employee->position, 
        upfl($employee->fam) . ' ' . upfl($employee->name) . ' ' . upfl($employee->otch),
        $len1 === 0 ? '-' : $dateArr1['start']->format('d.m.Y'),
        $len1 === 0 ? '-' : $dateArr1['end']->format('d.m.Y'),
        $len1 === 0 ? '-' : $len1,
        $len2 === 0 ? '-' : $dateArr2['start']->format('d.m.Y'),
        $len2 === 0 ? '-' : $dateArr2['end']->format('d.m.Y'),
        $len2 === 0 ? '-' : $len2,
        $len3 === 0 ? '-' : $dateArr3['start']->format('d.m.Y'),
        $len3 === 0 ? '-' : $dateArr3['end']->format('d.m.Y'),
        $len3 === 0 ? '-' : $len3,
    ];
endforeach;

$row = 3;
foreach ($data as $item) {
    $sheet->setCellValue('A' . $row, $item[0]);
    $sheet->setCellValue('B' . $row, $item[1]);
    $sheet->setCellValue('C' . $row, $item[2]);
    $sheet->setCellValue('D' . $row, $item[3]);
    $sheet->setCellValue('E' . $row, $item[4]);
    $sheet->setCellValue('F' . $row, $item[5]);
    $sheet->setCellValue('G' . $row, $item[6]);
    $sheet->setCellValue('H' . $row, $item[7]);
    $sheet->setCellValue('I' . $row, $item[8]);
    $sheet->setCellValue('J' . $row, $item[9]);
    $sheet->setCellValue('K' . $row, $item[10]);
    $row++;
}

$sheet->getStyle('A2:K' . $i)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'] // Черные границы
        ]
    ]
]);

// 4. Настраиваем скачивание
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Отпуска_'. $year . "_сохранено_" . date('d-m-Y') . '.xlsx"');
header('Cache-Control: max-age=0');

// 5. Отправляем файл
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>