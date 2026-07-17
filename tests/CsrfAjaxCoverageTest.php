<?php

declare(strict_types=1);

function csrf_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$csrf = (string)file_get_contents(__DIR__ . '/../includes/csrf.php');
csrf_check(str_contains($csrf, "['POST', 'PUT', 'PATCH', 'DELETE']"), 'all supported unsafe HTTP methods require CSRF validation');
csrf_check(str_contains($csrf, 'HTTP_X_CSRF_TOKEN'), 'unsafe requests accept the protected CSRF header');

foreach (['../assets/js/hris-global.js', '../hris/assets/js/hris-global.js'] as $script) {
    $contents = (string)file_get_contents(__DIR__ . '/' . $script);
    csrf_check(str_contains($contents, '.ajaxSend('), basename(dirname($script)) . ' injects CSRF after request-local callbacks are resolved');
    csrf_check(!str_contains($contents, '$.ajaxSetup({'), basename(dirname($script)) . ' no longer relies on overridable ajaxSetup beforeSend');
    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        csrf_check(str_contains($contents, "'{$method}'"), "global CSRF hook covers {$method}");
    }
}

echo "RESULT: Global AJAX CSRF coverage passed.\n";
