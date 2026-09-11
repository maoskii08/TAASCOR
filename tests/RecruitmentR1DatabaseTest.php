<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/mysql-config.php';
require_once __DIR__ . '/../recruitment/model/CandidateIdentityService.php';

$hostParts = explode(':', trim((string)HOST), 2);
$host = strtolower(trim($hostParts[0]));
$port = isset($hostParts[1]) && ctype_digit($hostParts[1]) ? $hostParts[1] : '3306';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || !str_starts_with((string)DATABASE, 'taascor_codex_')) {
    fwrite(STDERR, "FAIL: R1 database test requires a loopback taascor_codex_* disposable schema.\n");
    exit(2);
}

$db = new PDO("mysql:host={$host};port={$port};dbname=" . DATABASE . ';charset=utf8mb4', USER, PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$lookupKey = str_repeat('lookup-test-key-', 3);
$dataKey = str_repeat('data-test-key-', 3);
$service = new CandidateIdentityService($db, $lookupKey, $dataKey, new RecruitmentNotificationOutbox($db));
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        return;
    }
    echo "PASS: {$message}\n";
};

$email = 'synthetic.identity.' . bin2hex(random_bytes(4)) . '@example.invalid';
$initialPassword = 'Synthetic-Identity-42!';
$result = $service->register($email, $initialPassword, 'synthetic-notice-v1');
$check(($result['message'] ?? '') === RecruitmentSecurity::GENERIC_ACCOUNT_RESPONSE, 'registration returns the anti-enumeration response');
$service->register(strtoupper($email), $initialPassword, 'synthetic-notice-v1');
$emailHash = RecruitmentPolicy::emailLookupHash($email, $lookupKey);
$candidateCount = $db->prepare('SELECT COUNT(*) FROM recruitment_candidates WHERE email_lookup_hash = :email_hash');
$candidateCount->execute(['email_hash' => $emailHash]);
$check((int)$candidateCount->fetchColumn() === 1, 'normalized duplicate registration creates no second account');

$candidateQuery = $db->prepare('SELECT candidate_id, public_id, email_ciphertext, session_version FROM recruitment_candidates WHERE email_lookup_hash = :email_hash LIMIT 1');
$candidateQuery->execute(['email_hash' => $emailHash]);
$candidate = $candidateQuery->fetch();
$consentCount = $db->prepare('SELECT COUNT(*) FROM recruitment_candidate_consents WHERE candidate_id = :candidate_id');
$consentCount->execute(['candidate_id' => $candidate['candidate_id']]);
$check((int)$consentCount->fetchColumn() === 1, 'candidate privacy acknowledgement is durable');
$check(RecruitmentSecurity::decrypt((string)$candidate['email_ciphertext'], $dataKey) === $email, 'stored candidate email decrypts only with the data key');
$notificationQuery = $db->prepare("SELECT payload_json, delivery_status FROM recruitment_notification_outbox WHERE candidate_id = :candidate_id AND message_type = 'verify_email' ORDER BY notification_id DESC LIMIT 1");
$notificationQuery->execute(['candidate_id' => $candidate['candidate_id']]);
$notification = $notificationQuery->fetch();
$payload = json_decode((string)$notification['payload_json'], true, 512, JSON_THROW_ON_ERROR);
$verificationToken = RecruitmentSecurity::decrypt((string)$payload['token_ciphertext'], $dataKey);
$check(($notification['delivery_status'] ?? '') === 'pending', 'verification notification is queued, not reported as sent');
$check($service->verifyEmail($verificationToken), 'valid email verification token activates the account');
$check(!$service->verifyEmail($verificationToken), 'consumed email verification token cannot be replayed');

$identity = $service->authenticate($email, $initialPassword, '127.0.0.1', 'Synthetic Test Agent');
$check(($identity['candidate_id'] ?? 0) === (int)$candidate['candidate_id'], 'verified candidate can authenticate');
$oldVersion = (int)($identity['session_version'] ?? 0);
$check($service->authenticate($email, 'Wrong-Password-42!', '127.0.0.1', 'Synthetic Test Agent') === null, 'incorrect password receives no identity result');

$reset = $service->requestPasswordReset($email);
$check(($reset['message'] ?? '') === RecruitmentSecurity::GENERIC_ACCOUNT_RESPONSE, 'password recovery remains anti-enumerating');
$resetNotificationQuery = $db->prepare("SELECT payload_json FROM recruitment_notification_outbox WHERE candidate_id = :candidate_id AND message_type = 'password_reset' ORDER BY notification_id DESC LIMIT 1");
$resetNotificationQuery->execute(['candidate_id' => $candidate['candidate_id']]);
$resetNotification = $resetNotificationQuery->fetch();
$resetPayload = json_decode((string)$resetNotification['payload_json'], true, 512, JSON_THROW_ON_ERROR);
$resetToken = RecruitmentSecurity::decrypt((string)$resetPayload['token_ciphertext'], $dataKey);
$newPassword = 'Synthetic-Recovered-84!';
$check($service->resetPassword($resetToken, $newPassword), 'valid password-reset token updates the credential');
$check(!$service->resetPassword($resetToken, $newPassword), 'consumed password-reset token cannot be replayed');
$check(!$service->sessionVersionIsCurrent((int)$candidate['candidate_id'], $oldVersion), 'password reset revokes the previous session version');
$newIdentity = $service->authenticate($email, $newPassword, '127.0.0.1', 'Synthetic Test Agent');
$check(($newIdentity['session_version'] ?? 0) > $oldVersion, 'candidate can authenticate with the replacement credential and new session version');
$auditCount = $db->prepare("SELECT COUNT(*) FROM recruitment_audit_events WHERE actor_type = 'candidate' AND actor_reference IN (:candidate_id, :public_id)");
$auditCount->execute(['candidate_id' => (string)$candidate['candidate_id'], 'public_id' => (string)$candidate['public_id']]);
$check((int)$auditCount->fetchColumn() >= 4, 'candidate identity actions create audit evidence');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Recruitment R1 disposable-database checks passed.\n";
