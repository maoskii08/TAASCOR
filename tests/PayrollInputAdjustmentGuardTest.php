<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/payroll_adjustment_guard.php';

function adjustment_guard_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$validScope = [
    'client_name' => 'Fuji',
    'cut_off' => '16-30',
    'pay_day' => '2026-06-30',
    'start_date' => '2026-06-16',
    'end_date' => '2026-06-30',
];
$scope = PayrollAdjustmentGuard::validateScope($validScope);
adjustment_guard_check(
    ($scope['success'] ?? 0) === 1 && ($scope['scope'] ?? []) === $validScope,
    'accepts one exact canonical payroll scope'
);
foreach ([
    'No Client is rejected' => ['client_name' => 'No Client'],
    'blank client is rejected' => ['client_name' => ''],
    'invalid pay day is rejected' => ['pay_day' => '2026-02-30'],
    'invalid start date is rejected' => ['start_date' => '06/16/2026'],
    'reversed period is rejected' => ['start_date' => '2026-07-01', 'end_date' => '2026-06-30'],
    'pay day before period end is rejected' => ['pay_day' => '2026-06-29'],
] as $message => $override) {
    $result = PayrollAdjustmentGuard::validateScope(array_replace($validScope, $override));
    adjustment_guard_check(
        ($result['success'] ?? 1) === 0 && ($result['code'] ?? '') === 'invalid_payroll_scope',
        $message
    );
}

$validAdjustment = PayrollAdjustmentGuard::validateAdjustment([
    'employee_id' => '1008',
    'amount' => '99999999.99',
    'type' => str_repeat('A', 50),
], 'type');
adjustment_guard_check(
    ($validAdjustment['success'] ?? 0) === 1
        && ($validAdjustment['adjustment']['employee_id'] ?? 0) === 1008
        && ($validAdjustment['adjustment']['amount'] ?? '') === '99999999.99'
        && strlen((string)($validAdjustment['adjustment']['type'] ?? '')) === 50,
    'accepts the exact DECIMAL(10,2) and VARCHAR(50) boundaries'
);
foreach ([
    'non-positive employee is rejected' => ['employee_id' => '0', 'amount' => '10.00', 'type' => 'Valid'],
    'zero amount is rejected' => ['employee_id' => '1', 'amount' => '0.00', 'type' => 'Valid'],
    'negative amount is rejected' => ['employee_id' => '1', 'amount' => '-1.00', 'type' => 'Valid'],
    'excess decimals are rejected' => ['employee_id' => '1', 'amount' => '1.001', 'type' => 'Valid'],
    'scientific notation is rejected' => ['employee_id' => '1', 'amount' => '1e3', 'type' => 'Valid'],
    'amount beyond DECIMAL(10,2) is rejected' => ['employee_id' => '1', 'amount' => '100000000.00', 'type' => 'Valid'],
    'blank adjustment type is rejected' => ['employee_id' => '1', 'amount' => '10.00', 'type' => ''],
    'type beyond VARCHAR(50) is rejected without truncation' => ['employee_id' => '1', 'amount' => '10.00', 'type' => str_repeat('X', 51)],
] as $message => $input) {
    $result = PayrollAdjustmentGuard::validateAdjustment($input, 'type');
    adjustment_guard_check(($result['success'] ?? 1) === 0, $message);
}

$audit = PayrollAdjustmentGuard::validateAuditContext([
    'change_reason' => "  Approved\ncorrection ",
    'evidence_reference' => ' TICKET-321 ',
    'confirmation' => 'delete',
], true);
adjustment_guard_check(
    ($audit['success'] ?? 0) === 1
        && ($audit['audit']['change_reason'] ?? '') === 'Approved correction'
        && ($audit['audit']['evidence_reference'] ?? '') === 'TICKET-321',
    'normalizes auditable reason, evidence, and exact delete confirmation'
);
foreach ([
    'short reason is rejected' => ['change_reason' => 'fix', 'evidence_reference' => 'ABC', 'confirmation' => 'DELETE'],
    'short evidence is rejected' => ['change_reason' => 'Approved fix', 'evidence_reference' => 'A', 'confirmation' => 'DELETE'],
    'missing exact delete confirmation is rejected' => ['change_reason' => 'Approved fix', 'evidence_reference' => 'ABC', 'confirmation' => 'YES'],
    'overlong reason is rejected without truncation' => ['change_reason' => str_repeat('R', 256), 'evidence_reference' => 'ABC', 'confirmation' => 'DELETE'],
] as $message => $input) {
    $result = PayrollAdjustmentGuard::validateAuditContext($input, true);
    adjustment_guard_check(($result['success'] ?? 1) === 0, $message);
}

