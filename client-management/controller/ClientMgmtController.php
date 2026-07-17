<?php
require_once('../../includes/auth_guard.php');
auth_require_role([1, 2, 3]);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('content-type: application/json');

require('../../config/db_connect.php');
require('../model/ClientMgmt.php');

$model     = new ClientMgmt;
$model->db = $pdoConn;

switch ($_POST['request'] ?? '') {

    case 'get-client-overview':
        echo json_encode($model->getClientOverview());
        break;

    case 'get-client-employees':
        $model->client_id = (int)($_POST['client_id'] ?? 0);
        echo json_encode($model->getClientEmployees());
        break;

    case 'get-client-payroll-history':
        $model->client_id = (int)($_POST['client_id'] ?? 0);
        echo json_encode($model->getClientPayrollHistory());
        break;

    case 'update-client-profile':
        // Admin only for edits
        if (auth_level() !== 1) {
            echo json_encode(['success' => 0, 'error' => 'Admin access required.']);
            break;
        }
        $model->client_id      = (int)($_POST['client_id']      ?? 0);
        $model->address        = $_POST['address']              ?? '';
        $model->contact_person = $_POST['contact_person']       ?? '';
        $model->contact_number = $_POST['contact_number']       ?? '';
        $model->email          = $_POST['email']                ?? '';
        $model->industry       = $_POST['industry']             ?? '';
        $model->notes          = $_POST['notes']                ?? '';
        echo json_encode($model->updateClientProfile());
        break;

    default:
        echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
}
