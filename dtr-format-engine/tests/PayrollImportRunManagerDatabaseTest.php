<?php

declare(strict_types=1);

require_once __DIR__ . '/../model/PayrollImportRunManager.php';
require_once __DIR__ . '/../model/PayrollPopulationExceptionManager.php';

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

function payroll_database_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$database = 'taascor_codex_payflow_' . bin2hex(random_bytes(5));
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
$artifactPath = null;
$previousArtifactRoot = getenv('TAASCOR_PAYSLIP_ARTIFACT_ROOT');

try {
    $server->exec("CREATE DATABASE {$database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            $multiStatementsAttribute => true,
        ]
    );
    $db->exec("
        CREATE TABLE taascor_client (
            client_id INT NOT NULL,
            client_name VARCHAR(160) NOT NULL,
            PRIMARY KEY (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->exec("
        CREATE TABLE employee_list (
            employee_id INT NOT NULL,
            client_id INT NOT NULL,
            client_location_id INT NULL,
            payroll_employee_id VARCHAR(80) NULL,
            old_employee_id VARCHAR(80) NULL,
            full_name VARCHAR(255) NULL,
            first_name VARCHAR(120) NULL,
            last_name VARCHAR(120) NULL,
            hire_date DATE NULL,
            separation_date DATE NULL,
            status VARCHAR(40) NOT NULL,
            PRIMARY KEY (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $migrationDir = dirname(__DIR__) . '/migrations/';
    foreach ([
        '20260621_01_dtr_format_engine_base.sql',
        '20260621_02_dtr_staging_columns.sql',
        '20260621_03_payroll_basis_preview.sql',
        '20260716_employee_identity_notifications.sql',
        '20260717_01_payroll_import_run_foundation.sql',
        '20260726_02_payroll_population_exceptions.sql',
        '20260727_03_multi_client_dtr_intake.sql',
    ] as $migration) {
        $db->exec((string)file_get_contents($migrationDir . $migration));
    }
    foreach ([
        'dtr_upload', 'payroll_gross_variables', 'payroll_summary',
        'payroll_other_additional', 'payroll_other_deduction', 'loans_payment',
    ] as $legacyTable) {
        $db->exec("\n            CREATE TABLE `{$legacyTable}` (
                id INT NOT NULL AUTO_INCREMENT,
                employee_id INT NOT NULL,
                client_name VARCHAR(160) NOT NULL,
                pay_day DATE NOT NULL,
                amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    $db->exec("INSERT INTO taascor_client (client_id, client_name) VALUES (264, 'Fuji test client')");
    $db->exec("
        INSERT INTO employee_list (
            employee_id, client_id, client_location_id, payroll_employee_id,
            old_employee_id, full_name, first_name, last_name, hire_date, separation_date, status
        ) VALUES (
            1001, 264, 9, 'PAY-1001', 'OLD-1001',
            'Test, Employee', 'Employee', 'Test', '2025-01-01', NULL, 'Active'
        ), (
            1002, 264, 9, 'PAY-1002', 'OLD-1002',
            'Future, Employee', 'Employee', 'Future', '2026-07-01', NULL, 'Active'
        )
    ");
    $db->exec("
        INSERT INTO dtr_format_templates (
            template_name, client_id, source_type, file_type, expected_headers,
            date_format, time_format, employee_identifier_field, created_by
        ) VALUES (
            'Fuji run foundation test', 264, 'fuji_payroll_summary', 'xlsx',
            '[]', 'Y-m-d', 'H:i', 'employee_identifier', 'codex_test'
        )
    ");
    $templateId = (int)$db->lastInsertId();
    $batch = $db->prepare("
        INSERT INTO dtr_upload_batches (
            batch_uid, template_id, original_filename, uploaded_by, checksum,
            row_count, validation_status, error_count, processing_status,
            is_synthetic, source_context
        ) VALUES (
            'PAYRUN-DB-TEST', :template_id, 'fuji-test.xlsx', 'payroll_maker',
            :checksum, 1, 'passed', 0, 'identity_ready', 0, 'fuji_payroll_summary'
        )
    ");
    $batch->execute([':template_id' => $templateId, ':checksum' => hash('sha256', 'fixture')]);
    $batchId = (int)$db->lastInsertId();
    $parsed = [
        'employee_identifier' => 'FUJI-1001',
        'employee_name' => 'Test, Employee',
        'period_start' => '2026-06-16',
        'period_end' => '2026-06-30',
        'pay_date' => '2026-07-05',
        'worked_days' => 10.0,
        'regular_hours' => 80.0,
        'source_adapter' => 'FUJI_PAYROLL_SUMMARY',
    ];
    $row = $db->prepare("
        INSERT INTO dtr_upload_staging_rows (
            batch_id, source_row_number, raw_payload, parsed_payload,
            validation_status, error_summary, is_synthetic
        ) VALUES (
            :batch_id, 4, :raw_payload, :parsed_payload, 'valid', '[]', 0
        )
    ");
    $row->execute([
        ':batch_id' => $batchId,
        ':raw_payload' => json_encode(['B' => 'FUJI-1001', 'C' => 'Test, Employee']),
        ':parsed_payload' => json_encode($parsed),
    ]);

    $manager = new PayrollImportRunManager();
    $manager->db = $db;
    $options = [
        'ruleset_key' => 'PH_FUJI_PAYROLL',
        'ruleset_version' => '2026.07-test',
        'rules' => [
            [
                'rule_type' => 'rounding',
                'rule_key' => 'money_rounding',
                'rule_version' => '1',
                'effective_from' => '2026-01-01',
                'snapshot' => ['scale' => 2, 'mode' => 'half_up'],
            ],
            [
                'rule_type' => 'statutory',
                'rule_key' => 'contribution_tables',
                'rule_version' => '2026.01',
                'effective_from' => '2026-01-01',
                'snapshot' => ['source' => 'owner_approved_fixture'],
            ],
        ],
    ];

    $mainAlias = $manager->recordIdentityDecision([
        'client_id' => 264,
        'source_namespace' => 'template:' . $templateId,
        'source_employee_id' => 'FUJI-1001',
        'employee_id' => 1001,
        'decision_type' => 'safe_cohort_owner_approval',
        'reason' => 'Owner-approved Fuji fixture alias',
        'effective_from' => '2026-06-16',
        'evidence' => ['test_fixture' => true],
    ], 'identity_owner');
    payroll_database_check(($mainAlias['success'] ?? 0) === 1, 'Fuji canonical handoff is backed by an approved alias');

    $populationManager = new PayrollPopulationExceptionManager();
    $populationManager->db = $db;
    $populationImport = $populationManager->importForBatch($batchId, [[
        'reference_employee_name' => 'Payslip Only, Employee',
        'matched_employee_id' => 1001,
        'evidence' => [
            'source' => 'expected_payslip_reconciliation',
            'pdf_page' => 1,
            'pay_date' => '2026-07-05',
        ],
    ]], 'payroll_owner');
    payroll_database_check(
        ($populationImport['success'] ?? 0) === 1
        && (int)($populationImport['summary']['open'] ?? 0) === 1,
        'a payslip-only employee is recorded as an open population exception'
    );
    $populationBlocked = $manager->createFromStagedBatch($batchId, $options, 'payroll_maker');
    payroll_database_check(
        ($populationBlocked['success'] ?? 1) === 0
        && ($populationBlocked['error_code'] ?? '') === 'BATCH_NOT_READY'
        && (int)($populationBlocked['details']['open_population_exception_count'] ?? 0) === 1,
        'an open payslip-to-DTR population exception blocks canonical run creation'
    );
    $populationExceptionId = (int)($populationImport['data'][0]['id'] ?? 0);
    $populationResolved = $populationManager->resolve(
        $populationExceptionId,
        'approved_off_cycle',
        'Payroll owner approved the documented off-cycle treatment for this test fixture.',
        'payroll_owner'
    );
    payroll_database_check(
        ($populationResolved['success'] ?? 0) === 1
        && $populationManager->openCountForBatch($batchId) === 0,
        'an evidence-backed owner disposition clears the population gate'
    );

    $directBatch = $db->prepare("\n        INSERT INTO dtr_upload_batches (
            batch_uid, template_id, original_filename, uploaded_by, checksum,
            row_count, validation_status, error_count, processing_status,
            is_synthetic, source_context
        ) VALUES (
            'PAYRUN-FUJI-DIRECT-ID-BLOCK', :template_id, 'fuji-direct-id.xlsx', 'payroll_maker',
            :checksum, 1, 'passed', 0, 'identity_ready', 0, 'fuji_payroll_summary'
        )
    ");
    $directBatch->execute([':template_id' => $templateId, ':checksum' => hash('sha256', 'direct-id-fixture')]);
    $directBatchId = (int)$db->lastInsertId();
    $directPayload = $parsed;
    $directPayload['employee_identifier'] = '1001';
    $row->execute([
        ':batch_id' => $directBatchId,
        ':raw_payload' => json_encode(['B' => '1001', 'C' => 'Test, Employee']),
        ':parsed_payload' => json_encode($directPayload),
    ]);
    $directCreated = $manager->createFromStagedBatch($directBatchId, $options, 'payroll_maker');
    payroll_database_check(($directCreated['success'] ?? 0) === 1, 'raw Fuji vendor-ID regression fixture creates a draft');
    $directResult = $manager->canonicalizeFromStaging((int)$directCreated['run']['id'], 'payroll_maker');
    payroll_database_check(
        ($directResult['success'] ?? 1) === 0
        && ($directResult['error_code'] ?? '') === 'IDENTITY_GATE_BLOCKED',
        'raw Fuji vendor IDs cannot bypass approved identity mapping'
    );
    $directCanonicalCount = (int)$db->query(
        'SELECT COUNT(*) FROM payroll_import_run_rows WHERE run_id = ' . (int)$directCreated['run']['id']
    )->fetchColumn();
    payroll_database_check($directCanonicalCount === 0, 'blocked Fuji vendor-ID handoff leaves no canonical rows');

    $futureAlias = $manager->recordIdentityDecision([
        'client_id' => 264,
        'source_namespace' => 'template:' . $templateId,
        'source_employee_id' => 'FUJI-FUTURE',
        'employee_id' => 1002,
        'decision_type' => 'manual_match',
        'reason' => 'Period eligibility regression fixture',
        'effective_from' => '2026-06-16',
        'evidence' => ['test_fixture' => true],
    ], 'identity_owner');
    payroll_database_check(($futureAlias['success'] ?? 0) === 1, 'future-hire fixture has an approved alias');
    $futureBatch = $db->prepare("\n        INSERT INTO dtr_upload_batches (
            batch_uid, template_id, original_filename, uploaded_by, checksum,
            row_count, validation_status, error_count, processing_status,
            is_synthetic, source_context
        ) VALUES (
            'PAYRUN-FUTURE-HIRE-BLOCK', :template_id, 'future-hire.xlsx', 'payroll_maker',
            :checksum, 1, 'passed', 0, 'identity_ready', 0, 'fuji_payroll_summary'
        )
    ");
    $futureBatch->execute([':template_id' => $templateId, ':checksum' => hash('sha256', 'future-hire-fixture')]);
    $futureBatchId = (int)$db->lastInsertId();
    $futurePayload = $parsed;
    $futurePayload['employee_identifier'] = 'FUJI-FUTURE';
    $row->execute([
        ':batch_id' => $futureBatchId,
        ':raw_payload' => json_encode(['B' => 'FUJI-FUTURE', 'C' => 'Future, Employee']),
        ':parsed_payload' => json_encode($futurePayload),
    ]);
    $futureCreated = $manager->createFromStagedBatch($futureBatchId, $options, 'payroll_maker');
    payroll_database_check(($futureCreated['success'] ?? 0) === 1, 'future-hire regression fixture creates a draft');
    $futureResult = $manager->canonicalizeFromStaging((int)$futureCreated['run']['id'], 'payroll_maker');
    payroll_database_check(
        ($futureResult['success'] ?? 1) === 0
        && ($futureResult['error_code'] ?? '') === 'IDENTITY_GATE_BLOCKED',
        'an approved alias cannot canonicalize an employee hired after the payroll period'
    );

    $created = $manager->createFromStagedBatch($batchId, $options, 'payroll_maker');
    payroll_database_check(($created['success'] ?? 0) === 1, 'draft run is created from a ready staged batch');
    $runId = (int)$created['run']['id'];
    $replayed = $manager->createFromStagedBatch($batchId, $options, 'payroll_maker');
    payroll_database_check(
        ($replayed['success'] ?? 0) === 1
        && ($replayed['idempotent_replay'] ?? false) === true
        && (int)$replayed['run']['id'] === $runId,
        'equivalent run creation is idempotent'
    );

    $canonical = $manager->canonicalizeFromStaging($runId, 'payroll_maker');
    payroll_database_check(
        ($canonical['success'] ?? 0) === 1
        && $canonical['run']['status'] === 'canonicalized'
        && (int)$canonical['run']['canonical_row_count'] === 1,
        'resolved staging rows are atomically canonicalized'
    );
    $canonicalCount = (int)$db->query("SELECT COUNT(*) FROM payroll_import_run_rows WHERE run_id = {$runId}")->fetchColumn();
    payroll_database_check($canonicalCount === 1, 'exactly one run-scoped canonical row is stored');

    $controls = $manager->recordControlStatuses(
        $runId,
        'passed',
        'passed',
        [
            'calculation' => ['gross_delta' => 0],
            'reconciliation' => ['net_pay_delta' => 0],
        ],
        'payroll_maker'
    );
    payroll_database_check(
        ($controls['success'] ?? 0) === 1 && $controls['run']['status'] === 'validating',
        'passed calculation and reconciliation evidence is recorded'
    );
    foreach ([
        'dtr_upload', 'payroll_gross_variables', 'payroll_summary',
        'payroll_other_additional', 'payroll_other_deduction', 'loans_payment',
    ] as $legacyTable) {
        $legacyInsert = $db->prepare(
            "INSERT INTO `{$legacyTable}` (employee_id, client_name, pay_day, amount) "
            . "VALUES (1001, 'Fuji test client', '2026-07-05', 100.00)"
        );
        $legacyInsert->execute();
    }
    $binding = $manager->bindLegacyPayrollScope($runId, 'payroll_maker');
    payroll_database_check(
        ($binding['success'] ?? 0) === 1
        && preg_match('/^[a-f0-9]{64}$/', (string)($binding['binding']['live_snapshot_hash'] ?? '')) === 1,
        'calculated legacy rows are population-matched and sealed to the run'
    );
    $ready = $manager->markReadyForApproval($runId, 'payroll_maker');
    payroll_database_check(
        ($ready['success'] ?? 0) === 1 && $ready['run']['status'] === 'ready_for_approval',
        'all pre-approval controls move the run to ready'
    );
    $makerApproval = $manager->approveRun($runId, 'PAYROLL_MAKER');
    payroll_database_check(
        ($makerApproval['success'] ?? 1) === 0
        && ($makerApproval['error_code'] ?? '') === 'MAKER_CHECKER_CONFLICT',
        'maker cannot approve their own run'
    );
    $approved = $manager->approveRun($runId, 'payroll_checker');
    payroll_database_check(
        ($approved['success'] ?? 0) === 1 && $approved['run']['status'] === 'approved',
        'independent checker can approve a fully controlled run'
    );

    $artifactContents = "%PDF-1.4\nprivate-payslip-fixture\n%%EOF\n";
    $artifactRoot = dirname(dirname(__DIR__, 2)) . DIRECTORY_SEPARATOR . 'TAASCOR-private' . DIRECTORY_SEPARATOR . 'payruns';
    $artifactDirectory = $artifactRoot . DIRECTORY_SEPARATOR . 'test';
    if (!is_dir($artifactDirectory) && !mkdir($artifactDirectory, 0770, true) && !is_dir($artifactDirectory)) {
        throw new RuntimeException('Unable to create the private payslip fixture directory.');
    }
    $artifactPath = $artifactDirectory . DIRECTORY_SEPARATOR . $database . '-1001.pdf';
    if (file_put_contents($artifactPath, $artifactContents) === false) {
        throw new RuntimeException('Unable to create the private payslip fixture.');
    }
    putenv('TAASCOR_PAYSLIP_ARTIFACT_ROOT=' . $artifactRoot);
    $artifactStoragePath = 'payruns/test/' . basename($artifactPath);
    $contentHash = hash('sha256', $artifactContents);
    $forged = $manager->registerPayslipArtifact($runId, [
        'employee_id' => 1001,
        'storage_path' => $artifactStoragePath,
        'content_hash' => hash('sha256', 'different-contents'),
        'artifact_status' => 'verified',
    ], 'payroll_checker');
    payroll_database_check(
        ($forged['success'] ?? 1) === 0 && ($forged['error_code'] ?? '') === 'ARTIFACT_INTEGRITY_FAILED',
        'artifact registration rejects a forged content hash'
    );
    $generated = $manager->registerPayslipArtifact($runId, [
        'employee_id' => 1001,
        'storage_path' => $artifactStoragePath,
        'content_hash' => $contentHash,
        'artifact_status' => 'generated',
        'byte_size' => 2048,
    ], 'payroll_checker');
    payroll_database_check(
        ($generated['success'] ?? 0) === 1 && ($generated['coverage']['complete'] ?? true) === false,
        'generated but unverified payslip does not satisfy release coverage'
    );
    $storedByteSize = (int)$db->query("SELECT byte_size FROM payroll_import_payslip_artifacts WHERE run_id = {$runId} AND employee_id = 1001")->fetchColumn();
    payroll_database_check(
        $storedByteSize === strlen($artifactContents),
        'artifact byte size is measured from the sealed file instead of trusting request metadata'
    );
    $blockedRelease = $manager->getReleaseStatus($runId);
    payroll_database_check(
        ($blockedRelease['success'] ?? 0) === 1
        && ($blockedRelease['gate']['eligible'] ?? true) === false
        && $blockedRelease['run']['release_status'] === 'blocked',
        'release status remains blocked before payslip verification'
    );
    $verified = $manager->registerPayslipArtifact($runId, [
        'employee_id' => 1001,
        'storage_path' => $artifactStoragePath,
        'content_hash' => $contentHash,
        'artifact_status' => 'verified',
        'byte_size' => 2048,
    ], 'payroll_checker');
    payroll_database_check(
        ($verified['success'] ?? 0) === 1 && ($verified['coverage']['complete'] ?? false) === true,
        'verified payslip completes employee artifact coverage'
    );
    $released = $manager->getReleaseStatus($runId);
    payroll_database_check(
        ($released['success'] ?? 0) === 1
        && $released['run']['status'] === 'approved'
        && $released['run']['release_status'] === 'ready'
        && ($released['gate']['eligible'] ?? false) === true,
        'approved fully evidenced run is ready for atomic payroll posting'
    );
    $outboxCount = (int)$db->query("SELECT COUNT(*) FROM payroll_import_outbox WHERE aggregate_id = {$runId}")->fetchColumn();
    payroll_database_check($outboxCount >= 6, 'run lifecycle produces transactional outbox events');
    $latest = $manager->getLatestRunForBatch($batchId);
    payroll_database_check(
        ($latest['success'] ?? 0) === 1
        && (int)$latest['run']['id'] === $runId
        && $latest['run']['status'] === 'approved'
        && $latest['run']['release_status'] === 'ready'
        && isset($latest['checks'], $latest['gate'], $latest['artifact_coverage']),
        'latest batch lookup reloads durable run, checks, gate, and artifact coverage'
    );

    foreach (['SRC-A', 'SRC-B'] as $sourceAlias) {
        $decision = $manager->recordIdentityDecision([
            'client_id' => 264,
            'source_namespace' => 'template:' . $templateId,
            'source_employee_id' => $sourceAlias,
            'employee_id' => 1001,
            'decision_type' => 'manual_match',
            'reason' => 'Collision safety fixture',
            'effective_from' => '2026-01-01',
            'evidence' => ['test_fixture' => true],
        ], 'identity_owner');
        payroll_database_check(($decision['success'] ?? 0) === 1, "versioned alias {$sourceAlias} is recorded");
    }
    $collisionBatch = $db->prepare("
        INSERT INTO dtr_upload_batches (
            batch_uid, template_id, original_filename, uploaded_by, checksum,
            row_count, validation_status, error_count, processing_status,
            is_synthetic, source_context
        ) VALUES (
            'PAYRUN-COLLISION-TEST', :template_id, 'collision-test.xlsx', 'payroll_maker',
            :checksum, 2, 'passed', 0, 'identity_ready', 0, 'fuji_payroll_summary'
        )
    ");
    $collisionBatch->execute([':template_id' => $templateId, ':checksum' => hash('sha256', 'collision-fixture')]);
    $collisionBatchId = (int)$db->lastInsertId();
    $collisionRow = $db->prepare("
        INSERT INTO dtr_upload_staging_rows (
            batch_id, source_row_number, raw_payload, parsed_payload,
            validation_status, error_summary, is_synthetic
        ) VALUES (
            :batch_id, :source_row_number, :raw_payload, :parsed_payload,
            'valid', '[]', 0
        )
    ");
    foreach (['SRC-A', 'SRC-B'] as $index => $sourceAlias) {
        $collisionPayload = $parsed;
        $collisionPayload['employee_identifier'] = $sourceAlias;
        $collisionRow->execute([
            ':batch_id' => $collisionBatchId,
            ':source_row_number' => 10 + $index,
            ':raw_payload' => json_encode(['B' => $sourceAlias, 'C' => 'Test, Employee']),
            ':parsed_payload' => json_encode($collisionPayload),
        ]);
    }
    $collisionCreated = $manager->createFromStagedBatch($collisionBatchId, $options, 'payroll_maker');
    payroll_database_check(($collisionCreated['success'] ?? 0) === 1, 'collision fixture creates a draft snapshot');
    $collisionRunId = (int)$collisionCreated['run']['id'];
    $collisionResult = $manager->canonicalizeFromStaging($collisionRunId, 'payroll_maker');
    payroll_database_check(
        ($collisionResult['success'] ?? 1) === 0
        && ($collisionResult['error_code'] ?? '') === 'IDENTITY_GATE_BLOCKED'
        && (int)($collisionResult['details']['collision_count'] ?? 0) === 1,
        'different source identities targeting one employee block canonical handoff'
    );
    $collisionCanonicalCount = (int)$db->query("
        SELECT COUNT(*) FROM payroll_import_run_rows WHERE run_id = {$collisionRunId}
    ")->fetchColumn();
    payroll_database_check($collisionCanonicalCount === 0, 'blocked identity collision leaves no partial canonical rows');

    $driftBatch = $db->prepare("
        INSERT INTO dtr_upload_batches (
            batch_uid, template_id, original_filename, uploaded_by, checksum,
            row_count, validation_status, error_count, processing_status,
            is_synthetic, source_context
        ) VALUES (
            'PAYRUN-DRIFT-TEST', :template_id, 'drift-test.xlsx', 'payroll_maker',
            :checksum, 1, 'passed', 0, 'identity_ready', 0, 'fuji_payroll_summary'
        )
    ");
    $driftBatch->execute([':template_id' => $templateId, ':checksum' => hash('sha256', 'drift-fixture')]);
    $driftBatchId = (int)$db->lastInsertId();
    $row->execute([
        ':batch_id' => $driftBatchId,
        ':raw_payload' => json_encode(['B' => '1001', 'C' => 'Test, Employee']),
        ':parsed_payload' => json_encode($parsed),
    ]);
    $driftRowId = (int)$db->lastInsertId();
    $driftCreated = $manager->createFromStagedBatch($driftBatchId, $options, 'payroll_maker');
    payroll_database_check(($driftCreated['success'] ?? 0) === 1, 'drift fixture captures an immutable input snapshot');
    $mutatedPayload = $parsed;
    $mutatedPayload['worked_days'] = 11.0;
    $mutate = $db->prepare('UPDATE dtr_upload_staging_rows SET parsed_payload = :payload WHERE id = :id');
    $mutate->execute([':payload' => json_encode($mutatedPayload), ':id' => $driftRowId]);
    $driftResult = $manager->canonicalizeFromStaging((int)$driftCreated['run']['id'], 'payroll_maker');
    payroll_database_check(
        ($driftResult['success'] ?? 1) === 0
        && ($driftResult['error_code'] ?? '') === 'INPUT_SNAPSHOT_DRIFT',
        'staged data mutation after snapshot capture blocks canonical handoff'
    );
    $driftCanonicalCount = (int)$db->query("
        SELECT COUNT(*) FROM payroll_import_run_rows
        WHERE run_id = " . (int)$driftCreated['run']['id']
    )->fetchColumn();
    payroll_database_check($driftCanonicalCount === 0, 'snapshot drift leaves no partial canonical rows');

    echo "RESULT: Payroll import run database lifecycle passed end to end.\n";
} finally {
    $db = null;
    if ($artifactPath !== null && is_file($artifactPath)) {
        unlink($artifactPath);
    }
    if ($previousArtifactRoot === false) {
        putenv('TAASCOR_PAYSLIP_ARTIFACT_ROOT');
    } else {
        putenv('TAASCOR_PAYSLIP_ARTIFACT_ROOT=' . $previousArtifactRoot);
    }
    $server->exec("DROP DATABASE IF EXISTS {$database}");
}
