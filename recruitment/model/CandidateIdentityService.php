<?php

declare(strict_types=1);

require_once __DIR__ . '/RecruitmentPolicy.php';
require_once __DIR__ . '/RecruitmentSecurity.php';
require_once __DIR__ . '/RecruitmentNotificationOutbox.php';

final class CandidateIdentityService
{
    public function __construct(
        private PDO $db,
        private string $lookupKey,
        private string $dataKey,
        private RecruitmentNotificationOutbox $outbox
    ) {
        if (strlen($lookupKey) < 32 || strlen($dataKey) < 32) {
            throw new InvalidArgumentException('Recruitment identity keys must each contain at least 32 characters.');
        }
    }

    public function register(string $email, string $password, string $privacyNoticeVersion): array
    {
        $email = RecruitmentPolicy::normalizeEmail($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim($privacyNoticeVersion) === '') {
            throw new InvalidArgumentException('Valid account and privacy acknowledgement details are required.');
        }
        $passwordErrors = RecruitmentSecurity::validatePassword($password);
        if ($passwordErrors !== []) {
            throw new InvalidArgumentException(implode(' ', $passwordErrors));
        }

        $token = RecruitmentSecurity::issueToken();
        $publicId = self::uuidV4();
        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare(
                "INSERT INTO recruitment_candidates
                    (public_id, email_lookup_hash, email_ciphertext, password_hash, account_status,
                     privacy_notice_version, privacy_acknowledged_at)
                 VALUES
                    (:public_id, :email_lookup_hash, :email_ciphertext, :password_hash,
                     'pending_verification', :notice_version, UTC_TIMESTAMP())"
            );
            $insert->execute([
                'public_id' => $publicId,
                'email_lookup_hash' => RecruitmentPolicy::emailLookupHash($email, $this->lookupKey),
                'email_ciphertext' => RecruitmentSecurity::encrypt($email, $this->dataKey),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'notice_version' => trim($privacyNoticeVersion),
            ]);
            $candidateId = (int)$this->db->lastInsertId();
            $this->recordConsent($candidateId, trim($privacyNoticeVersion), 'candidate_account', 'acknowledged');
            $this->storeToken($candidateId, 'verify_email', $token['hash'], '+24 hours');
            $this->outbox->enqueue($candidateId, 'verify_email', [
                'candidate_public_id' => $publicId,
                'token_ciphertext' => RecruitmentSecurity::encrypt($token['raw'], $this->dataKey),
            ], $token['hash']);
            $this->audit('candidate_registered', 'candidate', $publicId, 'self_service');
            $this->db->commit();
        } catch (PDOException $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (($error->errorInfo[0] ?? $error->getCode()) !== '23000') {
                throw $error;
            }
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }

