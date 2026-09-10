<?php

declare(strict_types=1);

require_once __DIR__ . '/../recruitment/includes/feature.php';
require_once __DIR__ . '/../recruitment/includes/candidate_session.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentPolicy.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

putenv('TAASCOR_RECRUITMENT_PUBLIC_JOBS_ENABLED=1');
putenv('TAASCOR_RECRUITMENT_STAFF_ENABLED=1');
putenv('TAASCOR_RECRUITMENT_CANDIDATE_ENABLED=1');
putenv('TAASCOR_RECRUITMENT_MUTATIONS_ENABLED=1');

$check(recruitment_foundation_mode(), 'source release stage remains foundation');
$check(!recruitment_public_jobs_enabled(), 'environment cannot activate the public jobs feed in foundation mode');
$check(!recruitment_staff_workspace_enabled(), 'environment cannot activate staff data access in foundation mode');
$check(!recruitment_candidate_identity_enabled(), 'environment cannot activate candidate identity in foundation mode');
$check(!recruitment_mutations_enabled(), 'environment cannot activate recruitment mutations in foundation mode');

$check(
    RecruitmentPolicy::canTransition('requisition', 'draft', 'pending_approval'),
    'a draft requisition can enter approval'
);
$check(
    !RecruitmentPolicy::canTransition('requisition', 'draft', 'approved'),
    'a draft requisition cannot bypass approval'
);
$check(
    RecruitmentPolicy::canTransition('job', 'published', 'closed'),
    'a published job can be closed'
);
$check(
    !RecruitmentPolicy::canTransition('job', 'closed', 'published'),
    'a closed job cannot be republished without a new governed record'
);
$check(
    RecruitmentPolicy::canTransition('application', 'ready_for_conversion', 'converted'),
    'a conversion-ready application can become an employee record'
);
$check(
    !RecruitmentPolicy::canTransition('application', 'submitted', 'converted'),
    'a submitted application cannot bypass recruitment and onboarding'
);
$check(
    RecruitmentPolicy::candidateStatus('declined') === 'Application closed',
    'candidate wording does not expose an internal rejection label'
);
$check(
    RecruitmentPolicy::candidateStatus('unknown_internal_state') === 'Status unavailable',
    'unknown statuses fail closed in the candidate view'
);

$activeAt = new DateTimeImmutable('2026-09-09 04:00:00', new DateTimeZone('UTC'));
$check(
    RecruitmentPolicy::publicJobCanAcceptApplications([
        'publication_status' => 'published',
        'opens_at' => '2026-09-01 00:00:00',
        'closes_at' => '2026-09-10 00:00:00',
    ], $activeAt),
    'published job inside its date window can accept applications'
);
$check(
    !RecruitmentPolicy::publicJobCanAcceptApplications([
        'publication_status' => 'draft',
        'opens_at' => null,
        'closes_at' => null,
    ], $activeAt),
    'draft job cannot accept applications'
);
$check(
    !RecruitmentPolicy::publicJobCanAcceptApplications([
        'publication_status' => 'published',
        'opens_at' => null,
        'closes_at' => '2026-09-09 04:00:00',
    ], $activeAt),
    'job stops accepting applications at its closing instant'
);

$lookupKey = str_repeat('k', 32);
$check(
    RecruitmentPolicy::normalizeEmail('  Candidate@Example.COM ') === 'candidate@example.com',
    'candidate email normalization is deterministic'
);
$check(
    RecruitmentPolicy::emailLookupHash('Candidate@Example.COM', $lookupKey)
        === RecruitmentPolicy::emailLookupHash(' candidate@example.com ', $lookupKey),
    'email lookup hash does not depend on case or surrounding spaces'
);

$check(
    recruitment_candidate_cookie_name() !== session_name(),
    'candidate cookie name remains distinct from the current staff PHP session name'
);
$check(
    recruitment_candidate_cookie_path('/hris/recruitment/candidate/login.php') === '/hris/recruitment/candidate/',
    'candidate cookie is scoped to the mounted candidate route'
);
$candidateCookie = recruitment_candidate_cookie_options('/hris/recruitment/candidate/login.php', true);
$check(
    $candidateCookie['secure'] && $candidateCookie['httponly'] && $candidateCookie['samesite'] === 'Lax',
    'candidate cookie contract is Secure, HttpOnly, and SameSite Lax'
);

$migration = (string)file_get_contents(__DIR__ . '/../recruitment/migrations/20260909_01_recruitment_foundation.sql');
foreach ([
    'recruitment_candidates',
    'recruitment_candidate_tokens',
    'recruitment_auth_attempts',
    'recruitment_requisitions',
    'recruitment_requisition_approvals',
    'recruitment_jobs',
    'recruitment_applications',
    'recruitment_application_events',
    'recruitment_audit_events',
] as $table) {
    $check(str_contains($migration, "CREATE TABLE IF NOT EXISTS {$table}"), "migration creates {$table} additively");
}
foreach (['employee_list', 'dtr_upload', 'payroll_summary', 'taascor_user_access'] as $protectedTable) {
    $mutatingPattern = '/(?:UPDATE|DELETE\s+FROM|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|INSERT\s+INTO)\s+' . preg_quote($protectedTable, '/') . '\b/i';
    $check(!preg_match($mutatingPattern, $migration), "migration does not mutate protected table {$protectedTable}");
}

$endpoint = (string)file_get_contents(__DIR__ . '/../recruitment/public/jobs.php');
$gatePosition = strpos($endpoint, 'if (!recruitment_public_jobs_enabled())');
$databasePosition = strpos($endpoint, "config/db_connect.php");
$check(
    $gatePosition !== false && $databasePosition !== false && $gatePosition < $databasePosition,
    'public jobs endpoint fails closed before opening a database connection'
);
$check(!str_contains($endpoint, 'Access-Control-Allow-Origin: *'), 'public jobs endpoint does not enable wildcard CORS');

require __DIR__ . '/../config/page-permissions.php';
$check(isset($page_map[89]) && $page_map[89][0] === 'Recruitment', 'permission map registers the Recruitment module');
$check(in_array(89, $pages['1'], true), 'Admin can see the Recruitment module');
$check(in_array(89, $pages['2'], true), 'HR can see the Recruitment module');
$check(!in_array(89, $pages['3'], true), 'Payroll cannot see the Recruitment module');
$check(!in_array(89, $pages['4'], true), 'Coordinator cannot see the Recruitment module');
$check(!in_array(89, $pages['5'], true), 'C&B cannot see the Recruitment module');

$workspace = (string)file_get_contents(__DIR__ . '/../recruitment/index.php');
$check(str_contains($workspace, 'auth_require_role([1, 2])'), 'Recruitment workspace enforces Admin or HR access server-side');
$check(str_contains($workspace, 'recruitment_staff_workspace_enabled()'), 'Recruitment workspace checks the source-backed feature gate');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment foundation checks passed.\n";
