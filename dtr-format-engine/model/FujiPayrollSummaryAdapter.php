<?php

class FujiPayrollSummaryAdapter
{
    public $db = null;

    public const CLIENT_ID = 264;
    private const SOURCE_CONTEXT = 'fuji_payroll_summary';
    private const TEMPLATE_NAME = 'Fujifilm Payroll Summary';
    private const MAX_UPLOAD_BYTES = 5242880;
    private const MAX_ARCHIVE_ENTRIES = 2000;
    private const MAX_ARCHIVE_UNCOMPRESSED_BYTES = 67108864;
    private const MAX_ARCHIVE_ENTRY_BYTES = 16777216;
    private const MAX_COMPRESSION_RATIO = 200;

    public function stageUpload(array $post, array $file, string $user, ?array $profile = null): array
    {
        try {
            $fileCheck = $this->validateFile($file);
            if (!$fileCheck['valid']) {
                return ['success' => 0, 'error' => $fileCheck['error']];
            }

            $periodStart = trim((string)($post['period_start'] ?? ''));
            $periodEnd = trim((string)($post['period_end'] ?? ''));
            $payDate = trim((string)($post['pay_date'] ?? ''));
            if (!$this->validDate($periodStart) || !$this->validDate($periodEnd) || !$this->validDate($payDate)) {
                return ['success' => 0, 'error' => 'Enter valid Fuji period and pay-date values.'];
            }
            if ($periodEnd < $periodStart) {
                return ['success' => 0, 'error' => 'Fuji period end cannot be before period start.'];
            }

            $matrix = $this->readSheetMatrix((string)$file['tmp_name'], 'Sheet1');
            $workbookPeriod = $this->periodFromMarker($this->cell($matrix[3] ?? [], 2));
            if (!$workbookPeriod || $workbookPeriod['start'] !== $periodStart || $workbookPeriod['end'] !== $periodEnd) {
                return [
                    'success' => 0,
                    'error' => 'The workbook period marker does not match the selected Fuji payroll period.',
                    'workbook_period' => $workbookPeriod,
                ];
            }

            $rows = $this->buildRows($matrix, $periodStart, $periodEnd, $payDate);
            if (count($rows) === 0) {
                return ['success' => 0, 'error' => 'No Fuji employee summary rows were found.'];
            }
            if (count($rows) > 2000) {
                return ['success' => 0, 'error' => 'Fuji staging is limited to 2,000 employee summary rows.'];
            }

            $templateId = $this->ensureTemplate($user);
            if ($profile !== null
                && ((int)($profile['client_id'] ?? 0) !== self::CLIENT_ID
                    || (int)($profile['template_id'] ?? 0) !== $templateId
                    || (string)($profile['identity_policy'] ?? '') !== 'approved_mapping_required')) {
                return ['success' => 0, 'error' => 'The approved Fuji adapter binding is invalid.'];
            }
            $checksum = hash_file('sha256', (string)$file['tmp_name']);
            $existing = $this->existingBatch(
                $checksum,
                $periodStart,
                $periodEnd,
                $payDate,
                $profile === null ? null : (int)$profile['id']
            );
            if ($existing) {
                return [
                    'success' => 1,
                    'batch_id' => (int)$existing['id'],
                    'template_id' => $templateId,
                    'duplicate_upload' => true,
                    'summary' => [
                        'row_count' => (int)$existing['row_count'],
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'pay_date' => (string)$existing['pay_date'],
                        'checksum' => $checksum,
                    ],
                ];
            }

            $batchId = $this->stageRows(
                $templateId,
                basename((string)$file['name']),
                $user,
                $checksum,
                $periodStart,
                $periodEnd,
                $payDate,
                $profile,
                $rows
            );

            return [
                'success' => 1,
                'batch_id' => $batchId,
                'template_id' => $templateId,
                'duplicate_upload' => false,
                'summary' => [
                    'row_count' => count($rows),
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'pay_date' => $payDate,
                    'checksum' => $checksum,
                    'source_context' => self::SOURCE_CONTEXT,
                    'canonical_write' => 'blocked_staging_only',
                ],
            ];
        } catch (Throwable $error) {
            error_log('Fuji payroll summary staging failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to stage the Fuji payroll summary workbook.'];
        }
    }

