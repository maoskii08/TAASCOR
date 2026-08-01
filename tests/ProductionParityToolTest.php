<?php

declare(strict_types=1);

function parity_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$tool = $root . '/tools/verify-production-parity.py';
$command = sprintf(
    'python %s --repo %s --git-ref HEAD --manifest-only --list-files',
    escapeshellarg($tool),
    escapeshellarg($root)
);
$lines = [];
$exitCode = 0;
exec($command . ' 2>&1', $lines, $exitCode);
$output = implode("\n", $lines);

parity_check($exitCode === 0, 'exact-commit parity manifest builds without production access');
$result = json_decode($output, true);
parity_check(is_array($result), 'parity tool returns JSON evidence');
parity_check(($result['success'] ?? false) === true, 'manifest-only verification succeeds');
parity_check(($result['mode'] ?? '') === 'manifest-only', 'manifest-only mode is recorded explicitly');
parity_check(
    preg_match('/^[0-9a-f]{40}$/', (string) ($result['commit'] ?? '')) === 1,
    'evidence is anchored to an exact Git commit'
);
parity_check(
    (int) ($result['runtime_files_expected'] ?? 0) > 2000,
    'manifest covers the full tracked web runtime rather than a release delta'
);

$files = $result['expected_files'] ?? [];
parity_check(isset($files['payslip/payslip.php']), 'historical payslip entry point is parity-controlled');
parity_check(isset($files['dashboard/index.php']), 'dashboard entry point is parity-controlled');
parity_check(isset($files['hris/.htaccess']), 'tracked Apache policies are parity-controlled');
parity_check(!isset($files['tests/LegacyRouteQuarantineTest.php']), 'test harnesses are excluded from the web runtime');
parity_check(!isset($files['tools/apply-local-migration.php']), 'operator tools are excluded from the web runtime');
parity_check(
    !isset($files['dtr-format-engine/migrations/20260621_01_dtr_format_engine_base.sql']),
    'database migrations are excluded from the public web runtime'
);
parity_check(!isset($files['.env.example']), 'environment templates are excluded from the public web runtime');
parity_check(
    !isset($files['config/mysql-config.example.php']),
    'nested environment-specific config templates are excluded from the public web runtime'
);
parity_check(
    !isset($files['dtr-format-engine/config/multiclient_validation_profiles.json'])
        && !isset($files['dtr-format-engine/model/BulkSampleTemplateIntegrator.php']),
    'explicit local-only DTR analysis helpers are excluded from the deployable runtime'
);

echo "RESULT: Production parity tool checks passed.\n";
