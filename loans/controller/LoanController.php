<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3,5]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Loan.php');

$model = new Loan;
$model->db = $pdoConn;

if($_POST['request'] == 'get-loan-list'){
    $response['data'] = [];
    $model->employee = $_POST['employee'];
    $model->loan_type = $_POST['loan_type'];
    $getList = $model->getLoanList();

    if(isset($getList['error']) == false){
        $response['success'] = 1;
        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $stop = 'No';
                if($row['stop_payment'] == 1){
                    $stop = 'Yes';
                }
                $id = $row['id'];
                $action = "<button id='updateBtn' class='btn btn-sm btn-primary' value='$id'><i class='bx bx-pencil'></i></button>";

                $response['data'][] = array(
                    $action
                    ,$row['employee_id']
                    ,$row['employee_full_name']
                    ,$row['loan_type']
                    ,$row['loan_date']
                    ,$row['start_payment_date']
                    ,number_format($row['loan_amount'],2)
                    ,number_format($row['interest_amount'],2)
                    ,number_format($row['monthly_amortization'],2)
                    ,number_format($row['beginning_payment'],2)
                    ,number_format($row['in_system_payment'],2)
                    ,number_format($row['loan_running_balance'],2)
                    ,$stop
                    ,$row['reactivation_date']
                    ,$row['reactivation_remarks']
                    ,number_format($row['week1_amortization'],2)
                    ,number_format($row['week2_amortization'],2)
                    ,number_format($row['week3_amortization'],2)
                    ,number_format($row['week4_amortization'],2)
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'search-employee'){

    $model->employee_ident = $_POST['employee_ident'];

    $userExists = $model->checkIfUserExists();
    if($userExists['exists']){
        $hasPayDay = $model->checkEmployeePayDay();
        if($hasPayDay['exists']){
            $response = $model->searchEmployee();
            $response['monthly'] = $hasPayDay['monthly'];
        }else{
            $response['success'] = 3;
            $response['sql'] = $hasPayDay['sql'];
        }
    }else{
        $response['success'] = 2;
        $response['sql'] = $userExists['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'add-loan'){
    $model->employee_ident = $_POST["employee_ident"];
    $model->employee_full_name = $_POST["employee_full_name"];
    $model->loan_type = $_POST["loan_type"];
    $model->loan_date = $_POST["loan_date"];
    $model->start_payment = $_POST["start_payment"];
    $model->loan_amount = $_POST["loan_amount"];
    $model->interest_amount = $_POST["interest_amount"];
    $model->beginning_payment = $_POST["beginning_payment"];
    $model->system_payment = $_POST["system_payment"];
    $model->loan_balance = $_POST["loan_balance"];
    $model->stop_payment = $_POST["stop_payment"];
    $model->reactivation_date = $_POST["reactivation_date"];
    $model->remarks = $_POST["remarks"];
    $model->monthly_amortization = $_POST["monthly_amortization"];
    $model->week1_amortization = $_POST["week1_amortization"];
    $model->week2_amortization = $_POST["week2_amortization"];
    $model->week3_amortization = $_POST["week3_amortization"];
    $model->week4_amortization = $_POST["week4_amortization"];

    echo json_encode($model->addLoan());
}else if($_POST['request'] == 'update-loan'){
    $model->id = $_POST["id"];
    $model->employee_ident = $_POST["employee_ident"];
    $model->employee_full_name = $_POST["employee_full_name"];
    $model->loan_type = $_POST["loan_type"];
    $model->loan_date = $_POST["loan_date"];
    $model->start_payment = $_POST["start_payment"];
    $model->loan_amount = $_POST["loan_amount"];
    $model->interest_amount = $_POST["interest_amount"];
    $model->beginning_payment = $_POST["beginning_payment"];
    $model->system_payment = $_POST["system_payment"];
    $model->loan_balance = $_POST["loan_balance"];
    $model->stop_payment = $_POST["stop_payment"];
    $model->reactivation_date = $_POST["reactivation_date"];
    $model->remarks = $_POST["remarks"];
    $model->monthly_amortization = $_POST["monthly_amortization"];
    $model->week1_amortization = $_POST["week1_amortization"];
    $model->week2_amortization = $_POST["week2_amortization"];
    $model->week3_amortization = $_POST["week3_amortization"];
    $model->week4_amortization = $_POST["week4_amortization"];

    echo json_encode($model->updateLoan());
}else if($_POST['request'] == 'get-loan-type'){
    echo json_encode($model->getLoanType());
}else {
    echo 'Unknown Request';
}

       
    


?>