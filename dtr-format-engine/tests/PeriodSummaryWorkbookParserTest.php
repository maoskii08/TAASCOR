<?php

declare(strict_types=1);

require_once __DIR__ . '/../model/SyntheticUploadParser.php';
require_once __DIR__ . '/../model/DtrAdapterRegistry.php';

function period_summary_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$parser = new SyntheticUploadParser();
$periodMethod = new ReflectionMethod(SyntheticUploadParser::class, 'periodFromMarker');
$period = $periodMethod->invoke(
    $parser,
    'PERIOD COVERED June 29- July 13, 2026'
);
period_summary_check(
    ($period['start'] ?? '') === '2026-06-29'
        && ($period['end'] ?? '') === '2026-07-13',
    'parsed a human-readable payroll-period marker deterministically'
);

$matrix = [
    2 => [1 => 'FROM: SAMPLE CLIENT'],
    4 => [1 => 'PERIOD COVERED June 29- July 13, 2026'],
    7 => [3 => 'NO. OF', 5 => 'UT', 7 => 'OT', 9 => 'City Day', 11 => 'OT with Night diff.'],
    8 => [5 => 'HOURS', 7 => 'HOURS', 9 => '(July 2) 130%'],
    9 => [0 => '1', 1 => 'ALPHA, EMPLOYEE', 3 => '11', 5 => '0.5', 7 => '2.25', 9 => '8', 11 => '1'],
    10 => [0 => '2', 1 => 'BETA, EMPLOYEE', 3 => '12', 5 => '', 7 => '4', 9 => '9.5', 11 => ''],
];
$extractMethod = new ReflectionMethod(SyntheticUploadParser::class, 'extractPeriodSummaryTable');
$table = $extractMethod->invoke($parser, $matrix, 'June 29- July13');

period_summary_check(
    ($table['success'] ?? 0) === 1
        && count($table['rows'] ?? []) === 2,
    'extracted every employee row from a two-level period-summary header'
);
period_summary_check(
    ($table['rows'][0][0] ?? 0) === 9
        && ($table['rows'][0][1] ?? '') === 'ALPHA, EMPLOYEE'
        && (string)($table['rows'][0][2] ?? '') === '11'
        && (string)($table['rows'][0][4] ?? '') === '2.25'
        && (string)($table['rows'][0][6] ?? '') === '8'
        && (string)($table['rows'][0][7] ?? '') === '1',
    'preserved source row, employee, days, overtime, holiday, and night-differential values'
);

$registry = new ReflectionClass(DtrAdapterRegistry::class);
$parsers = $registry->getConstant('PARSERS');
period_summary_check(
    is_array($parsers) && in_array('period_summary_workbook_v1', $parsers, true),
    'registered the reusable multi-sheet period-summary parser'
);

echo "RESULT: Multi-sheet period-summary parsing is deterministic and client-agnostic.\n";
