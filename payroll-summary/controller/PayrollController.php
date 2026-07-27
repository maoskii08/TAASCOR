<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1, 3]);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
ini_set('memory_limit', '-1');

require('../../config/db_connect.php');
require('../model/Payroll.php');

$model = new Payroll();
$model->db = $pdoConn;
$request = (string)($_POST['request'] ?? '');

if ($request === 'get-payroll-filters') {
    echo json_encode($model->getPayrollFilters(), JSON_UNESCAPED_SLASHES);
    exit;
}

if ($request === 'get-payroll-summary') {
    $response = $model->getPayrollSummary([
        'date_from' => (string)($_POST['date_from'] ?? ''),
        'date_to' => (string)($_POST['date_to'] ?? ''),
        'client' => (string)($_POST['client'] ?? ''),
        'cut_off' => (string)($_POST['cut_off'] ?? ''),
    ]);
    if (($response['success'] ?? 0) !== 1) {
        http_response_code(422);
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(400);
echo json_encode([
    'success' => 0,
    'error' => 'Unknown request.',
], JSON_UNESCAPED_SLASHES);
