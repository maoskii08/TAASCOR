<?php

declare(strict_types=1);

$manager = (string)file_get_contents(
    __DIR__ . '/../dtr-format-engine/model/EmployeeIdentityNotificationManager.php'
);
$controller = (string)file_get_contents(
    __DIR__ . '/../dtr-format-engine/controller/TemplateController.php'
);
$page = (string)file_get_contents(__DIR__ . '/../dtr-format-engine/index.php');
$script = (string)file_get_contents(__DIR__ . '/../dtr-format-engine/js/index-01.js');

$checks = [
    [str_contains($manager, 'syncPopulationNotification'), 'population notification sync is implemented'],
    [str_contains($manager, "'DTR_PAYSLIP_POPULATION'"), 'population event has a distinct durable type'],
    [str_contains($manager, "'DTR_POPULATION_BATCH_'"), 'population event is idempotent per batch'],
    [str_contains($manager, "'PAYSLIP_WITHOUT_DTR'"), 'population event records the blocking exception code'],
    [str_contains($manager, '#payroll-population-review'), 'population event deep-links to the review queue'],
    [str_contains($manager, "'required_owner_evidence'"), 'population event identifies acceptable owner evidence'],
    [str_contains($controller, "'sync-population-notification'"), 'existing batches can explicitly synchronize notification state'],
    [substr_count($controller, 'syncPopulationNotification(') >= 3, 'import, resolution, and explicit sync update notification state'],
    [str_contains($page, 'id="notifyPopulationOwnersBtn"'), 'review queue exposes an owner-notification action'],
    [str_contains($script, 'syncPayrollPopulationNotification'), 'owner-notification action calls the protected endpoint'],
    [str_contains($page, 'index-01.js?v=20260728d'), 'updated workflow script is cache-busted'],
    [
        str_contains($page, 'TAASCOR_ENABLE_LOCAL_PREVIEWS')
            && str_contains($page, 'data-enable-local-previews')
            && str_contains($page, 'if ($enableLocalPreviewDiagnostics)'),
        'local engineering previews are disabled outside localhost unless explicitly enabled',
    ],
    [
        str_contains($script, 'function localPreviewDiagnosticsEnabled()')
            && str_contains($script, 'if (!localPreviewDiagnosticsEnabled())')
            && str_contains($script, 'if (localPreviewDiagnosticsEnabled())'),
        'local-only preview requests fail closed before production AJAX calls',
    ],
];

foreach ($checks as [$passed, $message]) {
    if (!$passed) {
        throw new RuntimeException('Payroll population notification check failed: ' . $message);
    }
    echo "PASS: {$message}\n";
}

echo "RESULT: Payroll population notification checks passed.\n";
