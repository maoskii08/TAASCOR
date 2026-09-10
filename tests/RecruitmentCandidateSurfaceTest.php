<?php

declare(strict_types=1);

require_once __DIR__ . '/../recruitment/includes/candidate_runtime.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

ob_start();
require __DIR__ . '/../recruitment/candidate/index.php';
$loginHtml = (string)ob_get_clean();
$accountPage = (string)file_get_contents(__DIR__ . '/../recruitment/candidate/account-page.php');
$controller = (string)file_get_contents(__DIR__ . '/../recruitment/candidate/actions/account.php');
$css = (string)file_get_contents(__DIR__ . '/../recruitment/assets/candidate.css');
$js = (string)file_get_contents(__DIR__ . '/../recruitment/assets/candidate.js');
$candidateFiles = $accountPage . $controller . $css . $js
    . (string)file_get_contents(__DIR__ . '/../recruitment/candidate/privacy.php');

$check(!recruitment_candidate_runtime_ready(), 'candidate runtime remains source-locked');
$check(substr_count(strtolower($loginHtml), '<h1') === 1, 'candidate login renders one primary heading');
$check(str_contains($loginHtml, '<fieldset disabled>'), 'locked preview disables every candidate form control');
$check(str_contains($loginHtml, 'does not collect or submit personal information'), 'locked preview states its data-collection boundary');
$check(str_contains($loginHtml, 'name="email" type="email"'), 'candidate email uses the correct input type');
$check(str_contains($loginHtml, 'autocomplete="current-password"'), 'candidate login preserves password-manager semantics');
$check(!str_contains($accountPage, 'placeholder='), 'candidate forms never use placeholders as labels');
$check(str_contains($accountPage, '<label for="candidate-email">'), 'candidate email has an explicit visible label');
$check(str_contains($accountPage, 'candidate privacy notice</a>'), 'registration links to the candidate privacy boundary');
$check(str_contains($accountPage, 'logo-wo-visio.svg'), 'candidate surface uses the tracked TAASCOR wordmark');
$check(str_contains($accountPage, 'favicon/favicon.ico'), 'candidate surface uses the tracked favicon');
$check(str_contains($accountPage, 'data-theme="light"'), 'light mode is explicit by default');
$check(str_contains($css, 'min-height: 100dvh'), 'candidate layout uses a stable mobile viewport unit');
$check(str_contains($css, '@media (max-width: 860px)'), 'candidate split layout has an explicit mobile collapse');
$check(str_contains($css, '@media (max-width: 420px)'), 'candidate surface has a narrow-phone refinement');
$check(str_contains($css, '@media (prefers-reduced-motion: reduce)'), 'candidate surface respects reduced motion');
$check(!str_contains($css, '100vh'), 'candidate layout does not use unstable mobile viewport height');
$check(str_contains($js, "setAttribute('aria-pressed'"), 'show-password control exposes its state accessibly');
$check(strpos($controller, 'recruitment_candidate_runtime_ready()') < strpos($controller, 'recruitment_candidate_service()'), 'controller fails closed before opening a database connection');
$check(str_contains($controller, "\$_SERVER['REQUEST_METHOD'] !== 'POST'"), 'candidate account mutations require POST');
$check(str_contains($controller, 'recruitment_candidate_csrf_is_valid'), 'candidate account mutations require candidate-realm CSRF');
$check(!str_contains($candidateFiles, '—') && !str_contains($candidateFiles, '–'), 'candidate visible source contains no em dash or en dash');

foreach (['register', 'recover', 'reset', 'verify'] as $route) {
    $source = (string)file_get_contents(__DIR__ . "/../recruitment/candidate/{$route}.php");
    $check(str_contains($source, "\$candidatePageMode = '{$route}'"), "candidate {$route} route selects its governed account mode");
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment candidate surface checks passed.\n";
