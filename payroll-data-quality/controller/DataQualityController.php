<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1, 3]);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');
ini_set('memory_limit', '256M');

require('../../config/db_connect.php');
require('../model/DataQuality.php');

$allowAllClients = auth_has_global_client_access();
$allowedClientIds = auth_client_ids();
if (!$allowAllClients && count($allowedClientIds) === 0) {
    http_response_code(403);
    echo json_encode([
        'success' => 0,
        'error' => 'No payroll client is assigned to this account.',
    ]);
    exit();
}

$model = new DataQuality();
$model->db = $pdoConn;
$model->allow_all_clients = $allowAllClients;
$model->allowed_client_ids = $allowedClientIds;

$request = $_GET['request'] ?? $_POST['request'] ?? 'summary';
if ($request === 'summary') {
    echo json_encode($model->getSummary());
    exit();
}

if ($request === 'details') {
    $key = trim((string)($_GET['key'] ?? $_POST['key'] ?? ''));
    $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 100);
    try {
        echo json_encode($model->getDetails($key, $limit));
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode([
            'success' => 0,
            'error' => $error->getMessage(),
        ]);
    }
    exit();
}

http_response_code(400);
echo json_encode([
    'success' => 0,
    'error' => 'Unknown Payroll Data Quality request.',
]);
