<?php
class AuditLog
{
    public $db        = null;
    public $username  = null;
    public $action    = null;
    public $date_from = null;
    public $date_to   = null;
    public $event_uid = null;
    public $client_name = null;
    public $pay_day = null;
    public $adjustment_kind = null;
    public $operation = null;
    public $employee_id = null;
    public $scope_kind = null;
    public $page = 1;
    public $page_size = 25;

    private const DTR_EVENT_PATTERN = '/^DTRM-[A-F0-9]{32}$/';
    private const DTR_MAX_PAGE = 10000;
    private const DTR_MAX_PAGE_SIZE = 100;
    private const DTR_MAX_PAYLOAD_BYTES = 524288;
    private const DTR_MAX_SCOPE_BYTES = 131072;
    private const DTR_MAX_TEXT_BYTES = 8192;
    private const DTR_MAX_JSON_DEPTH = 64;

    // ── Read logs with filters ─────────────────────────────────────────────
    public function getLogs()
    {
        $response = [];
        try {
            $where  = [];
            $params = [];

            if ($this->username && $this->username !== '') {
                $where[]              = "username LIKE :username";
                $params[':username']  = '%' . $this->username . '%';
            }
            if ($this->action && $this->action !== '') {
                $where[]            = "log_action = :action";
                $params[':action']  = $this->action;
            }
            if ($this->date_from && $this->date_from !== '') {
                $where[]                = "DATE(inserted_date_time_ph) >= :date_from";
                $params[':date_from']   = $this->date_from;
            }
            if ($this->date_to && $this->date_to !== '') {
                $where[]              = "DATE(inserted_date_time_ph) <= :date_to";
                $params[':date_to']   = $this->date_to;
            }

            $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "SELECT id, username, log_action, inserted_date_time_ph
                    FROM logs
                    $whereSQL
                    ORDER BY inserted_date_time_ph DESC
                    LIMIT 2000";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();

            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['total']   = count($response['data']);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Login activity summary per user ───────────────────────────────────
    public function getLoginSummary()
    {
        $response = [];
        try {
            $sql = "SELECT
                        username,
                        COUNT(*)                                AS total_logins,
                        MAX(inserted_date_time_ph)              AS last_login,
                        MIN(inserted_date_time_ph)              AS first_login,
                        SUM(CASE WHEN DATE(inserted_date_time_ph) = CURDATE() THEN 1 ELSE 0 END) AS logins_today
                    FROM logs
                    WHERE log_action = 'Login'
                    GROUP BY username
                    ORDER BY last_login DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    // ── Distinct action types for filter dropdown ──────────────────────────
    public function getActionTypes()
    {
        $response = [];
        try {
            $stmt = $this->db->query("SELECT DISTINCT log_action FROM logs ORDER BY log_action");
            $response['success'] = 1;
            $response['data']    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error']   = 'An error occurred. Please contact your administrator.';
        }
        return $response;
    }

    public function getPayrollAdjustmentEvents(): array
    {
        try {
            if (!$this->tableExists('payroll_adjustment_audit_events')) {
                return [
                    'success' => 0,
                    'error_code' => 'payroll_adjustment_audit_schema_missing',
                    'error' => 'Payroll adjustment evidence is unavailable until the required audit migration is applied.',
                    'data' => [],
                ];
            }

            $where = [];
            $params = [];
            $eventUid = strtoupper(trim((string)$this->event_uid));
            if ($eventUid !== '') {
                if (!preg_match('/^[A-F0-9]{24}$/', $eventUid)) {
                    return ['success' => 0, 'error' => 'The audit event identifier is invalid.', 'data' => []];
                }
                $where[] = 'event_uid = :event_uid';
                $params[':event_uid'] = $eventUid;
            }
            $clientName = trim((string)$this->client_name);
            if ($clientName !== '') {
                $where[] = 'client_name LIKE :client_name';
                $params[':client_name'] = '%' . mb_substr($clientName, 0, 190) . '%';
            }
            $payDay = trim((string)$this->pay_day);
            if ($payDay !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $payDay);
                if (!$date || $date->format('Y-m-d') !== $payDay) {
                    return ['success' => 0, 'error' => 'The pay date is invalid.', 'data' => []];
                }
                $where[] = 'pay_day = :pay_day';
                $params[':pay_day'] = $payDay;
            }
            $kind = strtolower(trim((string)$this->adjustment_kind));
            if ($kind !== '') {
                if (!in_array($kind, ['addition', 'deduction'], true)) {
                    return ['success' => 0, 'error' => 'The adjustment kind is invalid.', 'data' => []];
                }
                $where[] = 'adjustment_kind = :adjustment_kind';
                $params[':adjustment_kind'] = $kind;
            }
            $operation = strtoupper(trim((string)$this->operation));
            if ($operation !== '') {
                if (!preg_match('/^[A-Z0-9_]{3,40}$/', $operation)) {
                    return ['success' => 0, 'error' => 'The operation filter is invalid.', 'data' => []];
                }
                $where[] = 'operation = :operation';
                $params[':operation'] = $operation;
            }

            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $this->db->prepare(
                "SELECT event_uid, adjustment_kind, operation, client_name, cut_off,
                        period_start, period_end, pay_day, change_reason,
                        evidence_reference, source_filename, row_count,
                        payload_hash, actor, created_at
                 FROM payroll_adjustment_audit_events
                 {$whereSql}
                 ORDER BY created_at DESC, id DESC
                 LIMIT 500"
            );
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['success' => 1, 'data' => $data, 'total' => count($data)];
        } catch (Throwable $error) {
            return ['success' => 0, 'error' => 'Payroll adjustment evidence could not be loaded.', 'data' => []];
        }
    }

    public function getPayrollAdjustmentAuditEvent(string $eventUid): array
    {
        return $this->getPayrollAdjustmentEvent($eventUid);
    }

    public function getPayrollAdjustmentEvent(string $eventUid): array
    {
        try {
            if (!$this->tableExists('payroll_adjustment_audit_events')) {
                return [
                    'success' => 0,
                    'error_code' => 'payroll_adjustment_audit_schema_missing',
                    'error' => 'Payroll adjustment evidence is unavailable until the required audit migration is applied.',
                ];
            }

            $eventUid = strtoupper(trim($eventUid));
            if (!preg_match('/^[A-F0-9]{24}$/', $eventUid)) {
                return ['success' => 0, 'error' => 'The audit event identifier is invalid.'];
            }

            $stmt = $this->db->prepare(
                "SELECT event_uid, adjustment_kind, operation, client_name, cut_off,
                        period_start, period_end, pay_day, change_reason,
                        evidence_reference, source_filename, row_count, rows_payload,
                        payload_hash, actor, created_at
                 FROM payroll_adjustment_audit_events
                 WHERE event_uid = :event_uid
                 LIMIT 1"
            );
            $stmt->execute([':event_uid' => $eventUid]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($event)) {
                return ['success' => 0, 'error' => 'The payroll adjustment audit event was not found.'];
            }

            $rows = json_decode((string)$event['rows_payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows) || count($rows) !== (int)$event['row_count']) {
                return ['success' => 0, 'error' => 'The audit event row evidence is invalid.'];
            }
            $canonical = json_encode([
                'adjustment_kind' => (string)$event['adjustment_kind'],
                'operation' => (string)$event['operation'],
                'client_name' => (string)$event['client_name'],
                'cut_off' => (string)$event['cut_off'],
                'start_date' => (string)$event['period_start'],
                'end_date' => (string)$event['period_end'],
                'pay_day' => (string)$event['pay_day'],
                'change_reason' => (string)$event['change_reason'],
                'evidence_reference' => (string)$event['evidence_reference'],
                'source_filename' => (string)($event['source_filename'] ?? ''),
                'actor' => (string)$event['actor'],
                'rows' => $rows,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            unset($event['rows_payload']);
            $event['rows'] = $rows;
            $event['hash_valid'] = hash_equals(
                strtolower((string)$event['payload_hash']),
                hash('sha256', $canonical)
            );

            return ['success' => 1, 'data' => $event];
        } catch (Throwable $error) {
            return ['success' => 0, 'error' => 'Payroll adjustment evidence could not be verified.'];
        }
    }

    public function getDtrMutationEvents(): array
    {
        try {
            if (!$this->tableExists('dtr_mutation_audit_events')) {
                return [
                    'success' => 0,
                    'error_code' => 'dtr_mutation_audit_schema_missing',
                    'error' => 'DTR change evidence is unavailable until the required audit migration is applied.',
                    'data' => [],
                ];
            }

            $page = $this->validatedPositiveInteger($this->page, self::DTR_MAX_PAGE, 'page');
            $pageSize = $this->validatedPositiveInteger(
                $this->page_size,
                self::DTR_MAX_PAGE_SIZE,
                'page size'
            );
            $columns = $this->tableColumns('dtr_mutation_audit_events');
            $scopeSchema = $this->dtrScopeSchemaState($columns);
            if ($scopeSchema === 'partial') {
                return [
                    'success' => 0,
                    'error_code' => 'dtr_mutation_audit_scope_schema_incomplete',
                    'error' => 'DTR scope evidence is incomplete. Apply the complete audit migration before review.',
                    'data' => [],
                ];
            }

            $where = [];
            $params = [];
            $eventUid = strtoupper(trim((string)$this->event_uid));
            if ($eventUid !== '') {
                if (preg_match(self::DTR_EVENT_PATTERN, $eventUid) !== 1) {
                    return ['success' => 0, 'error' => 'The DTR audit event identifier is invalid.', 'data' => []];
                }
                $where[] = 'event_uid = :event_uid';
                $params[':event_uid'] = $eventUid;
            }

            $clientName = trim((string)$this->client_name);
            if ($clientName !== '') {
                $where[] = 'client_name LIKE :client_name';
                $params[':client_name'] = '%' . $this->boundedText($clientName, 190) . '%';
            }

            $payDay = trim((string)$this->pay_day);
            if ($payDay !== '') {
                if (!$this->isIsoDate($payDay)) {
                    return ['success' => 0, 'error' => 'The DTR pay date is invalid.', 'data' => []];
                }
                $where[] = 'pay_day = :pay_day';
                $params[':pay_day'] = $payDay;
            }

            $operation = strtoupper(trim((string)$this->operation));
            if ($operation !== '') {
                if (!in_array($operation, ['EDIT', 'BENEFIT', 'DELETE_EMPLOYEE', 'DELETE_BULK'], true)) {
                    return ['success' => 0, 'error' => 'The DTR operation filter is invalid.', 'data' => []];
                }
                $where[] = 'operation = :operation';
                $params[':operation'] = $operation;
            }

            $employeeId = trim((string)$this->employee_id);
            if ($employeeId !== '') {
                if (preg_match('/^[1-9][0-9]{0,18}$/', $employeeId) !== 1) {
                    return ['success' => 0, 'error' => 'The employee identifier is invalid.', 'data' => []];
                }
                $where[] = 'employee_id = :employee_id';
                $params[':employee_id'] = (int)$employeeId;
            }

            $scopeKind = strtoupper(trim((string)$this->scope_kind));
            if ($scopeKind !== '') {
                if ($scopeSchema !== 'complete') {
                    return [
                        'success' => 0,
                        'error_code' => 'dtr_mutation_audit_scope_filter_unavailable',
                        'error' => 'Scope filtering requires the current DTR audit migration.',
                        'data' => [],
                    ];
                }
                if (!in_array($scopeKind, ['EMPLOYEE', 'PAYROLL_SCOPE'], true)) {
                    return ['success' => 0, 'error' => 'The DTR scope filter is invalid.', 'data' => []];
                }
                $where[] = 'scope_kind = :scope_kind';
                $params[':scope_kind'] = $scopeKind;
            }

            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $countStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM dtr_mutation_audit_events {$whereSql}"
            );
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $scopeColumns = $scopeSchema === 'complete'
                ? 'scope_kind, branch_id, client_location_id'
                : 'NULL AS scope_kind, NULL AS branch_id, NULL AS client_location_id';
            $offset = ($page - 1) * $pageSize;
            $listStmt = $this->db->prepare(
                "SELECT event_uid, operation, {$scopeColumns}, employee_id,
                        client_name, cut_off, period_start, period_end, pay_day,
                        actor, calculator_routine, calculator_hash, created_at
                 FROM dtr_mutation_audit_events
                 {$whereSql}
                 ORDER BY created_at DESC, id DESC
                 LIMIT :page_size OFFSET :page_offset"
            );
            foreach ($params as $name => $value) {
                $listStmt->bindValue(
                    $name,
                    $value,
                    is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
                );
            }
            $listStmt->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
            $listStmt->bindValue(':page_offset', $offset, PDO::PARAM_INT);
            $listStmt->execute();
            $data = $listStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => 1,
                'data' => $data,
                'total' => $total,
                'returned' => count($data),
                'page' => $page,
                'page_size' => $pageSize,
                'has_more' => ($offset + count($data)) < $total,
                'scope_evidence_supported' => $scopeSchema === 'complete',
            ];
        } catch (InvalidArgumentException $error) {
            return ['success' => 0, 'error' => $error->getMessage(), 'data' => []];
        } catch (Throwable $error) {
            return [
                'success' => 0,
                'error' => 'DTR change evidence could not be loaded.',
                'data' => [],
            ];
        }
    }

    public function getDtrMutationAuditEvent(string $eventUid): array
    {
        return $this->getDtrMutationEvent($eventUid);
    }

    public function getDtrMutationEvent(string $eventUid): array
    {
        try {
            if (!$this->tableExists('dtr_mutation_audit_events')) {
                return [
                    'success' => 0,
                    'error_code' => 'dtr_mutation_audit_schema_missing',
                    'error' => 'DTR change evidence is unavailable until the required audit migration is applied.',
                ];
            }

            $eventUid = strtoupper(trim($eventUid));
            if (preg_match(self::DTR_EVENT_PATTERN, $eventUid) !== 1) {
                return ['success' => 0, 'error' => 'The DTR audit event identifier is invalid.'];
            }

            $columns = $this->tableColumns('dtr_mutation_audit_events');
            $scopeSchema = $this->dtrScopeSchemaState($columns);
            if ($scopeSchema === 'partial') {
                return [
                    'success' => 0,
                    'error_code' => 'dtr_mutation_audit_scope_schema_incomplete',
                    'error' => 'DTR scope evidence is incomplete. Apply the complete audit migration before review.',
                ];
            }
            $hasScope = $scopeSchema === 'complete';
            $scopeMeta = $hasScope
                ? 'scope_kind, branch_id, client_location_id, scope_hash, '
                    . 'OCTET_LENGTH(scope_payload) AS scope_payload_bytes'
                : 'NULL AS scope_kind, NULL AS branch_id, NULL AS client_location_id, '
                    . 'NULL AS scope_hash, 0 AS scope_payload_bytes';

            $metaStmt = $this->db->prepare(
                "SELECT event_uid, operation, {$scopeMeta}, employee_id,
                        client_name, cut_off, period_start, period_end, pay_day,
                        before_hash, after_hash, calculator_routine, calculator_hash,
                        actor, created_at,
                        OCTET_LENGTH(change_reason) AS change_reason_bytes,
                        OCTET_LENGTH(evidence_reference) AS evidence_reference_bytes,
                        OCTET_LENGTH(before_payload) AS before_payload_bytes,
                        OCTET_LENGTH(after_payload) AS after_payload_bytes
                 FROM dtr_mutation_audit_events
                 WHERE event_uid = :event_uid
                 LIMIT 1"
            );
            $metaStmt->execute([':event_uid' => $eventUid]);
            $event = $metaStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($event)) {
                return ['success' => 0, 'error' => 'The DTR change audit event was not found.'];
            }

            if (
                (int)$event['before_payload_bytes'] > self::DTR_MAX_PAYLOAD_BYTES
                || (int)$event['after_payload_bytes'] > self::DTR_MAX_PAYLOAD_BYTES
                || (int)$event['scope_payload_bytes'] > self::DTR_MAX_SCOPE_BYTES
                || (int)$event['change_reason_bytes'] > self::DTR_MAX_TEXT_BYTES
                || (int)$event['evidence_reference_bytes'] > self::DTR_MAX_TEXT_BYTES
            ) {
                return [
                    'success' => 0,
                    'error_code' => 'dtr_mutation_audit_detail_too_large',
                    'error' => 'This audit event exceeds the safe evidence-view limit and was not opened.',
                ];
            }

            $scopePayloadSelect = $hasScope ? 'scope_payload' : 'NULL AS scope_payload';
            $payloadStmt = $this->db->prepare(
                "SELECT change_reason, evidence_reference, before_payload,
                        after_payload, {$scopePayloadSelect}
                 FROM dtr_mutation_audit_events
                 WHERE event_uid = :event_uid
                 LIMIT 1"
            );
            $payloadStmt->execute([':event_uid' => $eventUid]);
            $payloads = $payloadStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($payloads)) {
                return ['success' => 0, 'error' => 'The DTR change evidence payload was not found.'];
            }

            $before = $this->decodeDtrEvidencePayload(
                (string)$payloads['before_payload'],
                self::DTR_MAX_PAYLOAD_BYTES,
                'before'
            );
            $after = $this->decodeDtrEvidencePayload(
                (string)$payloads['after_payload'],
                self::DTR_MAX_PAYLOAD_BYTES,
                'after'
            );
            $scope = null;
            $scopeHashValid = null;
            $scopePresent = $hasScope && $payloads['scope_payload'] !== null;
            if ($scopePresent) {
                $scope = $this->decodeDtrEvidencePayload(
                    (string)$payloads['scope_payload'],
                    self::DTR_MAX_SCOPE_BYTES,
                    'scope'
                );
                $scopeHashValid = $this->storedCanonicalHashIsValid(
                    (string)$payloads['scope_payload'],
                    $scope,
                    (string)$event['scope_hash']
                );
            }

            $beforeHashValid = $this->storedCanonicalHashIsValid(
                (string)$payloads['before_payload'],
                $before,
                (string)$event['before_hash']
            );
            $afterHashValid = $this->storedCanonicalHashIsValid(
                (string)$payloads['after_payload'],
                $after,
                (string)$event['after_hash']
            );
            $hashValid = $beforeHashValid
                && $afterHashValid
                && ($scopeHashValid === null || $scopeHashValid);

            unset(
                $event['change_reason_bytes'],
                $event['evidence_reference_bytes'],
                $event['scope_payload_bytes']
            );
            $event['change_reason'] = (string)$payloads['change_reason'];
            $event['evidence_reference'] = (string)$payloads['evidence_reference'];
            $event['before'] = $before;
            $event['after'] = $after;
            $event['scope'] = $scope;
            $event['scope_evidence_supported'] = $hasScope;
            $event['scope_evidence_present'] = $scopePresent;
            $event['before_hash_valid'] = $beforeHashValid;
            $event['after_hash_valid'] = $afterHashValid;
            $event['scope_hash_valid'] = $scopeHashValid;
            $event['hash_valid'] = $hashValid;
            $event['integrity_status'] = $hashValid ? 'verified' : 'hash_mismatch';

            return ['success' => 1, 'data' => $event];
        } catch (LengthException $error) {
            return [
                'success' => 0,
                'error_code' => 'dtr_mutation_audit_detail_too_large',
                'error' => 'This audit event exceeds the safe evidence-view limit and was not opened.',
            ];
        } catch (JsonException $error) {
            return [
                'success' => 0,
                'error_code' => 'dtr_mutation_audit_payload_invalid',
                'error' => 'The DTR audit evidence contains invalid canonical JSON.',
            ];
        } catch (Throwable $error) {
            return [
                'success' => 0,
                'error' => 'DTR change evidence could not be verified.',
            ];
        }
    }

    private function validatedPositiveInteger($value, int $maximum, string $label): int
    {
        $raw = trim((string)$value);
        if (preg_match('/^[1-9][0-9]*$/', $raw) !== 1) {
            throw new InvalidArgumentException("The DTR {$label} is invalid.");
        }
        $number = (int)$raw;
        if ($number < 1 || $number > $maximum) {
            throw new InvalidArgumentException("The DTR {$label} exceeds the allowed range.");
        }
        return $number;
    }

    private function boundedText(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length)
            : substr($value, 0, $length);
    }

    private function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function dtrScopeSchemaState(array $columns): string
    {
        $scopeColumns = [
            'scope_kind',
            'branch_id',
            'client_location_id',
            'scope_payload',
            'scope_hash',
        ];
        $present = 0;
        foreach ($scopeColumns as $column) {
            if (isset($columns[$column])) {
                $present++;
            }
        }
        if ($present === 0) {
            return 'legacy';
        }
        return $present === count($scopeColumns) ? 'complete' : 'partial';
    }

    private function decodeDtrEvidencePayload(string $payload, int $maximumBytes, string $label): array
    {
        if (strlen($payload) > $maximumBytes) {
            throw new LengthException("The DTR {$label} evidence exceeds the safe view limit.");
        }
        $decoded = json_decode(
            $payload,
            true,
            self::DTR_MAX_JSON_DEPTH,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded)) {
            throw new JsonException("The DTR {$label} evidence must be a JSON object or list.");
        }
        return $decoded;
    }

