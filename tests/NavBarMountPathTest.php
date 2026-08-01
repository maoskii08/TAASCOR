<?php

declare(strict_types=1);

function mount_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function derive_mount(string $scriptName): array
{
    $scriptName = str_replace('\\', '/', $scriptName);
    $appBasePath = str_replace('\\', '/', dirname(dirname($scriptName)));
    $appBasePath = rtrim($appBasePath, '/.');
    $appBaseUrl = $appBasePath === '' ? '' : '/' . ltrim($appBasePath, '/');
    $legacySegment = $appBaseUrl === '' ? '.' : trim($appBaseUrl, '/');

    return [$appBaseUrl, $legacySegment];
}

mount_check(derive_mount('/dashboard/index.php') === ['', '.'], 'root-mounted local app resolves to /login without a filesystem segment');
mount_check(derive_mount('/hris/dashboard/index.php') === ['/hris', 'hris'], 'subdirectory deployment preserves its URL mount');
mount_check(derive_mount('\\hris\\dashboard\\index.php') === ['/hris', 'hris'], 'Windows-style request separators are normalized');

$nav = (string) file_get_contents(__DIR__ . '/../includes/nav-bar.php');
mount_check(!str_contains($nav, 'explode(DIRECTORY_SEPARATOR, __DIR__)'), 'navigation no longer derives URLs from filesystem depth');
mount_check(str_contains($nav, 'Location: {$appBaseUrl}/login/'), 'unauthenticated redirect uses the request URL mount');
mount_check(str_contains($nav, 'Location: {$appBaseUrl}/login/?reason=timeout'), 'timeout redirect uses the request URL mount');

$payrollSummary = (string) file_get_contents(__DIR__ . '/../payroll-summary/index.php');
mount_check(!str_contains($payrollSummary, '$pathParts[3]'), 'payroll summary no longer reads a missing filesystem path segment');
mount_check(
    str_contains($payrollSummary, "\$appBaseUrl . '/login/logout.php'"),
    'payroll summary logout uses the request URL mount'
);

echo "RESULT: Navigation mount-path checks passed.\n";
