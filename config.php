<?php
require "vendor/autoload.php";

foreach (glob(__DIR__."/models/*.php") as $fileName)
{
    require_once $fileName;
}

use Illuminate\Database\Capsule\Manager as Capsule;
use Dotenv\Dotenv;

// Загружаем .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Теперь можно юзать переменные окружения
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $_ENV['DB_CONNECTION'],
    'host'      => $_ENV['DB_HOST'],
    'port'      => $_ENV['DB_PORT'],
    'database'  => $_ENV['DB_DATABASE'],
    'username'  => $_ENV['DB_USERNAME'],
    'password'  => $_ENV['DB_PASSWORD'],
    'charset'   => $_ENV['DB_CHARSET'],
    'schema'    => $_ENV['DB_SCHEMA'],
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    $capsule::connection()->getPdo();
} catch (\Exception $e) {
    die("Ошибка подключения: " . $e->getMessage());
}