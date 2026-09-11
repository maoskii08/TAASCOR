<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/mysql-config.php';
require_once __DIR__ . '/../recruitment/model/CandidateIdentityService.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentWorkflowService.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentOfferOnboardingService.php';
require_once __DIR__ . '/../recruitment/model/RecruitmentConversionService.php';

$hostParts = explode(':', trim((string) HOST), 2);
$host = strtolower(trim($hostParts[0]));
$port = isset($hostParts[1]) && ctype_digit($hostParts[1]) ? $hostParts[1] : '3306';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || !str_starts_with((string) DATABASE, 'taascor_codex_')) {
    fwrite(STDERR, "FAIL: lifecycle qualification requires a loopback taascor_codex_* schema.\n");
    exit(2);
}

$db = new PDO("mysql:host={$host};port={$port};dbname=" . DATABASE . ';charset=utf8mb4', USER, PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};
$rejects = static function (callable $action, string $message) use ($check): void {
    try {
        $action();
        $check(false, $message);
    } catch (DomainException|InvalidArgumentException $expected) {
        $check(true, $message);
    }
};

final class SyntheticEmployeeAdapter implements EmployeeConversionAdapter
{
    public bool $duplicateClear = true;
    public bool $forceMismatch = false;
    private ?array $record = null;

    public function normalizePayload(array $employeePayload): array
    {
        ksort($employeePayload);
        return $employeePayload;
    }

    public function duplicateCheck(array $employeePayload): array
    {
        return ['clear' => $this->duplicateClear, 'evidence' => ['synthetic' => true, 'matched' => !$this->duplicateClear]];
    }

    public function createEmployee(array $employeePayload): string
    {
        $this->record = $this->normalizePayload($employeePayload);
        return 'SYNTHETIC-EMPLOYEE-' . substr(hash('sha256', RecruitmentContentPolicy::canonicalJson($this->record)), 0, 16);
    }

    public function employeeSnapshot(string $employeeReference): ?array
    {
        if ($this->record === null) {
            return null;
        }
        return $this->forceMismatch ? $this->record + ['unexpected' => true] : $this->record;
    }
}

$lookupKey = str_repeat('lifecycle-lookup-', 3);
$dataKey = str_repeat('lifecycle-data-', 3);
$maker = 'synthetic.maker';
$approver = 'synthetic.approver';
$outsider = 'synthetic.unassigned';
$run = bin2hex(random_bytes(4));

$grant = $db->prepare(
    "INSERT INTO recruitment_staff_capability_grants
        (username, capability, scope_type, scope_reference, granted_by_username, reason)
     VALUES (:username, :capability, 'global', '*', 'qualification.bootstrap', 'Synthetic qualification')
     ON DUPLICATE KEY UPDATE revoked_at = NULL, expires_at = NULL, reason = VALUES(reason)"
);
foreach ([$maker, $approver] as $actor) {
    foreach (RecruitmentAuthorizationPolicy::CAPABILITIES as $capability) {
        $grant->execute(['username' => $actor, 'capability' => $capability]);
    }
}

$access = new RecruitmentStaffAccessService($db);
$outbox = new RecruitmentNotificationOutbox($db);
$identity = new CandidateIdentityService($db, $lookupKey, $dataKey, $outbox);
$workflow = new RecruitmentWorkflowService($db, $dataKey, $access, $outbox);
$offerOnboarding = new RecruitmentOfferOnboardingService($db, $dataKey, $access, $outbox);
$employeeAdapter = new SyntheticEmployeeAdapter();
$conversion = new RecruitmentConversionService($db, $dataKey, $access, $employeeAdapter, true);

$rejects(
    fn () => $workflow->createRequisition([
        'requisition_code' => 'SYNTH-' . $run,
        'title' => 'Synthetic qualification role',
        'headcount' => 1,
        'employment_type' => 'project_based',
        'hiring_owner_username' => 'synthetic.owner',
        'recruitment_owner_username' => $maker,
        'business_reason' => 'Synthetic lifecycle qualification only.',
        'target_start_date' => gmdate('Y-m-d', strtotime('+30 days')),
    ], $outsider),
    'unassigned staff cannot create a requisition'
);

