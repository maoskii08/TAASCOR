<?php

declare(strict_types=1);

require __DIR__ . '/../model/Import.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$import = new Import();
$method = new ReflectionMethod(Import::class, 'employeeColumnIndex');

check($method->invoke($import, ['employee id' => 3]) === 3, 'accepts the browser mapping label');
check($method->invoke($import, ['Employee_ID' => 5]) === 5, 'accepts underscore and case variants');
check($method->invoke($import, [' employee-id ' => 7]) === 7, 'accepts hyphen and whitespace variants');
check($method->invoke($import, ['worked days' => 2]) === -1, 'rejects a mapping without employee ID');

$referenceDate = new ReflectionMethod(Import::class, 'identityReferenceDate');
$import->pay_day = '2026-07-13';
check($referenceDate->invoke($import) === '2026-07-13', 'uses the payroll date for effective identity mappings');
$import->payrollDetails = [['Sample Client', '16-30', '2026-07-14']];
check($referenceDate->invoke($import) === '2026-07-14', 'uses upload payroll metadata when present');

echo "RESULT: Employee column mapping normalization passed.\n";
