<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['migration:', 'confirm-local']);
$migration = basename(trim((string)($options['migration'] ?? '')));
if ($migration === '' || !isset($options['confirm-local'])) {
    fwrite(STDERR, "Usage: php tools/apply-recruitment-migration.php --migration=<file.sql> --confirm-local\n");
    exit(2);
}

if (!preg_match('/^\d{8}_[A-Za-z0-9_-]+\.sql$/', $migration)) {
    fwrite(STDERR, "Invalid migration filename.\n");
    exit(2);
}

$configPath = dirname(__DIR__) . '/config/mysql-config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Local database configuration is missing. Copy config/mysql-config.example.php to the ignored config/mysql-config.php and add loopback-only credentials.\n");
    exit(2);
}
require $configPath;

$hostValue = trim((string)HOST);
$hostParts = explode(':', $hostValue, 2);
$host = strtolower(trim($hostParts[0]));
$port = isset($hostParts[1]) && ctype_digit($hostParts[1]) ? $hostParts[1] : '3306';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Refusing to apply a recruitment migration to a non-loopback database host.\n");
    exit(3);
}

$path = dirname(__DIR__) . '/recruitment/migrations/' . $migration;
if (!is_file($path)) {
    fwrite(STDERR, "Recruitment migration was not found.\n");
    exit(2);
}

$sql = (string)file_get_contents($path);
if (trim($sql) === '') {
    fwrite(STDERR, "Recruitment migration is empty.\n");
    exit(2);
}

$multiStatementsAttribute = defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    ? constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    : constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
$db = new PDO(
    "mysql:host={$host};port={$port};dbname=" . DATABASE . ';charset=utf8mb4',
    USER,
    PASSWORD,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        $multiStatementsAttribute => true,
    ]
);
$db->exec($sql);
echo "Applied local recruitment migration {$migration} to the configured loopback database.\n";
