<?php

declare(strict_types=1);

require __DIR__ . '/DatabaseTestConnection.php';
require __DIR__ . '/../payroll-dashboard/model/Dashboard.php';

function payroll_dashboard_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$page = (string)file_get_contents($root . '/payroll-dashboard/index.php');
$script = (string)file_get_contents($root . '/payroll-dashboard/js/index-01.js');
$styles = (string)file_get_contents($root . '/payroll-dashboard/css/dashboard.css');
$controller = (string)file_get_contents($root . '/payroll-dashboard/controller/DashboardController.php');
$modelSource = (string)file_get_contents($root . '/payroll-dashboard/model/Dashboard.php');

payroll_dashboard_check(
    str_contains($page, 'auth_require_role([1, 3])'),
    'Payroll Dashboard remains limited to Admin and Payroll roles'
);
payroll_dashboard_check(
    str_contains($page, 'id="payrollClientFilter"')
        && str_contains($page, 'id="payrollDateFilter"'),
    'dashboard exposes exact client and pay-date filters'
);
payroll_dashboard_check(
    str_contains($page, 'id="payrollDashboardState"')
        && str_contains($page, 'role="status"')
        && str_contains($script, "showPageState('loading'")
        && str_contains($script, "showPageState(\n            'empty'")
        && str_contains($script, "showPageState('error'"),
    'dashboard declares loading, empty, and error states'
);
payroll_dashboard_check(
    !str_contains($page, 'payrolldashboard-graphs.js')
        && !str_contains($page, 'Payroll Accuracy Rate')
        && !str_contains($page, 'Employee Performance Rate')
        && !str_contains($page, '<h4 class="card-title mb-0">18</h4>'),
    'static and invented dashboard KPIs are no longer rendered'
);
payroll_dashboard_check(
    str_contains($page, 'Run, approval, and release')
        && str_contains($page, 'Release readiness')
        && str_contains($page, 'What the dashboard could verify'),
    'dashboard surfaces run state, approval, blockers, and evidence contracts'
);
payroll_dashboard_check(
    str_contains($controller, "get-dashboard-filters")
        && str_contains($controller, "get-pay-date-filters")
        && str_contains($controller, "get-dashboard-snapshot")
        && substr_count($controller, 'auth_require_client_id') === 2,
    'dashboard endpoints authorize every selected client scope'
);
payroll_dashboard_check(
    str_contains($modelSource, 'FROM payroll_import_runs')
        && str_contains($modelSource, 'FROM payroll_summary')
        && str_contains($modelSource, 'FROM dtr_upload')
        && str_contains($modelSource, 'FROM payroll_import_release_checks')
        && str_contains($modelSource, 'FROM locked_payroll')
        && str_contains($modelSource, 'FROM payroll_import_legacy_scope_bindings'),
    'dashboard reads governed-run and legacy evidence sources'
);
payroll_dashboard_check(
    str_contains($modelSource, '+ COALESCE(total_tardy, 0)')
        && str_contains($modelSource, 'PayrollLegacyScopeHasher')
        && str_contains($modelSource, 'LEGACY_SCOPE_BINDING_INVALID')
        && str_contains($script, 'governed binding verified'),
    'dashboard includes tardy deductions and exposes governed financial binding state'
);
payroll_dashboard_check(
    str_contains($modelSource, 'ORDER BY pay_date DESC, id DESC')
        && str_contains($modelSource, "\$scopes[\$date]['run_status'] === null"),
    'pay-date filters retain the newest governed-run status when a date has multiple runs'
);
payroll_dashboard_check(
    str_contains($modelSource, "WHEN status = 'released' AND release_status = 'released' THEN 0")
        && str_contains($modelSource, "WHEN status = 'approved' AND release_status = 'ready' THEN 1"),
    'dashboard selects released or ready governed evidence before newer non-authoritative drafts'
);
payroll_dashboard_check(
    str_contains($modelSource, "'CHECKER_APPROVAL_MISSING'")
        && str_contains($modelSource, "'MAKER_CHECKER_NOT_INDEPENDENT'")
        && str_contains($modelSource, "strcasecmp(\$maker, \$checker)"),
    'dashboard release blockers enforce an independent maker-checker pair'
);
payroll_dashboard_check(
    !preg_match('/\bINSERT\s+INTO\b|\bUPDATE\s+[a-z_`]|\bDELETE\s+FROM\b/i', $modelSource),
    'dashboard model remains read-only'
);
payroll_dashboard_check(
    str_contains($styles, '@media (max-width: 575.98px)')
        && str_contains($styles, '@media (prefers-reduced-motion: reduce)'),
    'dashboard has mobile and reduced-motion behavior'
);

