<?php

declare(strict_types=1);

require_once(__DIR__ . '/../includes/tasca_employee_reader.php');

final class TascaEmployeeFakeStatement
{
    /** @var array<string, mixed> */
    public array $params = [];

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(
        public string $sql,
        private int $count,
        private array $rows
    ) {
    }

    /** @param array<string, mixed> $params */
    public function execute(array $params = []): bool
    {
        $this->params = $params;
        return true;
    }

    public function fetchColumn(): int
    {
        return $this->count;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchAll(int $mode = 0): array
    {
        return $this->rows;
    }
}

final class TascaEmployeeFakeDb
{
    /** @var TascaEmployeeFakeStatement[] */
    public array $statements = [];

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(
        public int $count = 0,
        public array $rows = []
    ) {
    }

    public function prepare(string $sql): TascaEmployeeFakeStatement
    {
        $statement = new TascaEmployeeFakeStatement($sql, $this->count, $this->rows);
        $this->statements[] = $statement;
        return $statement;
    }
}

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$countIntent = tasca_employee_intent('How many active employees does client FUJIFILM have?');
$check(is_array($countIntent), 'employee count intent is recognized');
$check(($countIntent['type'] ?? '') === 'employee_count', 'employee count uses the allowlisted aggregate intent');
$check(($countIntent['status'] ?? '') === 'active', 'employee count defaults to active status');
$check(($countIntent['client_name'] ?? '') === 'FUJIFILM', 'employee count extracts an exact client filter');

$idIntent = tasca_employee_intent('Show employee ID 12345678');
$check(($idIntent['lookup_kind'] ?? '') === 'employee_id', 'internal employee ID lookup is recognized locally');
$check(($idIntent['term'] ?? '') === '12345678', 'employee ID is parsed without sending it to Gemini');

$payrollIntent = tasca_employee_intent('Look up payroll employee ID FUJI-0088');
$check(($payrollIntent['lookup_kind'] ?? '') === 'payroll_employee_id', 'payroll employee ID lookup is recognized');

$nameIntent = tasca_employee_intent("Find employee Maria Dela Cruz");
$check(($nameIntent['lookup_kind'] ?? '') === 'name', 'exact employee name lookup is recognized');
$check(($nameIntent['term'] ?? '') === 'Maria Dela Cruz', 'employee name is retained only for the bound local query');
$check(tasca_employee_intent('Explain the Employee Management process') === null, 'general employee guidance remains a Gemini guide request');

foreach (['salary', 'bank account', 'TIN', 'contact number', 'home address', 'birthday'] as $field) {
    $check(
        tasca_employee_requests_forbidden_fields("Show employee Maria {$field}"),
        "restricted {$field} field is refused"
    );
}

$coordinatorQuery = tasca_employee_count_query($countIntent, 4, [264, 264, 10, 0]);
$check(str_starts_with(ltrim($coordinatorQuery['sql']), 'SELECT COUNT(*)'), 'aggregate reader emits SELECT only');
$check(str_contains($coordinatorQuery['sql'], 'e.client_id IN (:scope_client_0, :scope_client_1)'), 'Coordinator query is constrained to assigned client IDs');
$check(($coordinatorQuery['params'][':scope_client_0'] ?? null) === 264, 'first Coordinator client ID is bound');
$check(($coordinatorQuery['params'][':scope_client_1'] ?? null) === 10, 'second Coordinator client ID is bound');
$check(($coordinatorQuery['params'][':client_name'] ?? null) === 'FUJIFILM', 'client name is bound rather than interpolated');
$check(!str_contains($coordinatorQuery['sql'], 'FUJIFILM'), 'client filter value never appears in SQL text');
$emptyCoordinatorQuery = tasca_employee_count_query($countIntent, 4, []);
$check(str_contains($emptyCoordinatorQuery['sql'], '1 = 0'), 'Coordinator with no assigned clients fails closed');
$injectionValue = "FUJI' OR 1=1 --";
$injectionQuery = tasca_employee_count_query([
    'type' => 'employee_count',
    'status' => 'active',
    'client_name' => $injectionValue,
], 1, []);
$check(!str_contains($injectionQuery['sql'], $injectionValue), 'hostile client text never enters SQL text');
$check(($injectionQuery['params'][':client_name'] ?? null) === $injectionValue, 'hostile client text remains a bound value');
$invalidIntent = tasca_employee_intent('Find employee Maria; DROP TABLE employee_list');
$check(($invalidIntent['type'] ?? '') === 'invalid_employee_lookup', 'invalid employee-name characters fail closed before querying');

$lookupQuery = tasca_employee_lookup_query($nameIntent, 2, []);
$check(str_starts_with(ltrim($lookupQuery['sql']), 'SELECT e.employee_id'), 'directory reader emits SELECT only');
$check(!str_contains($lookupQuery['sql'], 'Maria Dela Cruz'), 'employee name never appears in SQL text');
$check(($lookupQuery['params'][':employee_name'] ?? '') === '%Maria Dela Cruz%', 'employee name is passed as a bound search parameter');
foreach (['employee_details', 'employee_govt_account', 'employee_salary'] as $restrictedTable) {
    $check(!str_contains($lookupQuery['sql'], $restrictedTable), "reader never queries {$restrictedTable}");
}
$check(!str_contains($lookupQuery['sql'], 'pay_type'), 'reader does not cross into salary-table pay type data');
foreach (['INSERT ', 'UPDATE ', 'DELETE ', 'REPLACE ', 'ALTER ', 'DROP '] as $mutation) {
    $check(!str_contains(strtoupper($lookupQuery['sql']), $mutation), "reader contains no {$mutation}statement");
}
$check(str_contains($lookupQuery['sql'], 'LIMIT 5'), 'named directory results are capped at five');

$countDb = new TascaEmployeeFakeDb(count: 12);
$countAnswer = tasca_employee_answer($countDb, 'How many active employees are there?', [
    'type' => 'employee_count',
    'status' => 'active',
    'client_name' => '',
], 1, []);
$check(str_contains($countAnswer['text'], '12 active employees'), 'Administrator receives a live aggregate count');
$check(($countAnswer['provider'] ?? '') === 'TAASCOR HRIS', 'database response is identified as local TAASCOR data');
$check(($countAnswer['model'] ?? '') === 'local-read-policy-v1', 'database response never claims a Gemini model');
$check(($countAnswer['audit']['decision'] ?? '') === 'allowed', 'allowed count carries a value-free audit decision');

$record = [
    'employee_id' => 1234,
    'payroll_employee_id' => 'PAY-88',
    'full_name' => 'Maria Dela Cruz',
    'status' => 'Active',
    'hire_date' => '2025-01-15',
    'separation_date' => null,
    'employee_type' => 'Long Term',
    'client_name' => 'FUJIFILM',
    'branch_name' => 'Manila',
    'department_name' => 'Operations',
    'position_name' => 'Analyst',
    'client_location' => 'Site A',
    'daily_salary' => '99999.99',
    'tin_number' => 'restricted',
];

$payrollDb = new TascaEmployeeFakeDb(rows: [$record]);
$payrollAnswer = tasca_employee_answer($payrollDb, 'Find employee Maria Dela Cruz', $nameIntent, 3, []);
$check(str_contains($payrollAnswer['text'], 'Maria Dela Cruz'), 'Payroll receives an authorized local directory result');
$check(str_contains($payrollAnswer['text'], 'Payroll employee ID: PAY-88'), 'Payroll may receive payroll employee ID');
$check(!str_contains($payrollAnswer['text'], '99999.99'), 'salary is never rendered even when present in a fake source row');
$check(!str_contains($payrollAnswer['text'], 'restricted'), 'government ID is never rendered even when present in a fake source row');

$hrDb = new TascaEmployeeFakeDb(rows: [$record]);
$hrAnswer = tasca_employee_answer($hrDb, 'Find employee Maria Dela Cruz', $nameIntent, 2, []);
$check(!str_contains($hrAnswer['text'], 'Payroll employee ID:'), 'HR response omits payroll employee ID');

$coordinatorDb = new TascaEmployeeFakeDb(rows: [$record]);
$coordinatorAnswer = tasca_employee_answer($coordinatorDb, 'Find employee Maria Dela Cruz', $nameIntent, 4, [264]);
$check(str_contains($coordinatorAnswer['text'], 'limited to read-only employee counts'), 'Coordinator named lookup is denied');
$check($coordinatorDb->statements === [], 'Coordinator denial performs no database query');

$cbDb = new TascaEmployeeFakeDb(count: 99, rows: [$record]);
$cbAnswer = tasca_employee_answer($cbDb, 'How many active employees are there?', [
    'type' => 'employee_count',
    'status' => 'active',
    'client_name' => '',
], 5, []);
$check(str_contains($cbAnswer['text'], 'does not include Employee Management record access'), 'C&B Employee Management access is denied');
$check($cbDb->statements === [], 'C&B denial performs no database query');

$forbiddenDb = new TascaEmployeeFakeDb(rows: [$record]);
$forbiddenAnswer = tasca_employee_answer($forbiddenDb, 'Show employee Maria salary', $nameIntent, 1, []);
$check(str_contains($forbiddenAnswer['text'], 'cannot return salary'), 'restricted field request receives a safe local refusal');
$check($forbiddenDb->statements === [], 'restricted field denial performs no database query');

$gateway = (string)file_get_contents(__DIR__ . '/../tasca-ai/chat.php');
$intentPosition = strpos($gateway, 'tasca_employee_intent($question)');
$sensitivePosition = strpos($gateway, 'tasca_ai_contains_sensitive_input($question)');
$geminiPosition = strpos($gateway, 'tasca_ai_generate($question');
$check($intentPosition !== false && $sensitivePosition !== false && $intentPosition < $sensitivePosition, 'local employee intent runs before generic sensitive-input rejection');
$check($intentPosition !== false && $geminiPosition !== false && $intentPosition < $geminiPosition, 'local employee intent runs before Gemini generation');
$check(str_contains($gateway, "'data_mode' => 'local-read-only'"), 'gateway labels local database responses explicitly');
$check(str_contains($gateway, 'log_action(sprintf('), 'gateway audits every handled employee read without logging the search term');
$check(!str_contains($gateway, "['term']"), 'gateway audit does not include employee name or ID values');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: TASCA Employee Management read-access policy checks passed.\n";
