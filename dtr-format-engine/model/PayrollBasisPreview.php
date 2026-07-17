<?php

class PayrollBasisPreview
{
    public $db = null;

    private const SOURCE_CONTEXT = 'real_sample_batch10_profile_adapter';
    private const ALLOWED_PROFILES = ['COXON', 'DELTA'];
    private const BLOCKED_PROFILES = ['CYA'];
    private const GENERIC_ERROR = 'Unable to build the payroll basis preview. Please contact your administrator.';

    public function buildFromCurrentPreview(string $user): array
    {
        try {
            $approvals = $this->loadApprovals();
            $rows = $this->loadEligibleSourceRows($approvals);
            $blocked = $this->blockedProfileSummary();

            $this->db->beginTransaction();
            $this->clearPreviewTablesInternal();

            $groups = [];
            foreach ($rows as $row) {
                $payload = $row['payload'];
                $profileKey = (string)$payload['source_adapter'];
                $employee = $this->findEmployee((string)$payload['employee_identifier']);
                if (!$employee) {
                    continue;
                }

                $client = $this->findClient((int)$employee['client_id']);
                $location = $this->findLocation((int)$employee['client_location_id']);
                $periodStart = (string)($payload['period_start'] ?? '');
                $periodEnd = (string)($payload['period_end'] ?? '');
                $groupKey = implode('|', [
                    $profileKey,
                    (int)$employee['client_id'],
                    (int)$employee['client_location_id'],
                    $periodStart,
                    $periodEnd,
                ]);

                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'profile_key' => $profileKey,
                        'profile_name' => (string)($payload['source_profile'] ?? $profileKey),
                        'client_id' => (int)$employee['client_id'],
                        'client_name_snapshot' => (string)($client['client_name'] ?? ''),
                        'location_id' => (int)$employee['client_location_id'],
                        'location_name_snapshot' => (string)($location['location_name'] ?? ''),
                        'pay_period_start' => $this->dateOrNull($periodStart),
                        'pay_period_end' => $this->dateOrNull($periodEnd),
                        'source_batch_ids' => [],
                        'rows' => [],
                    ];
                }

                $groups[$groupKey]['source_batch_ids'][(int)$row['batch_id']] = (int)$row['batch_id'];
                $groups[$groupKey]['rows'][] = [
                    'source' => $row,
                    'payload' => $payload,
                    'employee' => $employee,
                    'client' => $client,
                    'location' => $location,
                ];
            }

            $headerCount = 0;
            $rowCount = 0;
            $profileCounts = array_fill_keys(self::ALLOWED_PROFILES, 0);
            $profileHours = array_fill_keys(self::ALLOWED_PROFILES, 0.0);

            foreach ($groups as $group) {
                $previewHeaderId = $this->insertHeader($group, $user);
                $headerCount++;

                foreach ($group['rows'] as $groupRow) {
                    $this->insertRow($previewHeaderId, $groupRow);
                    $profileKey = (string)$group['profile_key'];
                    $hours = (float)($groupRow['payload']['hours_worked'] ?? 0);
                    $profileCounts[$profileKey] = ($profileCounts[$profileKey] ?? 0) + 1;
                    $profileHours[$profileKey] = ($profileHours[$profileKey] ?? 0) + $hours;
                    $rowCount++;
                }

                $this->updateHeaderTotals($previewHeaderId);
            }

            $this->db->commit();

