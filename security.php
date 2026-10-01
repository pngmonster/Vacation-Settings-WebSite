<?php
/**
 * Безопасность веб-части: заголовки, сессия админа, CSRF, защита входа от перебора и политика паролей.
 *
 * Настройки (переменные окружения / .env):
 *   APP_ENV              production (по умолчанию) или dev. В dev разрешены http и слабые пароли (для локальных тестов).
 *   APP_TRUST_PROXY      1, если сайт стоит за обратным прокси, который ПЕРЕЗАПИСЫВАЕТ X-Forwarded-For / X-Forwarded-Proto.
 *   ADMIN_IDLE_MINUTES   тайм-аут простоя сессии админа (по умолчанию 30)
 *   ADMIN_SESSION_HOURS  предельная длительность сессии (по умолчанию 8)
 */

// Настройка из окружения. ПРИОРИТЕТ: реальное окружение процесса (docker, systemd, веб-сервер) выше файла .env.
// Важно: в веб-режиме $_ENV по умолчанию пуст, и Dotenv записывает в него значения из .env, поэтому читать
// $_ENV первым нельзя - иначе забытый .env молча перебьёт настоящее APP_ENV=production.
function envValue($key, $default = null)
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? null; // значения из файла .env
    }
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

// production - по умолчанию: без явного APP_ENV=dev действуют все строгие правила
function isProduction()
{
    return !in_array(strtolower((string)envValue('APP_ENV', 'production')), ['dev', 'local', 'development', 'test', 'testing'], true);
}

function isHttps()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (envValue('APP_TRUST_PROXY') && strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0])) === 'https') {
        return true;
    }
    return false;
}

// IP клиента. За доверенным прокси берётся ПОСЛЕДНИЙ адрес из X-Forwarded-For (его добавил сам прокси;
// более ранние значения клиент мог подделать).
function clientIp()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (envValue('APP_TRUST_PROXY') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $candidate = end($parts);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    return $ip;
}

// Заголовки безопасности для всех страниц (вызывается из config.php)
function sendSecurityHeaders()
{
    static $sent = false;
    if ($sent || PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $sent = true;
    header_remove('X-Powered-By');                         // не сообщаем версию PHP
    header('X-Frame-Options: DENY');                       // нельзя встроить страницу в чужой сайт (кликджекинг)
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (isHttps()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

// ---------------------------------------------------------------------------------------------
// Сессия
// ---------------------------------------------------------------------------------------------

function adminIdleSeconds()
{
    return (int)round((float)envValue('ADMIN_IDLE_MINUTES', 30) * 60);
}

function adminSessionSeconds()
{
    return (int)round((float)envValue('ADMIN_SESSION_HOURS', 8) * 3600);
}

const ADMIN_SESSION_NAME = 'vacation_admin';

// Пришла ли cookie сессии админа. Сессию (и новую cookie) создаём ТОЛЬКО при её наличии или после успешного пароля:
// иначе запрос без cookie (например, подделанный со стороннего сайта, где браузер cookie не отправил) создал бы
// «пустую» сессию и затёр бы настоящую cookie админа - то есть любой сайт мог бы выбрасывать админа из системы.
function hasSessionCookie()
{
    return !empty($_COOKIE[ADMIN_SESSION_NAME]);
}

function secureSessionStart()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');   // сервер не принимает чужие (подсунутые) идентификаторы
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)max(adminSessionSeconds(), 3600));
    session_name(ADMIN_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,                 // до закрытия браузера (тайм-аут простоя - на стороне сервера)
        'path'     => '/',
        'secure'   => isHttps(),         // по HTTPS cookie не уходит по незащищённому каналу
        'httponly' => true,              // недоступна JavaScript (защита от кражи через XSS)
        'samesite' => 'Lax',             // не отправляется со сторонних сайтов при POST
    ]);
    session_start();
}

function destroyAdminSession()
{
    secureSessionStart();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------------------------

function csrfToken()
{
    secureSessionStart();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// Любой POST без верного токена отклоняется (403). Вызывается из auth.php (все админские страницы).
function requireCsrfForPost()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    secureSessionStart();
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (is_string($sent) && $sent !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent)) {
        return;
    }
    csrfFailPage();
}

// Защита формы входа без сессии (метод «двойной отправки»): случайный токен лежит и в cookie, и в скрытом поле формы.
// Чужой сайт не может ни прочитать, ни подставить cookie нашего сайта, поэтому подделать такой запрос не получится.
// Отдельное имя cookie гарантирует, что анонимные посетители не трогают cookie сессии админа.
const LOGIN_CSRF_COOKIE = 'vacation_login';

