<?php
require_once 'auth.php';
require_once 'functions.php';

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm'] ?? '');
    if ($confirm === 'Удалить') {
        clearPositionsData();
        clearPositionsEmpData();
        deleteAllEmployees();

        $message = '<div class="success-message">Все данные успешно удалены!</div>';
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
        <div class="navbar-container">
            <a href="/index.php">
                <i class="fas fa-cog"></i> Настройки
            </a>
            <a href="/report.php">
                <i class="fas fa-chart-bar"></i> Отчёт
            </a>
            <a href="/clear.php" class="danger-link">
                <i class="fas fa-trash-alt"></i> Очистить БД
            </a>
        </div>
    </nav>

    <div class="admin-container">
        <h1 class="danger-title">Очистка базы данных</h1>

        <div class="warning-card">
            <h2>🔴 ВНИМАНИЕ!</h2>
            <p>Вы собираетесь <strong>полностью очистить базу данных</strong>.</p>
            <ul>
                <li>Будут удалены <strong>все зарегистрированные сотрудники</strong>.</li>
                <li>Будет сброшено <strong>количество доступных дней отпуска на каждый месяц</strong>.</li>
            </ul>
            <p>Этот процесс <strong>необратим</strong>. После очистки восстановить данные будет невозможно.</p>
        </div>

        <form method="POST">
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