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
$reloadModules = [
    'branch-maintenance' => 'js/index-02.js',
    'department-maintenance' => 'js/index-02.js',
    'position-maintenance' => 'js/index-02.js',
    'payday' => 'js/index-01.js',
    'client-location-maintenance' => 'js/index-01.js',
];

foreach ($reloadModules as $module => $scriptPath) {
    $script = file_get_contents("{$root}/{$module}/{$scriptPath}");
    $page = file_get_contents("{$root}/{$module}/index.php");

    $check($script !== false, "{$module} script is readable");
    $check($page !== false, "{$module} page is readable");

    if ($script !== false) {
        $directSuccessReloads = preg_match_all(
            '/if\s*\(response\.success\s*==\s*1\)\s*\{\s*window\.location\.reload\(\);/m',
            $script
        );
        $check(
            $directSuccessReloads === 2,
            "{$module} guarantees a direct reload after successful add and edit"
        );
    }

    if ($page !== false) {
        $check(
            str_contains($page, '?v=20260731a'),
            "{$module} cache-busts its updated save workflow"
        );
    }
}

$clientScript = file_get_contents($root . '/client-maintenance/js/index-04.js');
$check($clientScript !== false, 'client-maintenance script is readable');
if ($clientScript !== false) {
    $check(
        str_contains($clientScript, 'getClientList(); loadAlignment();'),
        'Client Maintenance retains its intentional in-page table refresh'
    );
    $check(
        !preg_match('/if\s*\(r\.success\s*==\s*1\)\s*\{\s*window\.location\.reload\(\);/m', $clientScript),
        'Client Maintenance is not converted to a full-page success reload'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Maintenance success-reload regression checks passed.\n";
