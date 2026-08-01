<?php

declare(strict_types=1);

require __DIR__ . '/../report-filter-rules.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

check(payslip_positive_int_filter([], 'ei') === null, 'absent filter remains optional');
check(payslip_positive_int_filter(['ei' => '42'], 'ei') === 42, 'positive integer filter is accepted');

foreach (['abc', '0', '-1', '1.5', ''] as $invalid) {
    try {
        payslip_positive_int_filter(['ei' => $invalid], 'ei');
        check(false, "rejects invalid filter {$invalid}");
    } catch (InvalidArgumentException $error) {
        check(true, "rejects invalid filter {$invalid}");
    }
}

echo "RESULT: Payslip report filter rules passed.\n";
