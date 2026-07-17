<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,2,3,5]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Dashboard.php');

$model = new Dashboard;
$model->db = $pdoConn;

if($_POST['request'] == 'get-new-hires'){
    echo json_encode($model->getNewHires());
}else if($_POST['request'] == 'get-male-female'){
    echo json_encode($model->getMaleFemale());
}else if($_POST['request'] == 'get-civil-status'){
    echo json_encode($model->getCivilStatus());
}else if($_POST['request'] == 'get-quantities'){
    echo json_encode($model->getQuantities());
}else if($_POST['request'] == 'get-branch-employees'){
    echo json_encode($model->getBranchEmployees());
}else if($_POST['request'] == 'get-employee-type'){
    echo json_encode($model->getEmployeeType());
}else if($_POST['request'] == 'get-client-employees'){
    echo json_encode($model->getClientEmployees());
}else if($_POST['request'] == 'get-age-bracket'){
    echo json_encode($model->getAgeBracket());
}else if($_POST['request'] == 'get-location-employees'){
    echo json_encode($model->getLocationEmployees());
}else if($_POST['request'] == 'get-monthly-hires'){
    $model->year = $_POST['year'];
    $model->branch = $_POST['branch'];
    echo json_encode($model->getMonthlyHires());
}else if($_POST['request'] == 'get-yearly-hires'){
    $model->branch = $_POST['branch'];
    echo json_encode($model->getYearlyHires());
}else if($_POST['request'] == 'get-year-filter'){
    echo json_encode($model->getYearFilter());
}else if($_POST['request'] == 'get-branch-filter'){
    echo json_encode($model->getBranchFilter());
}else {
    echo 'Unknown Request';
}

       
    


?>