        return ['success' => 1, 'message' => RecruitmentSecurity::GENERIC_ACCOUNT_RESPONSE];
    }

    public function verifyEmail(string $rawToken): bool
    {
        $tokenHash = RecruitmentSecurity::tokenHash($rawToken);
        $this->db->beginTransaction();
        try {
            $token = $this->lockUsableToken($tokenHash, 'verify_email');
            if ($token === null) {
                $this->db->rollBack();
                return false;
            }
            $this->db->prepare("UPDATE recruitment_candidate_tokens SET consumed_at = UTC_TIMESTAMP() WHERE token_id = :token_id")
                ->execute(['token_id' => $token['token_id']]);
            $this->db->prepare("UPDATE recruitment_candidates SET account_status = 'active', email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()), failed_login_count = 0, locked_until = NULL WHERE candidate_id = :candidate_id AND deleted_at IS NULL")
                ->execute(['candidate_id' => $token['candidate_id']]);
            $this->audit('candidate_email_verified', 'candidate', (string)$token['candidate_id'], 'token');
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function requestPasswordReset(string $email): array
    {
        $normalized = RecruitmentPolicy::normalizeEmail($email);
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return ['success' => 1, 'message' => RecruitmentSecurity::GENERIC_ACCOUNT_RESPONSE];
        }
        $find = $this->db->prepare("SELECT candidate_id, public_id FROM recruitment_candidates WHERE email_lookup_hash = :email_hash AND account_status = 'active' AND deleted_at IS NULL LIMIT 1");
        $find->execute(['email_hash' => RecruitmentPolicy::emailLookupHash($normalized, $this->lookupKey)]);
        $candidate = $find->fetch(PDO::FETCH_ASSOC);
        if ($candidate) {
            $token = RecruitmentSecurity::issueToken();
            $this->db->beginTransaction();
            try {
                $this->db->prepare("UPDATE recruitment_candidate_tokens SET consumed_at = UTC_TIMESTAMP() WHERE candidate_id = :candidate_id AND purpose = 'password_reset' AND consumed_at IS NULL")
                    ->execute(['candidate_id' => $candidate['candidate_id']]);
                $this->storeToken((int)$candidate['candidate_id'], 'password_reset', $token['hash'], '+30 minutes');
                $this->outbox->enqueue((int)$candidate['candidate_id'], 'password_reset', [
                    'candidate_public_id' => $candidate['public_id'],
                    'token_ciphertext' => RecruitmentSecurity::encrypt($token['raw'], $this->dataKey),
                ], $token['hash']);
                $this->audit('candidate_password_reset_requested', 'candidate', (string)$candidate['public_id'], 'self_service');
                $this->db->commit();
            } catch (Throwable $error) {
                $this->db->rollBack();
                throw $error;
            }
        }
        return ['success' => 1, 'message' => RecruitmentSecurity::GENERIC_ACCOUNT_RESPONSE];
    }

    /** @return array{candidate_id:int,session_version:int}|null */
    public function authenticate(string $email, string $password, string $networkAddress, string $userAgent): ?array
    {
        $normalized = RecruitmentPolicy::normalizeEmail($email);
        $identifierHash = hash_hmac('sha256', $normalized, $this->lookupKey);
        $networkFingerprint = RecruitmentSecurity::networkFingerprint($networkAddress, $userAgent, $this->lookupKey);
        $counts = $this->db->prepare(
            "SELECT
                SUM(identifier_hash = :identifier_hash) AS identifier_attempts,
                SUM(network_fingerprint = :network_fingerprint) AS network_attempts
               FROM recruitment_auth_attempts
              WHERE attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                AND outcome IN ('failed', 'rate_limited')"
        );
        $counts->execute(['identifier_hash' => $identifierHash, 'network_fingerprint' => $networkFingerprint]);
        $attempts = $counts->fetch(PDO::FETCH_ASSOC) ?: [];
        if (RecruitmentSecurity::loginRateLimited((int)($attempts['identifier_attempts'] ?? 0), (int)($attempts['network_attempts'] ?? 0))) {
            $this->recordAttempt($identifierHash, $networkFingerprint, 'rate_limited');
            return null;
        }

        $candidate = null;
        if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            $find = $this->db->prepare("SELECT candidate_id, public_id, password_hash, session_version, account_status, email_verified_at, locked_until FROM recruitment_candidates WHERE email_lookup_hash = :email_hash AND deleted_at IS NULL LIMIT 1");
            $find->execute(['email_hash' => RecruitmentPolicy::emailLookupHash($normalized, $this->lookupKey)]);
            $candidate = $find->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $dummyHash = '$2y$10$9CqF6h1VNyjM3vD8oqkJ1uQeBRY5v0f7iFsn6VrSpjYdA1dJYx6uK';
        $passwordValid = password_verify($password, (string)($candidate['password_hash'] ?? $dummyHash));
        $active = $candidate !== null
            && ($candidate['account_status'] ?? '') === 'active'
            && !empty($candidate['email_verified_at'])
            && (empty($candidate['locked_until']) || strtotime((string)$candidate['locked_until']) <= time());
        if (!$passwordValid || !$active) {
            if ($candidate !== null) {
                $this->db->prepare("UPDATE recruitment_candidates SET failed_login_count = failed_login_count + 1, locked_until = CASE WHEN failed_login_count + 1 >= 5 THEN DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) ELSE locked_until END WHERE candidate_id = :candidate_id")
                    ->execute(['candidate_id' => $candidate['candidate_id']]);
            }
            $this->recordAttempt($identifierHash, $networkFingerprint, 'failed');
            return null;
        }

        $this->db->prepare("UPDATE recruitment_candidates SET failed_login_count = 0, locked_until = NULL WHERE candidate_id = :candidate_id")
            ->execute(['candidate_id' => $candidate['candidate_id']]);
        $this->recordAttempt($identifierHash, $networkFingerprint, 'success');
        $this->audit('candidate_authenticated', 'candidate', (string)$candidate['public_id'], 'password');
        return ['candidate_id' => (int)$candidate['candidate_id'], 'session_version' => (int)$candidate['session_version']];
    }

    public function resetPassword(string $rawToken, string $password): bool
    {
        $errors = RecruitmentSecurity::validatePassword($password);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }
        $this->db->beginTransaction();
        try {
            $token = $this->lockUsableToken(RecruitmentSecurity::tokenHash($rawToken), 'password_reset');
            if ($token === null) {
                $this->db->rollBack();
                return false;
            }
            $this->db->prepare("UPDATE recruitment_candidate_tokens SET consumed_at = UTC_TIMESTAMP() WHERE token_id = :token_id")
                ->execute(['token_id' => $token['token_id']]);
            $this->db->prepare("UPDATE recruitment_candidates SET password_hash = :password_hash, session_version = session_version + 1, failed_login_count = 0, locked_until = NULL WHERE candidate_id = :candidate_id AND account_status = 'active' AND deleted_at IS NULL")
                ->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'candidate_id' => $token['candidate_id']]);
            $this->audit('candidate_password_reset_completed', 'candidate', (string)$token['candidate_id'], 'token');
            $this->db->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function sessionVersionIsCurrent(int $candidateId, int $sessionVersion): bool
    {
        $query = $this->db->prepare("SELECT session_version FROM recruitment_candidates WHERE candidate_id = :candidate_id AND account_status = 'active' AND deleted_at IS NULL");
        $query->execute(['candidate_id' => $candidateId]);
        return $sessionVersion > 0 && (int)$query->fetchColumn() === $sessionVersion;
    }

    public function revokeAllSessions(int $candidateId): void
    {
        $statement = $this->db->prepare("UPDATE recruitment_candidates SET session_version = session_version + 1 WHERE candidate_id = :candidate_id AND deleted_at IS NULL");
        $statement->execute(['candidate_id' => $candidateId]);
        $this->audit('candidate_sessions_revoked', 'candidate', (string)$candidateId, 'candidate_request');
    }

    private function storeToken(int $candidateId, string $purpose, string $tokenHash, string $lifetime): void
    {
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify($lifetime)->format('Y-m-d H:i:s');
        $statement = $this->db->prepare("INSERT INTO recruitment_candidate_tokens (candidate_id, purpose, token_hash, expires_at) VALUES (:candidate_id, :purpose, :token_hash, :expires_at)");
        $statement->execute(['candidate_id' => $candidateId, 'purpose' => $purpose, 'token_hash' => $tokenHash, 'expires_at' => $expiresAt]);
    }

    private function lockUsableToken(string $tokenHash, string $purpose): ?array
    {
        $statement = $this->db->prepare("SELECT token_id, candidate_id FROM recruitment_candidate_tokens WHERE token_hash = :token_hash AND purpose = :purpose AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE");
        $statement->execute(['token_hash' => $tokenHash, 'purpose' => $purpose]);
        $token = $statement->fetch(PDO::FETCH_ASSOC);
        return $token ?: null;
    }

    private function recordConsent(int $candidateId, string $noticeVersion, string $purpose, string $action): void
    {
        $evidence = hash_hmac('sha256', implode('|', [$candidateId, $noticeVersion, $purpose, $action]), $this->lookupKey);
        $statement = $this->db->prepare("INSERT INTO recruitment_candidate_consents (candidate_id, notice_version, purpose_code, action, evidence_hash) VALUES (:candidate_id, :notice_version, :purpose_code, :action, :evidence_hash)");
        $statement->execute(['candidate_id' => $candidateId, 'notice_version' => $noticeVersion, 'purpose_code' => $purpose, 'action' => $action, 'evidence_hash' => $evidence]);
    }

    private function audit(string $eventType, string $subjectType, string $subjectReference, string $reasonCode): void
    {
        $statement = $this->db->prepare("INSERT INTO recruitment_audit_events (event_type, actor_type, actor_reference, subject_type, subject_reference, reason_code) VALUES (:event_type, 'candidate', :actor_reference, :subject_type, :subject_reference, :reason_code)");
        $statement->execute(['event_type' => $eventType, 'actor_reference' => $subjectReference, 'subject_type' => $subjectType, 'subject_reference' => $subjectReference, 'reason_code' => $reasonCode]);
    }

    private function recordAttempt(string $identifierHash, string $networkFingerprint, string $outcome): void
    {
        $statement = $this->db->prepare("INSERT INTO recruitment_auth_attempts (identifier_hash, network_fingerprint, outcome) VALUES (:identifier_hash, :network_fingerprint, :outcome)");
        $statement->execute(['identifier_hash' => $identifierHash, 'network_fingerprint' => $networkFingerprint, 'outcome' => $outcome]);
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
