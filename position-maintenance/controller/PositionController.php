<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Position.php');

$model = new Position;
$model->db = $pdoConn;

if($_POST['request'] == 'get-position-list'){
    $response['data'] = [];

    $getList = $model->getPositionList();

    if(isset($getList['error']) == false){

        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $id = $row['position_id'];

                $action = "<button id='updateBtn'  class='btn btn-sm btn-secondary' value='$id'><i class='bx bx-pencil'></i></button>";
                        //   <button id='deleteBtn' class='btn btn-sm btn-danger' value='$id'><i class='bx bx-trash-alt'></i></button>";

                $response['data'][] = array(
                    $action
                    ,$row['position_name']
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'delete-position'){
    $model->id = $_POST['id'];
    echo json_encode($model->deletePosition());
}else if($_POST['request'] == 'update-position'){
    $model->id = $_POST["id"];
    $model->position_name = $_POST["position_name"];
    echo json_encode($model->updatePosition());
}else if($_POST['request'] == 'add-position'){
    $model->position_name = $_POST["position_name"];
    echo json_encode($model->addPosition());
}else {
    echo 'Unknown Request';
}

       
    


?>