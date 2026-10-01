<?php

    require_once 'auth.php';
    require "functions.php"; //Функции PHP

    $queryParams = $_GET;
    $queryParams['show_all'] = 1;
    $showAllUrl = '?' . http_build_query($queryParams);

    // Удаление только через POST (ссылка/обновление страницы больше не удаляют записи)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        if ($id > 0) {
            deleteEmployee($id, $year); // вернёт дни в счётчики должности (кроме частей администратора)
        }
        // Post/Redirect/Get: возвращаемся на ту же страницу с теми же фильтрами
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($_GET ? '?' . http_build_query($_GET) : ''));
        exit;
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
    <link rel="stylesheet" href="./styles/combobox.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <?php
        $positions = \Models\Position::orderBy('position', 'asc')->get(['position'])->toArray(); //Получаем все должности из БД
        $maxdays = \Models\Position::pluck('maxday', 'position')->all();
        $sets = controlSets(); // «Не приступили» и «Заполнили не до конца»
        $curMap = curIdMap();  // для иконки «Редактировать отпуск» (переход в assign.php)
    ?>

    <nav class="navbar">
        <?php $adminActive = 'report'; include 'admin_nav.php'; ?>

        <?php//Список должностей?>
        <div class="form-group">
            <select id="position" class="dropdown">
                <option value="" disabled selected>Выберите должность</option>
                <option value="all">Все</option>
                <optgroup label="Контроль заполнения">
                    <option value="__not_started">Не приступили к заполнению (<?= count($sets['notStarted']) ?>)</option>
                    <option value="__incomplete">Заполнили не до конца (<?= count($sets['incomplete']) ?>)</option>
                </optgroup>
                <optgroup label="Должности">
                    <?php foreach ($positions as $row): ?>
                        <option value="<?= htmlspecialchars($row['position']) ?>">
                    <?= htmlspecialchars($row['position']) ?>
                </option>
                    <?php endforeach; ?>
                </optgroup>
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
        $mode = 'normal';
        $searchItems = \Models\Cur_emp::orderBy('fio', 'asc')->orderBy('position', 'asc')->get(['id', 'fio', 'position'])->toArray(); // для подсказок поиска
        $empId = validId($_GET['emp'] ?? ''); // сотрудник, выбранный в поиске по подсказке
        $personCur = $empId ? \Models\Cur_emp::find($empId) : null;

        if ($empId)
        {
            $mode = 'person';
            $countEmp = 0;
            if ($personCur) {
                $countEmp = 1;
                $record = findEmployeeForCur($personCur);
                if ($record) {
                    $employees = [$record];
                } else { // в списке есть, но записи отпуска ещё нет
                    $notStartedShown = [['id' => (int)$personCur->id, 'fio' => $personCur->fio, 'position' => $personCur->position, 'employee' => null]];
                }
            }
        }
        elseif (isset($_GET['position']))
        {
            $curPos = $_GET['position'];
            $searchText = trim((string)($_GET['search'] ?? ''));
            $limitRows = isset($_GET['show_all']) ? null : 3; // как и раньше: сначала 3 записи, остальные по кнопке

            if ($curPos === '__not_started') {
                // Не приступили к заполнению (не ходили на сайт или ничего не выбрали)
                $mode = 'not_started';
                $rows = array_values(array_filter($sets['notStarted'], function ($r) use ($searchText) {
                    return fioMatches($r['fio'], $searchText);
                }));
                $countEmp = count($rows);
                $notStartedShown = $limitRows ? array_slice($rows, 0, $limitRows) : $rows;
            } elseif ($curPos === '__incomplete') {
                // Заполнили не до конца: выбрана хотя бы одна часть, но дней меньше maxday или не подтверждён
                $mode = 'incomplete';
                $rows = array_values(array_filter($sets['incomplete'], function ($e) use ($searchText) {
                    return fioMatches(displayFio($e), $searchText);
                }));
                $countEmp = count($rows);
                $employees = $limitRows ? array_slice($rows, 0, $limitRows) : $rows;
            } else {
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
    }

    ?>

    <div class="vacation-container has-combo">
        <div class="vacation-header">
            <i class="fas fa-calendar-alt"></i> Всего записей - <?php echo $countEmp ?? 0; ?>
        </div>

        <!-- Поиск сотрудников -->
        <form method="get" class="search-form">
            <?php foreach ($_GET as $key => $value): ?>
                <?php if ($key !== 'search' && $key !== 'emp'): ?>
                    <input type="hidden" name="<?= esc($key) ?>" value="<?= esc(is_array($value) ? '' : $value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
                
            <div class="combo">
                <input type="text" name="search" id="fio-input" class="combo-input" placeholder="Поиск по ФИО"
                       value="<?= esc($_GET['search'] ?? '') ?>" autocapitalize="off" spellcheck="false"
                       enterkeyhint="search" aria-controls="fio-list">
                <ul id="fio-list" class="combo-list" hidden></ul>
            </div>
            <input type="hidden" id="fio-id">
            <button type="submit" aria-label="Найти"><i class="fas fa-search"></i></button>
        </form>

        <?php if ($personCur): ?>
            <?php
                $resetParams = $_GET;
                unset($resetParams['emp'], $resetParams['search'], $resetParams['show_all']);
                $resetUrl = '?' . http_build_query($resetParams ?: ['position' => 'all']);
            ?>
            <div class="person-filter">
                <span>Показан сотрудник: <strong><?= esc($personCur->fio) ?></strong></span>
                <a href="<?= esc($resetUrl) ?>"><i class="fas fa-times"></i> Показать всех</a>
            </div>
        <?php endif; ?>

        <ul class="employees-list">
            
            <?php foreach ($notStartedShown ?? [] as $item): ?>
                <li class="employee-card incomplete">
                    <div class="card-head">
                        <div class="employee-name"><?= esc($item['fio']) ?></div>
                                                <?php if (!empty($item['id'])): ?>
                            <a class="edit-btn" href="assign.php?cur=<?= (int)$item['id'] ?>" title="Ввести отпуск"
                               aria-label="Ввести отпуск: <?= esc($item['fio']) ?>"><i class="fas fa-pen-to-square"></i></a>
                        <?php else: ?>
                            <span class="edit-btn disabled" title="Сотрудника нет в загруженном списке — редактирование недоступно"
                                  aria-disabled="true"><i class="fas fa-pen-to-square"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="employee-position"><?= esc($item['position']) ?></div>
                    <div class="status-line incomplete">
                        <i class="fas fa-hourglass-start"></i>
                        <div>
                            <span class="status-title">Не приступил(а) к заполнению</span>
                            <span class="status-detail"><?= $item['employee'] ? 'Заходил(а) на сайт, но ничего не выбрал(а)' : 'Ещё не заходил(а) на сайт' ?></span>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>

            <?php foreach ($employees ?? [] as $employee): ?>
                <?php $st = vacationStatus($employee, $maxdays[$employee->position] ?? 0); ?>
                <li class="employee-card<?= $st['complete'] ? ' complete' : ' incomplete' ?>" id="employee-<?= $employee->id ?>">
                    <?php $editId = curIdForEmployee($employee, $curMap); ?>
                    <div class="card-head">
                        <div class="employee-name"><?php echo esc(displayFio($employee))?></div>
                                                <?php if ($editId): ?>
                            <a class="edit-btn" href="assign.php?cur=<?= (int)$editId ?>" title="Редактировать отпуск"
                               aria-label="Редактировать отпуск: <?= esc(displayFio($employee)) ?>"><i class="fas fa-pen-to-square"></i></a>
                        <?php else: ?>
                            <span class="edit-btn disabled" title="Сотрудника нет в загруженном списке — редактирование недоступно"
                                  aria-disabled="true"><i class="fas fa-pen-to-square"></i></span>
                        <?php endif; ?>
                        <button class="delete-btn" title="Удалить сотрудника" aria-label="Удалить сотрудника" onclick="confirmDelete(<?= (int)$employee->id ?>, <?= esc(json_encode(upfl($employee->name) . ' ' . upfl($employee->otch), JSON_UNESCAPED_UNICODE)) ?>)">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>

                    <div class="employee-position"><?php echo esc($employee->position)?></div>

                    <div class="status-line <?= $st['complete'] ? 'complete' : 'incomplete' ?>">
                        <i class="fas <?= $st['complete'] ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <div>
                            <span class="status-title"><?= esc($st['title']) ?></span>
                            <span class="status-detail"><?= esc($st['detail']) ?></span>
                        </div>
                    </div>

                    <div class="vacation-parts">
                        <div class="vacation-part<?= $employee->admin1 ? ' by-admin' : '' ?>">

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
                                    <div class="part-title">1 Часть<?php if ($employee->admin1): ?> <span class="badge badge-admin"><i class="fas fa-user-shield"></i> Введено администратором</span><?php endif; ?></div>
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
                        <div class="vacation-part<?= $employee->admin2 ? ' by-admin' : '' ?>">
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
                                    <div class="part-title">2 Часть<?php if ($employee->admin2): ?> <span class="badge badge-admin"><i class="fas fa-user-shield"></i> Введено администратором</span><?php endif; ?></div>
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
                        <div class="vacation-part<?= $employee->admin3 ? ' by-admin' : '' ?>">
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
                                    <div class="part-title">3 Часть<?php if ($employee->admin3): ?> <span class="badge badge-admin"><i class="fas fa-user-shield"></i> Введено администратором</span><?php endif; ?></div>
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
                            <span class="date-value"><?php echo esc($com ?? "Нет комментария")?></span>
                        </div>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>

        <script>
            function confirmDelete(id, name) {
                if (confirm('Удалить ' + name + '?')) {
                    // Отправляем POST на текущий адрес (фильтры в URL сохраняются)
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_id';
                    input.value = id;
                    form.appendChild(input);

                    document.body.appendChild(form);
                    form.submit();
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

    <script src="./assets/fio-combobox.js"></script>
    <script>
        // Поиск по ФИО с подсказками: выбор из списка открывает этого сотрудника;
        // обычный ввод + Enter/лупа по-прежнему фильтрует список по части ФИО.
        FioCombobox.init({
            input: document.getElementById('fio-input'),
            list: document.getElementById('fio-list'),
            hidden: document.getElementById('fio-id'),
            items: <?= json_encode($searchItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            onSelect: function (item) {
                if (!item) { return; }
                var url = new URL(window.location.href);
                url.searchParams.set('emp', item.id);
                ['search', 'show_all', 'delete_id'].forEach(function (k) { url.searchParams.delete(k); });
                if (!url.searchParams.get('position')) { url.searchParams.set('position', 'all'); }
                window.location.href = url.toString();
            }
        });
    </script>
</body>

</html>