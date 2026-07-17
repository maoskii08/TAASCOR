<?php

declare(strict_types=1);

require __DIR__ . '/../../tests/DatabaseTestConnection.php';
require __DIR__ . '/../model/FujiPayrollSummaryAdapter.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$db = test_database_connection();
$fixture = $db->query("\n    SELECT b.id, b.checksum,\n           JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.period_start')) AS period_start,\n           JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.period_end')) AS period_end,\n           JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.pay_date')) AS pay_date\n    FROM dtr_upload_batches b\n    INNER JOIN dtr_upload_staging_rows r ON r.batch_id = b.id\n    WHERE b.source_context = 'fuji_payroll_summary'\n      AND b.checksum IS NOT NULL\n      AND JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.pay_date')) IS NOT NULL\n    ORDER BY b.id DESC\n    LIMIT 1\n")->fetch(PDO::FETCH_ASSOC);
check(is_array($fixture), 'fixture has a staged Fuji batch with pay-date metadata');

$adapter = new FujiPayrollSummaryAdapter();
$adapter->db = $db;
$existingBatch = new ReflectionMethod(FujiPayrollSummaryAdapter::class, 'existingBatch');
$exact = $existingBatch->invoke(
    $adapter,
    (string)$fixture['checksum'],
    (string)$fixture['period_start'],
    (string)$fixture['period_end'],
    (string)$fixture['pay_date']
);
check((int)($exact['id'] ?? 0) === (int)$fixture['id'], 'same checksum, period, and pay date is a duplicate');

$differentPayDate = $existingBatch->invoke(
    $adapter,
    (string)$fixture['checksum'],
    (string)$fixture['period_start'],
    (string)$fixture['period_end'],
    '2099-12-31'
);
check($differentPayDate === null, 'different pay date is not collapsed into the existing batch');

$duplicateKey = new ReflectionMethod(FujiPayrollSummaryAdapter::class, 'duplicateKey');
$firstKey = $duplicateKey->invoke($adapter, 'SOURCE-1', '2026-06-16', '2026-06-30', '2026-07-13');
$secondKey = $duplicateKey->invoke($adapter, 'SOURCE-1', '2026-06-16', '2026-06-30', '2026-07-14');
check($firstKey !== $secondKey, 'row-level duplicate metadata includes the pay date');

echo "RESULT: Fuji duplicate detection includes pay date.\n";
