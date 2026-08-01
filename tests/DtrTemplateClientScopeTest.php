<?php

declare(strict_types=1);

require __DIR__ . '/DatabaseTestConnection.php';
require __DIR__ . '/../dtr-format-engine/model/TemplateManager.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$db = test_database_connection();

$admin = new TemplateManager();
$admin->db = $db;
$admin->allow_all_clients = true;
$adminTemplates = $admin->listTemplates();
$adminBatches = $admin->listBatches();
check(($adminTemplates['success'] ?? 0) === 1, 'administrator template list loads');
check(($adminBatches['success'] ?? 0) === 1, 'administrator batch list loads');

$fuji = new TemplateManager();
$fuji->db = $db;
$fuji->allowed_client_ids = [264];
$fujiTemplates = $fuji->listTemplates();
$fujiBatches = $fuji->listBatches();
check(($fujiTemplates['success'] ?? 0) === 1, 'client-scoped template list loads');
check(($fujiBatches['success'] ?? 0) === 1, 'client-scoped batch list loads');

foreach ($fujiTemplates['data'] as $template) {
    check((int)$template['client_id'] === 264, 'template list contains only the assigned client');
}

$batchIds = array_map(static fn(array $row): int => (int)$row['id'], $fujiBatches['data']);
if ($batchIds !== []) {
    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
    $scopeCheck = $db->prepare("
        SELECT COUNT(*)
        FROM dtr_upload_batches b
        INNER JOIN dtr_format_templates t ON t.id = b.template_id
        WHERE b.id IN ({$placeholders}) AND t.client_id <> 264
    ");
    $scopeCheck->execute($batchIds);
    check((int)$scopeCheck->fetchColumn() === 0, 'batch list contains only the assigned client');
}

check(count($fujiTemplates['data']) <= count($adminTemplates['data']), 'client template list does not exceed the global list');
check(count($fujiBatches['data']) <= count($adminBatches['data']), 'client batch list does not exceed the global list');

echo "RESULT: DTR template and batch client-scope checks passed.\n";