$requisition = $workflow->createRequisition([
    'requisition_code' => 'SYNTH-' . $run,
    'title' => 'Synthetic qualification role',
    'headcount' => 1,
    'employment_type' => 'project_based',
    'hiring_owner_username' => 'synthetic.owner',
    'recruitment_owner_username' => $maker,
    'business_reason' => 'Synthetic lifecycle qualification only.',
    'target_start_date' => gmdate('Y-m-d', strtotime('+30 days')),
], $maker);
$workflow->transitionRequisition($requisition, 'pending_approval', 'Ready for independent review.', $maker);
$rejects(fn () => $workflow->transitionRequisition($requisition, 'approved', 'Self approval attempt.', $maker), 'requisition maker cannot self-approve');
$workflow->transitionRequisition($requisition, 'approved', 'Synthetic approval evidence accepted.', $approver);
$check((string) $db->query("SELECT status FROM recruitment_requisitions WHERE public_id=" . $db->quote($requisition))->fetchColumn() === 'approved', 'independent requisition approval succeeds');

$jobInput = [
    'slug' => 'synthetic-qualification-' . $run,
    'title' => 'Synthetic qualification role',
    'location_label' => 'Synthetic site',
    'work_arrangement' => 'onsite',
    'employment_type' => 'project_based',
    'summary' => 'A synthetic role used only for disposable qualification.',
    'description' => 'This synthetic job description exists only for qualification and creates no real vacancy.',
    'requirements' => 'Synthetic qualification requirements only.',
    'hiring_process' => 'Synthetic review, interview, offer, and onboarding.',
    'opens_at' => gmdate('Y-m-d H:i:s', strtotime('-1 hour')),
    'closes_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days')),
];
$job = $workflow->createJobDraft($requisition, $jobInput, $maker);
$workflow->replaceScorecardCriteria($job, [
    ['code' => 'communication', 'label' => 'Communication', 'guidance' => 'Use only observed synthetic evidence.', 'weight_bps' => 5000],
    ['code' => 'role_fit', 'label' => 'Role fit', 'guidance' => 'Use only observed synthetic evidence.', 'weight_bps' => 5000],
], $maker);
$workflow->transitionJob($job, 'published', 'Publish only to the disposable qualification flow.', $maker);
$check((int) $db->query("SELECT COUNT(*) FROM recruitment_job_publication_events WHERE job_id=(SELECT job_id FROM recruitment_jobs WHERE public_id=" . $db->quote($job) . ")")->fetchColumn() >= 2, 'job publication retains versioned event evidence');

$email = "lifecycle.{$run}@example.invalid";
$identity->register($email, 'Synthetic-Lifecycle-42!', 'synthetic-notice-v1');
$candidateRow = $db->query("SELECT candidate_id FROM recruitment_candidates WHERE email_lookup_hash=" . $db->quote(RecruitmentPolicy::emailLookupHash($email, $lookupKey)))->fetch();
$candidateId = (int) $candidateRow['candidate_id'];
$notification = $db->query("SELECT payload_json FROM recruitment_notification_outbox WHERE candidate_id={$candidateId} AND message_type='verify_email' ORDER BY notification_id DESC LIMIT 1")->fetch();
$token = RecruitmentSecurity::decrypt((string) json_decode((string) $notification['payload_json'], true, 512, JSON_THROW_ON_ERROR)['token_ciphertext'], $dataKey);
$check($identity->verifyEmail($token), 'candidate email ownership is verified before application');

$application = $workflow->saveApplicationDraft($candidateId, $job, [
    'full_name' => 'Synthetic Candidate',
    'phone' => '+639000000000',
    'current_city' => 'Synthetic City',
    'experience_summary' => 'Disposable qualification record only.',
    'eligibility_confirmed' => true,
], 'synthetic-notice-v1');
$rejects(fn () => $workflow->submitApplication($candidateId + 9999, $application, false), 'another candidate cannot submit the application');
$workflow->submitApplication($candidateId, $application, false);
$workflow->assignApplication($application, $maker, 'recruiter', $maker);
$noteId = $workflow->addStaffNote($application, 'Synthetic restricted staff note.', 'recruitment', $maker);
$ciphertext = (string) $db->query("SELECT note_ciphertext FROM recruitment_application_staff_notes WHERE note_id={$noteId}")->fetchColumn();
$check(!str_contains($ciphertext, 'Synthetic restricted staff note'), 'staff notes are encrypted at rest');
$workflow->transitionApplication($application, 'reviewing', 'Your application is under review.', 'screening_started', $maker);
$workflow->transitionApplication($application, 'shortlisted', 'Your application is moving forward.', 'screening_passed', $maker);

