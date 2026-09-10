<?php

declare(strict_types=1);

require_once __DIR__ . '/RecruitmentPolicy.php';
require_once __DIR__ . '/RecruitmentSecurity.php';
require_once __DIR__ . '/RecruitmentContentPolicy.php';
require_once __DIR__ . '/RecruitmentStaffAccessService.php';
require_once __DIR__ . '/EmployeeConversionAdapter.php';

final class RecruitmentConversionService
{
    public function __construct(
        private PDO $db,
        private string $dataKey,
        private RecruitmentStaffAccessService $access,
        private EmployeeConversionAdapter $employeeAdapter,
        private bool $executionEnabled
    ) {
        if (strlen($dataKey) < 32) {
            throw new InvalidArgumentException('The recruitment data key must contain at least 32 characters.');
        }
    }

    public function prepare(string $onboardingPublicId, array $employeePayload, string $actor): string
    {
        $this->access->assertCapability($actor, 'employee_conversion.prepare');
        $employeePayload = $this->employeeAdapter->normalizePayload($employeePayload);
        $payloadJson = RecruitmentContentPolicy::canonicalJson($employeePayload);
        $payloadHash = hash('sha256', $payloadJson);
        $publicId = self::uuidV4();

        return $this->transaction(function () use ($onboardingPublicId, $employeePayload, $payloadJson, $payloadHash, $publicId, $actor): string {
            $case = $this->lockByPublicId('recruitment_onboarding_cases', 'onboarding_case_id', $onboardingPublicId);
            if ($case === null || (string)$case['status'] !== 'ready_for_conversion') {
                throw new DomainException('The onboarding case is not ready for employee conversion.');
            }
            $application = $this->lockById('recruitment_applications', 'application_id', (int)$case['application_id']);
            if ($application === null || (string)$application['current_status'] !== 'ready_for_conversion') {
                throw new DomainException('The application is not ready for employee conversion.');
            }
            $idempotencyKey = hash('sha256', $case['onboarding_case_id'] . '|' . $payloadHash);
            $existing = $this->db->prepare('SELECT public_id, employee_payload_sha256 FROM recruitment_employee_conversion_requests WHERE onboarding_case_id = :case_id OR idempotency_key = :idempotency_key LIMIT 1 FOR UPDATE');
            $existing->execute(['case_id' => $case['onboarding_case_id'], 'idempotency_key' => $idempotencyKey]);
            $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($existingRow)) {
                if (hash_equals((string)$existingRow['employee_payload_sha256'], $payloadHash)) {
                    return (string)$existingRow['public_id'];
                }
                throw new DomainException('This onboarding case already has a different conversion request.');
            }
            $this->db->prepare(
                "INSERT INTO recruitment_employee_conversion_requests
                    (public_id, onboarding_case_id, application_id, idempotency_key,
                     employee_payload_ciphertext, employee_payload_sha256, status, prepared_by_username)
                 VALUES
                    (:public_id, :onboarding_case_id, :application_id, :idempotency_key,
                     :payload_ciphertext, :payload_sha256, 'pending_duplicate_check', :actor)"
            )->execute([
                'public_id' => $publicId,
                'onboarding_case_id' => $case['onboarding_case_id'],
                'application_id' => $application['application_id'],
                'idempotency_key' => $idempotencyKey,
                'payload_ciphertext' => RecruitmentSecurity::encrypt($payloadJson, $this->dataKey),
                'payload_sha256' => $payloadHash,
                'actor' => $actor,
            ]);
            $this->audit('employee_conversion_prepared', $actor, 'employee_conversion', $publicId, 'onboarding_ready', [
                'onboarding_public_id' => $onboardingPublicId,
                'payload_sha256' => $payloadHash,
                'payload_fields' => array_values(array_keys($employeePayload)),
            ]);
            return $publicId;
        });
    }

    /** @return array{clear:bool,evidence:array<string, mixed>} */
    public function runDuplicateCheck(string $conversionPublicId, string $actor): array
    {
        $this->access->assertCapability($actor, 'employee_conversion.prepare');
        return $this->transaction(function () use ($conversionPublicId, $actor): array {
            $request = $this->lockByPublicId('recruitment_employee_conversion_requests', 'conversion_request_id', $conversionPublicId);
            if ($request === null || !in_array((string)$request['status'], ['pending_duplicate_check', 'blocked'], true)) {
                throw new DomainException('The conversion request is not awaiting duplicate review.');
            }
            $payload = $this->decryptPayload((string)$request['employee_payload_ciphertext'], (string)$request['employee_payload_sha256']);
            $result = $this->employeeAdapter->duplicateCheck($payload);
            $status = $result['clear'] ? 'pending_approval' : 'blocked';
            $duplicateStatus = $result['clear'] ? 'clear' : 'possible_duplicate';
            $evidenceJson = RecruitmentContentPolicy::canonicalJson($result['evidence']);
            $this->db->prepare(
                'UPDATE recruitment_employee_conversion_requests
                    SET status = :status, duplicate_check_status = :duplicate_status,
                        duplicate_check_evidence_json = :evidence_json
                  WHERE conversion_request_id = :request_id AND status IN (\'pending_duplicate_check\', \'blocked\')'
            )->execute([
                'status' => $status,
                'duplicate_status' => $duplicateStatus,
                'evidence_json' => $evidenceJson,
                'request_id' => $request['conversion_request_id'],
            ]);
            $this->audit('employee_conversion_duplicate_checked', $actor, 'employee_conversion', $conversionPublicId, 'duplicate_prevention', [
                'outcome' => $duplicateStatus,
                'evidence_sha256' => hash('sha256', $evidenceJson),
            ]);
            return $result;
        });
    }

    public function decide(string $conversionPublicId, string $decision, string $reason, string $actor): void
    {
        $this->access->assertCapability($actor, 'employee_conversion.approve');
        $decision = RecruitmentContentPolicy::oneOf($decision, 'conversion decision', ['approved', 'rejected']);
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Decision reason', 3, 500);

        $this->transaction(function () use ($conversionPublicId, $decision, $reason, $actor): void {
            $request = $this->lockByPublicId('recruitment_employee_conversion_requests', 'conversion_request_id', $conversionPublicId);
            if ($request === null || (string)$request['status'] !== 'pending_approval' || (string)$request['duplicate_check_status'] !== 'clear') {
                throw new DomainException('The conversion request is not eligible for approval.');
            }
            if (!RecruitmentAuthorizationPolicy::makerCheckerSatisfied((string)$request['prepared_by_username'], $actor)) {
                throw new DomainException('The conversion preparer cannot decide the same request.');
            }
            $this->db->prepare(
                'UPDATE recruitment_employee_conversion_requests
                    SET status = :decision,
                        approved_by_username = CASE WHEN :approved_decision = \'approved\' THEN :actor ELSE NULL END,
                        approved_at = CASE WHEN :approved_time = \'approved\' THEN UTC_TIMESTAMP() ELSE NULL END
                  WHERE conversion_request_id = :request_id AND status = \'pending_approval\''
            )->execute([
                'decision' => $decision,
                'approved_decision' => $decision,
                'actor' => $actor,
                'approved_time' => $decision,
                'request_id' => $request['conversion_request_id'],
            ]);
            $this->db->prepare(
                'INSERT INTO recruitment_employee_conversion_reviews
                    (conversion_request_id, decision, reviewed_by_username, decision_reason, payload_sha256)
                 VALUES (:request_id, :decision, :actor, :reason, :payload_sha256)'
            )->execute([
                'request_id' => $request['conversion_request_id'],
                'decision' => $decision,
                'actor' => $actor,
                'reason' => $reason,
                'payload_sha256' => $request['employee_payload_sha256'],
            ]);
            $this->audit('employee_conversion_decided', $actor, 'employee_conversion', $conversionPublicId, $reason, [
                'decision' => $decision,
                'payload_sha256' => $request['employee_payload_sha256'],
            ]);
        });
    }

    public function execute(string $conversionPublicId, string $reason, string $actor): string
    {
        $this->access->assertCapability($actor, 'employee_conversion.approve');
        if (!$this->executionEnabled) {
            throw new DomainException('Employee conversion execution is not enabled for this release.');
        }
        $reason = RecruitmentContentPolicy::requiredText($reason, 'Execution reason', 3, 500);

        $result = $this->transaction(function () use ($conversionPublicId, $reason, $actor): array {
            $request = $this->lockByPublicId('recruitment_employee_conversion_requests', 'conversion_request_id', $conversionPublicId);
            if ($request === null) {
                throw new DomainException('Conversion request not found.');
            }
            $existing = $this->db->prepare('SELECT employee_reference FROM recruitment_candidate_employee_links WHERE conversion_request_id = :request_id AND reversed_at IS NULL LIMIT 1 FOR UPDATE');
            $existing->execute(['request_id' => $request['conversion_request_id']]);
            $existingReference = $existing->fetchColumn();
            if ($existingReference !== false && in_array((string)$request['status'], ['executed', 'reconciled', 'reconciliation_failed'], true)) {
                return ['employee_reference' => (string)$existingReference, 'blocked' => false];
            }
            if ((string)$request['status'] !== 'approved' || (string)$request['duplicate_check_status'] !== 'clear') {
                throw new DomainException('The conversion request is not approved for execution.');
            }
            if (!RecruitmentAuthorizationPolicy::makerCheckerSatisfied((string)$request['prepared_by_username'], $actor)) {
                throw new DomainException('The conversion preparer cannot execute the same request.');
            }
            $payload = $this->decryptPayload((string)$request['employee_payload_ciphertext'], (string)$request['employee_payload_sha256']);
            $duplicate = $this->employeeAdapter->duplicateCheck($payload);
            if (!$duplicate['clear']) {
                $evidenceJson = RecruitmentContentPolicy::canonicalJson($duplicate['evidence']);
                $this->db->prepare("UPDATE recruitment_employee_conversion_requests SET status = 'blocked', duplicate_check_status = 'possible_duplicate', duplicate_check_evidence_json = :evidence WHERE conversion_request_id = :request_id")
                    ->execute([
                        'evidence' => $evidenceJson,
                        'request_id' => $request['conversion_request_id'],
                    ]);
                $this->audit('employee_conversion_blocked', $actor, 'employee_conversion', $conversionPublicId, 'new_duplicate_evidence', [
                    'evidence_sha256' => hash('sha256', $evidenceJson),
                ]);
                return ['employee_reference' => null, 'blocked' => true];
            }
            $employeeReference = $this->employeeAdapter->createEmployee($payload);
            $application = $this->lockById('recruitment_applications', 'application_id', (int)$request['application_id']);
            $case = $this->lockById('recruitment_onboarding_cases', 'onboarding_case_id', (int)$request['onboarding_case_id']);
            if ($application === null || $case === null || (string)$application['current_status'] !== 'ready_for_conversion' || (string)$case['status'] !== 'ready_for_conversion') {
                throw new DomainException('The source onboarding records changed before employee creation could finish.');
            }
            $this->db->prepare(
                'INSERT INTO recruitment_candidate_employee_links
                    (conversion_request_id, candidate_id, application_id, employee_reference, linked_by_username)
                 VALUES (:request_id, :candidate_id, :application_id, :employee_reference, :actor)'
            )->execute([
                'request_id' => $request['conversion_request_id'],
                'candidate_id' => $application['candidate_id'],
                'application_id' => $application['application_id'],
                'employee_reference' => $employeeReference,
                'actor' => $actor,
            ]);
            $this->db->prepare("UPDATE recruitment_employee_conversion_requests SET status = 'executed', executed_at = UTC_TIMESTAMP() WHERE conversion_request_id = :request_id AND status = 'approved'")
                ->execute(['request_id' => $request['conversion_request_id']]);
            $this->db->prepare("UPDATE recruitment_onboarding_cases SET status = 'converted' WHERE onboarding_case_id = :case_id AND status = 'ready_for_conversion'")
                ->execute(['case_id' => $case['onboarding_case_id']]);
            $this->db->prepare("UPDATE recruitment_applications SET current_status = 'converted', closed_at = UTC_TIMESTAMP() WHERE application_id = :application_id AND current_status = 'ready_for_conversion'")
                ->execute(['application_id' => $application['application_id']]);
            $this->recordApplicationEvent((int)$application['application_id'], 'ready_for_conversion', 'converted', 'Employee record created.', 'staff', $actor, 'conversion_executed');
            $this->audit('employee_conversion_executed', $actor, 'employee_conversion', $conversionPublicId, $reason, [
                'employee_reference' => $employeeReference,
                'payload_sha256' => $request['employee_payload_sha256'],
            ]);
            return ['employee_reference' => $employeeReference, 'blocked' => false];
        });
        if ($result['blocked']) {
            throw new DomainException('Employee conversion stopped because new duplicate evidence was found.');
        }
        return (string)$result['employee_reference'];
    }

    public function reconcile(string $conversionPublicId, string $actor): bool
    {
        $this->access->assertCapability($actor, 'employee_conversion.approve');
        return $this->transaction(function () use ($conversionPublicId, $actor): bool {
            $request = $this->lockByPublicId('recruitment_employee_conversion_requests', 'conversion_request_id', $conversionPublicId);
            if ($request === null || !in_array((string)$request['status'], ['executed', 'reconciliation_failed'], true)) {
                throw new DomainException('The conversion request is not awaiting reconciliation.');
            }
            $link = $this->db->prepare('SELECT employee_reference FROM recruitment_candidate_employee_links WHERE conversion_request_id = :request_id AND reversed_at IS NULL LIMIT 1 FOR UPDATE');
            $link->execute(['request_id' => $request['conversion_request_id']]);
            $employeeReference = $link->fetchColumn();
            if ($employeeReference === false) {
                throw new DomainException('The conversion link is missing.');
            }
            $observed = $this->employeeAdapter->employeeSnapshot((string)$employeeReference);
            $observedJson = $observed === null ? null : RecruitmentContentPolicy::canonicalJson($observed);
            $observedHash = $observedJson === null ? null : hash('sha256', $observedJson);
            $expectedHash = (string)$request['employee_payload_sha256'];
            $matched = $observedHash !== null && hash_equals($expectedHash, $observedHash);
            $outcome = $matched ? 'matched' : 'mismatch';
            $details = [
                'employee_reference' => (string)$employeeReference,
                'expected_sha256' => $expectedHash,
                'observed_sha256' => $observedHash,
            ];
            $this->db->prepare(
                'INSERT INTO recruitment_employee_conversion_reconciliations
                    (conversion_request_id, employee_reference, outcome, expected_sha256,
                     observed_sha256, details_json, reconciled_by_username)
                 VALUES (:request_id, :employee_reference, :outcome, :expected_sha256,
                         :observed_sha256, :details_json, :actor)'
            )->execute([
                'request_id' => $request['conversion_request_id'],
                'employee_reference' => (string)$employeeReference,
                'outcome' => $outcome,
                'expected_sha256' => $expectedHash,
                'observed_sha256' => $observedHash,
                'details_json' => RecruitmentContentPolicy::canonicalJson($details),
                'actor' => $actor,
            ]);
            $this->db->prepare('UPDATE recruitment_employee_conversion_requests SET status = :status WHERE conversion_request_id = :request_id AND status IN (\'executed\', \'reconciliation_failed\')')
                ->execute([
                    'status' => $matched ? 'reconciled' : 'reconciliation_failed',
                    'request_id' => $request['conversion_request_id'],
                ]);
            $this->audit('employee_conversion_reconciled', $actor, 'employee_conversion', $conversionPublicId, 'post_creation_reconciliation', [
                'outcome' => $outcome,
                'employee_reference' => (string)$employeeReference,
                'expected_sha256' => $expectedHash,
                'observed_sha256' => $observedHash,
            ]);
            return $matched;
        });
    }

    /** @return array<string, mixed> */
    private function decryptPayload(string $ciphertext, string $expectedHash): array
    {
        $json = RecruitmentSecurity::decrypt($ciphertext, $this->dataKey);
        if (!hash_equals($expectedHash, hash('sha256', $json))) {
            throw new RuntimeException('The employee conversion payload failed its integrity check.');
        }
        $payload = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('The employee conversion payload is invalid.');
        }
        return $payload;
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
        $this->db->prepare(
            'INSERT INTO recruitment_audit_events
                (event_type, actor_type, actor_reference, subject_type, subject_reference, reason_code, metadata_json)
             VALUES (:event_type, \'staff\', :actor_reference, :subject_type, :subject_reference, :reason_code, :metadata_json)'
        )->execute([
            'event_type' => $eventType,
            'actor_reference' => $actor,
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
            'recruitment_onboarding_cases' => 'onboarding_case_id',
            'recruitment_employee_conversion_requests' => 'conversion_request_id',
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
            'recruitment_applications' => 'application_id',
            'recruitment_onboarding_cases' => 'onboarding_case_id',
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
