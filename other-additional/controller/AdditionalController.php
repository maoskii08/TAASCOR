<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Additional.php');
require_once('../../dtr-upload/model/PayrollLockGuard.php');
require_once('../../includes/payroll_adjustment_guard.php');

$model = new Additional;
$model->db = $pdoConn;

function additional_error(array $result, int $status = 422): void
{
    http_response_code($status);
    echo json_encode($result);
}

function additional_transaction($db, callable $operation): array
{
    try {
        $db->beginTransaction();
        $result = $operation();
        if (($result['success'] ?? 0) === 1) {
            $db->commit();
        } else {
            $db->rollBack();
        }
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Additional payroll adjustment transaction failed: ' . $error->getMessage());
        return ['success' => 0, 'code' => 'adjustment_transaction_failed', 'error' => 'The payroll addition was not saved.'];
    }
}

$request = (string)($_POST['request'] ?? '');

if($request == 'get-additional-list'){
    $response['data'] = [];

    $scopeResult = PayrollAdjustmentGuard::validateScope($_POST);
    if (($scopeResult['success'] ?? 0) !== 1) {
        additional_error($scopeResult);
        exit();
    }
    $scope = $scopeResult['scope'];
    $model->client = $scope['client_name'];
    $model->pay_day = $scope['pay_day'];
    $model->start_date = $scope['start_date'];
    $model->end_date = $scope['end_date'];
    $model->cut_off = $scope['cut_off'];
    $model->client_location = $_POST["client_location"] ?? null;
    $model->branch = $_POST["branch"] ?? null;

    $getPayDay = $model->getPayDay();
    if($getPayDay['success'] == 1){      
        $islocked = $model->isLocked();
        $response['locked'] = $islocked['locked'];

        $getList = $model->getAdditionalList();

        if(isset($getList['error']) == false){
            $response['success'] = 1;
            $employeeIds = [];
            $adjustmentCount = 0;

            if(count($getList['data']) > 0){
                foreach ($getList['data'] as $key => $row) {
                    $id = $row["id"];
                    $action = "";
                    if($id != null && !$islocked['locked']){
                        $action = "<button type='button' id='deleteBtn' class='btn btn-sm btn-danger' value='" . (int)$id
                            . "' data-employee-id='" . (int)$row['employee_id']
                            . "' aria-label='Delete this payroll addition'><i class='bx bx-trash-alt' aria-hidden='true'></i></button>";
                        $adjustmentCount++;
                    }
                    $employeeIds[(int)$row['employee_id']] = true;

                    $response['data'][] = array(
                        $row['employee_id']
                        ,$row['employee_full_name']
                        ,$id === null ? '' : number_format((float)$row['amount'],2)
                        ,$row['type_of_addition'] ?? ''
                        ,$action
                    );
                }
            }   
            $response['population_count'] = count($employeeIds);
            $response['adjustment_count'] = $adjustmentCount;
        } else{
            $response['error'] = $getList['error'];
        }
    }else{
        $response = $getPayDay;
    }

    echo json_encode($response);

}else if($request == 'get-client-filter'){
    echo json_encode($model->getClientFilter());
}else if($request == 'get-pay-day'){
    $model->client = trim((string)($_POST["client_selected"] ?? ''));
    echo json_encode($model->getPayDayFilter());
}else if($request == 'delete'){
    $scopeResult = PayrollAdjustmentGuard::validateScope($_POST);
    $auditResult = PayrollAdjustmentGuard::validateAuditContext($_POST, true);
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (($scopeResult['success'] ?? 0) !== 1 || ($auditResult['success'] ?? 0) !== 1 || $id === false || $employeeId === false) {
        additional_error(
            ($scopeResult['success'] ?? 0) !== 1
                ? $scopeResult
                : (($auditResult['success'] ?? 0) !== 1
                    ? $auditResult
                    : ['success' => 0, 'code' => 'invalid_delete_target', 'error' => 'The selected addition is invalid.'])
        );
        exit();
    }
    $scope = $scopeResult['scope'];
    $audit = $auditResult['audit'];
    $model->id = (int)$id;
    $model->employee_id = (int)$employeeId;
    $model->client_name = $scope['client_name'];
    $model->cut_off = $scope['cut_off'];
    $model->pay_day = $scope['pay_day'];
    $model->start_date = $scope['start_date'];
    $model->end_date = $scope['end_date'];

    $response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
        (string)$model->client_name,
        (string)$model->pay_day,
        function () use ($model, $pdoConn, $scope, $audit): array {
            return additional_transaction($pdoConn, function () use ($model, $pdoConn, $scope, $audit): array {
                $result = $model->deleteAdditional();
                if (($result['success'] ?? 0) === 1) {
                    $deletedAdjustment = $result['deleted_adjustment'];
                    $auditEventId = PayrollAdjustmentGuard::writeAuditRows(
                        $pdoConn,
                        'ADDITION_DELETE',
                        $scope + [
                            'change_reason' => $audit['change_reason'],
                            'evidence_reference' => $audit['evidence_reference'],
                            'row_records' => [$deletedAdjustment],
                        ]
                    );
                    $result = $model->spDeleteAdditional();
                    if (($result['success'] ?? 0) === 1) {
                        $result['audit_event_id'] = $auditEventId;
                    }
                }
                return $result;
            });
        }
    );
    echo json_encode($response);
}else if($request == 'add-individual'){
    $scopeResult = PayrollAdjustmentGuard::validateScope($_POST);
    $adjustmentResult = PayrollAdjustmentGuard::validateAdjustment($_POST, 'type_of_addition');
    $auditResult = PayrollAdjustmentGuard::validateAuditContext($_POST);
    foreach ([$scopeResult, $adjustmentResult, $auditResult] as $validation) {
        if (($validation['success'] ?? 0) !== 1) {
            additional_error($validation);
            exit();
        }
    }
    $scope = $scopeResult['scope'];
    $adjustment = $adjustmentResult['adjustment'];
    $audit = $auditResult['audit'];
    $model->employee_id = $adjustment['employee_id'];
    $model->amount = $adjustment['amount'];
    $model->type_of_addition = $adjustment['type_of_addition'];
    $model->client_name = $scope['client_name'];
    $model->cut_off = $scope['cut_off'];
    $model->pay_day = $scope['pay_day'];
    $model->start_date = $scope['start_date'];
    $model->end_date = $scope['end_date'];

    $response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
        (string)$model->client_name,
        (string)$model->pay_day,
        function () use ($model, $pdoConn, $scope, $adjustment, $audit): array {
            return additional_transaction($pdoConn, function () use ($model, $pdoConn, $scope, $adjustment, $audit): array {
                $eligible = PayrollAdjustmentGuard::eligibleEmployeeIds($pdoConn, $scope, [$adjustment['employee_id']]);
                if (!in_array((int)$adjustment['employee_id'], $eligible, true)) {
                    return ['success' => 0, 'code' => 'employee_not_in_dtr_population', 'error' => 'Select an active employee from this payroll period DTR population.'];
                }
                $existing = PayrollAdjustmentGuard::existingFingerprints(
                    $pdoConn,
                    'payroll_other_additional',
                    'type_of_addition',
                    $scope
                );
                $fingerprint = PayrollAdjustmentGuard::fingerprint(
                    (int)$adjustment['employee_id'],
                    (string)$adjustment['amount'],
                    (string)$adjustment['type_of_addition']
                );
                if (isset($existing[$fingerprint])) {
                    return ['success' => 0, 'code' => 'duplicate_adjustment', 'error' => 'This exact payroll addition already exists for the selected period.'];
                }
                $result = $model->individualAdditional();
                if (($result['success'] ?? 0) === 1) {
                    $auditEventId = PayrollAdjustmentGuard::writeAuditRows(
                        $pdoConn,
                        'ADDITION_CREATE',
                        $scope + [
                            'change_reason' => $audit['change_reason'],
                            'evidence_reference' => $audit['evidence_reference'],
                            'row_records' => [[
                                'adjustment_id' => $result['inserted_id'] ?? null,
                                'employee_id' => $adjustment['employee_id'],
                                'amount' => $adjustment['amount'],
                                'type' => $adjustment['type_of_addition'],
                            ]],
                        ]
                    );
                    $result = $model->spIndividualAdditional();
                    if (($result['success'] ?? 0) === 1) {
                        $result['audit_event_id'] = $auditEventId;
                    }
                }
                return $result;
            });
        }
    );
    echo json_encode($response);
}else if($request == 'get-client-location'){
    $model->client = trim((string)($_POST["client_selected"] ?? ''));
    echo json_encode($model->getClientLocation());
}else if($request == 'get-branch'){
    $model->client = trim((string)($_POST["client_selected"] ?? ''));
    echo json_encode($model->getBranch());
}else {
    additional_error(['success' => 0, 'code' => 'unknown_request', 'error' => 'Unknown payroll addition request.'], 400);
}

       
    


?>
