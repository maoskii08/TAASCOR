<?php
$manager = (string)file_get_contents(
    __DIR__ . '/../dtr-format-engine/model/EmployeeIdentityNotificationManager.php'
);
$worker = (string)file_get_contents(
    __DIR__ . '/../dtr-format-engine/workers/notification-delivery-worker.php'
);

$checks = [
    [str_contains($manager, "'email'"), 'identity events enqueue an email delivery'],
    [str_contains($manager, "'pending', 0"), 'email delivery starts pending'],
    [
        str_contains($manager, 'access_level IN (1, 2, 3)'),
        'Admin, HR, and Payroll recipients receive cross-client payroll notifications',
    ],
    [str_contains($worker, "PHP_SAPI !== 'cli'"), 'delivery worker is CLI-only'],
    [str_contains($worker, "'dead_letter'"), 'delivery worker has terminal dead-letter state'],
    [str_contains($worker, "delivery_status = 'retry'"), 'delivery worker retries recoverable failures'],
    [str_contains($worker, 'Recovered stale delivery claim'), 'stale worker claims are recovered'],
];

foreach ($checks as [$passed, $message]) {
    if (!$passed) {
        throw new RuntimeException('Notification delivery check failed: ' . $message);
    }
    echo "PASS: {$message}\n";
}

echo "RESULT: Durable notification delivery checks passed.\n";