function loginCsrfToken()
{
    $t = $_COOKIE[LOGIN_CSRF_COOKIE] ?? '';
    if (!is_string($t) || !preg_match('/^[a-f0-9]{64}$/', $t)) {
        $t = bin2hex(random_bytes(32));
        setcookie(LOGIN_CSRF_COOKIE, $t, ['expires' => time() + 3600, 'path' => '/', 'secure' => isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE[LOGIN_CSRF_COOKIE] = $t;
    }
    return $t;
}

function loginCsrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(loginCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function requireLoginCsrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $cookie = $_COOKIE[LOGIN_CSRF_COOKIE] ?? '';
    $sent = $_POST['csrf_token'] ?? '';
    if (is_string($cookie) && is_string($sent) && $cookie !== '' && hash_equals($cookie, $sent)) {
        return;
    }
    csrfFailPage();
}

function csrfFailPage()
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<body style="font-family:sans-serif;max-width:480px;margin:15vh auto;padding:0 20px;text-align:center">'
        . '<h2>Запрос отклонён</h2><p>Защита от подделки запросов: страница устарела или запрос пришёл не с нашего сайта.</p>'
        . '<p><a href="index.php">Вернуться и повторить</a></p></body>';
    exit;
}

// ---------------------------------------------------------------------------------------------
// Политика паролей
// ---------------------------------------------------------------------------------------------

// Возвращает причину, почему пароль слабый, или null, если всё хорошо
function weakPasswordReason($password, $username = '')
{
    $password = (string)$password;
    if (mb_strlen($password) < 12) {
        return 'короче 12 символов';
    }
    $lower = mb_strtolower($password);
    if ($username !== '' && mb_strpos($lower, mb_strtolower($username)) !== false) {
        return 'содержит логин';
    }
    if (count(array_unique(preg_split('//u', $lower, -1, PREG_SPLIT_NO_EMPTY))) < 6) {
        return 'слишком мало разных символов';
    }
    $stripped = preg_replace('/[\d\W_]+/u', '', $lower);
    $common = ['password', 'passw0rd', 'qwerty', 'qwertyuiop', 'admin', 'administrator', 'letmein', 'welcome', 'iloveyou', 'abcdefghijkl', 'vacation', 'otpusk', 'отпуск', 'пароль', 'йцукен'];
    foreach ($common as $word) {
        if ($stripped === $word || preg_match('/^(?:' . preg_quote($word, '/') . ')+$/u', $stripped)) {
            return 'слишком распространённый пароль';
        }
    }
    return null;
}

// ---------------------------------------------------------------------------------------------
// Защита входа от перебора (журнал попыток в БД)
// ---------------------------------------------------------------------------------------------

const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_USER_IP = 5;    // неудач на пару «логин + IP» за окно
const LOGIN_MAX_IP = 20;        // неудач с одного IP (любые логины) за окно

// Сколько секунд ждать до следующей попытки (0 - можно пробовать)
function loginThrottleWait($username, $ip)
{
    $conn = \Illuminate\Database\Capsule\Manager::connection();
    $w = LOGIN_WINDOW_MINUTES;
    $wait = 0;

    // последние 5 неудач пары логин+IP после последнего успешного входа; если их 5 - ждём, пока самая старая «выйдет» из окна
    $row = $conn->selectOne("
        SELECT count(*) AS c, EXTRACT(EPOCH FROM (min(at) + interval '{$w} minutes' - now())) AS wait FROM (
            SELECT at FROM login_attempts
            WHERE username = ? AND ip = ? AND NOT success AND at > now() - interval '{$w} minutes'
              AND at > COALESCE((SELECT max(at) FROM login_attempts WHERE username = ? AND ip = ? AND success), 'epoch'::timestamptz)
            ORDER BY at DESC LIMIT " . LOGIN_MAX_USER_IP . ") t", [$username, $ip, $username, $ip]);
    if ((int)$row->c >= LOGIN_MAX_USER_IP) {
        $wait = max($wait, (int)ceil($row->wait));
    }

    $row = $conn->selectOne("
        SELECT count(*) AS c, EXTRACT(EPOCH FROM (min(at) + interval '{$w} minutes' - now())) AS wait FROM (
            SELECT at FROM login_attempts
            WHERE ip = ? AND NOT success AND at > now() - interval '{$w} minutes'
            ORDER BY at DESC LIMIT " . LOGIN_MAX_IP . ") t", [$ip]);
    if ((int)$row->c >= LOGIN_MAX_IP) {
        $wait = max($wait, (int)ceil($row->wait));
    }

    return max(0, $wait);
}

function recordLoginAttempt($username, $ip, $success)
{
    $conn = \Illuminate\Database\Capsule\Manager::connection();
    $conn->insert('INSERT INTO login_attempts (username, ip, success) VALUES (?, ?, ?)', [mb_substr($username, 0, 100), $ip, $success ? 'true' : 'false']);
    if ($success) { // заодно чистим старый журнал
        $conn->delete("DELETE FROM login_attempts WHERE at < now() - interval '30 days'");
    }
}
