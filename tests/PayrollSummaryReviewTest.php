<?php

declare(strict_types=1);

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$root = dirname(__DIR__);
$page = (string)file_get_contents($root . '/payroll-summary/index.php');
$script = (string)file_get_contents($root . '/payroll-summary/js/index-01.js');
$styles = (string)file_get_contents($root . '/payroll-summary/css/payroll-summary.css');
$controller = (string)file_get_contents($root . '/payroll-summary/controller/PayrollController.php');
$model = (string)file_get_contents($root . '/payroll-summary/model/Payroll.php');
$guides = (string)file_get_contents($root . '/assets/js/hris-help-guides.js');

$check(str_contains($page, 'auth_require_role([1,3])'), 'Payroll Summary remains limited to Admin and Payroll');
$check(str_contains($page, 'id="payrollQuickPeriod"'), 'page exposes a visible period filter');
$check(str_contains($page, 'id="payrollClient"'), 'page exposes a visible client filter');
$check(str_contains($page, 'id="payrollCutoff"'), 'page exposes a visible cutoff filter');
$check(
    str_contains($page, 'id="payrollDateFrom"') && str_contains($page, 'id="payrollDateTo"'),
    'page exposes an explicit pay-date range'
);
$check(
    str_contains($page, 'Executive overview') && str_contains($page, 'Payroll review'),
    'page provides executive and payroll-officer review modes'
);
$check(
    str_contains($page, 'id="payrollSourceNotice"')
        && str_contains($page, 'id="payrollKpiBand"')
        && str_contains($page, 'id="payrollClientChart"'),
    'page provides source lineage, KPIs, and a client comparison visual'
);
$check(
    str_contains($page, 'index-01.js?v=20260727b')
        && str_contains($page, 'payroll-summary.css?v=20260727b'),
    'Payroll Summary assets use the current cache version'
);

$check(
    str_contains($controller, "get-payroll-filters")
        && str_contains($controller, "get-payroll-summary"),
    'controller supports filter metadata and scoped summary requests'
);
$check(
    str_contains($controller, "'date_from'")
        && str_contains($controller, "'date_to'")
        && str_contains($controller, "'client'")
        && str_contains($controller, "'cut_off'"),
    'controller passes every visible scope filter to the model'
);
$check(
    str_contains($model, 'a.pay_day BETWEEN :date_from AND :date_to')
        && str_contains($model, 'a.client_name = :client')
        && str_contains($model, 'a.cut_off = :cut_off'),
    'model applies prepared server-side date, client, and cutoff filters'
);
$check(
    str_contains($model, "table_name = 'payroll_historical_summary'")
        && str_contains($model, "'included_in_summary' => false"),
    'model reports historical-table status without claiming the Drive archive is included'
);
$check(
    str_contains($model, 'COUNT(DISTINCT x.employee_id)')
        && str_contains($model, 'getScopePopulation')
        && str_contains($model, 'employee_deductions')
        && str_contains($model, 'employer_contributions'),
    'model provides de-duplicated scope population and separated deduction/contribution totals'
);
$check(
    str_contains($model, "Each client's previous available payday.")
        && str_contains($model, 'gross_variance_pct'),
    'comparison logic uses prior client payroll and returns review variances'
);

$check(
    str_contains($script, "request: 'get-payroll-filters'")
        && str_contains($script, "request: 'get-payroll-summary'"),
    'browser loads source metadata before the scoped report'
);
$check(
    str_contains($script, 'executiveColumns()')
        && str_contains($script, 'payrollColumns()'),
    'each review mode has a purpose-built table'
);
$check(
    str_contains($script, 'Download scoped summary')
        && str_contains($script, 'exportFilename()'),
    'download is labeled and named with the active scope'
);
$check(
    str_contains($script, 'The shared Google Drive archive for 2017-2026 is not included'),
    'the rendered source notice discloses the historical coverage gap'
);

$check(
    str_contains($styles, '.payroll-view-switch')
        && str_contains($styles, '@media (max-width: 575.98px)')
        && str_contains($styles, '@media (prefers-reduced-motion: reduce)'),
    'Payroll Summary modes are responsive and honor reduced motion'
);
$check(
    str_contains($styles, 'input:focus-visible + span'),
    'review-mode controls have a visible keyboard focus state'
);

$check(
    str_contains($guides, 'Set the payroll review scope')
        && str_contains($guides, 'Complete an executive review')
        && str_contains($guides, 'Complete a payroll-officer review'),
    'Payroll Summary guide documents both review responsibilities'
);
$check(
    str_contains($guides, 'not included until its one-time migration is validated')
        && str_contains($guides, 'This aggregate report does not approve payroll'),
    'guide documents source limitations and release governance'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Payroll Summary executive and payroll review checks passed.\n";
