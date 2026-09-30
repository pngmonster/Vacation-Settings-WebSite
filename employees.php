<?php
    require_once 'auth.php';
    require "functions.php"; //Функции PHP

    $errors = [];       // ошибки файла (с номерами строк)
    $fileError = null;  // ошибка самого файла (не Excel, слишком большой и т.п.)
    $message = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $file = $_FILES['list'] ?? null;
        $ext = $file ? strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) : '';

        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $fileError = 'Выберите файл.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $fileError = 'Файл не загрузился (код ошибки ' . (int)$file['error'] . '). Возможно, он слишком большой.';
        } elseif (!in_array($ext, ['xlsx', 'xls'], true)) {
            $fileError = 'Нужен файл Excel (.xlsx или .xls).';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $fileError = 'Файл больше 5 МБ.';
        } else {
            try {
                $sheetRows = readSpreadsheetRows($file['tmp_name']);
                $result = validateEmployeeRows($sheetRows, allowedPositions());
                if ($result['errors']) {
                    $errors = $result['errors'];
                } else {
                    $count = replaceEmployeeList($result['rows']);
                    $message = "Список заменён. Загружено сотрудников: $count.";
                }
            } catch (\RuntimeException $e) {
                $fileError = 'Файл не загружен: ' . $e->getMessage() . '.';
            }
        }
    }

    $list = \Models\Cur_emp::orderBy('fio', 'asc')->orderBy('position', 'asc')->get(['fio', 'position']);
    $positionNames = \Models\Position::orderBy('position', 'asc')->pluck('position');
    $maxShown = 100;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сотрудники</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <nav class="navbar">
        <?php $adminActive = 'employees'; include 'admin_nav.php'; ?>
    </nav>

    <div class="admin-container">
        <h1>Список сотрудников</h1>

        <div class="info-card">
            Загрузите Excel-файл (<strong>.xlsx</strong> или <strong>.xls</strong>) со списком сотрудников:
            <ul>
                <li>столбец <strong>A</strong> - ФИО, столбец <strong>B</strong> - должность;</li>
                <li>должность должна <strong>точно совпадать</strong> с одной из должностей из списка</li>
                <li>если хотя бы одна строка не пройдёт проверку, <strong>файл не загружается целиком</strong>, а список остаётся прежним.</li>
            </ul>
            <ul></ul>
            <strong>Загрузка полностью заменяет текущий список</strong>.
            Уже введённые отпуска сотрудников при этом НЕ удаляются.
        </div>

        <?php if ($message): ?><div class="success-message"><?= esc($message) ?></div><?php endif; ?>
        <?php if ($fileError): ?><div class="error-message"><?= esc($fileError) ?></div><?php endif; ?>

        <?php if ($errors): ?>
            <div class="error-message upload-errors">
                <strong>Файл не загружен — найдены ошибки (<?= count($errors) ?>). Список сотрудников не изменён.</strong>
                <ul>
                    <?php foreach (array_slice($errors, 0, $maxShown) as $e): ?>
                        <li><?= $e['line'] ? 'Строка ' . (int)$e['line'] . ': ' : '' ?><?= esc($e['text']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($errors) > $maxShown): ?>
                    <div>…и ещё <?= count($errors) - $maxShown ?>. Исправьте показанные и загрузите файл снова.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Текущий список сотрудников будет полностью заменён. Продолжить?');">
            <div class="form-group">
                <input type="file" name="list" accept=".xlsx,.xls" required class="file-input">
            </div>
            <button class="save-btn" type="submit"><i class="fas fa-file-upload"></i> Загрузить и заменить список</button>
        </form>

        <h2 class="subtitle">Текущий список (<?= count($list) ?>)</h2>
        <?php if (count($list)): ?>
            <div class="form-group">
                <input type="search" id="filter" class="days-input filter-input" placeholder="Поиск по ФИО или должности" autocomplete="off">
            </div>
            <div class="table-wrap">
                <table class="data-table" id="list-table">
                    <thead><tr><th>ФИО</th><th>Должность</th></tr></thead>
                    <tbody>
                        <?php foreach ($list as $row): ?>
                            <tr>
                                <td data-label="ФИО"><?= esc($row->fio) ?></td>
                                <td data-label="Должность"><?= esc($row->position) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="info-card">Список пуст. Пока он не загружен, сотрудники не смогут войти на сайт.</div>
        <?php endif; ?>
    </div>

    <script>
        var filter = document.getElementById('filter');
        if (filter) {
            filter.addEventListener('input', function () {
                var q = filter.value.toLowerCase().replace(/ё/g, 'е').trim();
                document.querySelectorAll('#list-table tbody tr').forEach(function (tr) {
                    var text = tr.textContent.toLowerCase().replace(/ё/g, 'е');
                    tr.style.display = text.indexOf(q) === -1 ? 'none' : '';
                });
            });
        }
    </script>
</body>

</html>
