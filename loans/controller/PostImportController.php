<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1, 3]);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');
ini_set('memory_limit', '-1');
require('../../config/db_connect.php');
require('../model/Import.php');
require_once('../../dtr-upload/model/PayrollLockGuard.php');

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    echo json_encode(['success' => 0, 'error' => 'Invalid import payload.'], JSON_PRETTY_PRINT);
    exit();
}

$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$columnMap = is_array($payload['column_map'] ?? null) ? $payload['column_map'] : [];
$payrollDetails = is_array($payload['payrollDetails'] ?? null) ? $payload['payrollDetails'] : [];
$scope = PayrollLockGuard::normalizePayrollDetails($payrollDetails);
if (($scope['success'] ?? 0) !== 1) {
    echo json_encode($scope, JSON_PRETTY_PRINT);
    exit();
}

$model = new Import();
$model->db = $pdoConn;
$model->payrollDetails = [[
    (string)$scope['client_name'],
    (string)$scope['cut_off'],
    (string)$scope['pay_day'],
    (string)$scope['start_date'],
    (string)$scope['end_date'],
]];

$response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
    (string)$scope['client_name'],
    (string)$scope['pay_day'],
    static function () use ($model, $data, $columnMap): array {
        return $model->add($data, $columnMap);
    }
);
echo json_encode($response, JSON_PRETTY_PRINT);
