<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);
require '../../config/db_connect.php';
require('../model/Import.php');

$model = new Import;
$model->db = $pdoConn;

$response = [];
$model->client_name = $_POST['client_name'];
$model->cut_off = $_POST['cut_off'];
$model->pay_day = $_POST['pay_day'];

$identityGate = $model->validateEmployeeIdentityScope();
if(($identityGate['success'] ?? 0) !== 1){
    $response = $identityGate;
}else{
    $validate = $model->validate();
    if($validate['success'] == 1){
        $response = $model->spCalculateDTR();
        $response['identity_gate'] = $identityGate['identity_gate'];
    }else{
        // Never delete failed rows and continue with a partial payroll calculation.
        $response = $validate;
        $response['partial_payroll_prevented'] = true;
    }
}


session_write_close();
echo json_encode ($response, JSON_PRETTY_PRINT);


?>
