<?php

declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/hris/recruitment/index.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../recruitment/index.php');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/hris/recruitment/';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';

require_once __DIR__ . '/../includes/session_security.php';
taascor_start_secure_session();
$_SESSION['taascor_access_level'] = 2;
$_SESSION['taascor_user_name'] = 'synthetic.hr';
$_SESSION['taascor_first_name'] = 'Synthetic';
$_SESSION['taascor_employee_full_name'] = 'Synthetic HR Reviewer';
$_SESSION['taascor_access_description'] = 'HR';
$_SESSION['taascor_employee_email'] = 'synthetic@example.invalid';
$_SESSION['taascor_client'] = '';
$_SESSION['session_start'] = time();
$_SESSION['last_activity'] = time();
session_write_close();

ob_start();
require __DIR__ . '/../recruitment/index.php';
$html = (string)ob_get_clean();

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$check(str_contains($html, '<h1 class="recruitment-title"'), 'workspace renders one explicit primary heading');
$check(str_contains($html, 'One hiring record, from request to first day.'), 'workspace renders the recruitment lifecycle promise');
$check(str_contains($html, 'Foundation mode'), 'workspace visibly identifies its foundation release state');
$check(str_contains($html, 'Not active'), 'workspace does not present fabricated recruitment metrics');
$check(str_contains($html, 'No real candidate data'), 'workspace displays the production release boundary');
$check(!str_contains($html, '<form'), 'foundation workspace exposes no mutating form');
$check(!str_contains($html, 'Candidate@Example.COM'), 'test candidate identity does not leak into rendered output');
$check(str_contains($html, 'aria-label="Recruitment workspace summary"'), 'workspace summary has an accessible name');
$check(str_contains($html, 'aria-labelledby="workflow-title"'), 'workflow section is associated with its heading');
$check(str_contains($html, 'aria-label="Open navigation"'), 'mobile navigation control has an accessible name');
$check(str_contains($html, '../assets/js/css/core.css'), 'workspace uses the tracked HRIS core stylesheet');
$check(str_contains($html, 'assets/recruitment.js'), 'workspace loads its dependency-free interaction layer');
$check(!str_contains($html, '../assets/vendor/'), 'workspace does not depend on absent vendor assets');
$check(!str_contains($html, '—'), 'workspace visible copy contains no em dash');

$css = (string)file_get_contents(__DIR__ . '/../recruitment/assets/recruitment.css');
$check(str_contains($css, '@media (max-width: 767px)'), 'workspace includes an explicit mobile layout');
$check(str_contains($css, '@media (prefers-reduced-motion: reduce)'), 'workspace respects reduced-motion preferences');
$check(str_contains($css, 'transform: translateX(-102%)'), 'mobile sidebar starts outside the viewport');
$check(!preg_match('/min-width:\s*[4-9][0-9]{2,}px/i', $css), 'workspace CSS does not force a wide mobile canvas');

$javascript = (string)file_get_contents(__DIR__ . '/../recruitment/assets/recruitment.js');
$check(str_contains($javascript, "root.classList.toggle('layout-menu-expanded')"), 'mobile navigation supports explicit open and close state');
$check(str_contains($javascript, "event.key === 'Escape'"), 'mobile navigation closes with Escape');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment workspace render checks passed.\n";
