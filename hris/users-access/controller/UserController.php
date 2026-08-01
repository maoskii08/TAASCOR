<?php

require_once(__DIR__ . '/../../legacy-route-disabled.php');
require_once('../../includes/auth_guard.php');
auth_require_role([1]);session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/User.php');

$model = new User;
$model->db = $pdoConn;

if($_POST['request'] == 'get-user-list'){
    $response['data'] = [];

    $getList = $model->getUserList();

    if(isset($getList['error']) == false){

        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $access_level = $row['access_level'];
                $is_active = $row['is_active'];
                $id = $row['id'];
                $client = $row['client'];

                $status = "ACTIVATED";
                if(!$is_active){
                    $status = "PENDING";
                }

                $action = "<button id='updateBtn'  class='btn btn-sm btn-primary' value='$id'
                                data-al='$access_level' data-ia='$is_active' data-ci='$client'><i class='bx bx-pencil'></i></button>
                          <button id='deleteBtn' class='btn btn-sm btn-danger' value='$id'><i class='bx bx-trash-alt'></i></button>";

                $response['data'][] = array(
                    $action
                    ,$row['employee_user_name']
                    ,$row['employee_full_name']
                    ,$row['employee_email']
                    ,$row['access_description']
                    ,$status
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'delete-user'){
    $model->id = $_POST['id'];
    $result = $model->deleteUser();
    if(($result['success'] ?? 0) == 1) log_action("User Deleted: ID {$_POST['id']}");
    echo json_encode($result);
}else if($_POST['request'] == 'update-user'){
    $model->id           = $_POST["id"];
    $model->is_active    = $_POST["is_active"];
    $model->full_name    = $_POST["full_name"];
    $model->email        = $_POST["email"];
    $model->user_role    = $_POST["user_role"];
    $model->user_role_txt = $_POST["user_role_txt"];
    $model->client       = $_POST["client"];
    $result = $model->updateUser();
    if(($result['success'] ?? 0) == 1) {
        $action = $model->is_active == '0' ? 'User Activated' : 'User Updated';
        log_action("$action: ID {$_POST['id']} | Role: {$_POST['user_role_txt']}");
    }
    echo json_encode($result);
}else if($_POST['request'] == 'get-client-location'){
    echo json_encode($model->getClientLocation());
}else {
    echo 'Unknown Request';
}

       
    


?>
