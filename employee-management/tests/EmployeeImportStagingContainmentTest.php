<?php

declare(strict_types=1);

function employee_import_staging_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function employee_import_staging_throws(callable $callback, string $expectedClass, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        if (!$error instanceof $expectedClass) {
            throw new RuntimeException(
                $message . ' (received ' . get_class($error) . ': ' . $error->getMessage() . ')'
            );
        }
        employee_import_staging_check(true, $message);
        return;
    }
    throw new RuntimeException($message);
}

$root = dirname(__DIR__, 2);
$postController = (string)file_get_contents(
    $root . '/employee-management/controller/PostImportController.php'
);
$finalizeController = (string)file_get_contents(
    $root . '/employee-management/controller/ImportController.php'
);
$browserSource = (string)file_get_contents(
    $root . '/employee-management/js/app-excel-import-v04.js'
);
$pageSource = (string)file_get_contents(
    $root . '/employee-management/index.php'
);
$employeeModel = (string)file_get_contents(
    $root . '/employee-management/model/Employee.php'
);

employee_import_staging_check(
    str_contains($postController, 'auth_require_role([1, 2])')
        && str_contains($finalizeController, 'auth_require_role([1, 2])')
        && !str_contains($postController, 'auth_require_role([1,2,3,4])'),
    'only Admin and HR may stage or finalize employee imports'
);
employee_import_staging_check(
    substr_count($postController, 'auth_require_client_id(') >= 2
        && str_contains($finalizeController, 'auth_require_client_id('),
    'initialization, staging, and finalization enforce authenticated client scope'
);
employee_import_staging_check(
    str_contains($postController, "\$action === 'init'")
        && str_contains($postController, '$model->generateId()')
        && !str_contains($postController, "\$rawData['importID']"),
    'the server issues import references and never accepts the legacy caller-chosen importID'
);
employee_import_staging_check(
    str_contains($postController, 'MAX_REQUEST_BYTES')
        && str_contains($browserSource, 'maxFileBytes')
        && str_contains($browserSource, 'maxTotalRows')
        && str_contains($pageSource, 'Maximum 1,000 rows and 5 MiB'),
    'request, workbook-byte, batch-row, and total-row limits are visible and enforced'
);
employee_import_staging_check(
    str_contains($browserSource, 'action: "init"')
        && str_contains($browserSource, 'action: "stage"')
        && str_contains($browserSource, 'Import reference: ')
        && str_contains($browserSource, 'response.import_id'),
    'the browser initializes server context before staging and displays the exact blocked reference'
);
employee_import_staging_check(
    str_contains($pageSource, 'id="import-client"')
        && str_contains($employeeModel, 'client_id, client_name'),
    'the upload requires a canonical client selection backed by client ID'
);

require_once $root . '/employee-management/model/EmployeeImportStagingContext.php';

$session = [];
$context = EmployeeImportStagingContext::create(
    $session,
    '123456789',
    'admin@example.test',
    22,
    'Fujifilm',
    'employees.xlsx',
    4096,
    2,
    1000
);
employee_import_staging_check(
    $context['import_id'] === '123456789'
        && $context['client_id'] === 22
        && $context['next_batch'] === 0
        && $context['staged_rows'] === 0,
    'a server context binds import, actor, client, file metadata, and batch sequence'
);

$requiredColumns = [
    'employee ident', 'old employee ident', 'payroll employee id', 'full name',
    'last name', 'first name', 'middle name', 'hire date', 'separation date',
    'present address', 'permanent address', 'contact number', 'email address',
    'birthday', 'birth place', 'gender', 'civil status', 'nationality',
    'emergency person', 'emergency contact number', 'client date', 'position',
    'client', 'branch', 'client location', 'department', 'tin', 'sss',
    'philhealth', 'pag-ibig', 'bank account number', 'daily salary', 'bank name',
    'insurance', 'annual leaves', 'employee type', 'pay type',
];
$columnMap = array_combine($requiredColumns, range(0, count($requiredColumns) - 1));
$firstRow = array_fill(0, count($requiredColumns), '');
$firstRow[0] = '10001';
$firstRow[22] = ' FUJIFILM ';
$firstRows = [$firstRow];
employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireForBatch(
        $session,
        '123456789',
        'admin@example.test',
        22,
        0,
        2,
        4096,
        'employees.xlsx',
        $firstRows,
        ['client' => 22],
        1001
    ),
    InvalidArgumentException::class,
    'an incomplete or caller-remapped employee schema fails before staging'
);
$first = EmployeeImportStagingContext::requireForBatch(
    $session,
    '123456789',
    'admin@example.test',
    22,
    0,
    2,
    4096,
    'employees.xlsx',
    $firstRows,
    $columnMap,
    1001
);
employee_import_staging_check(
    $first['staged_rows'] === 0,
    'the first correctly scoped batch is accepted without mutating context early'
);

