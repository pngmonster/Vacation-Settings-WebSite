<?php
/**
 * Журнал входа и блокировки администратора (только из консоли).
 *
 *   php bin/admin-security.php logins [N]    последние N попыток входа (по умолчанию 20): когда, с какого IP, удачно ли
 *   php bin/admin-security.php unlock        снять все блокировки входа (очистить журнал попыток)
 *
 *   docker compose exec app php bin/admin-security.php logins
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../config.php';

$cmd = $argv[1] ?? '';
$conn = \Illuminate\Database\Capsule\Manager::connection();

switch ($cmd) {
    case 'logins':
        $limit = max(1, (int)($argv[2] ?? 20));
        $rows = $conn->select('SELECT at, username, ip, success FROM login_attempts ORDER BY id DESC LIMIT ' . $limit);
        foreach ($rows as $r) {
            echo sprintf("  %s  %-6s  %-20s  %s\n", substr($r->at, 0, 19), $r->success ? 'ВХОД' : 'ОТКАЗ', $r->username, $r->ip);
        }
        if (!$rows) {
            echo "  журнал пуст\n";
        }
        break;

    case 'unlock':
        $n = $conn->delete('DELETE FROM login_attempts');
        echo "Журнал попыток входа очищен (записей: {$n}), блокировки сняты.\n";
        break;

    default:
        fwrite(STDERR, "Команды: logins [N] | unlock\n");
        exit(1);
}
