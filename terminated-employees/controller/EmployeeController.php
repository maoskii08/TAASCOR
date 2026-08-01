<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,2,3,4]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Employee.php');

$model = new Employee;
$model->db = $pdoConn;
$model->access_level = $_SESSION['taascor_access_level'];
$model->client_access = $_SESSION['taascor_client'];

if($_POST['request'] == 'get-employee-list'){
    $response['data'] = [];

    $model->employee = $_POST["employee"];
    $model->client = $_POST["client"];
    $model->branch = $_POST["branch"];

    $getList = $model->getEmployeeList();

    if(isset($getList['error']) == false){

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $employee_id = $row['employee_id'];

                $action = "<button id='restoreBtn' class='btn btn-sm btn-primary' value='$employee_id'><i class='bx bx-undo'></i></button>";

                if($_SESSION['taascor_access_level'] == "4"){
                    $response['data'][] = array(
                        $row['employee_id']
                        ,$row['lifecycle_status']
                        ,$row['separation_date']
                        ,$row['old_employee_id']
                        ,$row['payroll_employee_id']
                        ,$row['full_name']
                        ,$row['last_name']
                        ,$row['first_name']
                        ,$row['middle_name']
                        ,$row['hire_date']
                        ,$row['present_address']
                        ,$row['permanent_address']
                        ,$row['contact_number']
                        ,$row['email_address']
                        ,$row['birthday']
                        ,$row['birth_place']
                        ,$row['gender']
                        ,$row['civil_status']
                        ,$row['nationality']
                        ,$row['emergency_person']
                        ,$row['emergency_contact_number']
                        ,$row['client_date']
                        ,$row['position_name']
                        ,$row['client_name']   
                        ,$row['branch_name']     
                        ,$row['client_location']
                        ,$row['department_name']
                        ,$row['tin_number']
                        ,$row['sss_number']
                        ,$row['philhealth_number']
                        ,$row['pag_ibig_number']
                        ,$row['atm_number']
                        ,$row['bank_name']
                        ,$row['insurance']
                        ,$row['annual_leaves']     
                        ,$row['employee_type']
                        ,$row['pay_type']
                    );
                }else{
                    $response['data'][] = array(
                        $action
                        ,$row['employee_id']
                        ,$row['lifecycle_status']
                        ,$row['separation_date']
                        ,$row['old_employee_id']
                        ,$row['payroll_employee_id']
                        ,$row['full_name']
                        ,$row['last_name']
                        ,$row['first_name']
                        ,$row['middle_name']
                        ,$row['hire_date']
                        ,$row['present_address']
                        ,$row['permanent_address']
                        ,$row['contact_number']
                        ,$row['email_address']
                        ,$row['birthday']
                        ,$row['birth_place']
                        ,$row['gender']
                        ,$row['civil_status']
                        ,$row['nationality']
                        ,$row['emergency_person']
                        ,$row['emergency_contact_number']
                        ,$row['client_date']
                        ,$row['position_name']
                        ,$row['client_name']   
                        ,$row['branch_name']     
                        ,$row['client_location']
                        ,$row['department_name']
                        ,$row['tin_number']
                        ,$row['sss_number']
                        ,$row['philhealth_number']
                        ,$row['pag_ibig_number']
                        ,$row['atm_number']
                        ,$row['daily_salary']
                        ,$row['bank_name']
                        ,$row['insurance']
                        ,$row['annual_leaves']     
                        ,$row['employee_type']
                        ,$row['pay_type']
                    );
                }
            }
        }   
    } else{
        $response['error'] = $getList['error'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'restore-employee'){
    $model->employee = $_POST['employee'];
    echo json_encode($model->restoreEmployee());
}else if($_POST['request'] == 'get-client-filter'){
    echo json_encode($model->getClientFilter());
}else if($_POST['request'] == 'get-branch-filter'){
    echo json_encode($model->getBranchFilter());
}else if($_POST['request'] == 'get-client-branch'){
    $model->branch = $_POST["branch_selected"];
    echo json_encode($model->getClientBranch());
}else {
    echo 'Unknown Request';
}

       
    


?>
