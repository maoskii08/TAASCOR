<?php

declare(strict_types=1);

require_once __DIR__ . '/RecruitmentPolicy.php';
require_once __DIR__ . '/RecruitmentSecurity.php';
require_once __DIR__ . '/RecruitmentContentPolicy.php';
require_once __DIR__ . '/RecruitmentStaffAccessService.php';
require_once __DIR__ . '/RecruitmentNotificationOutbox.php';

final class RecruitmentWorkflowService
{
    public function __construct(
        private PDO $db,
        private string $dataKey,
        private RecruitmentStaffAccessService $access,
        private RecruitmentNotificationOutbox $outbox
    ) {
        if (strlen($dataKey) < 32) {
            throw new InvalidArgumentException('The recruitment data key must contain at least 32 characters.');
        }
    }

    public function createRequisition(array $input, string $actor): string
    {
        $actor = RecruitmentContentPolicy::requiredText($actor, 'Actor', 1, 190);
        $clientId = isset($input['client_id']) && (string)$input['client_id'] !== ''
            ? RecruitmentContentPolicy::positiveInteger($input['client_id'], 'Client', PHP_INT_MAX)
            : null;
        $scope = $clientId === null ? '*' : (string)$clientId;
        $this->access->assertCapability($actor, 'requisition.prepare', $clientId === null ? 'global' : 'client', $scope);

        $publicId = self::uuidV4();
        $code = strtoupper(RecruitmentContentPolicy::requiredText($input['requisition_code'] ?? '', 'Requisition code', 3, 40));
        $title = RecruitmentContentPolicy::requiredText($input['title'] ?? '', 'Requisition title', 3, 190);
        $headcount = RecruitmentContentPolicy::positiveInteger($input['headcount'] ?? null, 'Headcount', 10000);
        $employmentType = RecruitmentContentPolicy::oneOf(
            $input['employment_type'] ?? '',
            'employment type',
            ['regular', 'probationary', 'fixed_term', 'project_based', 'part_time', 'internship']
        );
        $businessReason = RecruitmentContentPolicy::requiredText($input['business_reason'] ?? '', 'Business reason', 10, 4000);
        $hiringOwner = RecruitmentContentPolicy::requiredText($input['hiring_owner_username'] ?? '', 'Hiring owner', 1, 190);
        $recruitmentOwner = RecruitmentContentPolicy::optionalText($input['recruitment_owner_username'] ?? '', 'Recruitment owner', 190);
        $targetStartDate = RecruitmentContentPolicy::date($input['target_start_date'] ?? '', 'target start date');
        $clientLocationId = isset($input['client_location_id']) && (string)$input['client_location_id'] !== ''
            ? RecruitmentContentPolicy::positiveInteger($input['client_location_id'], 'Client location', PHP_INT_MAX)
            : null;

        return $this->transaction(function () use (
            $publicId,
            $code,
            $title,
            $headcount,
            $employmentType,
            $clientId,
            $clientLocationId,
            $hiringOwner,
            $recruitmentOwner,
            $actor,
            $businessReason,
            $targetStartDate
        ): string {
            $statement = $this->db->prepare(
                "INSERT INTO recruitment_requisitions
                    (public_id, requisition_code, title, headcount, employment_type, client_id,
                     client_location_id, hiring_owner_username, recruitment_owner_username,
                     requested_by_username, business_reason, target_start_date, status)
                 VALUES
                    (:public_id, :requisition_code, :title, :headcount, :employment_type, :client_id,
                     :client_location_id, :hiring_owner_username, :recruitment_owner_username,
                     :requested_by_username, :business_reason, :target_start_date, 'draft')"
            );
            $statement->execute([
                'public_id' => $publicId,
                'requisition_code' => $code,
                'title' => $title,
                'headcount' => $headcount,
                'employment_type' => $employmentType,
                'client_id' => $clientId,
                'client_location_id' => $clientLocationId,
                'hiring_owner_username' => $hiringOwner,
                'recruitment_owner_username' => $recruitmentOwner,
                'requested_by_username' => $actor,
                'business_reason' => $businessReason,
                'target_start_date' => $targetStartDate,
            ]);
            $this->audit('requisition_created', $actor, 'requisition', $publicId, 'business_request', [
                'requisition_code' => $code,
                'client_id' => $clientId,
                'headcount' => $headcount,
            ]);
            return $publicId;
        });
    }

