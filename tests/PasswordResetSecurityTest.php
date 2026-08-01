<?php
$controller = (string)file_get_contents(__DIR__ . '/../login/controller/ForgotPasswordController.php');
$forgot = (string)file_get_contents(__DIR__ . '/../login/forgot-password.php');
$reset = (string)file_get_contents(__DIR__ . '/../login/reset-password.php');
$migration = (string)file_get_contents(
    __DIR__ . '/../dtr-format-engine/migrations/20260726_01_password_reset_security.sql'
);

$checks = [
    [!str_contains(strtoupper($controller), 'ALTER TABLE'), 'controller performs no request-time DDL'],
    [str_contains($controller, "hash('sha256', \$token)"), 'reset tokens are stored and queried by hash'],
    [str_contains($controller, "getenv('TAASCOR_APP_URL')"), 'reset links use configured canonical URL'],
    [str_contains($controller, 'resetRateLimited'), 'reset requests are rate limited'],
    [!str_contains($controller, '@mail('), 'mail delivery failures are not suppressed'],
    [str_contains($forgot, 'csrf_token') && str_contains($reset, 'csrf_token'), 'public reset requests require CSRF'],
    [str_contains($migration, 'password_reset_attempts'), 'password reset throttle schema is versioned'],
];

foreach ($checks as [$passed, $message]) {
    if (!$passed) {
        throw new RuntimeException('Password reset security check failed: ' . $message);
    }
    echo "PASS: {$message}\n";
}

echo "RESULT: Password reset security checks passed.\n";
