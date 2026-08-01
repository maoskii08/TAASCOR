<?php

declare(strict_types=1);

require __DIR__ . '/../model/DTR.php';

$checks = 0;

function checkScopedMutation(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class FakeScopedMutationDatabase
{
    public bool $transactionOpen = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public bool $scopeExists = true;
    public bool $failAudit = false;
    public int $deleteAttempts = 0;
    public int $updateAttempts = 0;
    public array $queries = [];
    public array $auditActions = [];
    public array $auditEvents = [];
    public array $counts = [
        'dtr_upload' => 1,
        'payroll_gross_variables' => 1,
        'payroll_other_additional' => 1,
        'payroll_other_deduction' => 1,
        'payroll_summary' => 1,
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

    public function prepare(string $sql): FakeScopedMutationStatement
    {
        $this->queries[] = $sql;
        return new FakeScopedMutationStatement($this, $sql);
    }
}

final class FakeScopedMutationStatement
{
    private FakeScopedMutationDatabase $db;
    private string $sql;

    public function __construct(FakeScopedMutationDatabase $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        if (str_contains($this->sql, 'UPDATE dtr_upload')) {
            $this->db->updateAttempts++;
        }
        if (str_contains($this->sql, 'DELETE FROM')) {
            $this->db->deleteAttempts++;
        }
        if (str_contains($this->sql, 'INSERT INTO logs')) {
            $this->db->auditActions[] = (string)($params[':log_action'] ?? '');
        }
        if (str_contains($this->sql, 'INSERT INTO dtr_mutation_audit_events')) {
            $this->db->auditEvents[] = $params;
        }
        return true;
    }

    public function bindParam(string $name, &$value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if (str_contains($this->sql, 'SELECT d.*')) {
            return $this->db->scopeExists
                ? [[
                    'employee_id' => 42,
                    'client_name' => 'FUJIFILM',
                    'pay_day' => '2026-07-05',
                    'cut_off' => '16-30',
                    'start_date' => '2026-06-16',
                    'end_date' => '2026-06-30',
                    'daily_salary' => '1250.50',
                    'daily_worked' => '11.00',
                ]]
                : [];
        }
        if (str_contains($this->sql, 'SELECT d.employee_id')) {
            return $this->db->scopeExists ? [42] : [];
        }
        if (str_contains($this->sql, 'SELECT a.*')) {
            $table = $this->table();
            $rows = [];
            for ($index = 0; $index < ($this->db->counts[$table] ?? 0); $index++) {
                $rows[] = [
                    'employee_id' => 42,
                    'client_name' => 'FUJIFILM',
                    'pay_day' => '2026-07-05',
                    'cut_off' => '16-30',
                    'source_table' => $table,
                    'row_marker' => $index + 1,
                ];
            }
            return $rows;
        }
        return [];
    }

    public function fetchColumn(int $column = 0): int
    {
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
        if (str_contains($this->sql, 'UPDATE dtr_upload')) {
            return 1;
        }
        if (str_contains($this->sql, 'DELETE FROM')) {
            return $this->db->counts[$this->table()] ?? 0;
        }
        return 0;
    }

    private function table(): string
    {
        if (preg_match('/(?:DELETE\s+FROM|FROM)\s+([a-z_]+)(?:\s+a)?/i', $this->sql, $match) === 1) {
            return strtolower($match[1]);
        }
        return '';
    }
}

function makeScopedMutationModel(FakeScopedMutationDatabase $db): DTR
{
    $model = new DTR();
    $model->db = $db;
    $model->employee_ident = 42;
    $model->client = 'FUJIFILM';
    $model->pay_day = '2026-07-05';
    $model->cut_off = '16-30';
    $model->start_date = '2026-06-16';
    $model->end_date = '2026-06-30';
    $model->actor = 'payroll.officer';
    foreach ([
        'daily_salary',
        'days_worked',
        'absent',
        'lates',
        'undertime',
        'vacation_leave',
        'sick_leave',
        'overtime',
        'night_diff',
        'night_diff_ot',
        'regular_holiday',
        'regular_holiday_ot',
        'regular_holiday_night_diff',
        'regular_holiday_nd_ot',
        'special_holiday',
        'special_holiday_ot',
        'special_holiday_night_diff',
        'special_holiday_nd_ot',
        'rest_day',
        'rest_day_ot',
        'rest_day_night_diff',
        'rest_day_nd_ot',
        'rd_regular_holiday',
        'rd_regular_holiday_ot',
        'rd_regular_holiday_night_diff',
        'rd_regular_holiday_nd_ot',
        'rd_special_holiday',
        'rd_special_holiday_ot',
        'rd_special_holiday_night_diff',
        'rd_special_holiday_nd_ot',
    ] as $field) {
        $model->{$field} = 0;
    }
    $model->daily_salary = 1250.50;
    $model->days_worked = 11;
    return $model;
}

$missingScopeDb = new FakeScopedMutationDatabase();
$missingScopeDb->scopeExists = false;
$missingScopeModel = makeScopedMutationModel($missingScopeDb);
$missingScope = $missingScopeModel->validateExistingDTRUpdateScope(true);
checkScopedMutation(
    ($missingScope['code'] ?? '') === 'dtr_update_scope_not_found',
    'manual update rejects an employee outside the exact client and payroll tuple'
);
checkScopedMutation($missingScopeDb->updateAttempts === 0, 'scope rejection performs no DTR update');

$exactScopeDb = new FakeScopedMutationDatabase();
$exactScopeModel = makeScopedMutationModel($exactScopeDb);
$exactScope = $exactScopeModel->validateExistingDTRUpdateScope(true);
checkScopedMutation(($exactScope['success'] ?? 0) === 1, 'existing exact-scope DTR row is accepted');
checkScopedMutation(
    count(array_filter(
        $exactScopeDb->queries,
        static fn(string $sql): bool => str_contains($sql, 'FOR UPDATE')
            && str_contains($sql, 'e.client_id = c.client_id')
            && str_contains($sql, 'd.cut_off = :cut_off')
            && str_contains($sql, 'd.start_date = :start_date')
            && str_contains($sql, 'd.end_date = :end_date')
    )) === 1,
    'manual update locks and verifies employee-client, cutoff, and canonical period'
);
$updated = $exactScopeModel->updateDTR();
checkScopedMutation(($updated['success'] ?? 0) === 1, 'verified DTR row can be updated');
checkScopedMutation($exactScopeDb->updateAttempts === 1, 'manual edit uses one UPDATE statement');

$bypassDb = new FakeScopedMutationDatabase();
$bypassModel = makeScopedMutationModel($bypassDb);
$bypass = $bypassModel->deleteEmployeeDTR();
checkScopedMutation(
    ($bypass['code'] ?? '') === 'dtr_employee_delete_confirmation_invalid',
    'direct employee-delete request without confirmation fails closed'
);
checkScopedMutation(
    !$bypassDb->transactionOpen && $bypassDb->deleteAttempts === 0,
    'employee-delete bypass reaches no transaction or delete statement'
);

$wrongScopeDb = new FakeScopedMutationDatabase();
$wrongScopeDb->scopeExists = false;
$wrongScopeModel = makeScopedMutationModel($wrongScopeDb);
$wrongScopeModel->deletion_confirmation = DTRMutationRules::expectedEmployeeDeleteConfirmation(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30'
);
$wrongScopeModel->deletion_reason = 'The duplicate employee payroll row must be removed.';
$wrongScopeModel->deletion_evidence = 'Payroll approval PAY-2026-0050';
$wrongScope = $wrongScopeModel->deleteEmployeeDTR();
checkScopedMutation(
    ($wrongScope['code'] ?? '') === 'dtr_employee_delete_scope_not_found',
    'employee deletion rejects a stale or cross-client exact scope'
);
checkScopedMutation(
    $wrongScopeDb->rolledBack && $wrongScopeDb->deleteAttempts === 0,
    'employee scope mismatch rolls back before deletion'
);

$deleteDb = new FakeScopedMutationDatabase();
$deleteModel = makeScopedMutationModel($deleteDb);
$deleteModel->deletion_confirmation = DTRMutationRules::expectedEmployeeDeleteConfirmation(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30'
);
$deleteModel->deletion_reason = 'The duplicate employee payroll row must be removed.';
$deleteModel->deletion_evidence = 'Payroll approval PAY-2026-0050';
$deleted = $deleteModel->deleteEmployeeDTR();
checkScopedMutation(($deleted['success'] ?? 0) === 1, 'authorized employee deletion completes');
checkScopedMutation(
    $deleteDb->committed && !$deleteDb->rolledBack && $deleteDb->deleteAttempts === 5,
    'all five employee payroll deletes commit in one transaction'
);
checkScopedMutation(($deleted['audit_recorded'] ?? false) === true, 'employee deletion confirms durable audit');
checkScopedMutation(
    count($deleteDb->auditEvents) === 1
        && ($deleteDb->auditEvents[0][':operation'] ?? '') === 'DELETE_EMPLOYEE'
        && ($deleteDb->auditEvents[0][':scope_kind'] ?? '') === 'EMPLOYEE'
        && ($deleteDb->auditEvents[0][':evidence_reference'] ?? '') === 'Payroll approval PAY-2026-0050',
    'employee deletion writes one reconstructable scoped event with evidence'
);
checkScopedMutation(
    count($deleteDb->auditActions) === 1
        && str_contains($deleteDb->auditActions[0], 'REF dtr_mutation_audit_events'),
    'employee deletion writes exactly one legacy pointer'
);
checkScopedMutation(
    count(array_filter(
        $deleteDb->auditActions,
        static fn(string $action): bool => strlen($action) > 100
    )) === 0,
    'employee deletion audit rows fit the legacy log contract'
);

$auditFailureDb = new FakeScopedMutationDatabase();
$auditFailureDb->failAudit = true;
$auditFailureModel = makeScopedMutationModel($auditFailureDb);
$auditFailureModel->deletion_confirmation = DTRMutationRules::expectedEmployeeDeleteConfirmation(
    42,
    'FUJIFILM',
    '2026-07-05',
    '16-30'
);
$auditFailureModel->deletion_reason = 'The duplicate employee payroll row must be removed.';
$auditFailureModel->deletion_evidence = 'Payroll approval PAY-2026-0050';
$auditFailure = $auditFailureModel->deleteEmployeeDTR();
checkScopedMutation(($auditFailure['success'] ?? 1) === 0, 'employee audit failure blocks completion');
checkScopedMutation(
    $auditFailureDb->rolledBack && !$auditFailureDb->committed,
    'employee audit failure rolls back all five deletes'
);

$controller = (string)file_get_contents(__DIR__ . '/../controller/DTRController.php');
$javascript = (string)file_get_contents(__DIR__ . '/../js/index-13.js');
$modelSource = (string)file_get_contents(__DIR__ . '/../model/DTR.php');
$updateRoute = substr(
    $controller,
    (int)strpos($controller, "}else if(\$_POST['request'] == 'update-dtr'){"),
    9000
);
checkScopedMutation(
    strpos($updateRoute, 'validateExistingDTRUpdateScope(true)')
        < strpos($updateRoute, '$model->updateDTR()'),
    'controller verifies exact DTR scope before manual mutation'
);
checkScopedMutation(
    !str_contains($modelSource, 'INSERT INTO dtr_upload (')
        && str_contains($modelSource, 'UPDATE dtr_upload'),
    'manual DTR edit cannot upsert a new employee payroll row'
);
checkScopedMutation(
    str_contains($controller, "\$model->cut_off = \$_POST['cut_off'] ?? '';")
        && str_contains($controller, "\$model->deletion_confirmation = \$_POST['deletion_confirmation'] ?? '';")
        && str_contains($controller, "\$model->deletion_reason = \$_POST['deletion_reason'] ?? '';")
        && str_contains($controller, "\$model->deletion_evidence = \$_POST['deletion_evidence'] ?? '';"),
    'employee-delete controller requires exact cutoff, confirmation, reason, and evidence inputs'
);
checkScopedMutation(
    str_contains($javascript, 'dtr-employee-delete-reason')
        && str_contains($javascript, 'dtr-employee-delete-evidence')
        && str_contains($javascript, 'dtr-employee-delete-confirmation')
        && str_contains($javascript, 'deletion_confirmation')
        && str_contains($javascript, 'deletion_reason')
        && str_contains($javascript, 'deletion_evidence'),
    'browser reviews exact employee scope and submits typed confirmation, reason, and evidence'
);
checkScopedMutation(
    str_contains($controller, "class='btn btn-sm btn-primary js-dtr-update'")
        && str_contains($controller, "class='btn btn-sm btn-danger js-remove-benefits'")
        && str_contains($controller, "class='btn btn-sm btn-danger js-delete-record'")
        && str_contains($controller, "aria-label='Edit DTR for employee")
        && !str_contains($controller, "id='updateBtn'")
        && !str_contains($controller, "id='deleteRecord'"),
    'DTR row actions use unique class hooks and accessible names instead of duplicate IDs'
);

echo "RESULT: {$checks} scoped DTR mutation containment checks passed.\n";
