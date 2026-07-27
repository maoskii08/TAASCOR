<?php

declare(strict_types=1);

require __DIR__ . '/../preview-binding-rules.php';

function preview_binding_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$payload = '{"client_name":"Fujifilm Optics Phils. Inc","pay_day":"2025-08-20"}';
$hash = hash('sha256', $payload);
$run = [
    'id' => 19,
    'client_name' => 'Fujifilm Optics Phils. Inc',
    'pay_date' => '2025-08-20',
    'status' => 'approved',
    'release_status' => 'ready',
    'binding_run_id' => 19,
    'binding_client_name' => 'Fujifilm Optics Phils. Inc',
    'binding_pay_day' => '2025-08-20',
    'binding_snapshot_hash' => $hash,
    'binding_snapshot_payload' => $payload,
    'binding_employee_count' => 18,
    'binding_payroll_row_count' => 18,
    'legacy_binding_check_passed' => 1,
];
$live = [
    'live_snapshot_hash' => $hash,
    'employee_count' => 18,
    'payroll_row_count' => 18,
];
$context = [
    'selected_run_id' => 19,
    'released_run_id' => 0,
    'client_name' => 'Fujifilm Optics Phils. Inc',
    'pay_day' => '2025-08-20',
    'run' => $run,
    'live' => $live,
];

$missing = payslip_preview_binding_policy(array_merge($context, [
    'selected_run_id' => null,
    'run' => [],
]));
preview_binding_check(
    ($missing['success'] ?? 1) === 0
        && ($missing['code'] ?? '') === 'authoritative_run_selection_required',
    'an enrolled dynamic preview requires an explicit selected governed run'
);

$wrongScope = payslip_preview_binding_policy(array_merge($context, [
    'client_name' => 'Another Client',
]));
preview_binding_check(
    ($wrongScope['success'] ?? 1) === 0
        && ($wrongScope['code'] ?? '') === 'selected_run_scope_mismatch',
    'a run selected from another client scope fails closed'
);

$unboundRun = $run;
$unboundRun['binding_snapshot_payload'] = '';
$unbound = payslip_preview_binding_policy(array_merge($context, ['run' => $unboundRun]));
preview_binding_check(
    ($unbound['success'] ?? 1) === 0
        && ($unbound['code'] ?? '') === 'selected_run_binding_evidence_invalid',
    'an approved run without intact stored binding evidence cannot render live legacy rows'
);

$changedLive = $live;
$changedLive['live_snapshot_hash'] = str_repeat('f', 64);
$changed = payslip_preview_binding_policy(array_merge($context, ['live' => $changedLive]));
preview_binding_check(
    ($changed['success'] ?? 1) === 0
        && ($changed['code'] ?? '') === 'selected_run_live_scope_changed',
    'dynamic preview fails closed when live payroll rows changed after the selected run was sealed'
);

$ready = payslip_preview_binding_policy($context);
preview_binding_check(
    ($ready['success'] ?? 0) === 1
        && ($ready['mode'] ?? '') === 'bound_dynamic_preview'
        && (int)($ready['authoritative_run_id'] ?? 0) === 19,
    'the exact approved run with matching stored and live evidence may render a watermarked preview'
);

$releasedRun = $run;
$releasedRun['status'] = 'released';
$releasedRun['release_status'] = 'released';
$released = payslip_preview_binding_policy(array_merge($context, [
    'released_run_id' => 19,
    'run' => $releasedRun,
    'live' => [],
]));
preview_binding_check(
    ($released['success'] ?? 0) === 1
        && ($released['mode'] ?? '') === 'sealed_artifact',
    'the exact released authoritative run routes to immutable artifacts'
);

$differentReleased = payslip_preview_binding_policy(array_merge($context, [
    'selected_run_id' => 20,
    'released_run_id' => 19,
    'run' => array_merge($releasedRun, ['id' => 20]),
]));
preview_binding_check(
    ($differentReleased['success'] ?? 1) === 0
        && ($differentReleased['code'] ?? '') === 'selected_run_not_released_authoritative',
    'a selected run cannot be substituted for a different released authoritative run'
);

$report = (string)file_get_contents(__DIR__ . '/../payslip2.php');
$javascript = (string)file_get_contents(__DIR__ . '/../js/index-09.js');
preview_binding_check(
    str_contains($report, "payslip_positive_int_filter(\$_GET, 'run_id')")
        && str_contains($report, 'payslip_preview_binding_policy([')
        && str_contains($report, "exit('Payslip preview blocked: '"),
    'the dynamic PDF endpoint validates the requested run and fails closed before rendering'
);
preview_binding_check(
    str_contains($javascript, "params.set('run_id', String(payrollRunId));")
        && str_contains($javascript, "params.set('preview', '1');")
        && str_contains($javascript, 'payrollPreviewRunIsSelected(payrollReleaseGate)')
        && str_contains($javascript, '"&employee_id="'),
    'bulk and employee preview requests stay bound to the selected governed run'
);

echo "RESULT: Payslip preview binding checks passed.\n";
