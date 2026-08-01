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
$issuePages = [
    'incomplete-details',
    'sss-format',
    'duplicate-sss',
    'duplicate-philhealth',
    'duplicate-pagibig',
    'duplicate-tin',
    'duplicate-bank-account',
    'invalid-salary',
    'invalid-contact-number',
    'invalid-employee-type',
];

foreach ($issuePages as $slug) {
    $page = (string)file_get_contents($root . '/' . $slug . '/index.php');
    $controller = (string)file_get_contents($root . '/' . $slug . '/controller/DataController.php');

    $check(str_contains($page, 'id="table_container"'), "{$slug} provides the shared tracker table mount");
    $check(
        str_contains($page, 'includes/custom-footer.php'),
        "{$slug} loads the shared Data Issue Tracker controls"
    );
    $check(
        preg_match('/auth_require_role\\(\\[1,\\s*2,\\s*3,\\s*5\\]\\)/', $controller) === 1,
        "{$slug} preserves the Admin, HR, Payroll, and C&B tracker role boundary"
    );
}

$customFooter = (string)file_get_contents($root . '/includes/custom-footer.php');
$trackerScript = (string)file_get_contents($root . '/assets/js/data-issue-tracker.js');
$trackerCss = (string)file_get_contents($root . '/assets/css/data-issue-tracker.css');
$employeeScript = (string)file_get_contents($root . '/employee-management/js/index-18.js');
$employeePage = (string)file_get_contents($root . '/employee-management/index.php');
$guides = (string)file_get_contents($root . '/assets/js/hris-help-guides.js');

$check(
    str_contains($customFooter, 'data-issue-tracker.css?v=20260727a')
        && str_contains($customFooter, 'data-issue-tracker.js?v=20260727a'),
    'shared Data Issue Tracker assets load on authenticated pages'
);
$check(
    str_contains($trackerScript, "id=\"dataIssueClientFilter\"")
        && str_contains($trackerScript, 'Population (Branch / Location)'),
    'tracker renders Client and Population filters'
);
$check(
    str_contains($trackerScript, 'exactColumnSearch')
        && str_contains($trackerScript, '$.fn.dataTable.util.escapeRegex'),
    'tracker applies escaped exact-match DataTable filters'
);
$check(
    str_contains($trackerScript, 'data-issue-employee-link')
        && str_contains($trackerScript, "params.set('from_data_issue', '1')"),
    'Employee Ident opens a scoped Employee Management handoff'
);
$check(
    str_contains($trackerScript, "employeeManagementUrl(employeeId, 'edit')")
        && str_contains($trackerScript, "employeeManagementUrl(employeeId, 'terminate')")
        && str_contains($trackerScript, "employeeManagementUrl(employeeId, 'delete')"),
    'tracker exposes update, terminate, and delete handoff actions'
);
$check(
    str_contains($trackerScript, "accessLevel() === '1'")
        && str_contains($trackerScript, "['1', '2', '3']"),
    'permanent delete is Admin-only while employee management is limited to authorized roles'
);
$check(
    str_contains($trackerCss, '.data-issue-toolbar')
        && str_contains($trackerCss, '@media (max-width: 575.98px)')
        && str_contains($trackerCss, ':focus-visible'),
    'tracker controls have responsive and keyboard-focus styles'
);

$check(
    str_contains($employeeScript, 'prepareDataIssueHandoff()')
        && str_contains($employeeScript, 'handleDataIssueHandoff(empTbl)'),
    'Employee Management processes the tracker handoff after loading the filtered record'
);
$check(
    str_contains($employeeScript, "actionName === 'edit'")
        && str_contains($employeeScript, "actionName === 'terminate'")
        && str_contains($employeeScript, "String(access_level) !== '1'"),
    'Employee Management routes update, terminate, and Admin-only delete actions'
);
$check(
    str_contains($employeeScript, 'clearDataIssueHandoffUrl()')
        && str_contains($employeeScript, 'bulkDelete();'),
    'handoff query parameters are cleared before the existing confirmed delete flow'
);
$check(
    str_contains($employeePage, 'index-18.js?v=20260727d'),
    'Employee Management loads the new handoff behavior without a stale browser cache'
);

$check(
    str_contains($guides, 'Client and Population (Branch / Location) filters')
        && str_contains($guides, 'Click Employee Ident or the Update employee pencil'),
    'Data Issue guides describe the implemented filters and update handoff'
);
$check(
    str_contains($guides, 'Terminate or delete an employee')
        && str_contains($guides, 'permanent deletion remains Admin-only')
        && str_contains($guides, 'Never terminate or delete an employee merely to hide a data-quality issue.'),
    'Data Issue guides document lifecycle permissions and destructive-action safeguards'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Data Issue Tracker employee-management workflow checks passed.\n";
