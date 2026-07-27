<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1, 2]);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

require '../../config/db_connect.php';
require('../model/Import.php');
require('../model/EmployeeImportStagingContext.php');

/**
 * @param array<string,mixed> $payload
 */
function employee_import_finalize_respond(int $status, array $payload): never
{
    http_response_code($status);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit();
}

$importId = trim((string)($_POST['import_id'] ?? ''));
if (preg_match('/^\d{9}$/', $importId) !== 1) {
    employee_import_finalize_respond(400, [
        'success' => 0,
        'error_code' => 'invalid_employee_import_id',
        'message' => 'The employee import reference is invalid. No transfer was attempted.',
        'route' => 'employee-management',
        'route_label' => 'Return to Employee Management',
        'staging_preserved' => true,
    ]);
}

try {
    taascor_start_secure_session();
    $context = EmployeeImportStagingContext::requireComplete(
        $_SESSION,
        $importId,
        auth_user()
    );
    auth_require_client_id((int)$context['client_id']);

    $model = new Import();
    $model->db = $pdoConn;
    $model->import_id = $importId;

    if ($model->countStagedRows() !== (int)$context['staged_rows']) {
        employee_import_finalize_respond(409, [
            'success' => 0,
            'error_code' => 'employee_import_staging_count_mismatch',
            'message' => 'The staged employee row count no longer matches its server-issued context. No transfer was attempted.',
            'route' => 'employee-management',
            'route_label' => 'Return to Employee Management',
            'staging_preserved' => true,
            'import_id' => $importId,
        ]);
    }

    $validate = $model->validate();
    if (($validate['success'] ?? 0) !== 1) {
        $response = $validate;
        $response['error_code'] = 'employee_import_validation_failed';
        $response['message'] = 'Employee import validation failed. No transfer was attempted, and the staged upload was preserved for review.';
        $response['route'] = 'employee-management';
        $response['route_label'] = 'Return to Employee Management';
        $response['staging_preserved'] = true;
        $response['import_id'] = $importId;
        employee_import_finalize_respond(422, $response);
    }

    EmployeeImportStagingContext::markFinalizationBlocked(
        $_SESSION,
        $importId,
        auth_user()
    );
    $response = $model->blockedEmployeeTransferResponse();
    $response['client_id'] = (int)$context['client_id'];
    $response['client_name'] = (string)$context['client_name'];
    employee_import_finalize_respond(409, $response);
} catch (RuntimeException $error) {
    employee_import_finalize_respond(409, [
        'success' => 0,
        'error_code' => 'employee_import_context_conflict',
        'message' => $error->getMessage() . ' No transfer was attempted.',
        'route' => 'employee-management',
        'route_label' => 'Return to Employee Management',
        'staging_preserved' => true,
        'import_id' => $importId,
    ]);
} catch (Throwable $error) {
    error_log('Employee import finalization containment failed: ' . $error->getMessage());
    employee_import_finalize_respond(500, [
        'success' => 0,
        'error_code' => 'employee_import_finalization_unavailable',
        'message' => 'Employee import finalization is unavailable. No transfer was attempted.',
        'route' => 'employee-management',
        'route_label' => 'Return to Employee Management',
        'staging_preserved' => true,
        'import_id' => $importId,
    ]);
}
