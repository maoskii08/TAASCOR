<?php

class SyntheticUploadParser
{
    public $db = null;

    private const MAX_UPLOAD_BYTES = 2097152;
    private const GENERIC_ERROR = 'Unable to process the synthetic DTR preview. Please contact your administrator.';

    public function uploadSynthetic(array $post, array $file, string $user): array
    {
        try {
            $templateId = (int)($post['template_id'] ?? 0);
            $periodStart = trim((string)($post['period_start'] ?? ''));
            $periodEnd = trim((string)($post['period_end'] ?? ''));

            $template = $this->loadTemplate($templateId);
            if (!$template) {
                return ['success' => 0, 'error' => 'Select a valid active DTR template.'];
            }

            $fileCheck = $this->validateUploadFile($file);
            if (!$fileCheck['valid']) {
                return ['success' => 0, 'error' => $fileCheck['error']];
            }

            $parsed = $this->parseFile($file['tmp_name'], $fileCheck['extension']);
            if (!$parsed['success']) {
                return $parsed;
            }

            $headerValidation = $this->validateHeaders($parsed['headers'], $template['fields']);
            if (!$headerValidation['success']) {
                return [
                    'success' => 0,
                    'error' => 'Synthetic file headers do not match the selected template.',
                    'summary' => $headerValidation,
                ];
            }

            $checksum = hash_file('sha256', $file['tmp_name']);
            $preview = $this->validateRows(
                $parsed['headers'],
                $parsed['rows'],
                $template,
                $periodStart,
                $periodEnd
            );

            $batchId = $this->stagePreview(
                $templateId,
                (string)$file['name'],
                $user,
                $checksum,
                count($parsed['rows']),
                $preview
            );

            return [
                'success' => 1,
                'batch_id' => $batchId,
                'summary' => [
                    'batch_id' => $batchId,
                    'filename' => basename((string)$file['name']),
                    'extension' => $fileCheck['extension'],
                    'row_count' => count($parsed['rows']),
                    'valid_rows' => $preview['valid_rows'],
                    'error_rows' => $preview['error_rows'],
                    'missing_required_headers' => [],
                    'extra_unmapped_headers' => $headerValidation['extra_unmapped_headers'],
                    'validation_status' => $preview['error_rows'] > 0 ? 'failed' : 'passed',
                    'processing_status' => 'validation_preview',
                    'synthetic' => true,
                ],
                'rows' => $preview['safe_rows'],
            ];
        } catch (\Throwable $th) {
            error_log('DTR synthetic upload failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function clearSyntheticBatches(): array
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM dtr_upload_batches
                WHERE is_synthetic = 1
                   OR source_context = 'synthetic_batch2'
            ");
            $stmt->execute();

            return ['success' => 1, 'deleted_batches' => $stmt->rowCount()];
        } catch (\Throwable $th) {
            error_log('DTR synthetic clear failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function getBatchRows(int $batchId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT source_row_number, parsed_payload, validation_status, error_summary
                FROM dtr_upload_staging_rows
                WHERE batch_id = :batch_id
                  AND is_synthetic = 1
                ORDER BY source_row_number ASC
                LIMIT 300
            ");
            $stmt->execute([':batch_id' => $batchId]);
            $rows = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $payload = json_decode((string)$row['parsed_payload'], true);
                $rows[] = [
                    'row_number' => (int)$row['source_row_number'],
                    'employee_identifier' => $payload['employee_identifier'] ?? '',
                    'work_date' => $payload['work_date'] ?? '',
                    'time_in' => $payload['time_in'] ?? '',
                    'time_out' => $payload['time_out'] ?? '',
                    'validation_status' => $row['validation_status'],
                    'errors' => $this->decodeErrors((string)$row['error_summary']),
                ];
            }

            return ['success' => 1, 'data' => $rows];
        } catch (\Throwable $th) {
            error_log('DTR synthetic row listing failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function getNormalizationPreview(string $sourceContext = ''): array
    {
        try {
            $contextFilter = trim($sourceContext) !== '' ? ' AND b.source_context = :source_context' : '';
            $stmt = $this->db->prepare("
                SELECT
                    r.id AS staging_row_id,
                    r.source_row_number,
                    r.parsed_payload,
                    r.validation_status,
                    r.error_summary,
                    b.id AS batch_id,
                    b.batch_uid,
                    b.uploaded_at,
                    t.template_name,
                    t.id AS template_id,
                    t.client_id AS template_client_id
                FROM dtr_upload_staging_rows r
                INNER JOIN dtr_upload_batches b ON b.id = r.batch_id
                LEFT JOIN dtr_format_templates t ON t.id = b.template_id
                WHERE r.is_synthetic = 1
                  AND (b.is_synthetic = 1 OR b.source_context = 'synthetic_batch2')
                  $contextFilter
                ORDER BY b.id ASC, r.source_row_number ASC
                LIMIT 500
            ");
            $params = [];
            if (trim($sourceContext) !== '') {
                $params[':source_context'] = trim($sourceContext);
            }
            $stmt->execute($params);

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $payload = json_decode((string)$row['parsed_payload'], true);
                if (!is_array($payload)) {
                    $payload = [];
                }

                $employeeIdentifier = (string)($payload['employee_identifier'] ?? '');
                $employee = $employeeIdentifier === '' ? null : $this->findEmployee(
                    $employeeIdentifier,
                    (int)($row['template_client_id'] ?? 0),
                    (int)($row['template_id'] ?? 0)
                );
                $client = $employee ? $this->findClient((int)$employee['client_id']) : null;
                $site = $employee ? $this->findLocation((int)$employee['client_location_id']) : null;
                $errors = $this->decodeErrors((string)$row['error_summary']);
                $validationStatus = (string)$row['validation_status'];

                $rows[] = [
                    'staging_row_id' => (int)$row['staging_row_id'],
                    'employee_reference' => $employee ? (string)$employee['employee_id'] : $employeeIdentifier,
                    'client_reference' => $client['client_name'] ?? '',
                    'site_reference' => $site['location_name'] ?? '',
                    'client_id' => $employee ? (int)$employee['client_id'] : 0,
                    'site_id' => $employee ? (int)$employee['client_location_id'] : 0,
                    'work_date' => (string)($payload['work_date'] ?? ''),
                    'time_in' => (string)($payload['time_in'] ?? ''),
                    'time_out' => (string)($payload['time_out'] ?? ''),
                    'worked_hours_preview' => (float)($payload['hours_worked'] ?? 0),
                    'worked_days_preview' => (float)($payload['worked_days'] ?? 0),
                    'summary_preview' => !empty($payload['summary_preview']),
                    'source_format' => (string)($payload['source_format'] ?? ''),
                    'source_template' => (string)($row['template_name'] ?? ''),
                    'source_batch' => (string)$row['batch_uid'],
                    'source_batch_id' => (int)$row['batch_id'],
                    'source_row_number' => (int)$row['source_row_number'],
                    'uploaded_at' => (string)$row['uploaded_at'],
                    'validation_status' => $validationStatus,
                    'normalization_status' => $validationStatus === 'valid' ? 'preview_ready' : 'blocked_validation',
                    'validation_errors' => $errors,
                    'conflict_indicator' => false,
                    'conflict_types' => [],
                ];
            }

            $rows = $this->applyConflictPreview($rows);
            $conflictRows = array_filter($rows, static function ($row) {
                return !empty($row['conflict_indicator']);
            });
            $readyRows = array_filter($rows, static function ($row) {
                return $row['normalization_status'] === 'preview_ready';
            });
            $blockedRows = array_filter($rows, static function ($row) {
                return $row['normalization_status'] !== 'preview_ready';
            });

            return [
                'success' => 1,
                'summary' => [
                    'rows' => count($rows),
                    'normalization_ready' => count($readyRows),
                    'blocked_validation' => count($blockedRows),
                    'conflict_rows' => count($conflictRows),
                    'mode' => 'read_only_preview',
                    'source_context' => trim($sourceContext) !== '' ? trim($sourceContext) : 'all_local_preview_contexts',
                ],
                'rows' => array_values($rows),
                'consolidation_preview' => [
                    'same_employee_date_client_conflict' => 'Flag multiple synthetic rows for the same employee, date, and client before any merge.',
                    'duplicate_time_entry' => 'Flag exact duplicate employee/date/time ranges across synthetic batches.',
                    'overlapping_time_range' => 'Flag overlapping time ranges for the same employee/date/client.',
                    'newer_upload_versus_older_upload' => 'Treat newer synthetic rows as candidates only; replacement requires owner-approved workflow.',
                    'approval_required' => 'No staged row may silently overwrite prior staged data.',
                ],
            ];
        } catch (\Throwable $th) {
            error_log('DTR normalization preview failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function getTimekeepingPreview(string $sourceContext = ''): array
    {
        try {
            $normalization = $this->getNormalizationPreview($sourceContext);
            if (($normalization['success'] ?? 0) !== 1) {
                return $normalization;
            }

            $rows = [];
            $issueCounts = [];
            $eligibleRows = 0;
            $excludedRows = 0;
            $conflictRows = 0;
            $totalWorkedHours = 0.0;

            foreach ($normalization['rows'] as $row) {
                $exclusionReasons = $this->timekeepingExclusionReasons($row);
                $workedHours = $this->previewWorkedHours($row);
                $eligible = count($exclusionReasons) === 0;

                if ($eligible) {
                    $eligibleRows++;
                    $totalWorkedHours += $workedHours;
                } else {
                    $excludedRows++;
                }

                if (!empty($row['conflict_indicator'])) {
                    $conflictRows++;
                }

                foreach ($exclusionReasons as $reason) {
                    $issueCounts[$reason] = ($issueCounts[$reason] ?? 0) + 1;
                }

                $rows[] = [
                    'employee_reference' => $row['employee_reference'],
                    'client_reference' => $row['client_reference'],
                    'site_reference' => $row['site_reference'],
                    'work_date' => $row['work_date'],
                    'time_in' => $row['time_in'],
                    'time_out' => $row['time_out'],
                    'total_worked_hours' => $workedHours,
                    'worked_days_preview' => $row['worked_days_preview'] ?? 0,
                    'summary_preview' => !empty($row['summary_preview']),
                    'source_format' => $row['source_format'] ?? '',
                    'late_minutes_placeholder' => null,
                    'undertime_minutes_placeholder' => null,
                    'absent_flag_placeholder' => null,
                    'overtime_placeholder' => null,
                    'night_differential_placeholder' => null,
                    'rest_day_holiday_placeholder' => null,
                    'validation_status' => $row['validation_status'],
                    'conflict_status' => !empty($row['conflict_indicator']) ? 'approval_required' : 'clear',
                    'payroll_eligibility_status' => $eligible ? 'eligible_preview' : 'excluded_preview',
                    'exclusion_reason' => $exclusionReasons,
                    'source_template' => $row['source_template'],
                    'source_batch' => $row['source_batch'],
                    'source_row_number' => $row['source_row_number'],
                ];
            }

            ksort($issueCounts);

            return [
                'success' => 1,
                'summary' => [
                    'total_staged_rows' => count($rows),
                    'valid_normalized_rows' => (int)($normalization['summary']['normalization_ready'] ?? 0),
                    'payroll_preview_eligible_rows' => $eligibleRows,
                    'excluded_rows' => $excludedRows,
                    'conflict_rows' => $conflictRows,
                    'total_preview_worked_hours' => round($totalWorkedHours, 2),
                    'issue_count_by_type' => $issueCounts,
                    'mode' => 'read_only_timekeeping_preview',
                ],
                'rows' => $rows,
            ];
        } catch (\Throwable $th) {
            error_log('DTR timekeeping preview failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    private function validateUploadFile(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Select a synthetic CSV or XLSX test file.'];
        }

        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > self::MAX_UPLOAD_BYTES) {
            return ['valid' => false, 'error' => 'Synthetic file size must be between 1 byte and 2 MB.'];
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            return ['valid' => false, 'error' => 'Only synthetic CSV and XLSX files are supported.'];
        }

        if (!is_uploaded_file((string)$file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Synthetic file upload was not accepted.'];
        }

        return ['valid' => true, 'extension' => $extension];
    }

    private function parseFile(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->parseCsv($path);
        }
        if ($extension === 'xlsx') {
            return $this->parseXlsx($path);
        }

        return ['success' => 0, 'error' => 'Unsupported synthetic file format.'];
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return ['success' => 0, 'error' => 'Unable to read the synthetic CSV file.'];
        }

        $headers = fgetcsv($handle);
        if (!is_array($headers) || count($headers) === 0) {
            fclose($handle);
            return ['success' => 0, 'error' => 'Synthetic CSV file has no header row.'];
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $rows[] = $row;
            if (count($rows) > 500) {
                fclose($handle);
                return ['success' => 0, 'error' => 'Synthetic preview is limited to 500 rows.'];
            }
        }
        fclose($handle);

        return [
            'success' => 1,
            'headers' => $this->normalizeHeaderList($headers),
            'rows' => $rows,
        ];
    }

    private function parseXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            return ['success' => 0, 'error' => 'Synthetic XLSX support is not available in this PHP runtime.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['success' => 0, 'error' => 'Unable to read the synthetic XLSX file.'];
        }

        $sharedStrings = $this->loadSharedStrings($zip);
        $sheetXml = $this->getZipEntry($zip, 'xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return ['success' => 0, 'error' => 'Synthetic XLSX file has no first worksheet.'];
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return ['success' => 0, 'error' => 'Synthetic XLSX worksheet is empty.'];
        }

        $matrix = [];
        foreach ($xml->sheetData->row as $row) {
            $rowIndex = (int)$row['r'];
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
                $values[$columnIndex] = $value;
            }
            if (!$this->isBlankRow($values)) {
                ksort($values);
                $matrix[$rowIndex] = $values;
            }
        }

        ksort($matrix);
        if (count($matrix) === 0) {
            return ['success' => 0, 'error' => 'Synthetic XLSX worksheet is empty.'];
        }

        $headers = array_shift($matrix);
        $rows = [];
        foreach ($matrix as $row) {
            $rows[] = $row;
            if (count($rows) > 500) {
                return ['success' => 0, 'error' => 'Synthetic preview is limited to 500 rows.'];
            }
        }

        return [
            'success' => 1,
            'headers' => $this->normalizeHeaderList($headers),
            'rows' => $rows,
        ];
    }

    private function validateHeaders(array $headers, array $fields): array
    {
        $headerKeys = array_map([$this, 'headerKey'], $headers);
        $mappedKeys = [];
        $missingRequired = [];

        foreach ($fields as $field) {
            $sourceHeader = (string)$field['source_header'];
            $key = $this->headerKey($sourceHeader);
            $mappedKeys[] = $key;
            if ((int)$field['is_required'] === 1 && !in_array($key, $headerKeys, true)) {
                $missingRequired[] = $sourceHeader;
            }
        }

        $extra = [];
        foreach ($headers as $header) {
            if (!in_array($this->headerKey($header), $mappedKeys, true)) {
                $extra[] = $header;
            }
        }

        return [
            'success' => count($missingRequired) === 0,
            'missing_required_headers' => $missingRequired,
            'extra_unmapped_headers' => $extra,
        ];
    }

    private function validateRows(array $headers, array $rows, array $template, string $periodStart, string $periodEnd): array
    {
        $headerMap = $this->indexedHeaderMap($headers);
        $existingKeys = $this->loadExistingSyntheticKeys((int)$template['id']);
        $seenKeys = [];
        $safeRows = [];
        $validRows = 0;
        $errorRows = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $canonical = $this->canonicalizeRow($row, $headerMap, $template['fields']);
            $employeeIdentifier = $this->extractEmployeeIdentifier($row, $headerMap, $template);
            $canonical['employee_identifier'] = $employeeIdentifier;
            $errors = [];

            if ($employeeIdentifier === '') {
                $errors[] = 'missing_employee_identifier';
            }

            $employee = $employeeIdentifier === '' ? null : $this->findEmployee(
                $employeeIdentifier,
                (int)($template['client_id'] ?? 0),
                (int)($template['id'] ?? 0)
            );
            if ($employeeIdentifier !== '' && !$employee) {
                $errors[] = 'unknown_employee_identifier';
            }

            $workDate = $this->parseDate((string)($canonical['work_date'] ?? ''), (string)$template['date_format']);
            if (!$workDate) {
                $errors[] = 'invalid_date';
            }

            $timeInRaw = (string)($canonical['time_in'] ?? '');
            $timeOutRaw = (string)($canonical['time_out'] ?? '');
            if (trim($timeInRaw) === '' || trim($timeOutRaw) === '') {
                $errors[] = 'missing_time_in_time_out';
            }

            $timeIn = trim($timeInRaw) === '' ? null : $this->parseTime($timeInRaw, (string)$template['time_format']);
            $timeOut = trim($timeOutRaw) === '' ? null : $this->parseTime($timeOutRaw, (string)$template['time_format']);
            if ((trim($timeInRaw) !== '' && !$timeIn) || (trim($timeOutRaw) !== '' && !$timeOut)) {
                $errors[] = 'invalid_time_in_time_out';
            }

            if ($workDate && $periodStart !== '' && $periodEnd !== '') {
                if ($workDate < $periodStart || $workDate > $periodEnd) {
                    $errors[] = 'payroll_period_mismatch';
                }
            }

            if ($employee) {
                if (!empty($template['client_id']) && (int)$employee['client_id'] !== (int)$template['client_id']) {
                    $errors[] = 'invalid_client_mapping';
                }
                if (!empty($template['location_id']) && (int)$employee['client_location_id'] !== (int)$template['location_id']) {
                    $errors[] = 'invalid_site_mapping';
                }
            }

            $duplicateKey = implode('|', [
                $employeeIdentifier,
                $workDate ?: (string)($canonical['work_date'] ?? ''),
                $timeIn ?: $timeInRaw,
                $timeOut ?: $timeOutRaw,
            ]);

            if (isset($seenKeys[$duplicateKey])) {
                $errors[] = 'duplicate_row_within_file';
            }
            $seenKeys[$duplicateKey] = true;

            if (isset($existingKeys[$duplicateKey])) {
                $errors[] = 'duplicate_row_against_staged_batch';
            }

            $status = count($errors) === 0 ? 'valid' : 'error';
            if ($status === 'valid') {
                $validRows++;
            } else {
                $errorRows++;
            }

            $safeRows[] = [
                'row_number' => $rowNumber,
                'employee_identifier' => $employeeIdentifier,
                'work_date' => $workDate ?: (string)($canonical['work_date'] ?? ''),
                'time_in' => $timeIn ?: $timeInRaw,
                'time_out' => $timeOut ?: $timeOutRaw,
                'validation_status' => $status,
                'errors' => $errors,
                'raw_payload' => $this->rowToAssoc($headers, $row),
                'parsed_payload' => [
                    'employee_identifier' => $employeeIdentifier,
                    'employee_name' => (string)($canonical['employee_name'] ?? ''),
                    'work_date' => $workDate ?: (string)($canonical['work_date'] ?? ''),
                    'time_in' => $timeIn ?: $timeInRaw,
                    'time_out' => $timeOut ?: $timeOutRaw,
                    'duplicate_key' => $duplicateKey,
                    'synthetic' => true,
                ],
            ];
        }

        return [
            'safe_rows' => $safeRows,
            'valid_rows' => $validRows,
            'error_rows' => $errorRows,
        ];
    }

    private function stagePreview(int $templateId, string $filename, string $user, string $checksum, int $rowCount, array $preview): int
    {
        $this->db->beginTransaction();
        try {
            $batchUid = 'SYN-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
            $validationStatus = $preview['error_rows'] > 0 ? 'failed' : 'passed';

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
                    'validation_preview',
                    1,
                    'synthetic_batch2'
                )
            ");
            $batch->execute([
                ':batch_uid' => $batchUid,
                ':template_id' => $templateId,
                ':original_filename' => basename($filename),
                ':uploaded_by' => $user,
                ':checksum' => $checksum,
                ':row_count' => $rowCount,
                ':validation_status' => $validationStatus,
                ':error_count' => $preview['error_rows'],
            ]);
            $batchId = (int)$this->db->lastInsertId();

            $rowInsert = $this->db->prepare("
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

            foreach ($preview['safe_rows'] as $row) {
                $rowInsert->execute([
                    ':batch_id' => $batchId,
                    ':source_row_number' => $row['row_number'],
                    ':raw_payload' => json_encode(['synthetic' => true, 'row' => $row['raw_payload']]),
                    ':parsed_payload' => json_encode($row['parsed_payload']),
                    ':validation_status' => $row['validation_status'],
                    ':error_summary' => json_encode($row['errors']),
                ]);
            }

            $this->db->commit();
            return $batchId;
        } catch (\Throwable $th) {
            $this->db->rollBack();
            throw $th;
        }
    }

    private function loadTemplate(int $templateId): ?array
    {
        if ($templateId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dtr_format_templates
            WHERE id = :id
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            return null;
        }

        $fields = $this->db->prepare("
            SELECT source_header, canonical_field, data_type, is_required, sort_order
            FROM dtr_format_template_fields
            WHERE template_id = :template_id
            ORDER BY sort_order ASC, id ASC
        ");
        $fields->execute([':template_id' => $templateId]);
        $template['fields'] = $fields->fetchAll(PDO::FETCH_ASSOC);

        return $template;
    }

    private function findEmployee(string $identifier, int $clientId = 0, int $templateId = 0): ?array
    {
        if ($clientId > 0 && $templateId > 0) {
            try {
                $mapped = $this->db->prepare("\n                    SELECT e.employee_id, e.payroll_employee_id, e.old_employee_id,\n                           e.client_id, e.client_location_id\n                    FROM employee_identity_map m\n                    INNER JOIN employee_list e ON e.employee_id = m.employee_id\n                    WHERE m.client_id = :client_id\n                      AND m.source_namespace = :source_namespace\n                      AND m.source_employee_id = :identifier\n                      AND m.status = 'approved'\n                      AND e.status = 'Active'\n                      AND (m.effective_from IS NULL OR m.effective_from <= CURDATE())\n                      AND (m.effective_to IS NULL OR m.effective_to >= CURDATE())\n                    LIMIT 1\n                ");
                $mapped->execute([
                    ':client_id' => $clientId,
                    ':source_namespace' => 'template:' . $templateId,
                    ':identifier' => $identifier,
                ]);
                $mappedRow = $mapped->fetch(PDO::FETCH_ASSOC);
                if ($mappedRow) {
                    return $mappedRow;
                }
            } catch (Throwable $ignored) {
                // Migration may not be installed yet; retain safe legacy lookup below.
            }
        }

        $stmt = $this->db->prepare("
            SELECT employee_id, payroll_employee_id, old_employee_id, client_id, client_location_id
            FROM employee_list
            WHERE (
                    CAST(employee_id AS CHAR) = :identifier_a
                 OR payroll_employee_id = :identifier_b
                 OR old_employee_id = :identifier_c
            )
              AND (:client_id_a = 0 OR client_id = :client_id_b)
            LIMIT 1
        ");
        $stmt->execute([
            ':identifier_a' => $identifier,
            ':identifier_b' => $identifier,
            ':identifier_c' => $identifier,
            ':client_id_a' => $clientId,
            ':client_id_b' => $clientId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function findClient(int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT client_id, client_name
            FROM taascor_client
            WHERE client_id = :client_id
            LIMIT 1
        ");
        $stmt->execute([':client_id' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function findLocation(int $locationId): ?array
    {
        if ($locationId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT location_id, location_name
            FROM taascor_client_location
            WHERE location_id = :location_id
            LIMIT 1
        ");
        $stmt->execute([':location_id' => $locationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function parseDate(string $value, string $format): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = array_values(array_unique([$format, 'Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y', 'd-m-Y']));
        foreach ($formats as $candidate) {
            $dt = DateTime::createFromFormat('!' . $candidate, $value);
            $errors = DateTime::getLastErrors();
            $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
            if ($dt && !$hasErrors && $dt->format($candidate) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseTime(string $value, string $format): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = array_values(array_unique([$format, 'H:i', 'H:i:s', 'h:i A', 'h:i a']));
        foreach ($formats as $candidate) {
            $dt = DateTime::createFromFormat('!' . $candidate, $value);
            $errors = DateTime::getLastErrors();
            $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
            if ($dt && !$hasErrors) {
                return $dt->format('H:i');
            }
        }

        return null;
    }

    private function loadExistingSyntheticKeys(int $templateId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.parsed_payload
            FROM dtr_upload_staging_rows r
            INNER JOIN dtr_upload_batches b ON b.id = r.batch_id
            WHERE b.template_id = :template_id
              AND r.is_synthetic = 1
              AND (b.is_synthetic = 1 OR b.source_context = 'synthetic_batch2')
            LIMIT 5000
        ");
        $stmt->execute([':template_id' => $templateId]);

        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $payload) {
            $decoded = json_decode((string)$payload, true);
            if (is_array($decoded) && !empty($decoded['duplicate_key'])) {
                $keys[(string)$decoded['duplicate_key']] = true;
            }
        }

        return $keys;
    }

    private function canonicalizeRow(array $row, array $headerMap, array $fields): array
    {
        $canonical = [];
        foreach ($fields as $field) {
            $sourceKey = $this->headerKey((string)$field['source_header']);
            if (array_key_exists($sourceKey, $headerMap)) {
                $canonical[(string)$field['canonical_field']] = trim((string)($row[$headerMap[$sourceKey]] ?? ''));
            }
        }

        return $canonical;
    }

    private function extractEmployeeIdentifier(array $row, array $headerMap, array $template): string
    {
        $employeeHeader = $this->headerKey((string)$template['employee_identifier_field']);
        if (array_key_exists($employeeHeader, $headerMap)) {
            return trim((string)($row[$headerMap[$employeeHeader]] ?? ''));
        }

        foreach ($template['fields'] as $field) {
            if (in_array((string)$field['canonical_field'], ['employee_id', 'employee_code'], true)) {
                $sourceKey = $this->headerKey((string)$field['source_header']);
                if (array_key_exists($sourceKey, $headerMap)) {
                    return trim((string)($row[$headerMap[$sourceKey]] ?? ''));
                }
            }
        }

        return '';
    }

    private function rowToAssoc(array $headers, array $row): array
    {
        $assoc = [];
        foreach ($headers as $index => $header) {
            $assoc[(string)$header] = trim((string)($row[$index] ?? ''));
        }

        return $assoc;
    }

    private function indexedHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $map[$this->headerKey((string)$header)] = $index;
        }

        return $map;
    }

    private function normalizeHeaderList(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $normalized[] = trim((string)$header);
        }

        return $normalized;
    }

    private function headerKey(string $header): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $header)));
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function loadSharedStrings(ZipArchive $zip): array
    {
        $xml = $this->getZipEntry($zip, 'xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $parsed = simplexml_load_string($xml);
        if (!$parsed || !isset($parsed->si)) {
            return [];
        }

        $strings = [];
        foreach ($parsed->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string)$si->t;
                continue;
            }

            $value = '';
            if (isset($si->r)) {
                foreach ($si->r as $run) {
                    $value .= (string)$run->t;
                }
            }
            $strings[] = $value;
        }

        return $strings;
    }

    private function columnIndexFromCellRef(string $cellRef): int
    {
        preg_match('/^[A-Z]+/i', $cellRef, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function getZipEntry(ZipArchive $zip, string $path)
    {
        $value = $zip->getFromName($path);
        if ($value !== false) {
            return $value;
        }

        return $zip->getFromName(str_replace('/', '\\', $path));
    }

    private function applyConflictPreview(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $index => $row) {
            $groupKey = implode('|', [
                $row['employee_reference'],
                $row['work_date'],
                $row['client_id'],
            ]);
            if ($row['employee_reference'] !== '' && $row['work_date'] !== '') {
                $grouped[$groupKey][] = $index;
            }
        }

        foreach ($grouped as $indexes) {
            if (count($indexes) <= 1) {
                continue;
            }

            $minBatch = min(array_map(static function ($index) use ($rows) {
                return $rows[$index]['source_batch_id'];
            }, $indexes));

            foreach ($indexes as $index) {
                $rows[$index] = $this->addConflictType($rows[$index], 'same_employee_date_client_conflict');
                if ($rows[$index]['source_batch_id'] > $minBatch) {
                    $rows[$index] = $this->addConflictType($rows[$index], 'newer_upload_requires_approval');
                }
            }

            for ($i = 0; $i < count($indexes); $i++) {
                for ($j = $i + 1; $j < count($indexes); $j++) {
                    $leftIndex = $indexes[$i];
                    $rightIndex = $indexes[$j];
                    $left = $rows[$leftIndex];
                    $right = $rows[$rightIndex];

                    if ($left['time_in'] === $right['time_in'] && $left['time_out'] === $right['time_out']) {
                        $rows[$leftIndex] = $this->addConflictType($rows[$leftIndex], 'duplicate_time_entry');
                        $rows[$rightIndex] = $this->addConflictType($rows[$rightIndex], 'duplicate_time_entry');
                    } elseif ($this->timeRangesOverlap($left['time_in'], $left['time_out'], $right['time_in'], $right['time_out'])) {
                        $rows[$leftIndex] = $this->addConflictType($rows[$leftIndex], 'overlapping_time_range');
                        $rows[$rightIndex] = $this->addConflictType($rows[$rightIndex], 'overlapping_time_range');
                    }
                }
            }
        }

        return $rows;
    }

    private function addConflictType(array $row, string $type): array
    {
        if (!in_array($type, $row['conflict_types'], true)) {
            $row['conflict_types'][] = $type;
        }
        $row['conflict_indicator'] = true;

        return $row;
    }

    private function timeRangesOverlap(string $leftIn, string $leftOut, string $rightIn, string $rightOut): bool
    {
        if ($leftIn === '' || $leftOut === '' || $rightIn === '' || $rightOut === '') {
            return false;
        }

        $leftStart = strtotime('2000-01-01 ' . $leftIn);
        $leftEnd = strtotime('2000-01-01 ' . $leftOut);
        $rightStart = strtotime('2000-01-01 ' . $rightIn);
        $rightEnd = strtotime('2000-01-01 ' . $rightOut);

        if (!$leftStart || !$leftEnd || !$rightStart || !$rightEnd) {
            return false;
        }

        return $leftStart < $rightEnd && $rightStart < $leftEnd;
    }

    private function timekeepingExclusionReasons(array $row): array
    {
        $reasons = [];

        foreach (($row['validation_errors'] ?? []) as $error) {
            $reasons[] = (string)$error;
        }

        if (($row['normalization_status'] ?? '') !== 'preview_ready') {
            $reasons[] = 'normalization_not_ready';
        }

        if (!empty($row['conflict_indicator'])) {
            $reasons[] = 'duplicate_or_conflict_requires_approval';
        }

        if ((string)($row['employee_reference'] ?? '') === '') {
            $reasons[] = 'missing_employee';
        }

        if ((string)($row['work_date'] ?? '') === '') {
            $reasons[] = 'invalid_date';
        }

        $summaryPreview = !empty($row['summary_preview']);
        $previewHours = (float)($row['worked_hours_preview'] ?? 0);

        if (!$summaryPreview && ((string)($row['time_in'] ?? '') === '' || (string)($row['time_out'] ?? '') === '')) {
            $reasons[] = 'missing_time_in_time_out';
        }

        if ($summaryPreview && $previewHours <= 0) {
            $reasons[] = 'missing_or_invalid_worked_hours';
        } elseif (!$summaryPreview && $this->workedHours((string)($row['time_in'] ?? ''), (string)($row['time_out'] ?? '')) <= 0) {
            $reasons[] = 'invalid_time_in_time_out';
        }

        return array_values(array_unique($reasons));
    }

    private function previewWorkedHours(array $row): float
    {
        if (!empty($row['summary_preview'])) {
            return round((float)($row['worked_hours_preview'] ?? 0), 2);
        }

        return $this->workedHours((string)($row['time_in'] ?? ''), (string)($row['time_out'] ?? ''));
    }

    private function workedHours(string $timeIn, string $timeOut): float
    {
        if ($timeIn === '' || $timeOut === '') {
            return 0.0;
        }

        $start = strtotime('2000-01-01 ' . $timeIn);
        $end = strtotime('2000-01-01 ' . $timeOut);

        if (!$start || !$end || $end <= $start) {
            return 0.0;
        }

        return round(($end - $start) / 3600, 2);
    }

    private function decodeErrors(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

?>
