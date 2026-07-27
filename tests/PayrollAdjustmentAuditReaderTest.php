<?php

declare(strict_types=1);

function payroll_adjustment_audit_reader_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$controller = (string)file_get_contents($root . '/audit-log/controller/AuditLogController.php');
$model = (string)file_get_contents($root . '/audit-log/model/AuditLog.php');
$page = (string)file_get_contents($root . '/audit-log/index.php');
$script = (string)file_get_contents($root . '/audit-log/js/audit-log.js');
$guides = (string)file_get_contents($root . '/assets/js/hris-help-guides.js');
$additionScript = (string)file_get_contents($root . '/other-additional/js/index-08.js');
$additionImport = (string)file_get_contents($root . '/other-additional/js/app-excel-import-v04.js');
$deductionScript = (string)file_get_contents($root . '/other-deduction/js/index-08.js');
$deductionImport = (string)file_get_contents($root . '/other-deduction/js/app-excel-import-v04.js');

payroll_adjustment_audit_reader_check(
    str_contains($controller, 'auth_require_role([1, 2, 3])')
        && str_contains($page, 'auth_require_role([1, 2, 3])'),
    'Admin, HR, and Payroll can access the governed audit reader'
);
payroll_adjustment_audit_reader_check(
    str_contains($controller, "case 'get-payroll-adjustment-audit-events':")
        && str_contains($controller, "case 'get-payroll-adjustment-audit-event':"),
    'audit controller exposes bounded list and exact-event read endpoints'
);
payroll_adjustment_audit_reader_check(
    str_contains($model, 'FROM payroll_adjustment_audit_events')
        && str_contains($model, 'LIMIT 500')
        && str_contains($model, "preg_match('/^[A-F0-9]{24}$/'"),
    'audit reader queries immutable events with a bounded result and strict identifier'
);
payroll_adjustment_audit_reader_check(
    str_contains($model, "'rows' => \$rows")
        && str_contains($model, 'hash_equals(')
        && str_contains($model, "'hash_valid'"),
    'event detail reconstructs rows and verifies the canonical SHA-256 payload'
);
payroll_adjustment_audit_reader_check(
    str_contains($page, 'Payroll Change Evidence')
        && str_contains($page, 'id="payrollEvidenceModal"')
        && str_contains($script, 'openPayrollEvidence')
        && str_contains($script, 'SHA-256 verified'),
    'Audit Log provides a searchable event table and evidence detail modal'
);
payroll_adjustment_audit_reader_check(
    str_contains($script, "new URLSearchParams(window.location.search).get('adjustment_event')")
        && str_contains($guides, 'Audit Event ID link returned after the change')
        && substr_count($additionScript . $additionImport . $deductionScript . $deductionImport, 'adjustmentAuditHtml(') >= 8,
    'every adjustment success flow links its returned event ID to exact evidence'
);
payroll_adjustment_audit_reader_check(
    preg_match(
        '/public function getPayrollAdjustmentEvents\(\): array(.*?)private function tableExists/s',
        $model,
        $readerMethods
    ) === 1
        && !preg_match('/\bINSERT\s+INTO\b|\bUPDATE\s+[a-z_`]|\bDELETE\s+FROM\b/i', $readerMethods[1]),
    'the new payroll adjustment evidence endpoints remain read-only'
);

echo "RESULT: Payroll adjustment audit reader checks passed.\n";
