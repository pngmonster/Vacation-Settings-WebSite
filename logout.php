<?php
require_once __DIR__ . '/security.php';
sendSecurityHeaders();

// Выход только по POST с токеном: ссылка или картинка на чужом сайте не должна разлогинивать админа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasSessionCookie()) {
    requireCsrfForPost();
    destroyAdminSession();
    header('Location: login.php');
    exit;
}
header('Location: index.php');
exit;
