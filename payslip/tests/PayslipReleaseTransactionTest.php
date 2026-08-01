<?php

declare(strict_types=1);

require __DIR__ . '/../model/Payslip.php';

function transaction_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

final class ReleaseTransactionStatement
{
    private ReleaseTransactionDatabase $database;
    private string $sql;
    private int $rowCount = 0;

    public function __construct(ReleaseTransactionDatabase $database, string $sql)
    {
        $this->database = $database;
        $this->sql = preg_replace('/\s+/', ' ', trim($sql)) ?: trim($sql);
    }

    public function bindParam(string $parameter, &$value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function bindValue(string $parameter, $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $parameters = null): bool
    {
        $this->database->executedSql[] = $this->sql;

        if ($this->database->failOnOutbox
            && str_contains($this->sql, 'INSERT INTO payroll_import_outbox')) {
            throw new RuntimeException('Injected outbox failure.');
        }

        if (str_contains($this->sql, 'INSERT INTO locked_payroll')) {
            $this->rowCount = 1;
            $this->database->pendingPayrollLock = true;
        } elseif (str_contains($this->sql, 'UPDATE payroll_import_runs')) {
            $this->rowCount = 1;
        } else {
            $this->rowCount = 0;
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        if (str_contains($this->sql, 'FROM taascor_client')) {
            return [[
                'client_id' => 268,
                'client_name' => 'Fujifilm Optics Phils. Inc',
            ]];
        }
        return [];
    }

    public function fetchColumn(int $column = 0)
    {
        if (str_contains($this->sql, 'SELECT GET_LOCK')) {
            return 1;
        }
        if (str_contains($this->sql, 'SELECT RELEASE_LOCK')) {
            return 1;
        }
        if (str_contains($this->sql, 'SELECT 1 FROM locked_payroll')) {
            return $this->database->committedPayrollLock ? 1 : false;
        }
        return false;
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }
}

final class ReleaseTransactionDatabase
{
    public array $executedSql = [];
    public bool $transactionActive = false;
    public bool $pendingPayrollLock = false;
    public bool $committedPayrollLock = false;
    public bool $failOnOutbox = false;
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;

    public function prepare(string $sql): ReleaseTransactionStatement
    {
        return new ReleaseTransactionStatement($this, $sql);
    }

    public function beginTransaction(): bool
    {
        if ($this->transactionActive) {
            throw new RuntimeException('Nested transaction attempted.');
        }
        $this->transactionActive = true;
        $this->pendingPayrollLock = false;
        $this->beginCount++;
        return true;
    }

    public function commit(): bool
    {
        if (!$this->transactionActive) {
            throw new RuntimeException('Commit without an active transaction.');
        }
        $this->transactionActive = false;
        $this->committedPayrollLock = $this->committedPayrollLock || $this->pendingPayrollLock;
        $this->pendingPayrollLock = false;
        $this->commitCount++;
        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->transactionActive) {
            throw new RuntimeException('Rollback without an active transaction.');
        }
        $this->transactionActive = false;
        $this->pendingPayrollLock = false;
        $this->rollbackCount++;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionActive;
    }
}

final class ReadyReleasePayslip extends Payslip
{
    public int $gateCalls = 0;

    public function releaseGate(bool $lockRows = false): array
    {
        $this->gateCalls++;
        return [
            'success' => 1,
            'mode' => 'smart_run',
            'gate_status' => 'ready',
            'authoritative_run_id' => 19,
            'release_attempt' => true,
            'release_actor' => 'release@example.test',
            'release_actor_verified' => true,
            'run_ids' => [19],
            'blocking_reasons' => [],
        ];
    }
}

function ready_payslip(ReleaseTransactionDatabase $database): ReadyReleasePayslip
{
    $payslip = new ReadyReleasePayslip();
    $payslip->db = $database;
    $payslip->client = 'Fujifilm Optics Phils. Inc';
    $payslip->pay_day = '2025-08-20';
    $payslip->run_id = 19;
    $payslip->actor = 'release@example.test';
    return $payslip;
}

$successDatabase = new ReleaseTransactionDatabase();
$successPayslip = ready_payslip($successDatabase);
$success = $successPayslip->postPayroll();

transaction_check(($success['success'] ?? 0) === 1, 'ready smart run posts successfully');
transaction_check($successPayslip->gateCalls === 2, 'release evidence is checked before and inside the transaction');
transaction_check(
    $successDatabase->beginCount === 1
        && $successDatabase->commitCount === 1
        && $successDatabase->rollbackCount === 0,
    'ready release commits exactly one transaction'
);
transaction_check(
    $successDatabase->committedPayrollLock,
    'payroll lock becomes durable only after the release transaction commits'
);
transaction_check(
    count(array_filter(
        $successDatabase->executedSql,
        static fn(string $sql): bool => str_contains($sql, 'SELECT RELEASE_LOCK')
    )) === 1,
    'mutation lease is released after a successful post'
);

$duplicatePayslip = ready_payslip($successDatabase);
$duplicate = $duplicatePayslip->postPayroll();
transaction_check(
    ($duplicate['success'] ?? 1) === 0 && ($duplicate['code'] ?? '') === 'payroll_locked',
    'a second post for the committed scope fails closed'
);
transaction_check(
    $successDatabase->beginCount === 1,
    'duplicate posting is blocked before a second database transaction starts'
);

$failureDatabase = new ReleaseTransactionDatabase();
$failureDatabase->failOnOutbox = true;
$failurePayslip = ready_payslip($failureDatabase);
$failure = $failurePayslip->postPayroll();

transaction_check(($failure['success'] ?? 1) === 0, 'release failure returns a blocked response');
transaction_check(
    $failureDatabase->beginCount === 1
        && $failureDatabase->commitCount === 0
        && $failureDatabase->rollbackCount === 1,
    'outbox failure rolls back the complete release transaction'
);
transaction_check(
    !$failureDatabase->committedPayrollLock,
    'failed release leaves no committed payroll lock'
);
transaction_check(
    count(array_filter(
        $failureDatabase->executedSql,
        static fn(string $sql): bool => str_contains($sql, 'SELECT RELEASE_LOCK')
    )) === 1,
    'mutation lease is released after rollback'
);

echo "RESULT: Payroll release transaction checks passed.\n";
