<?php

declare(strict_types=1);

require __DIR__ . '/../fuji-reference-rules.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

check(
    abs(fuji_reference_late_undertime_hours(9, 0) - 0.15) < 0.0000001,
    'converts late minutes to decimal hours'
);
check(
    number_format(fuji_reference_late_undertime_hours(20, 17), 4) === '0.6167',
    'combines late and undertime minutes using Fuji four-decimal rounding'
);
check(
    abs(fuji_reference_late_undertime_amount(42.25, 17.75) - 60.0) < 0.0000001,
    'combines the two deduction amounts shown on the Fuji payslip'
);
check(
    abs(fuji_reference_printed_total_deductions(1574.41, 11.25, 0) - 1563.16) < 0.0000001,
    'keeps the Fuji printed subtotal distinct from effective net-pay deductions'
);
check(
    payslip_layout_for_client('Fujifilm Optiocs Phils. Inc') === 'fuji-reference',
    'selects the Fuji reference layout from the configured client name'
);
check(
    payslip_layout_for_client('Any Client', 264) === 'fuji-reference',
    'selects the Fuji reference layout from the configured client ID'
);
check(
    payslip_layout_for_client('Another Client') === 'default',
    'keeps the default payslip layout for other clients'
);

echo "RESULT: Fuji reference payslip rules passed.\n";
