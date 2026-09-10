<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/candidate_runtime.php';
require_once __DIR__ . '/../../includes/candidate_csrf.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!recruitment_candidate_runtime_ready()) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => 0, 'error' => 'Candidate account access is not available.']);
    exit;
}

if (!recruitment_candidate_csrf_is_valid((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    recruitment_candidate_flash('error', 'Your session expired. Please try again.');
    header('Location: ../index.php');
    exit;
}

$action = (string)($_POST['action'] ?? '');
$allowed = ['login', 'register', 'recover', 'reset', 'verify', 'logout'];
if (!in_array($action, $allowed, true)) {
    http_response_code(400);
    recruitment_candidate_flash('error', 'That account action is not available.');
    header('Location: ../index.php');
    exit;
}

try {
    $service = recruitment_candidate_service();
    $destination = '../index.php';
    switch ($action) {
        case 'register':
            if ((string)($_POST['password'] ?? '') !== (string)($_POST['password_confirm'] ?? '')) {
                throw new InvalidArgumentException('The passwords do not match.');
            }
            if ((string)($_POST['privacy_acknowledged'] ?? '') !== '1') {
                throw new InvalidArgumentException('Read and acknowledge the candidate privacy notice to continue.');
            }
            $noticeVersion = trim((string)getenv('TAASCOR_RECRUITMENT_PRIVACY_NOTICE_VERSION'));
            if ($noticeVersion === '') {
                throw new RuntimeException('Candidate registration is not configured.');
            }
            $result = $service->register((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''), $noticeVersion);
            recruitment_candidate_flash('success', (string)$result['message']);
            break;

        case 'login':
            $identity = $service->authenticate(
                (string)($_POST['email'] ?? ''),
                (string)($_POST['password'] ?? ''),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
            if ($identity === null) {
                throw new InvalidArgumentException('The email address or password could not be verified.');
            }
            recruitment_candidate_complete_login($identity['candidate_id'], $identity['session_version']);
            recruitment_candidate_flash('success', 'You are signed in.');
            $destination = '../dashboard.php';
            break;

        case 'recover':
            $result = $service->requestPasswordReset((string)($_POST['email'] ?? ''));
            recruitment_candidate_flash('success', (string)$result['message']);
            break;

        case 'verify':
            if (!$service->verifyEmail((string)($_POST['token'] ?? ''))) {
                throw new InvalidArgumentException('This verification link is invalid or has expired.');
            }
            recruitment_candidate_flash('success', 'Your email is confirmed. You can now sign in.');
            break;

        case 'reset':
            if ((string)($_POST['password'] ?? '') !== (string)($_POST['password_confirm'] ?? '')) {
                throw new InvalidArgumentException('The passwords do not match.');
            }
            if (!$service->resetPassword((string)($_POST['token'] ?? ''), (string)($_POST['password'] ?? ''))) {
                throw new InvalidArgumentException('This recovery link is invalid or has expired.');
            }
            recruitment_candidate_destroy_session();
            recruitment_candidate_start_session();
            recruitment_candidate_flash('success', 'Your password was updated. Sign in with your new password.');
            break;

        case 'logout':
            recruitment_candidate_destroy_session();
            header('Location: ../index.php');
            exit;
    }
    header("Location: {$destination}");
} catch (InvalidArgumentException $error) {
    recruitment_candidate_flash('error', $error->getMessage());
    $errorDestination = match ($action) {
        'register' => 'register.php',
        'recover' => 'recover.php',
        'reset' => 'reset.php?token=' . rawurlencode((string)($_POST['token'] ?? '')),
        'verify' => 'verify.php?token=' . rawurlencode((string)($_POST['token'] ?? '')),
        default => 'index.php',
    };
    header('Location: ../' . $errorDestination);
} catch (Throwable $error) {
    error_log('Recruitment candidate account action failed: ' . $error->getMessage());
    recruitment_candidate_flash('error', 'The account request could not be completed. Please try again later.');
    header('Location: ../index.php');
}
exit;
