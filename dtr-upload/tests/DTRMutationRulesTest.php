<?php

declare(strict_types=1);

require __DIR__ . '/../model/DTRMutationRules.php';

$checks = 0;

function checkDtrRule(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function validDtrPayload(): array
{
    $payload = [
        'employee_ident' => '42',
        'client_name' => 'FUJIFILM',
        'cut_off' => '16-30',
        'start_date' => '2026-06-16',
        'end_date' => '2026-06-30',
        'pay_day' => '2026-07-05',
        'change_reason' => 'Correcting the reviewed DTR source values.',
        'change_evidence' => 'Payroll ticket PAY-2026-0042',
    ];
    foreach ([
        'daily_salary',
        'days_worked',
        'absent',
        'lates',
        'undertime',
        'vacation_leave',
        'sick_leave',
        'overtime',
        'night_diff',
        'night_diff_ot',
        'regular_holiday',
        'regular_holiday_ot',
        'regular_holiday_night_diff',
        'regular_holiday_nd_ot',
        'special_holiday',
        'special_holiday_ot',
        'special_holiday_night_diff',
        'special_holiday_nd_ot',
        'rest_day',
        'rest_day_ot',
        'rest_day_night_diff',
        'rest_day_nd_ot',
        'rd_regular_holiday',
        'rd_regular_holiday_ot',
        'rd_regular_holiday_night_diff',
        'rd_regular_holiday_nd_ot',
        'rd_special_holiday',
        'rd_special_holiday_ot',
        'rd_special_holiday_night_diff',
        'rd_special_holiday_nd_ot',
    ] as $field) {
        $payload[$field] = '0';
    }
    $payload['daily_salary'] = '1250.50';
    $payload['days_worked'] = '11';
    return $payload;
}

$valid = DTRMutationRules::validateUpdate(validDtrPayload());
checkDtrRule(($valid['success'] ?? 0) === 1, 'valid DTR update passes');
checkDtrRule(($valid['period_days'] ?? 0) === 15, 'period length is calculated server-side');
checkDtrRule(($valid['data']['daily_salary'] ?? null) === 1250.5, 'numeric values are normalized');
checkDtrRule(
    ($valid['data']['change_evidence'] ?? '') === 'Payroll ticket PAY-2026-0042',
    'manual update retains its reviewed evidence reference'
);

$missingEvidence = validDtrPayload();
$missingEvidence['change_evidence'] = '';
$missingEvidenceResult = DTRMutationRules::validateUpdate($missingEvidence);
checkDtrRule(
    ($missingEvidenceResult['code'] ?? '') === 'dtr_evidence_invalid',
    'manual update without evidence fails closed'
);

$blankOptional = validDtrPayload();
$blankOptional['overtime'] = '';
$blankResult = DTRMutationRules::validateUpdate($blankOptional);
checkDtrRule(($blankResult['data']['overtime'] ?? null) === 0.0, 'blank optional numerics normalize to zero');

$negative = validDtrPayload();
$negative['overtime'] = '-1';
$negativeResult = DTRMutationRules::validateUpdate($negative);
checkDtrRule(($negativeResult['code'] ?? '') === 'dtr_numeric_invalid', 'negative payroll values fail closed');

$nonNumeric = validDtrPayload();
$nonNumeric['daily_salary'] = '1,250';
$nonNumericResult = DTRMutationRules::validateUpdate($nonNumeric);
checkDtrRule(($nonNumericResult['code'] ?? '') === 'dtr_numeric_invalid', 'non-numeric payroll values fail closed');

$tooManyDays = validDtrPayload();
$tooManyDays['days_worked'] = '16';
$tooManyDaysResult = DTRMutationRules::validateUpdate($tooManyDays);
checkDtrRule(($tooManyDaysResult['code'] ?? '') === 'dtr_numeric_invalid', 'days worked cannot exceed selected period');

$badSequence = validDtrPayload();
$badSequence['pay_day'] = '2026-06-29';
$badSequenceResult = DTRMutationRules::validateUpdate($badSequence);
checkDtrRule(($badSequenceResult['code'] ?? '') === 'dtr_period_invalid', 'pay date before period end fails closed');

$badDate = validDtrPayload();
$badDate['start_date'] = '2026-6-16';
$badDateResult = DTRMutationRules::validateUpdate($badDate);
checkDtrRule(($badDateResult['code'] ?? '') === 'dtr_period_invalid', 'non-canonical dates fail closed');

$longPeriod = validDtrPayload();
$longPeriod['start_date'] = '2026-05-31';
$longPeriodResult = DTRMutationRules::validateUpdate($longPeriod);
checkDtrRule(($longPeriodResult['code'] ?? '') === 'dtr_period_invalid', 'periods spanning 30 days or more fail closed');

$phrase = DTRMutationRules::expectedBulkDeleteConfirmation('FUJIFILM', '2026-07-05');
checkDtrRule($phrase === 'DELETE FUJIFILM 2026-07-05', 'bulk-delete confirmation is deterministic');
$scopedPhrase = DTRMutationRules::expectedBulkDeleteConfirmation(
    'FUJIFILM',
    '2026-07-05',
    7,
    9
);
checkDtrRule(
    $scopedPhrase === 'DELETE FUJIFILM 2026-07-05 BRANCH 7 LOCATION 9',
    'bulk-delete confirmation binds branch and location'
);

$authorized = DTRMutationRules::validateBulkDeleteAuthorization(
    'FUJIFILM',
    '2026-07-05',
    $phrase,
    'Duplicate DTR batch uploaded in error.',
    'Payroll approval PAY-2026-0044'
);
checkDtrRule(
    ($authorized['success'] ?? 0) === 1
        && ($authorized['evidence'] ?? '') === 'Payroll approval PAY-2026-0044',
    'typed confirmation, reason, and evidence authorize scoped deletion'
);

$wrongPhrase = DTRMutationRules::validateBulkDeleteAuthorization(
    'FUJIFILM',
    '2026-07-05',
    'DELETE FUJIFILM',
    'Duplicate DTR batch uploaded in error.',
    'Payroll approval PAY-2026-0044'
);
checkDtrRule(
    ($wrongPhrase['code'] ?? '') === 'dtr_delete_confirmation_invalid',
    'wrong typed confirmation blocks deletion'
);

$shortReason = DTRMutationRules::validateBulkDeleteAuthorization(
    'FUJIFILM',
    '2026-07-05',
    $phrase,
    'mistake',
    'Payroll approval PAY-2026-0044'
);
checkDtrRule(
    ($shortReason['code'] ?? '') === 'dtr_business_reason_invalid',
    'short deletion reason blocks deletion'
);

$reviewScope = [
    'client' => 'FUJIFILM',
    'pay_day' => '2026-07-05',
    'branch' => 7,
    'client_location' => 9,
];
$reviewCounts = [
    'dtr_upload' => 4,
    'payroll_gross_variables' => 4,
    'payroll_other_additional' => 2,
    'payroll_other_deduction' => 3,
    'payroll_summary' => 4,
];
$reviewSecret = str_repeat('s', 64);
$reviewToken = DTRMutationRules::issueBulkDeleteReviewToken(
    $reviewScope,
    $reviewCounts,
    'payroll.officer',
    $reviewSecret,
    1000
);
$verifiedReview = DTRMutationRules::validateBulkDeleteReviewToken(
    $reviewToken,
    $reviewScope,
    $reviewCounts,
    'payroll.officer',
    $reviewSecret,
    1100
);
checkDtrRule(($verifiedReview['success'] ?? 0) === 1, 'server-issued bulk review token verifies exact scope and counts');

$driftedScope = $reviewScope;
$driftedScope['branch'] = null;
$scopeDrift = DTRMutationRules::validateBulkDeleteReviewToken(
    $reviewToken,
    $driftedScope,
    $reviewCounts,
    'payroll.officer',
    $reviewSecret,
    1100
);
checkDtrRule(($scopeDrift['code'] ?? '') === 'dtr_delete_scope_drift', 'review token rejects filter scope widening');

$driftedCounts = $reviewCounts;
$driftedCounts['dtr_upload']++;
$countDrift = DTRMutationRules::validateBulkDeleteReviewToken(
    $reviewToken,
    $reviewScope,
    $driftedCounts,
    'payroll.officer',
    $reviewSecret,
    1100
);
checkDtrRule(($countDrift['code'] ?? '') === 'dtr_delete_count_drift', 'review token rejects population drift');

$employeePhrase = DTRMutationRules::expectedEmployeeDeleteConfirmation(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30'
);
checkDtrRule(
    $employeePhrase === 'DELETE EMPLOYEE 42 FUJIFILM 2026-07-05 16-30',
    'employee-delete confirmation binds employee and exact payroll scope'
);
$employeeAuthorization = DTRMutationRules::validateEmployeeDeleteAuthorization(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30',
    $employeePhrase,
    'Duplicate employee DTR row was uploaded.',
    'Payroll approval PAY-2026-0045'
);
checkDtrRule(
    ($employeeAuthorization['success'] ?? 0) === 1
        && ($employeeAuthorization['evidence'] ?? '') === 'Payroll approval PAY-2026-0045',
    'employee deletion requires exact confirmation, reason, and evidence'
);
$employeeBypass = DTRMutationRules::validateEmployeeDeleteAuthorization(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30',
    '',
    '',
    ''
);
checkDtrRule(
    ($employeeBypass['success'] ?? 1) === 0,
    'direct employee-delete request without confirmation fails closed'
);

$benefitsPhrase = DTRMutationRules::expectedGovernmentBenefitsConfirmation(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30'
);
checkDtrRule(
    $benefitsPhrase === 'REMOVE BENEFITS 42 FUJIFILM 2026-07-05 16-30',
    'government-benefits confirmation binds the exact employee payroll scope'
);
$benefitsAuthorization = DTRMutationRules::validateGovernmentBenefitsRemoval(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30',
    $benefitsPhrase,
    'Approved correction of a duplicate statutory deduction.',
    'Payroll approval PAY-2026-0043'
);
checkDtrRule(
    ($benefitsAuthorization['success'] ?? 0) === 1,
    'benefits removal requires typed scope, reason, and evidence'
);
$benefitsBypass = DTRMutationRules::validateGovernmentBenefitsRemoval(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30',
    '',
    '',
    ''
);
checkDtrRule(
    ($benefitsBypass['code'] ?? '') === 'dtr_benefits_confirmation_invalid',
    'direct benefits-removal request without confirmation fails closed'
);

$javascript = (string)file_get_contents(__DIR__ . '/../js/index-13.js');
checkDtrRule(
    !str_contains($javascript, 'if(days_worked > 16)')
        && str_contains($javascript, 'periodDays')
        && str_contains($javascript, 'DTR values must be valid non-negative numbers.'),
    'browser validation uses the selected period and rejects invalid numerics'
);
checkDtrRule(
    str_contains($javascript, "complete: function () {\n            \$('#saveChanges').html('Save Changes');")
        && !str_contains($javascript, "title: 'DTR changes were not saved',\n                    text: response.error || 'The DTR update failed validation.'\n                }).then"),
    'DTR save errors restore the button without forcing a page reload'
);

echo "RESULT: {$checks} DTR mutation-rule checks passed.\n";
