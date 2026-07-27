<?php

declare(strict_types=1);

/**
 * Pure release policy shared by the controller-facing model and unit tests.
 * Database discovery is kept in Payslip; this function only decides whether
 * the supplied evidence is sufficient to make payroll immutable.
 */
function payroll_release_gate_policy(array $context): array
{
    if (empty($context['smart_run_schema_exists'])) {
        return payroll_legacy_preview_gate_blocked(['smart_run_schema_required'], $context);
    }
    if (empty($context['smart_flow_enrolled'])) {
        return payroll_legacy_preview_gate_blocked(['smart_payroll_enrollment_required'], $context);
    }
    if ((int)($context['live_payroll_row_count'] ?? 0) <= 0) {
        return payroll_release_gate_blocked(['payroll_scope_empty'], $context);
    }

    $authoritativeRunId = (int)($context['authoritative_run_id'] ?? 0);
    if ($authoritativeRunId <= 0) {
        return payroll_release_gate_blocked(['authoritative_run_selection_required'], $context);
    }
    $runs = is_array($context['runs'] ?? null) ? $context['runs'] : [];
    if (count($runs) !== 1 || (int)($runs[0]['id'] ?? 0) !== $authoritativeRunId) {
        return payroll_release_gate_blocked(['authoritative_smart_run_missing'], $context);
    }

    $actor = trim((string)($context['actor'] ?? ''));
    $releaseAttempt = !empty($context['release_attempt']);
    $releaseActorVerified = false;
    $blocking = [];
    foreach ($runs as $run) {
        $uid = trim((string)($run['run_uid'] ?? $run['id'] ?? 'unknown'));
        $prefix = 'run:' . $uid . ':';

        if (!in_array((string)($run['status'] ?? ''), ['approved', 'released'], true)) {
            $blocking[] = $prefix . 'status_not_approved';
        }
        if ((string)($run['identity_status'] ?? '') !== 'resolved') {
            $blocking[] = $prefix . 'identity_not_clear';
        }
        if ((int)($run['unresolved_identity_count'] ?? 0) !== 0
            || (int)($run['identity_collision_count'] ?? 0) !== 0) {
            $blocking[] = $prefix . 'identity_blockers_open';
        }
        if ((int)($run['validation_error_count'] ?? 0) !== 0) {
            $blocking[] = $prefix . 'validation_blockers_open';
        }
        if ((string)($run['ruleset_status'] ?? '') !== 'locked'
            || !payroll_release_gate_sha256($run['ruleset_hash'] ?? null)) {
            $blocking[] = $prefix . 'ruleset_not_locked';
        }
        if (!payroll_release_gate_sha256($run['input_snapshot_hash'] ?? null)
            || !payroll_release_gate_sha256($run['canonical_snapshot_hash'] ?? null)
            || (int)($run['source_row_count'] ?? 0) <= 0
            || (int)($run['canonical_row_count'] ?? 0) <= 0) {
            $blocking[] = $prefix . 'run_snapshot_not_sealed';
        }
        if (empty($run['input_hash_verified']) || empty($run['rules_hash_verified'])
            || empty($run['canonical_hash_verified'])) {
            $blocking[] = $prefix . 'stored_snapshot_hash_verification_failed';
        }
        if ((string)($run['calculation_status'] ?? '') !== 'passed') {
            $blocking[] = $prefix . 'calculation_not_passed';
        }
        if ((string)($run['reconciliation_status'] ?? '') !== 'passed') {
            $blocking[] = $prefix . 'reconciliation_not_passed';
        }
        if (!in_array((string)($run['release_status'] ?? ''), ['ready', 'released'], true)
            || (int)($run['release_blocker_count'] ?? 0) !== 0) {
            $blocking[] = $prefix . 'release_not_approved';
        }

        if ((int)($run['mandatory_check_count'] ?? 0) !== 8
            || (int)($run['passed_mandatory_check_count'] ?? 0) !== 8) {
            $blocking[] = $prefix . 'mandatory_checks_missing_or_failed';
        }
        $blockingChecks = (int)($run['blocking_check_count'] ?? 0);
        $passedChecks = (int)($run['passed_blocking_check_count'] ?? 0);
        if ($blockingChecks < 8 || $passedChecks !== $blockingChecks) {
            $blocking[] = $prefix . 'blocking_checks_not_passed';
        }
        if (empty($run['legacy_binding_verified'])) {
            $blocking[] = $prefix . 'live_payroll_scope_changed_or_unbound';
        }

        $rowCount = (int)($run['employee_count'] ?? 0);
        $artifactCount = (int)($run['ready_artifact_count'] ?? 0);
        if ($rowCount <= 0 || $artifactCount !== $rowCount) {
            $blocking[] = $prefix . 'payslip_artifact_coverage_incomplete';
        }
        if (empty($run['artifact_files_verified'])) {
            $blocking[] = $prefix . 'sealed_payslip_file_verification_failed';
        }

        $maker = trim((string)($run['maker_created_by'] ?? ''));
        $checker = trim((string)($run['checker_approved_by'] ?? ''));
        if ($maker === '' || $checker === '') {
            $blocking[] = $prefix . 'maker_checker_missing';
        } elseif (strcasecmp($maker, $checker) === 0) {
            $blocking[] = $prefix . 'maker_checker_same_user';
        }
        if ($releaseAttempt) {
            if ($actor === '') {
                $blocking[] = $prefix . 'release_actor_missing';
            } elseif ($checker !== '' && strcasecmp($actor, $checker) === 0) {
                $blocking[] = $prefix . 'release_actor_is_checker';
            } else {
                $releaseActorVerified = true;
            }
        }
    }

    if (count($blocking) > 0) {
        return payroll_release_gate_blocked(array_values(array_unique($blocking)), $context);
    }

    return [
        'success' => 1,
        'gate_status' => 'ready',
        'mode' => 'smart_run',
        'smart_flow_enrolled' => true,
        'authoritative_run_id' => $authoritativeRunId,
        'release_attempt' => $releaseAttempt,
        'release_actor' => $actor,
        'release_actor_verified' => $releaseActorVerified,
        'run_ids' => array_values(array_map(
            static fn(array $run): int => (int)($run['id'] ?? 0),
            $runs
        )),
        'blocking_reasons' => [],
    ];
}

