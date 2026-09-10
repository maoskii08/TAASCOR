<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/feature.php';
require_once __DIR__ . '/../../includes/candidate_csrf.php';
require_once __DIR__ . '/../../includes/candidate_runtime.php';
require_once __DIR__ . '/../../includes/candidate_portal_runtime.php';
require_once __DIR__ . '/../../model/RecruitmentStaffAccessService.php';
require_once __DIR__ . '/../../model/RecruitmentWorkflowService.php';
require_once __DIR__ . '/../../model/RecruitmentOfferOnboardingService.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!recruitment_candidate_runtime_ready()) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => 0, 'error' => 'Candidate actions are not available.']);
    exit;
}

if (!recruitment_candidate_csrf_is_valid((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    recruitment_candidate_flash('error', 'Your session expired. Please try again.');
    header('Location: ../dashboard.php');
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));
$allowedActions = [
    'application_save', 'application_submit', 'application_withdraw',
    'interview_response', 'offer_response', 'onboarding_item_update',
    'support_message', 'notification_preference', 'privacy_request',
    'document_upload',
];
if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    recruitment_candidate_flash('error', 'That candidate action is not available.');
    header('Location: ../dashboard.php');
    exit;
}

try {
    $context = recruitment_candidate_portal_context();
    $candidateId = $context['candidate_id'];
    $db = $context['db'];
    $access = new RecruitmentStaffAccessService($db);
    $outbox = new RecruitmentNotificationOutbox($db);
    $workflow = new RecruitmentWorkflowService($db, recruitment_data_key(), $access, $outbox);
    $offers = new RecruitmentOfferOnboardingService($db, recruitment_data_key(), $access, $outbox);
    $portal = $context['portal'];
    $destination = '../dashboard.php';

    switch ($action) {
        case 'application_save':
            $noticeVersion = trim((string)getenv('TAASCOR_RECRUITMENT_PRIVACY_NOTICE_VERSION'));
            if ($noticeVersion === '' || (string)($_POST['privacy_acknowledged'] ?? '') !== '1') {
                throw new InvalidArgumentException('Read and acknowledge the candidate privacy notice to continue.');
            }
            $applicationPublicId = $workflow->saveApplicationDraft(
                $candidateId,
                (string)($_POST['job_public_id'] ?? ''),
                [
                    'full_name' => $_POST['full_name'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'current_city' => $_POST['current_city'] ?? '',
                    'experience_summary' => $_POST['experience_summary'] ?? '',
                    'eligibility_confirmed' => $_POST['eligibility_confirmed'] ?? false,
                ],
                $noticeVersion
            );
            recruitment_candidate_flash('success', 'Your application draft was saved.');
            $destination = '../application.php?id=' . rawurlencode($applicationPublicId);
            break;

        case 'application_submit':
            $applicationPublicId = trim((string)($_POST['application_public_id'] ?? ''));
            $workflow->submitApplication(
                $candidateId,
                $applicationPublicId,
                (string)($_POST['job_change_acknowledged'] ?? '') === '1'
            );
            recruitment_candidate_flash('success', 'Your application was submitted.');
            $destination = '../application.php?id=' . rawurlencode($applicationPublicId);
            break;

        case 'application_withdraw':
            $workflow->withdrawApplication(
                $candidateId,
                (string)($_POST['application_public_id'] ?? ''),
                (string)($_POST['reason'] ?? '')
            );
            recruitment_candidate_flash('success', 'Your application was withdrawn.');
            $destination = '../applications.php';
            break;

        case 'interview_response':
            $workflow->respondToInterview(
                $candidateId,
                (string)($_POST['interview_public_id'] ?? ''),
                (string)($_POST['response'] ?? '')
            );
            recruitment_candidate_flash('success', 'Your interview response was recorded.');
            $destination = '../interviews.php';
            break;

        case 'offer_response':
            $offers->respondToOffer(
                $candidateId,
                (string)($_POST['offer_public_id'] ?? ''),
                (string)($_POST['response'] ?? ''),
                isset($_POST['note']) ? (string)$_POST['note'] : null
            );
            recruitment_candidate_flash('success', 'Your offer response was recorded.');
            $destination = '../offers.php';
            break;

        case 'onboarding_item_update':
            $offers->updateOnboardingItem(
                $candidateId,
                (string)($_POST['item_public_id'] ?? ''),
                (string)($_POST['status'] ?? ''),
                (string)($_POST['reason'] ?? '')
            );
            recruitment_candidate_flash('success', 'Your onboarding item was updated.');
            $destination = '../onboarding.php';
            break;

        case 'support_message':
            $portal->sendSupportMessage(
                $candidateId,
                trim((string)($_POST['application_public_id'] ?? '')) ?: null,
                (string)($_POST['subject'] ?? ''),
                (string)($_POST['body'] ?? '')
            );
            recruitment_candidate_flash('success', 'Your message was sent to the recruitment team.');
            $destination = '../messages.php';
            break;

        case 'notification_preference':
            $portal->setNotificationPreference(
                $candidateId,
                (string)($_POST['channel'] ?? ''),
                (string)($_POST['message_type'] ?? ''),
                (string)($_POST['enabled'] ?? '') === '1'
            );
            recruitment_candidate_flash('success', 'Your notification preference was saved.');
            $destination = '../settings.php';
            break;

        case 'privacy_request':
            $requestPublicId = $portal->submitPrivacyRequest(
                $candidateId,
                (string)($_POST['request_type'] ?? ''),
                (string)($_POST['details'] ?? '')
            );
            recruitment_candidate_flash('success', 'Your privacy request was received. Reference: ' . $requestPublicId);
            $destination = '../settings.php';
            break;

        case 'document_upload':
            recruitment_candidate_document_service($db)->upload($candidateId, (string)($_POST['request_public_id'] ?? ''), $_FILES['document'] ?? []);
            recruitment_candidate_flash('success', 'Your document was received and placed in security review.');
            $destination = '../documents.php';
            break;
    }

    header('Location: ' . $destination);
} catch (InvalidArgumentException | DomainException $error) {
    recruitment_candidate_flash('error', $error->getMessage());
    header('Location: ../dashboard.php');
} catch (Throwable $error) {
    error_log('Recruitment candidate portal action failed: ' . $error->getMessage());
    recruitment_candidate_flash('error', 'The request could not be completed. Please try again later.');
    header('Location: ../dashboard.php');
}
exit;