    private function storedCanonicalHashIsValid(
        string $storedPayload,
        array $decodedPayload,
        string $expectedHash
    ): bool
    {
        $expectedHash = strtolower(trim($expectedHash));
        if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
            return false;
        }
        $canonicalPayload = $this->canonicalDtrEvidenceJson($decodedPayload);
        if (!hash_equals($canonicalPayload, $storedPayload)) {
            return false;
        }
        return hash_equals($expectedHash, hash('sha256', $storedPayload));
    }

    private function canonicalDtrEvidenceJson(array $payload): string
    {
        $normalize = static function ($value) use (&$normalize) {
            if (!is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                return array_map($normalize, $value);
            }
            ksort($value, SORT_STRING);
            foreach ($value as $key => $child) {
                $value[$key] = $normalize($child);
            }
            return $value;
        };
        return json_encode(
            $normalize($payload),
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
        );
    }

    private function tableColumns(string $table): array
    {
        $stmt = $this->db->prepare(
            'SELECT COLUMN_NAME
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $table]);
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $columns[strtolower((string)$column)] = true;
        }
        return $columns;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $table]);
        return (int)$stmt->fetchColumn() === 1;
    }

    // ── Write a new log entry (called by other controllers) ───────────────
    public static function write($db, string $username, string $action): void
    {
        try {
            $sql  = "INSERT INTO logs (username, log_action, inserted_date_time_ph)
                     VALUES (:username, :action, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':action',   $action);
            $stmt->execute();
        } catch (\Throwable $ignored) {}
    }
}
