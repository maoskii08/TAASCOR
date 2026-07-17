<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,2,3,4]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);
require('../../config/db_connect.php');
require('../model/Import.php');

$model = new Import;
$model->db = $pdoConn;
// $model->employee_ident = $_SESSION['taascor_employee_ident']; 

$response = [];
$rawData = json_decode(file_get_contents("php://input"), true);
$data = $rawData['data'];
$columnMap = $rawData['column_map'];
$batch = $rawData['batch'];
$importID = $rawData['importID'];

if($batch == 0){
    $import_id = $model->generateId();
}else{
    $import_id = $importID;
}

$model->import_id = $import_id;

$response = $model->add($data, $columnMap);


echo json_encode ($response, JSON_PRETTY_PRINT);


?>