<?php

declare(strict_types=1);

require_once __DIR__ . '/../model/PayrollImportRunManager.php';

function payroll_run_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

payroll_run_check(
    PayrollImportRunManager::canTransition('draft', 'canonicalized'),
    'draft can transition to canonicalized'
);
payroll_run_check(
    !PayrollImportRunManager::canTransition('draft', 'approved'),
    'draft cannot bypass validation and approval'
);
payroll_run_check(
    PayrollImportRunManager::canTransition('ready_for_approval', 'approved'),
    'ready run can transition to approved'
);
payroll_run_check(
    !PayrollImportRunManager::canTransition('released', 'cancelled'),
    'released run is terminal'
);

$left = [
    'batch' => ['uid' => 'B-1', 'id' => 7],
    'period' => ['end' => '2026-06-30', 'start' => '2026-06-16'],
];
$right = [
    'period' => ['start' => '2026-06-16', 'end' => '2026-06-30'],
    'batch' => ['id' => 7, 'uid' => 'B-1'],
];
payroll_run_check(
    PayrollImportRunManager::canonicalJson($left) === PayrollImportRunManager::canonicalJson($right),
    'canonical JSON is independent of associative key order'
);
payroll_run_check(
    PayrollImportRunManager::buildIdempotencyKey($left) === PayrollImportRunManager::buildIdempotencyKey($right),
    'idempotency key is stable for equivalent input'
);
$changed = $right;
$changed['period']['end'] = '2026-07-01';
payroll_run_check(
    PayrollImportRunManager::buildIdempotencyKey($left) !== PayrollImportRunManager::buildIdempotencyKey($changed),
    'idempotency key changes when payroll scope changes'
);
payroll_run_check(
    PayrollImportRunManager::normalizeSourceIdentifier("  fuji-00\t123  ") === 'FUJI-00 123',
    'source identity normalization is deterministic'
);

$safeFacts = [
    'run_status' => 'approved',
    'identity_status' => 'resolved',
    'ruleset_status' => 'locked',
    'calculation_status' => 'passed',
    'reconciliation_status' => 'passed',
    'unresolved_identity_count' => 0,
    'identity_collision_count' => 0,
    'validation_error_count' => 0,
    'employee_count' => 18,
    'verified_artifact_count' => 18,
    'maker' => 'payroll_maker',
    'checker' => 'payroll_checker',
    'blocking_checks' => [
        ['code' => 'IDENTITY_RESOLUTION', 'status' => 'passed'],
        ['code' => 'PAYROLL_RECONCILIATION', 'status' => 'passed'],
    ],
];
$gate = PayrollImportRunManager::evaluateReleaseEligibility($safeFacts);
payroll_run_check($gate['eligible'] === true && $gate['blocker_count'] === 0, 'complete independent evidence opens the release gate');

$makerConflict = $safeFacts;
$makerConflict['checker'] = 'PAYROLL_MAKER';
$gate = PayrollImportRunManager::evaluateReleaseEligibility($makerConflict);
payroll_run_check(
    $gate['eligible'] === false && in_array('maker_checker_not_separated', $gate['blockers'], true),
    'maker-checker conflict blocks release case-insensitively'
);

$artifactGap = $safeFacts;
$artifactGap['verified_artifact_count'] = 17;
$gate = PayrollImportRunManager::evaluateReleaseEligibility($artifactGap);
payroll_run_check(
    $gate['eligible'] === false && in_array('payslip_artifact_coverage_incomplete', $gate['blockers'], true),
    'one missing verified payslip blocks release'
);

$identityCollision = $safeFacts;
$identityCollision['identity_collision_count'] = 1;
$gate = PayrollImportRunManager::evaluateReleaseEligibility($identityCollision);
payroll_run_check(
    $gate['eligible'] === false && in_array('identity_collisions', $gate['blockers'], true),
    'identity collision blocks release'
);

$failedCheck = $safeFacts;
$failedCheck['blocking_checks'][] = ['code' => 'STATUTORY_TOTALS', 'status' => 'failed'];
$gate = PayrollImportRunManager::evaluateReleaseEligibility($failedCheck);
payroll_run_check(
    $gate['eligible'] === false && in_array('check:STATUTORY_TOTALS', $gate['blockers'], true),
    'failed blocking control is explainable in the release result'
);

echo "RESULT: Payroll import run state, idempotency, and release gate rules passed.\n";