    public function transitionRequisition(string $publicId, string $toStatus, string $reason, string $actor): void
    {
        $publicId = RecruitmentContentPolicy::requiredText($publicId, 'Requisition reference', 36, 36);
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Decision reason', 3, 500);
        $actor = RecruitmentContentPolicy::requiredText($actor, 'Actor', 1, 190);

        $this->transaction(function () use ($publicId, $toStatus, $reason, $actor): void {
            $requisition = $this->lockByPublicId('recruitment_requisitions', 'requisition_id', $publicId);
            if ($requisition === null) {
                throw new DomainException('Requisition not found.');
            }
            $clientId = $requisition['client_id'] === null ? null : (int)$requisition['client_id'];
            $scopeType = $clientId === null ? 'global' : 'client';
            $scopeReference = $clientId === null ? '*' : (string)$clientId;
            $decision = in_array($toStatus, ['approved', 'rejected'], true);
            $this->access->assertCapability($actor, $decision ? 'requisition.approve' : 'requisition.prepare', $scopeType, $scopeReference);
            $fromStatus = (string)$requisition['status'];
            if (!RecruitmentPolicy::canTransition('requisition', $fromStatus, $toStatus)) {
                throw new DomainException('That requisition transition is not allowed.');
            }
            if ($decision && !RecruitmentAuthorizationPolicy::makerCheckerSatisfied((string)$requisition['requested_by_username'], $actor)) {
                throw new DomainException('The requisition requester cannot approve or reject the same request.');
            }

            $statement = $this->db->prepare(
                "UPDATE recruitment_requisitions
                    SET status = :to_status,
                        submitted_at = CASE WHEN :to_status_submitted = 'pending_approval' THEN UTC_TIMESTAMP() ELSE submitted_at END,
                        approved_at = CASE WHEN :to_status_approved = 'approved' THEN UTC_TIMESTAMP() ELSE approved_at END,
                        closed_at = CASE WHEN :to_status_closed = 'cancelled' THEN UTC_TIMESTAMP() ELSE closed_at END
                  WHERE requisition_id = :requisition_id AND status = :from_status"
            );
            $statement->execute([
                'to_status' => $toStatus,
                'to_status_submitted' => $toStatus,
                'to_status_approved' => $toStatus,
                'to_status_closed' => $toStatus,
                'requisition_id' => $requisition['requisition_id'],
                'from_status' => $fromStatus,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The requisition changed while it was being updated.');
            }
            if ($decision) {
                $approval = $this->db->prepare(
                    'INSERT INTO recruitment_requisition_approvals
                        (requisition_id, decision, decided_by_username, decision_reason)
                     VALUES (:requisition_id, :decision, :actor, :reason)'
                );
                $approval->execute([
                    'requisition_id' => $requisition['requisition_id'],
                    'decision' => $toStatus,
                    'actor' => $actor,
                    'reason' => $reason,
                ]);
            }
            $this->audit('requisition_status_changed', $actor, 'requisition', $publicId, $reason, [
                'from' => $fromStatus,
                'to' => $toStatus,
            ]);
        });
    }

    public function createJobDraft(string $requisitionPublicId, array $input, string $actor): string
    {
        $actor = RecruitmentContentPolicy::requiredText($actor, 'Actor', 1, 190);
        $this->access->assertCapability($actor, 'job.prepare');
        $publicId = self::uuidV4();
        $job = $this->validatedJobInput($input);

        return $this->transaction(function () use ($requisitionPublicId, $job, $actor, $publicId): string {
            $requisition = $this->lockByPublicId('recruitment_requisitions', 'requisition_id', $requisitionPublicId);
            if ($requisition === null || (string)$requisition['status'] !== 'approved') {
                throw new DomainException('An approved requisition is required before a job draft can be created.');
            }
            $statement = $this->db->prepare(
                "INSERT INTO recruitment_jobs
                    (requisition_id, public_id, slug, title, location_label, work_arrangement,
                     employment_type, summary, description_html, requirements_html, hiring_process_html,
                     publication_status, content_version, opens_at, closes_at)
                 VALUES
                    (:requisition_id, :public_id, :slug, :title, :location_label, :work_arrangement,
                     :employment_type, :summary, :description_html, :requirements_html, :hiring_process_html,
                     'draft', 1, :opens_at, :closes_at)"
            );
            $statement->execute(['requisition_id' => $requisition['requisition_id'], 'public_id' => $publicId] + $job);
            $jobId = (int)$this->db->lastInsertId();
            $this->recordPublicationEvent($jobId, 'draft_created', 1, $job, $actor, 'Initial approved-requisition draft');
            $this->audit('job_draft_created', $actor, 'job', $publicId, 'approved_requisition', [
                'requisition_public_id' => $requisitionPublicId,
                'content_version' => 1,
            ]);
            return $publicId;
        });
    }

    public function updateJobContent(string $jobPublicId, array $input, string $reason, string $actor): int
    {
        $actor = RecruitmentContentPolicy::requiredText($actor, 'Actor', 1, 190);
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Change reason', 3, 500);
        $this->access->assertCapability($actor, 'job.prepare');
        $jobInput = $this->validatedJobInput($input);

        return $this->transaction(function () use ($jobPublicId, $jobInput, $reason, $actor): int {
            $job = $this->lockByPublicId('recruitment_jobs', 'job_id', $jobPublicId);
            if ($job === null || !in_array((string)$job['publication_status'], ['draft', 'paused'], true)) {
                throw new DomainException('Only a draft or paused job can be edited.');
            }
            $version = (int)$job['content_version'] + 1;
            $statement = $this->db->prepare(
                'UPDATE recruitment_jobs
                    SET slug = :slug, title = :title, location_label = :location_label,
                        work_arrangement = :work_arrangement, employment_type = :employment_type,
                        summary = :summary, description_html = :description_html,
                        requirements_html = :requirements_html, hiring_process_html = :hiring_process_html,
                        opens_at = :opens_at, closes_at = :closes_at, content_version = :content_version
                  WHERE job_id = :job_id AND content_version = :expected_version'
            );
            $statement->execute($jobInput + [
                'content_version' => $version,
                'job_id' => $job['job_id'],
                'expected_version' => $job['content_version'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The job changed while its content was being updated.');
            }
            $this->recordPublicationEvent((int)$job['job_id'], 'content_updated', $version, $jobInput, $actor, $reason);
            $this->audit('job_content_updated', $actor, 'job', $jobPublicId, $reason, ['content_version' => $version]);
            return $version;
        });
    }

    public function transitionJob(string $jobPublicId, string $toStatus, string $reason, string $actor): void
    {
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Publication reason', 3, 500);
        $actor = RecruitmentContentPolicy::requiredText($actor, 'Actor', 1, 190);
        $this->access->assertCapability($actor, 'job.publish');

        $this->transaction(function () use ($jobPublicId, $toStatus, $reason, $actor): void {
            $job = $this->lockByPublicId('recruitment_jobs', 'job_id', $jobPublicId);
            if ($job === null) {
                throw new DomainException('Job not found.');
            }
            $fromStatus = (string)$job['publication_status'];
            if (!RecruitmentPolicy::canTransition('job', $fromStatus, $toStatus)) {
                throw new DomainException('That job-publication transition is not allowed.');
            }
            if ($toStatus === 'published') {
                $requisition = $this->lockById('recruitment_requisitions', 'requisition_id', (int)$job['requisition_id']);
                if ($requisition === null || (string)$requisition['status'] !== 'approved') {
                    throw new DomainException('The source requisition is not approved.');
                }
                if (!empty($job['closes_at']) && strtotime((string)$job['closes_at'] . ' UTC') <= time()) {
                    throw new DomainException('A job cannot publish with an elapsed closing time.');
                }
            }
            $statement = $this->db->prepare(
                "UPDATE recruitment_jobs
                    SET publication_status = :to_status,
                        published_at = CASE WHEN :publish_status = 'published' THEN COALESCE(published_at, UTC_TIMESTAMP()) ELSE published_at END,
                        published_by_username = CASE WHEN :publisher_status = 'published' THEN :actor ELSE published_by_username END
                  WHERE job_id = :job_id AND publication_status = :from_status"
            );
            $statement->execute([
                'to_status' => $toStatus,
                'publish_status' => $toStatus,
                'publisher_status' => $toStatus,
                'actor' => $actor,
                'job_id' => $job['job_id'],
                'from_status' => $fromStatus,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The job changed while its publication status was being updated.');
            }
            $snapshot = $this->jobSnapshot($job + ['publication_status' => $toStatus]);
            $this->recordPublicationEvent((int)$job['job_id'], $toStatus, (int)$job['content_version'], $snapshot, $actor, $reason);
            $this->audit('job_publication_status_changed', $actor, 'job', $jobPublicId, $reason, [
                'from' => $fromStatus,
                'to' => $toStatus,
                'content_version' => (int)$job['content_version'],
            ]);
        });
    }

    public function replaceScorecardCriteria(string $jobPublicId, array $criteria, string $actor): void
    {
        $actor = RecruitmentContentPolicy::requiredText($actor, 'Actor', 1, 190);
        $this->access->assertCapability($actor, 'interview.manage');
        if ($criteria === [] || count($criteria) > 20) {
            throw new InvalidArgumentException('Provide 1 to 20 scorecard criteria.');
        }
        $validated = [];
        $weightTotal = 0;
        foreach ($criteria as $index => $criterion) {
            if (!is_array($criterion)) {
                throw new InvalidArgumentException('Scorecard criteria are invalid.');
            }
            $code = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim((string)($criterion['code'] ?? ''))) ?? '');
            if ($code === '' || strlen($code) > 80) {
                throw new InvalidArgumentException('Each scorecard criterion requires a stable code.');
            }
            $weight = RecruitmentContentPolicy::positiveInteger($criterion['weight_bps'] ?? null, 'Criterion weight', 10000);
            $weightTotal += $weight;
            $validated[] = [
                'code' => $code,
                'label' => RecruitmentContentPolicy::requiredText($criterion['label'] ?? '', 'Criterion label', 2, 190),
                'guidance' => RecruitmentContentPolicy::requiredText($criterion['guidance'] ?? '', 'Criterion guidance', 5, 2000),
                'weight' => $weight,
                'order' => $index + 1,
            ];
        }
        if ($weightTotal !== 10000) {
            throw new InvalidArgumentException('Scorecard criterion weights must total 100 percent.');
        }

        $this->transaction(function () use ($jobPublicId, $validated, $actor): void {
            $job = $this->lockByPublicId('recruitment_jobs', 'job_id', $jobPublicId);
            if ($job === null || !in_array((string)$job['publication_status'], ['draft', 'paused'], true)) {
                throw new DomainException('Scorecard criteria can change only while the job is draft or paused.');
            }
            $this->db->prepare('UPDATE recruitment_scorecard_criteria SET retired_at = UTC_TIMESTAMP() WHERE job_id = :job_id AND retired_at IS NULL')
                ->execute(['job_id' => $job['job_id']]);
            $insert = $this->db->prepare(
                'INSERT INTO recruitment_scorecard_criteria
                    (job_id, criterion_code, criterion_label, guidance, weight_bps, display_order, active_from_version)
                 VALUES (:job_id, :criterion_code, :criterion_label, :guidance, :weight_bps, :display_order, :active_from_version)'
            );
            foreach ($validated as $criterion) {
                $insert->execute([
                    'job_id' => $job['job_id'],
                    'criterion_code' => $criterion['code'],
                    'criterion_label' => $criterion['label'],
                    'guidance' => $criterion['guidance'],
                    'weight_bps' => $criterion['weight'],
                    'display_order' => $criterion['order'],
                    'active_from_version' => $job['content_version'],
                ]);
            }
            $this->audit('job_scorecard_criteria_replaced', $actor, 'job', $jobPublicId, 'approved_job_criteria', [
                'criterion_count' => count($validated),
                'content_version' => (int)$job['content_version'],
            ]);
        });
    }

    public function saveApplicationDraft(int $candidateId, string $jobPublicId, array $profile, string $privacyNoticeVersion): string
    {
        if ($candidateId <= 0) {
            throw new InvalidArgumentException('A valid candidate is required.');
        }
        $notice = RecruitmentContentPolicy::requiredText($privacyNoticeVersion, 'Privacy notice version', 1, 80);
        $payload = [
            'full_name' => RecruitmentContentPolicy::requiredText($profile['full_name'] ?? '', 'Full name', 2, 190),
            'phone' => RecruitmentContentPolicy::requiredText($profile['phone'] ?? '', 'Phone number', 7, 40),
            'current_city' => RecruitmentContentPolicy::requiredText($profile['current_city'] ?? '', 'Current city', 2, 190),
            'experience_summary' => RecruitmentContentPolicy::optionalText($profile['experience_summary'] ?? '', 'Experience summary', 2000),
            'eligibility_confirmed' => filter_var($profile['eligibility_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
        if (!$payload['eligibility_confirmed']) {
            throw new InvalidArgumentException('Confirm eligibility to work in the Philippines.');
        }

        return $this->transaction(function () use ($candidateId, $jobPublicId, $payload, $notice): string {
            $candidate = $this->lockById('recruitment_candidates', 'candidate_id', $candidateId);
            if ($candidate === null || (string)$candidate['account_status'] !== 'active' || $candidate['email_verified_at'] === null || $candidate['deleted_at'] !== null) {
                throw new DomainException('A verified candidate account is required.');
            }
            $job = $this->lockByPublicId('recruitment_jobs', 'job_id', $jobPublicId);
            if ($job === null || !RecruitmentPolicy::publicJobCanAcceptApplications($job)) {
                throw new DomainException('This job is not accepting applications.');
            }
            $existing = $this->db->prepare('SELECT public_id, current_status FROM recruitment_applications WHERE candidate_id = :candidate_id AND job_id = :job_id LIMIT 1 FOR UPDATE');
            $existing->execute(['candidate_id' => $candidateId, 'job_id' => $job['job_id']]);
            $application = $existing->fetch(PDO::FETCH_ASSOC);
            $ciphertext = RecruitmentSecurity::encrypt(RecruitmentContentPolicy::canonicalJson($payload), $this->dataKey);
            if (is_array($application)) {
                if ((string)$application['current_status'] !== 'draft') {
                    throw new DomainException('An application for this role has already been submitted.');
                }
                $this->db->prepare('UPDATE recruitment_applications SET candidate_summary_ciphertext = :summary WHERE candidate_id = :candidate_id AND job_id = :job_id AND current_status = \'draft\'')
                    ->execute(['summary' => $ciphertext, 'candidate_id' => $candidateId, 'job_id' => $job['job_id']]);
                $publicId = (string)$application['public_id'];
                $eventType = 'application_draft_updated';
            } else {
                $publicId = self::uuidV4();
                $snapshot = $this->jobSnapshot($job);
                $this->db->prepare(
                    "INSERT INTO recruitment_applications
                        (public_id, candidate_id, job_id, current_status, job_snapshot, candidate_summary_ciphertext)
                     VALUES (:public_id, :candidate_id, :job_id, 'draft', :job_snapshot, :summary)"
                )->execute([
                    'public_id' => $publicId,
                    'candidate_id' => $candidateId,
                    'job_id' => $job['job_id'],
                    'job_snapshot' => RecruitmentContentPolicy::canonicalJson($snapshot),
                    'summary' => $ciphertext,
                ]);
                $applicationId = (int)$this->db->lastInsertId();
                $this->recordApplicationEvent($applicationId, null, 'draft', 'Application started.', 'candidate', (string)$candidateId, 'self_service');
                $eventType = 'application_draft_created';
            }
            $evidenceHash = hash('sha256', $candidateId . '|' . $notice . '|application|' . gmdate('Y-m-d'));
            $this->db->prepare(
                "INSERT INTO recruitment_candidate_consents
                    (candidate_id, notice_version, purpose_code, action, evidence_hash)
                 VALUES (:candidate_id, :notice_version, 'application', 'acknowledged', :evidence_hash)"
            )->execute([
                'candidate_id' => $candidateId,
                'notice_version' => $notice,
                'evidence_hash' => $evidenceHash,
            ]);
            $this->audit($eventType, 'candidate:' . $candidateId, 'application', $publicId, 'self_service', [
                'job_public_id' => $jobPublicId,
                'privacy_notice_version' => $notice,
            ]);
            return $publicId;
        });
    }

    public function submitApplication(int $candidateId, string $applicationPublicId, bool $jobChangeAcknowledged): void
    {
        $this->transaction(function () use ($candidateId, $applicationPublicId, $jobChangeAcknowledged): void {
            $application = $this->lockByPublicId('recruitment_applications', 'application_id', $applicationPublicId);
            if ($application === null || (int)$application['candidate_id'] !== $candidateId || (string)$application['current_status'] !== 'draft') {
                throw new DomainException('Application draft not found.');
            }
            if (trim((string)$application['candidate_summary_ciphertext']) === '') {
                throw new DomainException('Complete the required application information before submitting.');
            }
            $job = $this->lockById('recruitment_jobs', 'job_id', (int)$application['job_id']);
            if ($job === null || !RecruitmentPolicy::publicJobCanAcceptApplications($job)) {
                throw new DomainException('This job is no longer accepting applications.');
            }
            $currentSnapshotJson = RecruitmentContentPolicy::canonicalJson($this->jobSnapshot($job));
            $termsChanged = !hash_equals(hash('sha256', (string)$application['job_snapshot']), hash('sha256', $currentSnapshotJson));
            if ($termsChanged && !$jobChangeAcknowledged) {
                throw new DomainException('Review and acknowledge the updated job terms before submitting.');
            }
            $statement = $this->db->prepare(
                "UPDATE recruitment_applications
                    SET current_status = 'submitted', job_snapshot = :job_snapshot, submitted_at = UTC_TIMESTAMP()
                  WHERE application_id = :application_id AND candidate_id = :candidate_id AND current_status = 'draft'"
            );
            $statement->execute([
                'job_snapshot' => $currentSnapshotJson,
                'application_id' => $application['application_id'],
                'candidate_id' => $candidateId,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The application changed while it was being submitted.');
            }
            $this->recordApplicationEvent((int)$application['application_id'], 'draft', 'submitted', 'Application received.', 'candidate', (string)$candidateId, 'self_service');
            $this->audit('application_submitted', 'candidate:' . $candidateId, 'application', $applicationPublicId, 'self_service', [
                'job_terms_changed' => $termsChanged,
                'job_change_acknowledged' => $termsChanged && $jobChangeAcknowledged,
            ]);
        });
    }

    public function withdrawApplication(int $candidateId, string $applicationPublicId, string $reason): void
    {
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Withdrawal reason', 3, 500);
        $this->transaction(function () use ($candidateId, $applicationPublicId, $reason): void {
            $application = $this->lockByPublicId('recruitment_applications', 'application_id', $applicationPublicId);
            if ($application === null || (int)$application['candidate_id'] !== $candidateId) {
                throw new DomainException('Application not found.');
            }
            $fromStatus = (string)$application['current_status'];
            if (!RecruitmentPolicy::canTransition('application', $fromStatus, 'withdrawn')) {
                throw new DomainException('This application can no longer be withdrawn.');
            }
            $statement = $this->db->prepare(
                "UPDATE recruitment_applications
                    SET current_status = 'withdrawn', withdrawn_at = UTC_TIMESTAMP(), closed_at = UTC_TIMESTAMP()
                  WHERE application_id = :application_id AND candidate_id = :candidate_id AND current_status = :from_status"
            );
            $statement->execute([
                'application_id' => $application['application_id'],
                'candidate_id' => $candidateId,
                'from_status' => $fromStatus,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The application changed while it was being withdrawn.');
            }
            $this->recordApplicationEvent((int)$application['application_id'], $fromStatus, 'withdrawn', 'Application withdrawn.', 'candidate', (string)$candidateId, 'candidate_withdrawal');
            $this->audit('application_withdrawn', 'candidate:' . $candidateId, 'application', $applicationPublicId, 'candidate_withdrawal', [
                'from' => $fromStatus,
                'candidate_reason_sha256' => hash('sha256', $reason),
            ]);
        });
    }

    public function transitionApplication(string $applicationPublicId, string $toStatus, string $candidateMessage, string $reasonCode, string $actor): void
    {
        $this->access->assertCapability($actor, 'application.manage');
        $candidateMessage = RecruitmentContentPolicy::requiredText($candidateMessage, 'Candidate message', 3, 500);
        $reasonCode = RecruitmentContentPolicy::requiredText($reasonCode, 'Reason code', 2, 80);

        $this->transaction(function () use ($applicationPublicId, $toStatus, $candidateMessage, $reasonCode, $actor): void {
            $application = $this->lockByPublicId('recruitment_applications', 'application_id', $applicationPublicId);
            if ($application === null) {
                throw new DomainException('Application not found.');
            }
            $fromStatus = (string)$application['current_status'];
            if (!RecruitmentPolicy::canTransition('application', $fromStatus, $toStatus)) {
                throw new DomainException('That application transition is not allowed.');
            }
            $statement = $this->db->prepare(
                "UPDATE recruitment_applications
                    SET current_status = :to_status,
                        withdrawn_at = CASE WHEN :withdrawn_status = 'withdrawn' THEN UTC_TIMESTAMP() ELSE withdrawn_at END,
                        closed_at = CASE WHEN :closed_status IN ('converted', 'declined', 'withdrawn', 'offer_declined') THEN UTC_TIMESTAMP() ELSE closed_at END
                  WHERE application_id = :application_id AND current_status = :from_status"
            );
            $statement->execute([
                'to_status' => $toStatus,
                'withdrawn_status' => $toStatus,
                'closed_status' => $toStatus,
                'application_id' => $application['application_id'],
                'from_status' => $fromStatus,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The application changed while its status was being updated.');
            }
            $this->recordApplicationEvent((int)$application['application_id'], $fromStatus, $toStatus, $candidateMessage, 'staff', $actor, $reasonCode);
            $this->audit('application_status_changed', $actor, 'application', $applicationPublicId, $reasonCode, [
                'from' => $fromStatus,
                'to' => $toStatus,
            ]);
        });
    }

    public function assignApplication(string $applicationPublicId, string $assignee, string $assignmentRole, string $actor): void
    {
        $this->access->assertCapability($actor, 'candidate.assign');
        $assignee = RecruitmentContentPolicy::requiredText($assignee, 'Assignee', 1, 190);
        $assignmentRole = RecruitmentContentPolicy::oneOf($assignmentRole, 'assignment role', ['recruiter', 'hiring_manager', 'coordinator']);

        $this->transaction(function () use ($applicationPublicId, $assignee, $assignmentRole, $actor): void {
            $application = $this->lockByPublicId('recruitment_applications', 'application_id', $applicationPublicId);
            if ($application === null || in_array((string)$application['current_status'], ['draft', 'converted', 'declined', 'withdrawn', 'offer_declined'], true)) {
                throw new DomainException('Only an active submitted application can be assigned.');
            }
            $this->db->prepare(
                'UPDATE recruitment_application_assignments
                    SET released_at = UTC_TIMESTAMP(), released_by_username = :actor, release_reason = :reason
                  WHERE application_id = :application_id AND assignment_role = :assignment_role AND released_at IS NULL'
            )->execute([
                'actor' => $actor,
                'reason' => 'Reassigned',
                'application_id' => $application['application_id'],
                'assignment_role' => $assignmentRole,
            ]);
            $this->db->prepare(
                'INSERT INTO recruitment_application_assignments
                    (application_id, assignee_username, assignment_role, assigned_by_username)
                 VALUES (:application_id, :assignee, :assignment_role, :actor)'
            )->execute([
                'application_id' => $application['application_id'],
                'assignee' => $assignee,
                'assignment_role' => $assignmentRole,
                'actor' => $actor,
            ]);
            $this->audit('application_assigned', $actor, 'application', $applicationPublicId, 'workload_assignment', [
                'assignee' => $assignee,
                'assignment_role' => $assignmentRole,
            ]);
        });
    }

    public function addStaffNote(string $applicationPublicId, string $note, string $visibility, string $actor): int
    {
        $this->access->assertCapability($actor, 'candidate.view');
        $note = RecruitmentContentPolicy::requiredText($note, 'Staff note', 3, 4000);
        $visibility = RecruitmentContentPolicy::oneOf($visibility, 'note visibility', ['recruitment', 'hiring_panel', 'hr_restricted']);

        return $this->transaction(function () use ($applicationPublicId, $note, $visibility, $actor): int {
            $application = $this->lockByPublicId('recruitment_applications', 'application_id', $applicationPublicId);
            if ($application === null) {
                throw new DomainException('Application not found.');
            }
            $statement = $this->db->prepare(
                'INSERT INTO recruitment_application_staff_notes
                    (application_id, note_ciphertext, visibility, created_by_username)
                 VALUES (:application_id, :note_ciphertext, :visibility, :actor)'
            );
            $statement->execute([
                'application_id' => $application['application_id'],
                'note_ciphertext' => RecruitmentSecurity::encrypt($note, $this->dataKey),
                'visibility' => $visibility,
                'actor' => $actor,
            ]);
            $noteId = (int)$this->db->lastInsertId();
            $this->audit('application_staff_note_added', $actor, 'application', $applicationPublicId, 'case_management', [
                'note_id' => $noteId,
                'visibility' => $visibility,
            ]);
            return $noteId;
        });
    }

    public function scheduleInterview(string $applicationPublicId, array $input, array $participants, string $actor): string
    {
        $this->access->assertCapability($actor, 'interview.manage');
        $startsAt = RecruitmentContentPolicy::utcDateTime($input['starts_at'] ?? '', 'interview start');
        $endsAt = RecruitmentContentPolicy::utcDateTime($input['ends_at'] ?? '', 'interview end');
        if (strtotime($endsAt . ' UTC') <= strtotime($startsAt . ' UTC')) {
            throw new InvalidArgumentException('Interview end must be after its start.');
        }
        if (strtotime($startsAt . ' UTC') <= time()) {
            throw new InvalidArgumentException('Interview start must be in the future.');
        }
        $interviewType = RecruitmentContentPolicy::oneOf($input['interview_type'] ?? '', 'interview type', ['screening', 'functional', 'hiring_manager', 'panel', 'final']);
        $timezone = RecruitmentContentPolicy::requiredText($input['timezone_name'] ?? '', 'Timezone', 1, 80);
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Select a valid IANA timezone.');
        }
        $locationType = RecruitmentContentPolicy::oneOf($input['location_type'] ?? '', 'location type', ['video', 'phone', 'onsite']);
        $location = RecruitmentContentPolicy::requiredText($input['location'] ?? '', 'Interview location', 3, 1000);
        $instructions = RecruitmentContentPolicy::optionalText($input['instructions'] ?? '', 'Interview instructions', 2000);
        $accessibilityRoute = RecruitmentContentPolicy::optionalText($input['accessibility_route'] ?? '', 'Accessibility route', 255);
        if ($participants === [] || count($participants) > 20) {
            throw new InvalidArgumentException('Provide 1 to 20 interview participants.');
        }
        $normalizedParticipants = [];
        foreach ($participants as $participant) {
            if (!is_array($participant)) {
                throw new InvalidArgumentException('Interview participants are invalid.');
            }
            $normalizedParticipants[] = [
                'type' => RecruitmentContentPolicy::oneOf($participant['type'] ?? '', 'participant type', ['staff', 'candidate']),
                'reference' => RecruitmentContentPolicy::requiredText($participant['reference'] ?? '', 'Participant reference', 1, 190),
                'panel_role' => RecruitmentContentPolicy::optionalText($participant['panel_role'] ?? '', 'Panel role', 60),
            ];
        }
        $publicId = self::uuidV4();

        return $this->transaction(function () use (
            $applicationPublicId,
            $publicId,
            $interviewType,
            $startsAt,
            $endsAt,
            $timezone,
            $locationType,
            $location,
            $instructions,
            $accessibilityRoute,
            $normalizedParticipants,
            $actor
        ): string {
            $application = $this->lockByPublicId('recruitment_applications', 'application_id', $applicationPublicId);
            if ($application === null || !in_array((string)$application['current_status'], ['reviewing', 'shortlisted', 'interview', 'requirements'], true)) {
                throw new DomainException('The application is not eligible for interview scheduling.');
            }
            $statement = $this->db->prepare(
                "INSERT INTO recruitment_interviews
                    (public_id, application_id, interview_type, status, starts_at_utc, ends_at_utc,
                     timezone_name, location_type, location_ciphertext, instructions_ciphertext,
                     accessibility_route, created_by_username, sent_at)
                 VALUES
                    (:public_id, :application_id, :interview_type, 'scheduled', :starts_at_utc, :ends_at_utc,
                     :timezone_name, :location_type, :location_ciphertext, :instructions_ciphertext,
                     :accessibility_route, :actor, UTC_TIMESTAMP())"
            );
            $statement->execute([
                'public_id' => $publicId,
                'application_id' => $application['application_id'],
                'interview_type' => $interviewType,
                'starts_at_utc' => $startsAt,
                'ends_at_utc' => $endsAt,
                'timezone_name' => $timezone,
                'location_type' => $locationType,
                'location_ciphertext' => RecruitmentSecurity::encrypt($location, $this->dataKey),
                'instructions_ciphertext' => $instructions === null ? null : RecruitmentSecurity::encrypt($instructions, $this->dataKey),
                'accessibility_route' => $accessibilityRoute,
                'actor' => $actor,
            ]);
            $interviewId = (int)$this->db->lastInsertId();
            $insertParticipant = $this->db->prepare(
                'INSERT INTO recruitment_interview_participants
                    (interview_id, participant_type, participant_reference, panel_role)
                 VALUES (:interview_id, :participant_type, :participant_reference, :panel_role)'
            );
            foreach ($normalizedParticipants as $participant) {
                $insertParticipant->execute([
                    'interview_id' => $interviewId,
                    'participant_type' => $participant['type'],
                    'participant_reference' => $participant['reference'],
                    'panel_role' => $participant['panel_role'],
                ]);
            }
            $fromStatus = (string)$application['current_status'];
            if ($fromStatus !== 'interview') {
                if (!RecruitmentPolicy::canTransition('application', $fromStatus, 'interview')) {
                    throw new DomainException('The application cannot enter the interview stage.');
                }
                $this->db->prepare("UPDATE recruitment_applications SET current_status = 'interview' WHERE application_id = :application_id AND current_status = :from_status")
                    ->execute(['application_id' => $application['application_id'], 'from_status' => $fromStatus]);
                $this->recordApplicationEvent((int)$application['application_id'], $fromStatus, 'interview', 'Interview stage.', 'staff', $actor, 'interview_scheduled');
            }
            $this->outbox->enqueue((int)$application['candidate_id'], 'interview_invitation', [
                'interview_public_id' => $publicId,
                'starts_at_utc' => $startsAt,
                'ends_at_utc' => $endsAt,
                'timezone_name' => $timezone,
            ], $publicId);
            $this->audit('interview_scheduled', $actor, 'interview', $publicId, 'candidate_assessment', [
                'application_public_id' => $applicationPublicId,
                'participant_count' => count($normalizedParticipants),
            ]);
            return $publicId;
        });
    }

    public function respondToInterview(int $candidateId, string $interviewPublicId, string $response): void
    {
        $response = RecruitmentContentPolicy::oneOf($response, 'interview response', ['confirmed', 'reschedule_requested']);
        $this->transaction(function () use ($candidateId, $interviewPublicId, $response): void {
            $interview = $this->lockByPublicId('recruitment_interviews', 'interview_id', $interviewPublicId);
            if ($interview === null) {
                throw new DomainException('Interview not found.');
            }
            $application = $this->lockById('recruitment_applications', 'application_id', (int)$interview['application_id']);
            if ($application === null || (int)$application['candidate_id'] !== $candidateId) {
                throw new DomainException('Interview not found.');
            }
            $fromStatus = (string)$interview['status'];
            if (!RecruitmentPolicy::canTransition('interview', $fromStatus, $response)) {
                throw new DomainException('That interview response is no longer available.');
            }
            $statement = $this->db->prepare(
                "UPDATE recruitment_interviews
                    SET status = :status,
                        confirmed_at = CASE WHEN :confirmed_status = 'confirmed' THEN UTC_TIMESTAMP() ELSE confirmed_at END
                  WHERE interview_id = :interview_id AND status = :from_status"
            );
            $statement->execute([
                'status' => $response,
                'confirmed_status' => $response,
                'interview_id' => $interview['interview_id'],
                'from_status' => $fromStatus,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('The interview changed while the response was being saved.');
            }
            $this->db->prepare(
                "UPDATE recruitment_interview_participants
                    SET invitation_status = :status, responded_at = UTC_TIMESTAMP()
                  WHERE interview_id = :interview_id AND participant_type = 'candidate' AND participant_reference = :candidate_reference"
            )->execute([
                'status' => $response,
                'interview_id' => $interview['interview_id'],
                'candidate_reference' => (string)$candidateId,
            ]);
            $this->audit('interview_candidate_response', 'candidate:' . $candidateId, 'interview', $interviewPublicId, 'self_service', [
                'response' => $response,
            ]);
        });
    }

    public function submitScorecard(string $interviewPublicId, array $ratings, string $recommendation, string $actor): void
    {
        $this->access->assertCapability($actor, 'interview.manage');
        $recommendation = RecruitmentContentPolicy::oneOf($recommendation, 'recommendation', ['strong_yes', 'yes', 'mixed', 'no', 'strong_no']);
        if ($ratings === []) {
            throw new InvalidArgumentException('Complete every scorecard criterion before submitting.');
        }

        $this->transaction(function () use ($interviewPublicId, $ratings, $recommendation, $actor): void {
            $interview = $this->lockByPublicId('recruitment_interviews', 'interview_id', $interviewPublicId);
            if ($interview === null || !in_array((string)$interview['status'], ['scheduled', 'confirmed', 'completed'], true)) {
                throw new DomainException('The interview is not available for scorecard submission.');
            }
            $participantQuery = $this->db->prepare(
                "SELECT participant_id FROM recruitment_interview_participants
                  WHERE interview_id = :interview_id AND participant_type = 'staff' AND participant_reference = :actor LIMIT 1 FOR UPDATE"
            );
            $participantQuery->execute(['interview_id' => $interview['interview_id'], 'actor' => $actor]);
            $participantId = (int)$participantQuery->fetchColumn();
            if ($participantId <= 0) {
                throw new DomainException('Only an assigned interview participant may submit this scorecard.');
            }
            $criteriaQuery = $this->db->prepare(
                'SELECT c.criterion_id, c.criterion_code
                   FROM recruitment_scorecard_criteria c
                   JOIN recruitment_applications a ON a.job_id = c.job_id
                  WHERE a.application_id = :application_id AND c.retired_at IS NULL
                  ORDER BY c.display_order FOR UPDATE'
            );
            $criteriaQuery->execute(['application_id' => $interview['application_id']]);
            $criteria = $criteriaQuery->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($criteria === [] || count($criteria) !== count($ratings)) {
                throw new DomainException('The scorecard criteria changed or are incomplete.');
            }
            $normalized = [];
            foreach ($criteria as $criterion) {
                $code = (string)$criterion['criterion_code'];
                if (!isset($ratings[$code]) || !is_array($ratings[$code])) {
                    throw new InvalidArgumentException('Complete every scorecard criterion.');
                }
                $normalized[$code] = [
                    'criterion_id' => (int)$criterion['criterion_id'],
                    'rating' => RecruitmentContentPolicy::positiveInteger($ratings[$code]['rating'] ?? null, 'Rating', 5),
                    'evidence' => RecruitmentContentPolicy::requiredText($ratings[$code]['evidence'] ?? '', 'Rating evidence', 3, 2000),
                ];
            }
            $contentHash = hash('sha256', RecruitmentContentPolicy::canonicalJson([
                'recommendation' => $recommendation,
                'ratings' => $normalized,
            ]));
            $this->db->prepare(
                "INSERT INTO recruitment_interview_scorecards
                    (interview_id, participant_id, scorecard_status, recommendation, submitted_at, content_sha256)
                 VALUES (:interview_id, :participant_id, 'submitted', :recommendation, UTC_TIMESTAMP(), :content_sha256)"
            )->execute([
                'interview_id' => $interview['interview_id'],
                'participant_id' => $participantId,
                'recommendation' => $recommendation,
                'content_sha256' => $contentHash,
            ]);
            $scorecardId = (int)$this->db->lastInsertId();
            $insertScore = $this->db->prepare(
                'INSERT INTO recruitment_interview_scores
                    (scorecard_id, criterion_id, rating, evidence_ciphertext)
                 VALUES (:scorecard_id, :criterion_id, :rating, :evidence_ciphertext)'
            );
            foreach ($normalized as $score) {
                $insertScore->execute([
                    'scorecard_id' => $scorecardId,
                    'criterion_id' => $score['criterion_id'],
                    'rating' => $score['rating'],
                    'evidence_ciphertext' => RecruitmentSecurity::encrypt($score['evidence'], $this->dataKey),
                ]);
            }
            $this->audit('interview_scorecard_submitted', $actor, 'interview', $interviewPublicId, 'structured_assessment', [
                'scorecard_id' => $scorecardId,
                'content_sha256' => $contentHash,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function validatedJobInput(array $input): array
    {
        $opensAt = trim((string)($input['opens_at'] ?? '')) === '' ? null : RecruitmentContentPolicy::utcDateTime($input['opens_at'], 'opening time');
        $closesAt = trim((string)($input['closes_at'] ?? '')) === '' ? null : RecruitmentContentPolicy::utcDateTime($input['closes_at'], 'closing time');
        if ($opensAt !== null && $closesAt !== null && strtotime($closesAt . ' UTC') <= strtotime($opensAt . ' UTC')) {
            throw new InvalidArgumentException('Job closing time must be after its opening time.');
        }
        return [
            'slug' => RecruitmentContentPolicy::publicSlug($input['slug'] ?? ''),
            'title' => RecruitmentContentPolicy::requiredText($input['title'] ?? '', 'Job title', 3, 190),
            'location_label' => RecruitmentContentPolicy::requiredText($input['location_label'] ?? '', 'Location', 2, 190),
            'work_arrangement' => RecruitmentContentPolicy::oneOf($input['work_arrangement'] ?? '', 'work arrangement', ['onsite', 'hybrid', 'remote']),
            'employment_type' => RecruitmentContentPolicy::oneOf($input['employment_type'] ?? '', 'employment type', ['regular', 'probationary', 'fixed_term', 'project_based', 'part_time', 'internship']),
            'summary' => RecruitmentContentPolicy::requiredText($input['summary'] ?? '', 'Job summary', 20, 500),
            'description_html' => RecruitmentContentPolicy::plainTextHtml($input['description'] ?? '', 'Job description', 40, 12000),
            'requirements_html' => RecruitmentContentPolicy::plainTextHtml($input['requirements'] ?? '', 'Job requirements', 20, 8000),
            'hiring_process_html' => RecruitmentContentPolicy::plainTextHtml($input['hiring_process'] ?? '', 'Hiring process', 20, 4000),
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ];
    }

    /** @return array<string, mixed> */
    private function jobSnapshot(array $job): array
    {
        $snapshot = [];
        foreach ([
            'public_id', 'slug', 'title', 'location_label', 'work_arrangement', 'employment_type',
            'summary', 'description_html', 'requirements_html', 'hiring_process_html',
            'content_version', 'opens_at', 'closes_at', 'publication_status',
        ] as $field) {
            $snapshot[$field] = $job[$field] ?? null;
        }
        $snapshot['content_sha256'] = hash('sha256', RecruitmentContentPolicy::canonicalJson($snapshot));
        return $snapshot;
    }

    private function recordPublicationEvent(int $jobId, string $eventType, int $version, array $snapshot, string $actor, string $reason): void
    {
        $snapshotJson = RecruitmentContentPolicy::canonicalJson($snapshot);
        $this->db->prepare(
            'INSERT INTO recruitment_job_publication_events
                (job_id, event_type, content_version, content_sha256, channel, actor_username, reason, snapshot_json)
             VALUES (:job_id, :event_type, :content_version, :content_sha256, :channel, :actor, :reason, :snapshot_json)'
        )->execute([
            'job_id' => $jobId,
            'event_type' => $eventType,
            'content_version' => $version,
            'content_sha256' => hash('sha256', $snapshotJson),
            'channel' => 'taascor_website',
            'actor' => $actor,
            'reason' => $reason,
            'snapshot_json' => $snapshotJson,
        ]);
    }

    private function recordApplicationEvent(int $applicationId, ?string $from, string $to, string $message, string $actorType, string $actorReference, string $reasonCode): void
    {
        $this->db->prepare(
            'INSERT INTO recruitment_application_events
                (application_id, from_status, to_status, candidate_message, reason_code, changed_by_type, changed_by_reference)
             VALUES (:application_id, :from_status, :to_status, :candidate_message, :reason_code, :actor_type, :actor_reference)'
        )->execute([
            'application_id' => $applicationId,
            'from_status' => $from,
            'to_status' => $to,
            'candidate_message' => $message,
            'reason_code' => $reasonCode,
            'actor_type' => $actorType,
            'actor_reference' => $actorReference,
        ]);
    }

    private function audit(string $eventType, string $actor, string $subjectType, string $subjectReference, string $reasonCode, array $metadata): void
    {
        $actorType = str_starts_with($actor, 'candidate:') ? 'candidate' : 'staff';
        $actorReference = str_starts_with($actor, 'candidate:') ? substr($actor, 10) : $actor;
        $this->db->prepare(
            'INSERT INTO recruitment_audit_events
                (event_type, actor_type, actor_reference, subject_type, subject_reference, reason_code, metadata_json)
             VALUES (:event_type, :actor_type, :actor_reference, :subject_type, :subject_reference, :reason_code, :metadata_json)'
        )->execute([
            'event_type' => $eventType,
            'actor_type' => $actorType,
            'actor_reference' => $actorReference,
            'subject_type' => $subjectType,
            'subject_reference' => $subjectReference,
            'reason_code' => substr($reasonCode, 0, 80),
            'metadata_json' => RecruitmentContentPolicy::canonicalJson($metadata),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function lockByPublicId(string $table, string $primaryKey, string $publicId): ?array
    {
        $allowed = [
            'recruitment_requisitions' => 'requisition_id',
            'recruitment_jobs' => 'job_id',
            'recruitment_applications' => 'application_id',
            'recruitment_interviews' => 'interview_id',
        ];
        if (($allowed[$table] ?? null) !== $primaryKey) {
            throw new LogicException('Unsupported recruitment record lock.');
        }
        $statement = $this->db->prepare("SELECT * FROM {$table} WHERE public_id = :public_id LIMIT 1 FOR UPDATE");
        $statement->execute(['public_id' => $publicId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function lockById(string $table, string $primaryKey, int $id): ?array
    {
        $allowed = [
            'recruitment_candidates' => 'candidate_id',
            'recruitment_requisitions' => 'requisition_id',
            'recruitment_jobs' => 'job_id',
            'recruitment_applications' => 'application_id',
        ];
        if (($allowed[$table] ?? null) !== $primaryKey || $id <= 0) {
            throw new LogicException('Unsupported recruitment record lock.');
        }
        $statement = $this->db->prepare("SELECT * FROM {$table} WHERE {$primaryKey} = :id LIMIT 1 FOR UPDATE");
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function transaction(callable $operation): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
