<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);
require '../../config/db_connect.php';
require('../model/Import.php');
require_once('../model/PayrollLockGuard.php');

$model = new Import;
$model->db = $pdoConn;

$response = [];
$model->client_name = $_POST['client_name'];
$model->cut_off = $_POST['cut_off'];
$model->pay_day = $_POST['pay_day'];

$response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
    (string)$model->client_name,
    (string)$model->pay_day,
    function () use ($model): array {
        $identityGate = $model->validateEmployeeIdentityScope();
        if (($identityGate['success'] ?? 0) !== 1) {
            return $identityGate;
        }
        $validate = $model->validate();
        if (($validate['success'] ?? 0) === 1) {
            $result = $model->spCalculateDTR();
            $result['identity_gate'] = $identityGate['identity_gate'];
            return $result;
        }
        // Never delete failed rows and continue with a partial payroll calculation.
        $validate['partial_payroll_prevented'] = true;
        return $validate;
    }
);


session_write_close();
echo json_encode ($response, JSON_PRETTY_PRINT);


?>