            return [
                'success' => 1,
                'mode' => 'local_payroll_basis_preview_only',
                'source_context' => self::SOURCE_CONTEXT,
                'headers_created' => $headerCount,
                'rows_created' => $rowCount,
                'profile_counts' => $profileCounts,
                'profile_hours' => [
                    'COXON' => round((float)($profileHours['COXON'] ?? 0), 2),
                    'DELTA' => round((float)($profileHours['DELTA'] ?? 0), 2),
                ],
                'blocked_profiles' => $blocked,
                'safety' => $this->safetyStatus(),
            ];
        } catch (\Throwable $th) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR payroll basis preview build failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function listPreview(): array
    {
        try {
            $headers = $this->db->query("
                SELECT
                    id,
                    preview_uid,
                    profile_key,
                    profile_name,
                    client_name_snapshot,
                    location_name_snapshot,
                    pay_period_start,
                    pay_period_end,
                    source_batch_count,
                    row_count,
                    eligible_row_count,
                    excluded_row_count,
                    total_preview_worked_hours,
                    basis_status,
                    approval_status,
                    payroll_handoff_status,
                    review_notes,
                    created_at
                FROM dtr_payroll_basis_preview_headers
                ORDER BY profile_key ASC, client_name_snapshot ASC, location_name_snapshot ASC, pay_period_start ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $rows = $this->db->query("
                SELECT
                    r.id,
                    h.preview_uid,
                    r.profile_key,
                    r.employee_id,
                    r.payroll_employee_id_snapshot,
                    r.employee_name_snapshot,
                    r.client_name_snapshot,
                    r.location_name_snapshot,
                    r.pay_period_start,
                    r.pay_period_end,
                    r.work_date,
                    r.worked_hours_preview,
                    r.worked_days_preview,
                    r.eligibility_status,
                    r.issue_exclusion_reason,
                    r.source_batch_uid,
                    r.source_row_number,
                    r.validation_status,
                    r.conflict_status,
                    r.approval_status,
                    r.payroll_handoff_status
                FROM dtr_payroll_basis_preview_rows r
                INNER JOIN dtr_payroll_basis_preview_headers h ON h.id = r.preview_header_id
                ORDER BY r.profile_key ASC, r.employee_name_snapshot ASC, r.work_date ASC, r.source_row_number ASC
                LIMIT 300
            ")->fetchAll(PDO::FETCH_ASSOC);

            $summary = [
                'headers' => count($headers),
                'rows' => count($rows),
                'profiles' => [],
                'total_preview_worked_hours' => 0.0,
                'payroll_handoff_status' => 'blocked',
                'mode' => 'local_payroll_basis_preview_only',
            ];

            foreach ($headers as $header) {
                $key = (string)$header['profile_key'];
                if (!isset($summary['profiles'][$key])) {
                    $summary['profiles'][$key] = [
                        'headers' => 0,
                        'rows' => 0,
                        'hours' => 0.0,
                    ];
                }
                $summary['profiles'][$key]['headers']++;
                $summary['profiles'][$key]['rows'] += (int)$header['row_count'];
                $summary['profiles'][$key]['hours'] += (float)$header['total_preview_worked_hours'];
                $summary['total_preview_worked_hours'] += (float)$header['total_preview_worked_hours'];
            }

            foreach ($summary['profiles'] as $key => $profile) {
                $summary['profiles'][$key]['hours'] = round((float)$profile['hours'], 2);
            }
            $summary['total_preview_worked_hours'] = round((float)$summary['total_preview_worked_hours'], 2);
            $summary['blocked_profiles'] = $this->blockedProfileSummary();
            $summary['safety'] = $this->safetyStatus();

            return [
                'success' => 1,
                'summary' => $summary,
                'headers' => $headers,
                'rows' => $rows,
            ];
        } catch (\Throwable $th) {
            error_log('DTR payroll basis preview listing failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    private function loadEligibleSourceRows(array $approvals): array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.id AS source_row_id,
                r.source_row_number,
                r.parsed_payload,
                r.validation_status,
                r.error_summary,
                b.id AS batch_id,
                b.batch_uid,
                b.original_filename
            FROM dtr_upload_staging_rows r
            INNER JOIN dtr_upload_batches b ON b.id = r.batch_id
            WHERE b.source_context = :source_context
              AND b.is_synthetic = 1
              AND r.is_synthetic = 1
              AND r.validation_status = 'valid'
            ORDER BY b.id ASC, r.source_row_number ASC
        ");
        $stmt->execute([':source_context' => self::SOURCE_CONTEXT]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload = json_decode((string)$row['parsed_payload'], true);
            if (!is_array($payload)) {
                continue;
            }

            $profileKey = strtoupper((string)($payload['source_adapter'] ?? ''));
            if (!in_array($profileKey, self::ALLOWED_PROFILES, true)) {
                continue;
            }

            $approval = $approvals[$profileKey] ?? [];
            if (!$this->profileAllowedForPreview($approval)) {
                continue;
            }

            if ((float)($payload['hours_worked'] ?? 0) <= 0) {
                continue;
            }

            $row['payload'] = $payload;
            $rows[] = $row;
        }

        return $rows;
    }

    private function profileAllowedForPreview(array $approval): bool
    {
        $scope = (string)($approval['approval_scope'] ?? '');
        return (string)($approval['approval_status'] ?? '') === 'approved'
            && in_array($scope, ['preview_only', 'staging_only'], true)
            && empty($approval['payroll_handoff_owner_approval'])
            && empty($approval['payroll_generation_approved']);
    }

    private function insertHeader(array $group, string $user): int
    {
        $previewUid = 'PBPREV-' . strtoupper((string)$group['profile_key']) . '-' . date('YmdHis') . '-' . substr(hash('sha256', json_encode([
            $group['profile_key'],
            $group['client_id'],
            $group['location_id'],
            $group['pay_period_start'],
            $group['pay_period_end'],
            microtime(true),
        ])), 0, 10);

        $sourceBatchIds = array_values($group['source_batch_ids']);
        $stmt = $this->db->prepare("
            INSERT INTO dtr_payroll_basis_preview_headers (
                preview_uid,
                profile_key,
                profile_name,
                client_id,
                client_name_snapshot,
                location_id,
                location_name_snapshot,
                pay_period_start,
                pay_period_end,
                source_batch_ids_json,
                source_batch_count,
                row_count,
                eligible_row_count,
                excluded_row_count,
                total_preview_worked_hours,
                basis_status,
                approval_status,
                payroll_handoff_status,
                review_notes,
                created_by
            ) VALUES (
                :preview_uid,
                :profile_key,
                :profile_name,
                :client_id,
                :client_name_snapshot,
                :location_id,
                :location_name_snapshot,
                :pay_period_start,
                :pay_period_end,
                :source_batch_ids_json,
                :source_batch_count,
                0,
                0,
                0,
                0,
                'draft',
                'preview_only',
                'blocked',
                'Local-only preview. Not payroll-approved and not connected to payroll generation.',
                :created_by
            )
        ");
        $stmt->execute([
            ':preview_uid' => $previewUid,
            ':profile_key' => $group['profile_key'],
            ':profile_name' => $group['profile_name'],
            ':client_id' => $this->nullableInt($group['client_id']),
            ':client_name_snapshot' => $group['client_name_snapshot'],
            ':location_id' => $this->nullableInt($group['location_id']),
            ':location_name_snapshot' => $group['location_name_snapshot'],
            ':pay_period_start' => $group['pay_period_start'],
            ':pay_period_end' => $group['pay_period_end'],
            ':source_batch_ids_json' => json_encode($sourceBatchIds),
            ':source_batch_count' => count($sourceBatchIds),
            ':created_by' => $user,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function insertRow(int $previewHeaderId, array $groupRow): void
    {
        $payload = $groupRow['payload'];
        $employee = $groupRow['employee'];
        $client = $groupRow['client'];
        $location = $groupRow['location'];
        $source = $groupRow['source'];
        $safetyFlags = $payload['preview_safety_flags'] ?? [];
        $issueReasons = is_array($safetyFlags) ? $safetyFlags : [];

        $stmt = $this->db->prepare("
            INSERT INTO dtr_payroll_basis_preview_rows (
                preview_header_id,
                profile_key,
                employee_id,
                payroll_employee_id_snapshot,
                employee_name_snapshot,
                employee_identifier_source,
                client_id,
                client_name_snapshot,
                location_id,
                location_name_snapshot,
                pay_period_start,
                pay_period_end,
                work_date,
                time_in,
                time_out,
                worked_hours_preview,
                worked_days_preview,
                eligibility_status,
                issue_exclusion_reason,
                source_batch_id,
                source_batch_uid,
                source_row_id,
                source_row_number,
                validation_status,
                conflict_status,
                approval_status,
                payroll_handoff_status
            ) VALUES (
                :preview_header_id,
                :profile_key,
                :employee_id,
                :payroll_employee_id_snapshot,
                :employee_name_snapshot,
                :employee_identifier_source,
                :client_id,
                :client_name_snapshot,
                :location_id,
                :location_name_snapshot,
                :pay_period_start,
                :pay_period_end,
                :work_date,
                :time_in,
                :time_out,
                :worked_hours_preview,
                :worked_days_preview,
                'eligible_preview',
                :issue_exclusion_reason,
                :source_batch_id,
                :source_batch_uid,
                :source_row_id,
                :source_row_number,
                'valid',
                'clear',
                'preview_only',
                'blocked'
            )
        ");
        $stmt->execute([
            ':preview_header_id' => $previewHeaderId,
            ':profile_key' => (string)$payload['source_adapter'],
            ':employee_id' => $this->nullableInt($employee['employee_id'] ?? 0),
            ':payroll_employee_id_snapshot' => (string)($employee['payroll_employee_id'] ?? ''),
            ':employee_name_snapshot' => $this->employeeName($employee, $payload),
            ':employee_identifier_source' => (string)($payload['employee_identifier_source'] ?? ''),
            ':client_id' => $this->nullableInt($employee['client_id'] ?? 0),
            ':client_name_snapshot' => (string)($client['client_name'] ?? ''),
            ':location_id' => $this->nullableInt($employee['client_location_id'] ?? 0),
            ':location_name_snapshot' => (string)($location['location_name'] ?? ''),
            ':pay_period_start' => $this->dateOrNull((string)($payload['period_start'] ?? '')),
            ':pay_period_end' => $this->dateOrNull((string)($payload['period_end'] ?? '')),
            ':work_date' => $this->dateOrNull((string)($payload['work_date'] ?? '')),
            ':time_in' => (string)($payload['time_in'] ?? ''),
            ':time_out' => (string)($payload['time_out'] ?? ''),
            ':worked_hours_preview' => round((float)($payload['hours_worked'] ?? 0), 4),
            ':worked_days_preview' => round((float)($payload['worked_days'] ?? 0), 4),
            ':issue_exclusion_reason' => json_encode($issueReasons),
            ':source_batch_id' => (int)$source['batch_id'],
            ':source_batch_uid' => (string)$source['batch_uid'],
            ':source_row_id' => (int)$source['source_row_id'],
            ':source_row_number' => (int)$source['source_row_number'],
        ]);
    }

    private function updateHeaderTotals(int $previewHeaderId): void
    {
        $stmt = $this->db->prepare("
            UPDATE dtr_payroll_basis_preview_headers h
            SET
                row_count = (
                    SELECT COUNT(*)
                    FROM dtr_payroll_basis_preview_rows r
                    WHERE r.preview_header_id = h.id
                ),
                eligible_row_count = (
                    SELECT COUNT(*)
                    FROM dtr_payroll_basis_preview_rows r
                    WHERE r.preview_header_id = h.id
                      AND r.eligibility_status = 'eligible_preview'
                ),
                excluded_row_count = (
                    SELECT COUNT(*)
                    FROM dtr_payroll_basis_preview_rows r
                    WHERE r.preview_header_id = h.id
                      AND r.eligibility_status <> 'eligible_preview'
                ),
                total_preview_worked_hours = (
                    SELECT COALESCE(SUM(r.worked_hours_preview), 0)
                    FROM dtr_payroll_basis_preview_rows r
                    WHERE r.preview_header_id = h.id
                )
            WHERE h.id = :id
        ");
        $stmt->execute([':id' => $previewHeaderId]);
    }

    private function clearPreviewTablesInternal(): void
    {
        $this->db->exec('DELETE FROM dtr_payroll_basis_preview_rows');
        $this->db->exec('DELETE FROM dtr_payroll_basis_preview_headers');
    }

    private function blockedProfileSummary(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.source_adapter')) AS profile_key,
                COUNT(*) AS staged_rows,
                SUM(CASE WHEN r.validation_status = 'valid' THEN 1 ELSE 0 END) AS valid_rows
            FROM dtr_upload_staging_rows r
            INNER JOIN dtr_upload_batches b ON b.id = r.batch_id
            WHERE b.source_context = :source_context
              AND b.is_synthetic = 1
              AND r.is_synthetic = 1
            GROUP BY JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.source_adapter'))
        ");
        $stmt->execute([':source_context' => self::SOURCE_CONTEXT]);

        $summary = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $profileKey = strtoupper((string)$row['profile_key']);
            if (in_array($profileKey, self::BLOCKED_PROFILES, true)) {
                $summary[$profileKey] = [
                    'staged_rows' => (int)$row['staged_rows'],
                    'valid_rows' => (int)$row['valid_rows'],
                    'status' => 'needs_owner_mapping',
                    'payroll_basis_preview' => 'blocked',
                ];
            }
        }

        foreach (self::BLOCKED_PROFILES as $profileKey) {
            if (!isset($summary[$profileKey])) {
                $summary[$profileKey] = [
                    'staged_rows' => 0,
                    'valid_rows' => 0,
                    'status' => 'needs_owner_mapping',
                    'payroll_basis_preview' => 'blocked',
                ];
            }
        }

        return $summary;
    }

    private function loadApprovals(): array
    {
        $path = __DIR__ . '/../config/adapter_approvals.json';
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || !isset($data['approvals']) || !is_array($data['approvals'])) {
            return [];
        }

        return $data['approvals'];
    }

    private function findEmployee(string $identifier): ?array
    {
        static $cache = [];
        if (isset($cache[$identifier])) {
            return $cache[$identifier];
        }

        $stmt = $this->db->prepare("
            SELECT
                employee_id,
                payroll_employee_id,
                old_employee_id,
                full_name,
                first_name,
                middle_name,
                last_name,
                client_id,
                client_location_id
            FROM employee_list
            WHERE CAST(employee_id AS CHAR) = :identifier
               OR payroll_employee_id = :identifier
               OR old_employee_id = :identifier
            LIMIT 1
        ");
        $stmt->execute([':identifier' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$identifier] = $row ?: null;

        return $cache[$identifier];
    }

    private function findClient(int $clientId): ?array
    {
        static $cache = [];
        if ($clientId <= 0) {
            return null;
        }
        if (isset($cache[$clientId])) {
            return $cache[$clientId];
        }

        $stmt = $this->db->prepare("
            SELECT client_id, client_name
            FROM taascor_client
            WHERE client_id = :client_id
            LIMIT 1
        ");
        $stmt->execute([':client_id' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$clientId] = $row ?: null;

        return $cache[$clientId];
    }

    private function findLocation(int $locationId): ?array
    {
        static $cache = [];
        if ($locationId <= 0) {
            return null;
        }
        if (isset($cache[$locationId])) {
            return $cache[$locationId];
        }

        $stmt = $this->db->prepare("
            SELECT location_id, location_name
            FROM taascor_client_location
            WHERE location_id = :location_id
            LIMIT 1
        ");
        $stmt->execute([':location_id' => $locationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$locationId] = $row ?: null;

        return $cache[$locationId];
    }

    private function employeeName(array $employee, array $payload): string
    {
        $fullName = trim((string)($employee['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $parts = array_filter([
            trim((string)($employee['last_name'] ?? '')),
            trim((string)($employee['first_name'] ?? '')),
            trim((string)($employee['middle_name'] ?? '')),
        ]);

        if (count($parts) > 0) {
            return implode(', ', $parts);
        }

        return (string)($payload['employee_name_source'] ?? '');
    }

    private function nullableInt($value): ?int
    {
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    private function dateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date ? $date->format('Y-m-d') : null;
    }

    private function safetyStatus(): array
    {
        return [
            'canonical_dtr_write' => 'blocked',
            'payroll_table_write' => 'blocked',
            'payroll_generation' => 'blocked',
            'payroll_formula_change' => 'blocked',
            'profile_scope' => 'COXON_DELTA_preview_only',
            'cya_status' => 'needs_owner_mapping',
        ];
    }
}

?>
