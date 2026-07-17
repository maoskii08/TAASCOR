<?php
require_once('../../includes/auth_guard.php');
auth_require_role([1]);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('content-type: application/json');

require('../../config/db_connect.php');
require('../model/Billing.php');

$model     = new Billing;
$model->db = $pdoConn;

$request = $_POST['request'] ?? '';

switch ($request) {

    case 'get-billing-summary':
        $model->client  = $_POST['client']  ?? null;
        $model->pay_day = $_POST['pay_day'] ?? null;
        $model->cut_off = $_POST['cut_off'] ?? null;
        echo json_encode($model->getBillingSummary());
        break;

    case 'get-billing-detail':
        $model->client  = $_POST['client']  ?? '';
        $model->pay_day = $_POST['pay_day'] ?? '';
        $model->cut_off = $_POST['cut_off'] ?? '';
        echo json_encode($model->getBillingDetail());
        break;

    case 'get-client-filter':
        echo json_encode($model->getClientFilter());
        break;

    case 'get-pay-day-filter':
        $model->client = $_POST['client'] ?? null;
        echo json_encode($model->getPayDayFilter());
        break;

    default:
        echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
}
