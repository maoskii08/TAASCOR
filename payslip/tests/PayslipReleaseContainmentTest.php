<?php

declare(strict_types=1);

require __DIR__ . '/../model/Payslip.php';

function containment_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

final class UntouchedPayrollDatabase
{
    public int $calls = 0;

    public function __call(string $name, array $arguments)
    {
        $this->calls++;
        throw new RuntimeException("Blocked payroll unexpectedly touched the database through {$name}.");
    }
}

final class ContainedPayslip extends Payslip
{
    public array $releaseContext = [];
    public int $gateCalls = 0;

    public function releaseGate(bool $lockRows = false): array
    {
        $this->gateCalls++;
        return payroll_release_gate_policy($this->releaseContext);
    }
}

$cases = [
    'missing schema' => [
        'context' => ['smart_run_schema_exists' => false],
        'reason' => 'smart_run_schema_required',
    ],
    'unenrolled client' => [
        'context' => [
            'smart_run_schema_exists' => true,
            'smart_flow_enrolled' => false,
        ],
        'reason' => 'smart_payroll_enrollment_required',
    ],
    'missing authoritative run' => [
        'context' => [
            'smart_run_schema_exists' => true,
            'smart_flow_enrolled' => true,
            'live_payroll_row_count' => 1,
        ],
        'reason' => 'authoritative_run_selection_required',
    ],
    'zero payroll rows' => [
        'context' => [
            'smart_run_schema_exists' => true,
            'smart_flow_enrolled' => true,
            'live_payroll_row_count' => 0,
            'authoritative_run_id' => 19,
        ],
        'reason' => 'payroll_scope_empty',
    ],
];

foreach ($cases as $label => $case) {
    $database = new UntouchedPayrollDatabase();
    $payslip = new ContainedPayslip();
    $payslip->db = $database;
    $payslip->client = 'Containment Test Client';
    $payslip->pay_day = '2026-07-31';
    $payslip->releaseContext = $case['context'];

    $result = $payslip->postPayroll();

    containment_check(
        ($result['success'] ?? 1) === 0
            && in_array($case['reason'], $result['blocking_reasons'] ?? [], true),
        "{$label} blocks posting"
    );
    containment_check($payslip->gateCalls === 1, "{$label} stops at the release preflight");
    containment_check(
        $database->calls === 0,
        "{$label} creates no lock, loan, outbox, or other database side effect"
    );
}

$controller = (string)file_get_contents(__DIR__ . '/../controller/PayslipController.php');
$postRouteStart = strpos($controller, "}else if(\$request == 'post-payroll'){");
$nextRouteStart = $postRouteStart === false
    ? false
    : strpos($controller, "}else if(\$request == 'get-branch'){", $postRouteStart);
$postRoute = $postRouteStart === false || $nextRouteStart === false
    ? ''
    : substr($controller, $postRouteStart, $nextRouteStart - $postRouteStart);

containment_check(
    preg_match(
        '/if\s*\(\(\$result\[\'success\'\]\s*\?\?\s*0\)\s*==\s*1\)\s*\{\s*log_action\(/s',
        $postRoute
    ) === 1,
    'blocked posting cannot write the controller audit entry'
);

$payslipPage = (string)file_get_contents(__DIR__ . '/../index.php');
$payslipJavascript = (string)file_get_contents(__DIR__ . '/../js/index-09.js');
$payslipModel = (string)file_get_contents(__DIR__ . '/../model/Payslip.php');
containment_check(
    preg_match('/id="postBtn"[^>]*\sdisabled(?:\s|>)/', $payslipPage) === 1,
    'Post Payroll is disabled before release evidence loads'
);
containment_check(
    str_contains($payslipJavascript, 'function payrollReleaseGateIsReady(gate)')
        && str_contains($payslipJavascript, "gate.mode === 'smart_run'")
        && str_contains($payslipJavascript, "gate.gate_status === 'ready'")
        && str_contains($payslipJavascript, 'Number(gate.authoritative_run_id || 0) > 0'),
    'browser release control requires a ready authoritative smart run'
);
containment_check(
    str_contains($controller, '$model->actor = auth_user();')
        && str_contains($payslipModel, "'release_attempt' => true")
        && str_contains($payslipJavascript, 'gate.release_attempt === true')
        && str_contains($payslipJavascript, 'gate.release_actor_verified === true')
        && str_contains($payslipJavascript, "String(gate.release_actor || '').trim() !== ''"),
    'browser preflight carries the authenticated actor and fails closed when the actor cannot be verified'
);
containment_check(
    !str_contains($payslipJavascript, '(!payrollReleaseGate || payrollReleaseGate.success == 1)'),
    'missing browser gate evidence cannot default to ready'
);
containment_check(
    str_contains($payslipJavascript, "payrollReleaseGate.mode === 'legacy_preview'")
        && str_contains($payslipJavascript, "params.set('preview', '1');")
        && str_contains($payslipJavascript, 'UNVERIFIED LEGACY PREVIEW - NON-DISTRIBUTABLE')
        && str_contains($payslipJavascript, "gate.mode === 'smart_run'"),
    'unenrolled UI permits only a marked legacy preview while Post remains smart-run-only'
);
containment_check(
    str_contains($payslipPage, 'id="payrollReleaseGateStatus"')
        && str_contains($payslipJavascript, 'renderPayrollReleaseGateStatus'),
    'payroll officer receives a visible release-gate status'
);
containment_check(
    !str_contains($payslipJavascript, 'console.log(response.data')
        && !str_contains($payslipJavascript, 'console.log(response.columns'),
    'payroll response rows are not written to the browser console'
);

echo "RESULT: Payroll release containment passed.\n";
