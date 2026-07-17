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

        $response['sql'] = $getList['sql'];

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $response['data'][] = array(
                    $row['employee_id']
                    ,$row['full_name']
                    ,$row['branch_name']
                    ,$row['client_name']
                    ,$row['client_location']
                    ,$row['philhealth_number']
                    ,$row['cleaned_philhealth']
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