EmployeeImportStagingContext::markBatchCommitted(
    $session,
    '123456789',
    'admin@example.test',
    0,
    1,
    1001
);

employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireForBatch(
        $session,
        '123456789',
        'admin@example.test',
        22,
        0,
        2,
        4096,
        'employees.xlsx',
        $firstRows,
        $columnMap,
        1002
    ),
    RuntimeException::class,
    'a replayed or duplicated batch fails closed'
);
employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireForBatch(
        $session,
        '123456789',
        'other-user@example.test',
        22,
        1,
        2,
        4096,
        'employees.xlsx',
        [array_replace($firstRow, [0 => '10002', 22 => 'Fujifilm'])],
        $columnMap,
        1002
    ),
    RuntimeException::class,
    'another authenticated user cannot append to the import context'
);
employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireForBatch(
        $session,
        '123456789',
        'admin@example.test',
        23,
        1,
        2,
        4096,
        'employees.xlsx',
        [array_replace($firstRow, [0 => '10002', 22 => 'Fujifilm'])],
        $columnMap,
        1002
    ),
    RuntimeException::class,
    'a client scope change cannot append to the import context'
);
employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireForBatch(
        $session,
        '123456789',
        'admin@example.test',
        22,
        1,
        2,
        4096,
        'employees.xlsx',
        [array_replace($firstRow, [0 => '10002', 22 => 'Different Client'])],
        $columnMap,
        1002
    ),
    RuntimeException::class,
    'a workbook row outside the selected client fails before a database write'
);

$secondRows = [array_replace($firstRow, [0 => '10002', 22 => 'Fujifilm'])];
EmployeeImportStagingContext::requireForBatch(
    $session,
    '123456789',
    'admin@example.test',
    22,
    1,
    2,
    4096,
    'employees.xlsx',
    $secondRows,
    $columnMap,
    1002
);
$complete = EmployeeImportStagingContext::markBatchCommitted(
    $session,
    '123456789',
    'admin@example.test',
    1,
    1,
    1002
);
employee_import_staging_check(
    $complete['status'] === 'staged'
        && EmployeeImportStagingContext::requireComplete(
            $session,
            '123456789',
            'admin@example.test',
            1003
        )['staged_rows'] === 2,
    'finalization is reachable only after the exact declared row count is staged'
);

EmployeeImportStagingContext::markFinalizationBlocked(
    $session,
    '123456789',
    'admin@example.test',
    1003
);
employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireComplete(
        $session,
        '123456789',
        'admin@example.test',
        1004
    ),
    RuntimeException::class,
    'the quarantined finalization context cannot be replayed'
);

employee_import_staging_throws(
    static function (): void {
        $oversized = [];
        EmployeeImportStagingContext::create(
            $oversized,
            '987654321',
            'hr@example.test',
            22,
            'Fujifilm',
            'too-many.xlsx',
            4096,
            EmployeeImportStagingContext::MAX_TOTAL_ROWS + 1,
            2000
        );
    },
    LengthException::class,
    'workbooks above the total row limit fail before staging'
);

employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireForBatch(
        $session,
        '123456789',
        'admin@example.test',
        22,
        2,
        2,
        4096,
        'employees.xlsx',
        array_fill(
            0,
            EmployeeImportStagingContext::MAX_BATCH_ROWS + 1,
            array_replace($firstRow, [0 => '1', 22 => 'Fujifilm'])
        ),
        $columnMap,
        1004
    ),
    RuntimeException::class,
    'a blocked or oversized follow-up cannot append to a finalized context'
);

$expiredSession = [];
EmployeeImportStagingContext::create(
    $expiredSession,
    '444444444',
    'hr@example.test',
    22,
    'Fujifilm',
    'expired.xlsx',
    4096,
    1,
    1
);
employee_import_staging_throws(
    static fn() => EmployeeImportStagingContext::requireForBatch(
        $expiredSession,
        '444444444',
        'hr@example.test',
        22,
        0,
        1,
        4096,
        'expired.xlsx',
        [array_replace($firstRow, [0 => '1', 22 => 'Fujifilm'])],
        $columnMap,
        1 + EmployeeImportStagingContext::CONTEXT_TTL_SECONDS + 1
    ),
    RuntimeException::class,
    'expired server-issued contexts fail closed'
);

echo "RESULT: Employee import staging containment passed.\n";
