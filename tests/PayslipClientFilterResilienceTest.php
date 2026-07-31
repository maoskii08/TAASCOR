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
$page = (string)file_get_contents($root . '/payslip/index.php');
$script = (string)file_get_contents($root . '/payslip/js/index-09.js');

$check(
    str_contains($page, 'id="payslipClientLoadState"')
        && str_contains($page, 'role="alert"')
        && str_contains($page, 'aria-live="assertive"'),
    'Payslip provides an accessible client-loading error host'
);
$check(
    str_contains($page, 'index-09.js?v=20260731-client-load-recovery'),
    'Payslip loads the resilient client-filter script through a new cache key'
);
$check(
    str_contains($script, 'function parsePayslipRequestError(')
        && str_contains($script, "status === 401 ? 'ACCESS_REQUIRED'")
        && str_contains($script, "status === 403 ? 'REQUEST_REFRESH_REQUIRED'")
        && str_contains($script, "'CONNECTION_FAILED'"),
    'client-loading failures retain server detail and stable actionable error codes'
);
$check(
    str_contains($script, 'window.HrisActionableErrors.render(host, error')
        && str_contains($script, "secondaryLabel: 'Retry client list'")
        && str_contains($script, 'onSecondary: function ()')
        && str_contains($script, 'getClientFilter();'),
    'client-loading failures render an in-page retry action'
);
$check(
    str_contains($script, ".prop('disabled', false)")
        && str_contains($script, ".attr('aria-invalid', 'true')")
        && str_contains($script, ".attr('aria-describedby', 'payslipClientLoadState')")
        && str_contains($script, 'focusPayslipClientFilter();'),
    'a failed client request re-enables and routes focus to the affected filter'
);
$check(
    str_contains($script, 'Number(response.success) !== 1')
        && str_contains($script, '!Array.isArray(response.data)')
        && str_contains($script, 'error: function (xhr)'),
    'HTTP, application, and malformed-response failures enter the recovery state'
);
$check(
    str_contains($script, 'if(client_selected){')
        && str_contains($script, ".trigger('change.select2')"),
    'placeholder and loading-state updates do not trigger empty client requests'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Payslip client-filter resilience checks passed.\n";
