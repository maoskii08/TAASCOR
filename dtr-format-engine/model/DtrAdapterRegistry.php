<?php

class DtrAdapterRegistry
{
    public $db = null;
    public bool $allow_all_clients = false;
    public array $allowed_client_ids = [];

    private const PARSERS = [
        'template_tabular_v1',
        'period_summary_workbook_v1',
        'fuji_payroll_summary_v1',
    ];

    private const IDENTITY_POLICIES = [
        'approved_mapping_required',
        'trusted_hris_identifier',
    ];

    public function listProfiles(bool $approvedOnly = false): array
    {
        try {
            [$scopeSql, $scopeParams] = $this->clientScopeSql('p');
            $statusSql = $approvedOnly
                ? " AND p.profile_status = 'approved'
                    AND p.effective_from <= CURDATE()
                    AND (p.effective_to IS NULL OR p.effective_to >= CURDATE())"
                : '';
            $stmt = $this->db->prepare("
                SELECT
                    p.id, p.profile_uid, p.client_id, c.client_name,
                    p.location_id, l.location_name, p.template_id, t.template_name,
                    p.adapter_key, p.adapter_version, p.display_name, p.parser_key,
                    p.file_type, p.identity_policy, p.configuration_hash,
                    p.profile_status, p.effective_from, p.effective_to,
                    p.created_by, p.created_at, p.approved_by, p.approved_at,
                    p.approval_reason
                FROM dtr_adapter_profiles p
                INNER JOIN taascor_client c ON c.client_id = p.client_id
                INNER JOIN dtr_format_templates t ON t.id = p.template_id
                LEFT JOIN taascor_client_location l ON l.location_id = p.location_id
                WHERE 1 = 1
                {$scopeSql}
                {$statusSql}
                ORDER BY c.client_name, p.display_name, p.effective_from DESC, p.id DESC
            ");
            $stmt->execute($scopeParams);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['id'] = (int)$row['id'];
                $row['client_id'] = (int)$row['client_id'];
                $row['location_id'] = $row['location_id'] === null ? null : (int)$row['location_id'];
                $row['template_id'] = (int)$row['template_id'];
            }
            unset($row);
            return ['success' => 1, 'data' => $rows];
        } catch (Throwable $error) {
            error_log('DTR adapter registry listing failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to load the governed DTR adapter registry.'];
        }
    }

