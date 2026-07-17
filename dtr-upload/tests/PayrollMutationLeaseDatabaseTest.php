<?php

declare(strict_types=1);

require __DIR__ . '/../model/PayrollLockGuard.php';
require __DIR__ . '/../../tests/DatabaseTestConnection.php';

function lease_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$first = test_database_connection();
$second = test_database_connection();
$client = (string)$first->query('SELECT client_name FROM taascor_client ORDER BY client_id LIMIT 1')->fetchColumn();
$client !== '' || throw new RuntimeException('The lease test requires one configured payroll client.');
$payDay = '2099-12-31';
$firstGuard = new PayrollLockGuard($first);
$secondGuard = new PayrollLockGuard($second);
$lease = $firstGuard->acquireMutationLease($client, $payDay, 0);
try {
    lease_check(($lease['success'] ?? 0) === 1, 'first payroll mutation acquires the scope lease');
    $contended = $secondGuard->acquireMutationLease($client, $payDay, 0);
    lease_check(
        ($contended['success'] ?? 1) === 0 && ($contended['code'] ?? '') === 'payroll_mutation_busy',
        'concurrent payroll mutation cannot pass the same scope lease'
    );
} finally {
    if (($lease['success'] ?? 0) === 1) {
        $firstGuard->releaseMutationLease((string)$lease['lease_name']);
    }
}
$afterRelease = $secondGuard->acquireMutationLease($client, $payDay, 0);
lease_check(($afterRelease['success'] ?? 0) === 1, 'scope lease is reusable after the first mutation finishes');
$secondGuard->releaseMutationLease((string)$afterRelease['lease_name']);

echo "RESULT: Payroll mutation lease database behavior passed.\n";
