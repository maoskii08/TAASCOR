<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', '256M');

require('../../config/db_connect.php');
require('../../API/login/Login.php');

$model = new LoginClass;
$model->db = $pdoConn;

if($_POST['request'] == 'sign-up-user'){
    $model->username = $_POST['username'];
    $model->password = $_POST['password'];
    $model->firstname = $_POST['firstname'];
    $model->lastname = $_POST['lastname'];
    $model->email = $_POST['email'];
    $model->access_level = $_POST['access_level'];
    $model->access_description = $_POST['access_description'];
    $model->client = $_POST['client_location'];
    echo json_encode($model->signUpUser());
}else if($_POST['request'] == 'get-client-location'){
    echo json_encode($model->getClientLocation());
}else {
    echo 'Unknown Request';
}

       
    


?>