<?php
require_once('../../includes/auth_guard.php');
auth_require_role([1]);
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

    default:
        echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
}