$interview = $workflow->scheduleInterview($application, [
    'starts_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days')),
    'ends_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days +1 hour')),
    'interview_type' => 'panel',
    'timezone_name' => 'Asia/Manila',
    'location_type' => 'video',
    'location' => 'https://example.invalid/synthetic-interview',
    'instructions' => 'Synthetic interview only.',
    'accessibility_route' => 'support@example.invalid',
], [
    ['type' => 'candidate', 'reference' => (string) $candidateId, 'panel_role' => 'candidate'],
    ['type' => 'staff', 'reference' => $maker, 'panel_role' => 'panelist'],
], $maker);
$workflow->respondToInterview($candidateId, $interview, 'confirmed');
$workflow->submitScorecard($interview, [
    'communication' => ['rating' => 4, 'evidence' => 'Synthetic evidence for communication.'],
    'role_fit' => ['rating' => 4, 'evidence' => 'Synthetic evidence for role fit.'],
], 'yes', $maker);
$workflow->transitionApplication($application, 'conditional_offer', 'An offer is being prepared.', 'panel_recommendation', $maker);

$terms = [
    'position_title' => 'Synthetic qualification role',
    'employment_type' => 'project_based',
    'start_date' => gmdate('Y-m-d', strtotime('+30 days')),
    'work_location' => 'Synthetic site',
    'work_arrangement' => 'onsite',
    'compensation_currency' => 'PHP',
    'compensation_amount' => '10000.00',
    'pay_frequency' => 'monthly',
    'conditions' => 'Synthetic terms only; not an employment offer.',
];
$rejects(fn () => $offerOnboarding->prepareOffer($application, $terms, gmdate('Y-m-d H:i:s', strtotime('-1 hour')), $maker), 'expired offers cannot be prepared');
$offer = $offerOnboarding->prepareOffer($application, $terms, gmdate('Y-m-d H:i:s', strtotime('+3 days')), $maker);
$offerOnboarding->submitOfferForApproval($offer, 'Ready for independent approval.', $maker);
$rejects(fn () => $offerOnboarding->decideOffer($offer, 'approved', 'Self approval attempt.', $maker), 'offer preparer cannot self-approve');
$offerOnboarding->decideOffer($offer, 'approved', 'Synthetic terms independently approved.', $approver);
$offerOnboarding->deliverOffer($offer, 'Deliver to the synthetic candidate inbox.', $maker);
$offerOnboarding->respondToOffer($candidateId, $offer, 'accepted', 'Synthetic acceptance only.');

