<?php

declare(strict_types=1);

$_SERVER['SCRIPT_NAME'] = '/hris/recruitment/staff/requisitions.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../recruitment/staff/requisitions.php');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/hris/recruitment/staff/requisitions.php';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';

require_once __DIR__ . '/../includes/session_security.php';
taascor_start_secure_session();
$_SESSION['taascor_access_level'] = 2;
$_SESSION['taascor_user_name'] = 'synthetic.recruitment';
$_SESSION['taascor_first_name'] = 'Synthetic';
$_SESSION['taascor_employee_full_name'] = 'Synthetic Recruitment Reviewer';
$_SESSION['taascor_access_description'] = 'HR';
$_SESSION['taascor_employee_email'] = 'recruitment@example.invalid';
$_SESSION['taascor_client'] = '';
$_SESSION['session_start'] = time();
$_SESSION['last_activity'] = time();
session_write_close();

ob_start();
require __DIR__ . '/../recruitment/staff/requisitions.php';
$html = (string)ob_get_clean();

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$check(substr_count($html, '<h1') === 1, 'staff queue renders one primary heading');
$check(str_contains($html, '>Requisition queue</h1>'), 'requisition route selects its governed queue');
$check(str_contains($html, 'Foundation mode is active'), 'staff queue visibly remains source-locked');
$check(str_contains($html, 'No active records'), 'staff queue does not fabricate operational counts');
$check(str_contains($html, 'No requisitions are available'), 'staff queue renders a clear empty state');
$check(!str_contains($html, '<form'), 'foundation queue exposes no data or mutation form');
$check(!str_contains($html, 'Candidate@Example.COM'), 'staff queue renders no candidate identity');
$check(str_contains($html, 'aria-label="Recruitment operations"'), 'queue navigation has an accessible label');
$check(str_contains($html, 'href="requisitions.php" aria-current="page"'), 'queue navigation identifies its current route');
$check(str_contains($html, 'noindex,nofollow'), 'internal staff queue is excluded from indexing');
$check(str_contains($html, '../../assets/img/favicon/favicon.ico'), 'staff queue uses the tracked favicon');
$check(!str_contains($html, '—'), 'staff queue visible copy contains no em dash');

$template = (string)file_get_contents(__DIR__ . '/../recruitment/staff/workspace-page.php');
$featureCheck = strpos($template, 'recruitment_staff_workspace_enabled()');
$databaseLoad = strpos($template, "config/db_connect.php");
$check($featureCheck !== false && $databaseLoad !== false && $featureCheck < $databaseLoad, 'staff queue checks the source gate before database access');
$check(str_contains($template, 'auth_require_role([1, 2])'), 'staff queue is restricted to Admin and HR');
$check(str_contains($template, "'requisitions' => \$repository->requisitionQueue()"), 'requisition queue uses its read-only repository projection');
$check(str_contains($template, "'jobs' => \$repository->jobQueue()"), 'job queue uses its read-only repository projection');
$check(str_contains($template, "'pipeline' => \$repository->applicationPipeline()"), 'pipeline uses its read-only repository projection');
$check(str_contains($template, "'exceptions' => \$repository->exceptionQueue()"), 'exception desk uses its read-only repository projection');

foreach (['index.php', 'requisitions.php', 'jobs.php', 'pipeline.php', 'exceptions.php'] as $route) {
    $source = (string)file_get_contents(__DIR__ . '/../recruitment/staff/' . $route);
    $check(str_contains($source, "require __DIR__ . '/workspace-page.php';"), "{$route} uses the governed staff workspace template");
}

$repository = (string)file_get_contents(__DIR__ . '/../recruitment/model/RecruitmentRepository.php');
$check(!str_contains($repository, 'email_ciphertext'), 'staff queue projections do not retrieve encrypted candidate email');
$check(!str_contains($repository, 'candidate_summary_ciphertext'), 'staff queue projections do not retrieve encrypted candidate summaries');
$check(str_contains($repository, 'max(1, min($limit, 100))'), 'staff queue result limits are bounded');

$css = (string)file_get_contents(__DIR__ . '/../recruitment/assets/recruitment.css');
$check(str_contains($css, '.recruitment-workspace-tabs'), 'staff queue includes a compact operations navigation');
$check(str_contains($css, 'content: attr(data-label)'), 'staff records retain field labels on mobile');
$check(str_contains($css, '@media (max-width: 767px)'), 'staff queue has an explicit mobile layout');
$check(str_contains($css, '@media (prefers-reduced-motion: reduce)'), 'staff queue respects reduced motion');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment staff operations surface checks passed.\n";
