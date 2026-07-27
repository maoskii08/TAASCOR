<?php

declare(strict_types=1);

function adjustment_containment_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function adjustment_containment_position(string $haystack, string $needle, string $label): int
{
    $position = strpos($haystack, $needle);
    adjustment_containment_check($position !== false, $label);
    return (int)$position;
}

$root = dirname(__DIR__);
$sharedGuard = (string)file_get_contents($root . '/includes/payroll_adjustment_guard.php');
$auditMigration = (string)file_get_contents(
    $root . '/dtr-format-engine/migrations/20260727_01_payroll_adjustment_audit.sql'
);

adjustment_containment_check(
    str_contains($sharedGuard, 'public const MAX_ATOMIC_WORKBOOK_ROWS = 1000')
        && str_contains($sharedGuard, 'public const MAX_ATOMIC_WORKBOOK_BYTES = 1048576')
        && str_contains($sharedGuard, "upload_mode'] ?? '') !== 'atomic-v1'")
        && str_contains($sharedGuard, 'expected_row_count')
        && str_contains($sharedGuard, 'source_filename'),
    'shared guard enforces the bounded one-request atomic workbook contract'
);
adjustment_containment_check(
    str_contains($sharedGuard, 'FROM dtr_upload d')
        && str_contains($sharedGuard, "e.status = 'Active'")
        && str_contains($sharedGuard, 'c.is_active = 1')
        && str_contains($sharedGuard, 'start_date = :start_date')
        && str_contains($sharedGuard, 'end_date = :end_date'),
    'eligibility and duplicate checks use the exact active DTR scope'
);
adjustment_containment_check(
    str_contains($sharedGuard, 'public static function recalculateScope')
        && str_contains($sharedGuard, 'payroll_recalculation_transaction_required')
        && str_contains($sharedGuard, 'UPDATE payroll_summary ps')
        && str_contains($sharedGuard, 'SELECT DISTINCT')
        && str_contains($sharedGuard, ':summary_start_date')
        && str_contains($sharedGuard, ':summary_end_date')
        && str_contains($sharedGuard, ':addition_start_date')
        && str_contains($sharedGuard, ':addition_end_date')
        && str_contains($sharedGuard, ':deduction_start_date')
        && str_contains($sharedGuard, ':deduction_end_date'),
    'shared recalculation is caller-transaction-owned and exact-period constrained'
);
adjustment_containment_check(
    preg_match('/\bCALL\s+sp_/i', $sharedGuard) !== 1
        && preg_match('/\bSTART\s+TRANSACTION\b/i', $sharedGuard) !== 1
        && preg_match('/\bCOMMIT\s*;/i', $sharedGuard) !== 1
        && preg_match('/\bROLLBACK\s*;/i', $sharedGuard) !== 1,
    'shared recalculation cannot invoke or embed a self-committing routine'
);
adjustment_containment_check(
    str_contains($sharedGuard, 'INSERT INTO payroll_adjustment_audit_events')
        && str_contains($sharedGuard, "'row_records'")
        && str_contains($sharedGuard, "'source_row_number'")
        && str_contains($sharedGuard, "'adjustment_id'")
        && str_contains($sharedGuard, "'employee_id'")
        && str_contains($sharedGuard, "'amount'")
        && str_contains($sharedGuard, "'type'")
        && str_contains($sharedGuard, '$legacyAction = "PA|')
        && str_contains($sharedGuard, 'strlen($legacyAction) > 100'),
    'audit writer stores exact row records and a linked legacy pointer bounded to 100 characters'
);
adjustment_containment_check(
    str_contains($auditMigration, 'CREATE TABLE IF NOT EXISTS payroll_adjustment_audit_events')
        && str_contains($auditMigration, 'client_name VARCHAR(190) NOT NULL')
        && str_contains($auditMigration, 'cut_off VARCHAR(50) NOT NULL')
        && str_contains($auditMigration, 'period_start DATE NOT NULL')
        && str_contains($auditMigration, 'period_end DATE NOT NULL')
        && str_contains($auditMigration, 'pay_day DATE NOT NULL')
        && str_contains($auditMigration, 'rows_payload LONGTEXT NOT NULL')
        && str_contains($auditMigration, 'payload_hash CHAR(64) NOT NULL')
        && preg_match('/ENGINE\s*=\s*InnoDB/i', $auditMigration) === 1,
    'audit migration is transactional and can reconstruct exact payroll scope and every row'
);