    public function createDraftFromTemplate(array $input, string $user): array
    {
        $templateId = (int)($input['template_id'] ?? 0);
        $adapterKey = $this->normalizeKey((string)($input['adapter_key'] ?? ''));
        $adapterVersion = trim((string)($input['adapter_version'] ?? ''));
        $displayName = trim((string)($input['display_name'] ?? ''));
        $parserKey = trim((string)($input['parser_key'] ?? 'template_tabular_v1'));
        $identityPolicy = trim((string)($input['identity_policy'] ?? 'approved_mapping_required'));
        $effectiveFrom = trim((string)($input['effective_from'] ?? date('Y-m-d')));
        $effectiveTo = trim((string)($input['effective_to'] ?? '')) ?: null;

        if ($templateId <= 0 || $adapterKey === '' || $adapterVersion === '' || $displayName === '') {
            return ['success' => 0, 'error' => 'Template, adapter key, version, and display name are required.'];
        }
        if (!preg_match('/^[A-Za-z0-9._-]{1,80}$/', $adapterVersion)) {
            return ['success' => 0, 'error' => 'Adapter version may contain letters, numbers, dots, underscores, and dashes only.'];
        }
        if (!in_array($parserKey, self::PARSERS, true)) {
            return ['success' => 0, 'error' => 'Select a supported governed parser.'];
        }
        if (!in_array($identityPolicy, self::IDENTITY_POLICIES, true)) {
            return ['success' => 0, 'error' => 'Select a supported employee identity policy.'];
        }
        if (!$this->validDate($effectiveFrom)
            || ($effectiveTo !== null && !$this->validDate($effectiveTo))
            || ($effectiveTo !== null && $effectiveTo < $effectiveFrom)) {
            return ['success' => 0, 'error' => 'Enter a valid adapter effective-date window.'];
        }

        try {
            $this->db->beginTransaction();
            $template = $this->loadTemplate($templateId, true);
            if (!$template || (int)$template['client_id'] <= 0) {
                $this->db->rollBack();
                return ['success' => 0, 'error' => 'The adapter template must be active and assigned to one client.'];
            }
            if (!$this->canAccessClient((int)$template['client_id'])) {
                $this->db->rollBack();
                return ['success' => 0, 'error' => 'You do not have access to the selected client.'];
            }
            if (!$this->parserMatchesTemplate($parserKey, $template)) {
                $this->db->rollBack();
                return [
                    'success' => 0,
                    'error' => 'The selected parser does not support this template file type or source format.',
                ];
            }

            $configuration = [
                'schema_version' => 1,
                'template' => [
                    'id' => (int)$template['id'],
                    'template_name' => (string)$template['template_name'],
                    'client_id' => (int)$template['client_id'],
                    'location_id' => $template['location_id'] === null ? null : (int)$template['location_id'],
                    'source_type' => (string)$template['source_type'],
                    'file_type' => strtolower((string)$template['file_type']),
                    'expected_headers' => $this->decodeHeaders((string)$template['expected_headers']),
                    'date_format' => (string)$template['date_format'],
                    'time_format' => (string)$template['time_format'],
                    'employee_identifier_field' => (string)$template['employee_identifier_field'],
                    'fields' => $template['fields'],
                ],
                'parser_key' => $parserKey,
                'identity_policy' => $identityPolicy,
            ];
            $payload = self::canonicalJson($configuration);
            $hash = hash('sha256', $payload);
            $profileUid = 'DTRAP-' . strtoupper(bin2hex(random_bytes(12)));

            $insert = $this->db->prepare("
                INSERT INTO dtr_adapter_profiles (
                    profile_uid, client_id, location_id, template_id,
                    adapter_key, adapter_version, display_name, parser_key,
                    file_type, identity_policy, configuration_payload,
                    configuration_hash, profile_status, effective_from,
                    effective_to, created_by
                ) VALUES (
                    :profile_uid, :client_id, :location_id, :template_id,
                    :adapter_key, :adapter_version, :display_name, :parser_key,
                    :file_type, :identity_policy, :configuration_payload,
                    :configuration_hash, 'draft', :effective_from,
                    :effective_to, :created_by
                )
            ");
            $insert->execute([
                ':profile_uid' => $profileUid,
                ':client_id' => (int)$template['client_id'],
                ':location_id' => $this->nullablePositiveInt($template['location_id'] ?? null),
                ':template_id' => (int)$template['id'],
                ':adapter_key' => $adapterKey,
                ':adapter_version' => $adapterVersion,
                ':display_name' => mb_substr($displayName, 0, 180),
                ':parser_key' => $parserKey,
                ':file_type' => strtolower((string)$template['file_type']),
                ':identity_policy' => $identityPolicy,
                ':configuration_payload' => $payload,
                ':configuration_hash' => $hash,
                ':effective_from' => $effectiveFrom,
                ':effective_to' => $effectiveTo,
                ':created_by' => $user,
            ]);
            $profileId = (int)$this->db->lastInsertId();
            $this->recordEvent(
                $profileId,
                'created',
                null,
                'draft',
                'Immutable adapter version created from template snapshot.',
                [
                    'profile_uid' => $profileUid,
                    'configuration_hash' => $hash,
                    'template_id' => (int)$template['id'],
                ],
                $user
            );
            $this->db->commit();

            return [
                'success' => 1,
                'profile_id' => $profileId,
                'profile_uid' => $profileUid,
                'profile_status' => 'draft',
                'configuration_hash' => $hash,
                'message' => 'Draft adapter created. A different Admin must approve it before real uploads.',
            ];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR adapter draft creation failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'The adapter version already exists or could not be created.'];
        }
    }

