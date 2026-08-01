<?php

declare(strict_types=1);

require __DIR__ . '/../model/DTR.php';
require __DIR__ . '/../../audit-log/model/AuditLog.php';

$checks = 0;

function checkDestructiveAudit(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class FakeDestructiveAuditDatabase
{
    public bool $transactionOpen = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public bool $failDedicatedAudit = false;
    public bool $failPointer = false;
    public ?string $failSnapshotTable = null;
    public ?string $mismatchedDeleteTable = null;
    public array $countOverrides = [];
    public int $deleteAttempts = 0;
    public array $queries = [];
    public array $auditEvents = [];
    public array $legacyPointers = [];
    public array $rows = [
        'dtr_upload' => [
            [
                'employee_id' => 42,
                'client_name' => 'FUJIFILM',
                'pay_day' => '2026-07-05',
                'cut_off' => '16-30',
                'row_marker' => 2,
            ],
            [
                'employee_id' => 42,
                'client_name' => 'FUJIFILM',
                'pay_day' => '2026-07-05',
                'cut_off' => '16-30',
                'row_marker' => 1,
            ],
        ],
        'payroll_gross_variables' => [
            [
                'employee_id' => 42,
                'client_name' => 'FUJIFILM',
                'pay_day' => '2026-07-05',
                'cut_off' => '16-30',
                'gross_variable' => '1250.50',
            ],
        ],
        'payroll_other_additional' => [],
        'payroll_other_deduction' => [
            [
                'employee_id' => 42,
                'client_name' => 'FUJIFILM',
                'pay_day' => '2026-07-05',
                'cut_off' => '16-30',
                'deduction' => '75.25',
            ],
        ],
        'payroll_summary' => [
            [
                'employee_id' => 42,
                'client_name' => 'FUJIFILM',
                'pay_day' => '2026-07-05',
                'cut_off' => '16-30',
                'net_pay' => '13679.75',
            ],
        ],
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

    public function prepare(string $sql): FakeDestructiveAuditStatement
    {
        $this->queries[] = $sql;
        return new FakeDestructiveAuditStatement($this, $sql);
    }
}

final class FakeDestructiveAuditStatement
{
    private FakeDestructiveAuditDatabase $db;
    private string $sql;

    public function __construct(FakeDestructiveAuditDatabase $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        $table = $this->table();
        if (
            str_contains($this->sql, 'SELECT a.*')
            && $this->db->failSnapshotTable === $table
        ) {
            throw new RuntimeException('Injected destructive snapshot failure.');
        }
        if (str_contains($this->sql, 'DELETE FROM')) {
            $this->db->deleteAttempts++;
        }
        if (str_contains($this->sql, 'INSERT INTO dtr_mutation_audit_events')) {
            $this->db->auditEvents[] = $params;
        }
        if (str_contains($this->sql, 'INSERT INTO logs')) {
            $this->db->legacyPointers[] = (string)($params[':log_action'] ?? '');
        }
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if (str_contains($this->sql, 'SELECT d.employee_id')) {
            return [42];
        }
        if (str_contains($this->sql, 'SELECT a.*')) {
            return $this->db->rows[$this->table()] ?? [];
        }
        return [];
    }

    public function fetchColumn(int $column = 0): int
    {
        $table = $this->table();
        return $this->db->countOverrides[$table]
            ?? count($this->db->rows[$table] ?? []);
    }

    public function rowCount(): int
    {
        if (str_contains($this->sql, 'INSERT INTO dtr_mutation_audit_events')) {
            return $this->db->failDedicatedAudit ? 0 : 1;
        }
        if (str_contains($this->sql, 'INSERT INTO logs')) {
            return $this->db->failPointer ? 0 : 1;
        }
        if (str_contains($this->sql, 'DELETE FROM')) {
            $table = $this->table();
            $count = count($this->db->rows[$table] ?? []);
            return $this->db->mismatchedDeleteTable === $table
                ? max(0, $count - 1)
                : $count;
        }
        return 0;
    }

    private function table(): string
    {
        if (
            preg_match(
                '/(?:DELETE\s+FROM|FROM)\s+([a-z_]+)(?:\s+a)?/i',
                $this->sql,
                $match
            ) === 1
        ) {
            return strtolower($match[1]);
        }
        return '';
    }
}

function destructiveAuditModel(FakeDestructiveAuditDatabase $db): DTR
{
    $model = new DTR();
    $model->db = $db;
    $model->employee_ident = 42;
    $model->client = 'FUJIFILM';
    $model->pay_day = '2026-07-05';
    $model->cut_off = '16-30';
    $model->actor = 'payroll.officer';
    $model->deletion_confirmation = DTRMutationRules::expectedEmployeeDeleteConfirmation(
        42,
        'FUJIFILM',
        '2026-07-05',
        '16-30'
    );
    $model->deletion_reason = 'Duplicate employee payroll data was approved for deletion.';
    $model->deletion_evidence = 'Payroll approval PAY-2026-0052';
    return $model;
}

$successDb = new FakeDestructiveAuditDatabase();
$success = destructiveAuditModel($successDb)->deleteEmployeeDTR();
checkDestructiveAudit(
    ($success['success'] ?? 0) === 1
        && preg_match('/^DTRM-[A-F0-9]{32}$/', (string)($success['audit_event'] ?? '')) === 1,
    'successful destructive mutation returns a stable-format audit event ID'
);
checkDestructiveAudit(
    $successDb->committed && !$successDb->rolledBack && $successDb->deleteAttempts === 5,
    'all five table deletes and their evidence commit atomically'
);
checkDestructiveAudit(
    count(array_filter(
        $successDb->queries,
        static fn(string $sql): bool => str_contains($sql, 'SELECT a.*')
            && str_contains($sql, 'FOR UPDATE')
            && str_contains($sql, 'LIMIT ')
    )) === 5,
    'exact pre-delete rows are captured from all five tables under bounded row locks'
);
checkDestructiveAudit(
    count($successDb->auditEvents) === 1
        && count($successDb->legacyPointers) === 1
        && strlen($successDb->legacyPointers[0]) <= 100,
    'one reconstructable event and one compact legacy pointer are written'
);

$event = $successDb->auditEvents[0];
$before = json_decode((string)$event[':before_payload'], true, 512, JSON_THROW_ON_ERROR);
$after = json_decode((string)$event[':after_payload'], true, 512, JSON_THROW_ON_ERROR);
$scope = json_decode((string)$event[':scope_payload'], true, 512, JSON_THROW_ON_ERROR);
checkDestructiveAudit(
    array_keys($before) === [
        'dtr_upload',
        'payroll_gross_variables',
        'payroll_other_additional',
        'payroll_other_deduction',
        'payroll_summary',
    ]
        && ($before['dtr_upload'][0]['row_marker'] ?? 0) === 1
        && ($before['dtr_upload'][1]['row_marker'] ?? 0) === 2,
    'canonical before evidence preserves every table and deterministically sorted exact rows'
);
checkDestructiveAudit(
    ($after['deleted'] ?? false) === true
        && ($after['tombstone'] ?? false) === true
        && count(array_filter(
            $after['rows'] ?? [],
            static fn(array $rows): bool => $rows !== []
        )) === 0,
    'after evidence is an explicit tombstone with empty rows for every table'
);
checkDestructiveAudit(
    hash_equals(hash('sha256', (string)$event[':before_payload']), (string)$event[':before_hash'])
        && hash_equals(hash('sha256', (string)$event[':after_payload']), (string)$event[':after_hash'])
        && hash_equals(hash('sha256', (string)$event[':scope_payload']), (string)$event[':scope_hash']),
    'before, after, and exact scope payload hashes verify'
);
checkDestructiveAudit(
    ($event[':operation'] ?? '') === 'DELETE_EMPLOYEE'
        && ($event[':scope_kind'] ?? '') === 'EMPLOYEE'
        && ($scope['employee_id'] ?? 0) === 42
        && ($scope['client_name'] ?? '') === 'FUJIFILM'
        && ($scope['pay_day'] ?? '') === '2026-07-05'
        && ($scope['cut_off'] ?? '') === '16-30'
        && ($event[':evidence_reference'] ?? '') === 'Payroll approval PAY-2026-0052',
    'destructive event binds actor-reviewed evidence to the exact employee payroll scope'
);

$snapshotFailureDb = new FakeDestructiveAuditDatabase();
$snapshotFailureDb->failSnapshotTable = 'payroll_other_additional';
$snapshotFailure = destructiveAuditModel($snapshotFailureDb)->deleteEmployeeDTR();
checkDestructiveAudit(
    ($snapshotFailure['success'] ?? 1) === 0
        && $snapshotFailureDb->rolledBack
        && !$snapshotFailureDb->committed
        && $snapshotFailureDb->deleteAttempts === 0,
    'snapshot failure rolls back before any destructive statement'
);

$dedicatedFailureDb = new FakeDestructiveAuditDatabase();
$dedicatedFailureDb->failDedicatedAudit = true;
$dedicatedFailure = destructiveAuditModel($dedicatedFailureDb)->deleteEmployeeDTR();
checkDestructiveAudit(
    ($dedicatedFailure['success'] ?? 1) === 0
        && $dedicatedFailureDb->rolledBack
        && !$dedicatedFailureDb->committed
        && $dedicatedFailureDb->deleteAttempts === 5,
    'dedicated evidence persistence failure rolls back all five deletes'
);

$pointerFailureDb = new FakeDestructiveAuditDatabase();
$pointerFailureDb->failPointer = true;
$pointerFailure = destructiveAuditModel($pointerFailureDb)->deleteEmployeeDTR();
checkDestructiveAudit(
    ($pointerFailure['success'] ?? 1) === 0
        && $pointerFailureDb->rolledBack
        && !$pointerFailureDb->committed
        && $pointerFailureDb->deleteAttempts === 5,
    'legacy pointer persistence failure also rolls back all five deletes'
);

$countMismatchDb = new FakeDestructiveAuditDatabase();
$countMismatchDb->mismatchedDeleteTable = 'payroll_summary';
$countMismatch = destructiveAuditModel($countMismatchDb)->deleteEmployeeDTR();
checkDestructiveAudit(
    ($countMismatch['success'] ?? 1) === 0
        && $countMismatchDb->rolledBack
        && !$countMismatchDb->committed
        && count($countMismatchDb->auditEvents) === 0,
    'delete-count drift rolls back before incomplete evidence can be persisted'
);

$oversizedCountDb = new FakeDestructiveAuditDatabase();
$oversizedCountDb->countOverrides['dtr_upload'] = 25001;
$oversizedCount = destructiveAuditModel($oversizedCountDb)->deleteEmployeeDTR();
checkDestructiveAudit(
    ($oversizedCount['success'] ?? 1) === 0
        && $oversizedCountDb->rolledBack
        && !$oversizedCountDb->committed
        && $oversizedCountDb->deleteAttempts === 0
        && count(array_filter(
            $oversizedCountDb->queries,
            static fn(string $sql): bool => str_contains($sql, 'SELECT a.*')
        )) === 0,
    'oversized destructive scopes fail from exact counts before row capture or deletion'
);

$oversizedEvidenceDb = new FakeDestructiveAuditDatabase();
$oversizedEvidenceDb->rows['dtr_upload'] = [[
    'employee_id' => 42,
    'client_name' => 'FUJIFILM',
    'pay_day' => '2026-07-05',
    'cut_off' => '16-30',
    'evidence_blob' => str_repeat('x', 524288),
]];
$oversizedEvidence = destructiveAuditModel($oversizedEvidenceDb)->deleteEmployeeDTR();
checkDestructiveAudit(
    ($oversizedEvidence['success'] ?? 1) === 0
        && $oversizedEvidenceDb->rolledBack
        && !$oversizedEvidenceDb->committed
        && $oversizedEvidenceDb->deleteAttempts === 0,
    'evidence above the in-app reader ceiling rolls back before deletion'
);

$writerPayloadLimit = (new ReflectionClass(DTR::class))
    ->getReflectionConstant('DELETE_SNAPSHOT_MAX_BYTES')
    ->getValue();
$readerPayloadLimit = (new ReflectionClass(AuditLog::class))
    ->getReflectionConstant('DTR_MAX_PAYLOAD_BYTES')
    ->getValue();
checkDestructiveAudit(
    $writerPayloadLimit === $readerPayloadLimit && $writerPayloadLimit === 524288,
    'writer and in-app audit reader enforce the same reconstructable payload ceiling'
);

$modelSource = (string)file_get_contents(__DIR__ . '/../model/DTR.php');
checkDestructiveAudit(
    str_contains($modelSource, 'DELETE_SNAPSHOT_MAX_ROWS')
        && str_contains($modelSource, 'DELETE_SNAPSHOT_MAX_BYTES')
        && !str_contains($modelSource, 'writeBulkDeleteAudit')
        && !str_contains($modelSource, 'writeEmployeeDeleteAudit'),
    'destructive snapshots fail closed at governed bounds and legacy multi-row writers are removed'
);

echo "RESULT: {$checks} destructive DTR mutation-audit checks passed.\n";
