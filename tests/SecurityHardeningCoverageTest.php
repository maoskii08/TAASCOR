<?php

declare(strict_types=1);

function security_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$htaccess = (string) file_get_contents($root . '/.htaccess');
foreach ([
    'Strict-Transport-Security',
    'X-Frame-Options',
    'X-Content-Type-Options',
    'Referrer-Policy',
    'Permissions-Policy',
] as $header) {
    security_check(
        str_contains($htaccess, "Header always set {$header}"),
        "root Apache policy sets {$header} on all responses"
    );
}
security_check(
    str_contains($htaccess, 'Header always unset X-Powered-By'),
    'root Apache policy suppresses the PHP version response header'
);
security_check(
    preg_match('/RewriteRule\s+\(\^\|\/\)\(config\|includes\|model\)/', $htaccess) === 1,
    'root Apache policy blocks direct URL access to config, includes, and model directories'
);
security_check(
    preg_match('/\[F,L,NC\]/', $htaccess) === 1,
    'internal-directory rule fails closed with HTTP 403 semantics'
);

$dashboard = (string) file_get_contents($root . '/dashboard/index.php');
$dashboardDoctype = strpos(strtolower($dashboard), '<!doctype html>');
$dashboardGuard = strpos($dashboard, "require_once('../includes/auth_guard.php')");
$dashboardLogin = strpos($dashboard, 'auth_require_login();');
security_check($dashboardDoctype !== false, 'dashboard retains its document body');
security_check(
    $dashboardGuard !== false && $dashboardLogin !== false
        && $dashboardGuard < $dashboardDoctype && $dashboardLogin < $dashboardDoctype,
    'dashboard authentication completes before any HTML output'
);

$payslip = (string) file_get_contents($root . '/payslip/payslip.php');
$payslipGuard = strpos($payslip, "require_once __DIR__ . '/../includes/auth_guard.php'");
$payslipRole = strpos($payslip, 'auth_require_role([1, 3]);');
$payslipAlias = strpos($payslip, "require __DIR__ . '/payslip2.php'");
security_check(
    $payslipGuard !== false && $payslipRole !== false && $payslipAlias !== false
        && $payslipGuard < $payslipAlias && $payslipRole < $payslipAlias,
    'historical payslip URL enforces the explicit Payroll/Admin guard before its maintained implementation'
);

echo "RESULT: Security hardening coverage checks passed.\n";
