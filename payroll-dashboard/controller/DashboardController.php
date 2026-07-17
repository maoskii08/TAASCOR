<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Dashboard.php');

$model = new Dashboard;
$model->db = $pdoConn;

if($_POST['request'] == 'get-client-filter'){
    echo json_encode($model->getClientFilter());
}else if($_POST['request'] == 'get-pay-day'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getPayrollPeriod());
}else if($_POST['request'] == 'get-net-pay'){
    $model->client = $_POST["client_selected"];
    $model->payroll_month = $_POST["payroll_month"];
    $model->payroll_year = $_POST["payroll_year"];
    echo json_encode($model->getNetPay());
}else {
    echo 'Unknown Request';
}

       
    


?>