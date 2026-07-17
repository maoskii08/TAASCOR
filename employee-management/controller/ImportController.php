<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,2,3,4]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);
require '../../config/db_connect.php';
require('../../API/logs/log.php');
require('../model/Import.php');

$model = new Import;
$model->db = $pdoConn;

$response = [];
$model->import_id = $_POST['import_id'];


$validate = $model->validate();
if($validate['success'] == 1){
    $response = $model->spTransferEmployeeData();
}else{
    $model->spTransferEmployeeData();
    $response = $validate;
}
$log = new Logs;
$log->db = $pdoConn;
$log->log_action = 'Upload Employee Data';
$log->username = $_SESSION["taascor_user_name"];

// $response['sql1'] = $validate['sql'];
$response['insert_logs'] = $log->insertLog();
$response['delete_tmp'] = $model->deleteTemp();

session_write_close();
echo json_encode ($response, JSON_PRETTY_PRINT);


?>