<?php

declare(strict_types=1);

require_once(__DIR__ . '/../includes/tasca_gemini.php');

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$root = dirname(__DIR__);
$config = tasca_ai_load_config($root);
$check($config['model'] === 'gemini-3.5-flash', 'current Gemini 3.5 Flash model is selected locally');

$check(tasca_ai_contains_sensitive_input('Show employee ID 12345678'), 'employee identifiers are blocked');
$check(tasca_ai_contains_sensitive_input('Email the result to employee@example.com'), 'email addresses are blocked');
$check(tasca_ai_contains_sensitive_input('Salary: PHP 55,000'), 'compensation values are blocked');
$check(!tasca_ai_contains_sensitive_input('Explain the approved payroll review process'), 'general process guidance remains allowed');

$coordinatorInstruction = tasca_ai_system_instruction(4, []);
$check(str_contains($coordinatorInstruction, 'Coordinator role is read-only'), 'Coordinator system instruction is explicitly read-only');
$check(str_contains($coordinatorInstruction, 'Never claim that you viewed'), 'Gemini cannot claim record access or mutation');
$check(str_contains($coordinatorInstruction, 'Do not request, reveal, infer'), 'Gemini is instructed not to handle private records');

$secretPath = $root . '/.env.gemini';
$envExample = (string)file_get_contents($root . '/.env.example');
$check(str_contains($envExample, 'TAASCOR_GEMINI_ENV_FILE='), 'configuration template supports an external private key file');
$check(str_contains($envExample, 'GEMINI_API_KEY='), 'configuration template documents the runtime key variable');
$check(!preg_match('/GEMINI_API_KEY=\S+/', $envExample), 'configuration template contains no Gemini key');

if (is_file($secretPath)) {
    $check($config['configured'] === true, 'private Gemini configuration resolves without exposing the key');
    $check(strlen($config['api_key']) >= 20, 'resolved Gemini credential is populated');
    $check(str_contains((string)shell_exec('git check-ignore ' . escapeshellarg($secretPath)), '.env.gemini'), 'local Gemini pointer file is ignored by Git');
    $tracked = (string)shell_exec('git ls-files -- ' . escapeshellarg($secretPath));
    $check(trim($tracked) === '', 'local Gemini pointer file is not tracked');
} else {
    echo "PASS: private Gemini connectivity check is optional when no local pointer exists\n";
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: TASCA Gemini integration checks passed.\n";
