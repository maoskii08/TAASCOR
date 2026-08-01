<?php
require_once(__DIR__ . '/../includes/auth_guard.php');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$_SESSION['taascor_access_level'] = 3;
$_SESSION['taascor_user_name'] = 'payroll.user';
$_SESSION['taascor_client'] = '264, 10,264,invalid';

$check(auth_client_ids() === [264, 10], 'stored client IDs must still be normalized and deduplicated');
$check(auth_has_global_client_access(), 'Payroll must have global client access');
$check(auth_can_access_client_id(264), 'Payroll must access an assigned client');
$check(auth_can_access_client_id(11), 'Payroll must access an unassigned client globally');
$check(!auth_can_access_client_id(0), 'missing client scope must fail closed');

$_SESSION['taascor_access_level'] = 2;
$_SESSION['taascor_client'] = '';
$check(auth_has_global_client_access(), 'HR must have global client access');
$check(auth_can_access_client_id(264), 'HR must access every valid client without an assignment');

$_SESSION['taascor_access_level'] = 1;
$check(auth_has_global_client_access(), 'administrator must have explicit global client access');
$check(auth_can_access_client_id(999), 'administrator global access must allow a valid client ID');

$_SESSION['taascor_access_level'] = 4;
$_SESSION['taascor_client'] = '264';
$check(!auth_has_global_client_access(), 'Coordinator must remain client-scoped');
$check(auth_can_access_client_id(264), 'Coordinator must access an assigned client');
$check(!auth_can_access_client_id(999), 'Coordinator must not access an unassigned client');

$payslipController = (string)file_get_contents(__DIR__ . '/../payslip/controller/PayslipController.php');
$sealedPayslip = (string)file_get_contents(__DIR__ . '/../payslip/payslip-sealed.php');
$templateController = (string)file_get_contents(
    __DIR__ . '/../dtr-format-engine/controller/TemplateController.php'
);
$templateManager = (string)file_get_contents(
    __DIR__ . '/../dtr-format-engine/model/TemplateManager.php'
);
$check(
    str_contains($payslipController, 'requirePayslipClientAccess'),
    'payslip requests resolve and authorize posted clients'
);
$check(
    str_contains($sealedPayslip, 'r.client_id IN'),
    'sealed payslip query applies authenticated client scope'
);
$check(
    str_contains($templateController, 'requireBatchClientScope'),
    'DTR batch actions enforce client ownership'
);
$check(
    str_contains($templateController, 'requirePayrollRunClientScope'),
    'payroll run actions enforce client ownership'
);
$check(
    str_contains($templateController, '$model->allowed_client_ids = auth_client_ids()'),
    'DTR list endpoints inherit authenticated client scope'
);
$check(
    str_contains($templateManager, 'WHERE {$clientScope}'),
    'DTR template and batch list queries apply client scope'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Client-scope authorization checks passed.\n";
