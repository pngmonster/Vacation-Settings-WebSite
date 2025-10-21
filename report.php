<?php

    require_once 'auth.php';
    require "functions.php"; //Функции PHP

    $queryParams = $_GET;
    $queryParams['show_all'] = 1;
    $showAllUrl = '?' . http_build_query($queryParams);

    if (isset($_GET['delete_id'])) {
        $id = (int)$_GET['delete_id'];
        if ($id > 0) {
            $employee = \Models\Employees::find($id);
            minusLen($employee->position, $employee->day1, $employee->mon1, $year, $employee->lenght1);
            minusLen($employee->position, $employee->day2, $employee->mon2, $year, $employee->lenght2);
            minusLen($employee->position, $employee->day3, $employee->mon3, $year, $employee->lenght3);
            \Models\Employees::destroy($id);
        }
    }
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <?php $positions = \Models\Position::orderBy('position', 'asc')->get(['position'])->toArray(); //Получаем все должности из БД ?>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="/index.php">
                <i class="fas fa-cog"></i> Настройки
            </a>
            <a href="/report.php" class="active">
                <i class="fas fa-chart-bar"></i> Отчёт
            </a>
            <a href="/clear.php" style="color: #e63946; font-weight: bold;">
                <i class="fas fa-trash-alt"></i> Очистить БД
            </a>
        </div>

        <?php//Список должностей?>
        <div class="form-group">
            <select id="position" class="dropdown">
                <option value="" disabled selected>Выберите должность</option>
                <option value="all">Все</option>
                    <?php foreach ($positions as $row): ?>
                        <option value="<?= htmlspecialchars($row['position']) ?>">
                    <?= htmlspecialchars($row['position']) ?>
                </option>
                    <?php endforeach; ?>
            </select>
        </div>

        <script>
            document.getElementById('position').addEventListener('change', function() {
                const selectedPosition = this.value;
                
                if (selectedPosition) {
                    // 1. Создаем новый URL с параметром position
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('position', selectedPosition);
                    newUrl.searchParams.delete('show_all');
                    newUrl.searchParams.delete('delete_id');
                    
                    // 2. Обновляем URL без перезагрузки страницы
                    window.location.href = newUrl.toString();
                }
            });

            // При загрузке страницы проверяем есть ли параметр в URL
            window.addEventListener('load', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const savedPosition = urlParams.get('position');
                
                if (savedPosition) {
                    // Восстанавливаем выбранное значение
                    document.getElementById('position').value = savedPosition;
                }
            });
        </script>
    </nav>

    <?php
        if (isset($_GET['position']))
        {
            $curPos = $_GET['position'];

            // Загружаем сотрудников
            if ($curPos === 'all') {
                $employees = \Models\Employees::orderBy('position', 'asc')
                                ->orderBy('fam', 'asc');
            } else {
                $employees = \Models\Employees::where('position', $curPos)
                                ->orderBy('fam', 'asc');
            }

            // Фильтр по поиску (фамилия или имя)
            if (!empty($_GET['search'])) {
                $search = trim($_GET['search']);

                $employees->where(function($q) use ($search) {
                    $q->where('fam', 'ILIKE', "%{$search}%")
                      ->orWhere('name', 'ILIKE', "%{$search}%")
                      ->orWhere('otch', 'ILIKE', "%{$search}%");
                });
            }

            // Считаем сотрудников
            $countEmp = $employees->count();

            // Определяем лимит
            $showAll = isset($_GET['show_all']);
            $limit = $showAll ? null : 3;

            if ($limit) {
                $employees->take($limit);
            }

            $employees = $employees->get();
    }

    ?>

    <div class="vacation-container">
        <div class="vacation-header">
            <i class="fas fa-calendar-alt"></i> Всего записей - <?php echo $countEmp ?? 0; ?>
        </div>

        <!-- Поиск сотрудников -->
        <form method="get" class="search-form">
            <?php foreach ($_GET as $key => $value): ?>
                <?php if ($key !== 'search'): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
                
            <input type="text" name="search" placeholder="Поиск по фамилии"
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>

        <ul class="employees-list">
            
            <?php foreach ($employees as $employee): ?>
                <li class="employee-card" id="employee-<?= $employee->id ?>">
                    <div class="between">
                        <div class="employee-name"><?php echo upfl($employee->fam) . " " . upfl($employee->name) . " " . upfl($employee->otch)?></div>
                        <button class="delete-btn" onclick="confirmDelete(<?= $employee->id ?>, '<?= upfl($employee->name) ?> <?= upfl($employee->otch) ?>')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>

                    <div class="employee-position"><?php echo $employee->position?></div>

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
            <?php endforeach; ?>
        </ul>

        <script>
            function confirmDelete(id, name) {
                if (confirm('Удалить ' + name + '?')) {
                    // Создаем объект URL из текущего адреса
                    const url = new URL(window.location.href);
                    
                    // Добавляем параметр delete_id
                    url.searchParams.set('delete_id', id);
                    
                    // Переходим по новому URL
                    window.location.href = url.toString();
                }
            }
        </script>
        
        <div class="buttonsCont">

            <button class="save-btn" onclick="window.location.href='<?= $showAllUrl ?>'">
                <i class="fas fa-th-list"></i> Отобразить все
            </button>

            <form action="downloadExcel.php" method="post">
            <input type="hidden" name="filter" value="current">
            <button type="submit" class="save-btn">
                <i class="fas fa-file-excel"></i> Сохранить в Excel 
            </button>
            </form>

        </div>

    </div>

    
</form>

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