function payroll_release_gate_sha256($value): bool
{
    return preg_match('/^[a-f0-9]{64}$/i', trim((string)$value)) === 1;
}

function payroll_release_gate_blocked(array $reasons, array $context = []): array
{
    $runs = is_array($context['runs'] ?? null) ? $context['runs'] : [];
    $response = [
        'success' => 0,
        'gate_status' => 'blocked',
        'mode' => 'smart_run',
        'code' => 'payroll_release_gate_blocked',
        'error' => 'Payroll cannot be posted until identity, calculation, reconciliation, payslip, and maker-checker controls pass.',
        'blocking_reasons' => array_values(array_unique($reasons)),
    ];

    if (array_key_exists('smart_flow_enrolled', $context)) {
        $response['smart_flow_enrolled'] = !empty($context['smart_flow_enrolled']);
    }
    if (array_key_exists('authoritative_run_id', $context)) {
        $response['authoritative_run_id'] = (int)$context['authoritative_run_id'];
    }
    if (count($runs) > 0) {
        $response['run_ids'] = array_values(array_map(
            static fn(array $run): int => (int)($run['id'] ?? 0),
            $runs
        ));
    }
    if (array_key_exists('release_attempt', $context)) {
        $response['release_attempt'] = !empty($context['release_attempt']);
    }
    if (array_key_exists('actor', $context)) {
        $response['release_actor'] = trim((string)$context['actor']);
        $response['release_actor_verified'] = false;
    }

    return $response;
}

function payroll_legacy_preview_gate_blocked(array $reasons, array $context = []): array
{
    $response = payroll_release_gate_blocked($reasons, $context);
    $response['mode'] = 'legacy_preview';
    $response['preview_allowed'] = true;
    $response['preview_policy'] = 'unverified_legacy_non_distributable';
    $response['error'] = 'Legacy payslip preview is available, but payroll posting requires the governed smart-run controls.';
    return $response;
}
