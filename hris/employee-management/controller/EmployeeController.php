<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,2,3,4]);session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Employee.php');
require('../../API/logs/log.php');

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

        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $employee_id = $row['employee_id'];
                $branch_id = $row['branch_id'];
                $client_id = $row['client_id'];
                $position_id = $row['position_id'];
                $department_id = $row['department_id'];

                $action = "<button id='updateBtn'  class='btn btn-sm btn-primary' value='$employee_id'
                                data-bi='$branch_id' data-ci='$client_id' data-di='$department_id' data-pi='$position_id'><i class='bx bx-pencil'></i></button>
                          <button id='deleteBtn' class='btn btn-sm btn-danger' value='$employee_id'><i class='bx bx-trash-alt'></i></button>";

                if($_SESSION['taascor_access_level'] == "4"){
                    $response['data'][] = array(
                        ""
                        ,$row['employee_id']
                        ,$row['old_employee_id']
                        ,$row['payroll_employee_id']
                        ,$row['full_name']
                        ,$row['last_name']
                        ,$row['first_name']
                        ,$row['middle_name']
                        ,$row['hire_date']
                        ,$row['separation_date']
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
                        ""
                        ,$action
                        ,$row['employee_id']
                        ,$row['old_employee_id']
                        ,$row['payroll_employee_id']
                        ,$row['full_name']
                        ,$row['last_name']
                        ,$row['first_name']
                        ,$row['middle_name']
                        ,$row['hire_date']
                        ,$row['separation_date']
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
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'delete-employee'){
    $ids = $_POST['employee_id_array'];
    $model->employee_id_array = explode(',', $ids);
    $result = $model->deleteEmployees();
    if(($result['success'] ?? 0) == 1) log_action("Employee Deleted: IDs $ids");
    echo json_encode($result);
}else if($_POST['request'] == 'terminate-employee'){
    $model->employee = $_POST['employee'];
    $model->termination_date = $_POST['termination_date'];
    $result = $model->terminateEmployee();
    if(($result['success'] ?? 0) == 1) log_action("Employee Terminated: ID {$_POST['employee']}");
    echo json_encode($result);
}else if($_POST['request'] == 'get-client-filter'){
    echo json_encode($model->getClientFilter());
}else if($_POST['request'] == 'get-client-location'){
    echo json_encode($model->getClientLocation());
}else if($_POST['request'] == 'get-branch-filter'){
    echo json_encode($model->getBranchFilter());
}else if($_POST['request'] == 'get-department-filter'){
    echo json_encode($model->getDepartmentFilter());
}else if($_POST['request'] == 'get-position-filter'){
    echo json_encode($model->getPositionFilter());
}else if($_POST['request'] == 'get-client-branch'){
    $model->branch = $_POST["branch_selected"];
    echo json_encode($model->getClientBranch());
}else if($_POST['request'] == 'update-employee'){
    $model->employee_ident = $_POST["employee_ident"];
    $model->old_employee_ident = $_POST["old_employee_ident"];
    $model->last_name = $_POST["last_name"];
    $model->first_name = $_POST["first_name"];
    $model->middle_name = $_POST["middle_name"];
    $model->hire_date = $_POST["hire_date"];
    $model->present_address = $_POST["present_address"];
    $model->permanent_address = $_POST["permanent_address"];
    $model->contact_number = $_POST["contact_number"];
    $model->email_address = $_POST["email_address"];
    $model->birthday = $_POST["birthday"];
    $model->birth_place = $_POST["birth_place"];
    $model->gender = $_POST["gender"];
    $model->civil_status = $_POST["civil_status"];
    $model->nationality = $_POST["nationality"];
    $model->emergency_person = $_POST["emergency_person"];
    $model->emergency_contact_number = $_POST["emergency_contact_number"];
    $model->branch = $_POST["branch"];
    $model->client = $_POST["client"];
    $model->department = $_POST["department"];
    $model->position = $_POST["position"];
    $model->insurance = $_POST["insurance"];
    $model->tin = $_POST["tin"];
    $model->sss = $_POST["sss"];
    $model->pag_ibig = $_POST["pag_ibig"];
    $model->philhealth = $_POST["philhealth"];
    $model->daily_salary = $_POST["daily_salary"];
    $model->bank_name = $_POST["bank_name"];
    $model->bank_account_number = $_POST["bank_account_number"];
    $model->annual_leaves = $_POST["annual_leaves"];
    $model->payroll_employee_ident = $_POST["payroll_employee_ident"];
    $model->full_name = $_POST["full_name"];
    $model->employee_type = $_POST["employee_type"];
    $model->client_location = $_POST["client_location"];
    $model->pay_type = $_POST["pay_type"];
    $model->client_date = $_POST["client_date"];

    $response = $model->validateUpdate();
    if($response['success'] == 1){
        $response = $model->updateEmployee();
        if($response['success'] == 1){
            $log = new Logs;
            $log->db = $pdoConn;
            $log->log_action = 'Update Employee';
            $log->username = $_SESSION["taascor_user_name"];
            $response['insert_logs'] = $log->insertLog();
        }
    }
    echo json_encode($response);
}else if($_POST['request'] == 'add-employee'){
    $model->old_employee_ident = $_POST["old_employee_ident"];
    $model->last_name = $_POST["last_name"];
    $model->first_name = $_POST["first_name"];
    $model->middle_name = $_POST["middle_name"];
    $model->hire_date = $_POST["hire_date"];
    $model->present_address = $_POST["present_address"];
    $model->permanent_address = $_POST["permanent_address"];
    $model->contact_number = $_POST["contact_number"];
    $model->email_address = $_POST["email_address"];
    $model->birthday = $_POST["birthday"];
    $model->birth_place = $_POST["birth_place"];
    $model->gender = $_POST["gender"];
    $model->civil_status = $_POST["civil_status"];
    $model->nationality = $_POST["nationality"];
    $model->emergency_person = $_POST["emergency_person"];
    $model->emergency_contact_number = $_POST["emergency_contact_number"];
    $model->branch = $_POST["branch"];
    $model->client = $_POST["client"];
    $model->department = $_POST["department"];
    $model->position = $_POST["position"];
    $model->insurance = $_POST["insurance"];
    $model->tin = $_POST["tin"];
    $model->sss = $_POST["sss"];
    $model->pag_ibig = $_POST["pag_ibig"];
    $model->philhealth = $_POST["philhealth"];
    $model->daily_salary = $_POST["daily_salary"];
    $model->bank_name = $_POST["bank_name"];
    $model->bank_account_number = $_POST["bank_account_number"];
    $model->annual_leaves = $_POST["annual_leaves"];
    $model->payroll_employee_ident = $_POST["payroll_employee_ident"];
    $model->full_name = $_POST["full_name"];
    $model->employee_type = $_POST["employee_type"];
    $model->client_location = $_POST["client_location"];
    $model->pay_type = $_POST["pay_type"];
    $model->client_date = $_POST["client_date"];
    
    $response = $model->validate();
    if($response['success'] == 1){
        $response = $model->addEmployee();
        if($response['success'] == 1){
            $log = new Logs;
            $log->db = $pdoConn;
            $log->log_action = 'Add Employee';
            $log->username = $_SESSION["taascor_user_name"];
            $response['insert_logs'] = $log->insertLog();
        }
    }
    echo json_encode($response);
}else {
    echo 'Unknown Request';
}

       
    


?>