<?php

declare(strict_types=1);

function test_database_connection(): PDO
{
    $configPath = dirname(__DIR__) . '/config/mysql-config.php';
    if (is_file($configPath)) {
        require_once $configPath;
    }

    $host = defined('HOST') ? HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $database = defined('DATABASE') ? DATABASE : (getenv('DB_DATABASE') ?: '');
    $user = defined('USER') ? USER : (getenv('DB_USERNAME') ?: '');
    $password = defined('PASSWORD') ? PASSWORD : (getenv('DB_PASSWORD') ?: '');
    $port = getenv('DB_PORT') ?: '3306';

    if ($database === '' || $user === '') {
        throw new RuntimeException(
            'Set DB_DATABASE and DB_USERNAME or create the ignored config/mysql-config.php before running integration tests.'
        );
    }

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}
