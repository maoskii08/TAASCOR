<?php

declare(strict_types=1);

function employee_import_transfer_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

final class EmployeeImportNoWriteDatabase
{
    public int $calls = 0;

    public function __call(string $name, array $arguments): never
    {
        $this->calls++;
        throw new RuntimeException("Unexpected database call: {$name}");
    }
}

$root = dirname(__DIR__, 2);
$controllerSource = (string)file_get_contents($root . '/employee-management/controller/ImportController.php');
$modelSource = (string)file_get_contents($root . '/employee-management/model/Import.php');
$browserSource = (string)file_get_contents($root . '/employee-management/js/app-excel-import-v04.js');

employee_import_transfer_check(
    preg_match('/\bCALL\s+sp_transfer_employee_data\b/i', $modelSource) !== 1
        && preg_match('/\bCALL\s+sp_transfer_employee_data\b/i', $controllerSource) !== 1,
    'the internally committing legacy transfer routine is not callable from the application'
);
employee_import_transfer_check(
    !str_contains($controllerSource, 'spTransferEmployeeData(')
        && str_contains($controllerSource, '$model->blockedEmployeeTransferResponse()'),
    'the finalize controller can only return the fail-closed v2-required response'
);
employee_import_transfer_check(
    str_contains($controllerSource, "if ((\$validate['success'] ?? 0) !== 1)")
        && str_contains($controllerSource, "'employee_import_validation_failed'")
        && str_contains($controllerSource, "'staging_preserved' => true"),
    'validation failure stops before finalization and reports preserved staging'
);
employee_import_transfer_check(
    !str_contains($controllerSource, 'deleteTemp(')
        && !str_contains($controllerSource, 'insertLog(')
        && !str_contains($controllerSource, 'new Logs'),
    'failed or blocked finalization neither deletes staging nor records a false success log'
);
employee_import_transfer_check(
    str_contains($controllerSource, 'employee_import_finalize_respond(409')
        && str_contains($modelSource, "'employee_transfer_v2_required'")
        && str_contains($modelSource, "'route_label'"),
    'the API returns a clear conflict and routes the user back to Employee Management'
);
employee_import_transfer_check(
    str_contains($browserSource, "response.error_code === 'employee_transfer_v2_required'")
        && str_contains($browserSource, 'showEmployeeTransferBlocker(response)')
        && str_contains($browserSource, 'The staged upload was preserved'),
    'the upload UI presents the blocker and staging-preservation status'
);

require_once $root . '/employee-management/model/Import.php';

$database = new EmployeeImportNoWriteDatabase();
$model = new Import();
$model->db = $database;
$model->import_id = '012345678';
$response = $model->blockedEmployeeTransferResponse();

employee_import_transfer_check(
    ($response['success'] ?? null) === 0
        && ($response['error_code'] ?? null) === 'employee_transfer_v2_required'
        && ($response['staging_preserved'] ?? null) === true
        && ($response['route'] ?? null) === 'employee-management'
        && ($response['import_id'] ?? null) === '012345678',
    'the fail-closed response preserves the exact import reference and recovery route'
);
employee_import_transfer_check(
    $database->calls === 0,
    'building the blocked response performs no database write or read'
);

echo "RESULT: Employee import transfer containment passed.\n";
