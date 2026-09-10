<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth_guard.php';
auth_require_role([1, 2]);
require_once dirname(__DIR__) . '/includes/staff_runtime.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405); header('Allow: POST'); echo json_encode(['success'=>0,'error'=>'POST required.']); exit;
}
if (!recruitment_staff_mutations_ready()) {
    http_response_code(503); echo json_encode(['success'=>0,'error'=>'Recruitment actions remain source-locked.']); exit;
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$input = str_contains($contentType, 'application/json') ? json_decode((string)file_get_contents('php://input'), true) : $_POST;
if (!is_array($input)) { http_response_code(400); echo json_encode(['success'=>0,'error'=>'A valid request body is required.']); exit; }
$action = trim((string)($input['action'] ?? ''));
$actor = auth_user();

try {
    $services = recruitment_staff_services();
    $workflow = $services['workflow']; $offer = $services['offer_onboarding']; $conversion = $services['conversion'];
    $data = match ($action) {
        'requisition.create' => ['public_id'=>$workflow->createRequisition((array)($input['requisition'] ?? []),$actor)],
        'requisition.transition' => tap_void(fn()=>$workflow->transitionRequisition((string)$input['public_id'],(string)$input['status'],(string)$input['reason'],$actor)),
        'job.create' => ['public_id'=>$workflow->createJobDraft((string)$input['requisition_public_id'],(array)($input['job'] ?? []),$actor)],
        'job.update' => ['version'=>$workflow->updateJobContent((string)$input['public_id'],(array)($input['job'] ?? []),(string)$input['reason'],$actor)],
        'job.transition' => tap_void(fn()=>$workflow->transitionJob((string)$input['public_id'],(string)$input['status'],(string)$input['reason'],$actor)),
        'scorecard.criteria.replace' => tap_void(fn()=>$workflow->replaceScorecardCriteria((string)$input['job_public_id'],(array)($input['criteria'] ?? []),$actor)),
        'application.transition' => tap_void(fn()=>$workflow->transitionApplication((string)$input['public_id'],(string)$input['status'],(string)$input['candidate_message'],(string)$input['reason_code'],$actor)),
        'application.assign' => tap_void(fn()=>$workflow->assignApplication((string)$input['public_id'],(string)$input['assignee'],(string)$input['assignment_role'],$actor)),
        'application.note' => ['note_id'=>$workflow->addStaffNote((string)$input['public_id'],(string)$input['note'],(string)$input['visibility'],$actor)],
        'candidate.message' => ['public_id'=>$services['case']->sendCandidateMessage((string)$input['application_public_id'],(string)$input['subject'],(string)$input['body'],$actor)],
        'interview.schedule' => ['public_id'=>$workflow->scheduleInterview((string)$input['application_public_id'],(array)($input['interview'] ?? []),(array)($input['participants'] ?? []),$actor)],
        'interview.scorecard.submit' => tap_void(fn()=>$workflow->submitScorecard((string)$input['public_id'],(array)($input['ratings'] ?? []),(string)$input['recommendation'],$actor)),
        'offer.prepare' => ['public_id'=>$offer->prepareOffer((string)$input['application_public_id'],(array)($input['terms'] ?? []),nullable_string($input['expires_at'] ?? null),$actor)],
        'offer.revise' => ['version'=>$offer->reviseOffer((string)$input['public_id'],(array)($input['terms'] ?? []),nullable_string($input['expires_at'] ?? null),(string)$input['reason'],$actor)],
        'offer.submit' => tap_void(fn()=>$offer->submitOfferForApproval((string)$input['public_id'],(string)$input['reason'],$actor)),
        'offer.decide' => tap_void(fn()=>$offer->decideOffer((string)$input['public_id'],(string)$input['decision'],(string)$input['reason'],$actor)),
        'offer.deliver' => tap_void(fn()=>$offer->deliverOffer((string)$input['public_id'],(string)$input['reason'],$actor)),
        'onboarding.template.create' => ['public_id'=>$offer->createOnboardingTemplate((array)($input['template'] ?? []),(array)($input['items'] ?? []),$actor)],
        'onboarding.template.approve' => tap_void(fn()=>$offer->approveOnboardingTemplate((string)$input['public_id'],(string)$input['reason'],$actor)),
        'onboarding.start' => ['public_id'=>$offer->startOnboarding((string)$input['offer_public_id'],(string)$input['template_public_id'],(string)$input['owner'],nullable_string($input['target_start_date'] ?? null),$actor)],
        'onboarding.item.review' => tap_void(fn()=>$offer->reviewOnboardingItem((string)$input['public_id'],(string)$input['decision'],(string)$input['reason'],$actor)),
        'onboarding.readiness.prepare' => tap_void(fn()=>$offer->prepareReadiness((string)$input['public_id'],(string)$input['reason'],$actor)),
        'onboarding.readiness.approve' => tap_void(fn()=>$offer->approveReadiness((string)$input['public_id'],(string)$input['reason'],$actor)),
        'document.request' => ['public_id'=>recruitment_staff_document_service($services['db'])->request((string)$input['application_public_id'],(array)($input['request'] ?? []),$actor)],
        'document.review' => tap_void(fn()=>recruitment_staff_document_service($services['db'])->review((string)$input['public_id'],(string)$input['decision'],(string)$input['reason'],$actor)),
        'conversion.prepare' => ['public_id'=>$conversion->prepare((string)$input['onboarding_public_id'],(array)($input['employee'] ?? []),$actor)],
        'conversion.duplicate_check' => $conversion->runDuplicateCheck((string)$input['public_id'],$actor),
        'conversion.decide' => tap_void(fn()=>$conversion->decide((string)$input['public_id'],(string)$input['decision'],(string)$input['reason'],$actor)),
        'conversion.execute' => ['employee_reference'=>$conversion->execute((string)$input['public_id'],(string)$input['reason'],$actor)],
        'conversion.reconcile' => ['reconciled'=>$conversion->reconcile((string)$input['public_id'],$actor)],
        'access.grant' => tap_void(fn()=>$services['access']->grant((string)$input['username'],(string)$input['capability'],(string)$input['scope_type'],(string)$input['scope_reference'],nullable_string($input['expires_at']??null),(string)$input['reason'],$actor)),
        'access.revoke' => tap_void(fn()=>$services['access']->revoke((string)$input['username'],(string)$input['capability'],(string)$input['scope_type'],(string)$input['scope_reference'],(string)$input['reason'],$actor)),
        default => throw new InvalidArgumentException('Unsupported recruitment action.'),
    };
    echo json_encode(['success'=>1,'data'=>$data],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException|DomainException $e) {
    http_response_code(422); echo json_encode(['success'=>0,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Recruitment staff action failed: '.$action.' '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>0,'error'=>'The action could not be completed. No successful result was recorded.']);
}

function tap_void(callable $operation): array { $operation(); return ['completed'=>true]; }
function nullable_string(mixed $value): ?string { $value=trim((string)$value); return $value==='' ? null : $value; }
