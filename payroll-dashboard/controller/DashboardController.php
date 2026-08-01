<?php

declare(strict_types=1);

require_once('../../includes/auth_guard.php');
auth_require_role([1, 3]);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');

require('../../config/db_connect.php');
require('../model/Dashboard.php');

$model = new Dashboard();
$model->db = $pdoConn;
$request = (string)($_POST['request'] ?? '');

if ($request === 'get-dashboard-filters') {
    echo json_encode($model->getClientFilters(), JSON_UNESCAPED_SLASHES);
    exit;
}

if ($request === 'get-pay-date-filters') {
    $clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
    if ($clientId === false || $clientId === null || (int)$clientId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => 0, 'error' => 'Select a valid payroll client.']);
        exit;
    }
    auth_require_client_id((int)$clientId);
    $response = $model->getPayDateFilters((int)$clientId);
    if (($response['success'] ?? 0) !== 1) {
        http_response_code(422);
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($request === 'get-dashboard-snapshot') {
    $clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
    $payDate = trim((string)($_POST['pay_date'] ?? ''));
    if ($clientId === false || $clientId === null || (int)$clientId <= 0 || $payDate === '') {
        http_response_code(422);
        echo json_encode(['success' => 0, 'error' => 'Select a valid payroll client and pay date.']);
        exit;
    }
    auth_require_client_id((int)$clientId);
    $response = $model->getSnapshot((int)$clientId, $payDate);
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
