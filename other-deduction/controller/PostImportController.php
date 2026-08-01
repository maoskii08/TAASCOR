<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', '128M');
require('../../config/db_connect.php');
require('../model/Import.php');
require_once('../../dtr-upload/model/PayrollLockGuard.php');
require_once('../../includes/payroll_adjustment_guard.php');

$model = new Import;
$model->db = $pdoConn;

function deduction_import_error(array $response, int $status = 422): void
{
    http_response_code($status);
    echo json_encode($response, JSON_PRETTY_PRINT);
}

$contentLength = filter_var(
    $_SERVER['CONTENT_LENGTH'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0]]
);
if ($contentLength !== false && $contentLength > PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_BYTES) {
    deduction_import_error([
        'success' => 0,
        'code' => 'workbook_request_too_large',
        'error' => 'The workbook exceeds the 1 MiB atomic upload limit.',
    ], 413);
    exit();
}
$rawBody = file_get_contents(
    "php://input",
    false,
    null,
    0,
    PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_BYTES + 1
);
$decoded = PayrollAdjustmentGuard::decodeAtomicWorkbookPayload((string)$rawBody);
if (($decoded['success'] ?? 0) !== 1) {
    deduction_import_error($decoded, (int)($decoded['http_status'] ?? 422));
    exit();
}
$payload = $decoded['payload'];
$data = $payload['data'];
$columnMap = $payload['column_map'];
$expectedRowCount = $payload['expected_row_count'];
$model->payrollDetails = is_array($payload['payrollDetails'] ?? null) ? $payload['payrollDetails'] : [];
$scope = $model->validatedPayrollScope();
if (($scope['success'] ?? 0) !== 1) {
    deduction_import_error($scope);
    exit();
}
$scopeValidation = PayrollAdjustmentGuard::validateScope($scope);
if (($scopeValidation['success'] ?? 0) !== 1) {
    deduction_import_error($scopeValidation);
    exit();
}
$scope = $scopeValidation['scope'];
$audit = PayrollAdjustmentGuard::validateAuditContext($payload);
if (($audit['success'] ?? 0) !== 1) {
    deduction_import_error($audit);
    exit();
}
$model->change_reason = $audit['audit']['change_reason'];
$model->evidence_reference = $audit['audit']['evidence_reference'];
$model->source_filename = $payload['source_filename'];

$response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
    (string)$scope['client_name'],
    (string)$scope['pay_day'],
    function () use ($model, $data, $columnMap, $expectedRowCount): array {
        return $model->add($data, $columnMap, $expectedRowCount);
    }
);
if (($response['success'] ?? 0) !== 1) {
    http_response_code(422);
}
echo json_encode ($response, JSON_PRETTY_PRINT);


?>
