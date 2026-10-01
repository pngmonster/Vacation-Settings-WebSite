<?php
    require_once 'auth.php';
    require "functions.php"; //Функции PHP

    $errors = [];
    $message = null;

    // Сохранение: можно менять ТОЛЬКО maxday у существующих должностей. Всё или ничего.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rows = $_POST['rows'] ?? [];
        $updates = [];

        foreach ($rows as $row) {
            $name = (string)($row['position'] ?? '');
            $raw = trim((string)($row['maxday'] ?? ''));

            if (!\Models\Position::where('position', $name)->exists()) {
                $errors[] = "Должность «" . $name . "» не найдена (добавлять должности нельзя).";
                continue;
            }
            if (!ctype_digit($raw) || (int)$raw < 1 || (int)$raw > 366) {
                $errors[] = "«" . $name . "»: количество дней должно быть целым числом от 1 до 366.";
                continue;
            }
            $updates[$name] = (int)$raw;
        }

        if (!$errors) {
            $changed = 0;
            \Illuminate\Database\Capsule\Manager::connection()->transaction(function () use ($updates, &$changed) {
                foreach ($updates as $name => $maxday) {
                    $changed += \Models\Position::where('position', $name)
                        ->where('maxday', '<>', $maxday)
                        ->update(['maxday' => $maxday]);
                }
            });
            $message = $changed ? "Сохранено. Изменено должностей: $changed." : "Изменений нет.";
        }
    }

    $positions = \Models\Position::orderBy('position', 'asc')->get(['position', 'maxday']);
    $counts = \Models\Cur_emp::selectRaw('position, count(*) as c')->groupBy('position')->pluck('c', 'position');
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Должности</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <nav class="navbar">
        <?php $adminActive = 'positions'; include 'admin_nav.php'; ?>
    </nav>

    <div class="admin-container">
        <h1>Должности</h1>

        <div class="info-card">
            Список должностей фиксированный: добавить или удалить должность нельзя.
            Здесь можно изменить только <strong>максимальное количество дней отпуска</strong>.
            <ul>
                <li>От этого значения зависит максимальная длина одной части отпуска: при 48 и более днях - <strong>30</strong>, иначе - <strong>21</strong>.</li>
                <li>Лимиты дней по месяцам задаются на странице «Настройки».</li>
                <li>Отпуска, которые сотрудники уже выбрали, при изменении не пересчитываются.</li>
            </ul>
        </div>

        <?php if ($message): ?><div class="success-message"><?= esc($message) ?></div><?php endif; ?>
        <?php foreach ($errors as $error): ?><div class="error-message"><?= esc($error) ?></div><?php endforeach; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Должность</th>
                            <th>Макс. дней отпуска</th>
                            <th>Макс. длина части</th>
                            <th>Сотрудников в списке</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($positions as $i => $p): ?>
                            <?php $value = $errors ? ($_POST['rows'][$i]['maxday'] ?? $p->maxday) : $p->maxday; ?>
                            <tr>
                                <td data-label="Должность">
                                    <?= esc($p->position) ?>
                                    <input type="hidden" name="rows[<?= $i ?>][position]" value="<?= esc($p->position) ?>">
                                </td>
                                <td data-label="Макс. дней отпуска">
                                    <input class="days-input maxday-input" type="number" min="1" max="366" required
                                           name="rows[<?= $i ?>][maxday]" value="<?= esc($value) ?>">
                                </td>
                                <td data-label="Макс. длина части" class="part-len"><?= maxPartDays((int)$p->maxday) ?></td>
                                <td data-label="Сотрудников в списке"><?= (int)($counts[$p->position] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button class="save-btn" type="submit"><i class="fas fa-save"></i> Сохранить</button>
        </form>
    </div>

    <script>
        // Пересчёт «макс. длина части» при вводе (правило: 48+ дней -> 30, иначе 21)
        document.querySelectorAll('.maxday-input').forEach(function (input) {
            input.addEventListener('input', function () {
                var cell = input.closest('tr').querySelector('.part-len');
                var v = parseInt(input.value, 10);
                cell.textContent = isNaN(v) ? '-' : (v >= 48 ? 30 : 21);
            });
        });
    </script>
</body>

</html>
