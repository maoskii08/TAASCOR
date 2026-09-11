<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
        return;
    }
    echo "PASS: {$message}\n";
};

$pages = ['login/index.php', 'login/forgot-password.php', 'login/reset-password.php'];
foreach ($pages as $page) {
    $source = (string)file_get_contents($root . '/' . $page);
    $check(!str_contains($source, '../assets/vendor/'), "{$page} has no deployment-only vendor reference");
    $check(!str_contains($source, 'user-scalable=no'), "{$page} allows browser zoom");
    $check(str_contains($source, '../assets/img/favicon/favicon.ico'), "{$page} uses the tracked HRIS favicon");
    $check(substr_count($source, '<h1') === 1, "{$page} renders one primary heading");

    preg_match_all('/(?:href|src)=["\']([^"\']+)["\']/', $source, $matches);
    foreach ($matches[1] ?? [] as $reference) {
        if (
            str_starts_with($reference, 'http://')
            || str_starts_with($reference, 'https://')
            || str_starts_with($reference, '#')
            || str_contains($reference, '<?')
        ) {
            continue;
        }
        $referencePath = parse_url($reference, PHP_URL_PATH);
        if (!is_string($referencePath) || $referencePath === '' || str_ends_with($referencePath, '.php')) {
            continue;
        }
        if (str_ends_with($referencePath, '/')) {
            continue;
        }
        $resolved = realpath(dirname($root . '/' . $page) . '/' . $referencePath);
        $check($resolved !== false && is_file($resolved), "{$page} asset exists: {$referencePath}");
    }
}

$login = (string)file_get_contents($root . '/login/index.php');
$check(str_contains($login, 'for="loginUsername"'), 'login username has an explicit visible label');
$check(str_contains($login, 'autocomplete="username"'), 'login preserves username password-manager semantics');
$check(str_contains($login, 'for="loginPassword"'), 'login password has an explicit visible label');
$loginStyles = (string)file_get_contents($root . '/assets/css/login.css');
$check(str_contains($loginStyles, ':focus-visible'), 'login stylesheet exposes keyboard focus');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: HRIS authentication asset and accessibility checks passed.\n";
