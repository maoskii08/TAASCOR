<?php

declare(strict_types=1);

require_once __DIR__ . '/RecruitmentPolicy.php';
require_once __DIR__ . '/RecruitmentSecurity.php';
require_once __DIR__ . '/RecruitmentContentPolicy.php';

final class RecruitmentCandidatePortalService
{
    public function __construct(private PDO $db, private string $dataKey)
    {
        if (strlen($dataKey) < 32) {
            throw new InvalidArgumentException('The recruitment data key must contain at least 32 characters.');
        }
    }

    public function sessionIsCurrent(int $candidateId, int $sessionVersion): bool
    {
        if ($candidateId <= 0 || $sessionVersion <= 0) {
            return false;
        }
        $statement = $this->db->prepare(
            "SELECT session_version FROM recruitment_candidates
              WHERE candidate_id = :candidate_id AND account_status = 'active'
                AND email_verified_at IS NOT NULL AND deleted_at IS NULL LIMIT 1"
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $current = $statement->fetchColumn();
        return $current !== false && (int)$current === $sessionVersion;
    }

    /** @return array<string, int> */
    public function dashboardSummary(int $candidateId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                COUNT(*) AS applications,
                SUM(current_status NOT IN ('draft', 'converted', 'declined', 'withdrawn', 'offer_declined')) AS active_applications,
                SUM(current_status = 'requirements') AS actions_required
               FROM recruitment_applications
              WHERE candidate_id = :candidate_id"
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $applications = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $interviews = $this->db->prepare(
            "SELECT COUNT(*)
               FROM recruitment_interviews i
               JOIN recruitment_applications a ON a.application_id = i.application_id
              WHERE a.candidate_id = :candidate_id
                AND i.status IN ('scheduled', 'confirmed', 'reschedule_requested')
                AND i.ends_at_utc > UTC_TIMESTAMP()"
        );
        $interviews->execute(['candidate_id' => $candidateId]);
        $onboarding = $this->db->prepare(
            "SELECT COUNT(*)
               FROM recruitment_onboarding_items oi
               JOIN recruitment_onboarding_cases oc ON oc.onboarding_case_id = oi.onboarding_case_id
               JOIN recruitment_applications a ON a.application_id = oc.application_id
              WHERE a.candidate_id = :candidate_id AND oi.required_flag = 1
                AND oi.status IN ('pending', 'in_progress', 'changes_requested')"
        );
        $onboarding->execute(['candidate_id' => $candidateId]);
        return [
            'applications' => (int)($applications['applications'] ?? 0),
            'active_applications' => (int)($applications['active_applications'] ?? 0),
            'application_actions' => (int)($applications['actions_required'] ?? 0),
            'upcoming_interviews' => (int)$interviews->fetchColumn(),
            'onboarding_actions' => (int)$onboarding->fetchColumn(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function applications(int $candidateId): array
    {
        $statement = $this->db->prepare(
            'SELECT a.public_id, a.current_status, a.submitted_at, a.withdrawn_at, a.created_at, a.updated_at,
                    j.public_id AS job_public_id, j.slug AS job_slug, j.title AS job_title,
                    j.location_label, j.work_arrangement, j.employment_type
               FROM recruitment_applications a
               JOIN recruitment_jobs j ON j.job_id = a.job_id
              WHERE a.candidate_id = :candidate_id
              ORDER BY a.updated_at DESC, a.application_id DESC'
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['candidate_status'] = RecruitmentPolicy::candidateStatus((string)$row['current_status']);
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function application(int $candidateId, string $applicationPublicId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT a.*, j.public_id AS job_public_id, j.slug AS job_slug, j.title AS job_title,
                    j.location_label, j.work_arrangement, j.employment_type
               FROM recruitment_applications a
               JOIN recruitment_jobs j ON j.job_id = a.job_id
              WHERE a.candidate_id = :candidate_id AND a.public_id = :public_id LIMIT 1'
        );
        $statement->execute(['candidate_id' => $candidateId, 'public_id' => $applicationPublicId]);
        $application = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($application)) {
            return null;
        }
        $application['candidate_status'] = RecruitmentPolicy::candidateStatus((string)$application['current_status']);
        $application['candidate_summary'] = $application['candidate_summary_ciphertext'] === null
            ? null
            : $this->decryptJson((string)$application['candidate_summary_ciphertext']);
        unset($application['candidate_summary_ciphertext']);
        try {
            $snapshot = json_decode((string)$application['job_snapshot'], true, 64, JSON_THROW_ON_ERROR);
            $application['job_snapshot_data'] = is_array($snapshot) ? $snapshot : null;
        } catch (JsonException) {
            $application['job_snapshot_data'] = null;
        }
        unset($application['job_snapshot']);

        $timeline = $this->db->prepare(
            'SELECT to_status, candidate_message, changed_at
               FROM recruitment_application_events
              WHERE application_id = :application_id AND candidate_message IS NOT NULL
              ORDER BY changed_at, event_id'
        );
        $timeline->execute(['application_id' => $application['application_id']]);
        $application['timeline'] = array_map(static function (array $event): array {
            return [
                'status' => RecruitmentPolicy::candidateStatus((string)$event['to_status']),
                'message' => (string)$event['candidate_message'],
                'at' => (string)$event['changed_at'],
            ];
        }, $timeline->fetchAll(PDO::FETCH_ASSOC) ?: []);
        return $application;
    }

    /** @return list<array<string, mixed>> */
    public function interviews(int $candidateId): array
    {
        $statement = $this->db->prepare(
            'SELECT i.public_id, i.interview_type, i.status, i.starts_at_utc, i.ends_at_utc,
                    i.timezone_name, i.location_type, i.location_ciphertext,
                    i.instructions_ciphertext, i.accessibility_route, j.title AS job_title
               FROM recruitment_interviews i
               JOIN recruitment_applications a ON a.application_id = i.application_id
               JOIN recruitment_jobs j ON j.job_id = a.job_id
              WHERE a.candidate_id = :candidate_id
              ORDER BY i.starts_at_utc DESC, i.interview_id DESC'
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['location'] = RecruitmentSecurity::decrypt((string)$row['location_ciphertext'], $this->dataKey);
            $row['instructions'] = $row['instructions_ciphertext'] === null
                ? null
                : RecruitmentSecurity::decrypt((string)$row['instructions_ciphertext'], $this->dataKey);
            unset($row['location_ciphertext'], $row['instructions_ciphertext']);
        }
        unset($row);
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function offers(int $candidateId): array
    {
        $statement = $this->db->prepare(
            "SELECT o.public_id, o.status, o.current_version, o.delivered_at, o.expires_at, o.responded_at,
                    ov.terms_ciphertext, ov.content_sha256, j.title AS job_title
               FROM recruitment_offers o
               JOIN recruitment_offer_versions ov ON ov.offer_id = o.offer_id AND ov.version_number = o.current_version
               JOIN recruitment_applications a ON a.application_id = o.application_id
               JOIN recruitment_jobs j ON j.job_id = a.job_id
              WHERE a.candidate_id = :candidate_id
                AND o.status IN ('delivered', 'accepted', 'declined', 'expired', 'withdrawn')
              ORDER BY o.updated_at DESC, o.offer_id DESC"
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $termsJson = RecruitmentSecurity::decrypt((string)$row['terms_ciphertext'], $this->dataKey);
            if (!hash_equals((string)$row['content_sha256'], hash('sha256', $termsJson))) {
                throw new RuntimeException('An offer failed its integrity check.');
            }
            $terms = json_decode($termsJson, true, 64, JSON_THROW_ON_ERROR);
            $row['terms'] = is_array($terms) ? $terms : [];
            unset($row['terms_ciphertext']);
        }
        unset($row);
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function onboardingCases(int $candidateId): array
    {
        $statement = $this->db->prepare(
            'SELECT oc.public_id, oc.status, oc.target_start_date, oc.created_at, oc.updated_at,
                    j.title AS job_title, ot.template_name, ot.version_number
               FROM recruitment_onboarding_cases oc
               JOIN recruitment_applications a ON a.application_id = oc.application_id
               JOIN recruitment_jobs j ON j.job_id = a.job_id
               JOIN recruitment_onboarding_templates ot ON ot.template_id = oc.template_id
              WHERE a.candidate_id = :candidate_id
              ORDER BY oc.updated_at DESC, oc.onboarding_case_id DESC'
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $cases = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = $this->db->prepare(
            'SELECT oi.public_id, oi.item_code, oi.title, oi.purpose_text, oi.visibility_text,
                    oi.item_type, oi.classification, oi.required_flag, oi.due_at, oi.status,
                    oi.review_reason
               FROM recruitment_onboarding_items oi
               JOIN recruitment_onboarding_cases oc ON oc.onboarding_case_id = oi.onboarding_case_id
               JOIN recruitment_applications a ON a.application_id = oc.application_id
              WHERE a.candidate_id = :candidate_id AND oc.public_id = :case_public_id
              ORDER BY oi.due_at IS NULL, oi.due_at, oi.onboarding_item_id'
        );
        foreach ($cases as &$case) {
            $items->execute(['candidate_id' => $candidateId, 'case_public_id' => $case['public_id']]);
            $case['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        unset($case);
        return $cases;
    }

    /** @return list<array<string, mixed>> */
    public function documents(int $candidateId): array
    {
        $statement = $this->db->prepare(
            'SELECT d.public_id, dr.public_id AS request_public_id, dr.purpose_code, dr.classification,
                    dr.request_status, dr.due_at, d.media_type, d.byte_size, d.content_sha256,
                    d.scan_status, d.review_status, d.uploaded_at, d.reviewed_at
               FROM recruitment_document_requests dr
               JOIN recruitment_applications a ON a.application_id = dr.application_id
               LEFT JOIN recruitment_documents d ON d.request_id = dr.request_id AND d.deleted_at IS NULL
              WHERE a.candidate_id = :candidate_id
              ORDER BY dr.due_at IS NULL, dr.due_at, dr.request_id'
        );
        $statement->execute(['candidate_id' => $candidateId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function messages(int $candidateId): array
    {
        $statement = $this->db->prepare(
            'SELECT public_id, direction, message_type, subject_ciphertext, body_ciphertext,
                    sender_type, sent_at, read_at
               FROM recruitment_candidate_messages
              WHERE candidate_id = :candidate_id
              ORDER BY sent_at DESC, message_id DESC LIMIT 100'
        );
        $statement->execute(['candidate_id' => $candidateId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['subject'] = RecruitmentSecurity::decrypt((string)$row['subject_ciphertext'], $this->dataKey);
            $row['body'] = RecruitmentSecurity::decrypt((string)$row['body_ciphertext'], $this->dataKey);
            unset($row['subject_ciphertext'], $row['body_ciphertext']);
        }
        unset($row);
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function notificationHistory(int $candidateId): array
    {
        $statement = $this->db->prepare(
            'SELECT message_type, delivery_status, attempt_count, available_at, sent_at, last_error_code, created_at
               FROM recruitment_notification_outbox
              WHERE candidate_id = :candidate_id
              ORDER BY created_at DESC, notification_id DESC LIMIT 100'
        );
        $statement->execute(['candidate_id' => $candidateId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sendSupportMessage(int $candidateId, ?string $applicationPublicId, string $subject, string $body): string
    {
        $subject = RecruitmentContentPolicy::requiredText($subject, 'Message subject', 3, 190);
        $body = RecruitmentContentPolicy::requiredText($body, 'Message', 10, 4000);
        $applicationId = null;
        if ($applicationPublicId !== null && trim($applicationPublicId) !== '') {
            $application = $this->db->prepare('SELECT application_id FROM recruitment_applications WHERE candidate_id = :candidate_id AND public_id = :public_id LIMIT 1');
            $application->execute(['candidate_id' => $candidateId, 'public_id' => trim($applicationPublicId)]);
            $applicationId = $application->fetchColumn();
            if ($applicationId === false) {
                throw new DomainException('Application not found.');
            }
        }
        $publicId = self::uuidV4();
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO recruitment_candidate_messages
                    (public_id, candidate_id, application_id, direction, message_type,
                     subject_ciphertext, body_ciphertext, sender_type, sender_reference)
                 VALUES
                    (:public_id, :candidate_id, :application_id, 'inbound', 'candidate_support',
                     :subject_ciphertext, :body_ciphertext, 'candidate', :candidate_reference)"
            )->execute([
                'public_id' => $publicId,
                'candidate_id' => $candidateId,
                'application_id' => $applicationId === false ? null : $applicationId,
                'subject_ciphertext' => RecruitmentSecurity::encrypt($subject, $this->dataKey),
                'body_ciphertext' => RecruitmentSecurity::encrypt($body, $this->dataKey),
                'candidate_reference' => (string)$candidateId,
            ]);
            $this->audit('candidate_support_message_sent', $candidateId, 'candidate_message', $publicId, [
                'application_public_id' => $applicationPublicId,
            ]);
            $this->db->commit();
            return $publicId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function setNotificationPreference(int $candidateId, string $channel, string $messageType, bool $enabled): void
    {
        $channel = RecruitmentContentPolicy::oneOf($channel, 'notification channel', ['email']);
        $messageType = RecruitmentContentPolicy::oneOf($messageType, 'message type', ['interview_invitation', 'offer_available', 'onboarding_action']);
        $this->db->prepare(
            'INSERT INTO recruitment_candidate_notification_preferences
                (candidate_id, channel, message_type, enabled_flag)
             VALUES (:candidate_id, :channel, :message_type, :enabled_flag)
             ON DUPLICATE KEY UPDATE enabled_flag = VALUES(enabled_flag)'
        )->execute([
            'candidate_id' => $candidateId,
            'channel' => $channel,
            'message_type' => $messageType,
            'enabled_flag' => $enabled ? 1 : 0,
        ]);
        $this->audit('candidate_notification_preference_changed', $candidateId, 'candidate', (string)$candidateId, [
            'channel' => $channel,
            'message_type' => $messageType,
            'enabled' => $enabled,
        ]);
    }

    public function submitPrivacyRequest(int $candidateId, string $requestType, string $details): string
    {
        $requestType = RecruitmentContentPolicy::oneOf($requestType, 'privacy request', ['access', 'correction', 'deletion', 'restriction', 'objection']);
        $details = RecruitmentContentPolicy::requiredText($details, 'Privacy request details', 10, 4000);
        $publicId = self::uuidV4();
        $dueAt = gmdate('Y-m-d H:i:s', time() + (30 * 86400));
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO recruitment_candidate_privacy_requests
                    (public_id, candidate_id, request_type, request_ciphertext, due_at)
                 VALUES (:public_id, :candidate_id, :request_type, :request_ciphertext, :due_at)'
            )->execute([
                'public_id' => $publicId,
                'candidate_id' => $candidateId,
                'request_type' => $requestType,
                'request_ciphertext' => RecruitmentSecurity::encrypt($details, $this->dataKey),
                'due_at' => $dueAt,
            ]);
            $this->audit('candidate_privacy_request_received', $candidateId, 'privacy_request', $publicId, [
                'request_type' => $requestType,
                'due_at' => $dueAt,
            ]);
            $this->db->commit();
            return $publicId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    private function decryptJson(string $ciphertext): array
    {
        $value = json_decode(RecruitmentSecurity::decrypt($ciphertext, $this->dataKey), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new RuntimeException('Candidate application data is invalid.');
        }
        return $value;
    }

    private function audit(string $eventType, int $candidateId, string $subjectType, string $subjectReference, array $metadata): void
    {
        $this->db->prepare(
            'INSERT INTO recruitment_audit_events
                (event_type, actor_type, actor_reference, subject_type, subject_reference, reason_code, metadata_json)
             VALUES (:event_type, \'candidate\', :actor_reference, :subject_type, :subject_reference, \'self_service\', :metadata_json)'
        )->execute([
            'event_type' => $eventType,
            'actor_reference' => (string)$candidateId,
            'subject_type' => $subjectType,
            'subject_reference' => $subjectReference,
            'metadata_json' => RecruitmentContentPolicy::canonicalJson($metadata),
        ]);
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
