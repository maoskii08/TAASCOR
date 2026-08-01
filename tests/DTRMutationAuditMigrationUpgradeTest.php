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

$checks = 0;
function checkDtrAuditMigration(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$database = 'taascor_codex_dtr_audit_' . bin2hex(random_bytes(5));
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
    $db->exec(
        "CREATE TABLE dtr_mutation_audit_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_uid VARCHAR(64) NOT NULL,
            operation VARCHAR(32) NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            client_name VARCHAR(190) NOT NULL,
            cut_off VARCHAR(50) NOT NULL,
            period_start DATE NULL,
            period_end DATE NULL,
            pay_day DATE NOT NULL,
            actor VARCHAR(120) NOT NULL,
            change_reason TEXT NOT NULL,
            evidence_reference TEXT NOT NULL,
            before_payload LONGTEXT NOT NULL,
            after_payload LONGTEXT NOT NULL,
            before_hash CHAR(64) NOT NULL,
            after_hash CHAR(64) NOT NULL,
            calculator_routine VARCHAR(64) NULL,
            calculator_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dtr_mutation_audit_event (event_uid),
            KEY idx_dtr_mutation_audit_scope (
                client_name, pay_day, employee_id, operation
            )
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $beforePayload = '{"payroll_summary":{"employee_id":42,"net_pay":"11000.00"}}';
    $afterPayload = '{"payroll_summary":{"employee_id":42,"net_pay":"11500.00"}}';
    $insert = $db->prepare(
        'INSERT INTO dtr_mutation_audit_events ('
        . 'event_uid, operation, employee_id, client_name, cut_off, '
        . 'period_start, period_end, pay_day, actor, change_reason, '
        . 'evidence_reference, before_payload, after_payload, before_hash, '
        . 'after_hash, calculator_routine, calculator_hash'
        . ') VALUES ('
        . ':event_uid, :operation, :employee_id, :client_name, :cut_off, '
        . ':period_start, :period_end, :pay_day, :actor, :change_reason, '
        . ':evidence_reference, :before_payload, :after_payload, :before_hash, '
        . ':after_hash, NULL, NULL'
        . ')'
    );
    $insert->execute([
        ':event_uid' => 'DTRM-' . str_repeat('A', 32),
        ':operation' => 'BENEFIT',
        ':employee_id' => 42,
        ':client_name' => 'FUJIFILM',
        ':cut_off' => '16-30',
        ':period_start' => '2026-06-16',
        ':period_end' => '2026-06-30',
        ':pay_day' => '2026-07-05',
        ':actor' => 'payroll.officer',
        ':change_reason' => 'Approved statutory correction.',
        ':evidence_reference' => 'PAY-2026-0053',
        ':before_payload' => $beforePayload,
        ':after_payload' => $afterPayload,
        ':before_hash' => hash('sha256', $beforePayload),
        ':after_hash' => hash('sha256', $afterPayload),
    ]);

    $migration = (string)file_get_contents(
        dirname(__DIR__) . '/dtr-format-engine/migrations/20260727_02_dtr_mutation_audit.sql'
    );
    $db->exec($migration);
    $db->exec($migration);

    $columnQuery = $db->prepare(
        "SELECT COLUMN_NAME, IS_NULLABLE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :schema_name
           AND TABLE_NAME = 'dtr_mutation_audit_events'"
    );
    $columnQuery->execute([':schema_name' => $database]);
    $columns = [];
    foreach ($columnQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[(string)$column['COLUMN_NAME']] = (string)$column['IS_NULLABLE'];
    }
    checkDtrAuditMigration(
        ($columns['employee_id'] ?? '') === 'YES'
            && ($columns['cut_off'] ?? '') === 'YES',
        'upgrade makes employee and cutoff nullable for explicit payroll scopes'
    );
    checkDtrAuditMigration(
        ($columns['scope_kind'] ?? '') === 'NO'
            && ($columns['scope_payload'] ?? '') === 'NO'
            && ($columns['scope_hash'] ?? '') === 'NO'
            && array_key_exists('branch_id', $columns)
            && array_key_exists('client_location_id', $columns),
        'upgrade adds required scope evidence and optional filter columns'
    );

    $legacyEvent = $db->query(
        "SELECT *
         FROM dtr_mutation_audit_events
         WHERE event_uid = 'DTRM-" . str_repeat('A', 32) . "'"
    )->fetch(PDO::FETCH_ASSOC);
    checkDtrAuditMigration(
        is_array($legacyEvent)
            && $legacyEvent['scope_kind'] === 'EMPLOYEE'
            && $legacyEvent['before_payload'] === $beforePayload
            && $legacyEvent['after_payload'] === $afterPayload
            && hash_equals(
                hash('sha256', (string)$legacyEvent['scope_payload']),
                (string)$legacyEvent['scope_hash']
            ),
        'upgrade preserves legacy evidence and backfills a verifiable employee scope'
    );

    $scopePayload = '{"branch_id":7,"client_location_id":9,"client_name":"FUJIFILM","cut_off":null,"employee_id":null,"pay_day":"2026-07-05","period_end":null,"period_start":null,"scope_kind":"PAYROLL_SCOPE","table_counts":{"dtr_upload":1,"payroll_gross_variables":1,"payroll_other_additional":0,"payroll_other_deduction":0,"payroll_summary":1}}';
    $bulkInsert = $db->prepare(
        'INSERT INTO dtr_mutation_audit_events ('
        . 'event_uid, operation, scope_kind, employee_id, client_name, cut_off, '
        . 'branch_id, client_location_id, period_start, period_end, pay_day, '
        . 'actor, change_reason, evidence_reference, before_payload, after_payload, '
        . 'before_hash, after_hash, scope_payload, scope_hash'
        . ') VALUES ('
        . ':event_uid, :operation, :scope_kind, NULL, :client_name, NULL, '
        . ':branch_id, :client_location_id, NULL, NULL, :pay_day, :actor, '
        . ':change_reason, :evidence_reference, :before_payload, :after_payload, '
        . ':before_hash, :after_hash, :scope_payload, :scope_hash'
        . ')'
    );
    $bulkBefore = '{"dtr_upload":[{"employee_id":42}]}';
    $bulkAfter = '{"deleted":true,"rows":{"dtr_upload":[]},"tombstone":true}';
    $bulkInsert->execute([
        ':event_uid' => 'DTRM-' . str_repeat('B', 32),
        ':operation' => 'DELETE_BULK',
        ':scope_kind' => 'PAYROLL_SCOPE',
        ':client_name' => 'FUJIFILM',
        ':branch_id' => 7,
        ':client_location_id' => 9,
        ':pay_day' => '2026-07-05',
        ':actor' => 'payroll.officer',
        ':change_reason' => 'Approved duplicate payroll-scope deletion.',
        ':evidence_reference' => 'PAY-2026-0054',
        ':before_payload' => $bulkBefore,
        ':after_payload' => $bulkAfter,
        ':before_hash' => hash('sha256', $bulkBefore),
        ':after_hash' => hash('sha256', $bulkAfter),
        ':scope_payload' => $scopePayload,
        ':scope_hash' => hash('sha256', $scopePayload),
    ]);
    checkDtrAuditMigration(
        $bulkInsert->rowCount() === 1,
        'upgraded schema accepts a payroll-scope event with NULL employee and cutoff'
    );

    $indexQuery = $db->prepare(
        "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = :schema_name
           AND TABLE_NAME = 'dtr_mutation_audit_events'
           AND INDEX_NAME = 'idx_dtr_mutation_audit_scope'"
    );
    $indexQuery->execute([':schema_name' => $database]);
    checkDtrAuditMigration(
        $indexQuery->fetchColumn()
            === 'client_name,pay_day,scope_kind,employee_id,operation',
        'upgrade installs the bounded reader scope index'
    );

    echo "RESULT: {$checks} DTR mutation-audit migration upgrade checks passed.\n";
} finally {
    $db = null;
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
}
