<?php

declare(strict_types=1);

function fuji_reference_late_undertime_hours($lateMinutes, $undertimeMinutes): float
{
    return ((float)$lateMinutes + (float)$undertimeMinutes) / 60;
}

function fuji_reference_late_undertime_amount($lateAmount, $undertimeAmount): float
{
    return (float)$lateAmount + (float)$undertimeAmount;
}

function fuji_reference_printed_total_deductions(
    $effectiveTotalDeductions,
    $lateAmount,
    $undertimeAmount
): float {
    return (float)$effectiveTotalDeductions
        - fuji_reference_late_undertime_amount($lateAmount, $undertimeAmount);
}

function payslip_layout_for_client(string $clientName, ?int $clientId = null): string
{
    $normalized = strtoupper((string)preg_replace('/[^A-Z0-9]+/i', '', trim($clientName)));
    if ($clientId === 264 || str_contains($normalized, 'FUJIFILM')) {
        return 'fuji-reference';
    }
    return 'default';
}
