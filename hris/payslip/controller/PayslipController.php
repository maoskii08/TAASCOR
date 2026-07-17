<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Payslip.php');

$model = new Payslip;
$model->db = $pdoConn;

if($_POST['request'] == 'get-payroll-summary'){
    $response['columns'] = [];
    $response['columns2'] = [];
    $response['data'] = [];
    $response['data2'] = [];

    $model->client = $_POST["client"];
    $model->pay_type = $_POST["pay_type"];
    $model->pay_day = $_POST["pay_day"];
    $model->start_date = $_POST["start_date"];
    $model->end_date = $_POST["end_date"];
    $model->cut_off = $_POST["cut_off"];
    $model->bank_name = $_POST["bank_name"];
    $model->client_location = $_POST["client_location"];
    $model->branch = $_POST["branch"];

    $getPayDay = $model->getPayDay();
    if($getPayDay['success'] == 1){
        $islocked = $model->isLocked();
        $response['locked'] = $islocked['locked'];
        
        $getList = $model->getPayrollSummary();

        if(isset($getList['error']) == false){
            $response['success'] = 1;
            $response['sql1'] = $getList['sql1'];
            $response['sql2'] = $getList['sql2'];

            if(count($getList['data']) > 0){
                $response["columns"][] = ["data" => "action", "title" => "Action"];
                foreach ($getList['data'][0] as $key => $value) {
                    $all_zero_columns[$key] = true;
                }

                foreach ($getList['data'] as $row) {
                    foreach ($row as $key => $value) {
                        if ($value != 0) {
                            $all_zero_columns[$key] = false;
                        }
                    }
                }

                foreach ($getList['data'] as $row) {
                    $filteredRow = [];
                    $employee_id = $row['Employee_ID'];
                    $filteredRow["action"] = "<button id='empPayslip' class='btn btn-sm btn-danger' value='$employee_id'><i class='bx bx-receipt'></i></button>";

                    foreach ($row as $key => $value) {
                        if (!$all_zero_columns[$key]) { // Keep non-zero columns
                            if (!isset($response["columns"][$key])) {
                                $response["columns"][$key] = ["data" => $key, "title" => ucfirst(str_replace('_', ' ', $key))];
                            }
                            $filteredRow[$key] = ($key === 'Employee_ID') ? $value : (is_numeric($value) ? number_format($value, 2) : $value);
                        }
                    }

                    $response['data'][] = $filteredRow;
                }
                $response["columns"] = array_values($response["columns"]);
            } 
            
            if (count($getList['data2']) > 0) {
                foreach ($getList['data2'][0] as $key => $value) {
                    $all_zero_columns2[$key] = true;
                }

                foreach ($getList['data2'] as $row) {
                    foreach ($row as $key => $value) {
                        if ($value != 0) {
                            $all_zero_columns2[$key] = false; 
                        }
                    }
                }

                foreach ($getList['data2'] as $row) {
                    $filteredRow2 = [];
                    foreach ($row as $key => $value) {
                        if (!$all_zero_columns2[$key]) { 
                            if (!isset($response["columns2"][$key])) {
                                $response["columns2"][$key] = ["data" => $key, "title" => ucfirst(str_replace('_', ' ', $key))];
                            }
                            $filteredRow2[$key] = is_numeric($value) ? number_format($value, 2) : $value;
                        }
                    }
                    $response['data2'][] = $filteredRow2;
                }
                $response["columns2"] = array_values($response["columns2"]);
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
}else if($_POST['request'] == 'get-pay-type'){
    echo json_encode($model->getPayType());
}else if($_POST['request'] == 'get-bank-name'){
    echo json_encode($model->getBankName());
}else if($_POST['request'] == 'get-metro-bank'){
    $response['data'] = [];

    $model->client = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $model->cut_off = $_POST["cut_off"];

    $getMetroBank = $model->getMetroBank();
    if(isset($getMetroBank['error']) == false){
        $response['success'] = 1;
        $response['sql'] = $getMetroBank['sql'];
        $response['data'] = $getMetroBank['data'];
    } else{
        $response['error'] = $getMetroBank['error'];
        $response['sql'] = $getMetroBank['sql'];
    }
    echo json_encode($response);
}else if($_POST['request'] == 'get-gcash'){
    $response['data'] = [];

    $model->client = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $model->cut_off = $_POST["cut_off"];

    $getGcash = $model->getGcash();
    if(isset($getGcash['error']) == false){
        $response['success'] = 1;
        $response['sql'] = $getGcash['sql'];
        $response['data'] = $getGcash['data'];
    } else{
        $response['error'] = $getGcash['error'];
        $response['sql'] = $getGcash['sql'];
    }
    echo json_encode($response);
}else if($_POST['request'] == 'get-pnb'){
    $response['data'] = [];

    $model->client = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $model->cut_off = $_POST["cut_off"];

    $getPNB = $model->getPNB();
    if(isset($getPNB['error']) == false){
        $response['success'] = 1;
        $response['sql'] = $getPNB['sql'];
        $response['total_amount'] = number_format($getPNB['total_amount'],2);
        $response['data'] = $getPNB['data'];
    } else{
        $response['error'] = $getPNB['error'];
        $response['sql'] = $getPNB['sql'];
    }
    echo json_encode($response);
}else if($_POST['request'] == 'get-pay-day'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getPayDayFilter());
}else if($_POST['request'] == 'get-client-location'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getClientLocation());
}else if($_POST['request'] == 'post-payroll'){
    $model->client  = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $result = $model->postPayroll();
    if(($result['success'] ?? 0) == 1) {
        log_action("Payroll Locked: {$_POST['client']} | Pay Day: {$_POST['pay_day']}");
    }
    echo json_encode($result);
}else if($_POST['request'] == 'get-branch'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getBranch());
}else {
    echo 'Unknown Request';
}

       
    


?>