$db = test_database_connection();
$dashboard = new Dashboard();
$dashboard->db = $db;

$dashboardReflection = new ReflectionClass(Dashboard::class);
$approvalStateMethod = $dashboardReflection->getMethod('approvalState');
$sameActorApproval = $approvalStateMethod->invoke($dashboard, 'smart_run', [
    'maker_created_by' => 'payroll.owner',
    'checker_approved_by' => 'PAYROLL.OWNER',
    'maker_created_at' => '2026-07-27 10:00:00',
    'checker_approved_at' => '2026-07-27 10:05:00',
]);
payroll_dashboard_check(
    ($sameActorApproval['state'] ?? '') === 'invalid'
        && str_contains((string)($sameActorApproval['message'] ?? ''), 'approval is invalid'),
    'dashboard does not describe a same-user maker-checker record as independent approval'
);

$readinessStateMethod = $dashboardReflection->getMethod('readinessState');
$releasedWithBlockers = $readinessStateMethod->invoke($dashboard, 'smart_run', [
    'status' => 'released',
    'release_status' => 'released',
], 1);
payroll_dashboard_check(
    $releasedWithBlockers === 'blocked',
    'a released status cannot override current release blockers on the dashboard'
);

$fujiClient = $db->prepare(
    "SELECT client_id
     FROM taascor_client
     WHERE client_name = :client_name
     LIMIT 1"
);
$fujiClient->execute([':client_name' => 'Fujifilm Optics Phils. Inc']);
$fujiClientId = (int)$fujiClient->fetchColumn();
payroll_dashboard_check($fujiClientId > 0, 'Fuji payroll client is available for read-only reconciliation');

$fujiExpected = $db->prepare(
    "SELECT COUNT(*) AS row_count,
            COALESCE(SUM(total_tardy), 0) AS total_tardy,
            COALESCE(SUM(
                COALESCE(employee_sss, 0)
                + COALESCE(employee_philhealth, 0)
                + COALESCE(employee_pagibig, 0)
                + COALESCE(employee_tax, 0)
                + COALESCE(employee_sss_mpf, 0)
                + COALESCE(employee_loan, 0)
                + COALESCE(total_deduction, 0)
                + COALESCE(total_tardy, 0)
            ), 0) AS employee_deductions
     FROM payroll_summary
     WHERE client_name = :client_name
       AND pay_day = :pay_date"
);
$fujiExpected->execute([
    ':client_name' => 'Fujifilm Optics Phils. Inc',
    ':pay_date' => '2025-08-20',
]);
$fujiExpectedRow = $fujiExpected->fetch(PDO::FETCH_ASSOC) ?: [];
payroll_dashboard_check(
    (int)($fujiExpectedRow['row_count'] ?? 0) > 0
        && (float)($fujiExpectedRow['total_tardy'] ?? 0) > 0,
    'Fuji 2025-08-20 fixture materially exercises tardy deductions'
);

$fujiSnapshot = $dashboard->getSnapshot($fujiClientId, '2025-08-20');
payroll_dashboard_check(
    ($fujiSnapshot['success'] ?? 0) === 1
        && !empty($fujiSnapshot['metrics']['financials_available'])
        && abs(
            (float)($fujiSnapshot['metrics']['employee_deductions'] ?? 0)
            - (float)($fujiExpectedRow['employee_deductions'] ?? 0)
        ) < 0.005,
    'Fuji 2025-08-20 employee deductions reconcile exactly to Payroll Summary'
);

$missingBinding = $dashboard->legacyBindingStatus(
    PHP_INT_MAX,
    'Fujifilm Optics Phils. Inc',
    '2025-08-20'
);
payroll_dashboard_check(
    empty($missingBinding['verified'])
        && ($missingBinding['state'] ?? '') === 'missing'
        && !Dashboard::financialsAvailableForScope(1, true, $missingBinding),
    'an unbound governed run hides payroll financials'
);

