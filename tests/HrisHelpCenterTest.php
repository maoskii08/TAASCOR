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
$guides = (string)file_get_contents($root . '/assets/js/hris-help-guides.js');
$helpScript = (string)file_get_contents($root . '/assets/js/hris-help.js');
$helpCss = (string)file_get_contents($root . '/assets/css/hris-help.css');
$customFooter = (string)file_get_contents($root . '/includes/custom-footer.php');
$navigation = (string)file_get_contents($root . '/includes/nav-bar.php');
$permissions = (string)file_get_contents($root . '/config/page-permissions.php');
$restrictionJs = (string)file_get_contents($root . '/restriction/js/page-restriction-03.js');
$faqPage = (string)file_get_contents($root . '/payroll-help/index.php');
$faqScript = (string)file_get_contents($root . '/payroll-help/js/index.js');

$expectedGuides = [
    'dashboard',
    'employee-management',
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
    'payroll-dashboard',
    'payroll-summary',
    'payroll-data-quality',
    'dtr-format-engine',
    'dtr-upload',
    'other-additional',
    'other-deduction',
    'payslip',
    'loans',
    'loans-report',
    'billing',
    'accounting-finance',
    'client-management',
    'audit-log',
    'terminated-employees',
    'users-access',
    'forms-templates',
    'branch-maintenance',
    'client-maintenance',
    'department-maintenance',
    'position-maintenance',
    'client-location-maintenance',
    'payday',
    'payroll-help',
];

foreach ($expectedGuides as $slug) {
    $directKey = "'{$slug}':";
    $assignedKey = "guides['{$slug}']";
    $check(
        str_contains($guides, $directKey) || str_contains($guides, $assignedKey),
        "page guide exists for {$slug}"
    );
    if ($slug !== 'payroll-help') {
        $page = (string)file_get_contents($root . '/' . $slug . '/index.php');
        $check(str_contains($page, 'includes/custom-footer.php'), "{$slug} loads the global help launcher");
    }
}

