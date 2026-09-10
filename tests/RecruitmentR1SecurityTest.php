<?php

declare(strict_types=1);

require_once __DIR__ . '/../recruitment/model/RecruitmentSecurity.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentAuthorizationPolicy.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentDocumentPolicy.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$key = str_repeat('k', 32);
$ciphertext = RecruitmentSecurity::encrypt('candidate@example.invalid', $key);
$check($ciphertext !== 'candidate@example.invalid', 'candidate contact data is not stored as plaintext');
$check(RecruitmentSecurity::decrypt($ciphertext, $key) === 'candidate@example.invalid', 'candidate contact encryption round-trips');
$token = RecruitmentSecurity::issueToken();
$check(strlen($token['raw']) >= 40, 'candidate tokens contain sufficient random material');
$check(hash_equals($token['hash'], RecruitmentSecurity::tokenHash($token['raw'])), 'candidate tokens are persisted by hash');
$check(RecruitmentSecurity::validatePassword('Short1!') !== [], 'weak candidate passwords are rejected');
$check(RecruitmentSecurity::validatePassword('Qualified-Account-42!') === [], 'qualified candidate passwords are accepted');
$check(RecruitmentSecurity::loginRateLimited(5, 0), 'identifier throttling activates at the defined threshold');
$check(RecruitmentSecurity::loginRateLimited(0, 30), 'network throttling activates at the defined threshold');
$check(!RecruitmentSecurity::loginRateLimited(4, 29), 'requests below both throttle thresholds remain eligible');

$grant = [
    'username' => 'synthetic.recruiter',
    'capability' => 'candidate.view',
    'scope_type' => 'requisition',
    'scope_reference' => 'REQ-SYNTH-1',
    'expires_at' => '2099-01-01 00:00:00',
    'revoked_at' => null,
];
$check(RecruitmentAuthorizationPolicy::grantIsEffective($grant, 'synthetic.recruiter', 'candidate.view', 'requisition', 'REQ-SYNTH-1'), 'an exact active capability grant is effective');
$check(!RecruitmentAuthorizationPolicy::grantIsEffective($grant, 'synthetic.recruiter', 'offer.approve', 'requisition', 'REQ-SYNTH-1'), 'a grant cannot authorize another capability');
$check(!RecruitmentAuthorizationPolicy::grantIsEffective($grant, 'another.user', 'candidate.view', 'requisition', 'REQ-SYNTH-1'), 'a grant cannot authorize another user');
$expiredGrant = $grant;
$expiredGrant['expires_at'] = '2020-01-01 00:00:00';
$check(!RecruitmentAuthorizationPolicy::grantIsEffective($expiredGrant, 'synthetic.recruiter', 'candidate.view', 'requisition', 'REQ-SYNTH-1'), 'an expired grant fails closed');
$invalidExpiryGrant = $grant;
$invalidExpiryGrant['expires_at'] = 'not-a-date';
$check(!RecruitmentAuthorizationPolicy::grantIsEffective($invalidExpiryGrant, 'synthetic.recruiter', 'candidate.view', 'requisition', 'REQ-SYNTH-1'), 'a malformed grant expiry fails closed');
$check(!RecruitmentAuthorizationPolicy::makerCheckerSatisfied('same.user', 'same.user'), 'maker-checker rejects self-approval');
$check(RecruitmentAuthorizationPolicy::makerCheckerSatisfied('maker.user', 'checker.user'), 'maker-checker accepts distinct actors');

$check(RecruitmentDocumentPolicy::validateUpload('application/pdf', 2048, 'resume.pdf') === [], 'approved document shape passes validation');
$check(RecruitmentDocumentPolicy::validateUpload('text/html', 2048, 'resume.html') !== [], 'unapproved document media types fail closed');
$check(!RecruitmentDocumentPolicy::privateRootIsSafe('C:/site/public_html/private', 'C:/site/public_html'), 'document storage inside the web root is rejected');
$check(RecruitmentDocumentPolicy::privateRootIsSafe('C:/site-private/recruitment', 'C:/site/public_html'), 'document storage outside the web root is eligible');
$check(!RecruitmentDocumentPolicy::canRelease('quarantined', 'approved'), 'unscanned documents cannot be released');
$check(RecruitmentDocumentPolicy::canRelease('clean', 'approved'), 'only clean and approved documents can be released');

$service = (string)file_get_contents(__DIR__ . '/../recruitment/model/CandidateIdentityService.php');
$worker = (string)file_get_contents(__DIR__ . '/../recruitment/workers/notification-delivery-worker.php');
$migration = (string)file_get_contents(__DIR__ . '/../recruitment/migrations/20260909_02_recruitment_identity_controls.sql');
$csrf = (string)file_get_contents(__DIR__ . '/../recruitment/includes/candidate_csrf.php');
$check(str_contains($service, 'GENERIC_ACCOUNT_RESPONSE'), 'registration and recovery use an anti-enumeration response');
$check(str_contains($service, 'session_version = session_version + 1'), 'password reset and revocation invalidate prior candidate sessions');
$check(str_contains($service, "purpose = 'password_reset'"), 'prior password-reset tokens are invalidated');
$check(str_contains($worker, "PHP_SAPI !== 'cli'"), 'notification delivery worker is CLI-only');
$check(str_contains($worker, 'source-locked'), 'notification delivery remains source-locked');
$check(str_contains($migration, 'recruitment_staff_capability_grants'), 'R1 migration versions staff capability grants');
$check(str_contains($migration, 'recruitment_notification_outbox'), 'R1 migration versions the candidate notification outbox');
$check(str_contains($migration, "scan_status VARCHAR(32) NOT NULL DEFAULT 'quarantined'"), 'documents enter quarantine by default');
$check(str_contains($csrf, 'hash_equals'), 'candidate CSRF validation uses constant-time comparison');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment R1 security foundation checks passed.\n";
