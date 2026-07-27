<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,2,3,5]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Data.php');

$model = new Data;
$model->db = $pdoConn;

if($_POST['request'] == 'get-incomplete-list'){
    $response['data'] = [];

    $getList = $model->getIncompleteDetails();

    if(isset($getList['error']) == false){

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $response['data'][] = array(
                    $row['employee_id']
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
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
    }

    echo json_encode($response);

}else {
    echo 'Unknown Request';
}

       
    


?>
