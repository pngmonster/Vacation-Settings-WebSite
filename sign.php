<?php

    require "config.php"; //Подключение к БД
    require "functions.php"; //Функции PHP

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./styles/sign.css">
    <style>
        .answ {
            margin-top: 15px;
            font-size: 1rem;
            text-align: center;
            color: red;
            flex: 1;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-user-shield"></i> Авторизация</h2>
        </div>
        <div class="login-body">

            <form method="POST">
                <div class="form-group">
                    <label for="fullname">ФИО полностью</label>
                    <input type="text" name="fullname" class="form-control" placeholder="Введите ваше ФИО" required>
                </div>

                <?php $positions = \Models\Position::orderBy('position', 'asc')->get(['position'])->toArray(); //Получаем все должности из БД ?>

                <div class="form-group">
                    <label for="position">Должность</label>

                    <select name="position" class="form-control dropdown" required> <!--Отображение должностей-->
                       <option value="" disabled selected>Выберите должность</option>

                        <?php foreach ($positions as $row): ?>
                        <option value="<?= htmlspecialchars($row['position']) ?>">
                            <?= htmlspecialchars($row['position']) ?>
                        </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-checkbox">
                    <label class="simple-checkbox">
                        <input type="checkbox" name="agreement" required>
                        Согласен на обработку персональных данных
                    </label>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Войти
                </button>
            </form>

            <?php

            if ($_POST)
            {
                $fio = textToFio($_POST["fullname"]);
                $position = $_POST["position"] ?? null;

                if ($fio != 0 && $position) {
                    $fam = $fio[0];
                    $name = $fio[1];
                    $otch = $fio[2];
                
                    // Ищем существующую запись
                    $employee = \Models\Employees::where([
                        ['fam', '=', $fam],
                        ['name', '=', $name],
                        ['otch', '=', $otch],
                        ['position', '=', $position]
                    ])->first();
                    
                    if (!$employee) {
                        // Создаем новую запись
                        $employee = \Models\Employees::create([
                            'fam' => $fam,
                            'name' => $name,
                            'otch' => $otch,
                            'position' => $position
                        ]);
                    }
                
                    // 1. Проверяем, были ли уже отправлены заголовки
                    // Получаем текущий домен динамически
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $domain = $_SERVER['HTTP_HOST'];
                    $baseUrl = $protocol . $domain;

                    // 1. Проверяем, были ли уже отправлены заголовки
                    if (headers_sent()) {
                        $redirectUrl = $baseUrl . "/user.php?id=" . urlencode($employee->id);
                        die("<script>window.location.href='{$redirectUrl}';</script>");
                    }

                    // 2. Очищаем буфер вывода
                    ob_clean();

                    // 3. Используем абсолютный URL с динамическим определением домена
                    $redirectUrl = $baseUrl . "/user.php?id=" . urlencode($employee->id);
                    header("Location: " . $redirectUrl);
                    exit(); // Всегда вызывайте exit после header Location

                    // 4. Добавляем дополнительный JavaScript редирект
                    echo "<script>window.location.href='{$redirectUrl}';</script>";
                    exit();

                }
                
                else {

                    if ($fio === 0)
                    {
                        echo '<div class="answ">Неверно указанно ФИО</div>';
                    }
                    elseif ($position === 0)
                    {
                        echo '<div class="answ">Должность не выбрана</div>';
                    }
                    else
                    {
                        echo '<div class="answ">Неизвестная ошибка</div>';
                    }
                }
            }
            
            ?>
        </div>
    </div>
</body>

</html>