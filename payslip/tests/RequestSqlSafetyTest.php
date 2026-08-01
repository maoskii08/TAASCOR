<?php

declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$model = (string)file_get_contents(__DIR__ . '/../model/Payslip.php');
$pdf = (string)file_get_contents(__DIR__ . '/../payslip2.php');
$legacy = (string)file_get_contents(__DIR__ . '/../payslip.php');

foreach ([
    "pay_type = '{\$this->pay_type}'",
    "bank_name = '{\$this->bank_name}'",
    'client_location_id = {$this->client_location}',
    'branch_id = {$this->branch}',
] as $unsafe) {
    check(!str_contains($model, $unsafe), "removes interpolated filter {$unsafe}");
}

foreach (['s.pay_type = :pay_type', 's.bank_name = :bank_name', 'b.client_location_id = :client_location_id', 'b.branch_id = :branch_id'] as $bound) {
    check(str_contains($model, $bound), "binds payroll-summary filter {$bound}");
}

check(str_contains($pdf, 's.pay_type = :pay_type'), 'PDF pay-type filter is parameterized');
check(str_contains($pdf, 's.bank_name = :bank_name'), 'PDF bank filter is parameterized');
check(str_contains($pdf, 'c.client_location_id = :client_location_id'), 'PDF employee location uses the correct main-query alias');
check(!preg_match('/\bSELECT\b/i', $legacy), 'legacy report route no longer executes SQL');
check(str_contains($legacy, "require __DIR__ . '/payslip2.php'"), 'legacy report route delegates directly to the maintained endpoint');

echo "RESULT: Payslip request SQL safety passed.\n";
