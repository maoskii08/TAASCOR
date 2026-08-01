<?php

declare(strict_types=1);

require_once __DIR__ . '/../model/Import.php';

function deduction_atomic_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

final class DeductionAtomicStatement
{
    private DeductionAtomicDatabase $database;
    private string $sql;
    private array $parameters = [];
    private int $affectedRows = 0;

    public function __construct(DeductionAtomicDatabase $database, string $sql)
    {
        $this->database = $database;
        $this->sql = preg_replace('/\s+/', ' ', trim($sql)) ?: trim($sql);
    }

    public function execute(?array $parameters = null): bool
    {
        $this->parameters = $parameters ?? [];
        $this->database->executedSql[] = $this->sql;

        if (str_contains($this->sql, 'INSERT INTO payroll_other_deduction')) {
            $this->database->adjustmentInsertAttempts++;
            if ($this->database->failAdjustmentInsertAt === $this->database->adjustmentInsertAttempts) {
                throw new PDOException('Injected adjustment row failure.');
            }
            $this->database->lastInsertIdentifier++;
            $this->database->pendingAdjustments[] = [
                'id' => $this->database->lastInsertIdentifier,
                'values' => $this->parameters,
            ];
            $this->affectedRows = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE payroll_summary')) {
            $this->database->recalculationAttempts++;
            if ($this->database->failRecalculation) {
                throw new RuntimeException('Injected transaction-safe recalculation failure.');
            }
            $this->database->pendingSummaryUpdates++;
            $this->affectedRows = max(1, $this->countEmployeeParameters(':summary_employee_'));
            return true;
        }

        if (str_contains($this->sql, 'INSERT INTO payroll_adjustment_audit_events')) {
            $this->database->auditEventAttempts++;
            if ($this->database->failAuditEvent) {
                throw new RuntimeException('Injected canonical audit event failure.');
            }
            $this->database->pendingAuditEvents[] = $this->parameters;
            $this->affectedRows = 1;
            return true;
        }

        if (str_contains($this->sql, 'INSERT INTO logs')) {
            $this->database->legacyAuditAttempts++;
            $this->database->pendingLegacyAuditPointers[] = $this->parameters;
            $this->affectedRows = 1;
            return true;
        }

        $this->affectedRows = 0;
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        if (str_contains($this->sql, 'SELECT DISTINCT d.employee_id')) {
            return array_values(array_map('intval', array_slice($this->parameters, 5)));
        }
        if (str_contains($this->sql, 'SELECT employee_id, amount, type_of_deduction')) {
            return [];
        }
        return [];
    }

    public function fetchColumn(int $column = 0)
    {
        if (str_contains($this->sql, 'SELECT COUNT(DISTINCT employee_id) FROM payroll_summary')) {
            return $this->countEmployeeParameters(':coverage_employee_');
        }
        return false;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    private function countEmployeeParameters(string $prefix): int
    {
        return count(array_filter(
            array_keys($this->parameters),
            static fn($key): bool => is_string($key) && str_starts_with($key, $prefix)
        ));
    }
}

final class DeductionAtomicDatabase
{
    public array $executedSql = [];
    public array $pendingAdjustments = [];
    public array $committedAdjustments = [];
    public int $pendingSummaryUpdates = 0;
    public int $committedSummaryUpdates = 0;
    public array $pendingAuditEvents = [];
    public array $committedAuditEvents = [];
    public array $pendingLegacyAuditPointers = [];
    public array $committedLegacyAuditPointers = [];
    public bool $transactionActive = false;
    public bool $failRecalculation = false;
    public bool $failAuditEvent = false;
    public ?int $failAdjustmentInsertAt = null;
    public int $adjustmentInsertAttempts = 0;
    public int $recalculationAttempts = 0;
    public int $auditEventAttempts = 0;
    public int $legacyAuditAttempts = 0;
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;
    public int $lastInsertIdentifier = 2000;

    public function prepare(string $sql): DeductionAtomicStatement
    {
        return new DeductionAtomicStatement($this, $sql);
    }

    public function beginTransaction(): bool
    {
        if ($this->transactionActive) {
            throw new RuntimeException('Nested fake transaction attempted.');
        }
        $this->transactionActive = true;
        $this->beginCount++;
        return true;
    }