    private function buildRows(array $matrix, string $periodStart, string $periodEnd, string $payDate): array
    {
        $rows = [];
        $sequenceSeen = [];
        $sourceSeen = [];
        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber < 4) {
                continue;
            }
            $sequence = $this->cell($cells, 1);
            $sourceId = $this->cell($cells, 2);
            $sourceName = $this->cell($cells, 3);
            if (!is_numeric($sequence) || $sourceId === '' || $sourceName === '') {
                continue;
            }

            $errors = ['unknown_employee_identifier'];
            if (isset($sequenceSeen[$sequence])) {
                $errors[] = 'duplicate_sequence';
            }
            if (isset($sourceSeen[$sourceId])) {
                $errors[] = 'duplicate_source_employee_id';
            }
            $sequenceSeen[$sequence] = true;
            $sourceSeen[$sourceId] = true;

            $dailyAttendance = [];
            for ($column = 6; $column <= 20; $column++) {
                $dailyAttendance[$this->columnLetter($column)] = $this->cell($cells, $column);
            }
            $hireDateRaw = $this->cell($cells, 5);

            $parsed = [
                'employee_identifier' => $sourceId,
                'employee_identifier_source' => $sourceId,
                'employee_name' => $sourceName,
                'employee_name_source' => $sourceName,
                'sequence' => (int)$sequence,
                'area' => $this->cell($cells, 4),
                'hire_date_source' => $this->normalizeWorkbookDate($hireDateRaw),
                'hire_date_serial_source' => is_numeric($hireDateRaw) ? $hireDateRaw : null,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'pay_date' => $payDate,
                'worked_days' => $this->number($cells, 21),
                'regular_hours' => $this->number($cells, 22),
                'regular_hours_with_late' => $this->number($cells, 23),
                'late_minutes' => $this->sumColumns($cells, [24, 36, 44, 52, 61]),
                'undertime_minutes' => $this->sumColumns($cells, [28, 40, 48, 56, 65]),
                'overtime_hours' => $this->number($cells, 25),
                'night_diff_hours' => $this->number($cells, 26),
                'night_diff_ot_hours' => $this->number($cells, 27),
                'meal_allowance' => $this->number($cells, 29),
                'perfect_attendance' => $this->number($cells, 30),
                'quarterly_attendance' => $this->number($cells, 31),
                'hmo_deduction' => $this->number($cells, 32),
                'rest_day_days' => $this->number($cells, 33),
                'rest_day_hours' => $this->number($cells, 34),
                'rest_day_hours_with_late' => $this->number($cells, 35),
                'rest_day_ot_hours' => $this->number($cells, 37),
                'rest_day_night_diff_hours' => $this->number($cells, 38),
                'rest_day_nd_ot_hours' => $this->number($cells, 39),
                'regular_holiday_days' => $this->number($cells, 41),
                'regular_holiday_hours' => $this->number($cells, 42),
                'regular_holiday_hours_with_late' => $this->number($cells, 43),
                'regular_holiday_ot_hours' => $this->number($cells, 45),
                'regular_holiday_night_diff_hours' => $this->number($cells, 46),
                'regular_holiday_nd_ot_hours' => $this->number($cells, 47),
                'special_holiday_days' => $this->number($cells, 49),
                'special_holiday_hours' => $this->number($cells, 50),
                'special_holiday_hours_with_late' => $this->number($cells, 51),
                'special_holiday_ot_hours' => $this->number($cells, 53),
                'special_holiday_night_diff_hours' => $this->number($cells, 54),
                'special_holiday_nd_ot_hours' => $this->number($cells, 55),
                'sil_days' => $this->number($cells, 57),
                'adjustment_amount' => $this->number($cells, 58),
                'adjustment_hours' => $this->number($cells, 59),
                'adjustment_hours_with_late' => $this->number($cells, 60),
                'adjustment_ot_hours' => $this->number($cells, 62),
                'adjustment_night_diff_hours' => $this->number($cells, 63),
                'adjustment_nd_ot_hours' => $this->number($cells, 64),
                'adjustment_perfect_attendance' => $this->number($cells, 66),
                'adjustment_quarterly_attendance' => $this->number($cells, 67),
                'adjustment_meal_allowance' => $this->number($cells, 68),
                'adjustment_sil' => $this->number($cells, 69),
                'paternity_leave' => $this->number($cells, 70),
                'adjustment_legal_holiday' => $this->number($cells, 71),
                'daily_attendance' => $dailyAttendance,
                'summary_preview' => true,
                'source_format' => 'fuji_payroll_summary',
                'source_adapter' => 'FUJI_PAYROLL_SUMMARY',
                'duplicate_key' => $this->duplicateKey($sourceId, $periodStart, $periodEnd, $payDate),
                'canonical_write' => false,
            ];

