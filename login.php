<?php
require_once __DIR__ . '/security.php';
sendSecurityHeaders();
require_once 'config.php';

// Хэш-«пустышка»: для несуществующего логина проверка пароля занимает столько же времени (нельзя угадать логин по скорости ответа)
const DUMMY_HASH = '$2y$10$6ZsMuVCOFqRODfSXfjfG.e5m5WSfVYhpkr7nuiJ8dsh9vBI/DvSYi';

$error = null;
$info = null;

if (!empty($_GET['expired'])) {
    $info = 'Сессия завершена из-за бездействия. Войдите снова.';
}

function waitMessage($seconds)
{
    return 'Слишком много неудачных попыток. Повторите через ' . max(1, (int)ceil($seconds / 60)) . ' мин.';
}

// Сессия создаётся только после верного пароля: анонимные посетители cookie сессии не получают
function completeLogin($user, $ip)
{
    secureSessionStart();
    session_regenerate_id(true); // защита от фиксации сессии
    $_SESSION['is_admin'] = ($user->role === 'admin');
    $_SESSION['username'] = $user->username;
    $_SESSION['login_time'] = $_SESSION['last_activity'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    recordLoginAttempt(mb_strtolower($user->username), $ip, true);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLoginCsrf();
    $ip = clientIp();

    if (isProduction() && !isHttps()) {
        // пароль по обычному http виден в сети: вход запрещён
        $error = 'Вход возможен только по защищённому соединению (HTTPS). Если сайт стоит за прокси с HTTPS, задайте APP_TRUST_PROXY=1.';
    } else {
        $username = mb_substr(trim((string)($_POST['username'] ?? '')), 0, 100);
        $password = (string)($_POST['password'] ?? '');
        $key = mb_strtolower($username);
        $wait = loginThrottleWait($key, $ip);

        if ($wait > 0) {
            $error = waitMessage($wait);
        } else {
            $user = \Models\User::where('username', $username)->first();
            $valid = password_verify($password, $user ? $user->password : DUMMY_HASH) && $user;

            if (!$valid) {
                recordLoginAttempt($key, $ip, false);
                $error = 'Неверный логин или пароль';
            } elseif (isProduction() && ($reason = weakPasswordReason($password, $user->username))) {
                $error = "Пароль слишком простой ($reason). Задайте новый: php bin/create-admin.php <логин> <новый пароль>";
            } else {
                completeLogin($user, $ip);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Администратор</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./styles/sign.css">
    <style>
        .answ {
            margin-top: 15px;
            font-size: 1rem;
            text-align: center;
            color: red;
            flex: 1;
        }
    </style>
</head>
<body>
    <?php if (!isProduction()): ?>
        <div class="dev-banner"><i class="fas fa-triangle-exclamation"></i> РЕЖИМ РАЗРАБОТКИ (APP_ENV=dev): защита ослаблена, на боевом сервере так быть не должно</div>
    <?php endif; ?>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-user-shield"></i> Администратор</h2>
        </div>
        <div class="login-body">
            <form method="POST">
                <?= loginCsrfField() ?>
                <div class="form-group">
                    <label for="username">Логин</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Введите логин" autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Введите пароль" autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Войти
                </button>
            </form>

            <?php if ($info): ?>
                <div class="answ" style="color:#555"><?= htmlspecialchars($info) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="answ"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