$modules = [
    'addition' => [
        'directory' => 'other-additional',
        'manual_controller' => 'AdditionalController.php',
        'manual_model' => 'Additional.php',
        'table' => 'payroll_other_additional',
        'type_column' => 'type_of_addition',
        'operation' => 'ADDITION_BULK',
    ],
    'deduction' => [
        'directory' => 'other-deduction',
        'manual_controller' => 'DeductionController.php',
        'manual_model' => 'Deduction.php',
        'table' => 'payroll_other_deduction',
        'type_column' => 'type_of_deduction',
        'operation' => 'DEDUCTION_BULK',
    ],
];

foreach ($modules as $label => $module) {
    $base = $root . '/' . $module['directory'];
    $manualController = (string)file_get_contents($base . '/controller/' . $module['manual_controller']);
    $manualModel = (string)file_get_contents($base . '/model/' . $module['manual_model']);
    $importModel = (string)file_get_contents($base . '/model/Import.php');
    $postImport = (string)file_get_contents($base . '/controller/PostImportController.php');
    $legacyFinalize = (string)file_get_contents($base . '/controller/ImportController.php');
    $page = (string)file_get_contents($base . '/index.php');
    $javascript = (string)file_get_contents($base . '/js/index-08.js');
    $excelImport = (string)file_get_contents($base . '/js/app-excel-import-v04.js');
    $activeMutationSources = implode("\n", [
        $sharedGuard,
        $manualController,
        $manualModel,
        $importModel,
        $postImport,
        $legacyFinalize,
    ]);

    adjustment_containment_check(
        str_contains($manualController, 'auth_require_role([1,3])')
            && str_contains($postImport, 'auth_require_role([1,3])')
            && str_contains($legacyFinalize, 'auth_require_role([1,3])')
            && str_contains($manualController, 'runUnlockedMutation(')
            && str_contains($postImport, 'runUnlockedMutation('),
        "{$label} manual and workbook mutations preserve role authorization and the payroll lease"
    );
    adjustment_containment_check(
        str_contains($postImport, 'MAX_ATOMIC_WORKBOOK_BYTES + 1')
            && str_contains($postImport, 'decodeAtomicWorkbookPayload(')
            && str_contains($postImport, "['expected_row_count']")
            && str_contains($postImport, 'validatedPayrollScope()'),
        "{$label} upload endpoint bounds and validates the whole request before entering the mutation lease"
    );
    adjustment_containment_check(
        str_contains($legacyFinalize, "http_response_code(409)")
            && str_contains($legacyFinalize, "'atomic_workbook_required'")
            && !str_contains($legacyFinalize, 'beginTransaction(')
            && !str_contains($legacyFinalize, 'runUnlockedMutation('),
        "{$label} legacy batch-finalize endpoint is disabled before any write"
    );
    adjustment_containment_check(
        preg_match('/\bCALL\s+sp_/i', $activeMutationSources) !== 1,
        "{$label} active mutation paths contain no mutating stored-procedure CALL"
    );

    $begin = adjustment_containment_position(
        $importModel,
        '$this->db->beginTransaction()',
        "{$label} bulk model opens one caller-owned transaction"
    );
    $insert = adjustment_containment_position(
        $importModel,
        '$this->insertNormalizedRow(',
        "{$label} bulk model inserts normalized rows"
    );
    $recalculate = adjustment_containment_position(
        $importModel,
        'PayrollAdjustmentGuard::recalculateScope(',
        "{$label} bulk model recalculates within the request"
    );
    // Skip the legacy-compatible wrapper occurrence, if present, and select the
    // invocation within add().
    $recalculate = (int)strpos($importModel, 'PayrollAdjustmentGuard::recalculateScope(', $begin);
    $audit = adjustment_containment_position(
        $importModel,
        'PayrollAdjustmentGuard::writeAuditRows(',
        "{$label} bulk model writes the linked exact-row audit event"
    );
    $commit = adjustment_containment_position(
        $importModel,
        '$this->db->commit()',
        "{$label} bulk model commits only after recalculation and audit"
    );
    adjustment_containment_check(
        $begin < $insert
            && $insert < $recalculate
            && $recalculate < $audit
            && $audit < $commit
            && substr_count($importModel, '$this->db->commit()') === 1
            && substr_count($importModel, '$this->db->beginTransaction()') === 1
            && str_contains($importModel, '$this->db->rollBack()'),
        "{$label} complete workbook inserts, recalculation, audit, and commit have one atomic order"
    );
    adjustment_containment_check(
        str_contains($importModel, 'validateAtomicWorkbookRows($data)')
            && str_contains($importModel, 'MAX_ATOMIC_WORKBOOK_ROWS')
            && str_contains($importModel, '(int)$expectedRows !== count($data)')
            && str_contains($importModel, "'source_row_number'")
            && str_contains($importModel, "'adjustment_id'")
            && str_contains($importModel, "'employee_id'")
            && str_contains($importModel, "'amount'")
            && str_contains($importModel, "'type'")
            && str_contains($importModel, 'lastInsertId()')
            && str_contains($importModel, '$stmt->rowCount() !== 1'),
        "{$label} bulk audit rows identify each exact inserted database row and source row"
    );
    adjustment_containment_check(
        str_contains($importModel, "'{$module['operation']}'")
            && str_contains($importModel, "'row_records'")
            && str_contains($importModel, "'source_filename'")
            && str_contains($importModel, "'change_reason'")
            && str_contains($importModel, "'evidence_reference'"),
        "{$label} bulk audit event links source, business evidence, and exact row payload"
    );

    adjustment_containment_check(
        str_contains($manualController, 'beginTransaction()')
            && str_contains($manualController, 'rollBack()')
            && (
                str_contains($manualController, 'PayrollAdjustmentGuard::recalculateScope(')
                || str_contains($manualModel, 'PayrollAdjustmentGuard::recalculateScope(')
            )
            && str_contains($manualController, 'PayrollAdjustmentGuard::writeAuditRows(')
            && str_contains($manualController, "'row_records'"),
        "{$label} manual create/delete keeps mutation, recalculation, and audit in one transaction"
    );
    adjustment_containment_check(
        str_contains($manualModel, "INSERT INTO {$module['table']}")
            && str_contains($manualModel, 'lastInsertId()')
            && str_contains($manualModel, '$stmt->rowCount() !== 1')
            && str_contains($manualModel, 'FOR UPDATE')
            && (
                str_contains($manualModel, "DELETE FROM {$module['table']}")
                || (
                    str_contains($manualModel, "\"DELETE{\$scopeSql}\"")
                    && str_contains($manualModel, " FROM {$module['table']}")
                )
            )
            && str_contains($manualModel, 'AND employee_id = :employee_id')
            && str_contains($manualModel, 'AND client_name = :client_name')
            && str_contains($manualModel, 'AND start_date = :start_date')
            && str_contains($manualModel, 'AND end_date = :end_date'),
        "{$label} manual rows have exact insert IDs and exact-scope locked delete snapshots"
    );

    adjustment_containment_check(
        substr_count($excelImport, '$.ajax({') === 1
            && str_contains($excelImport, "upload_mode: 'atomic-v1'")
            && str_contains($excelImport, 'expected_row_count:')
            && str_contains($excelImport, '1048576')
            && (
                str_contains($excelImport, '1000')
                || str_contains($excelImport, '1,000')
            )
            && !str_contains($excelImport, 'controller/ImportController.php')
            && preg_match('/data\.slice\s*\(\s*start\s*,/i', $excelImport) !== 1
            && preg_match('/setTimeout\s*\(\s*(?:function|\(\)\s*=>).*pushDataToServer/is', $excelImport) !== 1,
        "{$label} browser sends one bounded complete-workbook request with no per-batch retry loop"
    );
    adjustment_containment_check(
        str_contains($javascript, 'maxWorkbookRows: 1000')
            && str_contains($javascript, 'maxWorkbookBytes: 1048576')
            && str_contains($page, 'controller/PostImportController.php')
            && !str_contains($page, 'controller/ImportController.php')
            && str_contains($page, 'id="import-change-reason"')
            && str_contains($page, 'id="import-evidence-reference"')
            && str_contains($page, 'max="99999999.99"')
            && str_contains($page, 'maxlength="50"')
            && str_contains($page, '20260727-p1atomic'),
        "{$label} page exposes only bounded atomic upload and schema-aligned manual inputs"
    );
}

echo "RESULT: Payroll input adjustment containment passed.\n";
