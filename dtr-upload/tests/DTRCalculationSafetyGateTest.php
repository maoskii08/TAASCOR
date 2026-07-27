<?php

declare(strict_types=1);

require __DIR__ . '/../model/DTRCalculationSafetyGate.php';

$checks = 0;

function checkCalculatorGate(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class FakeRoutineDatabase
{
    public array $definitions;
    public int $callAttempts = 0;

    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
    }

    public function prepare(string $sql): FakeRoutineStatement
    {
        if (stripos($sql, 'CALL ') !== false) {
            $this->callAttempts++;
        }
        return new FakeRoutineStatement($this, $sql);
    }
}

final class FakeRoutineStatement
{
    private FakeRoutineDatabase $db;
    private string $sql;
    private array $params = [];

    public function __construct(FakeRoutineDatabase $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        $this->params = $params;
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if (stripos($this->sql, 'INFORMATION_SCHEMA.ROUTINES') === false) {
            return [];
        }
        $routine = strtolower((string)($this->params[':routine_name'] ?? ''));
        if (!array_key_exists($routine, $this->db->definitions)) {
            return [];
        }
        $definition = $this->db->definitions[$routine];
        if (is_array($definition)) {
            return $definition;
        }
        return [[
            'ROUTINE_NAME' => $routine,
            'ROUTINE_DEFINITION' => $definition,
        ]];
    }
}

function approvedDefinition(string $definition): string
{
    return DTRCalculationSafetyGate::definitionHash($definition);
}

$unsafe = "BEGIN\nSTART TRANSACTION;\nUPDATE payroll_summary SET net_pay = net_pay;\nCOMMIT;\nEND";
$unsafeDb = new FakeRoutineDatabase(['sp_calculate_indv_dtr' => $unsafe]);
$unsafeGate = new DTRCalculationSafetyGate(
    $unsafeDb,
    ['sp_calculate_indv_dtr' => approvedDefinition($unsafe)]
);
$unsafeResult = $unsafeGate->verifyForCutoff('16-30', 'individual');
checkCalculatorGate(
    ($unsafeResult['code'] ?? '') === 'dtr_calculator_unsafe',
    'matching hash cannot approve a procedure with transaction control'
);
checkCalculatorGate(
    in_array('START TRANSACTION', $unsafeResult['detected_controls'] ?? [], true)
        && in_array('COMMIT', $unsafeResult['detected_controls'] ?? [], true),
    'unsafe transaction controls are reported without exposing the routine body'
);
checkCalculatorGate($unsafeDb->callAttempts === 0, 'unsafe proof check never executes a procedure');

$safe = "BEGIN\nUPDATE payroll_summary SET net_pay = net_pay WHERE employee_id = 42;\nEND";
$safeDb = new FakeRoutineDatabase(['sp_calculate_indv_dtr' => $safe]);
$unapproved = (new DTRCalculationSafetyGate($safeDb, []))
    ->verifyForCutoff('16-30', 'individual');
checkCalculatorGate(
    ($unapproved['code'] ?? '') === 'dtr_calculator_unapproved',
    'safe source without explicit hash approval remains blocked'
);

$wrongHash = (new DTRCalculationSafetyGate(
    $safeDb,
    ['sp_calculate_indv_dtr' => str_repeat('a', 64)]
))->verifyForCutoff('16-30', 'individual');
checkCalculatorGate(
    ($wrongHash['code'] ?? '') === 'dtr_calculator_hash_mismatch',
    'routine drift from the approved hash remains blocked'
);

$approved = (new DTRCalculationSafetyGate(
    $safeDb,
    ['sp_calculate_indv_dtr' => approvedDefinition($safe)]
))->verifyForCutoff('16-30', 'individual');
checkCalculatorGate(
    ($approved['success'] ?? 0) === 1
        && ($approved['routine'] ?? '') === 'sp_calculate_indv_dtr',
    'safe source with exact approval passes'
);