$liveFujiScope = (new PayrollLegacyScopeHasher($db))->snapshot(
    'Fujifilm Optics Phils. Inc',
    '2025-08-20'
);
$stalePayload = PayrollLegacyScopeHasher::canonicalJson([
    'schema' => 'legacy_payroll_scope_v1',
    'fixture' => 'stale-dashboard-binding',
]);
$staleBinding = [
    'run_id' => 424242,
    'client_name' => 'Fujifilm Optics Phils. Inc',
    'pay_day' => '2025-08-20',
    'live_snapshot_hash' => hash('sha256', $stalePayload),
    'employee_count' => (int)$liveFujiScope['employee_count'],
    'payroll_row_count' => (int)$liveFujiScope['payroll_row_count'],
    'snapshot_payload' => $stalePayload,
];
$staleStatus = Dashboard::evaluateLegacyBindingEvidence(
    $staleBinding,
    $liveFujiScope,
    424242,
    'Fujifilm Optics Phils. Inc',
    '2025-08-20'
);
payroll_dashboard_check(
    empty($staleStatus['verified'])
        && ($staleStatus['state'] ?? '') === 'stale'
        && !Dashboard::financialsAvailableForScope(1, true, $staleStatus),
    'a mismatched live snapshot hash fails closed and hides governed financials'
);

$verifiedBinding = $staleBinding;
$verifiedBinding['live_snapshot_hash'] = (string)$liveFujiScope['live_snapshot_hash'];
$verifiedBinding['snapshot_payload'] = (string)$liveFujiScope['snapshot_payload'];
$verifiedStatus = Dashboard::evaluateLegacyBindingEvidence(
    $verifiedBinding,
    $liveFujiScope,
    424242,
    'Fujifilm Optics Phils. Inc',
    '2025-08-20'
);
payroll_dashboard_check(
    !empty($verifiedStatus['verified'])
        && ($verifiedStatus['state'] ?? '') === 'verified'
        && Dashboard::financialsAvailableForScope(1, true, $verifiedStatus)
        && Dashboard::financialsAvailableForScope(1, false, ['verified' => false]),
    'verified governed bindings and clearly unverified legacy-only scopes may display financials'
);

$clients = $dashboard->getClientFilters();
payroll_dashboard_check(($clients['success'] ?? 0) === 1, 'client filter loads from the configured database');
payroll_dashboard_check(
    count($clients['data'] ?? []) === 0
        || isset($clients['data'][0]['client_id'], $clients['data'][0]['client_name']),
    'client filter returns stable identifiers and names'
);

$scopeFound = false;
foreach (array_slice($clients['data'] ?? [], 0, 12) as $client) {
    $clientId = (int)($client['client_id'] ?? 0);
    if ($clientId <= 0) {
        continue;
    }
    $dates = $dashboard->getPayDateFilters($clientId);
    payroll_dashboard_check(($dates['success'] ?? 0) === 1, "pay-date filter loads for client {$clientId}");
    if (empty($dates['default_pay_date'])) {
        continue;
    }

    $payDates = array_column($dates['data'] ?? [], 'pay_date');
    payroll_dashboard_check(
        in_array($dates['default_pay_date'], $payDates, true),
        'default pay date is one of the source-backed scope options'
    );

    $snapshot = $dashboard->getSnapshot($clientId, (string)$dates['default_pay_date']);
    payroll_dashboard_check(($snapshot['success'] ?? 0) === 1, 'source-backed payroll snapshot loads');
    payroll_dashboard_check(
        (int)($snapshot['scope']['client_id'] ?? 0) === $clientId
            && (string)($snapshot['scope']['pay_date'] ?? '') === (string)$dates['default_pay_date'],
        'snapshot preserves the exact selected client and pay date'
    );
    payroll_dashboard_check(
        isset(
            $snapshot['governance']['run_state'],
            $snapshot['governance']['approval'],
            $snapshot['governance']['release_state'],
            $snapshot['governance']['blockers']
        ),
        'snapshot returns the complete control-status contract'
    );
    payroll_dashboard_check(
        count($snapshot['contracts'] ?? []) === 6,
        'snapshot discloses all six dashboard evidence contracts'
    );
    $scopeFound = true;
    break;
}

payroll_dashboard_check(
    $scopeFound || count($clients['data'] ?? []) === 0,
    'configured data has a reviewable payroll scope, or explicitly has no clients'
);

$invalid = $dashboard->getSnapshot(1, '2026-7-1');
payroll_dashboard_check(
    ($invalid['success'] ?? 1) === 0,
    'non-canonical pay dates fail closed'
);

echo "RESULT: Payroll Dashboard source-backed control checks passed.\n";
