<?php
/**
 * Проверка защиты входа администратора на ЗАПУЩЕННОМ сайте (по HTTP).
 *
 *   php tests/security/http-check.php --base=https://vacation.example.org --user=boss --pass='...' [--bruteforce]
 *   docker compose exec app php tests/security/http-check.php --base=http://localhost --user=admin --pass=admin
 *
 * --proxy-https      добавлять X-Forwarded-Proto: https (тест за прокси без реального TLS, нужен APP_TRUST_PROXY=1)
 * --expect-production убедиться, что сайт НЕ в режиме разработки (нет красного баннера «РЕЖИМ РАЗРАБОТКИ»)
 * --check-demo       убедиться, что демо-вход admin/admin НЕ работает (обязательно перед запуском на проде)
 * --bruteforce       проверка блокировки перебора. ВНИМАНИЕ: блокирует IP тестирующего на 15 минут;
 *                    снять: php bin/admin-security.php unlock
 *
 * Скрипт безвреден для данных: POST-проверки используют «пустое» сохранение настроек должностей.
 * Код возврата 0 - все проверки пройдены.
 */
if (PHP_SAPI !== 'cli') { exit; }
$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([\w-]+)(?:=(.*))?$/', $a, $m)) { $opt[$m[1]] = $m[2] ?? true; }
}
$base = rtrim($opt['base'] ?? 'http://127.0.0.1:8081', '/');
$user = $opt['user'] ?? 'admin'; $pass = $opt['pass'] ?? '';
$proxyHttps = !empty($opt['proxy-https']);
$ok = 0; $fail = 0;

function t($name, $cond, $extra = '') {
    global $ok, $fail;
    if ($cond) { $ok++; echo "  ok    $name\n"; } else { $fail++; echo "  FAIL  $name" . ($extra !== '' ? " [$extra]" : '') . "\n"; }
}

// Мини-«браузер» с собственным хранилищем cookie
class Browser {
    public $jar; public $extraHeaders = [];
    function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'jar'); }
    function req($method, $path, $data = null, $headers = []) {
        global $base, $proxyHttps;
        $ch = curl_init(strpos($path, 'http') === 0 ? $path : $base . '/' . ltrim($path, '/'));
        $h = array_merge($this->extraHeaders, $headers);
        if ($proxyHttps) { $h[] = 'X-Forwarded-Proto: https'; }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 20]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data); }
        $raw = curl_exec($ch); $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $head = substr($raw, 0, $size); $body = substr($raw, $size);
        $lines = preg_split('/\r?\n/', trim($head));
        $hdr = [];
        foreach ($lines as $l) { if (strpos($l, ':') !== false) { list($k, $v) = explode(':', $l, 2); $hdr[strtolower(trim($k))][] = trim($v); } }
        return ['code' => $code, 'h' => $hdr, 'body' => $body, 'loc' => $hdr['location'][0] ?? null, 'raw' => $head];
    }
    function get($p, $h = []) { return $this->req('GET', $p, null, $h); }
    function post($p, $d, $h = []) { return $this->req('POST', $p, $d, $h); }
    function token($path = 'index.php') { $r = $this->get($path); preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m); return $m[1] ?? ''; }
}
function loginToken(Browser $b) { $r = $b->get('login.php'); preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m); return [$m[1] ?? '', $r]; }
function doLogin(Browser $b, $user, $pass) {
    list($tok) = loginToken($b);
    return $b->post('login.php', ['csrf_token' => $tok, 'username' => $user, 'password' => $pass]);
}
$adminPages = ['index.php', 'positions.php', 'employees.php', 'assign.php', 'report.php', 'clear.php', 'downloadExcel.php'];

