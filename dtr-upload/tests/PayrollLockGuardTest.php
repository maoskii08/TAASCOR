<?php

declare(strict_types=1);

require __DIR__ . '/../model/PayrollLockGuard.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

final class FakeLockStatement
{
    private array $columns;
    public array $params = [];

    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }

    public function execute(array $params = []): bool
    {
        $this->params = $params;
        return true;
    }

    public function fetchColumn()
    {
        return $this->columns[0] ?? false;
    }

    public function fetchAll(int $mode = 0): array
    {
        return $this->columns;
    }
}

final class FakeLockDb
{
    public array $columns;
    public ?FakeLockStatement $statement = null;
    public bool $throw = false;

    public function __construct(array $columns = [])
    {
        $this->columns = $columns;
    }

    public function prepare(string $sql): FakeLockStatement
    {
        if ($this->throw) {
            throw new RuntimeException('database unavailable');
        }
        $this->statement = new FakeLockStatement($this->columns);
        return $this->statement;
    }
}

$unlockedDb = new FakeLockDb([]);
$unlocked = (new PayrollLockGuard($unlockedDb))->check('FUJIFILM', '2026-07-13');
check(($unlocked['success'] ?? 0) === 1 && empty($unlocked['locked']), 'unposted payroll remains mutable');
check(
    ($unlockedDb->statement->params[':client'] ?? '') === 'FUJIFILM'
        && ($unlockedDb->statement->params[':pay_day'] ?? '') === '2026-07-13',
    'lock lookup uses bound client and pay-date parameters'
);

$locked = (new PayrollLockGuard(new FakeLockDb([1])))->check('FUJIFILM', '2026-07-13');
check(($locked['success'] ?? 1) === 0 && ($locked['code'] ?? '') === 'payroll_locked', 'posted payroll blocks mutation');

$invalid = (new PayrollLockGuard(new FakeLockDb()))->check('', 'not-a-date');
check(($invalid['code'] ?? '') === 'payroll_lock_scope_invalid', 'invalid lock scope fails closed');
$nonCanonicalDate = (new PayrollLockGuard(new FakeLockDb()))->check('FUJIFILM', '2026-7-13');
check(($nonCanonicalDate['code'] ?? '') === 'payroll_lock_scope_invalid', 'non-canonical pay dates cannot create a second lease key');

$failedDb = new FakeLockDb();
$failedDb->throw = true;
$failed = (new PayrollLockGuard($failedDb))->check('FUJIFILM', '2026-07-13');
check(($failed['code'] ?? '') === 'payroll_lock_check_failed', 'database lock-check errors fail closed');

$resolved = (new PayrollLockGuard(new FakeLockDb(['FUJIFILM'])))->resolveEmployeeClient(42, '2026-07-13');
check(($resolved['client'] ?? '') === 'FUJIFILM', 'legacy employee action resolves one client scope');

$ambiguous = (new PayrollLockGuard(new FakeLockDb(['CLIENT A', 'CLIENT B'])))->resolveEmployeeClient(42, '2026-07-13');
check(($ambiguous['code'] ?? '') === 'payroll_lock_scope_ambiguous', 'ambiguous employee scope fails closed');

$scope = PayrollLockGuard::normalizePayrollDetails([
    ['FUJIFILM', '16-30', '2026-07-13', '2026-06-16', '2026-06-30'],
]);
check(($scope['success'] ?? 0) === 1 && $scope['client_name'] === 'FUJIFILM', 'one canonical import scope is accepted');
$multiScope = PayrollLockGuard::normalizePayrollDetails([
    ['FUJIFILM', '16-30', '2026-07-13', '2026-06-16', '2026-06-30'],
    ['OTHER', '16-30', '2026-07-13', '2026-06-16', '2026-06-30'],
]);
check(($multiScope['success'] ?? 1) === 0, 'multi-scope import payload fails closed');

echo "RESULT: Payroll lock guard rules passed.\n";
