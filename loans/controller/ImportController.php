<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1, 3]);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');
ini_set('memory_limit', '-1');
require('../../config/db_connect.php');
require('../model/Import.php');
require_once('../../dtr-upload/model/PayrollLockGuard.php');

$model = new Import();
$model->db = $pdoConn;
$model->client_name = trim((string)($_POST['client_name'] ?? ''));
$model->cut_off = trim((string)($_POST['cut_off'] ?? ''));
$model->pay_day = trim((string)($_POST['pay_day'] ?? ''));

$response = (new PayrollLockGuard($pdoConn))->runUnlockedMutation(
    (string)$model->client_name,
    (string)$model->pay_day,
    static function () use ($model): array {
        return $model->spCalculateDTR();
    }
);

session_write_close();
echo json_encode($response, JSON_PRETTY_PRINT);
