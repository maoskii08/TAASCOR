<?php
require_once('../../includes/auth_guard.php');
auth_require_role([1]);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('content-type: application/json');

require('../../config/db_connect.php');
require('../model/Accounting.php');

$model     = new Accounting;
$model->db = $pdoConn;

switch ($_POST['request'] ?? '') {

    case 'get-remittance-summary':
        $model->client  = $_POST['client']  ?? null;
        $model->pay_day = $_POST['pay_day'] ?? null;
        $model->cut_off = $_POST['cut_off'] ?? null;
        echo json_encode($model->getRemittanceSummary());
        break;

    case 'get-annual-summary':
        $model->client = $_POST['client'] ?? null;
        $model->year   = $_POST['year']   ?? null;
        echo json_encode($model->getAnnualSummary());
        break;

    case 'get-employee-annual':
        $model->client = $_POST['client'] ?? null;
        $model->year   = $_POST['year']   ?? null;
        echo json_encode($model->getEmployeeAnnual());
        break;

    case 'get-client-filter':
        echo json_encode($model->getClientFilter());
        break;

    case 'get-year-filter':
        echo json_encode($model->getYearFilter());
        break;

    case 'get-pay-day-filter':
        $model->client = $_POST['client'] ?? null;
        echo json_encode($model->getPayDayFilter());
        break;

    default:
        echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
}
