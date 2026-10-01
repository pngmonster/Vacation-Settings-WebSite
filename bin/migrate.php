<?php
/**
 * Применение миграций БД (db/migrations/*.sql) по порядку, каждая - один раз.
 * Применённые миграции записываются в таблицу schema_migrations.
 *
 *   php bin/migrate.php                применить все новые миграции
 *   php bin/migrate.php --status       показать, что применено, а что ждёт
 *   php bin/migrate.php --baseline     ОТМЕТИТЬ все текущие миграции применёнными, не выполняя их
 *                                      (для БД, где вы уже вручную выполнили миграции)
 *   docker compose exec app php bin/migrate.php
 *
 * Новая БД, созданная из db/schema.sql, уже содержит все миграции: схема сама записывает их в
 * schema_migrations, поэтому после установки применять ничего не нужно.
 *
 * Разработчикам: добавляя миграцию NNN_*.sql, добавьте её имя в INSERT INTO schema_migrations в db/schema.sql.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../config.php';

$conn = \Illuminate\Database\Capsule\Manager::connection();
$pdo = $conn->getPdo();

$conn->statement('CREATE TABLE IF NOT EXISTS schema_migrations (
    filename   text PRIMARY KEY,
    applied_at timestamptz NOT NULL DEFAULT now()
)');

$files = glob(__DIR__ . '/../db/migrations/*.sql') ?: [];
sort($files);
$applied = array_column($conn->select('SELECT filename FROM schema_migrations'), 'filename');
$mode = $argv[1] ?? '';

if ($mode === '--status') {
    foreach ($files as $f) {
        $name = basename($f);
        echo sprintf("  %-9s %s\n", in_array($name, $applied, true) ? 'применена' : 'ОЖИДАЕТ', $name);
    }
    exit(0);
}

$pending = array_filter($files, function ($f) use ($applied) { return !in_array(basename($f), $applied, true); });

if (!$pending) {
    echo "Все миграции применены, новых нет.\n";
    exit(0);
}

foreach ($pending as $f) {
    $name = basename($f);
    if ($mode === '--baseline') {
        $conn->insert('INSERT INTO schema_migrations (filename) VALUES (?)', [$name]);
        echo "  отмечена применённой (без выполнения): $name\n";
        continue;
    }
    echo "  применяю $name ... ";
    try {
        $pdo->exec(file_get_contents($f)); // файлы сами управляют транзакцией (BEGIN/COMMIT) и атомарны
    } catch (\Throwable $e) {
        echo "ОШИБКА\n" . $e->getMessage() . "\nМиграция не записана как применённая; исправьте причину и запустите снова.\n";
        exit(1);
    }
    $conn->insert('INSERT INTO schema_migrations (filename) VALUES (?)', [$name]);
    echo "готово\n";
}
echo $mode === '--baseline' ? "Готово: текущие миграции отмечены применёнными.\n" : "Миграции применены.\n";