    public function approveProfile(int $profileId, string $user, string $reason): array
    {
        $reason = trim($reason);
        if ($profileId <= 0 || $reason === '') {
            return ['success' => 0, 'error' => 'Select a draft adapter and enter an approval reason.'];
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT * FROM dtr_adapter_profiles WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $profileId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$profile || !$this->canAccessClient((int)$profile['client_id'])) {
                $this->db->rollBack();
                return ['success' => 0, 'error' => 'The requested adapter profile was not found.'];
            }
            if ((string)$profile['profile_status'] !== 'draft') {
                $this->db->rollBack();
                return ['success' => 0, 'error' => 'Only a draft adapter can be approved.'];
            }
            if (strcasecmp(trim((string)$profile['created_by']), trim($user)) === 0) {
                $this->db->rollBack();
                return ['success' => 0, 'error' => 'Maker-checker control requires a different Admin to approve this adapter.'];
            }
            $payload = (string)$profile['configuration_payload'];
            if (!hash_equals((string)$profile['configuration_hash'], hash('sha256', $payload))) {
                $this->db->rollBack();
                return ['success' => 0, 'error' => 'The adapter configuration hash is invalid. Recreate the draft.'];
            }

            $overlap = $this->db->prepare("
                SELECT COUNT(*)
                FROM dtr_adapter_profiles
                WHERE id <> :id
                  AND client_id = :client_id
                  AND adapter_key = :adapter_key
                  AND profile_status = 'approved'
                  AND effective_from <= COALESCE(:effective_to_a, '9999-12-31')
                  AND COALESCE(effective_to, '9999-12-31') >= :effective_from
            ");
            $overlap->execute([
                ':id' => $profileId,
                ':client_id' => (int)$profile['client_id'],
                ':adapter_key' => (string)$profile['adapter_key'],
                ':effective_to_a' => $profile['effective_to'],
                ':effective_from' => (string)$profile['effective_from'],
            ]);
            if ((int)$overlap->fetchColumn() > 0) {
                $this->db->rollBack();
                return ['success' => 0, 'error' => 'An approved version of this adapter overlaps the selected effective dates.'];
            }

            $update = $this->db->prepare("
                UPDATE dtr_adapter_profiles
                SET profile_status = 'approved',
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    approval_reason = :approval_reason
                WHERE id = :id AND profile_status = 'draft'
            ");
            $update->execute([
                ':approved_by' => $user,
                ':approval_reason' => mb_substr($reason, 0, 1000),
                ':id' => $profileId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('The adapter status changed during approval.');
            }
            $this->recordEvent(
                $profileId,
                'approved',
                'draft',
                'approved',
                $reason,
                [
                    'configuration_hash' => (string)$profile['configuration_hash'],
                    'effective_from' => (string)$profile['effective_from'],
                    'effective_to' => $profile['effective_to'],
                    'identity_policy' => (string)$profile['identity_policy'],
                ],
                $user
            );
            $this->db->commit();
            return ['success' => 1, 'profile_id' => $profileId, 'profile_status' => 'approved'];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR adapter approval failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to approve the adapter profile.'];
        }
    }

    public function approvedProfile(int $profileId, int $clientId, string $extension, ?string $payPeriodEnd = null): ?array
    {
        if ($profileId <= 0 || $clientId <= 0 || !$this->canAccessClient($clientId)) {
            return null;
        }
        $effectiveDate = $this->validDate((string)$payPeriodEnd) ? (string)$payPeriodEnd : date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT p.*, t.source_type, t.template_name
            FROM dtr_adapter_profiles p
            INNER JOIN dtr_format_templates t ON t.id = p.template_id
            WHERE p.id = :id
              AND p.client_id = :client_id
              AND p.profile_status = 'approved'
              AND p.effective_from <= :effective_date_a
              AND (p.effective_to IS NULL OR p.effective_to >= :effective_date_b)
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $profileId,
            ':client_id' => $clientId,
            ':effective_date_a' => $effectiveDate,
            ':effective_date_b' => $effectiveDate,
        ]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile || strtolower((string)$profile['file_type']) !== strtolower($extension)) {
            return null;
        }
        if (!hash_equals(
            (string)$profile['configuration_hash'],
            hash('sha256', (string)$profile['configuration_payload'])
        )) {
            return null;
        }
        $profile['id'] = (int)$profile['id'];
        $profile['client_id'] = (int)$profile['client_id'];
        $profile['location_id'] = $profile['location_id'] === null ? null : (int)$profile['location_id'];
        $profile['template_id'] = (int)$profile['template_id'];
        $profile['configuration'] = json_decode((string)$profile['configuration_payload'], true);
        return is_array($profile['configuration']) ? $profile : null;
    }