echo "Заголовки и cookie\n";
$b = new Browser();
$r = $b->get('login.php');
t('X-Frame-Options: DENY', ($r['h']['x-frame-options'][0] ?? '') === 'DENY');
t('Content-Security-Policy: frame-ancestors none', strpos($r['h']['content-security-policy'][0] ?? '', "frame-ancestors 'none'") !== false);
t('X-Content-Type-Options: nosniff', ($r['h']['x-content-type-options'][0] ?? '') === 'nosniff');
t('Referrer-Policy задан', !empty($r['h']['referrer-policy']));
if ($proxyHttps || strpos($base, 'https://') === 0) { t('HSTS при HTTPS', !empty($r['h']['strict-transport-security'])); }
$setCookie = implode(' | ', $r['h']['set-cookie'] ?? []);
t('cookie сессии: HttpOnly', stripos($setCookie, 'httponly') !== false, $setCookie);
t('cookie сессии: SameSite=Lax', stripos($setCookie, 'samesite=lax') !== false);
if ($proxyHttps || strpos($base, 'https://') === 0) { t('cookie сессии: Secure (HTTPS)', stripos($setCookie, 'secure') !== false); }
t('версия PHP не раскрывается (нет X-Powered-By)', empty($r['h']['x-powered-by']));

echo "Без входа админ-страницы закрыты\n";
$anon = new Browser();
foreach ($adminPages as $p) { $x = $anon->get($p); t("$p -> редирект на вход", $x['code'] === 302 && strpos((string)$x['loc'], 'login.php') !== false); }

echo "CSRF на странице входа\n";
$x = $anon->post('login.php', ['username' => $user, 'password' => $pass]);
t('POST входа без токена отклонён (403)', $x['code'] === 403);

echo "Вход\n";
$bad = new Browser(); $x = doLogin($bad, $user, $pass . 'x');
t('неверный пароль -> сообщение, не пустило', strpos($x['body'], 'Неверный логин или пароль') !== false);
$b = new Browser(); $x = doLogin($b, $user, $pass);
t('верный вход -> редирект на index.php', $x['code'] === 302 && strpos((string)$x['loc'], 'index.php') !== false, "code={$x['code']} loc={$x['loc']} " . trim(strip_tags(substr($x['body'], strpos($x['body'], 'answ') ?: 0, 300))));
$loggedIn = $x['code'] === 302;
if ($loggedIn) {
    $x = $b->get('report.php');
    t('после входа админ-страница открывается', $x['code'] === 200);
    t('страницы админки: Cache-Control no-store', stripos($x['h']['cache-control'][0] ?? '', 'no-store') !== false);
    t('в меню есть кнопка «Выйти» с токеном', strpos($x['body'], 'action="logout.php"') !== false);

    echo "CSRF в админских действиях\n";
    $tok = $b->token('positions.php');
    t('токен выдаётся на страницах', strlen($tok) === 64);
    $rows = ['rows[0][position]' => 'Санитар', 'rows[0][maxday]' => '65'];
    $curMax = null; // «пустое» сохранение: значение не меняется
    $pg = $b->get('positions.php')['body'];
    preg_match('/name="rows\[0\]\[position\]" value="([^"]*)".*?name="rows\[0\]\[maxday\]" value="(\d+)"/s', html_entity_decode($pg), $m);
    $post = ['rows' => [0 => ['position' => $m[1] ?? '', 'maxday' => $m[2] ?? '1']]];
    $x = $b->post('positions.php', http_build_query($post));
    t('POST без токена -> 403', $x['code'] === 403);
    $x = $b->post('positions.php', http_build_query($post + ['csrf_token' => str_repeat('0', 64)]));
    t('POST с чужим токеном -> 403', $x['code'] === 403);
    $x = $b->post('positions.php', http_build_query($post + ['csrf_token' => $tok]));
    t('POST с верным токеном -> выполнено (200)', $x['code'] === 200 && strpos($x['body'], 'Изменений нет') !== false, "code={$x['code']}");
    $x = $b->post('clear.php', ['confirm' => 'Удалить']);
    t('«Очистить БД» без токена -> 403 (данные не тронуты)', $x['code'] === 403);
    $x = $b->post('report.php?position=all', ['delete_id' => '1']);
    t('удаление сотрудника без токена -> 403', $x['code'] === 403);
    $x = $b->post('downloadExcel.php', ['filter' => 'current']);
    t('выгрузка Excel (POST) без токена -> 403', $x['code'] === 403);
    $x = $b->post('employees.php', ['x' => '1']);
    t('загрузка списка без токена -> 403', $x['code'] === 403);
    $x = $b->post('assign.php?cur=1', ['part' => 1, 'action' => 'reset']);
    t('ввод отпуска админом без токена -> 403', $x['code'] === 403);

    echo "Выход\n";
    $x = $b->get('logout.php');
    t('GET /logout.php НЕ разлогинивает (чужая ссылка не выкинет админа)', $b->get('report.php')['code'] === 200);
    $x = $b->post('logout.php', []);
    t('POST выхода без токена -> 403, сессия жива', $x['code'] === 403 && $b->get('report.php')['code'] === 200);
    $oldJar = tempnam(sys_get_temp_dir(), 'old'); copy($b->jar, $oldJar);
    $x = $b->post('logout.php', ['csrf_token' => $b->token('index.php')]);
    t('POST выхода с токеном -> редирект на вход', $x['code'] === 302 && strpos((string)$x['loc'], 'login.php') !== false);
    t('после выхода админ-страница закрыта', $b->get('report.php')['code'] === 302);
    $stolen = new Browser(); copy($oldJar, $stolen->jar);
    t('старая (украденная до выхода) cookie больше не работает', $stolen->get('report.php')['code'] === 302);
}

