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

$root = isset($argv[1]) ? realpath((string)$argv[1]) : dirname(__DIR__);
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "FAIL\n- Runtime root is missing or unreadable.\n");
    exit(1);
}

$entrypoints = [
    'payslip/model/Payslip.php',
    'payslip/payslip2.php',
    'payroll-dashboard/model/Dashboard.php',
    'dtr-upload/model/DTR.php',
    'dtr-upload/model/Import.php',
    'dtr-format-engine/model/PayrollImportRunManager.php',
    'other-additional/model/Additional.php',
    'other-additional/model/Import.php',
    'other-deduction/model/Deduction.php',
    'other-deduction/model/Import.php',
];

/** @return list<string> */
function staticDirDependencies(string $source): array
{
    $pattern = '/\b(?:require|require_once|include|include_once)\s*(?:\(\s*)?__DIR__\s*\.\s*([\'\"])([^\'\"]+)\1\s*\)?\s*;/i';
    preg_match_all($pattern, $source, $matches);
    return array_values(array_unique($matches[2] ?? []));
}

/** @return array{checked:list<string>,missing:list<string>} */
function dependencyClosure(string $root, array $entrypoints): array
{
    $queue = $entrypoints;
    $checked = [];
    $missing = [];

    while ($queue !== []) {
        $relative = str_replace('\\', '/', (string)array_shift($queue));
        if (isset($checked[$relative])) {
            continue;
        }
        $checked[$relative] = true;
        $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($absolute)) {
            $missing[] = $relative;
            continue;
        }

        $source = (string)file_get_contents($absolute);
        foreach (staticDirDependencies($source) as $dependency) {
            $resolved = realpath(dirname($absolute) . DIRECTORY_SEPARATOR . $dependency);
            if ($resolved === false || !is_file($resolved)) {
                $missing[] = $relative . ' -> ' . $dependency;
                continue;
            }
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
            $normalized = str_replace('\\', '/', $resolved);
            if (!str_starts_with($normalized, $normalizedRoot . '/')) {
                $missing[] = $relative . ' -> dependency escapes the application root';
                continue;
            }
            $queue[] = ltrim(substr($normalized, strlen($normalizedRoot)), '/');
        }
    }

    return [
        'checked' => array_keys($checked),
        'missing' => array_values(array_unique($missing)),
    ];
}

$result = dependencyClosure($root, $entrypoints);
$check($result['missing'] === [], 'critical payroll PHP runtime dependency closure is complete');
$check(
    in_array('dtr-upload/model/PayrollLockGuard.php', $result['checked'], true),
    'Payslip release package closure includes PayrollLockGuard.php'
);
$check(
    count($result['checked']) >= count($entrypoints),
    'dependency check traverses every critical payroll entrypoint'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", array_merge($failures, $result['missing'])) . "\n");
    exit(1);
}

echo 'RESULT: Runtime dependency closure checks passed for '
    . count($result['checked'])
    . " PHP files.\n";
