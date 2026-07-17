<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/PayDay.php');

$model = new PayDay;
$model->db = $pdoConn;

if($_POST['request'] == 'get-pay-list'){
    $response['data'] = [];

    $getList = $model->getPayDayList();

    if(isset($getList['error']) == false){

        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $id = $row['id'];

                $action = "<button id='updateBtn' class='btn btn-sm btn-secondary' value='$id'><i class='bx bx-pencil'></i></button>";

                $response['data'][] = array(
                    $action
                    ,$row['client_name']
                    ,$row['cut_off']
                    ,$row['pay_day']
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'get-client-filter'){
    echo json_encode($model->getClientFilter());
}else if($_POST['request'] == 'delete-client'){
    $model->id = $_POST['id'];
    echo json_encode($model->deleteClient());
}else if($_POST['request'] == 'update-payday'){
    $model->id = $_POST["id"];
    $model->client_name = $_POST["client_name"];
    $model->cut_off = $_POST["cut_off"];
    $model->pay_day = $_POST["pay_day"];
    echo json_encode($model->updatePayDay());
}else if($_POST['request'] == 'add-payday'){
    $model->client_name = $_POST["client_name"];
    $model->cut_off = $_POST["cut_off"];
    $model->pay_day = $_POST["pay_day"];
    echo json_encode($model->addPayDay());
}else {
    echo 'Unknown Request';
}

       
    


?>