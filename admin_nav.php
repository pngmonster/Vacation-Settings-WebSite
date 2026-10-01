<?php if (!isProduction()): ?>
        <div class="dev-banner"><i class="fas fa-triangle-exclamation"></i> РЕЖИМ РАЗРАБОТКИ (APP_ENV=dev): защита ослаблена, на боевом сервере так быть не должно</div>
<?php endif; ?>
<?php
// Общее меню администратора. Перед подключением задайте $adminActive:
// settings | positions | employees | assign | report | clear
$adminLinks = [
    'settings'  => ['/index.php',     'fa-cog',           'Настройки'],
    'positions' => ['/positions.php', 'fa-briefcase',     'Должности'],
    'employees' => ['/employees.php', 'fa-users',         'Сотрудники'],
    'assign'    => ['/assign.php',    'fa-calendar-plus', 'Ввод отпуска'],
    'report'    => ['/report.php',    'fa-chart-bar',     'Отчёт'],
    'clear'     => ['/clear.php',     'fa-trash-alt',     'Очистить БД'],
];
?>
        <div class="navbar-container">
            <?php foreach ($adminLinks as $key => [$href, $icon, $title]): ?>
                <?php $isActive = $key === ($adminActive ?? ''); ?>
                <a href="<?= $href ?>" class="<?= $isActive ? ($key === 'clear' ? 'danger-link' : 'active') : '' ?>"<?= ($key === 'clear' && !$isActive) ? ' style="color: #e63946; font-weight: bold;"' : '' ?>>
                    <i class="fas <?= $icon ?>"></i> <?= $title ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            <form method="POST" action="logout.php">
                <?= csrfField() ?>
                <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Выйти</button>
            </form>
        </div>
