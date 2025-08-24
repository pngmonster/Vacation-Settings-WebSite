<?php

    require "functions.php"; //Функции PHP

    if(isset($_GET['id']))
    {
        $employee = \Models\Employees::find($_GET['id']);
    }
    else
    {
        header('Location: sign.php');
        exit;
    }
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой отпуск</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%; /* добавляем эту строку */
        }
        .vacation-container {
            max-width: 1000px;
            width: 100%;
            margin: 0 20px;
        }
        
    </style>
</head>

<body>

    <div class="vacation-container">
        
        <?php 
        
        if ($employee)
        {?>

        <h1>Ваш отпуск⛵</h1>

        <ul class="employees-list ready">
            
                <li class="employee-card" id="employee-<?= $employee->id ?>">
                    <div class="between">
                        <div class="employee-name"><?php echo upfl($employee->fam) . " " . upfl($employee->name) . " " . upfl($employee->otch)?></div>
                        <div class="employee-position"><?php echo $employee->position?></div>
                    </div>

                    <div class="vacation-parts">
                        <div class="vacation-part">

                            <?php
                                if ($employee->lenght1 === 0)
                                {
                                    ?>
                                    <s><div class="part-title">1 Часть</div></s>
                                    <div class="vacation-dates">
                                        <span class="date-label">Начало:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Конец:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Длительность:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <?php
                                }
                                else
                                {
                                    $day1 = $employee->day1;
                                    $mon1 = $employee->mon1;
                                    $len1 = $employee->lenght1;

                                    $dateArr1 = dateCalc($day1, $mon1, $year, $len1);
                                    
                                    ?>
                                    <div class="part-title">1 Часть</div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Начало:</span>
                                        <span class="date-value"><?php echo $dateArr1['start']->format('d.m.Y'); ?></span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Конец:</span>
                                        <span class="date-value"><?php echo $dateArr1['end']->format('d.m.Y'); ?></span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Длительность:</span>
                                        <span class="date-value"><?php echo $len1?> дней</span>
                                    </div>
                                    <?php
                                }
                            ?>
                            
                        </div>
                        <div class="vacation-part">
                            <?php
                                if ($employee->lenght2 === 0)
                                {
                                    ?>
                                    <s><div class="part-title">2 Часть</div></s>
                                    <div class="vacation-dates">
                                        <span class="date-label">Начало:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Конец:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Длительность:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <?php
                                }
                                else
                                {
                                    $day2 = $employee->day2;
                                    $mon2 = $employee->mon2;
                                    $len2 = $employee->lenght2;

                                    $dateArr2 = dateCalc($day2, $mon2, $year, $len2);
                                    
                                    ?>
                                    <div class="part-title">2 Часть</div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Начало:</span>
                                        <span class="date-value"><?php echo $dateArr2['start']->format('d.m.Y'); ?></span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Конец:</span>
                                        <span class="date-value"><?php echo $dateArr2['end']->format('d.m.Y'); ?></span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Длительность:</span>
                                        <span class="date-value"><?php echo $len2?> дней</span>
                                    </div>
                                    <?php
                                }
                            ?>
                        </div>
                        <div class="vacation-part">
                            <?php
                                if ($employee->lenght3 === 0)
                                {
                                    ?>
                                    <s><div class="part-title">3 Часть</div></s>
                                    <div class="vacation-dates">
                                        <span class="date-label">Начало:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Конец:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Длительность:</span>
                                        <span class="date-value">-</span>
                                    </div>
                                    <?php
                                }
                                else
                                {
                                    $day3 = $employee->day3;
                                    $mon3 = $employee->mon3;
                                    $len3 = $employee->lenght3;

                                    $dateArr3 = dateCalc($day3, $mon3, $year, $len3);
                                    
                                    ?>
                                    <div class="part-title">3 Часть</div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Начало:</span>
                                        <span class="date-value"><?php echo $dateArr3['start']->format('d.m.Y'); ?></span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Конец:</span>
                                        <span class="date-value"><?php echo $dateArr3['end']->format('d.m.Y'); ?></span>
                                    </div>
                                    <div class="vacation-dates">
                                        <span class="date-label">Длительность:</span>
                                        <span class="date-value"><?php echo $len3?> дней</span>
                                    </div>
                                    <?php
                                }
                            ?>
                        </div>

                    </div>

                    <div class="vacation-part com">
                        <?php $com = $employee->comment;?>

                        <div class="part-title">Комментарий</div>
                        <div class="vacation-dates">
                            <span class="date-value"><?php echo $com  ?? "Нет комментария"?></span>
                        </div>
                    </div>
                    
                </li>
        </ul>

        <?php }

        else
        {
            echo "Сотрудник не найден";
        }
        
        ?>
        
    </div>

</body>

</html>