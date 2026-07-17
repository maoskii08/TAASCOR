<?php

declare(strict_types=1);

require __DIR__ . '/../model/SmartEmployeeResolutionService.php';

function smart_service_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

smart_service_check(
    SmartEmployeeResolutionService::normalizeSourceDate('25569') === '1970-01-01',
    'Excel 1900-system serial dates normalize to an ISO date'
);
smart_service_check(
    SmartEmployeeResolutionService::normalizeSourceDate('6/16/2026') === '2026-06-16',
    'month/day workbook dates normalize to an ISO date'
);
smart_service_check(
    SmartEmployeeResolutionService::normalizeSourceDate('2026-06-30') === '2026-06-30',
    'ISO workbook dates remain stable'
);
smart_service_check(
    SmartEmployeeResolutionService::normalizeSourceDate('not-a-date') === '',
    'invalid workbook dates remain unavailable instead of being guessed'
);

echo "RESULT: Smart employee resolution source-date rules passed.\n";
