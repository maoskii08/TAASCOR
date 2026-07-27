<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Payslip.php');
require_once('../fuji-reference-rules.php');

$model = new Payslip;
$model->db = $pdoConn;
$model->actor = auth_user();
$model->run_id = isset($_POST['run_id']) ? (int)$_POST['run_id'] : null;
$model->allowed_client_ids = auth_client_ids();
$model->allow_all_clients = auth_has_global_client_access();
$request = (string)($_POST['request'] ?? '');

function requirePayslipClientAccess(PDO $db, string $clientName): int
{
    $clientName = trim($clientName);
    if ($clientName === '') {
        http_response_code(400);
        echo json_encode(['success' => 0, 'error' => 'A client is required.']);
        exit;
    }
    $stmt = $db->prepare('SELECT client_id FROM taascor_client WHERE client_name = :client LIMIT 1');
    $stmt->execute([':client' => $clientName]);
    $clientId = $stmt->fetchColumn();
    if ($clientId === false) {
        http_response_code(404);
        echo json_encode(['success' => 0, 'error' => 'The requested client was not found.']);
        exit;
    }
    auth_require_client_id((int)$clientId);
    return (int)$clientId;
}

$clientFields = [
    'get-payroll-summary' => 'client',
    'get-metro-bank' => 'client',
    'get-gcash' => 'client',
    'get-pnb' => 'client',
    'get-pay-day' => 'client_selected',
    'get-client-location' => 'client_selected',
    'post-payroll' => 'client',
    'get-branch' => 'client_selected',
];
if (isset($clientFields[$request])) {
    requirePayslipClientAccess($pdoConn, (string)($_POST[$clientFields[$request]] ?? ''));
}

if($request == 'get-payroll-summary'){
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
        $response['release_gate'] = $model->releaseGate();
        $clientConfig = $pdoConn->prepare('SELECT client_id FROM taascor_client WHERE client_name = :client LIMIT 1');
        $clientConfig->execute([':client' => $model->client]);
        $configuredClientId = $clientConfig->fetchColumn();
        $response['payslip_layout'] = payslip_layout_for_client(
            (string)$model->client,
            $configuredClientId === false ? null : (int)$configuredClientId
        );
        
        $getList = $model->getPayrollSummary();

        if(isset($getList['error']) == false){
            $response['success'] = 1;

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
        }
    }else{
        $response = $getPayDay;
    }

    echo json_encode($response);

}else if($request == 'get-client-filter'){
    echo json_encode($model->getClientFilter());
}else if($request == 'get-pay-type'){
    echo json_encode($model->getPayType());
}else if($request == 'get-bank-name'){
    echo json_encode($model->getBankName());
}else if($request == 'get-metro-bank'){
    $response['data'] = [];

    $model->client = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $model->cut_off = $_POST["cut_off"];

    $getMetroBank = $model->getMetroBank();
    if(isset($getMetroBank['error']) == false){
        $response['success'] = 1;
        $response['data'] = $getMetroBank['data'];
    } else{
        $response['error'] = $getMetroBank['error'];
    }
    echo json_encode($response);
}else if($request == 'get-gcash'){
    $response['data'] = [];

    $model->client = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $model->cut_off = $_POST["cut_off"];

    $getGcash = $model->getGcash();
    if(isset($getGcash['error']) == false){
        $response['success'] = 1;
        $response['data'] = $getGcash['data'];
    } else{
        $response['error'] = $getGcash['error'];
    }
    echo json_encode($response);
}else if($request == 'get-pnb'){
    $response['data'] = [];

    $model->client = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $model->cut_off = $_POST["cut_off"];

    $getPNB = $model->getPNB();
    if(isset($getPNB['error']) == false){
        $response['success'] = 1;
        $response['total_amount'] = number_format($getPNB['total_amount'],2);
        $response['data'] = $getPNB['data'];
    } else{
        $response['error'] = $getPNB['error'];
    }
    echo json_encode($response);
}else if($request == 'get-pay-day'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getPayDayFilter());
}else if($request == 'get-client-location'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getClientLocation());
}else if($request == 'post-payroll'){
    $model->client  = $_POST["client"];
    $model->pay_day = $_POST["pay_day"];
    $result = $model->postPayroll();
    if(($result['success'] ?? 0) == 1) {
        log_action("Payroll Locked: {$_POST['client']} | Pay Day: {$_POST['pay_day']}");
    }
    echo json_encode($result);
}else if($request == 'get-branch'){
    $model->client = $_POST["client_selected"];
    echo json_encode($model->getBranch());
}else {
    echo 'Unknown Request';
}

       
    


?>
