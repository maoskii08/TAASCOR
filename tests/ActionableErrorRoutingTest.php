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
$footer = (string)file_get_contents($root . '/includes/custom-footer.php');
$errorScript = (string)file_get_contents($root . '/assets/js/hris-actionable-errors.js');
$errorCss = (string)file_get_contents($root . '/assets/css/hris-actionable-errors.css');
$enginePage = (string)file_get_contents($root . '/dtr-format-engine/index.php');
$engineScript = (string)file_get_contents($root . '/dtr-format-engine/js/index-01.js');
$controller = (string)file_get_contents($root . '/dtr-format-engine/controller/TemplateController.php');
$guides = (string)file_get_contents($root . '/assets/js/hris-help-guides.js');

$check(
    str_contains($footer, 'hris-actionable-errors.css?v=20260728b')
        && str_contains($footer, 'hris-actionable-errors.js?v=20260728b'),
    'authenticated pages load the actionable error component'
);
$check(
    str_contains($errorScript, "document.addEventListener('DOMContentLoaded', observe)")
        && str_contains($errorScript, "'.alert-danger, .payroll-page-state--error, [data-hris-error]'"),
    'global error observer upgrades current and dynamically rendered inline failures'
);
$check(
    str_contains($errorScript, 'What failed') === false
        && str_contains($errorScript, 'hris-action-error__title')
        && str_contains($errorScript, 'hris-action-error__resolution')
        && str_contains($errorScript, 'hris-action-error__reference'),
    'actionable panel exposes a title, exact error, recovery instruction, and reference'
);
$check(
    str_contains($errorScript, "'APPROVED_RULESET_REQUIRED'")
        && str_contains($errorScript, "'EMPLOYEE_IDENTITY_BLOCKED'")
        && str_contains($errorScript, "'POPULATION_EXCEPTIONS'")
        && str_contains($errorScript, "'ADAPTER_REQUIRED'")
        && str_contains($errorScript, "'ACCESS_REQUIRED'"),
    'known payroll, identity, adapter, and access errors have owned recovery routes'
);
$check(
    str_contains($errorScript, "new CustomEvent('hris:resolve-error'")
        && str_contains($engineScript, "document.addEventListener('hris:resolve-error', handleActionableErrorRoute)")
        && str_contains($engineScript, "openPayrollRulesDrawer(detail.context || {})"),
    'same-page recovery actions preserve workflow state through a cancelable route event'
);
$check(
    str_contains($errorScript, "var assetMarker = '/assets/js/hris-actionable-errors.js'")
        && str_contains($errorScript, 'return scriptPath.slice(0, assetIndex)'),
    'recovery links derive the local or subdirectory application root from the loaded component asset'
);
$check(
    str_contains($enginePage, 'id="payrollRulesDrawer"')
        && str_contains($enginePage, 'id="payrollRulesForm"')
        && str_contains($enginePage, 'id="payrollRulesPayload"')
        && str_contains($enginePage, 'id="payrollRulesApprovalReason"'),
    'DTR workflow provides an in-page payroll ruleset resolution drawer'
);
$check(
    str_contains($controller, "case 'payroll-rule-sets':")
        && str_contains($controller, "case 'save-payroll-rule-set':")
        && str_contains($controller, "'APPROVED_RULESET_REQUIRED'")
        && str_contains($controller, "'RULESET_EFFECTIVE_OVERLAP'")
        && str_contains($controller, "'RULESET_INTEGRITY_FAILED'"),
    'ruleset API returns stable error codes used by the resolver'
);
$check(
    str_contains($controller, "'client_id' => \$clientId")
        && str_contains($controller, "'pay_date' => \$payDate"),
    'canonical snapshot blocker carries client and pay-date context'
);
$check(
    str_contains($controller, "mb_strlen(\$approvalReason) < 20")
        && str_contains($controller, 'owner_approval_evidence')
        && str_contains($controller, 'ruleset_status = \'approved\'')
        && str_contains($controller, 'COUNT(*)'),
    'ruleset creation requires evidence, records governance, and blocks effective-date overlap'
);
$check(
    str_contains($engineScript, 'showPayrollImportRunError(response')
        && str_contains($engineScript, 'HrisActionableErrors.render($status.get(0), response')
        && str_contains($engineScript, "clearActionableErrorHost($('#payrollImportRunStatus'))"),
    'canonical snapshot failures render actionably and reusable status hosts reset safely'
);
$check(
    str_contains($errorCss, '.hris-action-error__action:focus-visible')
        && str_contains($errorCss, '@media (max-width: 575.98px)')
        && str_contains($errorCss, '@media (prefers-reduced-motion: reduce)'),
    'error actions include focus, mobile, and reduced-motion support'
);
$check(
    str_contains($guides, 'Resolve a payroll ruleset blocker')
        && str_contains($guides, 'Never create a placeholder rule manifest'),
    'page guide documents the recovery flow and prohibits placeholder policy data'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Actionable error routing checks passed.\n";
