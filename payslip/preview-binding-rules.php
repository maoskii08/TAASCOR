<?php

declare(strict_types=1);

/**
 * Server-owned watermark policy. A request parameter may add a preview mark,
 * but it can never remove the mandatory mark from a legacy or governed preview.
 */
function payslip_dynamic_watermark_policy(
    bool $legacyPreview,
    bool $governedPreview,
    bool $requestedPreview
): array {
    if ($legacyPreview) {
        return [
            'required' => true,
            'mode' => 'unverified_legacy',
            'label' => 'UNVERIFIED LEGACY PREVIEW - NON-DISTRIBUTABLE',
        ];
    }
    if ($governedPreview || $requestedPreview) {
        return [
            'required' => true,
            'mode' => 'pre_release',
            'label' => 'PRE-RELEASE PREVIEW - NOT A PUBLISHED PAYSLIP',
        ];
    }
    return [
        'required' => false,
        'mode' => 'none',
        'label' => '',
    ];
}

/**
 * Decides whether an enrolled client's requested payslip may use an immutable
 * released artifact or a watermarked dynamic preview. Dynamic legacy rows are
 * allowed only when they still match the exact seal stored for the selected
 * governed run.
 */
function payslip_preview_binding_policy(array $context): array
{
    $selectedRunId = (int)($context['selected_run_id'] ?? 0);
    if ($selectedRunId <= 0) {
        return payslip_preview_binding_blocked(
            'authoritative_run_selection_required',
            'Select the governed payroll run before opening a payslip preview.'
        );
    }

    $run = is_array($context['run'] ?? null) ? $context['run'] : [];
    if (count($run) === 0 || (int)($run['id'] ?? 0) !== $selectedRunId) {
        return payslip_preview_binding_blocked(
            'selected_run_not_authoritative',
            'The selected payroll run does not belong to this client and pay date.'
        );
    }

    $clientName = trim((string)($context['client_name'] ?? ''));
    $payDay = trim((string)($context['pay_day'] ?? ''));
    if ($clientName === '' || $payDay === ''
        || trim((string)($run['client_name'] ?? '')) !== $clientName
        || (string)($run['pay_date'] ?? '') !== $payDay) {
        return payslip_preview_binding_blocked(
            'selected_run_scope_mismatch',
            'The selected payroll run is bound to a different payroll scope.'
        );
    }

    $releasedRunId = (int)($context['released_run_id'] ?? 0);
    if ($releasedRunId > 0) {
        if ($releasedRunId !== $selectedRunId
            || (string)($run['status'] ?? '') !== 'released'
            || (string)($run['release_status'] ?? '') !== 'released') {
            return payslip_preview_binding_blocked(
                'selected_run_not_released_authoritative',
                'This payroll scope has a different released authoritative run.'
            );
        }
        return [
            'success' => 1,
            'mode' => 'sealed_artifact',
            'authoritative_run_id' => $selectedRunId,
        ];
    }

    if ((string)($run['status'] ?? '') !== 'approved'
        || (string)($run['release_status'] ?? '') !== 'ready') {
        return payslip_preview_binding_blocked(
            'selected_run_not_preview_ready',
            'The selected payroll run is not approved and ready for governed preview.'
        );
    }
    if ((int)($run['legacy_binding_check_passed'] ?? 0) !== 1) {
        return payslip_preview_binding_blocked(
            'selected_run_binding_check_missing',
            'The selected payroll run has no passed legacy-scope binding control.'
        );
    }

    $bindingHash = strtolower(trim((string)($run['binding_snapshot_hash'] ?? '')));
    $bindingPayload = (string)($run['binding_snapshot_payload'] ?? '');
    $storedBindingValid = payslip_preview_sha256($bindingHash)
        && $bindingPayload !== ''
        && hash_equals($bindingHash, hash('sha256', $bindingPayload));
    if (!$storedBindingValid) {
        return payslip_preview_binding_blocked(
            'selected_run_binding_evidence_invalid',
            'The selected payroll run has invalid stored binding evidence.'
        );
    }

    $bindingScopeMatches = (int)($run['binding_run_id'] ?? 0) === $selectedRunId
        && trim((string)($run['binding_client_name'] ?? '')) === $clientName
        && (string)($run['binding_pay_day'] ?? '') === $payDay;
    if (!$bindingScopeMatches) {
        return payslip_preview_binding_blocked(
            'selected_run_binding_scope_mismatch',
            'The selected payroll run seal belongs to a different payroll scope.'
        );
    }

    $live = is_array($context['live'] ?? null) ? $context['live'] : [];
    $liveHash = strtolower(trim((string)($live['live_snapshot_hash'] ?? '')));
    $liveBindingMatches = payslip_preview_sha256($liveHash)
        && (int)($live['employee_count'] ?? 0) > 0
        && (int)($live['payroll_row_count'] ?? 0) > 0
        && hash_equals($bindingHash, $liveHash)
        && (int)($run['binding_employee_count'] ?? -1) === (int)($live['employee_count'] ?? -2)
        && (int)($run['binding_payroll_row_count'] ?? -1) === (int)($live['payroll_row_count'] ?? -2);
    if (!$liveBindingMatches) {
        return payslip_preview_binding_blocked(
            'selected_run_live_scope_changed',
            'Live payroll rows no longer match the selected governed run. Reconcile a superseding run.'
        );
    }

    return [
        'success' => 1,
        'mode' => 'bound_dynamic_preview',
        'authoritative_run_id' => $selectedRunId,
        'binding_snapshot_hash' => $bindingHash,
    ];
}

function payslip_preview_sha256(string $value): bool
{
    return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
}

function payslip_preview_binding_blocked(string $code, string $message): array
{
    return [
        'success' => 0,
        'mode' => 'blocked',
        'code' => $code,
        'error' => $message,
    ];
}
