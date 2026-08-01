<?php

require_once __DIR__ . '/../includes/auth_guard.php';
auth_require_role([1, 3]);

// Backward-compatible alias. Keep the historical URL returning the PDF body
// directly while executing only the maintained, authenticated, bound-query
// implementation.
require __DIR__ . '/payslip2.php';
