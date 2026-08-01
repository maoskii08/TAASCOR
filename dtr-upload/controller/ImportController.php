<?php

declare(strict_types=1);

require_once('../../includes/auth_guard.php');
auth_require_role([1, 3]);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');
http_response_code(410);

echo json_encode([
    'success' => 0,
    'code' => 'legacy_dtr_workbook_import_quarantined',
    'error' => 'The legacy workbook finalization flow is unavailable because browser-batched uploads cannot guarantee all-or-nothing payroll writes.',
    'mutation_blocked' => true,
    'recovery_route' => '../dtr-format-engine/',
], JSON_PRETTY_PRINT);