adjustment_guard_check(
    PayrollAdjustmentGuard::filterIdOrNull('', 'branch') === null
        && PayrollAdjustmentGuard::filterIdOrNull('42', 'branch') === 42,
    'normalizes optional branch and location filters'
);
$invalidFilterRejected = false;
try {
    PayrollAdjustmentGuard::filterIdOrNull('1 OR 1=1', 'branch');
} catch (InvalidArgumentException $error) {
    $invalidFilterRejected = true;
}
adjustment_guard_check($invalidFilterRejected, 'invalid filter identifiers fail closed');
adjustment_guard_check(
    PayrollAdjustmentGuard::fingerprint(8, '10', '  Meal   Allowance ')
        === PayrollAdjustmentGuard::fingerprint(8, '10.00', 'meal allowance'),
    'duplicate fingerprint normalizes amount, case, and whitespace'
);

$atomicPayload = [
    'upload_mode' => 'atomic-v1',
    'expected_row_count' => 2,
    'column_map' => ['employee id' => 0, 'amount' => 1, 'type of addition' => 2],
    'data' => [[1008, '10.00', 'Meal'], [1009, '20.00', 'Bonus']],
    'payrollDetails' => [['Fuji', '16-30', '2026-06-30', '2026-06-16', '2026-06-30']],
    'change_reason' => 'Approved correction',
    'evidence_reference' => 'TICKET-321',
    'source_filename' => 'fuji-additions.xlsx',
];
$decoded = PayrollAdjustmentGuard::decodeAtomicWorkbookPayload((string)json_encode($atomicPayload));
adjustment_guard_check(
    ($decoded['success'] ?? 0) === 1
        && ($decoded['payload']['expected_row_count'] ?? 0) === 2,
    'accepts one complete atomic-v1 workbook request'
);
foreach ([
    'legacy batched upload is rejected' => array_diff_key($atomicPayload, ['upload_mode' => true]),
    'declared row mismatch is rejected' => array_replace($atomicPayload, ['expected_row_count' => 1]),
    'missing source filename is rejected' => array_replace($atomicPayload, ['source_filename' => '']),
] as $message => $payload) {
    $result = PayrollAdjustmentGuard::decodeAtomicWorkbookPayload((string)json_encode($payload));
    adjustment_guard_check(($result['success'] ?? 1) === 0, $message);
}
$tooManyRows = array_replace($atomicPayload, [
    'expected_row_count' => PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS + 1,
    'data' => array_fill(0, PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_ROWS + 1, [1008, '10.00', 'Meal']),
]);
adjustment_guard_check(
    (PayrollAdjustmentGuard::decodeAtomicWorkbookPayload((string)json_encode($tooManyRows))['success'] ?? 1) === 0,
    'rejects a workbook above the 1,000-row atomic boundary'
);
adjustment_guard_check(
    (PayrollAdjustmentGuard::decodeAtomicWorkbookPayload(
        str_repeat('X', PayrollAdjustmentGuard::MAX_ATOMIC_WORKBOOK_BYTES + 1)
    )['code'] ?? '') === 'workbook_request_too_large',
    'rejects a request above the 1 MiB atomic boundary before JSON decoding'
);

final class PayrollAuditStatement
{
    public function __construct(private PayrollAuditDatabase $db, public string $sql)
    {
    }

    public function execute(array $values): bool
    {
        $this->db->executions[] = ['sql' => $this->sql, 'values' => $values];
        return true;
    }

    public function rowCount(): int
    {
        return 1;
    }
}

final class PayrollAuditDatabase
{
    public array $executions = [];

