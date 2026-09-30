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
