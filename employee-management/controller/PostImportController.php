<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1, 2]);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

require('../../config/db_connect.php');
require('../model/Import.php');
require('../model/EmployeeImportStagingContext.php');

/**
 * @param array<string,mixed> $payload
 */
function employee_import_stage_respond(int $status, array $payload): never
{
    http_response_code($status);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit();
}

function employee_import_positive_int(mixed $value): int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value <= 0) {
        throw new InvalidArgumentException('A valid client scope is required for employee import.');
    }
    return (int)$value;
}

$rawBody = (string)file_get_contents('php://input');
if ($rawBody === '' || strlen($rawBody) > EmployeeImportStagingContext::MAX_REQUEST_BYTES) {
    employee_import_stage_respond(413, [
        'success' => 0,
        'error_code' => 'employee_import_request_too_large',
        'message' => 'Employee staging requests are limited to 2 MiB.',
    ]);
}

try {
    $rawData = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($rawData)) {
        throw new InvalidArgumentException('The employee import request must be a JSON object.');
    }

    $action = strtolower(trim((string)($rawData['action'] ?? '')));
    $clientId = employee_import_positive_int($rawData['client_id'] ?? null);
    $totalRows = employee_import_positive_int($rawData['total_rows'] ?? null);
    $fileSize = employee_import_positive_int($rawData['file_size'] ?? null);
    $fileName = trim((string)($rawData['file_name'] ?? ''));
    $actor = auth_user();

    auth_require_client_id($clientId);
    taascor_start_secure_session();

    $model = new Import();
    $model->db = $pdoConn;

    if ($action === 'init') {
        $client = $model->getClientById($clientId);
        if ($client === null) {
            throw new InvalidArgumentException('The selected employee import client does not exist.');
        }

        $importId = $model->generateId();
        $context = EmployeeImportStagingContext::create(
            $_SESSION,
            $importId,
            $actor,
            $clientId,
            (string)$client['client_name'],
            $fileName,
            $fileSize,
            $totalRows
        );

        employee_import_stage_respond(201, [
            'success' => 1,
            'message' => 'Employee staging context initialized.',
            'import_id' => $importId,
            'client_id' => $clientId,
            'client_name' => (string)$client['client_name'],
            'max_batch_rows' => EmployeeImportStagingContext::MAX_BATCH_ROWS,
            'max_total_rows' => EmployeeImportStagingContext::MAX_TOTAL_ROWS,
            'expires_at' => (int)$context['expires_at'],
        ]);
    }

    if ($action !== 'stage') {
        throw new InvalidArgumentException('Initialize a server-issued employee import context before staging rows.');
    }

    $importId = trim((string)($rawData['import_id'] ?? ''));
    $batch = filter_var($rawData['batch'] ?? null, FILTER_VALIDATE_INT);
    $rows = $rawData['data'] ?? null;
    $columnMap = $rawData['column_map'] ?? null;
    if (preg_match('/^\d{9}$/', $importId) !== 1
        || $batch === false
        || (int)$batch < 0
        || !is_array($rows)
        || !is_array($columnMap)) {
        throw new InvalidArgumentException('The employee staging batch is invalid.');
    }

    $context = EmployeeImportStagingContext::requireForBatch(
        $_SESSION,
        $importId,
        $actor,
        $clientId,
        (int)$batch,
        $totalRows,
        $fileSize,
        $fileName,
        $rows,
        $columnMap
    );
    auth_require_client_id((int)$context['client_id']);

    $model->import_id = $importId;
    $beforeCount = $model->countStagedRows();
    if ($beforeCount !== (int)$context['staged_rows']) {
        employee_import_stage_respond(409, [
            'success' => 0,
            'error_code' => 'employee_import_staging_count_mismatch',
            'message' => 'Staged employee rows no longer match the server-issued upload context. No batch was appended.',
            'import_id' => $importId,
            'staging_preserved' => true,
        ]);
    }

    $response = $model->add($rows, $columnMap);
    if (($response['success'] ?? 0) !== 1) {
        employee_import_stage_respond(422, [
            'success' => 0,
            'error_code' => 'employee_import_staging_failed',
            'message' => 'The employee batch could not be staged. Earlier committed batches were preserved.',
            'import_id' => $importId,
            'staging_preserved' => true,
        ]);
    }

    $afterCount = $model->countStagedRows();
    $expectedAfterCount = $beforeCount + count($rows);
    if ($afterCount !== $expectedAfterCount) {
        employee_import_stage_respond(409, [
            'success' => 0,
            'error_code' => 'employee_import_staging_count_mismatch',
            'message' => 'The committed employee staging count could not be reconciled. Finalization remains blocked.',
            'import_id' => $importId,
            'staging_preserved' => true,
        ]);
    }

    $updatedContext = EmployeeImportStagingContext::markBatchCommitted(
        $_SESSION,
        $importId,
        $actor,
        (int)$batch,
        count($rows)
    );

    employee_import_stage_respond(200, [
        'success' => 1,
        'message' => 'Employee batch staged.',
        'import_id' => $importId,
        'batch' => (int)$batch,
        'staged_rows' => (int)$updatedContext['staged_rows'],
        'total_rows' => (int)$updatedContext['total_rows'],
        'ready_for_validation' => (string)$updatedContext['status'] === 'staged',
    ]);
} catch (LengthException $error) {
    employee_import_stage_respond(413, [
        'success' => 0,
        'error_code' => 'employee_import_limit_exceeded',
        'message' => $error->getMessage(),
    ]);
} catch (InvalidArgumentException $error) {
    employee_import_stage_respond(400, [
        'success' => 0,
        'error_code' => 'invalid_employee_import_request',
        'message' => $error->getMessage(),
    ]);
} catch (RuntimeException $error) {
    employee_import_stage_respond(409, [
        'success' => 0,
        'error_code' => 'employee_import_context_conflict',
        'message' => $error->getMessage(),
        'staging_preserved' => true,
    ]);
} catch (Throwable $error) {
    error_log('Employee import staging failed: ' . $error->getMessage());
    employee_import_stage_respond(500, [
        'success' => 0,
        'error_code' => 'employee_import_staging_unavailable',
        'message' => 'Employee staging is unavailable. No new batch was accepted.',
    ]);
}
