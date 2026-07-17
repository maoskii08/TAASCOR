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
    $model->loan_date = $_POST['loan_date'];
    $model->loan_type = $_POST['loan_type'];
    $getList = $model->getLoanList();

    if(isset($getList['error']) == false){
        $response['success'] = 1;
        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $response['data'][] = array(
                    $row['employee_full_name']
                    ,$row['date_awarded']
                    ,number_format($row['first_cutoff'],2)
                    ,number_format($row['second_cutoff'],2)
                    ,number_format($row['total_collected'],2)
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
        $response['sql'] = $getList['sql'];
    }

    echo json_encode($response);

}else if($_POST['request'] == 'get-loan-type'){
    echo json_encode($model->getLoanType());
}else {
    echo 'Unknown Request';
}

       
    


?>