<?php

class RealSampleAdapter
{
    public $db = null;

    private const SOURCE_CONTEXT = 'real_sample_batch10_profile_adapter';
    private const GENERIC_ERROR = 'Unable to run the real sample adapter preview. Please contact your administrator.';

    public function profileSamples(): array
    {
        try {
            $profiles = [];
            foreach ($this->sampleConfigs() as $config) {
                $path = $this->samplePath($config['filename']);
                $profiles[] = [
                    'key' => $config['key'],
                    'profile_name' => $config['profile_name'],
                    'filename' => $config['filename'],
                    'exists' => is_file($path),
                    'format' => $config['file_type'] ?? strtolower(pathinfo((string)$config['filename'], PATHINFO_EXTENSION)),
                    'workbook_type' => $config['workbook_type'],
                    'sheet' => $config['sheet_selector'],
                    'header_rows' => $config['header_row'],
                    'data_start_row' => $config['data_start_row'],
                    'employee_identifier' => $config['columns']['employee_identifier'] ?? 0,
                    'employee_name' => $config['columns']['employee_name'] ?? 0,
                    'date_columns' => $this->dateColumnSummary($config),
                    'totals' => $config['totals_description'],
                    'remarks_status_fields' => $config['remarks_status_fields'],
                    'matching_rules' => $config['employee_matching']['enabled_rules'],
                    'status_codes' => array_keys($config['status_dictionary']['codes']),
                    'owner_decision_checklist' => $config['owner_decision_checklist'],
                    'adapter_hardening_notes' => $config['adapter_hardening_notes'] ?? [],
                    'client_source_inferred' => $config['client_source_inferred'] ?? '',
                    'coverage_classification' => $config['coverage_classification'] ?? 'owner mapping decision required',
                    'profile_reuse_notes' => $config['profile_reuse_notes'] ?? '',
                    'payroll_period_indicators' => $config['payroll_period_indicators'] ?? '',
                    'merged_header_structure' => $config['merged_header_structure'] ?? '',
                    'mode' => $config['mode'],
                ];
            }

            return ['success' => 1, 'data' => $profiles];
        } catch (\Throwable $th) {
            error_log('DTR real sample profile failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function runAdapters(string $user): array
    {
        try {
            $this->db->beginTransaction();
            $this->clearAdapterBatchesInternal();

            $results = [];
            foreach ($this->sampleConfigs() as $config) {
                if (($config['mode'] ?? '') === 'unsupported') {
                    $results[] = $this->unsupportedResult($config);
                    continue;
                }

                try {
                    $templateId = $this->ensureTemplate($config, $user);
                    $rows = $this->buildRows($config);
                    $batchId = $this->stageRows($config, $templateId, $rows, $user);
                } catch (\Throwable $profileError) {
                    error_log('DTR real sample adapter profile skipped: ' . ($config['key'] ?? 'unknown') . ' / ' . $profileError->getMessage());
                    $results[] = $this->profileErrorResult($config);
                    continue;
                }

                $stats = $this->rowStats($rows);
                $ownerChecklist = $config['owner_decision_checklist'];
                if ($stats['valid_rows'] === 0 && count($ownerChecklist) === 0) {
                    $ownerChecklist[] = 'Confirm employee identifier and worked-days mapping for this workbook profile.';
                }
                $profileSafetyWarnings = [];
                $maxProfileHours = (float)($config['preview_safety']['max_profile_preview_hours'] ?? 0);
                if ($maxProfileHours > 0 && $stats['preview_hours'] > $maxProfileHours) {
                    $profileSafetyWarnings[] = 'aggregate_preview_hours_exceeds_review_threshold';
                }

                $results[] = [
                    'key' => $config['key'],
                    'profile_name' => $config['profile_name'],
                    'filename' => $config['filename'],
                    'sheet' => $config['sheet_selector'],
                    'template_id' => $templateId,
                    'batch_id' => $batchId,
                    'rows' => count($rows),
                    'valid_rows' => $stats['valid_rows'],
                    'error_rows' => $stats['error_rows'],
                    'matched_rows' => $stats['matched_rows'],
                    'unmatched_rows' => $stats['unmatched_rows'],
                    'total_mismatch_rows' => $stats['total_mismatch_rows'],
                    'suspicious_hour_rows' => $stats['suspicious_hour_rows'],
                    'preview_hours' => round($stats['preview_hours'], 2),
                    'mode' => $config['mode'],
                    'workbook_type' => $config['workbook_type'],
                    'coverage_classification' => $config['coverage_classification'] ?? 'owner mapping decision required',
                    'status_summary' => $stats['status_summary'],
                    'matching_summary' => $stats['matching_summary'],
                    'error_summary' => $stats['error_summary'],
                    'suspicious_hour_summary' => $stats['suspicious_hour_summary'],
                    'profile_safety_warnings' => $profileSafetyWarnings,
                    'owner_mapping_required' => $ownerChecklist,
                    'adapter_hardening_notes' => $config['adapter_hardening_notes'] ?? [],
                    'adapter_diagnostics' => $this->adapterDiagnostics($config, $stats, $ownerChecklist, $profileSafetyWarnings),
                ];
            }

            $this->db->commit();

            return [
                'success' => 1,
                'source_context' => self::SOURCE_CONTEXT,
                'results' => $results,
            ];
        } catch (\Throwable $th) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR real sample adapter failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function clearAdapterBatches(): array
    {
        try {
            $this->db->beginTransaction();
            $deleted = $this->clearAdapterBatchesInternal();
            $this->db->commit();

            return ['success' => 1, 'deleted_batches' => $deleted];
        } catch (\Throwable $th) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR real sample adapter clear failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function adapterSummary(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    b.id,
                    b.original_filename,
                    t.template_name,
                    b.row_count,
                    b.error_count,
                    b.validation_status,
                    b.processing_status,
                    COUNT(r.id) AS staged_rows,
                    SUM(CASE WHEN r.validation_status = 'valid' THEN 1 ELSE 0 END) AS valid_rows
                FROM dtr_upload_batches b
                LEFT JOIN dtr_format_templates t ON t.id = b.template_id
                LEFT JOIN dtr_upload_staging_rows r ON r.batch_id = b.id
                WHERE b.source_context = :source_context
                GROUP BY
                    b.id, b.original_filename, t.template_name, b.row_count,
                    b.error_count, b.validation_status, b.processing_status
                ORDER BY b.id ASC
            ");
            $stmt->execute([':source_context' => self::SOURCE_CONTEXT]);

            return ['success' => 1, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (\Throwable $th) {
            error_log('DTR real sample adapter summary failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function approvalWorkflow(): array
    {
        try {
            $approvals = $this->loadApprovals();
            $profiles = [];

            foreach ($this->sampleConfigs() as $config) {
                $key = (string)$config['key'];
                $approval = $approvals[$key] ?? $this->defaultApproval($key);
                $preview = $this->latestPreviewStatus($config);
                $gate = $this->approvalGate($approval, $preview);

                $profiles[] = [
                    'key' => $key,
                    'profile_name' => $config['profile_name'],
                    'workbook_type' => $config['workbook_type'],
                    'sample_file' => $config['filename'],
                    'employee_matching_rule' => implode(', ', $config['employee_matching']['enabled_rules']),
                    'client_site_binding_rule' => $this->clientSiteBindingRule($config),
                    'pay_period_extraction_rule' => $config['payroll_period_indicators'] ?? '',
                    'status_code_dictionary' => $this->statusDictionarySummary($config),
                    'worked_hours_interpretation' => $this->workedHoursInterpretation($config),
                    'total_validation_rule' => $this->totalValidationRule($config),
                    'suspicious_hour_threshold' => $this->suspiciousHourThreshold($config),
                    'approval_status' => $approval['approval_status'],
                    'decision_status' => $approval['decision_status'],
                    'approval_scope' => $approval['approval_scope'],
                    'risk_accepted' => !empty($approval['risk_accepted']),
                    'payroll_handoff_blocked' => !empty($approval['payroll_handoff_blocked']),
                    'payroll_handoff_owner_approval' => !empty($approval['payroll_handoff_owner_approval']),
                    'payroll_generation_approved' => !empty($approval['payroll_generation_approved']),
                    'mapping_gaps' => $approval['mapping_gaps'],
                    'adapter_hardening_notes' => $config['adapter_hardening_notes'] ?? [],
                    'adapter_diagnostics' => $this->approvalDiagnostics($config, $approval, $preview, $gate),
                    'approver' => $approval['approver'],
                    'approval_date' => $approval['approval_date'],
                    'approval_notes' => $approval['approval_notes'],
                    'checklist' => $this->mappingChecklist($config, $approval),
                    'preview_flags' => $preview,
                    'approval_gate' => $gate,
                    'payroll_handoff_ready' => $gate['payroll_handoff_ready'],
                ];
            }

            return ['success' => 1, 'data' => $profiles];
        } catch (\Throwable $th) {
            error_log('DTR adapter approval workflow failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function saveApprovalReview(array $post, string $user): array
    {
        try {
            $profileKey = strtoupper(trim((string)($post['profile_key'] ?? '')));
            $config = $this->profileByKey($profileKey);
            if (!$config) {
                return ['success' => 0, 'error' => 'Select a valid adapter profile.'];
            }

            $allowedStatuses = ['draft', 'under_review', 'approved', 'rejected', 'needs_owner_mapping'];
            $status = trim((string)($post['approval_status'] ?? 'under_review'));
            if (!in_array($status, $allowedStatuses, true)) {
                return ['success' => 0, 'error' => 'Select a valid approval status.'];
            }

            $allowedScopes = ['preview_only', 'staging_only', 'blocked'];
            $approvalScope = trim((string)($post['approval_scope'] ?? 'blocked'));
            if (!in_array($approvalScope, $allowedScopes, true)) {
                return ['success' => 0, 'error' => 'Select a valid approval scope.'];
            }

            $approval = [
                'profile_key' => $profileKey,
                'approval_status' => $status,
                'decision_status' => $status,
                'approval_scope' => $approvalScope,
                'risk_accepted' => $this->boolPost($post, 'risk_accepted'),
                'employee_matching_approved' => $this->boolPost($post, 'employee_matching_approved'),
                'status_dictionary_approved' => $this->boolPost($post, 'status_dictionary_approved'),
                'client_site_binding_approved' => $this->boolPost($post, 'client_site_binding_approved'),
                'pay_period_extraction_approved' => $this->boolPost($post, 'pay_period_extraction_approved'),
                'suspicious_preview_reviewed' => $this->boolPost($post, 'suspicious_preview_reviewed'),
                'owner_approval_captured' => $this->boolPost($post, 'owner_approval_captured'),
                'payroll_handoff_owner_approval' => false,
                'payroll_handoff_blocked' => true,
                'payroll_generation_approved' => false,
                'mapping_gaps' => $this->mappingGapsFromApproval($profileKey, $post),
                'approval_notes' => trim((string)($post['approval_notes'] ?? '')),
                'approver' => $status === 'approved' ? $user : trim((string)($post['approver'] ?? '')),
                'approval_date' => $status === 'approved' ? date('Y-m-d H:i:s') : '',
                'updated_by' => $user,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $preview = $this->latestPreviewStatus($config);
            $gate = $this->approvalGate($approval, $preview);
            if ($status === 'approved' && !$gate['profile_review_complete']) {
                return [
                    'success' => 0,
                    'error' => 'Profile approval requires all mapping checks, suspicious preview review, and owner approval capture.',
                    'approval_gate' => $gate,
                ];
            }

            $approvals = $this->loadApprovals();
            $approvals[$profileKey] = $approval;
            $this->saveApprovals($approvals);

            return [
                'success' => 1,
                'profile_key' => $profileKey,
                'approval_status' => $approval['approval_status'],
                'approval_gate' => $this->approvalGate($approval, $preview),
            ];
        } catch (\Throwable $th) {
            error_log('DTR adapter approval save failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    private function clearAdapterBatchesInternal(): int
    {
        $ids = $this->db->prepare("
            SELECT id
            FROM dtr_upload_batches
            WHERE source_context = :source_context
        ");
        $ids->execute([':source_context' => self::SOURCE_CONTEXT]);
        $batchIds = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
        if (count($batchIds) === 0) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $rows = $this->db->prepare("DELETE FROM dtr_upload_staging_rows WHERE batch_id IN ($placeholders)");
        $rows->execute($batchIds);

        $batches = $this->db->prepare("DELETE FROM dtr_upload_batches WHERE id IN ($placeholders)");
        $batches->execute($batchIds);

        return $batches->rowCount();
    }

    private function ensureTemplate(array $config, string $user): int
    {
        $existing = $this->db->prepare("
            SELECT id
            FROM dtr_format_templates
            WHERE template_name = :template_name
            LIMIT 1
        ");
        $existing->execute([':template_name' => $config['template_name']]);
        $templateId = (int)$existing->fetchColumn();

        if ($templateId === 0) {
            $insert = $this->db->prepare("
                INSERT INTO dtr_format_templates (
                    template_name,
                    client_id,
                    location_id,
                    source_type,
                    file_type,
                    expected_headers,
                    date_format,
                    time_format,
                    employee_identifier_field,
                    is_active,
                    created_by,
                    updated_by
                ) VALUES (
                    :template_name,
                    :client_id,
                    :location_id,
                    :source_type,
                    :file_type,
                    :expected_headers,
                    'Y-m-d',
                    'summary-profile',
                    :employee_identifier_field,
                    1,
                    :created_by,
                    :updated_by
                )
            ");
            $insert->execute([
                ':template_name' => $config['template_name'],
                ':client_id' => $config['client_id'] ?: null,
                ':location_id' => $config['location_id'] ?: null,
                ':source_type' => 'owner-configurable-' . $config['workbook_type'],
                ':file_type' => $config['file_type'] ?? strtolower(pathinfo((string)$config['filename'], PATHINFO_EXTENSION)) ?: 'xlsx',
                ':expected_headers' => json_encode($config['headers']),
                ':employee_identifier_field' => $config['employee_identifier_label'],
                ':created_by' => $user,
                ':updated_by' => $user,
            ]);
            $templateId = (int)$this->db->lastInsertId();
        }

        $count = $this->db->prepare("
            SELECT COUNT(*)
            FROM dtr_format_template_fields
            WHERE template_id = :template_id
        ");
        $count->execute([':template_id' => $templateId]);
        if ((int)$count->fetchColumn() === 0) {
            $fieldInsert = $this->db->prepare("
                INSERT INTO dtr_format_template_fields (
                    template_id,
                    source_header,
                    canonical_field,
                    data_type,
                    is_required,
                    sort_order
                ) VALUES (
                    :template_id,
                    :source_header,
                    :canonical_field,
                    :data_type,
                    :is_required,
                    :sort_order
                )
            ");

            foreach ($config['fields'] as $index => $field) {
                $fieldInsert->execute([
                    ':template_id' => $templateId,
                    ':source_header' => $field['source_header'],
                    ':canonical_field' => $field['canonical_field'],
                    ':data_type' => $field['data_type'],
                    ':is_required' => $field['is_required'],
                    ':sort_order' => $index + 1,
                ]);
            }
        }

        return $templateId;
    }

    private function buildRows(array $config): array
    {
        $path = $this->samplePathForConfig($config);
        if (!is_file($path)) {
            throw new RuntimeException('Sample file not found: ' . $config['filename']);
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('Only .xlsx sample parsing is available in the local adapter preview.');
        }

        $matrix = $this->readSheetMatrix($path, $config['sheet_selector']);
        if ($config['mode'] === 'wide_daily') {
            return $this->buildWideDailyRows($config, $matrix);
        }
        if ($config['mode'] === 'raw_punch') {
            return $this->buildRawPunchRows($config, $matrix);
        }

        return $this->buildSummaryRows($config, $matrix);
    }

    private function buildWideDailyRows(array $config, array $matrix): array
    {
        $rows = [];
        $periodStart = new DateTimeImmutable($config['period_start']);
        $columns = $config['columns'];
        $range = $config['date_day_column_range'];

        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber < $config['data_start_row'] || $this->isIgnoredOrFooterRow($config, $rowNumber, $cells)) {
                continue;
            }

            $employeeIdentifier = $this->cell($cells, (int)$columns['employee_identifier']);
            $employeeName = $this->cell($cells, (int)$columns['employee_name']);
            if ($employeeIdentifier === '' && $employeeName === '') {
                continue;
            }

            $match = $this->findEmployeeByRules($employeeIdentifier, $employeeName, $config);
            for ($offset = 0; $offset < (int)$range['count']; $offset++) {
                $columnIndex = (int)$range['start_col'] + $offset;
                $cellValue = $this->cell($cells, $columnIndex);
                $interpretation = $this->interpretStatusValue($cellValue, $config);
                if ($interpretation['skip']) {
                    continue;
                }

                $workDate = $periodStart->modify('+' . $offset . ' days')->format('Y-m-d');
                $errors = $this->rowErrors($match, $interpretation);
                $totalCheck = $this->wideDailyTotalCheck($config, $cells);
                if ($totalCheck['error'] !== '') {
                    $errors[] = $totalCheck['error'];
                }
                $safetyFlags = $this->previewSafetyFlags($interpretation['hours'], $interpretation['worked_days'], $config, $interpretation);

                $rows[] = $this->adapterRow(
                    $rowNumber,
                    $match,
                    $employeeIdentifier,
                    $employeeName,
                    $workDate,
                    $interpretation['hours'],
                    $interpretation['worked_days'],
                    $config,
                    [
                        'source_row' => $rowNumber,
                        'source_column' => $columnIndex,
                        'source_value' => $cellValue,
                        'area' => $this->cell($cells, (int)($columns['area'] ?? 0)),
                        'status_category' => $interpretation['category'],
                        'total_check' => $totalCheck,
                        'preview_safety_flags' => $safetyFlags,
                    ],
                    $errors,
                    $interpretation
                );
            }
        }

        return array_slice($rows, 0, 1000);
    }

    private function buildSummaryRows(array $config, array $matrix): array
    {
        $rows = [];
        $columns = $config['columns'];
        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber < $config['data_start_row'] || $this->isIgnoredOrFooterRow($config, $rowNumber, $cells)) {
                continue;
            }

            $employeeIdentifier = $this->cell($cells, (int)$columns['employee_identifier']);
            $employeeName = $this->cell($cells, (int)$columns['employee_name']);
            if ($employeeIdentifier === '' && $employeeName === '') {
                continue;
            }

            $match = $this->findEmployeeByRules($employeeIdentifier, $employeeName, $config);
            $workedDays = $this->numericCell($cells, (int)($columns['worked_days'] ?? 0));
            $hours = $workedDays > 0 ? round($workedDays * (float)$config['summary_conversion']['hours_per_worked_day'], 4) : 0.0;
            $interpretation = [
                'category' => $workedDays > 0 ? 'worked_days_summary' : 'invalid',
                'hours' => $hours,
                'worked_days' => $workedDays,
                'blocking' => $workedDays <= 0,
                'skip' => false,
                'error' => $workedDays <= 0 ? 'missing_or_invalid_worked_days' : '',
            ];
            $errors = $this->rowErrors($match, $interpretation);
            $totalCheck = $this->summaryTotalCheck($config, $cells, $workedDays, $hours);
            if ($totalCheck['error'] !== '') {
                $errors[] = $totalCheck['error'];
            }
            $safetyFlags = $this->previewSafetyFlags($hours, $workedDays, $config, $interpretation);

            $rows[] = $this->adapterRow(
                $rowNumber,
                $match,
                $employeeIdentifier,
                $employeeName,
                $config['period_start'],
                $hours,
                $workedDays,
                $config,
                [
                    'source_row' => $rowNumber,
                    'worked_days_source' => $this->cell($cells, (int)($columns['worked_days'] ?? 0)),
                    'designation_or_area' => $this->cell($cells, (int)($columns['area'] ?? 0)),
                    'period_end' => $config['period_end'],
                    'hours_rule' => 'worked_days_x_' . $config['summary_conversion']['hours_per_worked_day'] . '_preview_only',
                    'total_check' => $totalCheck,
                    'preview_safety_flags' => $safetyFlags,
                ],
                $errors,
                $interpretation
            );
        }

        return array_slice($rows, 0, 1000);
    }

    private function buildRawPunchRows(array $config, array $matrix): array
    {
        $rows = [];
        $columns = $config['columns'];
        $seen = [];

        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber < $config['data_start_row'] || $this->isIgnoredOrFooterRow($config, $rowNumber, $cells)) {
                continue;
            }

            $employeeIdentifier = $this->cell($cells, (int)($columns['employee_identifier'] ?? 0));
            $employeeName = $this->cell($cells, (int)($columns['employee_name'] ?? 0));
            $dateSource = $this->cell($cells, (int)($columns['work_date'] ?? 0));
            $timeInSource = $this->cell($cells, (int)($columns['time_in'] ?? 0));
            $timeOutSource = $this->cell($cells, (int)($columns['time_out'] ?? 0));

            if ($employeeIdentifier === '' && $employeeName === '' && $dateSource === '' && $timeInSource === '' && $timeOutSource === '') {
                continue;
            }

            $workDate = $this->normalizeWorkDate($dateSource);
            $timeIn = $this->normalizeTimeValue($timeInSource);
            $timeOut = $this->normalizeTimeValue($timeOutSource);
            $worked = $this->workedHoursFromTimes($timeIn, $timeOut);
            $match = $this->findEmployeeByRules($employeeIdentifier, $employeeName, $config);

            $interpretation = [
                'category' => 'raw_punch',
                'hours' => $worked['hours'],
                'worked_days' => $worked['hours'] > 0 ? round($worked['hours'] / 8, 4) : 0.0,
                'blocking' => false,
                'skip' => false,
                'error' => '',
            ];

            $errors = $this->rowErrors($match, $interpretation);
            if ($workDate === '') {
                $errors[] = 'invalid_date';
            }
            if ($timeIn === '' || $timeOut === '') {
                $errors[] = 'missing_time_in_time_out';
            }
            if (($timeInSource !== '' && $timeIn === '') || ($timeOutSource !== '' && $timeOut === '')) {
                $errors[] = 'invalid_time';
            }
            if ($worked['hours'] <= 0 && $timeIn !== '' && $timeOut !== '') {
                $errors[] = 'invalid_time_range';
            }

            $duplicateKey = implode('__', [
                $config['key'],
                $this->employeeIdKey($employeeIdentifier !== '' ? $employeeIdentifier : $employeeName),
                $workDate,
                $timeIn,
                $timeOut,
            ]);
            if (isset($seen[$duplicateKey])) {
                $errors[] = 'duplicate_punch_within_file';
            }
            $seen[$duplicateKey] = true;

            $safetyFlags = $this->previewSafetyFlags($worked['hours'], $interpretation['worked_days'], $config, $interpretation);
            if ($worked['overnight']) {
                $safetyFlags[] = 'overnight_shift_preview';
            }

            $rows[] = $this->adapterRow(
                $rowNumber,
                $match,
                $employeeIdentifier,
                $employeeName,
                $workDate !== '' ? $workDate : $config['period_start'],
                $worked['hours'],
                $interpretation['worked_days'],
                $config,
                [
                    'source_row' => $rowNumber,
                    'source_column' => 'raw_punch',
                    'raw_date_source' => $dateSource,
                    'time_in_source' => $timeInSource,
                    'time_out_source' => $timeOutSource,
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'overnight_shift_preview' => $worked['overnight'],
                    'preview_safety_flags' => array_values(array_unique($safetyFlags)),
                ],
                $errors,
                $interpretation
            );
        }

        return array_slice($rows, 0, 1000);
    }

    private function adapterRow(
        int $rowNumber,
        array $match,
        string $sourceEmployeeIdentifier,
        string $employeeName,
        string $workDate,
        float $hours,
        float $workedDays,
        array $config,
        array $rawExtra,
        array $errors,
        array $interpretation
    ): array {
        $employee = $match['employee'];
        $employeeIdentifier = $employee ? (string)$employee['employee_id'] : $sourceEmployeeIdentifier;
        if ($employeeIdentifier === '') {
            $employeeIdentifier = $employeeName;
        }

        $raw = array_merge([
            'adapter' => $config['key'],
            'profile_name' => $config['profile_name'],
            'sheet' => $config['sheet_selector'],
            'employee_identifier_source' => $sourceEmployeeIdentifier,
            'employee_name_source' => $employeeName,
            'format_mode' => $config['mode'],
            'workbook_type' => $config['workbook_type'],
            'matching_rule' => $match['rule'],
        ], $rawExtra);

        $parsed = [
            'employee_identifier' => $employeeIdentifier,
            'employee_identifier_source' => $sourceEmployeeIdentifier,
            'employee_name_source' => $employeeName,
            'work_date' => $workDate,
            'period_start' => $config['period_start'],
            'period_end' => $config['period_end'],
            'time_in' => (string)($rawExtra['time_in'] ?? ''),
            'time_out' => (string)($rawExtra['time_out'] ?? ''),
            'hours_worked' => $hours,
            'worked_days' => $workedDays,
            'summary_preview' => true,
            'source_format' => $config['mode'],
            'source_adapter' => $config['key'],
            'source_profile' => $config['profile_name'],
            'status_category' => $interpretation['category'],
            'employee_match_rule' => $match['rule'],
            'preview_safety_flags' => $rawExtra['preview_safety_flags'] ?? [],
            'duplicate_key' => implode('__', [$config['key'], $employeeIdentifier, $workDate, $rowNumber, $rawExtra['source_column'] ?? 'summary']),
            'real_sample_local' => true,
        ];

        return [
            'row_number' => $rowNumber,
            'raw_payload' => $raw,
            'parsed_payload' => $parsed,
            'validation_status' => count($errors) === 0 ? 'valid' : 'error',
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function stageRows(array $config, int $templateId, array $rows, string $user): int
    {
        $path = $this->samplePathForConfig($config);
        $errorCount = 0;
        foreach ($rows as $row) {
            if ($row['validation_status'] !== 'valid') {
                $errorCount++;
            }
        }

        $batchUid = 'REAL10-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $batch = $this->db->prepare("
            INSERT INTO dtr_upload_batches (
                batch_uid,
                template_id,
                original_filename,
                uploaded_by,
                checksum,
                row_count,
                validation_status,
                error_count,
                processing_status,
                is_synthetic,
                source_context
            ) VALUES (
                :batch_uid,
                :template_id,
                :original_filename,
                :uploaded_by,
                :checksum,
                :row_count,
                :validation_status,
                :error_count,
                'configurable_adapter_preview',
                1,
                :source_context
            )
        ");
        $batch->execute([
            ':batch_uid' => $batchUid,
            ':template_id' => $templateId,
            ':original_filename' => basename($config['filename']),
            ':uploaded_by' => $user,
            ':checksum' => hash_file('sha256', $path),
            ':row_count' => count($rows),
            ':validation_status' => $errorCount > 0 ? 'warning' : 'passed',
            ':error_count' => $errorCount,
            ':source_context' => self::SOURCE_CONTEXT,
        ]);
        $batchId = (int)$this->db->lastInsertId();

        $insert = $this->db->prepare("
            INSERT INTO dtr_upload_staging_rows (
                batch_id,
                source_row_number,
                raw_payload,
                parsed_payload,
                validation_status,
                error_summary,
                is_synthetic
            ) VALUES (
                :batch_id,
                :source_row_number,
                :raw_payload,
                :parsed_payload,
                :validation_status,
                :error_summary,
                1
            )
        ");

        foreach ($rows as $index => $row) {
            $insert->execute([
                ':batch_id' => $batchId,
                ':source_row_number' => ($row['row_number'] * 1000) + $index,
                ':raw_payload' => json_encode($row['raw_payload']),
                ':parsed_payload' => json_encode($row['parsed_payload']),
                ':validation_status' => $row['validation_status'],
                ':error_summary' => json_encode($row['errors']),
            ]);
        }

        return $batchId;
    }

    private function rowStats(array $rows): array
    {
        $stats = [
            'valid_rows' => 0,
            'error_rows' => 0,
            'matched_rows' => 0,
            'unmatched_rows' => 0,
            'total_mismatch_rows' => 0,
            'suspicious_hour_rows' => 0,
            'preview_hours' => 0.0,
            'status_summary' => [],
            'matching_summary' => [],
            'error_summary' => [],
            'suspicious_hour_summary' => [],
        ];

        foreach ($rows as $row) {
            $parsed = $row['parsed_payload'];
            $raw = $row['raw_payload'];
            $statusCategory = (string)($parsed['status_category'] ?? '');
            $matchRule = (string)($parsed['employee_match_rule'] ?? 'unmatched');

            $stats['status_summary'][$statusCategory] = ($stats['status_summary'][$statusCategory] ?? 0) + 1;
            $stats['matching_summary'][$matchRule] = ($stats['matching_summary'][$matchRule] ?? 0) + 1;

            if ($matchRule === 'unmatched') {
                $stats['unmatched_rows']++;
            } else {
                $stats['matched_rows']++;
            }

            if (in_array('total_mismatch', $row['errors'], true)) {
                $stats['total_mismatch_rows']++;
            }

            foreach ($row['errors'] as $error) {
                $stats['error_summary'][$error] = ($stats['error_summary'][$error] ?? 0) + 1;
            }
            foreach (($parsed['preview_safety_flags'] ?? []) as $flag) {
                $stats['suspicious_hour_summary'][$flag] = ($stats['suspicious_hour_summary'][$flag] ?? 0) + 1;
            }
            if (count($parsed['preview_safety_flags'] ?? []) > 0) {
                $stats['suspicious_hour_rows']++;
            }

            if ($row['validation_status'] === 'valid') {
                $stats['valid_rows']++;
                $stats['preview_hours'] += (float)($parsed['hours_worked'] ?? 0);
            } else {
                $stats['error_rows']++;
            }
        }

        ksort($stats['status_summary']);
        ksort($stats['matching_summary']);
        ksort($stats['error_summary']);
        ksort($stats['suspicious_hour_summary']);

        return $stats;
    }

    private function previewSafetyFlags(float $hours, float $workedDays, array $config, array $interpretation): array
    {
        $safety = $config['preview_safety'] ?? [];
        $flags = [];

        $maxDaily = (float)($safety['max_daily_hours_per_row'] ?? 0);
        if ($config['mode'] === 'wide_daily' && $maxDaily > 0 && $hours > $maxDaily) {
            $flags[] = 'daily_preview_hours_exceeds_review_threshold';
        }
        if ($config['mode'] === 'wide_daily' && ($interpretation['category'] ?? '') === 'numeric_hours' && $hours > 8 && $hours <= $maxDaily) {
            $flags[] = 'daily_numeric_hours_exceed_standard_shift_review';
        }
        if ($config['mode'] === 'wide_daily' && ($interpretation['category'] ?? '') === 'numeric_hours' && $hours >= 24) {
            $flags[] = 'possible_total_interpreted_as_daily_row';
        }
        if ($hours == 0.0 && empty($interpretation['blocking']) && empty($interpretation['skip']) && ($interpretation['category'] ?? '') !== 'blank') {
            $flags[] = 'zero_hour_valid_preview_row';
        }

        $maxSummary = (float)($safety['max_summary_hours_per_row'] ?? 0);
        if ($config['mode'] === 'summary_period' && $maxSummary > 0 && $hours > $maxSummary) {
            $flags[] = 'summary_preview_hours_exceeds_review_threshold';
        }
        if ($config['mode'] === 'summary_period' && ($interpretation['category'] ?? '') === 'worked_days_summary' && $hours > 0) {
            $flags[] = 'numeric_summary_value_may_represent_days_not_hours';
        }

        $maxWorkedDays = (float)($safety['max_worked_days_per_period'] ?? 0);
        if ($maxWorkedDays > 0 && $workedDays > $maxWorkedDays) {
            $flags[] = 'worked_days_exceeds_period_review_threshold';
        }

        if (($interpretation['category'] ?? '') === 'worked_days_summary') {
            $flags[] = 'summary_hours_are_preview_only_not_payroll_truth';
        }

        return array_values(array_unique($flags));
    }

    private function adapterDiagnostics(array $config, array $stats, array $ownerChecklist, array $profileSafetyWarnings): array
    {
        $diagnostics = [];
        $key = (string)$config['key'];

        if ($key === 'COXON') {
            $diagnostics[] = 'Footer and summary rows are filtered before staging; numeric daily cells remain preview-only.';
        }
        if ($key === 'DELTA') {
            $diagnostics[] = 'Worked-days and minutes are summary-basis diagnostics only, not payroll-approved timekeeping.';
        }
        if ($key === 'CYA') {
            $diagnostics[] = 'CYA requires owner mapping for identifier/name/alias and worked-days interpretation.';
        }
        if (($stats['unmatched_rows'] ?? 0) > 0) {
            $diagnostics[] = 'Unmatched employee references require owner review or alias/manual mapping.';
        }
        if (($stats['suspicious_hour_rows'] ?? 0) > 0 || count($profileSafetyWarnings) > 0) {
            $diagnostics[] = 'Suspicious preview-hour flags must be reviewed before any future staging decision.';
        }
        if (count($ownerChecklist) > 0) {
            $diagnostics[] = 'Owner decision checklist remains part of the approval packet.';
        }

        return array_values(array_unique($diagnostics));
    }

    private function unsupportedResult(array $config): array
    {
        return [
            'key' => $config['key'],
            'profile_name' => $config['profile_name'],
            'filename' => $config['filename'],
            'sheet' => $config['sheet_selector'] ?? '',
            'template_id' => 0,
            'batch_id' => 0,
            'rows' => 0,
            'valid_rows' => 0,
            'error_rows' => 0,
            'matched_rows' => 0,
            'unmatched_rows' => 0,
            'total_mismatch_rows' => 0,
            'suspicious_hour_rows' => 0,
            'preview_hours' => 0,
            'mode' => 'unsupported',
            'workbook_type' => $config['workbook_type'] ?? 'unsupported',
            'coverage_classification' => $config['coverage_classification'] ?? 'unsupported structure',
            'status_summary' => [],
            'matching_summary' => [],
            'error_summary' => ['unsupported_profile' => 1],
            'suspicious_hour_summary' => [],
            'profile_safety_warnings' => [],
            'owner_mapping_required' => $config['owner_decision_checklist'] ?? ['Owner mapping or readable workbook export required.'],
            'adapter_hardening_notes' => $config['adapter_hardening_notes'] ?? [],
            'adapter_diagnostics' => ['Profile is classified as unsupported for local preview parsing.'],
        ];
    }

    private function profileErrorResult(array $config): array
    {
        return [
            'key' => $config['key'] ?? 'UNKNOWN',
            'profile_name' => $config['profile_name'] ?? 'Unknown profile',
            'filename' => $config['filename'] ?? '',
            'sheet' => $config['sheet_selector'] ?? '',
            'template_id' => 0,
            'batch_id' => 0,
            'rows' => 0,
            'valid_rows' => 0,
            'error_rows' => 0,
            'matched_rows' => 0,
            'unmatched_rows' => 0,
            'total_mismatch_rows' => 0,
            'suspicious_hour_rows' => 0,
            'preview_hours' => 0,
            'mode' => $config['mode'] ?? 'error',
            'workbook_type' => $config['workbook_type'] ?? 'error',
            'coverage_classification' => 'owner mapping decision required',
            'status_summary' => [],
            'matching_summary' => [],
            'error_summary' => ['profile_parse_error' => 1],
            'suspicious_hour_summary' => [],
            'profile_safety_warnings' => [],
            'owner_mapping_required' => ['Review generated profile mapping before parser retry.'],
            'adapter_hardening_notes' => $config['adapter_hardening_notes'] ?? [],
            'adapter_diagnostics' => ['Profile was skipped after a safe parser error.'],
        ];
    }

    private function approvalDiagnostics(array $config, array $approval, array $preview, array $gate): array
    {
        $diagnostics = [];
        $scope = (string)($approval['approval_scope'] ?? 'blocked');

        if ($scope === 'preview_only') {
            $diagnostics[] = 'Approved only for local preview review; no canonical DTR or payroll write is permitted.';
        } elseif ($scope === 'staging_only') {
            $diagnostics[] = 'Approved only for local DTR Format Engine staging; payroll handoff remains blocked.';
        } else {
            $diagnostics[] = 'Profile remains blocked beyond diagnostic review.';
        }
        if (!empty($approval['payroll_handoff_blocked']) || empty($approval['payroll_handoff_owner_approval'])) {
            $diagnostics[] = 'Payroll handoff is blocked because explicit payroll handoff owner approval is missing.';
        }
        if (!empty($preview['flags'])) {
            $diagnostics[] = 'Preview flags require reviewer attention: ' . implode(', ', $preview['flags']);
        }
        if ((string)$config['key'] === 'CYA' && count((array)($approval['mapping_gaps'] ?? [])) > 0) {
            $diagnostics[] = 'CYA mapping gaps remain unresolved: ' . implode('; ', $approval['mapping_gaps']);
        }
        if (!empty($gate['profile_blocking_items'])) {
            $diagnostics[] = 'Profile review blockers: ' . implode(', ', $gate['profile_blocking_items']);
        }

        return array_values(array_unique($diagnostics));
    }

    private function rowErrors(array $match, array $interpretation): array
    {
        $errors = [];
        if (!$match['employee']) {
            $errors[] = 'unknown_employee';
        }
        if ($interpretation['error'] !== '') {
            $errors[] = $interpretation['error'];
        }
        if ($interpretation['blocking']) {
            $errors[] = 'status_' . $interpretation['category'];
        }

        return array_values(array_unique($errors));
    }

    private function interpretStatusValue(string $value, array $config): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['category' => 'blank', 'hours' => 0.0, 'worked_days' => 0.0, 'blocking' => false, 'skip' => true, 'error' => ''];
        }

        $dictionary = $config['status_dictionary'];
        if (is_numeric(str_replace(',', '', $value))) {
            $hours = (float)str_replace(',', '', $value);
            if ($hours > 0) {
                return ['category' => 'numeric_hours', 'hours' => $hours, 'worked_days' => round($hours / 8, 4), 'blocking' => false, 'skip' => false, 'error' => ''];
            }

            $zeroCategory = $dictionary['numeric_zero_category'] ?? 'absent';
            return ['category' => $zeroCategory, 'hours' => 0.0, 'worked_days' => 0.0, 'blocking' => true, 'skip' => false, 'error' => 'non_work_status_' . $zeroCategory];
        }

        $key = strtoupper(trim($value));
        $mapped = $dictionary['codes'][$key] ?? null;
        if (!$mapped) {
            return ['category' => 'invalid_unknown', 'hours' => 0.0, 'worked_days' => 0.0, 'blocking' => true, 'skip' => false, 'error' => 'invalid_status_code'];
        }

        $category = (string)$mapped['category'];
        if (!empty($mapped['skip'])) {
            return ['category' => $category, 'hours' => 0.0, 'worked_days' => 0.0, 'blocking' => false, 'skip' => true, 'error' => ''];
        }

        $hours = isset($mapped['default_hours']) ? (float)$mapped['default_hours'] : 0.0;
        $blocking = !empty($mapped['blocking']);
        $error = $blocking ? 'non_work_status_' . $category : '';

        return [
            'category' => $category,
            'hours' => $hours,
            'worked_days' => $hours > 0 ? round($hours / 8, 4) : 0.0,
            'blocking' => $blocking,
            'skip' => false,
            'error' => $error,
        ];
    }

    private function summaryTotalCheck(array $config, array $cells, float $workedDays, float $hours): array
    {
        $check = $config['total_validation'] ?? ['enabled' => false];
        if (empty($check['enabled'])) {
            return ['enabled' => false, 'status' => 'not_configured', 'error' => '', 'detail' => $check['reason'] ?? ''];
        }

        $actual = $this->numericCell($cells, (int)$check['source_col']);
        $expected = $check['unit'] === 'minutes' ? round($workedDays * 60, 4) : $hours;
        $tolerance = (float)($check['tolerance'] ?? 0.01);
        $diff = abs($actual - $expected);

        return [
            'enabled' => true,
            'status' => $diff <= $tolerance ? 'matched' : 'mismatch',
            'error' => $diff <= $tolerance ? '' : 'total_mismatch',
            'expected' => $expected,
            'actual' => $actual,
            'unit' => $check['unit'],
        ];
    }

    private function wideDailyTotalCheck(array $config, array $cells): array
    {
        $check = $config['total_validation'] ?? ['enabled' => false];
        if (empty($check['enabled'])) {
            return ['enabled' => false, 'status' => 'not_configured', 'error' => '', 'detail' => $check['reason'] ?? ''];
        }

        return ['enabled' => false, 'status' => 'not_reliable_for_wide_profile', 'error' => '', 'detail' => 'Wide profile total validation is disabled until owner confirms daily unit meaning.'];
    }

    private function findEmployeeByRules(string $identifier, string $name, array $config): array
    {
        $rules = $config['employee_matching']['enabled_rules'];
        $aliases = $config['employee_matching']['alias_map'] ?? [];

        if (in_array('alias_manual_mapping', $rules, true)) {
            foreach ([$identifier, $name, $this->nameKey($name)] as $sourceKey) {
                if ($sourceKey !== '' && isset($aliases[$sourceKey])) {
                    $employee = $this->findEmployeeByExactIdentifier((string)$aliases[$sourceKey]);
                    if ($employee) {
                        return ['employee' => $employee, 'rule' => 'alias_manual_mapping'];
                    }
                }
            }
        }

        if (in_array('exact_employee_id', $rules, true) && trim($identifier) !== '') {
            $employee = $this->findEmployeeByExactIdentifier($identifier);
            if ($employee) {
                return ['employee' => $employee, 'rule' => 'exact_employee_id'];
            }
        }

        if (in_array('normalized_employee_id', $rules, true) && trim($identifier) !== '') {
            $normalized = $this->employeeIdKey($identifier);
            if ($normalized !== '' && $normalized !== trim($identifier)) {
                $employee = $this->findEmployeeByExactIdentifier($normalized);
                if ($employee) {
                    return ['employee' => $employee, 'rule' => 'normalized_employee_id'];
                }
            }
        }

        if (in_array('employee_name_fallback', $rules, true) && trim($name) !== '') {
            $employee = $this->findEmployeeByName($name);
            if ($employee) {
                return ['employee' => $employee, 'rule' => 'employee_name_fallback'];
            }
        }

        return ['employee' => null, 'rule' => 'unmatched'];
    }

    private function findEmployeeByExactIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT employee_id, payroll_employee_id, old_employee_id, full_name, client_id, client_location_id
            FROM employee_list
            WHERE CAST(employee_id AS CHAR) = :identifier
               OR payroll_employee_id = :identifier
               OR old_employee_id = :identifier
            LIMIT 1
        ");
        $stmt->execute([':identifier' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function findEmployeeByName(string $name): ?array
    {
        $keys = $this->nameKeys($name);
        if (count($keys) === 0) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare("
            SELECT employee_id, payroll_employee_id, old_employee_id, full_name, client_id, client_location_id
            FROM employee_list
            WHERE REPLACE(REPLACE(REPLACE(UPPER(full_name), ',', ''), '.', ''), ' ', '') IN ($placeholders)
            LIMIT 2
        ");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return count($rows) === 1 ? $rows[0] : null;
    }

    private function isIgnoredOrFooterRow(array $config, int $rowNumber, array $cells): bool
    {
        if (in_array($rowNumber, $config['ignored_rows'], true)) {
            return true;
        }

        $joined = strtoupper(implode(' ', array_map('strval', $cells)));
        foreach ($config['footer_detection_rules'] as $keyword) {
            if ($keyword !== '' && strpos($joined, strtoupper($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function readSheetMatrix(string $path, string $sheetName): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to read workbook: ' . basename($path));
        }

        try {
            $sheetTarget = $this->sheetTarget($zip, $sheetName);
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName($sheetTarget);
            if ($sheetXml === false) {
                throw new RuntimeException('Worksheet not found: ' . $sheetName);
            }
        } finally {
            $zip->close();
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }

        $matrix = [];
        foreach ($xml->sheetData->row as $row) {
            $rowNumber = (int)$row['r'];
            $values = [];
            foreach ($row->c as $cell) {
                $cellRef = (string)$cell['r'];
                $columnIndex = $this->columnIndexFromCellRef($cellRef);
                $type = (string)$cell['t'];
                $value = isset($cell->v) ? (string)$cell->v : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string)$cell->is->t;
                }
                $value = trim(preg_replace('/\s+/', ' ', (string)$value));
                if ($value !== '') {
                    $values[$columnIndex] = $value;
                }
            }
            if (count($values) > 0) {
                $matrix[$rowNumber] = $values;
            }
        }

        return $matrix;
    }

    private function sheetTarget(ZipArchive $zip, string $sheetName): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('Workbook metadata missing.');
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        if (!$workbook || !$rels) {
            throw new RuntimeException('Workbook metadata unreadable.');
        }

        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string)$rel['Id']] = (string)$rel['Target'];
        }

        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        foreach ($workbook->xpath('//m:sheet') as $sheet) {
            if ((string)$sheet['name'] !== $sheetName) {
                continue;
            }
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string)$attrs['id'];
            $target = $relMap[$rid] ?? '';
            if ($target === '') {
                break;
            }
            return strpos($target, 'xl/') === 0 ? $target : 'xl/' . ltrim($target, '/');
        }

        throw new RuntimeException('Sheet not found: ' . $sheetName);
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $parsed = simplexml_load_string($xml);
        if (!$parsed || !isset($parsed->si)) {
            return [];
        }

        $strings = [];
        foreach ($parsed->si as $si) {
            $value = '';
            if (isset($si->t)) {
                $value .= (string)$si->t;
            }
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    $value .= (string)$run->t;
                }
            }
            $strings[] = trim(preg_replace('/\s+/', ' ', $value));
        }

        return $strings;
    }

    private function dateColumnSummary(array $config): string
    {
        if ($config['mode'] === 'wide_daily') {
            $range = $config['date_day_column_range'];
            return 'columns ' . $range['start_col'] . ' through ' . ((int)$range['start_col'] + (int)$range['count'] - 1) . ' / ' . $config['period_start'] . ' through ' . $config['period_end'];
        }
        if ($config['mode'] === 'raw_punch') {
            return 'row date column ' . (string)($config['columns']['work_date'] ?? 0) . ' / ' . $config['period_start'] . ' through ' . $config['period_end'];
        }

        return 'period summary row / ' . $config['period_start'] . ' through ' . $config['period_end'];
    }

    private function cell(array $cells, int $index): string
    {
        if ($index <= 0) {
            return '';
        }

        return trim((string)($cells[$index] ?? ''));
    }

    private function numericCell(array $cells, int $index): float
    {
        $value = str_replace(',', '', $this->cell($cells, $index));
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private function normalizeWorkDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (is_numeric($value)) {
            $serial = (float)$value;
            if ($serial > 20000 && $serial < 80000) {
                return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)floor($serial) . ' days')->format('Y-m-d');
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private function normalizeTimeValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $clean = str_replace(',', '', $value);
        if (is_numeric($clean)) {
            $number = (float)$clean;
            if ($number >= 0 && $number < 2) {
                $minutes = (int)round(($number - floor($number)) * 1440);
                $hours = intdiv($minutes, 60) % 24;
                $mins = $minutes % 60;
                return sprintf('%02d:%02d', $hours, $mins);
            }
            if (preg_match('/^\d{3,4}$/', $clean)) {
                $padded = str_pad($clean, 4, '0', STR_PAD_LEFT);
                return substr($padded, 0, 2) . ':' . substr($padded, 2, 2);
            }
        }
        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $value, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }
        if (preg_match('/\b([01]\d|2[0-3])([0-5]\d)\b/', $value, $m)) {
            return $m[1] . ':' . $m[2];
        }
        return '';
    }

    private function workedHoursFromTimes(string $timeIn, string $timeOut): array
    {
        if ($timeIn === '' || $timeOut === '') {
            return ['hours' => 0.0, 'overnight' => false];
        }
        [$inHour, $inMinute] = array_map('intval', explode(':', $timeIn));
        [$outHour, $outMinute] = array_map('intval', explode(':', $timeOut));
        $in = ($inHour * 60) + $inMinute;
        $out = ($outHour * 60) + $outMinute;
        $overnight = false;
        if ($out < $in) {
            $out += 1440;
            $overnight = true;
        }
        $minutes = $out - $in;
        return ['hours' => round($minutes / 60, 4), 'overnight' => $overnight];
    }

    private function employeeIdKey(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($value)));
        return ltrim($value, '0');
    }

    private function nameKeys(string $value): array
    {
        $keys = [];
        $direct = $this->nameKey($value);
        if ($direct !== '') {
            $keys[] = $direct;
        }

        if (strpos($value, ',') !== false) {
            $parts = array_map('trim', explode(',', $value, 2));
            if (count($parts) === 2) {
                $reversed = trim($parts[1] . ' ' . $parts[0]);
                $key = $this->nameKey($reversed);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function nameKey(string $value): string
    {
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]/', '', $value);

        return (string)$value;
    }

    private function columnIndexFromCellRef(string $cellRef): int
    {
        preg_match('/^[A-Z]+/i', $cellRef, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index;
    }

    private function samplePath(string $filename): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Sample Data' . DIRECTORY_SEPARATOR . $filename;
    }

    private function samplePathForConfig(array $config): string
    {
        $converted = (string)($config['converted_sample_path'] ?? '');
        if ($converted !== '' && is_file($converted)) {
            return $converted;
        }

        return $this->samplePath((string)$config['filename']);
    }

    private function sampleConfigs(): array
    {
        $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'adapter_profiles.json';
        if (!is_file($configPath)) {
            throw new RuntimeException('Adapter profile configuration is missing.');
        }

        $decoded = json_decode((string)file_get_contents($configPath), true);
        if (!is_array($decoded) || !isset($decoded['profiles']) || !is_array($decoded['profiles'])) {
            throw new RuntimeException('Adapter profile configuration is invalid.');
        }

        $profiles = [];
        foreach ($decoded['profiles'] as $profile) {
            $profile['client_id'] = (int)($profile['client_id'] ?? 0);
            $profile['location_id'] = (int)($profile['location_id'] ?? 0);
            $profile['file_type'] = $profile['file_type'] ?? strtolower(pathinfo((string)($profile['filename'] ?? ''), PATHINFO_EXTENSION)) ?: 'xlsx';
            $profile['converted_sample_path'] = (string)($profile['converted_sample_path'] ?? '');
            if ($profile['converted_sample_path'] !== '' && !is_file($profile['converted_sample_path'])) {
                $profile['converted_sample_path'] = '';
            }
            $profile['ignored_rows'] = array_map('intval', $profile['ignored_rows'] ?? []);
            $profile['footer_detection_rules'] = $profile['footer_detection_rules'] ?? ['TOTAL'];
            $profile['owner_decision_checklist'] = $profile['owner_decision_checklist'] ?? [];
            $profile['adapter_hardening_notes'] = $profile['adapter_hardening_notes'] ?? [];
            $profile['headers'] = is_array($profile['headers'] ?? null) ? $profile['headers'] : [];
            $profile['fields'] = is_array($profile['fields'] ?? null) ? $profile['fields'] : [];
            $profile['columns'] = is_array($profile['columns'] ?? null) ? $profile['columns'] : ['employee_identifier' => 0, 'employee_name' => 0, 'area' => 0, 'worked_days' => 0];
            $profile['date_day_column_range'] = is_array($profile['date_day_column_range'] ?? null) ? $profile['date_day_column_range'] : ['start_col' => 0, 'count' => 0];
            $profile['employee_matching']['enabled_rules'] = $profile['employee_matching']['enabled_rules'] ?? ['exact_employee_id', 'normalized_employee_id', 'employee_name_fallback'];
            $profile['employee_matching']['alias_map'] = $profile['employee_matching']['alias_map'] ?? [];
            $profile['status_dictionary']['codes'] = $profile['status_dictionary']['codes'] ?? [];
            $profile['status_dictionary']['numeric_zero_category'] = $profile['status_dictionary']['numeric_zero_category'] ?? 'absent';
            $profile['summary_conversion']['hours_per_worked_day'] = (float)($profile['summary_conversion']['hours_per_worked_day'] ?? 8);
            $profile['total_validation'] = $profile['total_validation'] ?? ['enabled' => false];
            $profiles[] = $profile;
        }

        return $profiles;
    }

    private function profileByKey(string $profileKey): ?array
    {
        foreach ($this->sampleConfigs() as $config) {
            if (strtoupper((string)$config['key']) === $profileKey) {
                return $config;
            }
        }

        return null;
    }

    private function loadApprovals(): array
    {
        $path = $this->approvalPath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['approvals']) || !is_array($decoded['approvals'])) {
            return [];
        }

        $approvals = [];
        foreach ($decoded['approvals'] as $key => $approval) {
            $profileKey = strtoupper((string)($approval['profile_key'] ?? $key));
            $approvals[$profileKey] = $this->normalizeApproval($profileKey, is_array($approval) ? $approval : []);
        }

        return $approvals;
    }

    private function saveApprovals(array $approvals): void
    {
        $path = $this->approvalPath();
        $payload = [
            'version' => '2026-06-21-batch14',
            'updated_at' => date('Y-m-d H:i:s'),
            'approvals' => $approvals,
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    }

    private function approvalPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'adapter_approvals.json';
    }

    private function defaultApproval(string $profileKey): array
    {
        return [
            'profile_key' => $profileKey,
            'approval_status' => 'draft',
            'decision_status' => 'draft',
            'approval_scope' => 'blocked',
            'risk_accepted' => false,
            'employee_matching_approved' => false,
            'status_dictionary_approved' => false,
            'client_site_binding_approved' => false,
            'pay_period_extraction_approved' => false,
            'suspicious_preview_reviewed' => false,
            'owner_approval_captured' => false,
            'payroll_handoff_owner_approval' => false,
            'payroll_handoff_blocked' => true,
            'payroll_generation_approved' => false,
            'mapping_gaps' => [],
            'approval_notes' => '',
            'approver' => '',
            'approval_date' => '',
            'updated_by' => '',
            'updated_at' => '',
        ];
    }

    private function normalizeApproval(string $profileKey, array $approval): array
    {
        $normalized = array_merge($this->defaultApproval($profileKey), $approval);
        $normalized['profile_key'] = $profileKey;
        $normalized['decision_status'] = (string)($normalized['decision_status'] ?: $normalized['approval_status']);
        $normalized['approval_scope'] = (string)($normalized['approval_scope'] ?: 'blocked');
        $normalized['mapping_gaps'] = is_array($normalized['mapping_gaps']) ? $normalized['mapping_gaps'] : [];
        $normalized['risk_accepted'] = !empty($normalized['risk_accepted']);
        $normalized['payroll_handoff_owner_approval'] = !empty($normalized['payroll_handoff_owner_approval']);
        $normalized['payroll_handoff_blocked'] = array_key_exists('payroll_handoff_blocked', $normalized)
            ? !empty($normalized['payroll_handoff_blocked'])
            : true;
        $normalized['payroll_generation_approved'] = !empty($normalized['payroll_generation_approved']);

        return $normalized;
    }

    private function boolPost(array $post, string $key): bool
    {
        $value = $post[$key] ?? '';
        return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true);
    }

    private function mappingGapsFromApproval(string $profileKey, array $post): array
    {
        $raw = trim((string)($post['mapping_gaps'] ?? ''));
        if ($raw !== '') {
            $items = preg_split('/\r\n|\r|\n/', $raw);
            return array_values(array_filter(array_map('trim', $items), static function ($item) {
                return $item !== '';
            }));
        }

        if ($profileKey === 'CYA') {
            return [
                'CYA identifier/name source requires owner confirmation.',
                'CYA alias/manual mapping requires owner confirmation.',
                'CYA worked-days interpretation requires owner confirmation.',
            ];
        }

        return [];
    }

    private function approvalGate(array $approval, array $preview): array
    {
        $profileChecks = [
            'employee_matching_approved' => !empty($approval['employee_matching_approved']),
            'status_dictionary_approved' => !empty($approval['status_dictionary_approved']),
            'client_site_binding_approved' => !empty($approval['client_site_binding_approved']),
            'pay_period_extraction_approved' => !empty($approval['pay_period_extraction_approved']),
            'suspicious_preview_reviewed' => !empty($approval['suspicious_preview_reviewed']),
            'owner_approval_captured' => !empty($approval['owner_approval_captured']),
        ];

        $profileBlocking = [];
        foreach ($profileChecks as $check => $passed) {
            if (!$passed) {
                $profileBlocking[] = $check;
            }
        }

        if (($approval['approval_status'] ?? '') !== 'approved') {
            $profileBlocking[] = 'approval_status_not_approved';
        }

        if (!in_array(($approval['approval_scope'] ?? 'blocked'), ['preview_only', 'staging_only'], true)) {
            $profileBlocking[] = 'approval_scope_not_preview_or_staging';
        }

        $profileReviewComplete = count($profileBlocking) === 0;
        $handoffBlocking = $profileBlocking;
        if (empty($approval['payroll_handoff_owner_approval'])) {
            $handoffBlocking[] = 'explicit_payroll_handoff_owner_approval_missing';
        }
        if (!empty($approval['payroll_handoff_blocked'])) {
            $handoffBlocking[] = 'payroll_handoff_blocked';
        }
        if (($approval['approval_scope'] ?? '') !== 'payroll_handoff_eligible') {
            $handoffBlocking[] = 'approval_scope_not_payroll_handoff_eligible';
        }

        return [
            'profile_review_complete' => $profileReviewComplete,
            'payroll_handoff_ready' => false,
            'profile_blocking_items' => array_values(array_unique($profileBlocking)),
            'blocking_items' => array_values(array_unique($handoffBlocking)),
            'checks' => $profileChecks + [
                'explicit_payroll_handoff_owner_approval' => !empty($approval['payroll_handoff_owner_approval']),
                'payroll_handoff_not_blocked' => empty($approval['payroll_handoff_blocked']),
                'approval_scope_payroll_handoff_eligible' => ($approval['approval_scope'] ?? '') === 'payroll_handoff_eligible',
            ],
            'approval_scope' => $approval['approval_scope'] ?? 'blocked',
            'payroll_handoff_blocked' => !empty($approval['payroll_handoff_blocked']),
            'preview_flags_review_required' => !empty($preview['requires_suspicious_review']),
        ];
    }

    private function mappingChecklist(array $config, array $approval): array
    {
        $items = [
            ['key' => 'employee_ids_reliable', 'question' => 'Are employee IDs reliable?', 'required_field' => 'employee_matching_approved'],
            ['key' => 'name_fallback_allowed', 'question' => 'Is name fallback allowed?', 'required_field' => 'employee_matching_approved'],
            ['key' => 'aliases_needed', 'question' => 'Are aliases/manual mappings needed?', 'required_field' => 'employee_matching_approved'],
            ['key' => 'blank_meaning', 'question' => 'What does blank mean?', 'required_field' => 'status_dictionary_approved'],
            ['key' => 'numeric_meaning', 'question' => 'What does numeric value mean?', 'required_field' => 'status_dictionary_approved'],
            ['key' => 'status_meaning', 'question' => 'What does absent/rest day/leave/holiday look like?', 'required_field' => 'status_dictionary_approved'],
            ['key' => 'totals_meaning', 'question' => 'Are totals hours, days, or payroll summaries?', 'required_field' => 'suspicious_preview_reviewed'],
            ['key' => 'total_rows_ignored', 'question' => 'Should total rows be ignored?', 'required_field' => 'suspicious_preview_reviewed'],
            ['key' => 'client_site_assignment', 'question' => 'How should client/site be assigned?', 'required_field' => 'client_site_binding_approved'],
            ['key' => 'pay_period_extraction', 'question' => 'How should pay period be extracted?', 'required_field' => 'pay_period_extraction_approved'],
        ];

        foreach ($items as &$item) {
            $item['approved'] = !empty($approval[$item['required_field']]);
        }
        unset($item);

        foreach (($config['owner_decision_checklist'] ?? []) as $index => $decision) {
            $items[] = [
                'key' => 'owner_decision_' . ($index + 1),
                'question' => $decision,
                'required_field' => 'owner_approval_captured',
                'approved' => !empty($approval['owner_approval_captured']),
            ];
        }

        return $items;
    }

    private function latestPreviewStatus(array $config): array
    {
        $stmt = $this->db->prepare("
            SELECT id, row_count, error_count
            FROM dtr_upload_batches
            WHERE source_context = :source_context
              AND original_filename = :filename
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':source_context' => self::SOURCE_CONTEXT,
            ':filename' => basename((string)$config['filename']),
        ]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            return [
                'has_preview' => false,
                'batch_id' => 0,
                'row_count' => 0,
                'valid_rows' => 0,
                'error_rows' => 0,
                'unmatched_employee_count' => 0,
                'error_rate' => 0,
                'suspicious_hour_rows' => 0,
                'total_mismatch_rows' => 0,
                'unsupported_status_rows' => 0,
                'summary_row_included_risk' => false,
                'zero_eligible_rows' => true,
                'requires_suspicious_review' => true,
                'flags' => ['no_current_preview_run'],
            ];
        }

        $rows = $this->db->prepare("
            SELECT validation_status, parsed_payload, error_summary
            FROM dtr_upload_staging_rows
            WHERE batch_id = :batch_id
              AND is_synthetic = 1
        ");
        $rows->execute([':batch_id' => (int)$batch['id']]);

        $validRows = 0;
        $unmatched = 0;
        $suspiciousRows = 0;
        $totalMismatch = 0;
        $unsupported = 0;
        $summaryRisk = false;

        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((string)$row['validation_status'] === 'valid') {
                $validRows++;
            }

            $payload = json_decode((string)$row['parsed_payload'], true);
            $errors = json_decode((string)$row['error_summary'], true);
            $errors = is_array($errors) ? $errors : [];
            $flags = is_array($payload) && isset($payload['preview_safety_flags']) && is_array($payload['preview_safety_flags'])
                ? $payload['preview_safety_flags']
                : [];

            if (in_array('unknown_employee', $errors, true)) {
                $unmatched++;
            }
            if (count($flags) > 0) {
                $suspiciousRows++;
            }
            if (in_array('total_mismatch', $errors, true)) {
                $totalMismatch++;
            }
            if (in_array('invalid_status_code', $errors, true)) {
                $unsupported++;
            }
            if (in_array('summary_row_included_as_employee_row', $flags, true)) {
                $summaryRisk = true;
            }
        }

        $rowCount = (int)$batch['row_count'];
        $errorRows = (int)$batch['error_count'];
        $errorRate = $rowCount > 0 ? round($errorRows / $rowCount, 4) : 0;
        $flags = [];
        if ($validRows === 0) {
            $flags[] = 'zero_eligible_rows';
        }
        if ($unmatched >= 10) {
            $flags[] = 'large_unmatched_employee_count';
        }
        if ($errorRate >= 0.25) {
            $flags[] = 'high_error_rate';
        }
        if ($suspiciousRows > 0) {
            $flags[] = 'suspicious_preview_hours';
        }
        if ($totalMismatch > 0) {
            $flags[] = 'total_mismatch';
        }
        if ($summaryRisk) {
            $flags[] = 'summary_row_included_as_employee_row';
        }
        if ($unsupported > 0) {
            $flags[] = 'unsupported_status_value';
        }

        return [
            'has_preview' => true,
            'batch_id' => (int)$batch['id'],
            'row_count' => $rowCount,
            'valid_rows' => $validRows,
            'error_rows' => $errorRows,
            'unmatched_employee_count' => $unmatched,
            'error_rate' => $errorRate,
            'suspicious_hour_rows' => $suspiciousRows,
            'total_mismatch_rows' => $totalMismatch,
            'unsupported_status_rows' => $unsupported,
            'summary_row_included_risk' => $summaryRisk,
            'zero_eligible_rows' => $validRows === 0,
            'requires_suspicious_review' => count($flags) > 0,
            'flags' => $flags,
        ];
    }

    private function clientSiteBindingRule(array $config): string
    {
        if (!empty($config['client_id']) || !empty($config['location_id'])) {
            return 'Profile-bound client/site IDs: client=' . (int)$config['client_id'] . ', site=' . (int)$config['location_id'];
        }

        return 'Owner review required: infer from employee record unless profile-specific client/site binding is approved.';
    }

    private function statusDictionarySummary(array $config): array
    {
        $summary = [];
        foreach (($config['status_dictionary']['codes'] ?? []) as $code => $rule) {
            $summary[] = [
                'code' => $code,
                'category' => $rule['category'] ?? '',
                'blocking' => !empty($rule['blocking']),
                'skip' => !empty($rule['skip']),
                'default_hours' => $rule['default_hours'] ?? null,
            ];
        }

        return $summary;
    }

    private function workedHoursInterpretation(array $config): string
    {
        if ($config['mode'] === 'wide_daily') {
            return 'Wide daily numeric cells are previewed as hours/units only; owner must confirm meaning before any future basis use.';
        }

        return 'Summary worked-days rows are previewed as worked_days x ' . (float)$config['summary_conversion']['hours_per_worked_day'] . ' hours; not payroll truth.';
    }

    private function totalValidationRule(array $config): string
    {
        $rule = $config['total_validation'] ?? ['enabled' => false];
        if (empty($rule['enabled'])) {
            return 'Not enforced: ' . ($rule['reason'] ?? 'Owner confirmation required.');
        }

        return 'Enabled: source column ' . (int)$rule['source_col'] . ' compared as ' . (string)$rule['unit'] . ' with tolerance ' . (string)($rule['tolerance'] ?? '0.01') . '.';
    }

    private function suspiciousHourThreshold(array $config): string
    {
        $safety = $config['preview_safety'] ?? [];
        $parts = [];
        foreach (['max_daily_hours_per_row', 'max_summary_hours_per_row', 'max_worked_days_per_period', 'max_profile_preview_hours'] as $key) {
            if (isset($safety[$key])) {
                $parts[] = $key . '=' . $safety[$key];
            }
        }

        return count($parts) > 0 ? implode(', ', $parts) : 'No threshold configured.';
    }
}

?>
