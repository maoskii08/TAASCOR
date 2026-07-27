<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/dtr-format-engine/model/DtrAdapterRegistry.php';

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
    $db->exec("\n        CREATE TABLE taascor_user_access (\n            id INT NOT NULL AUTO_INCREMENT,\n            employee_user_name VARCHAR(255) DEFAULT NULL,\n            PRIMARY KEY (id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
    $db->exec("
        CREATE TABLE dtr_mutation_audit_events (
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
            ),
            KEY idx_dtr_mutation_audit_actor (actor, created_at),
            KEY idx_dtr_mutation_audit_before_hash (before_hash),
            KEY idx_dtr_mutation_audit_after_hash (after_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $legacyDtrEvent = $db->prepare("
        INSERT INTO dtr_mutation_audit_events (
            event_uid, operation, employee_id, client_name, cut_off,
            period_start, period_end, pay_day, actor, change_reason,
            evidence_reference, before_payload, after_payload,
            before_hash, after_hash, calculator_routine, calculator_hash
        ) VALUES (
            :event_uid, 'DELETE_BULK', 0, 'FUJIFILM', 'BULK',
            NULL, NULL, '2026-07-05', 'legacy.payroll',
            'Legacy approved bulk deletion',
            'Legacy approval PAY-2026-0001', '{}', '{}',
            :before_hash, :after_hash, NULL, NULL
        )
    ");
    $emptyObjectHash = hash('sha256', '{}');
    $legacyDtrEvent->execute([
        ':event_uid' => 'DTRM-' . str_repeat('A', 32),
        ':before_hash' => $emptyObjectHash,
        ':after_hash' => $emptyObjectHash,
    ]);

    $migrations = [
        '20260621_01_dtr_format_engine_base.sql',
        '20260621_02_dtr_staging_columns.sql',
        '20260621_03_payroll_basis_preview.sql',
        '20260716_employee_identity_notifications.sql',
        '20260717_01_payroll_import_run_foundation.sql',
        '20260726_01_password_reset_security.sql',
        '20260726_02_payroll_population_exceptions.sql',
        '20260727_01_payroll_adjustment_audit.sql',
        '20260727_02_dtr_mutation_audit.sql',
        '20260727_03_multi_client_dtr_intake.sql',
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
        'payroll_import_runs',
        'payroll_import_run_inputs',
        'employee_identity_decisions',
        'employee_identity_aliases',
        'payroll_import_run_rows',
        'payroll_import_run_rule_versions',
        'payroll_import_release_checks',
        'payroll_import_payslip_artifacts',
        'payroll_import_outbox',
        'notification_delivery_outbox',
        'payroll_import_client_settings',
        'payroll_import_rule_sets',
        'payroll_import_legacy_scope_bindings',
        'payroll_import_release_locks',
        'password_reset_attempts',
        'payroll_population_exceptions',
        'payroll_adjustment_audit_events',
        'dtr_mutation_audit_events',
        'dtr_adapter_profiles',
        'dtr_adapter_profile_events',
    ];
    $tableQuery = $db->prepare("\n        SELECT COUNT(*)\n        FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name\n    ");
    foreach ($expectedTables as $table) {
        $tableQuery->execute([':schema_name' => $database, ':table_name' => $table]);
        check((int)$tableQuery->fetchColumn() === 1, "created expected table {$table}");
    }

    $db->exec("
        INSERT INTO taascor_client (client_id, client_name)
        VALUES (101, 'Adapter Client A'), (202, 'Adapter Client B')
    ");
    $db->exec("
        INSERT INTO dtr_format_templates (
            template_name, client_id, location_id, source_type, file_type,
            expected_headers, date_format, time_format, employee_identifier_field,
            is_active, created_by
        ) VALUES (
            'Client A Standard DTR', 101, NULL, 'vendor-export', 'xlsx',
            '[\"Employee ID\",\"Work Date\",\"Time In\",\"Time Out\"]',
            'Y-m-d', 'H:i', 'Employee ID', 1, 'maker.admin'
        )
    ");
    $templateId = (int)$db->lastInsertId();
    $field = $db->prepare("
        INSERT INTO dtr_format_template_fields (
            template_id, source_header, canonical_field, data_type,
            is_required, sort_order
        ) VALUES (
            :template_id, :source_header, :canonical_field, :data_type,
            1, :sort_order
        )
    ");
    foreach ([
        ['Employee ID', 'employee_identifier', 'text'],
        ['Work Date', 'work_date', 'date'],
        ['Time In', 'time_in', 'time'],
        ['Time Out', 'time_out', 'time'],
    ] as $index => $definition) {
        $field->execute([
            ':template_id' => $templateId,
            ':source_header' => $definition[0],
            ':canonical_field' => $definition[1],
            ':data_type' => $definition[2],
            ':sort_order' => $index + 1,
        ]);
    }

    $registry = new DtrAdapterRegistry();
    $registry->db = $db;
    $registry->allow_all_clients = true;
    $draft = $registry->createDraftFromTemplate([
        'template_id' => $templateId,
        'adapter_key' => 'CLIENT_A_DTR',
        'adapter_version' => 'v1.0.0',
        'display_name' => 'Client A Standard DTR',
        'parser_key' => 'template_tabular_v1',
        'identity_policy' => 'approved_mapping_required',
        'effective_from' => '2026-01-01',
    ], 'maker.admin');
    check(
        ($draft['success'] ?? 0) === 1 && ($draft['profile_status'] ?? '') === 'draft',
        'created an immutable client-bound adapter draft'
    );
    $profileId = (int)$draft['profile_id'];
    $selfApproval = $registry->approveProfile(
        $profileId,
        'maker.admin',
        'Maker attempted self approval.'
    );
    check(
        ($selfApproval['success'] ?? 1) === 0,
        'blocked adapter maker from approving the same version'
    );
    $approval = $registry->approveProfile(
        $profileId,
        'checker.admin',
        'Verified headers, client scope, identity policy, and row controls.'
    );
    check(
        ($approval['success'] ?? 0) === 1,
        'approved the adapter through a different checker'
    );
    $approved = $registry->approvedProfile($profileId, 101, 'xlsx', '2026-06-30');
    check(
        is_array($approved)
            && ($approved['identity_policy'] ?? '') === 'approved_mapping_required'
            && hash_equals(
                (string)$approved['configuration_hash'],
                hash('sha256', (string)$approved['configuration_payload'])
            ),
        'loaded only a hash-verified effective adapter profile'
    );
    check(
        $registry->approvedProfile($profileId, 202, 'xlsx', '2026-06-30') === null,
        'blocked cross-client adapter profile reuse'
    );
    $detected = $registry->detectApprovedProfile(
        101,
        'xlsx',
        ['Employee ID', 'Work Date', 'Time In', 'Time Out'],
        '2026-06-30'
    );
    check(
        ($detected['success'] ?? 0) === 1
            && (int)($detected['profile_id'] ?? 0) === $profileId
            && (float)($detected['confidence'] ?? 0) === 1.0,
        'auto-detected an approved adapter from the client and required-header fingerprint'
    );
    $unknownFormat = $registry->detectApprovedProfile(
        101,
        'xlsx',
        ['Unrelated Header', 'Another Field'],
        '2026-06-30'
    );
    check(
        ($unknownFormat['success'] ?? 1) === 0
            && ($unknownFormat['error_code'] ?? '') === 'NO_APPROVED_FORMAT_MATCH',
        'kept an unknown file structure blocked in staging intake'
    );

    $batch = $db->prepare("
        INSERT INTO dtr_upload_batches (
            batch_uid, template_id, client_id, adapter_profile_id,
            adapter_key, adapter_version, adapter_config_hash, identity_policy,
            original_filename, checksum, upload_idempotency_key,
            row_count, accepted_row_count, rejected_row_count,
            validation_status, processing_status, is_synthetic, source_context
        ) VALUES (
            'DTR-IMMUTABLE-CLIENT-TEST', :template_id, 101, :profile_id,
            'CLIENT_A_DTR', 'v1.0.0', :configuration_hash, 'approved_mapping_required',
            'client-a.xlsx', :checksum, :idempotency_key,
            4, 3, 1, 'failed', 'identity_review_required', 0, 'generic_real_dtr'
        )
    ");
    $batch->execute([
        ':template_id' => $templateId,
        ':profile_id' => $profileId,
        ':configuration_hash' => (string)$approved['configuration_hash'],
        ':checksum' => str_repeat('a', 64),
        ':idempotency_key' => str_repeat('b', 64),
    ]);
    $db->exec("UPDATE dtr_format_templates SET client_id = 202 WHERE id = {$templateId}");
    $batchClient = $db->query("
        SELECT client_id FROM dtr_upload_batches
        WHERE batch_uid = 'DTR-IMMUTABLE-CLIENT-TEST'
    ")->fetchColumn();
    check(
        (int)$batchClient === 101,
        'retained immutable batch client ownership after a template scope change'
    );

    $legacyUpgrade = $db->query("
        SELECT scope_kind, employee_id, cut_off, scope_payload, scope_hash
        FROM dtr_mutation_audit_events
        WHERE event_uid = 'DTRM-AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
    ")->fetch(PDO::FETCH_ASSOC);
    check(is_array($legacyUpgrade), 'retained the seeded legacy DTR audit event');
    check(
        ($legacyUpgrade['scope_kind'] ?? '') === 'PAYROLL_SCOPE'
            && $legacyUpgrade['employee_id'] === null
            && $legacyUpgrade['cut_off'] === null,
        'normalized legacy bulk employee and cutoff sentinels to a nullable payroll scope'
    );
    $legacyScope = json_decode(
        (string)$legacyUpgrade['scope_payload'],
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    check(
        ($legacyScope['scope_kind'] ?? '') === 'PAYROLL_SCOPE'
            && array_key_exists('employee_id', $legacyScope)
            && $legacyScope['employee_id'] === null
            && array_key_exists('cut_off', $legacyScope)
            && $legacyScope['cut_off'] === null,
        'rebuilt canonical legacy bulk scope evidence without sentinels'
    );
    check(
        hash_equals(
            hash('sha256', (string)$legacyUpgrade['scope_payload']),
            (string)$legacyUpgrade['scope_hash']
        ),
        'rebuilt the legacy bulk scope hash from the normalized payload'
    );

    $foreignKey = $db->prepare("\n        SELECT DELETE_RULE\n        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS\n        WHERE CONSTRAINT_SCHEMA = :schema_name\n          AND TABLE_NAME = 'notification_events'\n          AND CONSTRAINT_NAME = 'fk_notification_events_batch'\n    ");
    $foreignKey->execute([':schema_name' => $database]);
    check($foreignKey->fetchColumn() === 'SET NULL', 'notification history survives source batch deletion');

    echo "RESULT: Ordered migrations are rerunnable on a disposable schema.\n";
} finally {
    $db = null;
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
}