    public function detectApprovedProfile(
        int $clientId,
        string $extension,
        array $headers,
        ?string $payPeriodEnd = null
    ): array {
        if ($clientId <= 0 || !$this->canAccessClient($clientId)) {
            return ['success' => 0, 'error' => 'Select a client you are authorized to process.'];
        }
        $effectiveDate = $this->validDate((string)$payPeriodEnd)
            ? (string)$payPeriodEnd
            : date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT p.*
            FROM dtr_adapter_profiles p
            WHERE p.client_id = :client_id
              AND p.file_type = :file_type
              AND p.profile_status = 'approved'
              AND p.effective_from <= :effective_date_a
              AND (p.effective_to IS NULL OR p.effective_to >= :effective_date_b)
            ORDER BY p.location_id IS NULL, p.id DESC
        ");
        $stmt->execute([
            ':client_id' => $clientId,
            ':file_type' => strtolower($extension),
            ':effective_date_a' => $effectiveDate,
            ':effective_date_b' => $effectiveDate,
        ]);
        $uploadedHeaders = array_values(array_unique(array_filter(array_map(
            [$this, 'normalizeHeader'],
            $headers
        ))));
        $candidates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $profile) {
            $payload = (string)$profile['configuration_payload'];
            if (!hash_equals((string)$profile['configuration_hash'], hash('sha256', $payload))) {
                continue;
            }
            $configuration = json_decode($payload, true);
            if (!is_array($configuration)) {
                continue;
            }
            $parserKey = (string)$profile['parser_key'];
            $requiredHeaders = [];
            $allMappedHeaders = [];
            foreach (($configuration['template']['fields'] ?? []) as $field) {
                $header = $this->normalizeHeader((string)($field['source_header'] ?? ''));
                if ($header === '') {
                    continue;
                }
                $allMappedHeaders[] = $header;
                if ((int)($field['is_required'] ?? 0) === 1) {
                    $requiredHeaders[] = $header;
                }
            }
            $requiredHeaders = array_values(array_unique($requiredHeaders));
            $allMappedHeaders = array_values(array_unique($allMappedHeaders));
            $requiredMatches = count(array_intersect($requiredHeaders, $uploadedHeaders));
            $mappedMatches = count(array_intersect($allMappedHeaders, $uploadedHeaders));
            $requiredCoverage = count($requiredHeaders) > 0
                ? $requiredMatches / count($requiredHeaders)
                : 0.0;
            $mappedCoverage = count($allMappedHeaders) > 0
                ? $mappedMatches / count($allMappedHeaders)
                : 0.0;
            $specializedParser = in_array(
                $parserKey,
                ['period_summary_workbook_v1', 'fuji_payroll_summary_v1'],
                true
            );
            $confidence = $parserKey === 'fuji_payroll_summary_v1'
                ? 0.60
                : ($parserKey === 'period_summary_workbook_v1'
                    ? 0.70
                    : round(($requiredCoverage * 0.85) + ($mappedCoverage * 0.15), 6));
            $eligible = $specializedParser
                || (count($requiredHeaders) > 0 && $requiredCoverage >= 1.0);
            if (!$eligible) {
                continue;
            }
            $candidates[] = [
                'profile_id' => (int)$profile['id'],
                'adapter_key' => (string)$profile['adapter_key'],
                'adapter_version' => (string)$profile['adapter_version'],
                'display_name' => (string)$profile['display_name'],
                'parser_key' => $parserKey,
                'confidence' => $confidence,
                'required_header_coverage' => round($requiredCoverage, 6),
                'mapped_header_coverage' => round($mappedCoverage, 6),
            ];
        }
        usort($candidates, static function (array $left, array $right): int {
            return $right['confidence'] <=> $left['confidence']
                ?: $right['profile_id'] <=> $left['profile_id'];
        });
        if (!$candidates) {
            return [
                'success' => 0,
                'error_code' => 'NO_APPROVED_FORMAT_MATCH',
                'error' => 'No approved client adapter matches the uploaded file structure.',
                'candidates' => [],
            ];
        }
        $top = $candidates[0];
        $secondConfidence = isset($candidates[1]) ? (float)$candidates[1]['confidence'] : -1.0;
        $isUnambiguous = count($candidates) === 1
            || ((float)$top['confidence'] >= 0.80
                && ((float)$top['confidence'] - $secondConfidence) >= 0.10);
        if (!$isUnambiguous) {
            return [
                'success' => 0,
                'error_code' => 'AMBIGUOUS_FORMAT_MATCH',
                'error' => 'More than one approved adapter could parse this file. Select the correct format explicitly.',
                'candidates' => $candidates,
            ];
        }
        return [
            'success' => 1,
            'profile_id' => (int)$top['profile_id'],
            'confidence' => (float)$top['confidence'],
            'match_method' => count($candidates) === 1
                ? 'single_effective_client_profile'
                : 'header_fingerprint',
            'candidates' => $candidates,
        ];
    }

    public static function canonicalJson($value): string
    {
        $normalized = self::canonicalize($value);
        return (string)json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private static function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonicalize'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        return $value;
    }

    private function loadTemplate(int $templateId, bool $forUpdate): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM dtr_format_templates WHERE id = :id AND is_active = 1 LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':id' => $templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            return null;
        }
        $fields = $this->db->prepare("
            SELECT source_header, canonical_field, data_type, is_required,
                   sort_order, transform_rule
            FROM dtr_format_template_fields
            WHERE template_id = :template_id
            ORDER BY sort_order, id
        ");
        $fields->execute([':template_id' => $templateId]);
        $template['fields'] = $fields->fetchAll(PDO::FETCH_ASSOC);
        return $template;
    }

    private function parserMatchesTemplate(string $parserKey, array $template): bool
    {
        $fileType = strtolower((string)($template['file_type'] ?? ''));
        if ($parserKey === 'template_tabular_v1') {
            return in_array($fileType, ['csv', 'xlsx'], true);
        }
        if ($parserKey === 'period_summary_workbook_v1') {
            return $fileType === 'xlsx'
                && strtolower((string)($template['source_type'] ?? '')) === 'period_summary_workbook';
        }
        if ($parserKey === 'fuji_payroll_summary_v1') {
            return $fileType === 'xlsx'
                && (int)($template['client_id'] ?? 0) === FujiPayrollSummaryAdapter::CLIENT_ID
                && strtolower((string)($template['source_type'] ?? '')) === 'fuji_payroll_summary';
        }
        return false;
    }

    private function recordEvent(
        int $profileId,
        string $eventType,
        ?string $previousStatus,
        string $resultingStatus,
        string $reason,
        array $evidence,
        string $user
    ): void {
        $payload = self::canonicalJson($evidence);
        $stmt = $this->db->prepare("
            INSERT INTO dtr_adapter_profile_events (
                event_uid, profile_id, event_type, previous_status,
                resulting_status, reason, evidence_payload, evidence_hash, actor
            ) VALUES (
                :event_uid, :profile_id, :event_type, :previous_status,
                :resulting_status, :reason, :evidence_payload, :evidence_hash, :actor
            )
        ");
        $stmt->execute([
            ':event_uid' => 'DTRAPE-' . strtoupper(bin2hex(random_bytes(12))),
            ':profile_id' => $profileId,
            ':event_type' => $eventType,
            ':previous_status' => $previousStatus,
            ':resulting_status' => $resultingStatus,
            ':reason' => mb_substr(trim($reason), 0, 1000),
            ':evidence_payload' => $payload,
            ':evidence_hash' => hash('sha256', $payload),
            ':actor' => $user,
        ]);
    }

    private function clientScopeSql(string $alias): array
    {
        if ($this->allow_all_clients) {
            return ['', []];
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->allowed_client_ids),
            static fn(int $id): bool => $id > 0
        )));
        if (!$ids) {
            return [' AND 1 = 0', []];
        }
        return [
            " AND {$alias}.client_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids,
        ];
    }

    private function canAccessClient(int $clientId): bool
    {
        return $clientId > 0 && (
            $this->allow_all_clients
            || in_array($clientId, array_map('intval', $this->allowed_client_ids), true)
        );
    }

    private function decodeHeaders(string $value): array
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    }

    private function normalizeKey(string $value): string
    {
        $value = strtoupper(trim($value));
        return trim((string)preg_replace('/[^A-Z0-9._-]+/', '_', $value), '_');
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim((string)preg_replace('/\s+/', ' ', $value)));
    }

    private function validDate(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date instanceof DateTimeImmutable
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    private function nullablePositiveInt($value): ?int
    {
        $value = (int)$value;
        return $value > 0 ? $value : null;
    }
}
