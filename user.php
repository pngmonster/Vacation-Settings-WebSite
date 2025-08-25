<?php

    require "functions.php"; //Функции PHP

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Планирование отпуска</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/userStyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div>

        <div class="header">

        <?php // Подгружаем данные сотрудника
            $employeeId = $_GET['id'] ?? null;

            if ($employeeId)
            {
                // Находим сотрудника по ID
                $employee = \Models\Employees::find($employeeId);

                if ($employee->isReady) // Если пользователь сохранен, то перенаправляем
                {
                    header("Location: ready.php?id=" . urlencode($employeeId));
                    exit;
                }
                else
                {
                    if ($employee)
                    {
                        $part1Days = $employee->lenght1;
                        $part2Days = $employee->lenght2;
                        $part3Days = $employee->lenght3;

                        //echo $part1Days . " " . $part2Days . " " . $part3Days; //Удалить

                        // Получаем связанную таблицу
                        $position = $employee->position()->first();

                        if ($position) {
                            $maxday = $position->maxday;
                            $name = upfl($employee->name);
                            $userPosition = $employee->position;

                            if ($maxday >= 48)
                            {
                                $maxPartDay = 30;
                                $minPartDay = 14;
                            }
                            else
                            {
                                $maxPartDay = 21;
                                $minPartDay = 14;
                            }
                        }
                        
                        else
                        {
                            $answer = "Позиция не найдена";
                        }
                    } 
                    
                    else
                    {
                        $answer = "Сотрудник не найден";
                    }
                }
            }
            
            else
            {
                $answer = "ID сотрудника не указан";
            }
        ?>

        <div id="myModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Доступные дни отпуска</h2>
                    <p class="modal-subtitle">Количество свободных дней на каждый месяц для вашей должности. Эти значения зависят от выбора других сотрудников</p>
                </div>
                
                <?php
                $months = [
                    'Январь' => $position->jan - $position->janEmp,
                    'Февраль' => $position->feb - $position->febEmp,
                    'Март' => $position->mar - $position->marEmp,
                    'Апрель' => $position->apr - $position->aprEmp,
                    'Май' => $position->may - $position->mayEmp,
                    'Июнь' => $position->jun - $position->junEmp,
                    'Июль' => $position->jul - $position->julEmp,
                    'Август' => $position->aug - $position->augEmp,
                    'Сентябрь' => $position->sep - $position->sepEmp,
                    'Октябрь' => $position->oct - $position->octEmp,
                    'Ноябрь' => $position->nov - $position->novEmp,
                    'Декабрь' => $position->dec - $position->decEmp
                ];
                ?>
                
                <div class="month-list">
                    <?php foreach ($months as $month => $days): ?>
                        <div class="month-row">
                            <span class="month-name"><?= $month ?></span>
                            <span class="days-available"><?= $days ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button class="close-btn" onclick="closeModal()">Закрыть</button>
            </div>
        </div>

        <script>
            function openModal() {
                const modal = document.getElementById("myModal");
                modal.style.display = "block";
                setTimeout(() => {
                    modal.classList.add("active");
                }, 10);
            }
            
            function closeModal() {
                const modal = document.getElementById("myModal");
                modal.classList.remove("active");
                setTimeout(() => {
                    modal.style.display = "none";
                }, 300);
            }
            
            window.onclick = function(event) {
                const modal = document.getElementById("myModal");
                if (event.target === modal) {
                    closeModal();
                }
            }
            
            document.addEventListener('keydown', function(event) {
                const modal = document.getElementById("myModal");
                if (event.key === "Escape" && modal.style.display === "block") {
                    closeModal();
                }
            });
        </script>

            <div class="vacation-days">
                <?php //Количество доступных дней отпуска
                    if ($maxday)
                    {
                        ?> <span class="days-label">Вам доступно</span>
                        <span class="days-count" id="maxday"><?php echo $maxday - $part1Days - $part2Days - $part3Days?></span>
                        <span class="days-label">дней отпуска</span>

                        <?php
                    }

                    else
                    {
                        ?><span class="days-label"><?php echo $answer; ?></span><?php
                    }
                ?>
            </div>
        </div>

        <div class="container">
            
            <?php
                if ($name and $userPosition)
                {
                    ?><h1> Здравствуйте, <?php echo $name ?>!</h1>


                    <div class="posth1">Здесь вы можете заполнить свои пожелания на отпуск в <?php echo $year ?> году</div>

                    <button class="avalivleAll" onclick="openModal()" title="Показать доступные дни">
                        <i class="fas fa-calendar-alt"></i>
                    </button>
                    
                    
                    <div class="vacation-sections">
                        <?php //Зеленый фон блока
                            if ($employee->lenght1 != 0)
                            {
                                $hid1 = 'style="display: none;"';
                                $succ = "success";
                                $dis1 = "disabled";
                            }
                            else
                            {
                                $hid1 = "";
                                $succ = "";
                                $dis1 = "";
                            }
                        ?>
                        <div class="vacation-section <?php echo $succ ?>">
                            <h2 class="section-title">Часть 1 отпуска</h2>

                            <form method="POST">
                                <div class="date-row">
                                    <div class="form-group">
                                        <label for="part1-month">Месяц</label>
                                        <select id="part1-month" name="part1-month" <?php echo $dis1 ?>>
                                            <option value="<?php echo $monthsToStr[$employee->mon1] ?? ''?>" disabled selected>
                                                <?php echo $monthsToLongStr[$employee->mon1] ?? 'Выберите месяц'?>
                                            </option>
                                            <option value="jan">Январь</option>
                                            <option value="feb">Февраль</option>
                                            <option value="mar">Март</option>
                                            <option value="apr">Апрель</option>
                                            <option value="may">Май</option>
                                            <option value="jun">Июнь</option>
                                            <option value="jul">Июль</option>
                                            <option value="aug">Август</option>
                                            <option value="sep">Сентябрь</option>
                                            <option value="oct">Октябрь</option>
                                            <option value="nov">Ноябрь</option>
                                            <option value="dec">Декабрь</option>
                                        </select>
                                    </div>
                    
                                    <div class="form-group">
                                        <label for="part1-day">Число</label>
                                        <select id="part1-day" name="part1-day" <?php echo $dis1 ?>>
                                            <option value="<?php echo $employee->day1 ?? ''?>" disabled selected>
                                                <?php echo $employee->day1 ?? ''?>
                                            </option>
                    
                                            <?php
                                            
                                            for($i = 1; $i <= 20; $i++)
                                            {
                                                ?><option value="<?php echo $i?>"><?php echo $i?></option><?php
                                            }
                                                
                                            ?>
    
                                        </select>
                                    </div>
                                        
                                </div>
                                        
                                <div id="result" class="availableDays">-</div>
                                        
                                <div class="form-group">
                                    <label for="part1-days">Количество дней отпуска</label>
                                        <select id="part1-days" name="part1-days" <?php echo $dis1 ?>>
                                            <option value="<?php echo $employee->lenght1 ?? ''?>" disabled selected>
                                                <?php echo $employee->lenght1 ?? ''?>
                                            </option>
                                        
                                            <?php
                                            
                                            for($i = $minPartDay; $i <= $maxPartDay; $i++)
                                            {
                                                ?><option value="<?php echo $i?>"><?php echo $i?></option><?php
                                            }
                                                
                                            ?>
    
                                        </select>
                                </div>

                                <div class="date-row">
                                
                                <button type="submit" class="apply-btn" <?php echo $hid1?>>Применить</button>
                                
                            </form>

                            <form method="POST" action="">
                                <input type="hidden" name="cancel1" value="1">
                                <button type="submit" class="apply-btn" onclick="return confirm('Вы уверены, что хотите сбросить 1 часть отпуска?')">
                                    Сбросить
                                </button>
                            </form>

                                </div>
                            
                            <div class="answ-container">
                                            
                                <?php

                                    if($_POST['cancel1'])
                                    {
                                        minusLen($employee->position, $employee->day1, $employee->mon1, $year, $employee->lenght1);

                                        $employee->update([
                                            'mon1' => 0,
                                            'lenght1' => 0,
                                            'day1' => 0
                                            ]);

                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                        exit;
                                    }
                                    elseif($_POST['part1-month'] && $_POST['part1-day'] && $_POST['part1-days'])
                                    {
                                        if($year)
                                        {
                                            $day1 = $_POST['part1-day'];
                                            $month1 = $_POST['part1-month'];
                                            $lenght1 = $_POST['part1-days'];

                                            $conMon1 = convertMonth($monthsToInt[$_POST['part1-month']]);

                                            $thisAvalibleDays = $position->{$conMon1['this']} - $position->{$conMon1['thisEmp']};
                                            $nextAvalibleDays = $position->{$conMon1['next']} - $position->{$conMon1['nextEmp']};

                                            if($lenght1 >= $minPartDay && $lenght1 <= $maxPartDay)
                                            {
                                                $dateArr1 = dateCalc($day1, $month1, $year, $lenght1);

                                                $isCurOk = false;
                                                $isNextOk = false;

                                                if($thisAvalibleDays - $dateArr1[0]['this'] >= 0)
                                                {
                                                    $isCurOk = true;
                                                }
                                                else
                                                {
                                                    echo '<div class="answ">Не хватает в этом месяце</div>';
                                                    $isCurOk = false;
                                                }
                                                if($nextAvalibleDays - $dateArr1[0]['next'] >= 0)
                                                {
                                                    $isNextOk = true;
                                                }
                                                else
                                                {
                                                    echo '<div class="answ">Не хватает в след месяце</div>';
                                                    $isNextOk = false;
                                                }

                                                if($isCurOk && $isNextOk)
                                                {   

                                                    if($lenght1 + $employee->lenght2 + $employee->lenght3 <= $maxday)
                                                    {
                                                        $mon1 = $dateArr1['start']->format('n'); //Месяц без ведущего нуля

                                                        if($employee->lenght1 != 0)
                                                        {
                                                            minusLen($employee->position, $employee->day1, $employee->mon1, $year, $employee->lenght1);
                                                        }

                                                        $employee->update([
                                                            'mon1' => $mon1,
                                                            'lenght1' => $lenght1,
                                                            'day1' => $day1
                                                            ]);

                                                        plusLen($employee->position, $day1, $mon1, $year, $lenght1);

                                                        echo "Данные успешно обновлены!";

                                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                                        exit;
                                                    }

                                                    else
                                                    {
                                                        echo '<div class="answ">Количество всех дней отпуска не должно привышать максимальное значение</div>';
                                                    }

                                                }
                                            }

                                            else
                                            {
                                                echo '<div class="answ">Запрещенная длина отпуска</div>';
                                            }

                                        }

                                        else
                                        {
                                            echo '<div class="answ">Год не найден</div>';
                                        }
                                    }


                                ?>

                            </div>

                            <script>
                                //Подсчет доступных дней
                                const positionData = JSON.parse('<?= json_encode($position->toArray()) ?>');

                                document.getElementById('part1-month').addEventListener('change', function() {
                                    const month = this.value;
                                    const monthsOrder = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 
                                                        'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
                                
                                    const currentIndex = monthsOrder.indexOf(month);
                                    let nextAvailable = 0;

                                    // Расчет доступных дней для текущего месяца
                                    const currentAvailable = (positionData[month] || 0) - (positionData[month+'Emp'] || 0);

                                    // Если выбран не декабрь - считаем для следующего месяца
                                    if (month !== 'dec') {
                                        const nextMonth = monthsOrder[(currentIndex + 1) % 12];
                                        nextAvailable = (positionData[nextMonth] || 0) - (positionData[nextMonth+'Emp'] || 0);
                                    }

                                    // Формируем сообщение с учетом условия
                                    let message = `Доступно ${currentAvailable} дней в этом месяце`;
                                    if (month !== 'dec') {
                                        message += ` и ${nextAvailable} в следующем`;
                                    }

                                    document.getElementById('result').innerHTML = message;
                                });
                            </script>                   
                            
                        </div>

                        <?php //Зеленый фон блока
                            if ($employee->lenght2 != 0)
                            {
                                $hid2 = 'style="display: none;"';
                                $succ2 = "success";
                                $dis2 = "disabled";
                            }
                            else
                            {
                                $hid2 = "";
                                $succ2 = "";
                                $dis3 = "";
                            }
                        ?>

                        <div class="vacation-section <?php echo $succ2 ?>">
                            <h2 class="section-title">Часть 2 отпуска</h2>

                            <form method="POST">
                                <div class="date-row">
                                    <div class="form-group">
                                        <label for="part2-month">Месяц</label>
                                        <select id="part2-month" name="part2-month" <?php echo $dis2 ?>>
                                            <option value="<?php echo $monthsToStr[$employee->mon2] ?? ''?>" disabled selected>
                                                <?php echo $monthsToLongStr[$employee->mon2] ?? 'Выберите месяц'?>
                                            </option>
                                            <option value="jan">Январь</option>
                                            <option value="feb">Февраль</option>
                                            <option value="mar">Март</option>
                                            <option value="apr">Апрель</option>
                                            <option value="may">Май</option>
                                            <option value="jun">Июнь</option>
                                            <option value="jul">Июль</option>
                                            <option value="aug">Август</option>
                                            <option value="sep">Сентябрь</option>
                                            <option value="oct">Октябрь</option>
                                            <option value="nov">Ноябрь</option>
                                            <option value="dec">Декабрь</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="part2-day">Число</label>
                                        <select id="part2-day" name="part2-day" <?php echo $dis2 ?>>
                                            <option value="<?php echo $employee->day2 ?? ''?>" disabled selected>
                                                <?php echo $employee->day2 ?? ''?>
                                            </option>

                                            <?php

                                            for($i = 1; $i <= 20; $i++)
                                            {
                                                ?><option value="<?php echo $i?>"><?php echo $i?></option><?php
                                            }

                                            ?>

                                        </select>
                                    </div>
                                        
                                </div>
                                        
                                <div id="result2" class="availableDays">-</div>
                                        
                                <div class="form-group">
                                    <label for="part2-days">Количество дней отпуска</label>
                                        <select id="part2-days" name="part2-days" <?php echo $dis2 ?>>
                                            <option value="<?php echo $employee->lenght2 ?? ''?>" disabled selected>
                                                <?php echo $employee->lenght2 ?? ''?>
                                            </option>
                                        
                                            <?php

                                            for($i = 1; $i <= $maxPartDay; $i++)
                                            {
                                                ?><option value="<?php echo $i?>"><?php echo $i?></option><?php
                                            }

                                            ?>

                                        </select>
                                </div>
                                        
                                <div class="date-row">
                                        
                                <button type="submit" class="apply-btn" <?php echo $hid2?>>Применить</button>
                                        
                            </form>
                                        
                            <form method="POST" action="">
                                <input type="hidden" name="cancel2" value="1">
                                <button type="submit" class="apply-btn" onclick="return confirm('Вы уверены, что хотите сбросить 2 часть отпуска?')">
                                    Сбросить
                                </button>
                            </form>
                                        
                                </div>
                                        
                            <div class="answ-container">
                                        
                                <?php

                                    if($_POST['cancel2'])
                                    {
                                        minusLen($employee->position, $employee->day2, $employee->mon2, $year, $employee->lenght2);
                                    
                                        $employee->update([
                                            'mon2' => 0,
                                            'lenght2' => 0,
                                            'day2' => 0
                                            ]);
                                        
                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                        exit;
                                    }
                                    elseif($_POST['part2-month'] && $_POST['part2-day'] && $_POST['part2-days'])
                                    {
                                        if($year)
                                        {
                                            $day2 = $_POST['part2-day'];
                                            $month2 = $_POST['part2-month'];
                                            $lenght2 = $_POST['part2-days'];
                                        
                                            $conMon2 = convertMonth($monthsToInt[$_POST['part2-month']]);
                                        
                                            $thisAvalibleDays = $position->{$conMon2['this']} - $position->{$conMon2['thisEmp']};
                                            $nextAvalibleDays = $position->{$conMon2['next']} - $position->{$conMon2['nextEmp']};
                                        
                                            if($lenght2 >= 1 && $lenght2 <= $maxPartDay)
                                            {
                                                $dateArr2 = dateCalc($day2, $month2, $year, $lenght2);
                                            
                                                $isCurOk = false;
                                                $isNextOk = false;
                                            
                                                if($thisAvalibleDays - $dateArr2[0]['this'] >= 0)
                                                {
                                                    $isCurOk = true;
                                                }
                                                else
                                                {
                                                    echo '<div class="answ">Не хватает в этом месяце</div>';
                                                    $isCurOk = false;
                                                }
                                                if($nextAvalibleDays - $dateArr2[0]['next'] >= 0)
                                                {
                                                    $isNextOk = true;
                                                }
                                                else
                                                {
                                                    echo '<div class="answ">Не хватает в след месяце</div>';
                                                    $isNextOk = false;
                                                }
                                            
                                                if($isCurOk && $isNextOk)
                                                {   
                                                
                                                    if($lenght2 + $employee->lenght1 + $employee->lenght3 <= $maxday)
                                                    {
                                                        $mon2 = $dateArr2['start']->format('n'); //Месяц без ведущего нуля
                                                    
                                                        if($employee->lenght2 != 0)
                                                        {
                                                            minusLen($employee->position, $employee->day2, $employee->mon2, $year, $employee->lenght2);
                                                        }
                                                    
                                                        $employee->update([
                                                            'mon2' => $mon2,
                                                            'lenght2' => $lenght2,
                                                            'day2' => $day2
                                                            ]);
                                                        
                                                        plusLen($employee->position, $day2, $mon2, $year, $lenght2);
                                                        
                                                        echo "Данные успешно обновлены!";
                                                        
                                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                                        exit;
                                                    }
                                                
                                                    else
                                                    {
                                                        echo '<div class="answ">Количество всех дней отпуска не должно привышать максимальное значение</div>';
                                                    }
                                                
                                                }
                                            }
                                        
                                            else
                                            {
                                                echo '<div class="answ">Запрещенная длина отпуска</div>';
                                            }
                                        
                                        }
                                    
                                        else
                                        {
                                            echo '<div class="answ">Год не найден</div>';
                                        }
                                    }
                                
                                
                                ?>

                            </div>
                                
                            <script>
                                //Подсчет доступных дней
                                const positionData2 = JSON.parse('<?= json_encode($position->toArray()) ?>');
                                
                                document.getElementById('part2-month').addEventListener('change', function() {
                                    const month2 = this.value;
                                    const monthsOrder2 = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 
                                                        'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
                                
                                    const currentIndex2 = monthsOrder2.indexOf(month2);
                                    let nextAvailable2 = 0;
                                
                                    // Расчет доступных дней для текущего месяца
                                    const currentAvailable = (positionData2[month2] || 0) - (positionData2[month2+'Emp'] || 0);
                                
                                    // Если выбран не декабрь - считаем для следующего месяца
                                    if (month2 !== 'dec') {
                                        const nextMonth2 = monthsOrder2[(currentIndex2 + 1) % 12];
                                        nextAvailable2 = (positionData2[nextMonth2] || 0) - (positionData2[nextMonth2+'Emp'] || 0);
                                    }
                                
                                    // Формируем сообщение с учетом условия
                                    let message2 = `Доступно ${currentAvailable} дней в этом месяце`;
                                    if (month2 !== 'dec') {
                                        message2 += ` и ${nextAvailable2} в следующем`;
                                    }
                                
                                    document.getElementById('result2').innerHTML = message2;
                                                    });
                                                </script>                   

                        </div>
                        <?php //Зеленый фон блока
                            if ($employee->lenght3 != 0)
                            {
                                $hid3 = 'style="display: none;"';
                                $succ3 = "success";
                                $dis3 = "disabled";
                            }
                            else
                            {
                                $hid3 = "";
                                $succ3 = "";
                                $dis3 = "";
                            }
                        ?>

                        <div class="vacation-section <?php echo $succ3 ?>">
                            <h2 class="section-title">Часть 3 отпуска (опционально)</h2>

                            <form method="POST">
                                <div class="date-row">
                                    <div class="form-group">
                                        <label for="part3-month">Месяц</label>
                                        <select id="part3-month" name="part3-month" <?php echo $dis3 ?>>
                                            <option value="<?php echo $monthsToStr[$employee->mon3] ?? ''?>" disabled selected>
                                                <?php echo $monthsToLongStr[$employee->mon3] ?? 'Выберите месяц'?>
                                            </option>
                                            <option value="jan">Январь</option>
                                            <option value="feb">Февраль</option>
                                            <option value="mar">Март</option>
                                            <option value="apr">Апрель</option>
                                            <option value="may">Май</option>
                                            <option value="jun">Июнь</option>
                                            <option value="jul">Июль</option>
                                            <option value="aug">Август</option>
                                            <option value="sep">Сентябрь</option>
                                            <option value="oct">Октябрь</option>
                                            <option value="nov">Ноябрь</option>
                                            <option value="dec">Декабрь</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="part3-day">Число</label>
                                        <select id="part3-day" name="part3-day" <?php echo $dis3 ?>>
                                            <option value="<?php echo $employee->day3 ?? ''?>" disabled selected>
                                                <?php echo $employee->day3 ?? ''?>
                                            </option>

                                            <?php

                                            for($i = 1; $i <= 20; $i++)
                                            {
                                                ?><option value="<?php echo $i?>"><?php echo $i?></option><?php
                                            }

                                            ?>

                                        </select>
                                    </div>
                                        
                                </div>
                                        
                                <div id="result3" class="availableDays">-</div>
                                        
                                <div class="form-group">
                                    <label for="part3-days">Количество дней отпуска</label>
                                        <select id="part3-days" name="part3-days" <?php echo $dis3 ?>>
                                            <option value="<?php echo $employee->lenght3 ?? ''?>" disabled selected>
                                                <?php echo $employee->lenght3 ?? ''?>
                                            </option>
                                        
                                            <?php

                                            for($i = 1; $i <= $maxPartDay; $i++)
                                            {
                                                ?><option value="<?php echo $i?>"><?php echo $i?></option><?php
                                            }

                                            ?>

                                        </select>
                                </div>
                                        
                                <div class="date-row">
                                        
                                <button type="submit" class="apply-btn" <?php echo $hid3?>>Применить</button>
                                        
                            </form>
                                        
                            <form method="POST" action="">
                                <input type="hidden" name="cancel3" value="1">
                                <button type="submit" class="apply-btn" onclick="return confirm('Вы уверены, что хотите сбросить 3 часть отпуска?')">
                                    Сбросить
                                </button>
                            </form>
                                        
                                </div>
                                        
                            <div class="answ-container">
                                        
                                <?php

                                    if($_POST['cancel3'])
                                    {
                                        minusLen($employee->position, $employee->day3, $employee->mon3, $year, $employee->lenght3);
                                    
                                        $employee->update([
                                            'mon3' => 0,
                                            'lenght3' => 0,
                                            'day3' => 0
                                            ]);
                                        
                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                        exit;
                                    }
                                    elseif($_POST['part3-month'] && $_POST['part3-day'] && $_POST['part3-days'])
                                    {
                                        if($year)
                                        {
                                            $day3 = $_POST['part3-day'];
                                            $month3 = $_POST['part3-month'];
                                            $lenght3 = $_POST['part3-days'];
                                        
                                            $conMon3 = convertMonth($monthsToInt[$_POST['part3-month']]);
                                        
                                            $thisAvalibleDays = $position->{$conMon3['this']} - $position->{$conMon3['thisEmp']};
                                            $nextAvalibleDays = $position->{$conMon3['next']} - $position->{$conMon3['nextEmp']};
                                        
                                            if($lenght3 >= 1 && $lenght3 <= $maxPartDay)
                                            {
                                                $dateArr3 = dateCalc($day3, $month3, $year, $lenght3);
                                            
                                                $isCurOk = false;
                                                $isNextOk = false;
                                            
                                                if($thisAvalibleDays - $dateArr3[0]['this'] >= 0)
                                                {
                                                    $isCurOk = true;
                                                }
                                                else
                                                {
                                                    echo '<div class="answ">Не хватает в этом месяце</div>';
                                                    $isCurOk = false;
                                                }
                                                if($nextAvalibleDays - $dateArr3[0]['next'] >= 0)
                                                {
                                                    $isNextOk = true;
                                                }
                                                else
                                                {
                                                    echo '<div class="answ">Не хватает в след месяце</div>';
                                                    $isNextOk = false;
                                                }
                                            
                                                if($isCurOk && $isNextOk)
                                                {   
                                                
                                                    if($lenght3 + $employee->lenght1 + $employee->lenght2 <= $maxday)
                                                    {
                                                        $mon3 = $dateArr3['start']->format('n'); //Месяц без ведущего нуля
                                                    
                                                        if($employee->lenght3 != 0)
                                                        {
                                                            minusLen($employee->position, $employee->day3, $employee->mon3, $year, $employee->lenght3);
                                                        }
                                                    
                                                        $employee->update([
                                                            'mon3' => $mon3,
                                                            'lenght3' => $lenght3,
                                                            'day3' => $day3
                                                            ]);
                                                        
                                                        plusLen($employee->position, $day3, $mon3, $year, $lenght3);
                                                        
                                                        echo "Данные успешно обновлены!";
                                                        
                                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                                        exit;
                                                    }
                                                
                                                    else
                                                    {
                                                        echo '<div class="answ">Количество всех дней отпуска не должно привышать максимальное значение</div>';
                                                    }
                                                
                                                }
                                            }
                                        
                                            else
                                            {
                                                echo '<div class="answ">Запрещенная длина отпуска</div>';
                                            }
                                        
                                        }
                                    
                                        else
                                        {
                                            echo '<div class="answ">Год не найден</div>';
                                        }
                                    }
                                
                                
                                ?>

                            </div>
                                
                            <script>
                                //Подсчет доступных дней
                                const positionData3 = JSON.parse('<?= json_encode($position->toArray()) ?>');
                                
                                document.getElementById('part3-month').addEventListener('change', function() {
                                    const month3 = this.value;
                                    const monthsOrder3 = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 
                                                        'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
                                
                                    const currentIndex3 = monthsOrder3.indexOf(month3);
                                    let nextAvailable3 = 0;
                                
                                    // Расчет доступных дней для текущего месяца
                                    const currentAvailable3 = (positionData3[month3] || 0) - (positionData3[month3+'Emp'] || 0);
                                
                                    // Если выбран не декабрь - считаем для следующего месяца
                                    if (month3 !== 'dec') {
                                        const nextMonth3 = monthsOrder3[(currentIndex3 + 1) % 12];
                                        nextAvailable3 = (positionData3[nextMonth3] || 0) - (positionData3[nextMonth3+'Emp'] || 0);
                                    }
                                
                                    // Формируем сообщение с учетом условия
                                    let message3 = `Доступно ${currentAvailable3} дней в этом месяце`;
                                    if (month3 !== 'dec') {
                                        message3 += ` и ${nextAvailable3} в следующем`;
                                    }
                                
                                    document.getElementById('result3').innerHTML = message3;
                                });
                            </script>

                        </div>

                    </div>

                    <?php //Зеленый фон блока
                        if (isset($employee->comment))
                        {
                            $hid4 = 'style="display: none;"';
                            $succ4 = "success";
                            $dis4 = "disabled";
                        }
                        else
                        {
                            $hid4 = "";
                            $succ4 = "";
                            $dis4 = "";
                        }
                    ?>

                    <div class="vacation-section <?php echo $succ4 ?>">
                            <h2 class="section-title">Дополнительный комментарий</h2>

                            <form method="POST">                       
                                        
                                <div class="form-group">
                                    <label for="comment" class="dopcom">Здесь вы можете оставить дополнитеьный комментарий (Не обязательно для заполнения)</label>
                                        <input class="comment" name="comment" <?php echo $dis4 ?> placeholder="Например ребенок идет в 1 класс" value="<?php echo $employee->comment ?? ''; ?>">
                                </div>
                                        
                                <div class="date-row">
                                        
                                <button type="submit" class="apply-btn" <?php echo $hid4?>>Применить</button>
                                        
                            </form>
                                        
                            <form method="POST" action="">
                                <input type="hidden" name="cancel4" value="1">
                                <button type="submit" class="apply-btn" onclick="return confirm('Вы уверены, что хотите сбросить комментарий?')">
                                    Сбросить
                                </button>
                            </form>
                                        
                                </div>
                                        
                            <div class="answ-container">
                                        
                                <?php

                                    if($_POST['cancel4'])
                                    {
                                        minusLen($employee->position, $employee->day3, $employee->mon3, $year, $employee->lenght3);
                                    
                                        $employee->update([
                                            'comment' => null
                                            ]);
                                        
                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                        exit;
                                    }
                                    elseif($_POST['comment'])
                                    {
                                        $com = $_POST['comment'];

                                        $employee->update([
                                            'comment' => $com
                                            ]);

                                        echo "Данные успешно обновлены!";
                                                        
                                        echo '<script>location.href="' . $_SERVER['PHP_SELF'] . '?id=' . $employee->id . '"</script>';
                                        exit;
                                    }
                                ?>

                            </div>
                        </div>

                <button type="button" class="save-btn" onclick="confirmSave()">Сохранить изменения</button>

                <script>
                    function confirmSave() {
                        if (confirm("После нажатия на эту кнопку, данные сохранятся и их больше нельзя будет изменить. Продолжить?")) {
                            // Получаем ID из URL
                            const urlParams = new URLSearchParams(window.location.search);
                            const id = urlParams.get('id');
                            
                            // Отправляем данные на сервер
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = 'confirmUser.php';
                            
                            // Добавляем скрытое поле для подтверждения
                            const confirmInput = document.createElement('input');
                            confirmInput.type = 'hidden';
                            confirmInput.name = 'confirm_save';
                            confirmInput.value = '1';
                            form.appendChild(confirmInput);
                            
                            // Добавляем скрытое поле с ID
                            const idInput = document.createElement('input');
                            idInput.type = 'hidden';
                            idInput.name = 'id';
                            idInput.value = id;
                            form.appendChild(idInput);
                            
                            // Добавляем форму на страницу и отправляем
                            document.body.appendChild(form);
                            form.submit();
                        }
                    }
                </script>

                <?php
                }

                else
                {
                    ?><h1 class = "notUser">:(</h1><?php
                }

            ?>
        </div>
    </div>

    <script>
        // Сохраняем позицию прокрутки перед обновлением страницы
        window.addEventListener('beforeunload', function() {
            localStorage.setItem('scrollPosition', window.scrollY);
        });

        // Восстанавливаем позицию после загрузки страницы
        window.addEventListener('load', function() {
            const savedPosition = localStorage.getItem('scrollPosition');
            if (savedPosition) {
                window.scrollTo(0, savedPosition);
                localStorage.removeItem('scrollPosition');
            }
            
            // Дополнительно: плавная прокрутка
            setTimeout(() => {
                window.scrollTo({
                    top: savedPosition,
                    behavior: 'smooth'
                });
            }, 100);
        });
    </script>
</body>

</html>