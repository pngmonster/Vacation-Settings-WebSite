<?php
    require_once 'auth.php';
    require "functions.php"; //Функции PHP

    $errors = [];
    $formPart = 0; // часть, при сохранении которой была ошибка (чтобы не терять введённые значения)

    $curId = (int)($_GET['cur'] ?? 0);
    $curEmp = $curId ? \Models\Cur_emp::find($curId) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $curEmp) {
        $n = (int)($_POST['part'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $result = adminSavePart($curEmp, $n, (string)($_POST['start'] ?? ''), (int)($_POST['days'] ?? 0), $year);
        } elseif ($action === 'reset') {
            $existing = findEmployeeForCur($curEmp);
            $result = $existing ? adminResetPart($existing->id, $n, $year) : partFail('У сотрудника пока нет отпуска');
        } else {
            $result = partFail('Неизвестное действие');
        }

        if ($result['ok']) { // Post/Redirect/Get
            header('Location: assign.php?cur=' . $curId . '&msg=' . ($action === 'save' ? 'saved' : 'reset') . '&part=' . $n);
            exit;
        }
        $errors = $result['errors'];
        $formPart = $n;
    }

    $items = \Models\Cur_emp::orderBy('fio', 'asc')->orderBy('position', 'asc')->get(['id', 'fio', 'position'])->toArray();

    $employee = $curEmp ? findEmployeeForCur($curEmp) : null;
    $position = $curEmp ? \Models\Position::where('position', $curEmp->position)->first() : null;

    $flash = null;
    if (($_GET['msg'] ?? '') === 'saved') {
        $flash = 'Часть ' . (int)($_GET['part'] ?? 0) . ' сохранена как введённая администратором.';
    } elseif (($_GET['msg'] ?? '') === 'reset') {
        $flash = 'Часть ' . (int)($_GET['part'] ?? 0) . ' сброшена.';
    }

    $totalDays = 0;
    $status = null;
    if ($employee) {
        $totalDays = chosenDays($employee);
        $status = vacationStatus($employee, $position->maxday ?? 0);
    }
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ввод отпуска</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="./styles/combobox.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <nav class="navbar">
        <?php $adminActive = 'assign'; include 'admin_nav.php'; ?>
    </nav>

    <div class="admin-container">
        <h1>Ввод отпуска администратором</h1>

        <div class="info-card">
            Здесь можно поставить отпуск сотруднику <strong>без каких-либо ограничений</strong>: не действуют лимиты по месяцам,
            максимальное количество дней, длина части и пересечения.
            <ul>
                <li>Дни такой части <strong>не учитываются</strong> в лимитах должности.</li>
                <li>В общей Excel-выгрузке она помечается «Введено администратором» и выделяется цветом.</li>
                <li>Сотрудник не может изменить или сбросить часть, введённую администратором.</li>
                <li><strong>Автоподтверждение:</strong> если после сохранения выбрано <strong>не меньше maxday</strong> дней, отпуск подтверждается автоматически; если меньше — остаётся <strong>не заполненным</strong> (сотрудник сможет дозаполнить его сам).</li>
                <li>Отпуск планируется на <strong><?= (int)$year ?> год</strong>.</li>
            </ul>
        </div>

        <?php if ($curId && !$curEmp): ?>
            <div class="error-message">Сотрудник не найден в списке.</div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="info-card">Список сотрудников пуст. Сначала загрузите его на странице «Сотрудники».</div>
        <?php else: ?>
            <div class="form-group">
                <label for="fio-input" class="field-label">Сотрудник</label>
                <div class="combo">
                    <input type="text" id="fio-input" class="combo-input" placeholder="Начните вводить фамилию"
                           autocapitalize="off" spellcheck="false" aria-controls="fio-list">
                    <ul id="fio-list" class="combo-list" hidden></ul>
                </div>
                <input type="hidden" id="fio-id" value="<?= (int)$curId ?>">
            </div>
        <?php endif; ?>

        <?php if ($curEmp): ?>

            <div class="emp-summary">
                <div class="emp-summary-name"><?= esc($curEmp->fio) ?></div>
                <div class="emp-summary-meta">
                    <span><i class="fas fa-briefcase"></i> <?= esc($curEmp->position) ?></span>
                    <?php if ($position): ?><span>Максимум дней: <strong><?= (int)$position->maxday ?></strong></span><?php endif; ?>
                    <span>Выбрано всего: <strong><?= $totalDays ?></strong> дн.</span>
                    <?php if ($status && $status['complete']): ?>
                        <span class="tag-ready"><i class="fas fa-check-circle"></i> Заполнено и подтверждено</span>
                    <?php elseif ($status): ?>
                        <span class="tag-incomplete"><i class="fas fa-exclamation-circle"></i> <?= esc($status['label']) ?></span>
                    <?php else: ?>
                        <span class="tag-incomplete"><i class="fas fa-exclamation-circle"></i> Не заполнено: ещё не заходил на сайт</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($flash): ?><div class="success-message"><?= esc($flash) ?></div><?php endif; ?>
            <?php foreach ($errors as $error): ?><div class="error-message"><?= esc($error) ?></div><?php endforeach; ?>

            <div class="assign-parts">
                <?php for ($n = 1; $n <= 3; $n++): ?>
                    <?php
                        $len = $employee ? (int)$employee->{"lenght$n"} : 0;
                        $isAdmin = $employee && $employee->{"admin$n"};
                        $startValue = '';
                        $rangeText = null;
                        if ($len) {
                            $r = dateCalc($employee->{"day$n"}, $employee->{"mon$n"}, $year, $len);
                            $startValue = $r['start']->format('Y-m-d');
                            $rangeText = $r['start']->format('d.m.Y') . ' — ' . $r['end']->format('d.m.Y') . ' (' . $len . ' дн.)';
                        }
                        $daysValue = $len ?: '';
                        if ($formPart === $n) { // после ошибки оставляем то, что ввёл админ
                            $startValue = (string)($_POST['start'] ?? '');
                            $daysValue = (string)($_POST['days'] ?? '');
                        }
                    ?>
                    <div class="assign-part <?= $isAdmin ? 'by-admin' : ($len ? 'by-employee' : '') ?>">
                        <div class="assign-part-head">
                            <strong>Часть <?= $n ?></strong>
                            <?php if ($isAdmin): ?><span class="badge badge-admin"><i class="fas fa-user-shield"></i> Введено администратором</span>
                            <?php elseif ($len): ?><span class="badge badge-employee">Выбрано сотрудником</span>
                            <?php else: ?><span class="badge badge-empty">Не задана</span><?php endif; ?>
                        </div>

                        <?php if ($rangeText): ?><div class="assign-range"><?= esc($rangeText) ?></div><?php endif; ?>

                        <form method="POST" action="assign.php?cur=<?= (int)$curId ?>">
                            <input type="hidden" name="part" value="<?= $n ?>">
                            <label class="field-label">Начало</label>
                            <input class="days-input" type="date" name="start" required
                                   min="<?= (int)$year ?>-01-01" max="<?= (int)$year ?>-12-31" value="<?= esc($startValue) ?>">
                            <label class="field-label">Количество дней</label>
                            <input class="days-input" type="number" name="days" min="1" max="366" required
                                   inputmode="numeric" placeholder="дней" value="<?= esc($daysValue) ?>">
                            <div class="assign-buttons">
                                <button class="save-btn" type="submit" name="action" value="save"
                                    <?php if ($len && !$isAdmin): ?>onclick="return confirm('Эту часть выбрал сам сотрудник. Заменить её на введённую администратором?');"<?php endif; ?>>
                                    <i class="fas fa-save"></i> Сохранить
                                </button>
                                <?php if ($len): ?>
                                    <button class="save-btn reset-btn" type="submit" name="action" value="reset" formnovalidate
                                            onclick="return confirm('Сбросить часть <?= $n ?>?');">
                                        <i class="fas fa-undo"></i> Сбросить
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endfor; ?>
            </div>

        <?php endif; ?>
    </div>

    <?php if ($items): ?>
    <script src="./assets/fio-combobox.js"></script>
    <script>
        (function () {
            var items = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var input = document.getElementById('fio-input');
            var hidden = document.getElementById('fio-id');
            var currentId = parseInt(hidden.value, 10) || 0;

            FioCombobox.init({
                input: input,
                list: document.getElementById('fio-list'),
                hidden: hidden,
                items: items,
                onSelect: function (item) {
                    if (item) { window.location.href = 'assign.php?cur=' + encodeURIComponent(item.id); }
                }
            });

            // Уже выбранный сотрудник показывается в поле
            if (currentId) {
                items.forEach(function (it) {
                    if (it.id === currentId) {
                        input.value = it.fio;
                        input.classList.add('is-selected');
                    }
                });
            }
        })();
    </script>
    <?php endif; ?>
</body>

</html>
