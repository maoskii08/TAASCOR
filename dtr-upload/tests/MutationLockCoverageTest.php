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
    $nextRoute = strpos($controller, '}else if(', $routePosition + strlen($route) + 2);
    $routeBlock = $nextRoute === false
        ? substr($controller, $routePosition)
        : substr($controller, $routePosition, $nextRoute - $routePosition);
    check(
        str_contains($routeBlock, 'run_unlocked_payroll_mutation('),
        "{$route} executes inside the centralized payroll mutation lease"
    );
}

foreach ([$import, $postImport] as $legacyWorkbookController) {
    check(
        str_contains($legacyWorkbookController, 'legacy_dtr_workbook_import_quarantined')
            && str_contains($legacyWorkbookController, 'http_response_code(410)')
            && !str_contains($legacyWorkbookController, 'db_connect.php')
            && !str_contains($legacyWorkbookController, 'runUnlockedMutation('),
        'legacy DTR workbook route is quarantined before loading mutation dependencies'
    );
}
foreach ([
    '../../other-additional/controller/AdditionalController.php',
    '../../other-additional/controller/PostImportController.php',
    '../../other-deduction/controller/DeductionController.php',
    '../../other-deduction/controller/PostImportController.php',
] as $guardedController) {
    $contents = (string)file_get_contents(__DIR__ . '/' . $guardedController);
    check(str_contains($contents, 'runUnlockedMutation('), basename($guardedController) . ' uses the payroll mutation lease');
}
foreach ([
    '../../loans/controller/ImportController.php',
    '../../loans/controller/PostImportController.php',
] as $quarantinedLoanController) {
    $contents = (string)file_get_contents(__DIR__ . '/' . $quarantinedLoanController);
    check(
        str_contains($contents, 'legacy_loans_dtr_import_quarantined')
            && str_contains($contents, 'http_response_code(410)')
            && !str_contains($contents, 'db_connect.php')
            && !str_contains($contents, 'runUnlockedMutation('),
        basename($quarantinedLoanController) . ' permanently rejects the misleading DTR importer'
    );
}
foreach ([
    '../../other-additional/controller/ImportController.php',
    '../../other-deduction/controller/ImportController.php',
] as $disabledFinalizeController) {
    $contents = (string)file_get_contents(__DIR__ . '/' . $disabledFinalizeController);
    check(
        str_contains($contents, 'atomic_workbook_required')
            && str_contains($contents, 'http_response_code(409)')
            && !str_contains($contents, 'runUnlockedMutation(')
            && !str_contains($contents, 'beginTransaction('),
        basename(dirname($disabledFinalizeController)) . ' legacy batch-finalize route rejects before mutation'
    );
}
$loanImportModel = (string)file_get_contents(__DIR__ . '/../../loans/model/Import.php');
check(!str_contains($loanImportModel, "'{\$this->client_name}'"), 'loan recalculation does not interpolate the client into SQL');
check(
    str_contains($loanImportModel, 'legacy_loans_dtr_import_quarantined')
        && !str_contains($loanImportModel, 'CALL '),
    'retired Loans import model contains no payroll calculator path'
);
$boundClientScopes = preg_match_all('/\bclient_name\s*=\s*:[a-zA-Z_][a-zA-Z0-9_]*/', $model);
check(
    $boundClientScopes !== false && $boundClientScopes >= 15,
    'employee-level updates, deletes, and scoped joins use bound client parameters'
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