    public function commit(): bool
    {
        if (!$this->transactionActive) {
            throw new RuntimeException('Fake commit without an active transaction.');
        }
        $this->committedAdjustments = array_merge($this->committedAdjustments, $this->pendingAdjustments);
        $this->committedSummaryUpdates += $this->pendingSummaryUpdates;
        $this->committedAuditEvents = array_merge($this->committedAuditEvents, $this->pendingAuditEvents);
        $this->committedLegacyAuditPointers = array_merge(
            $this->committedLegacyAuditPointers,
            $this->pendingLegacyAuditPointers
        );
        $this->clearPendingState();
        $this->transactionActive = false;
        $this->commitCount++;
        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->transactionActive) {
            throw new RuntimeException('Fake rollback without an active transaction.');
        }
        $this->clearPendingState();
        $this->transactionActive = false;
        $this->rollbackCount++;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }

    public function lastInsertId(?string $name = null): string
    {
        return (string)$this->lastInsertIdentifier;
    }

    private function clearPendingState(): void
    {
        $this->pendingAdjustments = [];
        $this->pendingSummaryUpdates = 0;
        $this->pendingAuditEvents = [];
        $this->pendingLegacyAuditPointers = [];
    }
}

function deduction_atomic_rows(int $count): array
{
    $rows = [];
    for ($index = 1; $index <= $count; $index++) {
        $rows[] = [
            (string)(7000 + $index),
            number_format(15 + (($index - 1) % 100) + 0.50, 2, '.', ''),
            'Deduction ' . $index,
        ];
    }
    return $rows;
}

function deduction_atomic_import(DeductionAtomicDatabase $database): Import
{
    $import = new Import();
    $import->db = $database;
    $import->payrollDetails = [[
        'Fujifilm Optics Phils. Inc',
        '16-30',
        '2026-07-10',
        '2026-06-16',
        '2026-06-30',
    ]];
    $import->change_reason = 'Approved workbook correction';
    $import->evidence_reference = 'PAYROLL-TICKET-2042';
    $import->source_filename = 'fuji-deduction-2026-06-30.xlsx';
    return $import;
}

function deduction_atomic_map(): array
{
    return [
        'employee id' => 0,
        'amount' => 1,
        'type of deduction' => 2,
    ];
}

function deduction_assert_rolled_back(DeductionAtomicDatabase $database, string $phase): void
{
    deduction_atomic_check(
        $database->beginCount === 1
            && $database->commitCount === 0
            && $database->rollbackCount === 1,
        "{$phase} rolls back once and never commits"
    );
    deduction_atomic_check(
        $database->committedAdjustments === []
            && $database->committedSummaryUpdates === 0
            && $database->committedAuditEvents === []
            && $database->committedLegacyAuditPointers === []
            && $database->pendingAdjustments === []
            && $database->pendingSummaryUpdates === 0
            && $database->pendingAuditEvents === []
            && $database->pendingLegacyAuditPointers === [],
        "{$phase} leaves zero applied adjustments, summary changes, or audit records"
    );
}

$failureRows = deduction_atomic_rows(2);

$rowFailureDatabase = new DeductionAtomicDatabase();
$rowFailureDatabase->failAdjustmentInsertAt = 2;
$rowFailure = deduction_atomic_import($rowFailureDatabase)->add(
    $failureRows,
    deduction_atomic_map(),
    count($failureRows)
);
deduction_atomic_check(($rowFailure['success'] ?? 1) === 0, 'injected row insert failure returns a failed import');
deduction_atomic_check(
    $rowFailureDatabase->adjustmentInsertAttempts === 2
        && $rowFailureDatabase->recalculationAttempts === 0
        && $rowFailureDatabase->auditEventAttempts === 0,
    'row failure stops before recalculation and audit'
);
deduction_assert_rolled_back($rowFailureDatabase, 'row insert failure');

$recalculationFailureDatabase = new DeductionAtomicDatabase();
$recalculationFailureDatabase->failRecalculation = true;
$recalculationFailure = deduction_atomic_import($recalculationFailureDatabase)->add(
    $failureRows,
    deduction_atomic_map(),
    count($failureRows)
);
deduction_atomic_check(
    ($recalculationFailure['success'] ?? 1) === 0
        && ($recalculationFailure['code'] ?? '') === 'payroll_recalculation_failed',
    'injected transaction-safe recalculation failure returns a failed import'
);
deduction_atomic_check(
    $recalculationFailureDatabase->adjustmentInsertAttempts === 2
        && $recalculationFailureDatabase->recalculationAttempts === 1
        && $recalculationFailureDatabase->auditEventAttempts === 0,
    'recalculation failure occurs after all rows and before audit'
);
deduction_assert_rolled_back($recalculationFailureDatabase, 'recalculation failure');

