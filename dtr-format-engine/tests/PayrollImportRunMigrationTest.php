<?php

declare(strict_types=1);

$configPath = dirname(__DIR__, 2) . '/config/mysql-config.php';
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

function payroll_migration_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$database = 'taascor_codex_payrun_' . bin2hex(random_bytes(5));
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
    $db->exec("\n        CREATE TABLE taascor_client (\n            client_id INT NOT NULL, client_name VARCHAR(160) NOT NULL, PRIMARY KEY (client_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
    $db->exec("\n        CREATE TABLE employee_list (\n            employee_id INT NOT NULL, client_id INT NOT NULL, status VARCHAR(40) NOT NULL, PRIMARY KEY (employee_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");

    $migrationDir = dirname(__DIR__) . '/migrations/';
    $migrations = [
        '20260621_01_dtr_format_engine_base.sql',
        '20260621_02_dtr_staging_columns.sql',
        '20260621_03_payroll_basis_preview.sql',
        '20260716_employee_identity_notifications.sql',
        '20260717_01_payroll_import_run_foundation.sql',
    ];
    foreach ([1, 2] as $pass) {
        foreach ($migrations as $migration) {
            $db->exec((string)file_get_contents($migrationDir . $migration));
        }
        echo "PASS: payroll import migration pass {$pass} completed\n";
    }

    $expectedTables = [
        'notification_delivery_outbox',
        'payroll_import_runs',
        'payroll_import_run_inputs',
        'employee_identity_decisions',
        'employee_identity_aliases',
        'payroll_import_run_rows',
        'payroll_import_run_rule_versions',
        'payroll_import_release_checks',
        'payroll_import_payslip_artifacts',
        'payroll_import_outbox',
        'payroll_import_client_settings',
        'payroll_import_rule_sets',
        'payroll_import_legacy_scope_bindings',
        'payroll_import_release_locks',
    ];
    $tableQuery = $db->prepare("\n        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name\n    ");
    foreach ($expectedTables as $table) {
        $tableQuery->execute([':schema_name' => $database, ':table_name' => $table]);
        payroll_migration_check((int)$tableQuery->fetchColumn() === 1, "created expected table {$table}");
    }

    $defaults = $db->prepare("\n        SELECT COLUMN_NAME, COLUMN_DEFAULT\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = 'payroll_import_runs'\n          AND COLUMN_NAME IN ('status', 'identity_status', 'calculation_status', 'reconciliation_status', 'release_status')\n    ");
    $defaults->execute([':schema_name' => $database]);
    $actualDefaults = [];
    foreach ($defaults->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $actualDefaults[$row['COLUMN_NAME']] = $row['COLUMN_DEFAULT'];
    }
    payroll_migration_check(($actualDefaults['status'] ?? null) === 'draft', 'run status defaults to draft');
    payroll_migration_check(($actualDefaults['identity_status'] ?? null) === 'pending', 'identity status defaults to pending');
    payroll_migration_check(($actualDefaults['release_status'] ?? null) === 'blocked', 'release status defaults to blocked');

    $outboxFk = $db->prepare("\n        SELECT DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS\n        WHERE CONSTRAINT_SCHEMA = :schema_name\n          AND TABLE_NAME = 'notification_delivery_outbox'\n          AND CONSTRAINT_NAME = 'fk_notification_delivery_event'\n    ");
    $outboxFk->execute([':schema_name' => $database]);
    payroll_migration_check($outboxFk->fetchColumn() === 'CASCADE', 'notification delivery outbox is owned by its durable event');

    $db->exec("
        INSERT INTO notification_events (
            fingerprint, event_type, severity, title, message, open_count, status
        ) VALUES (
            'OUTBOX-MIGRATION-TEST', 'DTR_EMPLOYEE_IDENTITY', 'P0',
            'Identity review required', 'One or more employees need review.', 2, 'open'
        )
    ");
    $notificationId = (int)$db->lastInsertId();
    $delivery = $db->prepare("
        INSERT INTO notification_delivery_outbox (
            delivery_uid, notification_id, idempotency_key, recipient,
            channel, delivery_payload, delivery_status
        ) VALUES (
            :delivery_uid, :notification_id, :idempotency_key, 'payroll_owner',
            'web_push', :delivery_payload, 'pending'
        )
    ");
    foreach (['revision-1', 'revision-2'] as $revision) {
        $delivery->execute([
            ':delivery_uid' => 'DELIVERY-' . $revision,
            ':notification_id' => $notificationId,
            ':idempotency_key' => hash('sha256', $notificationId . '|payroll_owner|web_push|' . $revision),
            ':delivery_payload' => json_encode(['revision' => $revision]),
        ]);
    }
    $revisionCount = (int)$db->query("
        SELECT COUNT(*) FROM notification_delivery_outbox
        WHERE notification_id = {$notificationId}
          AND recipient = 'payroll_owner'
          AND channel = 'web_push'
    ")->fetchColumn();
    payroll_migration_check($revisionCount === 2, 'changed notification revisions can enqueue separate deliveries');
    $duplicateRejected = false;
    try {
        $delivery->execute([
            ':delivery_uid' => 'DELIVERY-DUPLICATE',
            ':notification_id' => $notificationId,
            ':idempotency_key' => hash('sha256', $notificationId . '|payroll_owner|web_push|revision-2'),
            ':delivery_payload' => json_encode(['revision' => 'revision-2']),
        ]);
    } catch (PDOException $error) {
        $duplicateRejected = (string)$error->getCode() === '23000';
    }
    payroll_migration_check($duplicateRejected, 'delivery idempotency key rejects a duplicate revision');

    echo "RESULT: Payroll import foundation migration is rerunnable and complete.\n";
} finally {
    $db = null;
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
}