$check(str_contains($customFooter, 'hris-help.css?v=20260727c'), 'global help styles load on authenticated pages');
$check(str_contains($customFooter, 'hris-help-guides.js?v=20260728-owner-approval'), 'page-guide registry loads globally');
$check(str_contains($customFooter, 'hris-help.js?v=20260727d'), 'question-mark launcher loads globally');
$check(str_contains($helpScript, 'id="hrisHelpLauncher"'), 'global help launcher is injected');
$check(str_contains($helpScript, 'aria-label="Open guide for '), 'help launcher has a page-specific accessible label');
$check(str_contains($helpScript, "navbarActions.insertBefore(launcherItem, navbarActions.firstChild)"), 'help launcher is placed beside the profile menu');
$check(!str_contains($helpScript, 'hris-help-launcher-label'), 'help launcher displays only the question mark');
$check(str_contains($helpScript, "canvas.toDataURL('image/png')"), 'each guide renders its process flow as a PNG image');
$check(str_contains($helpScript, 'id="hrisHelpLightbox"'), 'process diagrams open in an in-window viewer');
$check(str_contains($helpScript, 'closeProcessImage'), 'process viewer has a close action');
$check(str_contains($helpScript, 'openProcessImage'), 'process viewer can be opened from page guides and Payroll FAQ');
$check(!str_contains($helpScript, 'hrisHelpDownloadDiagram'), 'page guides no longer offer diagram downloads');
$check(str_contains($helpCss, 'body.hris-help-lightbox-open #layout-menu'), 'process viewer covers the application sidebar');
$check(str_contains($helpScript, "document.addEventListener('keydown'"), 'guide drawer supports keyboard dismissal');
$check(str_contains($helpScript, "event.key === 'Tab'"), 'guide drawer keeps keyboard focus inside the open dialog');
$check(str_contains($helpScript, 'Payroll cycle checklist'), 'payroll guides include the end-to-end checklist in the drawer');
$check(str_contains($helpScript, 'hrisHelpContextFaqSearch'), 'payroll guides include contextual FAQ search');
$check(str_contains($helpScript, 'contextualPayrollFaqs'), 'drawer selects page-relevant questions and searches common payroll topics');
$check(str_contains($helpScript, 'Open Full Payroll Help Center'), 'drawer links to the complete Payroll Help Center');
$check(str_contains($helpScript, "app.setAttribute('inert', '')"), 'open help drawer makes background controls unavailable');
$check(str_contains($guides, 'payrollHelp: payrollHelp'), 'checklist and contextual FAQ data are structured in the guide registry');
$check(substr_count($guides, 'question:') >= 12, 'guide registry includes a useful contextual payroll FAQ set');
$check(
    str_contains($guides, 'using source-backed payroll totals')
        && str_contains($guides, 'The dashboard does not invent accuracy, compliance, or performance scores.')
        && !str_contains($guides, 'payroll accuracy indicators'),
    'Payroll Dashboard guide documents source-backed controls without invented KPIs'
);
$check(
    str_contains($guides, 'A Post Payroll button that stays disabled')
        && str_contains($guides, 'smart-run schema, client enrollment, authoritative run'),
    'Payslip guide documents the fail-closed authoritative release gate'
);
$check(
    str_contains($guides, 'legacy self-committing calculator is quarantined')
        && str_contains($guides, 'Legacy direct upload and manual DTR Save are intentionally blocked')
        && str_contains($guides, 'Use the DTR Format Engine for every new or corrected payroll input'),
    'DTR guide routes new and corrected inputs away from unsafe legacy transactions'
);
$check(
    str_contains($guides, 'no more than 1,000 normalized rows and 1 MiB')
        && str_contains($guides, 'Adjustment, recalculation, and audit evidence succeed together or roll back together.')
        && str_contains($guides, 'Deduction, recalculation, and audit evidence succeed together or roll back together.'),
    'adjustment guides document bounded atomic upload and transaction-coupled audit'
);
$check(
    str_contains($guides, 'employee transfer v2 migration')
        && str_contains($guides, 'A blocked workbook is preserved staging evidence'),
    'Employee Management guide explains the fail-closed staged workbook flow'
);
$check(
    str_contains($faqPage, 'Why are legacy DTR Upload and manual Save blocked?')
        && str_contains($faqPage, 'What happens if one row in an adjustment workbook fails?')
        && str_contains($faqPage, 'Why did Employee Upload say finalization is blocked?'),
    'Payroll FAQ explains current containment blockers and atomic recovery behavior'
);
$check(str_contains($helpCss, '@media (max-width: 575.98px)'), 'guide drawer has a mobile layout');
$check(str_contains($helpCss, '@media (prefers-reduced-motion: reduce)'), 'guide honors reduced-motion preferences');

$check(str_contains($faqPage, 'auth_require_role([1, 2, 3])'), 'Payroll FAQ is limited to Admin, HR, and Payroll');
$check(substr_count($faqPage, "['") >= 30, 'Payroll FAQ contains at least 30 beginner questions');
$check(str_contains($faqPage, 'payrollFaqSearch'), 'Payroll FAQ has question search');
$check(str_contains($faqScript, 'filterFaqs'), 'Payroll FAQ search and category filters are functional');
$check(str_contains($faqScript, 'createProcessImage'), 'Payroll FAQ renders the end-to-end process image');
$check(str_contains($faqScript, 'openProcessImage'), 'Payroll FAQ opens its process diagram in the shared viewer');
$check(!str_contains($faqPage, 'downloadPayrollFlow'), 'Payroll FAQ no longer offers a diagram download');
$check(str_contains($navigation, 'value="39" id="a39" data-secondary-navigation="true"'), 'Payroll Help Center remains authorized but is marked as secondary navigation');
$check(str_contains($restrictionJs, "menuItem.dataset.secondaryNavigation !== 'true'"), 'secondary Payroll Help Center is not shown as a primary Payroll submenu item');
$check(str_contains($permissions, "39  => ['Payroll Help & FAQ'"), 'Payroll Help has a centralized permission entry');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: HRIS page guides and Payroll FAQ checks passed.\n";
