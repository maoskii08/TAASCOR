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

$declaredLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? 0, FILTER_VALIDATE_INT);
if ($declaredLength !== false && $declaredLength > PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_BYTES) {
    http_response_code(413);
    echo json_encode([
        'success' => 0,
        'code' => 'workbook_request_too_large',
        'error' => 'The workbook exceeds the 1 MiB atomic upload limit.',
    ], JSON_PRETTY_PRINT);
    exit();
}
$rawBody = file_get_contents(
    'php://input',
    false,
    null,
    0,
    PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_BYTES + 1
);
$decoded = PayrollAdjustmentGuard::decodeAtomicWorkbookPayload((string)$rawBody);
if (($decoded['success'] ?? 0) !== 1) {
    http_response_code((int)($decoded['http_status'] ?? 422));
    echo json_encode($decoded, JSON_PRETTY_PRINT);
    exit();
}
$rawData = $decoded['payload'];
$data = $rawData['data'];
$columnMap = $rawData['column_map'];
$model->payrollDetails = is_array($rawData['payrollDetails'] ?? null) ? $rawData['payrollDetails'] : [];
$model->change_reason = $rawData['change_reason'] ?? null;
$model->evidence_reference = $rawData['evidence_reference'] ?? null;
$model->source_filename = $rawData['source_filename'];
$scope = $model->validatedPayrollScope();
if (($scope['success'] ?? 0) !== 1) {
    http_response_code(422);
    echo json_encode($scope, JSON_PRETTY_PRINT);
    exit();
}
$audit = PayrollAdjustmentGuard::validateAuditContext($rawData);
if (($audit['success'] ?? 0) !== 1) {
    http_response_code(422);
    echo json_encode($audit, JSON_PRETTY_PRINT);
    exit();
}

$response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
    (string)$scope['client_name'],
    (string)$scope['pay_day'],
    function () use ($model, $data, $columnMap, $rawData): array {
        return $model->add($data, $columnMap, $rawData['expected_row_count']);
    }
);
if (($response['success'] ?? 0) !== 1) {
    http_response_code(422);
}
echo json_encode ($response, JSON_PRETTY_PRINT);
