<?php

declare(strict_types=1);

function recruitment_database(): PDO
{
    $configPath = dirname(__DIR__, 2) . '/config/mysql-config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Recruitment database configuration is incomplete.');
    }
    require_once $configPath;

    $hostParts = explode(':', trim((string)HOST), 2);
    $host = trim($hostParts[0]);
    $port = isset($hostParts[1]) && ctype_digit($hostParts[1]) ? $hostParts[1] : '3306';

    return new PDO(
        "mysql:host={$host};port={$port};dbname=" . DATABASE . ';charset=utf8mb4',
        USER,
        PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function recruitment_data_key(): string
{
    $key = (string)getenv('TAASCOR_RECRUITMENT_DATA_KEY');
    if (strlen($key) < 32) {
        throw new RuntimeException('Recruitment data-key configuration is incomplete.');
    }
    return $key;
}

function recruitment_lookup_key(): string
{
    $key = (string)getenv('TAASCOR_RECRUITMENT_LOOKUP_KEY');
    if (strlen($key) < 32) {
        throw new RuntimeException('Recruitment lookup-key configuration is incomplete.');
    }
    return $key;
}