$weekly = "BEGIN\nUPDATE payroll_summary SET net_pay = net_pay;\nEND";
$weeklyDb = new FakeRoutineDatabase(['sp_calculate_dtr_weekly' => $weekly]);
$weeklyResult = (new DTRCalculationSafetyGate(
    $weeklyDb,
    ['sp_calculate_dtr_weekly' => approvedDefinition($weekly)]
))->verifyForCutoff('Weekly', 'batch');
checkCalculatorGate(
    ($weeklyResult['routine'] ?? '') === 'sp_calculate_dtr_weekly',
    'weekly batch scope selects the weekly calculator'
);

$missing = (new DTRCalculationSafetyGate(new FakeRoutineDatabase([]), []))
    ->verifyForCutoff('16-30', 'batch');
checkCalculatorGate(
    ($missing['code'] ?? '') === 'dtr_calculator_definition_unavailable',
    'missing or unreadable definitions fail closed'
);

$dynamic = "BEGIN\nSET @sql = 'UPDATE payroll_summary SET net_pay = 0';\nPREPARE s FROM @sql;\nEXECUTE s;\nEND";
$dynamicResult = (new DTRCalculationSafetyGate(
    new FakeRoutineDatabase(['sp_calculate_dtr' => $dynamic]),
    ['sp_calculate_dtr' => approvedDefinition($dynamic)]
))->verifyForCutoff('16-30', 'batch');
checkCalculatorGate(
    ($dynamicResult['code'] ?? '') === 'dtr_calculator_unsafe'
        && in_array('DYNAMIC SQL', $dynamicResult['detected_controls'] ?? [], true),
    'dynamic SQL cannot bypass static transaction-safety proof'
);

$helper = "BEGIN\nUPDATE payroll_summary SET net_pay = net_pay;\nEND";
$root = "BEGIN\nCALL payroll_helper();\nEND";
$dependencyDb = new FakeRoutineDatabase([
    'sp_calculate_dtr' => $root,
    'payroll_helper' => $helper,
]);
$dependencyResult = (new DTRCalculationSafetyGate(
    $dependencyDb,
    [
        'sp_calculate_dtr' => approvedDefinition($root),
        'payroll_helper' => approvedDefinition($helper),
    ]
))->verifyForCutoff('16-30', 'batch');
checkCalculatorGate(
    ($dependencyResult['success'] ?? 0) === 1
        && count($dependencyResult['verified_routines'] ?? []) === 2,
    'every called procedure is recursively proven and hash-approved'
);

$unsafeHelper = "BEGIN\nROLLBACK;\nEND";
$unsafeDependencyDb = new FakeRoutineDatabase([
    'sp_calculate_dtr' => $root,
    'payroll_helper' => $unsafeHelper,
]);
$unsafeDependency = (new DTRCalculationSafetyGate(
    $unsafeDependencyDb,
    [
        'sp_calculate_dtr' => approvedDefinition($root),
        'payroll_helper' => approvedDefinition($unsafeHelper),
    ]
))->verifyForCutoff('16-30', 'batch');
checkCalculatorGate(
    ($unsafeDependency['code'] ?? '') === 'dtr_calculator_unsafe'
        && ($unsafeDependency['required_by'] ?? '') === 'sp_calculate_dtr',
    'unsafe dependencies block their approved caller'
);

$commentOnly = "BEGIN\n-- COMMIT is documentation only\nSET @note = 'START TRANSACTION';\nUPDATE payroll_summary SET net_pay = net_pay;\nEND";
$commentResult = (new DTRCalculationSafetyGate(
    new FakeRoutineDatabase(['sp_calculate_indv_dtr' => $commentOnly]),
    ['sp_calculate_indv_dtr' => approvedDefinition($commentOnly)]
))->verifyForCutoff('16-30', 'individual');
checkCalculatorGate(
    ($commentResult['success'] ?? 0) === 1,
    'transaction words in comments and string literals do not create false positives'
);

echo "RESULT: {$checks} DTR calculator safety-gate checks passed.\n";