    public function __construct(private bool $transaction = true)
    {
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function prepare(string $sql): PayrollAuditStatement
    {
        return new PayrollAuditStatement($this, $sql);
    }
}

$auditContext = $validScope + [
    'change_reason' => 'Approved correction',
    'evidence_reference' => 'TICKET-321',
    'source_filename' => 'fuji-additions.xlsx',
    'row_records' => [
        ['source_row_number' => 2, 'adjustment_id' => 71, 'employee_id' => 1008, 'amount' => '123.40', 'type' => 'Meal'],
        ['source_row_number' => 3, 'adjustment_id' => 72, 'employee_id' => 1009, 'amount' => '50.00', 'type' => 'Bonus'],
    ],
];
$auditDb = new PayrollAuditDatabase();
$eventId = PayrollAdjustmentGuard::writeAuditRows($auditDb, 'ADDITION_BULK', $auditContext, 'tester');
$eventExecution = $auditDb->executions[0] ?? [];
$legacyExecution = $auditDb->executions[1] ?? [];
$storedRows = json_decode((string)($eventExecution['values'][':rows_payload'] ?? ''), true);
adjustment_guard_check(strlen($eventId) === 24, 'creates a collision-resistant linked payroll audit event identifier');
adjustment_guard_check(
    ($eventExecution['values'][':client_name'] ?? '') === 'Fuji'
        && ($eventExecution['values'][':cut_off'] ?? '') === '16-30'
        && ($eventExecution['values'][':period_start'] ?? '') === '2026-06-16'
        && ($eventExecution['values'][':period_end'] ?? '') === '2026-06-30'
        && ($eventExecution['values'][':pay_day'] ?? '') === '2026-06-30',
    'persists the exact client, cutoff, period, and pay day'
);
adjustment_guard_check(
    is_array($storedRows)
        && count($storedRows) === 2
        && ($storedRows[0]['source_row_number'] ?? 0) === 2
        && ($storedRows[0]['adjustment_id'] ?? 0) === 71
        && ($storedRows[0]['employee_id'] ?? 0) === 1008
        && ($storedRows[0]['amount'] ?? '') === '123.40'
        && ($storedRows[0]['type'] ?? '') === 'Meal',
    'persists every inserted row exactly under the linked event'
);
$legacyAction = (string)($legacyExecution['values'][1] ?? '');
adjustment_guard_check(
    strlen($legacyAction) <= 100 && str_contains($legacyAction, $eventId) && str_contains($legacyAction, 'rows=2'),
    'writes one linked legacy audit pointer within VARCHAR(100)'
);
$secondAuditDb = new PayrollAuditDatabase();
PayrollAdjustmentGuard::writeAuditRows($secondAuditDb, 'ADDITION_BULK', $auditContext, 'tester');
adjustment_guard_check(
    ($eventExecution['values'][':payload_hash'] ?? '') === ($secondAuditDb->executions[0]['values'][':payload_hash'] ?? ''),
    'canonical audit hash is deterministic and excludes the random event identifier'
);

$outsideTransactionRejected = false;
try {
    PayrollAdjustmentGuard::writeAuditRows(
        new PayrollAuditDatabase(false),
        'ADDITION_BULK',
        $auditContext
    );
} catch (RuntimeException $error) {
    $outsideTransactionRejected = true;
}
adjustment_guard_check($outsideTransactionRejected, 'rejects audit writes outside the payroll mutation transaction');

$invalidExactRowsRejected = 0;
foreach ([
    array_replace($auditContext, [
        'row_records' => [[
            'source_row_number' => 2,
            'adjustment_id' => null,
            'employee_id' => 1008,
            'amount' => '123.40',
            'type' => 'Meal',
        ]],
    ]),
    array_replace($auditContext, [
        'row_records' => [[
            'source_row_number' => 0,
            'adjustment_id' => 71,
            'employee_id' => 1008,
            'amount' => '123.40',
            'type' => 'Meal',
        ]],
    ]),
] as $invalidAuditContext) {
    try {
        PayrollAdjustmentGuard::writeAuditRows(
            new PayrollAuditDatabase(),
            'ADDITION_BULK',
            $invalidAuditContext
        );
    } catch (InvalidArgumentException $error) {
        $invalidExactRowsRejected++;
    }
}
adjustment_guard_check(
    $invalidExactRowsRejected === 2,
    'rejects audit rows without an exact database identifier or valid source row number'
);

echo "RESULT: Payroll input adjustment guard passed.\n";
