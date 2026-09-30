<?php
/**
 * Создание (или смена пароля) администратора.
 *
 *   php bin/create-admin.php <логин> <пароль>
 *   docker compose exec app php bin/create-admin.php <логин> <пароль>
 *
 * Пароль можно передать и через переменную окружения ADMIN_PASSWORD, чтобы он
 * не оставался в истории команд:  ADMIN_PASSWORD='...' php bin/create-admin.php <логин>
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../config.php';

$username = $argv[1] ?? null;
$password = $argv[2] ?? getenv('ADMIN_PASSWORD');

if (!$username || !$password) {
    fwrite(STDERR, "Использование: php bin/create-admin.php <логин> <пароль>\n");
    exit(1);
}
if (mb_strlen($password) < 8) {
    fwrite(STDERR, "Пароль должен быть не короче 8 символов.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$user = \Models\User::where('username', $username)->first();

if ($user) {
    $user->update(['password' => $hash, 'role' => 'admin']);
    echo "Пароль администратора «{$username}» обновлён.\n";
} else {
    \Models\User::create(['username' => $username, 'password' => $hash, 'role' => 'admin']);
    echo "Администратор «{$username}» создан.\n";
}
