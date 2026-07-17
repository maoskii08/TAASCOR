<?php

declare(strict_types=1);

function dynamic_seal_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$report = (string)file_get_contents(__DIR__ . '/../payslip2.php');
dynamic_seal_check(
    str_contains($report, 'FROM payroll_import_release_locks l')
        && str_contains($report, "header('Location: payslip-sealed.php?"),
    'released enrolled payroll routes dynamic requests to the sealed artifact endpoint'
);
dynamic_seal_check(
    str_contains($report, '$serverRequiresPreview = true;')
        && str_contains($report, '$pdf->preReleasePreview = $serverRequiresPreview ||'),
    'unreleased enrolled payroll is watermarked by server state even when preview=1 is omitted'
);
dynamic_seal_check(
    str_contains($report, "exit('Payslip release status could not be verified. Please try again.');"),
    'dynamic payslip rendering fails closed when smart release status cannot be checked'
);

echo "RESULT: Dynamic payslip seal enforcement passed.\n";
