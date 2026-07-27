<?php

declare(strict_types=1);

require __DIR__ . '/DatabaseTestConnection.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$manifestPath = __DIR__ . '/../database/routines/manifest.json';
$manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$expected = $manifest['routines'] ?? [];
check(is_array($expected) && count($expected) === 10, 'routine manifest contains all observed payroll procedures');

$db = test_database_connection();
$routineRows = $db->query("
    SELECT ROUTINE_NAME,
           LOWER(SHA2(ROUTINE_DEFINITION, 256)) AS routine_hash,
           ROUTINE_DEFINITION
    FROM INFORMATION_SCHEMA.ROUTINES
    WHERE ROUTINE_SCHEMA = DATABASE()
      AND ROUTINE_TYPE = 'PROCEDURE'
    ORDER BY ROUTINE_NAME
")->fetchAll(PDO::FETCH_ASSOC);
$rows = [];
foreach ($routineRows as $routineRow) {
    $rows[(string)$routineRow['ROUTINE_NAME']] = $routineRow;
}

check(count($rows) === count($expected), 'database routine count matches the versioned manifest');
foreach ($expected as $name => $hash) {
    check(isset($rows[$name]), "database contains {$name}");
    check(
        hash_equals(
            strtolower((string)$hash),
            strtolower((string)$rows[$name]['routine_hash'])
        ),
        "{$name} matches the observed routine hash"
    );
    $definition = strtoupper((string)$rows[$name]['ROUTINE_DEFINITION']);
    check(
        str_contains($definition, 'START TRANSACTION')
            && preg_match('/\bCOMMIT\s*;/', $definition) === 1,
        "{$name} is explicitly classified as a self-committing legacy procedure"
    );
    check(
        !str_contains($definition, 'ROLLBACK')
            && !str_contains($definition, 'DECLARE CONTINUE HANDLER')
            && !str_contains($definition, 'DECLARE EXIT HANDLER'),
        "{$name} has no database exception handler and cannot be treated as caller-atomic"
    );
}

check(($manifest['status'] ?? '') === 'observed_local_unapproved', 'routine manifest cannot be mistaken for an approved deployment migration');
check(
    ($manifest['transaction_contract'] ?? '') === 'self_committing_without_exception_handler'
        && ($manifest['application_call_policy'] ?? '') === 'blocked_until_transaction_neutral_v2',
    'manifest records the fail-closed application policy for unsafe routine transactions'
);

$root = dirname(__DIR__);
$unsafeCalls = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    if (
        str_starts_with($relative, 'tests/')
        || str_starts_with($relative, 'vendor/')
        || str_starts_with($relative, 'assets/vendor/')
    ) {
        continue;
    }
    $source = (string)file_get_contents($file->getPathname());
    foreach (array_keys($expected) as $routineName) {
        if (preg_match('/\bCALL\s+' . preg_quote((string)$routineName, '/') . '\s*\(/i', $source) === 1) {
            $unsafeCalls[] = "{$relative}:{$routineName}";
        }
    }
}
check(
    $unsafeCalls === [],
    'application code contains no direct literal invocation of a self-committing legacy payroll procedure'
        . ($unsafeCalls === [] ? '' : ' (' . implode(', ', $unsafeCalls) . ')')
);
$safetyGate = (string)file_get_contents(
    $root . '/dtr-upload/model/DTRCalculationSafetyGate.php'
);
check(
    str_contains($safetyGate, 'TAASCOR_DTR_SAFE_ROUTINE_HASHES')
        && str_contains($safetyGate, "'START TRANSACTION'")
        && str_contains($safetyGate, "'COMMIT'")
        && str_contains($safetyGate, "'DDL'")
        && str_contains($safetyGate, 'dtr_calculator_unapproved')
        && str_contains($safetyGate, 'dtr_calculator_hash_mismatch'),
    'dynamic DTR routine selection requires an approved hash and rejects transaction control and DDL'
);
$dynamicCallers = [
    'dtr-upload/model/DTR.php',
    'dtr-upload/model/Import.php',
    'loans/model/Import.php',
];
foreach ($dynamicCallers as $dynamicCaller) {
    $source = (string)file_get_contents($root . '/' . $dynamicCaller);
    if (!str_contains($source, 'CALL {$routine}')) {
        continue;
    }
    check(
        str_contains($source, 'calculatorSafetyEvidence()')
            && str_contains($source, "(\$safety['success'] ?? 0) !== 1"),
        "{$dynamicCaller} proves the selected routine safe before its dynamic CALL"
    );
}
echo "RESULT: Stored payroll routine drift gate passed.\n";
