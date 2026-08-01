<?php

declare(strict_types=1);

require __DIR__ . '/../model/DTR.php';

$checks = 0;

function checkBulkDelete(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class FakeDtrDeleteDatabase
{
    public bool $transactionOpen = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public bool $failAudit = false;
    public int $deleteAttempts = 0;
    public array $auditActions = [];
    public array $auditEvents = [];
    public array $counts = [
        'dtr_upload' => 4,
        'payroll_gross_variables' => 4,
        'payroll_other_additional' => 2,
        'payroll_other_deduction' => 3,
        'payroll_summary' => 4,
    ];

    public function beginTransaction(): bool
    {
        $this->transactionOpen = true;
        return true;
    }

    public function commit(): bool
    {
        $this->transactionOpen = false;
        $this->committed = true;
        return true;
    }

    public function rollBack(): bool
    {
        $this->transactionOpen = false;
        $this->rolledBack = true;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionOpen;
    }

    public function prepare(string $sql): FakeDtrDeleteStatement
    {
        return new FakeDtrDeleteStatement($this, $sql);
    }
}

final class FakeDtrDeleteStatement
{
    private FakeDtrDeleteDatabase $db;
    private string $sql;
    private array $params = [];

    public function __construct(FakeDtrDeleteDatabase $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        $this->params = $params;
        if (str_contains($this->sql, 'INSERT INTO logs')) {
            $this->db->auditActions[] = (string)($params[':log_action'] ?? '');
        }
        if (str_contains($this->sql, 'INSERT INTO dtr_mutation_audit_events')) {
            $this->db->auditEvents[] = $params;
        }
        if (str_contains($this->sql, 'DELETE a FROM')) {
            $this->db->deleteAttempts++;
        }
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if (!str_contains($this->sql, 'SELECT a.*')) {
            return [];
        }
        $table = $this->table();
        $rows = [];
        for ($index = 0; $index < ($this->db->counts[$table] ?? 0); $index++) {
            $rows[] = [
                'employee_id' => 1000 + $index,
                'client_name' => 'FUJIFILM',
                'pay_day' => '2026-07-05',
                'cut_off' => $index % 2 === 0 ? '16-30' : '1-15',
                'source_table' => $table,
                'row_marker' => $index + 1,
            ];
        }
        return $rows;
    }

    public function fetchColumn()
    {
        if (str_contains($this->sql, 'COUNT(DISTINCT a.employee_id)')) {
            return 4;
        }
        return $this->db->counts[$this->table()] ?? 0;
    }

    public function rowCount(): int
    {
        if (str_contains($this->sql, 'INSERT INTO logs')) {
            return $this->db->failAudit ? 0 : 1;
        }
        if (str_contains($this->sql, 'INSERT INTO dtr_mutation_audit_events')) {
            return 1;
        }
        if (str_contains($this->sql, 'DELETE a FROM')) {
            return $this->db->counts[$this->table()] ?? 0;
        }
        return 0;
    }

    private function table(): string
    {
        if (preg_match('/(?:FROM|JOIN)\s+([a-z_]+)\s+a\b/i', $this->sql, $match) === 1) {
            return strtolower($match[1]);
        }
        return '';
    }
}

function makeDtrDeleteModel(FakeDtrDeleteDatabase $db): DTR
{
    $model = new DTR();
    $model->db = $db;
    $model->client = 'FUJIFILM';
    $model->pay_day = '2026-07-05';
    $model->branch = null;
    $model->client_location = null;
    $model->actor = 'payroll.officer';
    $model->deletion_review_secret = str_repeat('r', 64);
    return $model;
}

$database = new FakeDtrDeleteDatabase();
$model = makeDtrDeleteModel($database);
$preflight = $model->getDeleteDTRUploadPreflight();
checkBulkDelete(($preflight['success'] ?? 0) === 1, 'preflight succeeds for a valid unlocked scope');
checkBulkDelete(($preflight['total_rows'] ?? 0) === 17, 'preflight reports the full multi-table impact');
checkBulkDelete(($preflight['employee_count'] ?? 0) === 4, 'preflight reports affected employees');
checkBulkDelete(
    ($preflight['confirmation_phrase'] ?? '') === 'DELETE FUJIFILM 2026-07-05',
    'preflight returns the exact typed confirmation'
);

$model->deletion_confirmation = $preflight['confirmation_phrase'];
$model->deletion_reason = 'Duplicate payroll upload was selected by mistake.';
$model->deletion_evidence = 'Payroll approval PAY-2026-0051';
$model->deletion_review_token = $preflight['review_token'];
$result = $model->deleteDTRUpload();
checkBulkDelete(($result['success'] ?? 0) === 1, 'authorized deletion completes');
checkBulkDelete(($result['total_deleted'] ?? 0) === 17, 'deletion reports affected rows');
checkBulkDelete(($result['audit_recorded'] ?? false) === true, 'deletion confirms durable audit evidence');
checkBulkDelete($database->committed && !$database->rolledBack, 'deletion and audit commit atomically');
checkBulkDelete(
    count($database->auditEvents) === 1
        && ($database->auditEvents[0][':operation'] ?? '') === 'DELETE_BULK'
        && ($database->auditEvents[0][':scope_kind'] ?? '') === 'PAYROLL_SCOPE'
        && array_key_exists(':employee_id', $database->auditEvents[0])
        && $database->auditEvents[0][':employee_id'] === null,
    'audit captures one reconstructable payroll-scope deletion event without an employee sentinel'
);
checkBulkDelete(
    count($database->auditActions) === 1
        && str_contains($database->auditActions[0], 'REF dtr_mutation_audit_events'),
    'bulk deletion writes exactly one legacy pointer'
);
checkBulkDelete(
    count(array_filter(
        $database->auditActions,
        static fn(string $action): bool => strlen($action) > 100
    )) === 0,
    'every audit row fits the legacy VARCHAR(100) contract'
);

$failingDatabase = new FakeDtrDeleteDatabase();
$failingModel = makeDtrDeleteModel($failingDatabase);
$failingPreflight = $failingModel->getDeleteDTRUploadPreflight();
$failingModel->deletion_confirmation = $failingPreflight['confirmation_phrase'];
$failingModel->deletion_reason = 'Duplicate payroll upload was selected by mistake.';
$failingModel->deletion_evidence = 'Payroll approval PAY-2026-0051';
$failingModel->deletion_review_token = $failingPreflight['review_token'];
$failingDatabase->failAudit = true;
$failed = $failingModel->deleteDTRUpload();
checkBulkDelete(($failed['success'] ?? 1) === 0, 'audit failure blocks deletion completion');
checkBulkDelete(
    $failingDatabase->rolledBack && !$failingDatabase->committed,
    'audit failure rolls back every payroll delete'
);

$bypassDatabase = new FakeDtrDeleteDatabase();
$bypassModel = makeDtrDeleteModel($bypassDatabase);
$bypassModel->deletion_confirmation = 'DELETE FUJIFILM 2026-07-05';
$bypassModel->deletion_reason = 'Attempted direct deletion without a review token.';
$bypassModel->deletion_evidence = 'Payroll approval PAY-2026-0051';
$bypass = $bypassModel->deleteDTRUpload();
checkBulkDelete(
    ($bypass['code'] ?? '') === 'dtr_delete_review_required',
    'direct bulk-delete request without server review fails closed'
);
checkBulkDelete(
    $bypassDatabase->rolledBack && $bypassDatabase->deleteAttempts === 0,
    'missing review token rolls back before any delete statement'
);

$driftDatabase = new FakeDtrDeleteDatabase();
$driftModel = makeDtrDeleteModel($driftDatabase);
$driftPreflight = $driftModel->getDeleteDTRUploadPreflight();
$driftModel->deletion_confirmation = $driftPreflight['confirmation_phrase'];
$driftModel->deletion_reason = 'Population changed after review and must be blocked.';
$driftModel->deletion_evidence = 'Payroll approval PAY-2026-0051';
$driftModel->deletion_review_token = $driftPreflight['review_token'];
$driftDatabase->counts['dtr_upload']++;
$drift = $driftModel->deleteDTRUpload();
checkBulkDelete(
    ($drift['code'] ?? '') === 'dtr_delete_count_drift',
    'bulk delete rejects row-count drift after preflight'
);
checkBulkDelete(
    $driftDatabase->rolledBack && $driftDatabase->deleteAttempts === 0,
    'count drift rolls back before any delete statement'
);

$scopeDatabase = new FakeDtrDeleteDatabase();
$scopeModel = makeDtrDeleteModel($scopeDatabase);
$scopeModel->branch = 7;
$scopePreflight = $scopeModel->getDeleteDTRUploadPreflight();
$scopeModel->branch = 8;
$scopeModel->deletion_confirmation = DTRMutationRules::expectedBulkDeleteConfirmation(
    'FUJIFILM',
    '2026-07-05',
    8,
    null
);
$scopeModel->deletion_reason = 'Filter scope changed after review and must be blocked.';
$scopeModel->deletion_evidence = 'Payroll approval PAY-2026-0051';
$scopeModel->deletion_review_token = $scopePreflight['review_token'];
$scopeDrift = $scopeModel->deleteDTRUpload();
checkBulkDelete(
    ($scopeDrift['code'] ?? '') === 'dtr_delete_scope_drift',
    'bulk delete rejects branch or location scope drift'
);
checkBulkDelete(
    $scopeDatabase->rolledBack && $scopeDatabase->deleteAttempts === 0,
    'scope drift rolls back before any delete statement'
);

$controller = (string)file_get_contents(__DIR__ . '/../controller/DTRController.php');
$javascript = (string)file_get_contents(__DIR__ . '/../js/index-13.js');
checkBulkDelete(
    str_contains($controller, "'preflight-delete-dtr-upload'")
        && str_contains($controller, 'getDeleteDTRUploadPreflight'),
    'controller exposes a read-only delete preflight'
);
checkBulkDelete(
    str_contains($javascript, 'preflight-delete-dtr-upload')
        && str_contains($javascript, 'dtr-delete-confirmation')
        && str_contains($javascript, 'dtr-delete-reason')
        && str_contains($javascript, 'dtr-delete-evidence')
        && str_contains($javascript, 'review_token'),
    'browser requires impact review token, typed confirmation, reason, and evidence'
);
checkBulkDelete(
    str_contains($controller, "class='btn btn-sm btn-danger js-delete-record' disabled")
        && str_contains($controller, "aria-disabled='true'")
        && str_contains($controller, "aria-label='Delete payroll records for employee"),
    'employee delete action is disabled when payroll is locked'
);

echo "RESULT: {$checks} DTR bulk-delete containment checks passed.\n";