$auditFailureDatabase = new DeductionAtomicDatabase();
$auditFailureDatabase->failAuditEvent = true;
$auditFailure = deduction_atomic_import($auditFailureDatabase)->add(
    $failureRows,
    deduction_atomic_map(),
    count($failureRows)
);
deduction_atomic_check(($auditFailure['success'] ?? 1) === 0, 'injected audit event failure returns a failed import');
deduction_atomic_check(
    $auditFailureDatabase->adjustmentInsertAttempts === 2
        && $auditFailureDatabase->recalculationAttempts === 1
        && $auditFailureDatabase->auditEventAttempts === 1
        && $auditFailureDatabase->legacyAuditAttempts === 0,
    'audit failure occurs after row and summary mutation but before the legacy pointer'
);
deduction_assert_rolled_back($auditFailureDatabase, 'audit event failure');

$overLimitDatabase = new DeductionAtomicDatabase();
$overLimitRows = deduction_atomic_rows(PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS + 1);
$overLimit = deduction_atomic_import($overLimitDatabase)->add(
    $overLimitRows,
    deduction_atomic_map(),
    count($overLimitRows)
);
deduction_atomic_check(
    ($overLimit['success'] ?? 1) === 0
        && ($overLimit['code'] ?? '') === 'workbook_row_limit'
        && $overLimitDatabase->beginCount === 0,
    '1,001 rows are rejected before a transaction begins'
);

$mismatchDatabase = new DeductionAtomicDatabase();
$limitRows = deduction_atomic_rows(PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS);
$mismatch = deduction_atomic_import($mismatchDatabase)->add(
    $limitRows,
    deduction_atomic_map(),
    PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS - 1
);
deduction_atomic_check(
    ($mismatch['success'] ?? 1) === 0
        && ($mismatch['code'] ?? '') === 'incomplete_atomic_workbook'
        && $mismatchDatabase->beginCount === 0,
    'a mismatched expected row count is rejected before a transaction begins'
);

$successDatabase = new DeductionAtomicDatabase();
$success = deduction_atomic_import($successDatabase)->add(
    $limitRows,
    deduction_atomic_map(),
    PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS
);
deduction_atomic_check(
    ($success['success'] ?? 0) === 1
        && ($success['rows_written'] ?? 0) === PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS,
    'the 1,000-row model boundary succeeds as one workbook'
);
deduction_atomic_check(
    $successDatabase->beginCount === 1
        && $successDatabase->commitCount === 1
        && $successDatabase->rollbackCount === 0
        && count($successDatabase->committedAdjustments) === PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS
        && $successDatabase->committedSummaryUpdates === 1,
    'success commits every adjustment and the payroll summary exactly once'
);
deduction_atomic_check(
    $successDatabase->auditEventAttempts === 1
        && $successDatabase->legacyAuditAttempts === 1
        && count($successDatabase->committedAuditEvents) === 1
        && count($successDatabase->committedLegacyAuditPointers) === 1,
    'success commits one canonical audit event and one linked legacy pointer'
);

$auditEvent = $successDatabase->committedAuditEvents[0];
$auditRows = json_decode((string)($auditEvent[':rows_payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
$legacyPointer = (string)($successDatabase->committedLegacyAuditPointers[0][1] ?? '');
deduction_atomic_check(
    ($auditEvent[':operation'] ?? '') === 'DEDUCTION_BULK'
        && ($auditEvent[':adjustment_kind'] ?? '') === 'deduction'
        && ($auditEvent[':row_count'] ?? 0) === PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS
        && ($auditEvent[':source_filename'] ?? '') === 'fuji-deduction-2026-06-30.xlsx'
        && count($auditRows) === PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS
        && ($auditRows[0]['employee_id'] ?? 0) === 7001
        && ($auditRows[999]['employee_id'] ?? 0) === 8000
        && ($auditRows[999]['source_row_number'] ?? 0) === 1001
        && ($success['audit_event_id'] ?? '') === ($auditEvent[':event_uid'] ?? '')
        && str_contains($legacyPointer, '|' . ($auditEvent[':event_uid'] ?? '') . '|DEDUCTION_BULK|rows=1000'),
    'the committed audit exactly reconstructs and links the complete workbook'
);

echo "RESULT: Other Deduction atomic workbook importer passed.\n";
