<?php

declare(strict_types=1);

require __DIR__ . '/../../tests/DatabaseTestConnection.php';
require __DIR__ . '/../model/EmployeeIdentityNotificationManager.php';
require __DIR__ . '/../model/SyntheticUploadParser.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$db = test_database_connection();
$manager = new EmployeeIdentityNotificationManager();
$manager->db = $db;
$batchId = 0;
$sourceMappingId = 'CODEX-IDENTITY-MAP-' . date('YmdHis');
$user = 'codex_identity_test';
$templateId = 0;
$clientId = 0;
$employeeId = 0;

try {
    $fixture = $db->query("\n        SELECT e.employee_id, e.client_id, e.first_name, e.last_name, t.id AS template_id\n        FROM employee_list e\n        INNER JOIN dtr_format_templates t ON t.client_id = e.client_id\n        WHERE e.status = 'Active'\n          AND t.source_type <> 'fuji_payroll_summary'\n          AND TRIM(COALESCE(e.first_name, '')) <> ''\n          AND TRIM(COALESCE(e.last_name, '')) <> ''\n        ORDER BY e.employee_id\n        LIMIT 1\n    ")->fetch(PDO::FETCH_ASSOC);
    check(is_array($fixture), 'fixture has an active employee with a DTR template');
    $employeeId = (int)$fixture['employee_id'];
    $clientId = (int)$fixture['client_id'];
    $templateId = (int)$fixture['template_id'];
    $employeeName = trim((string)$fixture['last_name'] . ', ' . (string)$fixture['first_name']);

    $otherEmployee = $db->prepare("\n        SELECT employee_id\n        FROM employee_list\n        WHERE client_id <> :client_id AND status = 'Active'\n        ORDER BY employee_id\n        LIMIT 1\n    ");
    $otherEmployee->execute([':client_id' => $clientId]);
    $otherEmployeeId = (int)$otherEmployee->fetchColumn();
    check($otherEmployeeId > 0, 'fixture has an active employee from another client');

    $batchUid = 'CODEX-IDENTITY-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    $insertBatch = $db->prepare("\n        INSERT INTO dtr_upload_batches (\n            batch_uid, template_id, original_filename, uploaded_by, row_count,\n            validation_status, error_count, processing_status, is_synthetic, source_context\n        ) VALUES (\n            :batch_uid, :template_id, 'codex-identity-test.xlsx', :uploaded_by, 5,\n            'failed', 5, 'validation_preview', 1, 'codex_identity_test'\n        )\n    ");
    $insertBatch->execute([
        ':batch_uid' => $batchUid,
        ':template_id' => $templateId,
        ':uploaded_by' => $user,
    ]);
    $batchId = (int)$db->lastInsertId();

    $rows = [
        [1, (string)$employeeId, $employeeName, ['unknown_employee_identifier']],
        [2, $sourceMappingId, $employeeName, ['unknown_employee_identifier']],
        [3, '', '', ['missing_employee_identifier']],
        [4, (string)$otherEmployeeId, 'Cross-client test employee', ['unknown_employee_identifier']],
        [5, (string)$employeeId, $employeeName, ['unknown_employee_identifier', 'invalid_work_date']],
    ];
    $insertRow = $db->prepare("\n        INSERT INTO dtr_upload_staging_rows (\n            batch_id, source_row_number, raw_payload, parsed_payload,\n            validation_status, error_summary, is_synthetic\n        ) VALUES (\n            :batch_id, :source_row_number, :raw_payload, :parsed_payload,\n            'error', :error_summary, 1\n        )\n    ");
    foreach ($rows as [$rowNumber, $identifier, $name, $errors]) {
        $payload = [
            'employee_identifier' => $identifier,
            'employee_name' => $name,
            'work_date' => '2026-06-16',
        ];
        $insertRow->execute([
            ':batch_id' => $batchId,
            ':source_row_number' => $rowNumber,
            ':raw_payload' => json_encode(['Employee Name' => $name], JSON_UNESCAPED_UNICODE),
            ':parsed_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':error_summary' => json_encode($errors),
        ]);
    }

    $sync = $manager->syncBatch($batchId, $user);
    check(($sync['success'] ?? 0) === 1, 'identity sync completes');
    check(($sync['gate_status'] ?? '') === 'blocked', 'unresolved identities block the batch');
    check((int)($sync['open_p0_count'] ?? 0) === 3, 'missing, mapping review, and client conflict create three P0 exceptions');

    $rowState = $db->prepare("\n        SELECT source_row_number, validation_status, error_summary\n        FROM dtr_upload_staging_rows\n        WHERE batch_id = :batch_id\n        ORDER BY source_row_number\n    ");
    $rowState->execute([':batch_id' => $batchId]);
    $states = [];
    foreach ($rowState->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $states[(int)$row['source_row_number']] = $row;
    }
    check($states[1]['validation_status'] === 'valid', 'direct HRIS match clears its identity-only validation error');
    check($states[5]['validation_status'] === 'error', 'non-identity validation errors remain blocking');
    check(json_decode((string)$states[5]['error_summary'], true) === ['invalid_work_date'], 'only identity-specific errors are removed');

    $exceptions = $manager->listExceptions($batchId, 'open');
    check(($exceptions['success'] ?? 0) === 1 && count($exceptions['data'] ?? []) === 3, 'open resolution queue lists all identity blockers');
    $byCode = [];
    foreach ($exceptions['data'] as $exception) {
        $byCode[$exception['exception_code']] = $exception;
    }
    check((int)$byCode['EMPLOYEE_MAPPING_REVIEW']['suggested_employee_id'] === $employeeId, 'name match is suggested but not auto-approved');

    $mapped = $manager->resolveException(
        (int)$byCode['EMPLOYEE_MAPPING_REVIEW']['id'],
        'map_existing',
        $employeeId,
        'Test owner approval',
        $user
    );
    check(($mapped['success'] ?? 0) === 1 && (int)$mapped['open_p0_count'] === 2, 'approved source mapping removes one blocker');

    $parser = new SyntheticUploadParser();
    $parser->db = $db;
    $findEmployee = new ReflectionMethod(SyntheticUploadParser::class, 'findEmployee');
    $mappedEmployee = $findEmployee->invoke($parser, $sourceMappingId, $clientId, $templateId);
    check((int)($mappedEmployee['employee_id'] ?? 0) === $employeeId, 'normalization parser consumes the approved source mapping');

    foreach (['MISSING_HRIS_EMPLOYEE', 'HRIS_STATUS_CONFLICT'] as $code) {
        $current = $manager->listExceptions($batchId, 'open');
        $target = null;
        foreach ($current['data'] as $exception) {
            if ($exception['exception_code'] === $code) {
                $target = $exception;
                break;
            }
        }
        check($target !== null, "{$code} remains available for owner resolution");
        $excluded = $manager->resolveException(
            (int)$target['id'],
            'exclude',
            0,
            'Test owner-approved exclusion',
            $user
        );
        check(($excluded['success'] ?? 0) === 1, "{$code} can be excluded with an audit reason");
    }

    $gate = $manager->getGateSummary($batchId);
    check(($gate['gate_status'] ?? '') === 'ready' && (int)($gate['open_p0_count'] ?? -1) === 0, 'identity gate opens after every P0 has an owner resolution');

    $batchState = $db->prepare('SELECT validation_status, processing_status, error_count FROM dtr_upload_batches WHERE id = :id');
    $batchState->execute([':id' => $batchId]);
    $batch = $batchState->fetch(PDO::FETCH_ASSOC);
    check($batch['validation_status'] === 'failed' && (int)$batch['error_count'] === 1, 'unrelated row validation still prevents a passed batch');

    $notifications = $manager->listNotifications($user, 20);
    check(($notifications['success'] ?? 0) === 1 && count($notifications['data'] ?? []) === 1, 'uploader receives one durable aggregated notification');
    $notification = $notifications['data'][0];
    check($notification['status'] === 'resolved' && (int)$notification['open_count'] === 0, 'notification resolves when the identity queue is cleared');
    $read = $manager->markNotificationRead((int)$notification['id'], $user);
    check(($read['success'] ?? 0) === 1, 'notification read state is persisted');
    $delivery = $db->prepare("\n        SELECT COUNT(*)\n        FROM notification_delivery_outbox\n        WHERE notification_id = :notification_id\n          AND recipient = :recipient\n          AND channel = 'in_app'\n          AND delivery_status = 'sent'\n    ");
    $delivery->execute([':notification_id' => (int)$notification['id'], ':recipient' => $user]);
    check((int)$delivery->fetchColumn() >= 1, 'in-app notification delivery is recorded idempotently in the durable outbox');

    $expectedOwnersQuery = $db->prepare("\n        SELECT COUNT(DISTINCT employee_user_name)\n        FROM taascor_user_access\n        WHERE is_active = b'1' AND access_level IN (1, 2, 3)\n    ");
    $expectedOwnersQuery->execute();
    $expectedOwners = (int)$expectedOwnersQuery->fetchColumn();
    $actualOwners = $db->prepare("\n        SELECT COUNT(DISTINCT r.user_name)\n        FROM notification_recipients r\n        INNER JOIN taascor_user_access u\n          ON u.employee_user_name COLLATE utf8mb4_unicode_ci = r.user_name COLLATE utf8mb4_unicode_ci\n        WHERE r.notification_id = :notification_id\n          AND u.access_level IN (1, 2, 3)\n          AND u.is_active = b'1'\n    ");
    $actualOwners->execute([':notification_id' => (int)$notification['id']]);
    check((int)$actualOwners->fetchColumn() === $expectedOwners, 'active Admin, HR, and Payroll owners receive every identity notification');

    echo "RESULT: Employee identity notification flow passed.\n";
} finally {
    if ($batchId > 0) {
        $deleteBatch = $db->prepare('DELETE FROM dtr_upload_batches WHERE id = :id');
        $deleteBatch->execute([':id' => $batchId]);
    }
    $governanceTable = $db->prepare("\n        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name\n    ");
    $governanceTable->execute([':table_name' => 'employee_identity_aliases']);
    if ((int)$governanceTable->fetchColumn() > 0) {
        $deleteAliases = $db->prepare("\n            DELETE FROM employee_identity_aliases\n            WHERE client_id = :client_id\n              AND source_namespace = :source_namespace\n              AND source_employee_id = :source_employee_id\n        ");
        $deleteAliases->execute([
            ':client_id' => $clientId,
            ':source_namespace' => 'template:' . $templateId,
            ':source_employee_id' => $sourceMappingId,
        ]);
        $deleteDecisions = $db->prepare("\n            DELETE FROM employee_identity_decisions\n            WHERE client_id = :client_id\n              AND source_namespace = :source_namespace\n              AND source_employee_id = :source_employee_id\n        ");
        $deleteDecisions->execute([
            ':client_id' => $clientId,
            ':source_namespace' => 'template:' . $templateId,
            ':source_employee_id' => $sourceMappingId,
        ]);
    }
    $deleteMap = $db->prepare("\n        DELETE FROM employee_identity_map\n        WHERE client_id = :client_id\n          AND source_namespace = :source_namespace\n          AND source_employee_id = :source_employee_id\n    ");
    $deleteMap->execute([
        ':client_id' => $clientId,
        ':source_namespace' => 'template:' . $templateId,
        ':source_employee_id' => $sourceMappingId,
    ]);
}
