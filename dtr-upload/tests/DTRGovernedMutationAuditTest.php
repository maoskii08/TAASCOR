<?php

declare(strict_types=1);

require __DIR__ . '/../model/DTR.php';

$checks = 0;

function checkGovernedMutation(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class FakeGovernedMutationDatabase
{
    public bool $transactionOpen = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public bool $scopeExists = true;
    public bool $updated = false;
    public bool $failAudit = false;
    public bool $failDedicatedAudit = false;
    public int $beginAttempts = 0;
    public int $updateAttempts = 0;
    public array $queries = [];
    public array $auditActions = [];
    public array $auditEvents = [];

    public function beginTransaction(): bool
    {
        $this->beginAttempts++;
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

    public function prepare(string $sql): FakeGovernedMutationStatement
    {
        $this->queries[] = $sql;
        return new FakeGovernedMutationStatement($this, $sql);
    }
}

final class FakeGovernedMutationStatement
{
    private FakeGovernedMutationDatabase $db;
    private string $sql;

    public function __construct(FakeGovernedMutationDatabase $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        if (str_contains($this->sql, 'UPDATE payroll_summary')) {
            $this->db->updateAttempts++;
            $this->db->updated = true;
        }
        if (str_contains($this->sql, 'INSERT INTO logs')) {
            $this->db->auditActions[] = (string)($params[':log_action'] ?? '');
        }
        if (str_contains($this->sql, 'INSERT INTO dtr_mutation_audit_events')) {
            $this->db->auditEvents[] = $params;
        }
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if (str_contains($this->sql, 'SELECT d.*')) {
            if (!$this->db->scopeExists) {
                return [];
            }
            return [[
                'employee_id' => 42,
                'client_name' => 'FUJIFILM',
                'pay_day' => '2026-07-05',
                'cut_off' => '16-30',
                'start_date' => '2026-06-16',
                'end_date' => '2026-06-30',
                'daily_salary' => '1250.50',
                'daily_worked' => $this->db->updated ? '12.00' : '11.00',
            ]];
        }
        if (!str_contains($this->sql, 'SELECT') || !str_contains($this->sql, 'FROM payroll_summary')) {
            return [];
        }
        if (!$this->db->scopeExists) {
            return [];
        }
        $removed = $this->db->updated;
        return [[
            'employee_id' => 42,
            'client_name' => 'FUJIFILM',
            'pay_day' => '2026-07-05',
            'cut_off' => '16-30',
            'employee_sss' => $removed ? '0.00' : '250.00',
            'employee_philhealth' => $removed ? '0.00' : '125.00',
            'employee_pagibig' => $removed ? '0.00' : '100.00',
            'employer_sss' => $removed ? '0.00' : '500.00',
            'employer_philhealth' => $removed ? '0.00' : '125.00',
            'employer_pagibig' => $removed ? '0.00' : '100.00',
            'employee_sss_mpf' => '0.00',
            'employer_sss_mpf' => '0.00',
            'employer_sss_ec' => $removed ? '0.00' : '10.00',
            'taxable_income' => $removed ? '12500.00' : '12125.00',
            'employee_tax' => $removed ? '312.45' : '256.20',
            'net_pay' => $removed ? '11500.00' : '11000.00',
        ]];
    }

    public function rowCount(): int
    {
        if (str_contains($this->sql, 'INSERT INTO dtr_mutation_audit_events')) {
            return $this->db->failDedicatedAudit ? 0 : 1;
        }
        if (str_contains($this->sql, 'INSERT INTO logs')) {
            return $this->db->failAudit ? 0 : 1;
        }
        return str_contains($this->sql, 'UPDATE payroll_summary') ? 1 : 0;
    }
}

function governedModel(FakeGovernedMutationDatabase $db): DTR
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
    $model->change_reason = 'Approved correction of a duplicate statutory deduction.';
    $model->change_evidence = 'Payroll approval PAY-2026-0043';
    $model->benefits_confirmation = DTRMutationRules::expectedGovernmentBenefitsConfirmation(
        42,
        'FUJIFILM',
        '2026-07-05',
        '16-30'
    );
    return $model;
}

$bypassDb = new FakeGovernedMutationDatabase();
$bypassModel = governedModel($bypassDb);
$bypassModel->benefits_confirmation = '';
$bypass = $bypassModel->removeGovtBenefits();
checkGovernedMutation(
    ($bypass['code'] ?? '') === 'dtr_benefits_confirmation_invalid',
    'direct benefits-removal request without exact confirmation fails closed'
);
checkGovernedMutation(
    $bypassDb->beginAttempts === 0 && $bypassDb->updateAttempts === 0,
    'authorization failure reaches no transaction or payroll update'
);

$scopeDb = new FakeGovernedMutationDatabase();
$scopeDb->scopeExists = false;
$scopeResult = governedModel($scopeDb)->removeGovtBenefits();
checkGovernedMutation(
    ($scopeResult['code'] ?? '') === 'dtr_benefits_scope_not_found',
    'stale or cross-client benefits scope is rejected'
);
checkGovernedMutation(
    $scopeDb->rolledBack && $scopeDb->updateAttempts === 0,
    'scope rejection rolls back before payroll mutation'
);

$successDb = new FakeGovernedMutationDatabase();
$success = governedModel($successDb)->removeGovtBenefits();
checkGovernedMutation(
    ($success['success'] ?? 0) === 1
        && ($success['audit_recorded'] ?? false) === true
        && preg_match('/^DTRM-[A-F0-9]{32}$/', (string)($success['audit_event'] ?? '')) === 1,
    'authorized government-benefits change returns a stable durable audit event ID'
);
checkGovernedMutation(
    $successDb->committed && !$successDb->rolledBack && $successDb->updateAttempts === 4,
    'four exact-scope payroll recalculations commit together'
);
checkGovernedMutation(
    count(array_filter(
        $successDb->queries,
        static fn(string $sql): bool => str_contains($sql, 'UPDATE payroll_summary')
            && str_contains($sql, 'AND cut_off = :cut_off')
    )) === 4,
    'every benefits update is constrained by employee, client, pay date, and cutoff'
);
checkGovernedMutation(
    count($successDb->auditEvents) === 1
        && ($successDb->auditEvents[0][':change_reason'] ?? '') === 'Approved correction of a duplicate statutory deduction.'
        && ($successDb->auditEvents[0][':evidence_reference'] ?? '') === 'Payroll approval PAY-2026-0043'
        && str_contains((string)($successDb->auditEvents[0][':before_payload'] ?? ''), 'employee_sss')
        && str_contains((string)($successDb->auditEvents[0][':after_payload'] ?? ''), 'employee_sss')
        && hash_equals(
            hash('sha256', (string)$successDb->auditEvents[0][':before_payload']),
            (string)$successDb->auditEvents[0][':before_hash']
        )
        && hash_equals(
            hash('sha256', (string)$successDb->auditEvents[0][':after_payload']),
            (string)$successDb->auditEvents[0][':after_hash']
        )
        && hash_equals(
            hash('sha256', (string)$successDb->auditEvents[0][':scope_payload']),
            (string)$successDb->auditEvents[0][':scope_hash']
        ),
    'dedicated audit retains full statutory before/after/scope values, hashes, reason, and evidence'
);
checkGovernedMutation(
    count($successDb->auditActions) === 1
        && strlen($successDb->auditActions[0]) <= 100
        && str_contains($successDb->auditActions[0], 'REF dtr_mutation_audit_events'),
    'legacy logs receives exactly one compact pointer to reconstructable evidence'
);

$auditFailureDb = new FakeGovernedMutationDatabase();
$auditFailureDb->failAudit = true;
$auditFailure = governedModel($auditFailureDb)->removeGovtBenefits();
checkGovernedMutation(
    ($auditFailure['success'] ?? 1) === 0
        && $auditFailureDb->rolledBack
        && !$auditFailureDb->committed,
    'audit failure rolls back every government-benefits update'
);

$dedicatedFailureDb = new FakeGovernedMutationDatabase();
$dedicatedFailureDb->failDedicatedAudit = true;
$dedicatedFailure = governedModel($dedicatedFailureDb)->removeGovtBenefits();
checkGovernedMutation(
    ($dedicatedFailure['success'] ?? 1) === 0
        && $dedicatedFailureDb->rolledBack
        && !$dedicatedFailureDb->committed,
    'missing or unavailable dedicated audit storage rolls back the payroll change'
);

$manualAuditDb = new FakeGovernedMutationDatabase();
$manualModel = governedModel($manualAuditDb);
$manualAuditDb->beginTransaction();
$manualScope = $manualModel->validateExistingDTRUpdateScope(true);
$manualAuditDb->updated = true;
$manualAfter = $manualModel->manualMutationAfterSnapshot();
$manualEvent = $manualModel->writeManualUpdateAudit([
    'success' => 1,
    'routine' => 'sp_calculate_indv_dtr',
    'routine_hash' => str_repeat('a', 64),
], $manualAfter);
checkGovernedMutation(
    ($manualScope['success'] ?? 0) === 1
        && preg_match('/^DTRM-[A-F0-9]{32}$/', $manualEvent) === 1
        && count($manualAuditDb->auditEvents) === 1
        && ($manualAuditDb->auditEvents[0][':scope_kind'] ?? '') === 'EMPLOYEE'
        && ($manualAuditDb->auditEvents[0][':calculator_routine'] ?? '') === 'sp_calculate_indv_dtr'
        && str_contains((string)($manualAuditDb->auditEvents[0][':before_payload'] ?? ''), 'daily_worked')
        && str_contains((string)($manualAuditDb->auditEvents[0][':after_payload'] ?? ''), 'payroll_summary')
        && hash_equals(
            hash('sha256', (string)$manualAuditDb->auditEvents[0][':scope_payload']),
            (string)$manualAuditDb->auditEvents[0][':scope_hash']
        ),
    'manual DTR audit returns a stable ID and stores scope, values, and calculator proof'
);
$manualAuditDb->rollBack();

$controller = (string)file_get_contents(__DIR__ . '/../controller/DTRController.php');
$migration = (string)file_get_contents(
    __DIR__ . '/../../dtr-format-engine/migrations/20260727_02_dtr_mutation_audit.sql'
);
$updateRoute = substr(
    $controller,
    (int)strpos($controller, "}else if(\$_POST['request'] == 'update-dtr'){"),
    10000
);
checkGovernedMutation(
    strpos($updateRoute, '$model->calculatorSafetyEvidence()')
        < strpos($updateRoute, '$model->updateDTR()'),
    'controller proves calculator safety before any manual DTR update'
);
checkGovernedMutation(
    strpos($updateRoute, '$model->writeManualUpdateAudit(')
        < strpos($updateRoute, '$pdoConn->commit()'),
    'manual audit is persisted before the application transaction commits'
);
checkGovernedMutation(
    strpos($updateRoute, '$model->manualMutationAfterSnapshot()')
        < strpos($updateRoute, '$model->writeManualUpdateAudit('),
    'manual audit captures submitted DTR and derived payroll values before persistence'
);
checkGovernedMutation(
    str_contains($migration, 'CREATE TABLE IF NOT EXISTS dtr_mutation_audit_events')
        && str_contains($migration, 'before_payload LONGTEXT NOT NULL')
        && str_contains($migration, 'after_payload LONGTEXT NOT NULL')
        && str_contains($migration, 'before_hash CHAR(64) NOT NULL')
        && str_contains($migration, 'after_hash CHAR(64) NOT NULL')
        && str_contains($migration, 'scope_kind VARCHAR(24) NOT NULL')
        && str_contains($migration, 'scope_payload LONGTEXT NOT NULL')
        && str_contains($migration, 'scope_hash CHAR(64) NOT NULL')
        && str_contains($migration, 'ENGINE=InnoDB'),
    'migration provides durable InnoDB before/after/scope payloads and SHA-256 hashes'
);
checkGovernedMutation(
    str_contains($migration, 'MODIFY COLUMN employee_id BIGINT UNSIGNED NULL')
        && str_contains($migration, 'MODIFY COLUMN cut_off VARCHAR(50) NULL')
        && str_contains($migration, 'INFORMATION_SCHEMA.COLUMNS')
        && str_contains($migration, "WHEN operation = 'DELETE_BULK' THEN 'PAYROLL_SCOPE'")
        && str_contains($migration, 'SET scope_hash = SHA2(scope_payload, 256)')
        && str_contains($migration, 'INFORMATION_SCHEMA.STATISTICS'),
    'migration safely upgrades the already-applied employee-only audit table without a bulk sentinel'
);

$javascript = (string)file_get_contents(__DIR__ . '/../js/index-13.js');
checkGovernedMutation(
    str_contains($javascript, 'dtr-benefits-reason')
        && str_contains($javascript, 'dtr-benefits-evidence')
        && str_contains($javascript, 'dtr-benefits-confirmation')
        && str_contains($javascript, 'response.audit_recorded'),
    'benefits UI reviews exact scope and requires audited completion'
);

echo "RESULT: {$checks} governed DTR mutation-audit checks passed.\n";