if (!empty($opt['expect-production'])) {
    echo "Режим работы\n";
    t('сайт запущен в production (нет баннера «РЕЖИМ РАЗРАБОТКИ»)', strpos($b->get('login.php')['body'], 'РЕЖИМ РАЗРАБОТКИ') === false,
      'APP_ENV=dev: на боевом сервере задайте APP_ENV=production');
}

if (!empty($opt['check-demo'])) {
    echo "Демо-аккаунт и слабые пароли\n";
    $demo = new Browser(); $x = doLogin($demo, 'admin', 'admin');
    t('вход admin/admin НЕ работает', !($x['code'] === 302 && strpos((string)$x['loc'], 'index.php') !== false),
      'ДЕМО-АККАУНТ ДЕЙСТВУЕТ: удалите его (DELETE FROM users WHERE username = \'admin\') или смените пароль');
}

echo "Фиксация сессии\n";
$fx = new Browser(); $fx->extraHeaders = ['Cookie: vacation_admin=attackerchosenid1234567890abcd'];
$x = $fx->get('login.php');
$sc = implode(' ', $x['h']['set-cookie'] ?? []);
t('подсунутый идентификатор сессии не принимается сервером', strpos($sc, 'attackerchosenid1234567890abcd') === false && $sc !== '', $sc);

if (!empty($opt['bruteforce'])) {
    echo "Перебор пароля (блокирует IP тестирующего на 15 минут!)\n";
    $victim = 'victim' . mt_rand(1000, 9999); $bf = new Browser();
    for ($i = 1; $i <= 5; $i++) { doLogin($bf, $victim, "wrong$i"); }
    $x = doLogin($bf, $victim, 'wrong6');
    t('после 5 неудач вход блокируется с сообщением об ожидании', strpos($x['body'], 'Слишком много неудачных попыток') !== false);
    $x = doLogin($bf, $user, $pass);
    t('логин админа с ТОГО ЖЕ IP, пока не набрал свои неудачи, не заблокирован (нет «общего» DoS)', $x['code'] === 302 || strpos($x['body'], 'Слишком много') === false);
    echo "  (запустите: php bin/admin-security.php unlock — чтобы снять блокировку)\n";
}

echo "\n" . ($fail ? "ПРОВАЛЕНО: $fail, пройдено: $ok" : "Все проверки пройдены ($ok)") . "\n";
exit($fail ? 1 : 0);