$rejects(fn () => $offerOnboarding->createOnboardingTemplate([
    'template_name' => 'Cyclic synthetic template', 'worker_type' => 'project', 'version_number' => 1,
], [
    ['item_code' => 'a', 'title' => 'Synthetic A', 'purpose_text' => 'Synthetic purpose only.', 'visibility_text' => 'Visible to synthetic reviewers.', 'item_type' => 'acknowledgement', 'classification' => 'standard', 'dependency_item_code' => 'b'],
    ['item_code' => 'b', 'title' => 'Synthetic B', 'purpose_text' => 'Synthetic purpose only.', 'visibility_text' => 'Visible to synthetic reviewers.', 'item_type' => 'acknowledgement', 'classification' => 'standard', 'dependency_item_code' => 'a'],
], $maker), 'cyclic onboarding dependencies are rejected');
$template = $offerOnboarding->createOnboardingTemplate([
    'template_name' => 'Synthetic onboarding template ' . $run,
    'worker_type' => 'project',
    'location_scope' => '*',
    'client_scope' => '*',
    'version_number' => 1,
], [[
    'item_code' => 'policy_ack',
    'title' => 'Synthetic policy acknowledgement',
    'purpose_text' => 'Confirm the synthetic qualification policy.',
    'visibility_text' => 'Visible to the candidate and synthetic HR reviewer.',
    'item_type' => 'acknowledgement',
    'classification' => 'standard',
    'due_offset_days' => 1,
    'required_flag' => true,
]], $maker);
$rejects(fn () => $offerOnboarding->approveOnboardingTemplate($template, 'Self approval attempt.', $maker), 'template creator cannot self-approve');
$offerOnboarding->approveOnboardingTemplate($template, 'Synthetic template independently approved.', $approver);
$onboarding = $offerOnboarding->startOnboarding($offer, $template, $maker, gmdate('Y-m-d', strtotime('+30 days')), $maker);
$item = $db->query("SELECT public_id FROM recruitment_onboarding_items WHERE onboarding_case_id=(SELECT onboarding_case_id FROM recruitment_onboarding_cases WHERE public_id=" . $db->quote($onboarding) . ")")->fetchColumn();
$offerOnboarding->updateOnboardingItem($candidateId, (string) $item, 'submitted', 'Synthetic acknowledgement submitted.');
$offerOnboarding->reviewOnboardingItem((string) $item, 'approved', 'Synthetic acknowledgement accepted.', $maker);
$offerOnboarding->prepareReadiness($onboarding, 'All synthetic requirements are complete.', $maker);
$rejects(fn () => $offerOnboarding->approveReadiness($onboarding, 'Self approval attempt.', $maker), 'readiness preparer cannot self-approve');
$offerOnboarding->approveReadiness($onboarding, 'Synthetic readiness independently approved.', $approver);

$payload = ['employee_number' => 'SYNTH-' . $run, 'full_name' => 'Synthetic Candidate'];
$conversionId = $conversion->prepare($onboarding, $payload, $maker);
$check($conversion->prepare($onboarding, $payload, $maker) === $conversionId, 'repeated conversion preparation is idempotent');
$employeeAdapter->duplicateClear = false;
$duplicate = $conversion->runDuplicateCheck($conversionId, $maker);
$check(!$duplicate['clear'], 'duplicate evidence blocks conversion');
$employeeAdapter->duplicateClear = true;
$check($conversion->runDuplicateCheck($conversionId, $maker)['clear'], 'cleared duplicate review advances conversion');
$rejects(fn () => $conversion->decide($conversionId, 'approved', 'Self approval attempt.', $maker), 'conversion preparer cannot self-approve');
$conversion->decide($conversionId, 'approved', 'Synthetic conversion independently approved.', $approver);
$employeeReference = $conversion->execute($conversionId, 'Execute only through the synthetic adapter.', $approver);
$check($conversion->execute($conversionId, 'Idempotent retry.', $approver) === $employeeReference, 'repeated conversion execution returns the existing synthetic employee');
$employeeAdapter->forceMismatch = true;
$check(!$conversion->reconcile($conversionId, $approver), 'reconciliation mismatch is recorded as a failure');
$employeeAdapter->forceMismatch = false;
$check($conversion->reconcile($conversionId, $approver), 'corrected employee snapshot reconciles successfully');

$final = $db->query("SELECT r.status conversion_status, a.current_status application_status, o.status onboarding_status FROM recruitment_employee_conversion_requests r JOIN recruitment_applications a ON a.application_id=r.application_id JOIN recruitment_onboarding_cases o ON o.onboarding_case_id=r.onboarding_case_id WHERE r.public_id=" . $db->quote($conversionId))->fetch();
$check(($final['conversion_status'] ?? '') === 'reconciled', 'conversion reaches reconciled status');
$check(($final['application_status'] ?? '') === 'converted', 'application reaches converted status');
$check(($final['onboarding_status'] ?? '') === 'converted', 'onboarding reaches converted status');
$check((int) $db->query("SELECT COUNT(*) FROM recruitment_audit_events WHERE subject_reference IN (" . $db->quote($requisition) . ',' . $db->quote($job) . ',' . $db->quote($application) . ',' . $db->quote($offer) . ',' . $db->quote($onboarding) . ',' . $db->quote($conversionId) . ")")->fetchColumn() >= 15, 'consequential lifecycle actions retain audit evidence');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment synthetic lifecycle qualification passed.\n";
