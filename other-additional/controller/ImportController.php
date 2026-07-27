<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1,3]);

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');
http_response_code(409);

echo json_encode([
    'success' => 0,
    'code' => 'atomic_workbook_required',
    'error' => 'This legacy finalization endpoint is disabled. Refresh the page and upload the complete workbook atomically.',
], JSON_PRETTY_PRINT);
