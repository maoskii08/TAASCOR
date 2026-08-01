<?php

declare(strict_types=1);

require __DIR__ . '/../model/Import.php';

$checks = 0;

function checkLegacyImport(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class NoWriteLegacyDatabase
{
    public int $prepareAttempts = 0;
    public int $transactionAttempts = 0;

    public function prepare(string $sql)
    {
        $this->prepareAttempts++;
        throw new RuntimeException('A quarantined importer must not prepare SQL.');
    }

    public function beginTransaction(): bool
    {
        $this->transactionAttempts++;
        throw new RuntimeException('A quarantined importer must not start a transaction.');
    }
}

$db = new NoWriteLegacyDatabase();
$model = new Import();
$model->db = $db;
$model->payrollDetails = [[
    'FUJIFILM',
    '16-30',
    '2026-07-05',
    '2026-06-16',
    '2026-06-30',
]];

$staging = $model->add([['42']], ['employee id' => 0]);
checkLegacyImport(
    ($staging['code'] ?? '') === 'legacy_dtr_workbook_import_quarantined'
        && ($staging['mutation_blocked'] ?? false) === true,
    'direct DTR workbook staging is permanently quarantined'
);
$finalization = $model->spCalculateDTR();
checkLegacyImport(
    ($finalization['code'] ?? '') === 'legacy_dtr_workbook_import_quarantined',
    'direct legacy workbook finalization is permanently quarantined'
);
checkLegacyImport(
    $db->prepareAttempts === 0 && $db->transactionAttempts === 0,
    'quarantined model paths perform no SQL or transaction work'
);

$root = dirname(__DIR__, 2);
$dtrControllers = [
    $root . '/dtr-upload/controller/PostImportController.php',
    $root . '/dtr-upload/controller/ImportController.php',
];
$loanControllers = [
    $root . '/loans/controller/PostImportController.php',
    $root . '/loans/controller/ImportController.php',
];

foreach ($dtrControllers as $path) {
    $source = (string)file_get_contents($path);
    checkLegacyImport(
        str_contains($source, "http_response_code(410)")
            && str_contains($source, 'legacy_dtr_workbook_import_quarantined')
            && str_contains($source, "'recovery_route' => '../dtr-format-engine/'")
            && !str_contains($source, 'db_connect.php')
            && !str_contains($source, 'runUnlockedMutation')
            && !str_contains($source, '->add(')
            && !str_contains($source, 'spCalculateDTR'),
        basename($path) . ' returns 410 before loading any mutation dependency'
    );
}

foreach ($loanControllers as $path) {
    $source = (string)file_get_contents($path);
    checkLegacyImport(
        str_contains($source, "http_response_code(410)")
            && str_contains($source, 'legacy_loans_dtr_import_quarantined')
            && !str_contains($source, 'db_connect.php')
            && !str_contains($source, 'runUnlockedMutation')
            && !str_contains($source, '->add(')
            && !str_contains($source, 'spCalculateDTR'),
        basename(dirname($path)) . '/' . basename($path)
            . ' permanently quarantines the misleading Loans DTR importer'
    );
}

$dtrIndex = (string)file_get_contents($root . '/dtr-upload/index.php');
$dtrJavascript = (string)file_get_contents($root . '/dtr-upload/js/index-13.js');
$loansIndex = (string)file_get_contents($root . '/loans/index.php');
$loanModel = (string)file_get_contents($root . '/loans/model/Import.php');

checkLegacyImport(
    !str_contains($dtrIndex, 'app-excel-import-v05.js')
        && !str_contains($dtrIndex, 'controller/PostImportController.php')
        && !str_contains($dtrIndex, 'id="fileUploader"')
        && str_contains($dtrJavascript, 'Open DTR Format Engine')
        && str_contains($dtrJavascript, "window.location.href = '../dtr-format-engine/'"),
    'DTR page has no browser-batched uploader and routes users to the governed engine'
);
checkLegacyImport(
    !str_contains($loansIndex, 'app-excel-import-v02.js'),
    'Loans page no longer loads the inert and misleading DTR importer'
);
checkLegacyImport(
    str_contains($loanModel, 'legacy_loans_dtr_import_quarantined')
        && !str_contains($loanModel, 'CALL ')
        && preg_match('/private function insertToDatabase/', $loanModel) === 1,
    'legacy Loans model cannot execute a calculator or expose its DTR writer'
);
checkLegacyImport(
    !str_contains((string)file_get_contents($root . '/dtr-upload/model/Import.php'), 'CALL '),
    'retired DTR workbook model contains no calculator invocation'
);

echo "RESULT: {$checks} legacy DTR import-quarantine checks passed.\n";
