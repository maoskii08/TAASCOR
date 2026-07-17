<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Department.php');

$model = new Department;
$model->db = $pdoConn;

if($_POST['request'] == 'get-department-list'){
    $response['data'] = [];

    $getList = $model->getDepartmentList();

    if(isset($getList['error']) == false){

        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $id = $row['department_id'];

                $action = "<button id='updateBtn'  class='btn btn-sm btn-secondary' value='$id'><i class='bx bx-pencil'></i></button>";
                        //   <button id='deleteBtn' class='btn btn-sm btn-danger' value='$id'><i class='bx bx-trash-alt'></i></button>";

                $response['data'][] = array(
                    $action
                    ,$row['department_name']
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'delete-department'){
    $model->id = $_POST['id'];
    echo json_encode($model->deleteDepartment());
}else if($_POST['request'] == 'update-department'){
    $model->id = $_POST["id"];
    $model->department_name = $_POST["department_name"];
    echo json_encode($model->updateDepartment());
}else if($_POST['request'] == 'add-department'){
    $model->department_name = $_POST["department_name"];
    echo json_encode($model->addDepartment());
}else {
    echo 'Unknown Request';
}

       
    


?>