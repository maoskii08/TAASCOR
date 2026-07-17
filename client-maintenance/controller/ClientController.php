<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/Client.php');

$model = new Client;
$model->db = $pdoConn;

// ── Client list ───────────────────────────────────────────────────────────
if ($_POST['request'] == 'get-client-list') {

    $getList = $model->getClientList();
    $response = ['data' => []];

    if (!isset($getList['error']) && count($getList['data']) > 0) {
        foreach ($getList['data'] as $row) {
            $id        = $row['client_id'];
            $isActive  = (int)($row['is_active'] ?? 1);
            $fdCode    = htmlspecialchars($row['fd_code'] ?? '');
            $empCount  = (int)($row['active_count'] ?? 0);

            $statusBtn = $isActive
                ? "<button class='btn btn-sm btn-success toggleStatusBtn' value='$id' data-active='1' title='Click to deactivate'><i class='bx bx-check'></i></button>"
                : "<button class='btn btn-sm btn-secondary toggleStatusBtn' value='$id' data-active='0' title='Click to activate'><i class='bx bx-x'></i></button>";

            $action = "<button class='btn btn-sm btn-primary updateBtn me-1' value='$id'
                            data-name='" . htmlspecialchars($row['client_name']) . "'
                            data-fd='" . $fdCode . "'
                            data-active='$isActive'>
                            <i class='bx bx-pencil'></i></button>
                       <button class='btn btn-sm btn-danger deleteBtn' value='$id'><i class='bx bx-trash-alt'></i></button>";

            $statusBadge = $isActive
                ? "<span class='badge bg-success'>Active</span>"
                : "<span class='badge bg-secondary'>Inactive</span>";

            $fdDisplay = $fdCode
                ? "<span class='badge bg-info text-dark'>$fdCode</span>"
                : "<span class='text-muted small'>—</span>";

            $response['data'][] = [
                $action,
                $statusBadge,
                $row['client_name'],
                $fdDisplay,
                $empCount,
            ];
        }
    } elseif (isset($getList['error'])) {
        $response['error'] = $getList['error'];
    }

    echo json_encode($response);

// ── Alignment data ────────────────────────────────────────────────────────
} elseif ($_POST['request'] == 'get-alignment') {

    echo json_encode($model->getAlignment());

// ── Update client name + fd_code ──────────────────────────────────────────
} elseif ($_POST['request'] == 'update-client') {

    $model->id          = $_POST['id'];
    $model->client_name = $_POST['client_name'];
    $model->fd_code     = $_POST['fd_code'] !== '' ? $_POST['fd_code'] : null;
    echo json_encode($model->updateClient());

// ── Toggle active / inactive ──────────────────────────────────────────────
} elseif ($_POST['request'] == 'update-status') {

    $model->id        = $_POST['id'];
    $model->is_active = (int)$_POST['is_active'];
    echo json_encode($model->updateStatus());

// ── Add client ────────────────────────────────────────────────────────────
} elseif ($_POST['request'] == 'add-client') {

    $model->client_name = $_POST['client_name'];
    echo json_encode($model->addClient());

// ── Delete client ─────────────────────────────────────────────────────────
} elseif ($_POST['request'] == 'delete-client') {

    $model->id = $_POST['id'];
    echo json_encode($model->deleteClient());

} else {
    echo json_encode(['error' => 'Unknown request']);
}
