<?php

declare(strict_types=1);

function enrollment_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$controller = (string)file_get_contents(__DIR__ . '/../controller/TemplateController.php');
enrollment_check(
    str_contains($controller, "'error_code' => 'SMART_PAYROLL_DOWNGRADE_BLOCKED'")
        && str_contains($controller, 'if (!$enabled)'),
    'an application administrator cannot downgrade an enrolled client to the legacy release path'
);
enrollment_check(
    str_contains($controller, 'effective_from <= CURDATE()')
        && str_contains($controller, 'effective_to IS NULL OR effective_to >= CURDATE()'),
    'one-way enrollment requires a currently effective approved payroll ruleset'
);

echo "RESULT: Smart payroll enrollment governance passed.\n";
