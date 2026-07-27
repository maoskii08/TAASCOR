<?php

declare(strict_types=1);

require_once __DIR__ . '/../model/SyntheticUploadParser.php';

function intake_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$parser = new SyntheticUploadParser();
$parseCsv = new ReflectionMethod(SyntheticUploadParser::class, 'parseCsv');

$small = tempnam(sys_get_temp_dir(), 'dtr-small-');
$large = tempnam(sys_get_temp_dir(), 'dtr-large-');
if ($small === false || $large === false) {
    throw new RuntimeException('Unable to create temporary parser fixtures.');
}

try {
    $handle = fopen($small, 'wb');
    fputcsv($handle, ['Employee ID', 'Work Date', 'Time In', 'Time Out'], ',', '"', '\\');
    fputcsv($handle, ['A-001', '2026-06-16', '08:00', '17:00'], ',', '"', '\\');
    fputcsv($handle, ['A-002', '2026-06-16', '08:15', '17:15'], ',', '"', '\\');
    fclose($handle);

    $smallResult = $parseCsv->invoke($parser, $small, 5000, false);
    intake_check(
        ($smallResult['success'] ?? 0) === 1
            && count($smallResult['rows'] ?? []) === 2,
        'returned every source row for a file within the governed limit'
    );

    $handle = fopen($large, 'wb');
    fputcsv($handle, ['Employee ID', 'Work Date', 'Time In', 'Time Out'], ',', '"', '\\');
    for ($index = 1; $index <= 5001; $index++) {
        fputcsv($handle, [
            'A-' . str_pad((string)$index, 5, '0', STR_PAD_LEFT),
            '2026-06-16',
            '08:00',
            '17:00',
        ], ',', '"', '\\');
    }
    fclose($handle);

    $largeResult = $parseCsv->invoke($parser, $large, 5000, false);
    intake_check(
        ($largeResult['success'] ?? 1) === 0
            && str_contains((string)($largeResult['error'] ?? ''), 'No rows were staged'),
        'rejected an over-limit file explicitly instead of silently truncating it'
    );

    $validateRows = new ReflectionMethod(SyntheticUploadParser::class, 'validateRows');
    $summary = $validateRows->invoke(
        $parser,
        ['Vendor ID', 'Days Worked', 'Regular Hours'],
        [[' abc 001 ', '10', '80.5']],
        [
            'id' => 91,
            'client_id' => 264,
            'location_id' => null,
            'date_format' => 'Y-m-d',
            'time_format' => 'summary',
            'employee_identifier_field' => 'Vendor ID',
            'fields' => [
                [
                    'source_header' => 'Vendor ID',
                    'canonical_field' => 'employee_identifier',
                    'data_type' => 'text',
                    'is_required' => 1,
                    'transform_rule' => 'remove_spaces|uppercase',
                ],
                [
                    'source_header' => 'Days Worked',
                    'canonical_field' => 'worked_days',
                    'data_type' => 'number',
                    'is_required' => 1,
                    'transform_rule' => '',
                ],
                [
                    'source_header' => 'Regular Hours',
                    'canonical_field' => 'regular_hours',
                    'data_type' => 'number',
                    'is_required' => 1,
                    'transform_rule' => '',
                ],
            ],
        ],
        '2026-06-16',
        '2026-06-30',
        'approved_mapping_required',
        false,
        [
            'pay_date' => '2026-07-13',
            'source_adapter' => 'SUMMARY_TEST',
            'source_context' => 'generic_real_dtr',
        ]
    );
    $summaryPayload = $summary['safe_rows'][0]['parsed_payload'] ?? [];
    intake_check(
        ($summaryPayload['employee_identifier'] ?? '') === 'ABC001'
            && ($summaryPayload['summary_preview'] ?? false) === true
            && (float)($summaryPayload['worked_days'] ?? -1) === 10.0
            && (float)($summaryPayload['worked_hours_preview'] ?? -1) === 80.5,
        'normalized an employee-level summary format through declarative mappings and transforms'
    );
    echo "RESULT: Generic real DTR parser row limits are exact and fail closed.\n";
} finally {
    @unlink($small);
    @unlink($large);
}
