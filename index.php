<?php

    require_once 'auth.php';
    require "functions.php"; //Функции PHP

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройка</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <?php $positions = \Models\Position::orderBy('position', 'asc')->get(['position'])->toArray(); //Получаем все должности из БД ?>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="/index.php" class="active">
                <i class="fas fa-cog"></i> Настройки
            </a>
            <a href="/report.php">
                <i class="fas fa-chart-bar"></i> Отчёт
            </a>
            <a href="/clear.php" style="color: #e63946; font-weight: bold;">
                <i class="fas fa-trash-alt"></i> Очистить БД
            </a>
        </div>

        <div class="form-group">

            <select id="position" class="dropdown">
                <option value="" disabled selected>Выберите должность</option>
                    <?php foreach ($positions as $row): ?>
                        <option value="<?= htmlspecialchars($row['position']) ?>">
                    <?= htmlspecialchars($row['position']) ?>
                </option>
                    <?php endforeach; ?>
            </select>
    
            <script>
            document.getElementById('position').addEventListener('change', function() {
                const selectedPosition = this.value;
                
                if (selectedPosition) {
                    // 1. Создаем новый URL с параметром position
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('position', selectedPosition);
                    
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

        </div>
    </nav>

    <?php //Загрузка кортежа с выбранной должностью

    $tupleOfCurrentPos = \Models\Position::where('position', '=', $_GET['position'])->get()->toArray();
    $params = \Models\Params::find(1);

    ?>

    <div class="admin-container">
        <form method="POST">
            <h1>Настройка дней отпуска</h1>
            
            <div class="year-card">
                <h3>Год</h3>
                <input name="year" type="number" placeholder="Год" class="days-input" value="<?= $_POST['year'] ?? $params->year?>" min="2025" max="2200">
            </div>
        
            <div class="months-container">
                <?php
                $months = [
                    'jan' => 'Январь',
                    'feb' => 'Февраль',
                    'mar' => 'Март',
                    'apr' => 'Апрель',
                    'may' => 'Май',
                    'jun' => 'Июнь',
                    'jul' => 'Июль',
                    'aug' => 'Август',
                    'sep' => 'Сентябрь',
                    'oct' => 'Октябрь',
                    'nov' => 'Ноябрь',
                    'dec' => 'Декабрь'
                ];
                
                foreach ($months as $key => $name) {
                    $value = $_POST[$key] ?? ($tupleOfCurrentPos[0][$key] ?? '');
                    ?>
                    <div class="month-card">
                        <h3><?= htmlspecialchars($name) ?></h3>
                        <input name="<?= $key ?>" type="number" placeholder="Дней" class="days-input" 
                            value="<?= htmlspecialchars($value) ?>">
                    </div>
                    <?php
                }
                ?>                
            </div>

            <!-- Кнопка сохранения -->
            <button class="save-btn" type="submit">Сохранить</button>
        </form>

    <?php

    // Получаем объект модели (не преобразуем в массив!)
    $position = \Models\Position::where('position', $_GET['position'])->first();

    if ($_POST && $position && $params) {
        $position->update([
            'jan' => $_POST['jan'] ?? '0',
            'feb' => $_POST['feb'] ?? '0',
            'mar' => $_POST['mar'] ?? '0',
            'apr' => $_POST['apr'] ?? '0',
            'may' => $_POST['may'] ?? '0',
            'jun' => $_POST['jun'] ?? '0',
            'jul' => $_POST['jul'] ?? '0',
            'aug' => $_POST['aug'] ?? '0',
            'sep' => $_POST['sep'] ?? '0',
            'oct' => $_POST['oct'] ?? '0',
            'nov' => $_POST['nov'] ?? '0',
            'dec' => $_POST['dec'] ?? '0',
        ]);

        $params->update(['year' => $_POST['year']]);
    }
    ?>


</body>

<?php //clearPositionsData(); ?>

</html>