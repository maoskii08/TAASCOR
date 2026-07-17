<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);
require('../../config/db_connect.php');
require('../model/Import.php');
require_once('../model/PayrollLockGuard.php');

$model = new Import;
$model->db = $pdoConn;
// $model->employee_ident = $_SESSION['taascor_employee_ident']; 

$response = [];
$rawData = json_decode(file_get_contents("php://input"), true);
if (!is_array($rawData)) {
    echo json_encode(['success' => 0, 'error' => 'Invalid import payload.'], JSON_PRETTY_PRINT);
    exit();
}
$data = is_array($rawData['data'] ?? null) ? $rawData['data'] : [];
$columnMap = is_array($rawData['column_map'] ?? null) ? $rawData['column_map'] : [];
$model->payrollDetails = is_array($rawData['payrollDetails'] ?? null) ? $rawData['payrollDetails'] : [];

$payrollScope = $model->validatedPayrollScope();
if (($payrollScope['success'] ?? 0) !== 1) {
    echo json_encode($payrollScope, JSON_PRETTY_PRINT);
    exit();
}

$response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
    (string)$payrollScope['client_name'],
    (string)$payrollScope['pay_day'],
    function () use ($model, $data, $columnMap): array {
        return $model->add($data, $columnMap);
    }
);
echo json_encode ($response, JSON_PRETTY_PRINT);


?>
