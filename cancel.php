<?php
require "functions.php";

// Всегда проверяем входные данные!
$part = $_POST['part'] ?? null;
$employeeId = $_POST['employee'] ?? null;

if ($part && $employeeId) {
    // Обновляем только нужные поля
    $updateData = [
        'mon'.$part => 0,
        'day'.$part => 0, 
        'lenght'.$part => 0
    ];
    
    $result = \Models\Employees::where('id', $employeeId)
        ->update($updateData);
    
    echo "Часть $part отменена. Затронуто записей: " . $result;
} else {
    echo "Ошибка: не указаны параметры";
}
?>