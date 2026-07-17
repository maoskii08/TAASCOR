<?php

class TemplateManager
{
    public $db = null;

    private const GENERIC_ERROR = 'Unable to complete the DTR Format Engine request. Please contact your administrator.';

    public function getLookups(): array
    {
        try {
            return [
                'success' => 1,
                'clients' => $this->fetchClients(),
                'locations' => $this->fetchLocations(),
                'source_types' => ['biometric', 'excel', 'csv', 'manual-template', 'vendor-export'],
                'file_types' => ['xlsx', 'xls', 'csv'],
                'canonical_fields' => [
                    'employee_id',
                    'employee_code',
                    'employee_name',
                    'client',
                    'site',
                    'work_date',
                    'time_in',
                    'time_out',
                    'break_start',
                    'break_end',
                    'hours_worked',
                    'late_minutes',
                    'undertime_minutes',
                    'overtime_hours',
                    'remarks',
                ],
            ];
        } catch (\Throwable $th) {
            error_log('DTR template lookups failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function listTemplates(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    t.id,
                    t.template_name,
                    t.client_id,
                    c.client_name,
                    t.location_id,
                    l.location_name,
                    t.source_type,
                    t.file_type,
                    t.date_format,
                    t.time_format,
                    t.employee_identifier_field,
                    t.is_active,
                    t.created_by,
                    t.created_at,
                    t.updated_by,
                    t.updated_at,
                    COUNT(f.id) AS field_count
                FROM dtr_format_templates t
                LEFT JOIN taascor_client c ON c.client_id = t.client_id
                LEFT JOIN taascor_client_location l ON l.location_id = t.location_id
                LEFT JOIN dtr_format_template_fields f ON f.template_id = t.id
                GROUP BY
                    t.id, t.template_name, t.client_id, c.client_name,
                    t.location_id, l.location_name, t.source_type, t.file_type,
                    t.date_format, t.time_format, t.employee_identifier_field,
                    t.is_active, t.created_by, t.created_at, t.updated_by, t.updated_at
                ORDER BY t.is_active DESC, t.template_name ASC
            ");
            $stmt->execute();

            return ['success' => 1, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (\Throwable $th) {
            error_log('DTR template list failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function getTemplate(int $id): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM dtr_format_templates
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return ['success' => 0, 'error' => 'DTR template was not found.'];
            }

            $fields = $this->getTemplateFields($id);
            $template['fields'] = $fields;

            return ['success' => 1, 'data' => $template];
        } catch (\Throwable $th) {
            error_log('DTR template get failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function saveTemplate(array $input, string $user): array
    {
        try {
            $payload = $this->normalizeTemplateInput($input);
            if (!$payload['valid']) {
                return ['success' => 0, 'error' => $payload['error']];
            }

            $data = $payload['data'];
            $this->db->beginTransaction();

            if ($data['id'] > 0) {
                $stmt = $this->db->prepare("
                    UPDATE dtr_format_templates
                    SET template_name = :template_name,
                        client_id = :client_id,
                        location_id = :location_id,
                        source_type = :source_type,
                        file_type = :file_type,
                        expected_headers = :expected_headers,
                        date_format = :date_format,
                        time_format = :time_format,
                        employee_identifier_field = :employee_identifier_field,
                        is_active = :is_active,
                        updated_by = :updated_by
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':template_name' => $data['template_name'],
                    ':client_id' => $data['client_id'],
                    ':location_id' => $data['location_id'],
                    ':source_type' => $data['source_type'],
                    ':file_type' => $data['file_type'],
                    ':expected_headers' => $data['expected_headers'],
                    ':date_format' => $data['date_format'],
                    ':time_format' => $data['time_format'],
                    ':employee_identifier_field' => $data['employee_identifier_field'],
                    ':is_active' => $data['is_active'],
                    ':updated_by' => $user,
                    ':id' => $data['id'],
                ]);
                $templateId = $data['id'];
            } else {
                $stmt = $this->db->prepare("
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
                        :date_format,
                        :time_format,
                        :employee_identifier_field,
                        :is_active,
                        :created_by,
                        :updated_by
                    )
                ");
                $stmt->execute([
                    ':template_name' => $data['template_name'],
                    ':client_id' => $data['client_id'],
                    ':location_id' => $data['location_id'],
                    ':source_type' => $data['source_type'],
                    ':file_type' => $data['file_type'],
                    ':expected_headers' => $data['expected_headers'],
                    ':date_format' => $data['date_format'],
                    ':time_format' => $data['time_format'],
                    ':employee_identifier_field' => $data['employee_identifier_field'],
                    ':is_active' => $data['is_active'],
                    ':created_by' => $user,
                    ':updated_by' => $user,
                ]);
                $templateId = (int)$this->db->lastInsertId();
            }

            $this->replaceFields($templateId, $data['fields']);
            $this->db->commit();

            return ['success' => 1, 'id' => $templateId];
        } catch (\Throwable $th) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR template save failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function deactivateTemplate(int $id, string $user): array
    {
        try {
            if ($id <= 0) {
                return ['success' => 0, 'error' => 'A valid DTR template is required.'];
            }

            $stmt = $this->db->prepare("
                UPDATE dtr_format_templates
                SET is_active = 0,
                    updated_by = :updated_by
                WHERE id = :id
            ");
            $stmt->execute([':updated_by' => $user, ':id' => $id]);

            return ['success' => 1];
        } catch (\Throwable $th) {
            error_log('DTR template deactivate failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    public function listBatches(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    b.id,
                    b.batch_uid,
                    b.template_id,
                    t.template_name,
                    b.original_filename,
                    b.uploaded_by,
                    b.uploaded_at,
                    b.checksum,
                    b.row_count,
                    b.validation_status,
                    b.error_count,
                    b.processing_status,
                    b.is_synthetic,
                    b.source_context,
                    COUNT(r.id) AS staged_rows
                FROM dtr_upload_batches b
                LEFT JOIN dtr_format_templates t ON t.id = b.template_id
                LEFT JOIN dtr_upload_staging_rows r ON r.batch_id = b.id
                GROUP BY
                    b.id, b.batch_uid, b.template_id, t.template_name,
                    b.original_filename, b.uploaded_by, b.uploaded_at,
                    b.checksum, b.row_count, b.validation_status,
                    b.error_count, b.processing_status, b.is_synthetic,
                    b.source_context
                ORDER BY b.uploaded_at DESC, b.id DESC
            ");
            $stmt->execute();

            return ['success' => 1, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } catch (\Throwable $th) {
            error_log('DTR upload batch list failed: ' . $th->getMessage());
            return ['success' => 0, 'error' => self::GENERIC_ERROR];
        }
    }

    private function fetchClients(): array
    {
        $stmt = $this->db->prepare("
            SELECT client_id, client_name
            FROM taascor_client
            WHERE client_name <> 'No Client'
              AND COALESCE(is_active, 1) = 1
            ORDER BY client_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchLocations(): array
    {
        $stmt = $this->db->prepare("
            SELECT location_id, location_name
            FROM taascor_client_location
            ORDER BY location_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTemplateFields(int $templateId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, source_header, canonical_field, data_type, is_required, sort_order, transform_rule
            FROM dtr_format_template_fields
            WHERE template_id = :template_id
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([':template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeTemplateInput(array $input): array
    {
        $fieldsJson = trim((string)($input['fields_json'] ?? '[]'));
        $fields = json_decode($fieldsJson, true);
        if (!is_array($fields)) {
            return ['valid' => false, 'error' => 'Column mapping is not valid.'];
        }

        $expectedHeaders = $this->normalizeLines((string)($input['expected_headers'] ?? ''));
        $data = [
            'id' => (int)($input['id'] ?? 0),
            'template_name' => trim((string)($input['template_name'] ?? '')),
            'client_id' => $this->nullableInt($input['client_id'] ?? null),
            'location_id' => $this->nullableInt($input['location_id'] ?? null),
            'source_type' => trim((string)($input['source_type'] ?? '')),
            'file_type' => trim((string)($input['file_type'] ?? 'xlsx')),
            'expected_headers' => json_encode($expectedHeaders),
            'date_format' => trim((string)($input['date_format'] ?? '')),
            'time_format' => trim((string)($input['time_format'] ?? '')),
            'employee_identifier_field' => trim((string)($input['employee_identifier_field'] ?? '')),
            'is_active' => isset($input['is_active']) ? 1 : 0,
            'fields' => $this->normalizeFields($fields),
        ];

        if ($data['template_name'] === '') {
            return ['valid' => false, 'error' => 'Template name is required.'];
        }
        if ($data['source_type'] === '') {
            return ['valid' => false, 'error' => 'Source type is required.'];
        }
        if ($data['date_format'] === '' || $data['time_format'] === '') {
            return ['valid' => false, 'error' => 'Date and time formats are required.'];
        }
        if ($data['employee_identifier_field'] === '') {
            return ['valid' => false, 'error' => 'Employee identifier field is required.'];
        }
        if (count($expectedHeaders) === 0) {
            return ['valid' => false, 'error' => 'At least one expected header is required.'];
        }
        if (count($data['fields']) === 0) {
            return ['valid' => false, 'error' => 'At least one column mapping is required.'];
        }

        return ['valid' => true, 'data' => $data];
    }

    private function normalizeLines(string $value): array
    {
        $items = preg_split('/[\r\n,]+/', $value);
        $normalized = [];

        foreach ($items as $item) {
            $trimmed = trim((string)$item);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeFields(array $fields): array
    {
        $clean = [];
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $sourceHeader = trim((string)($field['source_header'] ?? ''));
            $canonicalField = trim((string)($field['canonical_field'] ?? ''));
            if ($sourceHeader === '' || $canonicalField === '') {
                continue;
            }

            $clean[] = [
                'source_header' => $sourceHeader,
                'canonical_field' => $canonicalField,
                'data_type' => trim((string)($field['data_type'] ?? 'text')) ?: 'text',
                'is_required' => !empty($field['is_required']) ? 1 : 0,
                'sort_order' => (int)($field['sort_order'] ?? ($index + 1)),
                'transform_rule' => trim((string)($field['transform_rule'] ?? '')),
            ];
        }

        return $clean;
    }

    private function replaceFields(int $templateId, array $fields): void
    {
        $delete = $this->db->prepare("DELETE FROM dtr_format_template_fields WHERE template_id = :template_id");
        $delete->execute([':template_id' => $templateId]);

        $insert = $this->db->prepare("
            INSERT INTO dtr_format_template_fields (
                template_id,
                source_header,
                canonical_field,
                data_type,
                is_required,
                sort_order,
                transform_rule
            ) VALUES (
                :template_id,
                :source_header,
                :canonical_field,
                :data_type,
                :is_required,
                :sort_order,
                :transform_rule
            )
        ");

        foreach ($fields as $field) {
            $insert->execute([
                ':template_id' => $templateId,
                ':source_header' => $field['source_header'],
                ':canonical_field' => $field['canonical_field'],
                ':data_type' => $field['data_type'],
                ':is_required' => $field['is_required'],
                ':sort_order' => $field['sort_order'],
                ':transform_rule' => $field['transform_rule'] === '' ? null : $field['transform_rule'],
            ]);
        }
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }
}

?>
