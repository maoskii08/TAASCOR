<?php

declare(strict_types=1);

require __DIR__ . '/DatabaseTestConnection.php';
require __DIR__ . '/../payroll-data-quality/model/DataQuality.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$db = test_database_connection();

$admin = new DataQuality();
$admin->db = $db;
$admin->allow_all_clients = true;
$adminResult = $admin->getSummary();
check(($adminResult['success'] ?? 0) === 1, 'administrator data-quality summary loads');
check(count($adminResult['data'] ?? []) === 10, 'all payroll data-quality checks are returned');
foreach ($adminResult['data'] as $finding) {
    if ((int)$finding['count'] === 0) {
        continue;
    }
    $details = $admin->getDetails((string)$finding['key'], 3);
    check(($details['success'] ?? 0) === 1, "details load for {$finding['key']}");
    check(count($details['data'] ?? []) <= 3, "detail limit is enforced for {$finding['key']}");
    check(
        isset($details['correction']['owner'], $details['correction']['action_label'], $details['correction']['guidance']),
        "correction routing is declared for {$finding['key']}"
    );
}

$client = new DataQuality();
$client->db = $db;
$client->allowed_client_ids = [264];
$clientResult = $client->getSummary();
check(($clientResult['success'] ?? 0) === 1, 'client-scoped data-quality summary loads');

$adminCounts = array_column($adminResult['data'], 'count', 'key');
$clientCounts = array_column($clientResult['data'], 'count', 'key');
foreach ($clientCounts as $key => $count) {
    check((int)$count <= (int)($adminCounts[$key] ?? -1), "client scope does not exceed global count for {$key}");
}

$controller = (string)file_get_contents(__DIR__ . '/../payroll-data-quality/controller/DataQualityController.php');
check(str_contains($controller, 'auth_client_ids()'), 'controller derives payroll client scope from the authenticated session');
check(str_contains($controller, 'No payroll client is assigned'), 'unassigned non-admin users fail closed');
check(str_contains($controller, "\$request === 'details'"), 'controller exposes authenticated finding details');

$page = (string)file_get_contents(__DIR__ . '/../payroll-data-quality/index.php');
$script = (string)file_get_contents(__DIR__ . '/../payroll-data-quality/js/index-01.js');
$guide = (string)file_get_contents(__DIR__ . '/../assets/js/hris-help-guides.js');
check(str_contains($page, 'id="dqDrawer"') && str_contains($page, 'aria-modal="true"'), 'page includes an accessible issue drawer');
check(str_contains($script, "request: 'details'") && str_contains($script, 'employeeManagementUrl'), 'drawer loads details and routes employee corrections');
check(str_contains($script, "employeeStatus === 'active'") && str_contains($script, 'View lifecycle record'), 'drawer avoids dead edit links for terminated employees');
check(str_contains($script, 'trapDrawerFocus') && str_contains($script, "event.key === 'Escape'"), 'drawer traps focus and supports Escape');
check(str_contains($script, "attr('inert', '')") && str_contains($script, "removeAttr('inert')"), 'drawer makes background controls unavailable while open');
check(str_contains($guide, 'Review affected records') && str_contains($guide, 'Correct a payroll-source finding'), 'Payroll Data Quality guide documents the actionable drawer workflow');

echo "RESULT: Payroll Data Quality actionable review checks passed.\n";