            $raw = [];
            for ($column = 1; $column <= 82; $column++) {
                $value = $this->cell($cells, $column);
                if ($value !== '') {
                    $raw[$this->columnLetter($column)] = $value;
                }
            }

            $rows[] = [
                'source_row_number' => (int)$rowNumber,
                'raw_payload' => $raw,
                'parsed_payload' => $parsed,
                'validation_status' => 'error',
                'errors' => array_values(array_unique($errors)),
            ];
        }
        return $rows;
    }

    private function stageRows(
        int $templateId,
        string $filename,
        string $user,
        string $checksum,
        string $periodStart,
        string $periodEnd,
        string $payDate,
        ?array $profile,
        array $rows
    ): int {
        if (count($rows) <= 0 || count($rows) > 2000) {
            throw new RuntimeException('Fuji source row count is outside the governed intake limit.');
        }
        $this->db->beginTransaction();
        try {
            $batchUid = 'FUJI-' . str_replace('-', '', $periodStart) . '-' . str_replace('-', '', $periodEnd)
                . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
            $profileId = $profile === null ? null : (int)$profile['id'];
            $adapterKey = $profile === null
                ? 'FUJI_PAYROLL_SUMMARY'
                : (string)$profile['adapter_key'];
            $adapterVersion = $profile === null
                ? 'legacy-pilot'
                : (string)$profile['adapter_version'];
            $adapterHash = $profile === null
                ? null
                : (string)$profile['configuration_hash'];
            $idempotencyKey = hash('sha256', implode('|', [
                self::CLIENT_ID,
                $profileId ?: 0,
                $adapterHash ?: 'legacy-pilot',
                $checksum,
                $periodStart,
                $periodEnd,
                $payDate,
            ]));
            $batch = $this->db->prepare("
                INSERT INTO dtr_upload_batches (
                    batch_uid, template_id, client_id, location_id,
                    adapter_profile_id, adapter_key, adapter_version,
                    adapter_config_hash, identity_policy,
                    original_filename, uploaded_by, checksum, upload_idempotency_key,
                    row_count, accepted_row_count, rejected_row_count, was_truncated,
                    validation_status, error_count, processing_status,
                    is_synthetic, source_context
                ) VALUES (
                    :batch_uid, :template_id, :client_id, NULL,
                    :adapter_profile_id, :adapter_key, :adapter_version,
                    :adapter_config_hash, 'approved_mapping_required',
                    :original_filename, :uploaded_by, :checksum, :upload_idempotency_key,
                    :row_count, 0, :rejected_row_count, 0,
                    'blocked_employee_identity', :error_count, 'identity_review_required',
                    0, :source_context
                )
            ");
            $batch->execute([
                ':batch_uid' => $batchUid,
                ':template_id' => $templateId,
                ':client_id' => self::CLIENT_ID,
                ':adapter_profile_id' => $profileId,
                ':adapter_key' => $adapterKey,
                ':adapter_version' => $adapterVersion,
                ':adapter_config_hash' => $adapterHash,
                ':original_filename' => $filename,
                ':uploaded_by' => $user,
                ':checksum' => $checksum,
                ':upload_idempotency_key' => $idempotencyKey,
                ':row_count' => count($rows),
                ':rejected_row_count' => count($rows),
                ':error_count' => count($rows),
                ':source_context' => self::SOURCE_CONTEXT,
            ]);
            $batchId = (int)$this->db->lastInsertId();

            $insert = $this->db->prepare("
                INSERT INTO dtr_upload_staging_rows (
                    batch_id, source_row_number, raw_payload, parsed_payload,
                    validation_status, error_summary, is_synthetic
                ) VALUES (
                    :batch_id, :source_row_number, :raw_payload, :parsed_payload,
                    :validation_status, :error_summary, 0
                )
            ");
            foreach ($rows as $row) {
                $insert->execute([
                    ':batch_id' => $batchId,
                    ':source_row_number' => $row['source_row_number'],
                    ':raw_payload' => json_encode($row['raw_payload'], JSON_UNESCAPED_UNICODE),
                    ':parsed_payload' => json_encode($row['parsed_payload'], JSON_UNESCAPED_UNICODE),
                    ':validation_status' => $row['validation_status'],
                    ':error_summary' => json_encode($row['errors']),
                ]);
            }
            $count = $this->db->prepare(
                'SELECT COUNT(*) FROM dtr_upload_staging_rows WHERE batch_id = :batch_id'
            );
            $count->execute([':batch_id' => $batchId]);
            if ((int)$count->fetchColumn() !== count($rows)) {
                throw new RuntimeException('Fuji staged row count does not match the source row count.');
            }
            $this->db->commit();
            return $batchId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function ensureTemplate(string $user): int
    {
        $find = $this->db->prepare("\n            SELECT id FROM dtr_format_templates\n            WHERE template_name = :template_name AND client_id = :client_id\n            ORDER BY id DESC LIMIT 1\n        ");
        $find->execute([':template_name' => self::TEMPLATE_NAME, ':client_id' => self::CLIENT_ID]);
        $templateId = (int)$find->fetchColumn();
        if ($templateId > 0) {
            $activate = $this->db->prepare("\n                UPDATE dtr_format_templates\n                SET is_active = 1, source_type = :source_type, updated_by = :updated_by\n                WHERE id = :id\n            ");
            $activate->execute([
                ':source_type' => self::SOURCE_CONTEXT,
                ':updated_by' => $user,
                ':id' => $templateId,
            ]);
            return $templateId;
        }

        $headers = [
            'Source ID', 'Employee Name', 'Area', 'Days Worked', 'Regular Hours',
            'Late Minutes', 'Undertime Minutes', 'OT', 'ND', 'ND OT',
            'Rest Day', 'Regular Holiday', 'Special Holiday', 'Meal Allowance',
            'Attendance Additions', 'HMO', 'SIL', 'Adjustments',
        ];
        $insert = $this->db->prepare("\n            INSERT INTO dtr_format_templates (\n                template_name, client_id, location_id, source_type, file_type,\n                expected_headers, date_format, time_format, employee_identifier_field,\n                is_active, created_by\n            ) VALUES (\n                :template_name, :client_id, NULL, :source_type, 'xlsx',\n                :expected_headers, 'Y-m-d', 'summary', 'Source ID', 1, :created_by\n            )\n        ");
        $insert->execute([
            ':template_name' => self::TEMPLATE_NAME,
            ':client_id' => self::CLIENT_ID,
            ':source_type' => self::SOURCE_CONTEXT,
            ':expected_headers' => json_encode($headers),
            ':created_by' => $user,
        ]);
        $templateId = (int)$this->db->lastInsertId();

        $fields = [
            ['Source ID', 'employee_identifier', 'text', 1],
            ['Employee Name', 'employee_name', 'text', 1],
            ['Area', 'area', 'text', 0],
            ['Days Worked', 'worked_days', 'number', 1],
            ['Regular Hours', 'regular_hours', 'number', 0],
            ['Late Minutes', 'late_minutes', 'number', 0],
            ['Undertime Minutes', 'undertime_minutes', 'number', 0],
            ['Regular OT', 'overtime_hours', 'number', 0],
            ['Night Differential', 'night_diff_hours', 'number', 0],
            ['Night Differential OT', 'night_diff_ot_hours', 'number', 0],
            ['Meal Allowance', 'meal_allowance', 'number', 0],
            ['HMO', 'hmo_deduction', 'number', 0],
            ['SIL', 'sil_days', 'number', 0],
            ['Adjustment', 'adjustment_amount', 'number', 0],
        ];
        $field = $this->db->prepare("\n            INSERT INTO dtr_format_template_fields (\n                template_id, source_header, canonical_field, data_type, is_required, sort_order\n            ) VALUES (\n                :template_id, :source_header, :canonical_field, :data_type, :is_required, :sort_order\n            )\n        ");
        foreach ($fields as $index => $definition) {
            $field->execute([
                ':template_id' => $templateId,
                ':source_header' => $definition[0],
                ':canonical_field' => $definition[1],
                ':data_type' => $definition[2],
                ':is_required' => $definition[3],
                ':sort_order' => $index + 1,
            ]);
        }
        return $templateId;
    }

    private function existingBatch(
        string $checksum,
        string $periodStart,
        string $periodEnd,
        string $payDate,
        ?int $profileId = null
    ): ?array {
        $profileFilter = $profileId === null
            ? ''
            : ' AND b.adapter_profile_id = :adapter_profile_id';
        $stmt = $this->db->prepare("
            SELECT b.id, b.row_count,
                   JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.pay_date')) AS pay_date
            FROM dtr_upload_batches b
            INNER JOIN dtr_upload_staging_rows r ON r.batch_id = b.id
            WHERE b.source_context = :source_context
              AND b.checksum = :checksum
              AND JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.period_start')) = :period_start
              AND JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.period_end')) = :period_end
              AND JSON_UNQUOTE(JSON_EXTRACT(r.parsed_payload, '$.pay_date')) = :pay_date
              {$profileFilter}
            ORDER BY b.id DESC
            LIMIT 1
        ");
        $params = [
            ':source_context' => self::SOURCE_CONTEXT,
            ':checksum' => $checksum,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd,
            ':pay_date' => $payDate,
        ];
        if ($profileId !== null) {
            $params[':adapter_profile_id'] = $profileId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function duplicateKey(string $sourceId, string $periodStart, string $periodEnd, string $payDate): string
    {
        return implode('__', ['FUJI', $sourceId, $periodStart, $periodEnd, $payDate]);
    }

    private function normalizeWorkbookDate(string $value): string
    {
        $value = trim($value);
        if (is_numeric($value)) {
            $serial = (float)$value;
            if ($serial >= 1 && $serial <= 80000) {
                return (new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC')))
                    ->modify('+' . (int)floor($serial) . ' days')
                    ->format('Y-m-d');
            }
        }
        foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y', '!Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date instanceof DateTimeImmutable
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        return '';
    }

    private function validateFile(array $file): array
    {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Select the Fuji .xlsx workbook.'];
        }
        $path = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? '');
        if (!is_file($path) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
            return ['valid' => false, 'error' => 'Fuji staging accepts only a valid .xlsx workbook.'];
        }
        $size = (int)($file['size'] ?? filesize($path));
        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            return ['valid' => false, 'error' => 'Fuji workbook size must be between 1 byte and 5 MB.'];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || $zip->locateName('[Content_Types].xml') === false) {
            if ($zip->status === ZipArchive::ER_OK) {
                $zip->close();
            }
            return ['valid' => false, 'error' => 'The selected file is not a readable Excel workbook.'];
        }
        try {
            $this->validateArchive($zip);
        } catch (RuntimeException $error) {
            $zip->close();
            return ['valid' => false, 'error' => $error->getMessage()];
        }
        $zip->close();
        return ['valid' => true];
    }

    private function readSheetMatrix(string $path, string $sheetName): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open Fuji workbook.');
        }
        try {
            $this->validateArchive($zip);
            $sheetTarget = $this->sheetTarget($zip, $sheetName);
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $this->safeZipEntry($zip, $sheetTarget, true);
        } finally {
            $zip->close();
        }

        $xml = $this->parseXml($sheetXml, 'Fuji worksheet');
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }
        $matrix = [];
        foreach ($xml->sheetData->row as $row) {
            $rowNumber = (int)$row['r'];
            $values = [];
            foreach ($row->c as $cell) {
                $column = $this->columnIndexFromCellRef((string)$cell['r']);
                $type = (string)$cell['t'];
                $value = isset($cell->v) ? (string)$cell->v : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string)$cell->is->t;
                }
                $values[$column] = trim(preg_replace('/\s+/', ' ', (string)$value));
            }
            if ($values) {
                $matrix[$rowNumber] = $values;
            }
        }
        return $matrix;
    }

    private function sheetTarget(ZipArchive $zip, string $sheetName): string
    {
        $workbook = $this->parseXml(
            $this->safeZipEntry($zip, 'xl/workbook.xml', true),
            'Fuji workbook metadata'
        );
        $rels = $this->parseXml(
            $this->safeZipEntry($zip, 'xl/_rels/workbook.xml.rels', true),
            'Fuji workbook relationships'
        );
        if (!$workbook || !$rels) {
            throw new RuntimeException('Fuji workbook metadata is unreadable.');
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
            $target = $relMap[(string)$attrs['id']] ?? '';
            if ($target !== '') {
                return strpos($target, 'xl/') === 0 ? $target : 'xl/' . ltrim($target, '/');
            }
        }
        throw new RuntimeException('Sheet not found: ' . $sheetName);
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $raw = $this->safeZipEntry($zip, 'xl/sharedStrings.xml', false);
        if ($raw === null) {
            return [];
        }
        $xml = $this->parseXml($raw, 'Fuji shared strings');
        if (!$xml || !isset($xml->si)) {
            return [];
        }
        $strings = [];
        foreach ($xml->si as $si) {
            $value = isset($si->t) ? (string)$si->t : '';
            foreach ($si->r as $run) {
                $value .= (string)$run->t;
            }
            $strings[] = trim(preg_replace('/\s+/', ' ', $value));
        }
        return $strings;
    }

    private function validateArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw new RuntimeException('The workbook contains too many archive entries.');
        }
        $uncompressed = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat)) {
                throw new RuntimeException('The workbook archive is unreadable.');
            }
            $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                throw new RuntimeException('The workbook contains an unsafe archive path.');
            }
            $size = (int)($stat['size'] ?? 0);
            $compressed = (int)($stat['comp_size'] ?? 0);
            if ($size < 0 || $size > self::MAX_ARCHIVE_ENTRY_BYTES) {
                throw new RuntimeException('The workbook contains an oversized XML entry.');
            }
            if ($compressed > 0 && $size / $compressed > self::MAX_COMPRESSION_RATIO) {
                throw new RuntimeException('The workbook compression ratio is unsafe.');
            }
            $uncompressed += $size;
            if ($uncompressed > self::MAX_ARCHIVE_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('The expanded workbook is too large.');
            }
        }
    }

    private function safeZipEntry(ZipArchive $zip, string $path, bool $required): ?string
    {
        $stat = $zip->statName($path);
        if (!is_array($stat)) {
            if ($required) {
                throw new RuntimeException('The workbook is missing required content.');
            }
            return null;
        }
        if ((int)($stat['size'] ?? 0) > self::MAX_ARCHIVE_ENTRY_BYTES) {
            throw new RuntimeException('The workbook contains an oversized XML entry.');
        }
        $value = $zip->getFromName($path);
        if ($value === false) {
            throw new RuntimeException('The workbook content could not be read.');
        }
        return $value;
    }

    private function parseXml(string $xml, string $label): SimpleXMLElement
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new RuntimeException($label . ' contains prohibited XML declarations.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $parsed = simplexml_load_string(
                $xml,
                SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_COMPACT
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$parsed instanceof SimpleXMLElement) {
            throw new RuntimeException($label . ' is unreadable.');
        }
        return $parsed;
    }

    private function periodFromMarker(string $marker): ?array
    {
        if (!preg_match('/^([A-Z]+)\s+(\d{1,2})\s*-\s*(\d{1,2}),\s*(\d{4})$/i', trim($marker), $match)) {
            return null;
        }
        $start = DateTimeImmutable::createFromFormat('!F j Y', ucfirst(strtolower($match[1])) . ' ' . $match[2] . ' ' . $match[4]);
        $end = DateTimeImmutable::createFromFormat('!F j Y', ucfirst(strtolower($match[1])) . ' ' . $match[3] . ' ' . $match[4]);
        if (!$start || !$end) {
            return null;
        }
        return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function cell(array $cells, int $column): string
    {
        return trim((string)($cells[$column] ?? ''));
    }

    private function number(array $cells, int $column): float
    {
        $value = str_replace(',', '', $this->cell($cells, $column));
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private function sumColumns(array $cells, array $columns): float
    {
        $total = 0.0;
        foreach ($columns as $column) {
            $total += $this->number($cells, $column);
        }
        return round($total, 4);
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

    private function columnLetter(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(65 + ($column % 26)) . $letters;
            $column = intdiv($column, 26);
        }
        return $letters;
    }
}
