<?php

declare(strict_types=1);

require __DIR__ . '/../release-gate-rules.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$legacy = payroll_release_gate_policy(['smart_run_schema_exists' => false]);
check(
    ($legacy['success'] ?? 1) === 0
        && in_array('smart_run_schema_required', $legacy['blocking_reasons'] ?? [], true)
        && ($legacy['mode'] ?? '') === 'legacy_preview'
        && ($legacy['preview_allowed'] ?? false) === true,
    'missing smart-run schema blocks posting but explicitly permits unverified legacy preview'
);

$notEnrolled = payroll_release_gate_policy(['smart_run_schema_exists' => true, 'smart_flow_enrolled' => false]);
check(
    ($notEnrolled['success'] ?? 1) === 0
        && in_array('smart_payroll_enrollment_required', $notEnrolled['blocking_reasons'] ?? [], true)
        && ($notEnrolled['mode'] ?? '') === 'legacy_preview'
        && ($notEnrolled['preview_policy'] ?? '') === 'unverified_legacy_non_distributable',
    'unenrolled clients fail closed for posting while retaining explicit legacy preview'
);

$missing = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 1,
]);
check(($missing['success'] ?? 1) === 0 && in_array('authoritative_run_selection_required', $missing['blocking_reasons'], true), 'enrolled client without an explicit run fails closed');

$empty = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 0,
    'authoritative_run_id' => 19,
]);
check(
    ($empty['success'] ?? 1) === 0
        && in_array('payroll_scope_empty', $empty['blocking_reasons'] ?? [], true),
    'zero-row payroll scope fails closed'
);

$readyRun = [
    'id' => 19,
    'run_uid' => 'RUN-19',
    'status' => 'approved',
    'identity_status' => 'resolved',
    'unresolved_identity_count' => 0,
    'identity_collision_count' => 0,
    'validation_error_count' => 0,
    'ruleset_status' => 'locked',
    'ruleset_hash' => str_repeat('a', 64),
    'input_snapshot_hash' => str_repeat('b', 64),
    'canonical_snapshot_hash' => str_repeat('c', 64),
    'source_row_count' => 691,
    'canonical_row_count' => 691,
    'calculation_status' => 'passed',
    'reconciliation_status' => 'passed',
    'release_status' => 'ready',
    'release_blocker_count' => 0,
    'blocking_check_count' => 8,
    'passed_blocking_check_count' => 8,
    'mandatory_check_count' => 8,
    'passed_mandatory_check_count' => 8,
    'input_hash_verified' => true,
    'rules_hash_verified' => true,
    'canonical_hash_verified' => true,
    'legacy_binding_verified' => true,
    'artifact_files_verified' => true,
    'employee_count' => 691,
    'ready_artifact_count' => 691,
    'maker_created_by' => 'maker.user',
    'checker_approved_by' => 'checker.user',
];
$ready = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'release.user',
    'runs' => [$readyRun],
]);
check(
    ($ready['success'] ?? 0) === 1
        && ($ready['mode'] ?? '') === 'smart_run'
        && ($ready['release_attempt'] ?? false) === true
        && ($ready['release_actor'] ?? '') === 'release.user'
        && ($ready['release_actor_verified'] ?? false) === true,
    'fully approved smart run opens the actor-aware release gate'
);

$sameUser = $readyRun;
$sameUser['checker_approved_by'] = 'maker.user';
$blocked = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'maker.user',
    'runs' => [$sameUser],
]);
check(
    ($blocked['success'] ?? 0) === 1
        && ($blocked['release_actor_verified'] ?? false) === true,
    'the same authorized Payroll owner may create, approve, and release the run'
);

$checkerActor = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => ' CHECKER.USER ',
    'runs' => [$readyRun],
]);
check(
    ($checkerActor['success'] ?? 0) === 1
        && ($checkerActor['release_attempt'] ?? false) === true
        && ($checkerActor['release_actor'] ?? '') === 'CHECKER.USER'
        && ($checkerActor['release_actor_verified'] ?? false) === true,
    'the recorded approver may also release the run'
);

$independentActor = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'another.user',
    'runs' => [$readyRun],
]);
check(
    ($independentActor['success'] ?? 0) === 1
        && ($independentActor['release_actor_verified'] ?? false) === true,
    'another authenticated Payroll owner may release the approved run'
);

$missingActor = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => '',
    'runs' => [$readyRun],
]);
check(
    in_array('run:RUN-19:release_actor_missing', $missingActor['blocking_reasons'], true),
    'release preflight fails closed when the authenticated actor is missing'
);

$unreconciled = $readyRun;
$unreconciled['reconciliation_status'] = 'failed';
$unreconciled['ready_artifact_count'] = 690;
$blocked = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'release.user',
    'runs' => [$unreconciled],
]);
check(in_array('run:RUN-19:reconciliation_not_passed', $blocked['blocking_reasons'], true), 'failed reconciliation blocks posting');
check(in_array('run:RUN-19:payslip_artifact_coverage_incomplete', $blocked['blocking_reasons'], true), 'incomplete payslip artifacts block posting');

$tamperedArtifact = $readyRun;
$tamperedArtifact['artifact_files_verified'] = false;
$blocked = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'release.user',
    'runs' => [$tamperedArtifact],
]);
check(
    in_array('run:RUN-19:sealed_payslip_file_verification_failed', $blocked['blocking_reasons'], true),
    'missing or tampered sealed payslip files block posting'
);

$checksPending = $readyRun;
$checksPending['passed_blocking_check_count'] = 7;
$checksPending['passed_mandatory_check_count'] = 7;
$blocked = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'live_payroll_row_count' => 691,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'release.user',
    'runs' => [$checksPending],
]);
check(in_array('run:RUN-19:blocking_checks_not_passed', $blocked['blocking_reasons'], true), 'every blocking release check must pass');

echo "RESULT: Payroll release gate rules passed.\n";
