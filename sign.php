<?php

    require "config.php"; //Подключение к БД
    require "functions.php"; //Функции PHP

    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        // Сотрудник выбирается из списка (cur_emp) по id: ФИО и должность берутся из БД,
        // а не из текста формы, поэтому ФИО может состоять из любого числа слов.
        $curId = (int)($_POST['emp'] ?? 0);
        $curEmp = $curId ? \Models\Cur_emp::find($curId) : null;

        if (!$curEmp)
        {
            $error = 'Выберите свои ФИО из списка';
        }
        elseif (empty($_POST['agreement']))
        {
            $error = 'Нужно согласие на обработку персональных данных';
        }
        else
        {
            $employee = findOrCreateEmployee($curEmp);

            // Абсолютный URL с динамическим определением домена
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            header("Location: " . $protocol . $_SERVER['HTTP_HOST'] . "/user.php?id=" . urlencode($employee->id));
            exit();
        }
    }

    $items = \Models\Cur_emp::orderBy('fio', 'asc')->orderBy('position', 'asc')->get(['id', 'fio', 'position'])->toArray();

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
    <link rel="stylesheet" href="./styles/combobox.css">
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

            <?php if (!$items): ?>

                <div class="answ">Список сотрудников ещё не загружен. Обратитесь к администратору.</div>

            <?php else: ?>

            <form method="POST" id="sign-form">

                <div class="form-group">
                    <label for="fio-input">Ваше ФИО</label>
                    <div class="combo">
                        <input type="text" id="fio-input" class="combo-input" placeholder="Начните вводить фамилию"
                               autocapitalize="off" spellcheck="false" enterkeyhint="search" aria-controls="fio-list">
                        <ul id="fio-list" class="combo-list" hidden></ul>
                    </div>
                    <input type="hidden" name="emp" id="fio-id">
                    <div class="pos-info" id="pos-info" hidden>Должность: <strong id="pos-name"></strong></div>
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

            <div class="answ" id="answ"><?= $error ? esc($error) : '' ?></div>

            <noscript><div class="answ">Для входа нужен включённый JavaScript.</div></noscript>

            <script src="./assets/fio-combobox.js"></script>
            <script>
                (function () {
                    var items = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                    var input = document.getElementById('fio-input');
                    var hidden = document.getElementById('fio-id');
                    var info = document.getElementById('pos-info');
                    var answ = document.getElementById('answ');

                    var combo = FioCombobox.init({
                        input: input,
                        list: document.getElementById('fio-list'),
                        hidden: hidden,
                        items: items,
                        onSelect: function (item) {
                            answ.textContent = '';
                            if (item) {
                                document.getElementById('pos-name').textContent = item.position;
                                info.hidden = false;
                            } else {
                                info.hidden = true;
                            }
                        }
                    });

                    // Нельзя отправить форму, пока ФИО не выбрано из списка
                    document.getElementById('sign-form').addEventListener('submit', function (e) {
                        if (!hidden.value) {
                            e.preventDefault();
                            answ.textContent = 'Выберите свои ФИО из списка подсказок';
                            input.focus();
                        }
                    });
                })();
            </script>

            <?php endif; ?>
        </div>
    </div>
</body>

</html>
