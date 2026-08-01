<?php

declare(strict_types=1);

require __DIR__ . '/../../tests/DatabaseTestConnection.php';
require __DIR__ . '/../model/Import.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$db = test_database_connection();
$sourceId = 'CODEX-IMPORT-NS-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
$clientId = 0;
$correctNamespace = '';
$otherNamespace = 'template:codex-other';

try {
    $fixture = $db->query("\n        SELECT t.id AS template_id, t.client_id, c.client_name\n        FROM dtr_format_templates t\n        INNER JOIN taascor_client c ON c.client_id = t.client_id\n        WHERE t.source_type = 'fuji_payroll_summary'\n          AND t.is_active = 1\n        ORDER BY t.id DESC\n        LIMIT 1\n    ")->fetch(PDO::FETCH_ASSOC);
    check(is_array($fixture), 'fixture has an active Fuji staging template');
    $clientId = (int)$fixture['client_id'];
    $correctNamespace = 'template:' . (int)$fixture['template_id'];

    $employeeQuery = $db->prepare("\n        SELECT employee_id\n        FROM employee_list\n        WHERE client_id = :client_id AND status = 'Active'\n        ORDER BY employee_id\n        LIMIT 2\n    ");
    $employeeQuery->execute([':client_id' => $clientId]);
    $employees = array_map('intval', $employeeQuery->fetchAll(PDO::FETCH_COLUMN));
    check(count($employees) === 2, 'fixture has two active employees in the Fuji client');

    $insert = $db->prepare("\n        INSERT INTO employee_identity_map (\n            client_id, source_namespace, source_employee_id, employee_id, status, approved_by\n        ) VALUES (\n            :client_id, :source_namespace, :source_employee_id, :employee_id, 'approved', 'codex_namespace_test'\n        )\n    ");
    $insert->execute([
        ':client_id' => $clientId,
        ':source_namespace' => $correctNamespace,
        ':source_employee_id' => $sourceId,
        ':employee_id' => $employees[0],
    ]);
    $insert->execute([
        ':client_id' => $clientId,
        ':source_namespace' => $otherNamespace,
        ':source_employee_id' => $sourceId,
        ':employee_id' => $employees[1],
    ]);

    $import = new Import();
    $import->db = $db;
    $import->client_name = (string)$fixture['client_name'];
    $resolve = new ReflectionMethod(Import::class, 'resolveEmployeeIdentitiesForImport');
    $result = $resolve->invoke($import, [[$sourceId]], ['employee id' => 0]);
    check(($result['success'] ?? 0) === 1, 'approved Fuji mapping opens the import identity gate');
    check((int)$result['data'][0][0] === $employees[0], 'only the active Fuji template namespace is consumed');

    $deleteCorrect = $db->prepare("\n        DELETE FROM employee_identity_map\n        WHERE client_id = :client_id\n          AND source_namespace = :source_namespace\n          AND source_employee_id = :source_employee_id\n    ");
    $deleteCorrect->execute([
        ':client_id' => $clientId,
        ':source_namespace' => $correctNamespace,
        ':source_employee_id' => $sourceId,
    ]);
    $blocked = $resolve->invoke($import, [[$sourceId]], ['employee id' => 0]);
    check(($blocked['success'] ?? 1) === 0, 'a mapping from another template cannot open the Fuji gate');
    check(
        ($blocked['identity_gate']['unresolved'][0]['reason'] ?? '') === 'fuji_mapping_not_approved',
        'cross-template mappings produce the expected blocking reason'
    );

    echo "RESULT: Fuji import namespace isolation passed.\n";
} finally {
    if ($clientId > 0) {
        $cleanup = $db->prepare("\n            DELETE FROM employee_identity_map\n            WHERE client_id = :client_id AND source_employee_id = :source_employee_id\n        ");
        $cleanup->execute([':client_id' => $clientId, ':source_employee_id' => $sourceId]);
    }
}
