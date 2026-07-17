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
check(($legacy['success'] ?? 0) === 1 && ($legacy['mode'] ?? '') === 'legacy', 'legacy posting remains available without smart-run schema');

$notEnrolled = payroll_release_gate_policy(['smart_run_schema_exists' => true, 'smart_flow_enrolled' => false]);
check(($notEnrolled['success'] ?? 0) === 1 && ($notEnrolled['mode'] ?? '') === 'legacy', 'installing smart tables does not cut over unenrolled clients');

$missing = payroll_release_gate_policy(['smart_run_schema_exists' => true, 'smart_flow_enrolled' => true]);
check(($missing['success'] ?? 1) === 0 && in_array('authoritative_run_selection_required', $missing['blocking_reasons'], true), 'enrolled client without an explicit run fails closed');

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
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'checker.user',
    'runs' => [$readyRun],
]);
check(($ready['success'] ?? 0) === 1 && ($ready['mode'] ?? '') === 'smart_run', 'fully approved smart run opens release gate');

$sameUser = $readyRun;
$sameUser['checker_approved_by'] = 'maker.user';
$blocked = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'maker.user',
    'runs' => [$sameUser],
]);
check(in_array('run:RUN-19:maker_checker_same_user', $blocked['blocking_reasons'], true), 'maker cannot approve and release the same run');

$wrongActor = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'another.user',
    'runs' => [$readyRun],
]);
check(in_array('run:RUN-19:release_actor_not_checker', $wrongActor['blocking_reasons'], true), 'only the recorded checker may post the approved run');

$unreconciled = $readyRun;
$unreconciled['reconciliation_status'] = 'failed';
$unreconciled['ready_artifact_count'] = 690;
$blocked = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'checker.user',
    'runs' => [$unreconciled],
]);
check(in_array('run:RUN-19:reconciliation_not_passed', $blocked['blocking_reasons'], true), 'failed reconciliation blocks posting');
check(in_array('run:RUN-19:payslip_artifact_coverage_incomplete', $blocked['blocking_reasons'], true), 'incomplete payslip artifacts block posting');

$tamperedArtifact = $readyRun;
$tamperedArtifact['artifact_files_verified'] = false;
$blocked = payroll_release_gate_policy([
    'smart_run_schema_exists' => true,
    'smart_flow_enrolled' => true,
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'checker.user',
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
    'authoritative_run_id' => 19,
    'release_attempt' => true,
    'actor' => 'checker.user',
    'runs' => [$checksPending],
]);
check(in_array('run:RUN-19:blocking_checks_not_passed', $blocked['blocking_reasons'], true), 'every blocking release check must pass');

echo "RESULT: Payroll release gate rules passed.\n";
