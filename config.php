<?php
require_once __DIR__ . '/security.php';
sendSecurityHeaders(); // X-Frame-Options, CSP frame-ancestors, nosniff, HSTS (по HTTPS) и др.
require "vendor/autoload.php";

foreach (glob(__DIR__."/models/*.php") as $fileName)
{
    require_once $fileName;
}

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

// Загружаем .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad(); // .env необязателен: в Docker переменные приходят из окружения

// Значение из .env / переменных окружения
$env = function ($key, $default = null) {
    // реальное окружение процесса приоритетнее файла .env (см. envValue в security.php)
    return envValue($key, $default);
};

// Теперь можно юзать переменные окружения
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $env('DB_CONNECTION'),
    'host'      => $env('DB_HOST'),
    'port'      => $env('DB_PORT'),
    'database'  => $env('DB_DATABASE'),
    'username'  => $env('DB_USERNAME'),
    'password'  => $env('DB_PASSWORD'),
    'charset'   => $env('DB_CHARSET'),
    'schema'    => $env('DB_SCHEMA'),
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    $capsule::connection()->getPdo();
} catch (\Exception $e) {
    // Текст ошибки (адрес БД, логин) пользователю не показываем - только в лог
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die("Ошибка подключения к базе данных");
}