<?php
// Подключается в начале КАЖДОЙ админской страницы: проверяет вход, тайм-ауты сессии и CSRF для всех POST.
require_once __DIR__ . '/security.php';
sendSecurityHeaders();
header('Cache-Control: no-store, no-cache, must-revalidate'); // после выхода «Назад» не покажет данные из кэша

// Без cookie сессии - сразу на вход, не создавая сессию (и не ставя cookie, которая затёрла бы настоящую)
if (!hasSessionCookie()) {
    header('Location: login.php');
    exit;
}
secureSessionStart();

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$now = time();
$idleExpired = $now - (int)($_SESSION['last_activity'] ?? 0) > adminIdleSeconds();
$absoluteExpired = $now - (int)($_SESSION['login_time'] ?? 0) > adminSessionSeconds();
if ($idleExpired || $absoluteExpired) {
    destroyAdminSession();
    header('Location: login.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = $now;

// Любой POST без верного токена отклоняется - поэтому обработчикам на страницах ничего добавлять не нужно
requireCsrfForPost();
