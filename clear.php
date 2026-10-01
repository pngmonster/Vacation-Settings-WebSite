<?php
require_once 'auth.php';
require_once 'functions.php';

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm === 'Удалить') {
        try {
            rebuildDatabase(); // одна транзакция: при ошибке ничего не меняется
            $message = '<div class="success-message">База данных пересобрана заново. Загрузите список сотрудников («Сотрудники») и задайте год и лимиты («Настройки»).</div>';
        } catch (\Throwable $e) {
            error_log('rebuildDatabase failed: ' . $e->getMessage());
            $message = '<div class="error-message">Не удалось пересобрать базу, данные не изменены. Подробности в журнале сервера.</div>';
        }
    } else {
        $message = '<div class="error-message">Для подтверждения нужно ввести слово «Удалить».</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Очистка БД</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <nav class="navbar">
        <?php $adminActive = 'clear'; include 'admin_nav.php'; ?>
    </nav>

    <div class="admin-container">
        <h1 class="danger-title">Очистка базы данных</h1>

        <div class="warning-card">
            <h2>🔴 ВНИМАНИЕ!</h2>
            <p>Вы собираетесь <strong>заново собрать базу данных</strong>: она будет создана с нуля, как при первой установке.</p>
            <ul>
                <li>Будут удалены <strong>все сотрудники, их отпуска и список сотрудников из Excel</strong>.</li>
                <li>Будут сброшены <strong>лимиты дней по месяцам и счётчики занятых дней</strong>.</li>
                <li><strong>Максимум дней отпуска по должностям</strong> и <strong>год отпусков</strong> вернутся к значениям по умолчанию.</li>
                <li>Нумерация записей начнётся с единицы.</li>
            </ul>
            <p>Сохранится только <strong>учётная запись администратора</strong>.</p>
            <p>Этот процесс <strong>необратим</strong>. Рекомендуется делать его, когда сотрудники не работают с сайтом: открытые у них
            старые страницы после пересборки станут недействительными.</p>
        </div>

        <form method="POST">
            <?= csrfField() ?>
            <p class="confirm-text">Для подтверждения удаления всех данных введите слово <strong>Удалить</strong>:</p>
            <input type="text" name="confirm" class="confirm-input">

            <?php if (!empty($message)): ?>
                <div><?= $message ?></div>
            <?php endif; ?>

            <button type="submit" class="save-btn danger-btn">
                <i class="fas fa-trash"></i> Очистить базу
            </button>
        </form>
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