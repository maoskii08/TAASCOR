<?php
require_once('../../includes/auth_guard.php');
auth_require_role([1, 2, 3]);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('content-type: application/json');

require('../../config/db_connect.php');
require('../model/AuditLog.php');

$model     = new AuditLog;
$model->db = $pdoConn;

switch ($_POST['request'] ?? '') {

    case 'get-logs':
        $model->username  = $_POST['username']  ?? '';
        $model->action    = $_POST['action']    ?? '';
        $model->date_from = $_POST['date_from'] ?? '';
        $model->date_to   = $_POST['date_to']   ?? '';
        echo json_encode($model->getLogs());
        break;

    case 'get-login-summary':
        echo json_encode($model->getLoginSummary());
        break;

    case 'get-action-types':
        echo json_encode($model->getActionTypes());
        break;

    case 'get-payroll-adjustment-audit-events':
        $model->event_uid       = $_POST['event_uid'] ?? '';
        $model->client_name     = $_POST['client_name'] ?? '';
        $model->pay_day         = $_POST['pay_day'] ?? '';
        $model->adjustment_kind = $_POST['adjustment_kind'] ?? '';
        $model->operation       = $_POST['operation'] ?? '';
        echo json_encode($model->getPayrollAdjustmentEvents());
        break;

    case 'get-payroll-adjustment-audit-event':
        echo json_encode($model->getPayrollAdjustmentEvent($_POST['event_uid'] ?? ''));
        break;

    case 'get-dtr-mutation-audit-events':
        $model->event_uid   = $_POST['event_uid'] ?? '';
        $model->client_name = $_POST['client_name'] ?? '';
        $model->pay_day     = $_POST['pay_day'] ?? '';
        $model->operation   = $_POST['operation'] ?? '';
        $model->employee_id = $_POST['employee_id'] ?? '';
        $model->scope_kind  = $_POST['scope_kind'] ?? '';
        $model->page        = $_POST['page'] ?? 1;
        $model->page_size   = $_POST['page_size'] ?? 25;
        echo json_encode($model->getDtrMutationEvents());
        break;

    case 'get-dtr-mutation-audit-event':
        echo json_encode($model->getDtrMutationEvent($_POST['event_uid'] ?? ''));
        break;

    default:
        echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
}
