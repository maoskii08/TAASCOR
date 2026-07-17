<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Deduction.php');

$model = new Deduction;
$model->db = $pdoConn;

if($_POST['request'] == 'get-deduction-list'){
    $response['data'] = [];

    $model->client = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $model->start_date = $_POST["start_date"];
    $model->end_date = $_POST["end_date"];
    $model->cut_off = $_POST["cut_off"];
    $model->client_location = $_POST["client_location"];
    $model->branch = $_POST["branch"];

    $getPayDay = $model->getPayDay();
    if($getPayDay['success'] == 1){
        $islocked = $model->isLocked();
        $response['locked'] = $islocked['locked'];

        $getList = $model->getDeductionList();

        if(isset($getList['error']) == false){
            $response['success'] = 1;
            $response['sql'] = $getList['sql'];

            if(count($getList['data']) > 0){
                foreach ($getList['data'] as $key => $row) {
                    $id = $row["id"];
                    $action = "";
                    if($id != null && !$islocked['locked']){
                        $action = "<button id='deleteBtn' class='btn btn-sm btn-danger' value='$id'><i class='bx bx-trash-alt'></i></button>";
                    }

                    $response['data'][] = array(
                        $row['employee_id']
                        ,$row['employee_full_name']
                        ,number_format($row['amount'],2)
                        ,$row['type_of_deduction']
                        ,$action
                    );
                }
            }   
        } else{
            $response['error'] = $getList['error'];
            $response['sql'] = $getList['sql'];
        }
    }else{
        $response = $getPayDay;
    }

    echo json_encode($response);

}else if($_POST['request'] == 'get-client-filter'){
    echo json_encode($model->getClientFilter());
}else if($_POST['request'] == 'get-pay-day'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getPayDayFilter());
}else if($_POST['request'] == 'delete'){
    $model->id = $_POST["id"];
    $model->employee_id = $_POST["employee_id"];
    $model->client_name = $_POST['client_name'];
    $model->cut_off = $_POST['cut_off'];
    $model->pay_day = $_POST['pay_day'];

    $response = $model->deleteDeduction();
    if($response['success'] == 1){
        $response = $model->spDeleteDeduction();
    }
    echo json_encode($response);
}else if($_POST['request'] == 'add-individual'){
    $model->employee_id = $_POST["employee_id"];
    $model->employee_name = $_POST["employee_name"];
    $model->amount = $_POST["amount"];
    $model->type_of_deduction = $_POST["type_of_deduction"];
    $model->client_name = $_POST['client_name'];
    $model->cut_off = $_POST['cut_off'];
    $model->pay_day = $_POST['pay_day'];
    $model->start_date = $_POST['start_date'];
    $model->end_date = $_POST['end_date'];

    $response = $model->individualAdditional();
    if($response['success'] == 1){
        $response = $model->spIndividualAdditional();
    }
    echo json_encode($response);
}else if($_POST['request'] == 'get-client-location'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getClientLocation());
}else if($_POST['request'] == 'get-branch'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getBranch());
}else {
    echo 'Unknown Request';
}

       
    


?>