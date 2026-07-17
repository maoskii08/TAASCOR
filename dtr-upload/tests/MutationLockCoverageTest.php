<?php

declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$controller = (string)file_get_contents(__DIR__ . '/../controller/DTRController.php');
$import = (string)file_get_contents(__DIR__ . '/../controller/ImportController.php');
$postImport = (string)file_get_contents(__DIR__ . '/../controller/PostImportController.php');
$model = (string)file_get_contents(__DIR__ . '/../model/DTR.php');

foreach (['update-dtr', 'remove-govt-benefits', 'delete-employee-dtr', 'delete-dtr-upload'] as $route) {
    $routePosition = strpos($controller, "'{$route}'");
    check($routePosition !== false, "finds mutation route {$route}");
    $routeBlock = substr($controller, $routePosition, 2600);
    check(
        str_contains($routeBlock, 'run_unlocked_payroll_mutation('),
        "{$route} executes inside the centralized payroll mutation lease"
    );
}

check(str_contains($import, 'new PayrollLockGuard($pdoConn)'), 'final import/calculation route checks payroll lock');
check(str_contains($postImport, 'new PayrollLockGuard($pdoConn)'), 'batched import write route checks payroll lock');
check(str_contains($postImport, 'validatedPayrollScope()'), 'batched import validates exactly one immutable payroll scope');
foreach ([
    '../../other-additional/controller/AdditionalController.php',
    '../../other-additional/controller/ImportController.php',
    '../../other-additional/controller/PostImportController.php',
    '../../other-deduction/controller/DeductionController.php',
    '../../other-deduction/controller/ImportController.php',
    '../../other-deduction/controller/PostImportController.php',
    '../../loans/controller/ImportController.php',
    '../../loans/controller/PostImportController.php',
] as $guardedController) {
    $contents = (string)file_get_contents(__DIR__ . '/' . $guardedController);
    check(str_contains($contents, 'runUnlockedMutation('), basename($guardedController) . ' uses the payroll mutation lease');
}
$loanImportModel = (string)file_get_contents(__DIR__ . '/../../loans/model/Import.php');
check(!str_contains($loanImportModel, "'{\$this->client_name}'"), 'loan recalculation does not interpolate the client into SQL');
check(str_contains($loanImportModel, ':client_name'), 'loan recalculation binds the client scope');
check(
    substr_count($model, 'AND client_name = :client') >= 9,
    'employee-level updates and deletes are constrained to the guarded client scope'
);
$deleteStart = strpos($model, 'public function deleteDTRUpload');
$deleteEnd = strpos($model, 'public function isLocked', $deleteStart ?: 0);
$deleteMethod = substr($model, (int)$deleteStart, (int)$deleteEnd - (int)$deleteStart);
check(!str_contains($deleteMethod, 'client_location_id = {$this->client_location}'), 'bulk delete does not interpolate location filter');
check(!str_contains($deleteMethod, 'branch_id = {$this->branch}'), 'bulk delete does not interpolate branch filter');
check(str_contains($deleteMethod, 'b.client_location_id = :client_location_id'), 'bulk delete binds location filter');
check(str_contains($deleteMethod, 'b.branch_id = :branch_id'), 'bulk delete binds branch filter');
check(!str_contains($model, '{$this->'), 'DTR model no longer interpolates request state into SQL');
check(str_contains($model, 'c.client_name = :join_client'), 'DTR list joins use bound client scope');

echo "RESULT: DTR mutation lock coverage passed.\n";
