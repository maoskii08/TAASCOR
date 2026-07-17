<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Payroll.php');

$model = new Payroll;
$model->db = $pdoConn;

if($_POST['request'] == 'get-payroll-summary'){
    $response['data'] = [];

    $getList = $model->getPayrollSummary();

    if(isset($getList['error']) == false){

        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $response['data'][] = array(
                    $row['client_name']
                    ,number_format($row['Total_Basic_Pay'],2)
                    ,number_format($row['Total_OT'],2)
                    ,number_format($row['Total_Leaves'],2)
                    ,number_format($row['Total_Other_Additional'],2)
                    ,number_format($row['Total_Gross_Income'],2)
                    ,number_format($row['Total_Taxable'],2)
                    ,number_format($row['Total_Tax'],2)
                    ,number_format($row['Total_tardy'],2)
                    ,number_format($row['Total_Employee_SSS'],2)
                    ,number_format($row['Total_Employee_SSS_MPF'],2)
                    ,number_format($row['Total_Employee_Philhealth'],2)
                    ,number_format($row['Total_Employee_Pagibig'],2)
                    ,number_format($row['Total_Employee_Loan'],2)
                    ,number_format($row['Total_Other_Deduction'],2)
                    ,number_format($row['Total_Net_Pay'],2)
                    ,number_format($row['Total_13th_Month'],2)
                    ,number_format($row['Total_Employer_SSS'],2)
                    ,number_format($row['Total_Employer_SSS_MPF'],2)
                    ,number_format($row['Total_Employer_SSS_EC'],2)
                    ,number_format($row['Total_Employer_Philhealth'],2)
                    ,number_format($row['Total_Employer_Pagibig'],2)
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else {
    echo 'Unknown Request';
}

       
    


?>