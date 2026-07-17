<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/DTR.php');

$model = new DTR;
$model->db = $pdoConn;
$model->access_level = $_SESSION['taascor_access_level'];
$model->client_access = $_SESSION['taascor_client'];

if($_POST['request'] == 'get-dtr-list'){
    $response['data'] = [];
    $response['data3'] = [];

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
        
        $getList = $model->getDTRList();

        if(isset($getList['error']) == false){
            $response['success'] = 1;
            $response['sql'] = $getList['sql'];

            if(count($getList['data']) > 0){
                foreach ($getList['data'] as $key => $row) {
                    $employee_id = $row['employee_id'];
                    $action = "<button id='updateBtn'  class='btn btn-sm btn-primary' value='$employee_id'><i class='bx bx-pencil'></i></button>";
                    if($row['daily_worked'] > 0){
                        $action .= " <button id='removeBenBtn' class='btn btn-sm btn-danger' value='$employee_id'><i class='bx bx-shield-x'></i></button>
                                    <button id='deleteRecord' class='btn btn-sm btn-danger' value='$employee_id'><i class='bx bx-trash'></i></button>";
                    }
                    if($islocked['locked']){
                        $action = "<button id='updateBtn' class='btn btn-sm btn-primary' disabled><i class='bx bx-pencil'></i></button>";
                        if($row['daily_worked'] > 0){
                            $action .= " <button id='removeBenBtn' class='btn btn-sm btn-danger' disabled><i class='bx bx-shield-x'></i></button>
                                        <button id='deleteRecord' class='btn btn-sm btn-danger'><i class='bx bx-trash'></i></button>";
                        }
                    }
                    $response['data'][] = array(
                        $action
                        ,$row['payroll_employee_id']
                        ,$row['employee_id']
                        ,$row['employee_full_name']
                        ,$row['daily_salary']
                        ,$row['daily_worked']
                        ,$row['absent']
                        ,$row['lates']
                        ,$row['undertime']
                        ,$row['vacation_leave']
                        ,$row['sick_leave']
                        ,$row['overtime']
                        ,$row['night_diff']
                        ,$row['night_diff_ot']
                        ,$row['regular_holiday']
                        ,$row['regular_holiday_ot']
                        ,$row['regular_holiday_night_diff']
                        ,$row['regular_holiday_nd_ot']
                        ,$row['special_holiday']
                        ,$row['special_holiday_ot']
                        ,$row['special_holiday_night_diff']
                        ,$row['special_holiday_nd_ot']
                        ,$row['rest_day']
                        ,$row['rest_day_ot']
                        ,$row['rest_day_night_diff']
                        ,$row['rest_day_nd_ot']
                        ,$row['rest_day_regular_holiday']
                        ,$row['rest_day_regular_holiday_ot']
                        ,$row['rest_day_regular_holiday_night_diff']
                        ,$row['rest_day_regular_holiday_nd_ot']
                        ,$row['rest_day_special_holiday']
                        ,$row['rest_day_special_holiday_ot']
                        ,$row['rest_day_special_holiday_night_diff']
                        ,$row['rest_day_special_holiday_nd_ot']
                    );
                }

                foreach ($getList['data3'] as $key => $row) {
                    $response['data3'][] = array(
                        $row['employee_id']
                        ,$row['employee_full_name']
                        ,number_format($row['basic_pay'],2)
                        ,number_format($row['lates'],2)
                        ,number_format($row['undertime'],2)
                        ,number_format($row['vacation_leave'],2)
                        ,number_format($row['sick_leave'],2)
                        ,number_format($row['overtime'],2)
                        ,number_format($row['night_diff'],2)
                        ,number_format($row['night_diff_ot'],2)
                        ,number_format($row['regular_holiday'],2)
                        ,number_format($row['regular_holiday_ot'],2)
                        ,number_format($row['regular_holiday_night_diff'],2)
                        ,number_format($row['regular_holiday_nd_ot'],2)
                        ,number_format($row['special_holiday'],2)
                        ,number_format($row['special_holiday_ot'],2)
                        ,number_format($row['special_holiday_night_diff'],2)
                        ,number_format($row['special_holiday_nd_ot'],2)
                        ,number_format($row['rest_day'],2)
                        ,number_format($row['rest_day_ot'],2)
                        ,number_format($row['rest_day_night_diff'],2)
                        ,number_format($row['rest_day_nd_ot'],2)
                        ,number_format($row['rest_day_regular_holiday'],2)
                        ,number_format($row['rest_day_regular_holiday_ot'],2)
                        ,number_format($row['rest_day_regular_holiday_night_diff'],2)
                        ,number_format($row['rest_day_regular_holiday_nd_ot'],2)
                        ,number_format($row['rest_day_special_holiday'],2)
                        ,number_format($row['rest_day_special_holiday_ot'],2)
                        ,number_format($row['rest_day_special_holiday_night_diff'],2)
                        ,number_format($row['rest_day_special_holiday_nd_ot'],2)
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
}else if($_POST['request'] == 'get-branch'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getBranch());
}else if($_POST['request'] == 'get-client-location'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getClientLocation());
}else if($_POST['request'] == 'validate-manual-dates'){
    $model->client = $_POST["client"];
    $model->cut_off = $_POST['cut_off'];
    $model->pay_day = $_POST['pay_day'];
    echo json_encode($model->validateManualDates());
}else if($_POST['request'] == 'update-dtr'){
    $model->employee_ident = $_POST["employee_ident"];
    $model->daily_salary = $_POST["daily_salary"];
    $model->days_worked = $_POST["days_worked"];
    $model->absent = $_POST["absent"];
    $model->lates = $_POST["lates"];
    $model->undertime = $_POST["undertime"];
    $model->vacation_leave = $_POST["vacation_leave"];
    $model->sick_leave = $_POST["sick_leave"];
    $model->overtime = $_POST["overtime"];
    $model->night_diff = $_POST["night_diff"];
    $model->night_diff_ot = $_POST["night_diff_ot"];
    $model->regular_holiday = $_POST["regular_holiday"];
    $model->regular_holiday_ot = $_POST["regular_holiday_ot"];
    $model->regular_holiday_night_diff = $_POST["regular_holiday_night_diff"];
    $model->special_holiday = $_POST["special_holiday"];
    $model->special_holiday_ot = $_POST["special_holiday_ot"];
    $model->special_holiday_night_diff = $_POST["special_holiday_night_diff"];
    $model->rest_day = $_POST["rest_day"];
    $model->rest_day_ot = $_POST["rest_day_ot"];
    $model->rest_day_night_diff = $_POST["rest_day_night_diff"];
    $model->rd_regular_holiday = $_POST["rd_regular_holiday"];
    $model->rd_regular_holiday_ot = $_POST["rd_regular_holiday_ot"];
    $model->rd_regular_holiday_night_diff = $_POST["rd_regular_holiday_night_diff"];
    $model->rd_special_holiday = $_POST["rd_special_holiday"];
    $model->rd_special_holiday_ot = $_POST["rd_special_holiday_ot"];
    $model->rd_special_holiday_night_diff = $_POST["rd_special_holiday_night_diff"];
    $model->client = $_POST['client_name'];
    $model->cut_off = $_POST['cut_off'];
    $model->pay_day = $_POST['pay_day'];
    $model->start_date = $_POST['start_date'];
    $model->end_date = $_POST['end_date'];

    $model->regular_holiday_nd_ot = $_POST['regular_holiday_nd_ot'];
    $model->special_holiday_nd_ot = $_POST['special_holiday_nd_ot'];
    $model->rest_day_nd_ot = $_POST['rest_day_nd_ot'];
    $model->rd_regular_holiday_nd_ot = $_POST['rd_regular_holiday_nd_ot'];
    $model->rd_special_holiday_nd_ot = $_POST['rd_special_holiday_nd_ot'];

    $response = $model->updateDTR();
    if($response['success'] == 1){
        $response = $model->spCalculateDTR();
    }
    echo json_encode($response);
}else if($_POST['request'] == 'remove-govt-benefits'){
    $model->employee_ident = $_POST["employee_ident"];
    $model->pay_day = $_POST['pay_day'];
    echo json_encode($model->removeGovtBenefits());
}else if($_POST['request'] == 'delete-employee-dtr'){
    $model->employee_ident = $_POST["employee_ident"];
    $model->pay_day = $_POST['pay_day'];
    echo json_encode($model->deleteEmployeeDTR());
}else if($_POST['request'] == 'delete-dtr-upload'){
    $model->client = $_POST["client_name"];
    $model->pay_day = $_POST['pay_day'];
    $model->branch = $_POST['branch'];
    $model->client_location = $_POST['client_location'];
    echo json_encode($model->deleteDTRUpload());
}else {
    echo 'Unknown Request';
}

       
    


?>