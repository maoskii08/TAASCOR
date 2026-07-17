<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/mysql-config.php';
if (is_file($configPath)) {
    require_once $configPath;
}

$host = defined('HOST') ? HOST : (getenv('DB_HOST') ?: '127.0.0.1');
$user = defined('USER') ? USER : (getenv('DB_USERNAME') ?: '');
$password = defined('PASSWORD') ? PASSWORD : (getenv('DB_PASSWORD') ?: '');
$port = getenv('DB_PORT') ?: '3306';
if ($user === '') {
    throw new RuntimeException('Set DB_USERNAME or create the ignored config/mysql-config.php.');
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$database = 'taascor_codex_migration_' . bin2hex(random_bytes(5));
$multiStatementsAttribute = defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    ? constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')
    : constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS');
$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        $multiStatementsAttribute => true,
    ]
);
$db = null;

try {
    $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            $multiStatementsAttribute => true,
        ]
    );
    $db->exec("\n        CREATE TABLE taascor_client (\n            client_id INT NOT NULL,\n            client_name VARCHAR(160) NOT NULL,\n            PRIMARY KEY (client_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
    $db->exec("\n        CREATE TABLE employee_list (\n            employee_id INT NOT NULL,\n            client_id INT NOT NULL,\n            status VARCHAR(40) NOT NULL,\n            PRIMARY KEY (employee_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    $migrations = [
        '20260621_01_dtr_format_engine_base.sql',
        '20260621_02_dtr_staging_columns.sql',
        '20260621_03_payroll_basis_preview.sql',
        '20260716_employee_identity_notifications.sql',
    ];
    foreach ([1, 2] as $pass) {
        foreach ($migrations as $migration) {
            $path = dirname(__DIR__) . '/dtr-format-engine/migrations/' . $migration;
            $db->exec((string)file_get_contents($path));
        }
        echo "PASS: migration pass {$pass} completed\n";
    }

    $expectedTables = [
        'dtr_format_templates',
        'dtr_format_template_fields',
        'dtr_upload_batches',
        'dtr_upload_staging_rows',
        'dtr_payroll_basis_preview_headers',
        'dtr_payroll_basis_preview_rows',
        'employee_identity_map',
        'dtr_employee_exceptions',
        'notification_events',
        'notification_recipients',
    ];
    $tableQuery = $db->prepare("\n        SELECT COUNT(*)\n        FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name\n    ");
    foreach ($expectedTables as $table) {
        $tableQuery->execute([':schema_name' => $database, ':table_name' => $table]);
        check((int)$tableQuery->fetchColumn() === 1, "created expected table {$table}");
    }

    $foreignKey = $db->prepare("\n        SELECT DELETE_RULE\n        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS\n        WHERE CONSTRAINT_SCHEMA = :schema_name\n          AND TABLE_NAME = 'notification_events'\n          AND CONSTRAINT_NAME = 'fk_notification_events_batch'\n    ");
    $foreignKey->execute([':schema_name' => $database]);
    check($foreignKey->fetchColumn() === 'SET NULL', 'notification history survives source batch deletion');

    echo "RESULT: Ordered migrations are rerunnable on a disposable schema.\n";
} finally {
    $db = null;
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
}
