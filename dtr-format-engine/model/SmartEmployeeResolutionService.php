<?php

declare(strict_types=1);

require_once __DIR__ . '/EmployeeResolutionEngine.php';

/**
 * Database adapter for the pure employee resolution engine.
 *
 * The service evaluates one unique source identity at a time, keeps the
 * resolver in shadow mode, and requires an explicit owner action before any
 * reusable alias is approved. It never writes DTR or payroll tables.
 */
final class SmartEmployeeResolutionService
{
    public $db = null;

    private EmployeeResolutionEngine $engine;

    public function __construct(?EmployeeResolutionEngine $engine = null)
    {
        $this->engine = $engine ?: new EmployeeResolutionEngine();
    }

    public function previewBatch(int $batchId): array
    {
        try {
            $resolution = $this->buildResolution($batchId);
            return [
                'success' => 1,
                'batch' => $resolution['batch_public'],
                'engine_version' => $resolution['engine']['engine_version'],
                'mode' => $resolution['engine']['mode'],
                'policy' => $resolution['engine']['policy'],
                'cohort_preview' => $resolution['engine']['cohort_preview'],
                'results' => $resolution['engine']['results'],
            ];
        } catch (Throwable $error) {
            error_log('Smart employee resolution preview failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => $this->safeError($error)];
        }
    }

    /**
     * Approve only the cohort that is still safe after a fresh, transaction-
     * scoped shadow evaluation. Any conflicting alias aborts the entire cohort.
     */
    public function approveSafeCohort(int $batchId, string $reason, string $user): array
    {
        $reason = trim($reason);
        $user = trim($user);
        if ($reason === '') {
            return ['success' => 0, 'error' => 'Enter an owner approval reason for the safe cohort.'];
        }
        if ($user === '') {
            return ['success' => 0, 'error' => 'An authenticated owner is required.'];
        }

        try {
            $this->assertGovernanceTables();
            $this->db->beginTransaction();
            $resolution = $this->buildResolution($batchId, true);
            $eligible = array_values(array_filter(
                $resolution['engine']['results'],
                static function (array $result): bool {
                    return ($result['classification'] ?? '') === 'auto_eligible_shadow'
                        && (int)($result['assigned_employee_id'] ?? 0) > 0;
                }
            ));
            if (!$eligible) {
                $this->db->rollBack();
                return [
                    'success' => 0,
                    'error' => 'No collision-free shadow matches are eligible for owner approval.',
                    'cohort_preview' => $resolution['engine']['cohort_preview'],
                ];
            }

            $approved = 0;
            $alreadyApproved = 0;
            foreach ($eligible as $result) {
                $sourceKey = (string)$result['source_key'];
                $source = $resolution['source_index'][$sourceKey] ?? null;
                if (!is_array($source)) {
                    throw new RuntimeException('The shadow result no longer has a staged source identity.');
                }
                $employeeId = (int)$result['assigned_employee_id'];
                $this->assertEmployeeStillEligible(
                    $employeeId,
                    (int)$resolution['batch']['client_id'],
                    (string)$resolution['period_start'],
                    (string)$resolution['period_end']
                );

                $outcome = $this->approveAlias(
                    $resolution,
                    $source,
                    $result,
                    $employeeId,
                    $reason,
                    $user
                );
                if ($outcome === 'approved') {
                    $approved++;
                } else {
                    $alreadyApproved++;
                }
            }

            $this->db->commit();
            if (function_exists('log_action')) {
                log_action(
                    sprintf(
                        'Smart DTR identity cohort approved: batch %d, new %d, existing %d, engine %s',
                        $batchId,
                        $approved,
                        $alreadyApproved,
                        EmployeeResolutionEngine::VERSION
                    ),
                    $this->db
                );
            }

            return [
                'success' => 1,
                'batch_id' => $batchId,
                'approved_count' => $approved,
                'already_approved_count' => $alreadyApproved,
                'review_or_block_count' => count($resolution['engine']['results']) - count($eligible),
                'engine_version' => EmployeeResolutionEngine::VERSION,
                'message' => sprintf(
                    '%d safe employee mappings approved; %d were already approved.',
                    $approved,
                    $alreadyApproved
                ),
            ];
        } catch (Throwable $error) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Smart employee cohort approval failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => $this->safeError($error)];
        }
    }

    private function buildResolution(int $batchId, bool $lockBatch = false): array
    {
        if ($batchId <= 0) {
            throw new InvalidArgumentException('Select a staged DTR batch.');
        }
        $batch = $this->loadBatch($batchId, $lockBatch);
        if (!$batch) {
            throw new InvalidArgumentException('The staged DTR batch was not found.');
        }
        $clientId = (int)($batch['client_id'] ?? 0);
        if ($clientId <= 0) {
            throw new RuntimeException('The batch does not have a client-scoped template.');
        }

        $namespace = $this->sourceNamespace($batch);
        $rows = $this->loadRows($batchId);
        if (!$rows) {
            throw new RuntimeException('The batch has no staged rows to resolve.');
        }

        $periodStart = '';
        $periodEnd = '';
        $payDate = '';
        $workDates = [];
        $groups = [];
        $rowCount = 0;
        foreach ($rows as $row) {
            $parsed = $this->decodePayload((string)($row['parsed_payload'] ?? ''));
            $raw = $this->decodePayload((string)($row['raw_payload'] ?? ''));
            $rowPeriodStart = trim((string)($parsed['period_start'] ?? ''));
            $rowPeriodEnd = trim((string)($parsed['period_end'] ?? ''));
            $rowPayDate = trim((string)($parsed['pay_date'] ?? ''));
            $workDate = trim((string)($parsed['work_date'] ?? ''));
            if ($workDate !== '' && $this->isDate($workDate)) {
                $workDates[$workDate] = true;
            }
            if ($rowPeriodStart !== '') {
                $periodStart = $this->consistentValue($periodStart, $rowPeriodStart, 'period start');
            }
            if ($rowPeriodEnd !== '') {
                $periodEnd = $this->consistentValue($periodEnd, $rowPeriodEnd, 'period end');
            }
            $payDate = $this->consistentValue($payDate, $rowPayDate, 'pay date');

            $sourceId = trim((string)($parsed['employee_identifier'] ?? ''));
            $sourceName = $this->sourceName($parsed, $raw);
            $normalizedId = $this->engine->normalizeIdentifier($sourceId);
            $nameKey = $this->engine->canonicalNameKey($sourceName);
            $identityKey = $normalizedId !== '' ? 'id:' . $normalizedId : 'name:' . $nameKey;
            $sourceKey = 'source:' . hash('sha256', $namespace . '|' . $identityKey);
            if (!isset($groups[$sourceKey])) {
                $groups[$sourceKey] = [
                    'source_key' => $sourceKey,
                    'client_id' => $clientId,
                    'source_namespace' => $namespace,
                    'source_employee_id' => $sourceId,
                    'employee_name' => $sourceName,
                    'hire_date' => self::normalizeSourceDate((string)($parsed['hire_date'] ?? $parsed['hire_date_source'] ?? '')),
                    'period_start' => $rowPeriodStart,
                    'period_end' => $rowPeriodEnd,
                    'staging_row_ids' => [],
                    'source_row_numbers' => [],
                    'identity_variants' => [],
                ];
            }
            $groups[$sourceKey]['staging_row_ids'][] = (int)$row['id'];
            $groups[$sourceKey]['source_row_numbers'][] = (int)$row['source_row_number'];
            $groups[$sourceKey]['identity_variants'][] = hash('sha256', json_encode([
                $normalizedId,
                $nameKey,
                self::normalizeSourceDate((string)($parsed['hire_date'] ?? $parsed['hire_date_source'] ?? '')),
            ]));
            $rowCount++;
        }
        $workDates = array_keys($workDates);
        sort($workDates, SORT_STRING);
        if ($periodStart === '' && count($workDates) > 0) {
            $periodStart = $workDates[0];
        }
        if ($periodEnd === '' && count($workDates) > 0) {
            $periodEnd = $workDates[count($workDates) - 1];
        }
        if ($periodStart === '' || $periodEnd === '') {
            throw new RuntimeException('The staged rows do not contain a complete payroll period.');
        }
        if ($periodStart > $periodEnd) {
            throw new RuntimeException('The staged payroll period is invalid.');
        }
        foreach ($groups as &$group) {
            $group['period_start'] = $periodStart;
            $group['period_end'] = $periodEnd;
        }
        unset($group);

        $sources = [];
        $sourceIndex = [];
        foreach ($groups as $source) {
            $source['identity_variants'] = array_values(array_unique($source['identity_variants']));
            $sources[] = $source;
            $sourceIndex[$source['source_key']] = $source;
        }
        $employees = $this->loadRelevantEmployees($clientId, $sources);
        $aliases = $this->loadApprovedAliases($clientId);
        $engine = $this->engine->resolveBatch($sources, $employees, $aliases, [
            'client_id' => $clientId,
            'source_namespace' => $namespace,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ], [
            // Fuji source IDs are vendor identifiers, not governed HRIS IDs.
            // Approved aliases remain deterministic; raw direct-ID matches do not.
            'direct_identifier_matching' => (string)($batch['source_type'] ?? '') !== 'fuji_payroll_summary',
        ]);

        foreach ($engine['results'] as &$result) {
            $source = $sourceIndex[(string)$result['source_key']] ?? [];
            $preflightCode = null;
            $preflightMessage = null;
            if (trim((string)($source['source_employee_id'] ?? '')) === '') {
                $preflightCode = 'missing_reusable_source_identifier';
                $preflightMessage = 'A reusable source employee identifier is required before owner approval.';
            } elseif (count($source['identity_variants'] ?? []) > 1) {
                $preflightCode = 'source_identity_payload_conflict';
                $preflightMessage = 'Repeated staged rows disagree on name or hire date for this source identifier.';
            }
            if ($preflightCode !== null) {
                if (!in_array($preflightCode, $result['contradictions'], true)) {
                    $result['contradictions'][] = $preflightCode;
                    $result['contradiction_details'][] = [
                        'code' => $preflightCode,
                        'message' => $preflightMessage,
                        'severity' => 'hard',
                    ];
                }
                $result['classification'] = 'block';
                $result['classification_reason'] = $preflightCode;
                $result['assigned_employee_id'] = null;
            }
            $result['source_employee_id'] = (string)($source['source_employee_id'] ?? '');
            $result['source_employee_name'] = (string)($source['employee_name'] ?? '');
            $result['source_row_numbers'] = array_values($source['source_row_numbers'] ?? []);
            $result['staged_row_count'] = count($source['staging_row_ids'] ?? []);
        }
        unset($result);
        $collisionGroups = (int)($engine['cohort_preview']['collision_group_count'] ?? 0);
        $engine['cohort_preview'] = $this->engine->buildSafeCohortPreview($engine['results'], $engine['policy']);
        $engine['cohort_preview']['collision_group_count'] = $collisionGroups;
        $engine['cohort_preview']['staged_row_count'] = $rowCount;
        $engine['cohort_preview']['unique_source_count'] = count($sources);

        return [
            'batch' => $batch,
            'batch_public' => [
                'id' => (int)$batch['id'],
                'batch_uid' => (string)$batch['batch_uid'],
                'original_filename' => (string)$batch['original_filename'],
                'client_id' => $clientId,
                'client_name' => (string)($batch['client_name'] ?? ''),
                'template_name' => (string)($batch['template_name'] ?? ''),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'pay_date' => $payDate,
                'source_row_count' => $rowCount,
                'unique_source_count' => count($sources),
                'validation_status' => (string)($batch['validation_status'] ?? ''),
                'processing_status' => (string)($batch['processing_status'] ?? ''),
            ],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'pay_date' => $payDate,
            'source_namespace' => $namespace,
            'source_index' => $sourceIndex,
            'engine' => $engine,
        ];
    }

    private function approveAlias(
        array $resolution,
        array $source,
        array $result,
        int $employeeId,
        string $reason,
        string $user
    ): string {
        $clientId = (int)$resolution['batch']['client_id'];
        $namespace = (string)$resolution['source_namespace'];
        $sourceId = trim((string)$source['source_employee_id']);
        $normalizedId = $this->engine->normalizeIdentifier($sourceId);
        $periodStart = (string)$resolution['period_start'];

        $legacy = $this->db->prepare("\n            SELECT id, employee_id\n            FROM employee_identity_map\n            WHERE client_id = :client_id\n              AND source_namespace = :source_namespace\n              AND source_employee_id = :source_employee_id\n            FOR UPDATE\n        ");
        $legacy->execute([
            ':client_id' => $clientId,
            ':source_namespace' => $namespace,
            ':source_employee_id' => $sourceId,
        ]);
        $legacyRow = $legacy->fetch(PDO::FETCH_ASSOC);
        if ($legacyRow && (int)$legacyRow['employee_id'] !== $employeeId) {
            throw new DomainException('An existing approved source alias points to a different employee. No mappings were changed.');
        }

        $active = $this->db->prepare("\n            SELECT id, employee_id, effective_from, effective_to\n            FROM employee_identity_aliases\n            WHERE client_id = :client_id\n              AND source_namespace = :source_namespace\n              AND normalized_source_employee_id = :normalized_source_employee_id\n              AND alias_status = 'active'\n              AND effective_from <= :period_end\n              AND (effective_to IS NULL OR effective_to >= :period_start)\n            ORDER BY version_no DESC\n            FOR UPDATE\n        ");
        $active->execute([
            ':client_id' => $clientId,
            ':source_namespace' => $namespace,
            ':normalized_source_employee_id' => $normalizedId,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd,
        ]);
        $activeRows = $active->fetchAll(PDO::FETCH_ASSOC);
        foreach ($activeRows as $activeRow) {
            if ((int)$activeRow['employee_id'] !== $employeeId) {
                throw new DomainException('A period-effective alias already points to another employee. No mappings were changed.');
            }
        }

        if (!$activeRows) {
            $decisionUid = $this->uid('IDDEC');
            $decision = $this->db->prepare("\n                INSERT INTO employee_identity_decisions (\n                    decision_uid, client_id, source_namespace, source_employee_id,\n                    normalized_source_employee_id, employee_id, decision_type,\n                    decision_status, confidence_score, evidence_payload, reason, decided_by\n                ) VALUES (\n                    :decision_uid, :client_id, :source_namespace, :source_employee_id,\n                    :normalized_source_employee_id, :employee_id, 'safe_cohort_owner_approval',\n                    'approved', :confidence_score, :evidence_payload, :reason, :decided_by\n                )\n            ");
            $decision->execute([
                ':decision_uid' => $decisionUid,
                ':client_id' => $clientId,
                ':source_namespace' => $namespace,
                ':source_employee_id' => $sourceId,
                ':normalized_source_employee_id' => $normalizedId,
                ':employee_id' => $employeeId,
                ':confidence_score' => round(((float)$result['top_score']) / 100, 6),
                ':evidence_payload' => json_encode([
                    'engine_version' => EmployeeResolutionEngine::VERSION,
                    'classification' => $result['classification'],
                    'classification_reason' => $result['classification_reason'],
                    'match_basis' => $result['match_basis'],
                    'top_score' => $result['top_score'],
                    'second_score' => $result['second_score'],
                    'margin' => $result['margin'],
                    'explanations' => $result['explanations'],
                    'warnings' => $result['warnings'],
                    'batch_id' => (int)$resolution['batch']['id'],
                    'period_start' => $resolution['period_start'],
                    'period_end' => $resolution['period_end'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':reason' => $reason,
                ':decided_by' => $user,
            ]);
            $decisionId = (int)$this->db->lastInsertId();

            $version = $this->db->prepare("\n                SELECT COALESCE(MAX(version_no), 0) + 1\n                FROM employee_identity_aliases\n                WHERE client_id = :client_id\n                  AND source_namespace = :source_namespace\n                  AND normalized_source_employee_id = :normalized_source_employee_id\n            ");
            $version->execute([
                ':client_id' => $clientId,
                ':source_namespace' => $namespace,
                ':normalized_source_employee_id' => $normalizedId,
            ]);
            $versionNo = (int)$version->fetchColumn();

            $alias = $this->db->prepare("\n                INSERT INTO employee_identity_aliases (\n                    alias_uid, client_id, source_namespace, source_employee_id,\n                    normalized_source_employee_id, employee_id, decision_id, version_no,\n                    alias_status, effective_from, effective_to, created_by\n                ) VALUES (\n                    :alias_uid, :client_id, :source_namespace, :source_employee_id,\n                    :normalized_source_employee_id, :employee_id, :decision_id, :version_no,\n                    'active', :effective_from, NULL, :created_by\n                )\n            ");
            $alias->execute([
                ':alias_uid' => $this->uid('IDALIAS'),
                ':client_id' => $clientId,
                ':source_namespace' => $namespace,
                ':source_employee_id' => $sourceId,
                ':normalized_source_employee_id' => $normalizedId,
                ':employee_id' => $employeeId,
                ':decision_id' => $decisionId,
                ':version_no' => $versionNo,
                ':effective_from' => $periodStart,
                ':created_by' => $user,
            ]);
        }

        $cache = $this->db->prepare("\n            INSERT INTO employee_identity_map (\n                client_id, source_namespace, source_employee_id, employee_id,\n                status, approved_by, approved_at, effective_from, effective_to\n            ) VALUES (\n                :client_id, :source_namespace, :source_employee_id, :employee_id,\n                'approved', :approved_by, NOW(), :effective_from, NULL\n            )\n            ON DUPLICATE KEY UPDATE\n                employee_id = VALUES(employee_id),\n                status = 'approved',\n                approved_by = VALUES(approved_by),\n                approved_at = NOW(),\n                effective_from = LEAST(COALESCE(effective_from, VALUES(effective_from)), VALUES(effective_from)),\n                effective_to = NULL\n        ");
        $cache->execute([
            ':client_id' => $clientId,
            ':source_namespace' => $namespace,
            ':source_employee_id' => $sourceId,
            ':employee_id' => $employeeId,
            ':approved_by' => $user,
            ':effective_from' => $periodStart,
        ]);
        return $activeRows ? 'already_approved' : 'approved';
    }

    private function assertEmployeeStillEligible(int $employeeId, int $clientId, string $periodStart, string $periodEnd): void
    {
        $stmt = $this->db->prepare("\n            SELECT employee_id, client_id, status, hire_date, separation_date\n            FROM employee_list\n            WHERE employee_id = :employee_id\n            FOR UPDATE\n        ");
        $stmt->execute([':employee_id' => $employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            throw new DomainException('A proposed employee is missing or belongs to another client. No mappings were changed.');
        }
        $hireDate = trim((string)($employee['hire_date'] ?? ''));
        $separationDate = trim((string)($employee['separation_date'] ?? ''));
        $status = strtolower(trim((string)($employee['status'] ?? '')));
        $activeForPeriod = ($hireDate === '' || $hireDate === '0000-00-00' || $hireDate <= $periodEnd)
            && ($separationDate === '' || $separationDate === '0000-00-00' || $separationDate >= $periodStart)
            && ($status === 'active' || ($status === 'terminated' && $separationDate >= $periodStart));
        if (!$activeForPeriod) {
            throw new DomainException('A proposed employee is not active for the payroll period. No mappings were changed.');
        }
    }

    private function loadBatch(int $batchId, bool $forUpdate): ?array
    {
        $stmt = $this->db->prepare("\n            SELECT b.*, t.client_id, t.location_id, t.template_name, t.source_type, c.client_name\n            FROM dtr_upload_batches b\n            LEFT JOIN dtr_format_templates t ON t.id = b.template_id\n            LEFT JOIN taascor_client c ON c.client_id = t.client_id\n            WHERE b.id = :batch_id\n            LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':batch_id' => $batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function loadRows(int $batchId): array
    {
        $stmt = $this->db->prepare("\n            SELECT id, source_row_number, raw_payload, parsed_payload\n            FROM dtr_upload_staging_rows\n            WHERE batch_id = :batch_id\n              AND validation_status <> 'excluded'\n            ORDER BY source_row_number, id\n        ");
        $stmt->execute([':batch_id' => $batchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadRelevantEmployees(int $clientId, array $sources): array
    {
        $identifiers = [];
        foreach ($sources as $source) {
            $identifier = trim((string)($source['source_employee_id'] ?? ''));
            if ($identifier !== '') {
                $identifiers[$identifier] = true;
            }
        }
        $params = [':client_id' => $clientId];
        $where = ['client_id = :client_id'];
        if ($identifiers) {
            $employeePlaceholders = [];
            $payrollPlaceholders = [];
            $oldPlaceholders = [];
            foreach (array_keys($identifiers) as $index => $identifier) {
                $employeeKey = ':employee_identifier_' . $index;
                $payrollKey = ':payroll_identifier_' . $index;
                $oldKey = ':old_identifier_' . $index;
                $employeePlaceholders[] = $employeeKey;
                $payrollPlaceholders[] = $payrollKey;
                $oldPlaceholders[] = $oldKey;
                $params[$employeeKey] = $identifier;
                $params[$payrollKey] = $identifier;
                $params[$oldKey] = $identifier;
            }
            $where[] = 'CAST(employee_id AS CHAR) IN (' . implode(',', $employeePlaceholders) . ')';
            $where[] = 'payroll_employee_id IN (' . implode(',', $payrollPlaceholders) . ')';
            $where[] = 'old_employee_id IN (' . implode(',', $oldPlaceholders) . ')';
        }
        $stmt = $this->db->prepare("\n            SELECT employee_id, client_id, status, payroll_employee_id, old_employee_id,\n                   first_name, middle_name, last_name, full_name, hire_date, separation_date\n            FROM employee_list\n            WHERE " . implode(' OR ', $where) . "\n            ORDER BY employee_id\n        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadApprovedAliases(int $clientId): array
    {
        $aliases = [];
        $legacy = $this->db->prepare("\n            SELECT client_id, source_namespace, source_employee_id, employee_id,\n                   status, effective_from, effective_to\n            FROM employee_identity_map\n            WHERE client_id = :client_id AND status = 'approved'\n        ");
        $legacy->execute([':client_id' => $clientId]);
        foreach ($legacy->fetchAll(PDO::FETCH_ASSOC) as $alias) {
            $aliases[] = $alias;
        }
        if ($this->tableExists('employee_identity_aliases')) {
            $versioned = $this->db->prepare("\n                SELECT client_id, source_namespace, source_employee_id, employee_id,\n                       'approved' AS status, effective_from, effective_to\n                FROM employee_identity_aliases\n                WHERE client_id = :client_id AND alias_status = 'active'\n            ");
            $versioned->execute([':client_id' => $clientId]);
            foreach ($versioned->fetchAll(PDO::FETCH_ASSOC) as $alias) {
                $aliases[] = $alias;
            }
        }
        return $aliases;
    }

    private function assertGovernanceTables(): void
    {
        foreach (['employee_identity_decisions', 'employee_identity_aliases'] as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException('Run the payroll import foundation migration before approving smart identity mappings.');
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("\n            SELECT COUNT(*)\n            FROM INFORMATION_SCHEMA.TABLES\n            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name\n        ");
        $stmt->execute([':table_name' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function consistentValue(string $current, string $candidate, string $label): string
    {
        if ($candidate === '') {
            return $current;
        }
        if ($current !== '' && $current !== $candidate) {
            throw new DomainException('The staged batch contains mixed ' . $label . ' values.');
        }
        return $candidate;
    }

    /** Normalize common workbook dates, including Excel's 1900 date system. */
    public static function normalizeSourceDate(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        if (is_numeric($value)) {
            $serial = (float)$value;
            if ($serial >= 1 && $serial <= 80000) {
                $days = (int)floor($serial);
                return (new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC')))
                    ->modify('+' . $days . ' days')
                    ->format('Y-m-d');
            }
        }
        foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y', '!Y/m/d', '!d-M-Y', '!d M Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date instanceof DateTimeImmutable
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        return '';
    }

    private function sourceName(array $parsed, array $raw): string
    {
        foreach (['employee_name', 'employee_name_source', 'source_employee_name'] as $key) {
            $value = trim((string)($parsed[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        foreach ($raw as $key => $value) {
            $normalized = strtolower((string)preg_replace('/[^a-z0-9]+/i', '_', (string)$key));
            if (strpos($normalized, 'employee') !== false && strpos($normalized, 'name') !== false) {
                return trim((string)$value);
            }
        }
        return '';
    }

    private function sourceNamespace(array $batch): string
    {
        $templateId = (int)($batch['template_id'] ?? 0);
        return $templateId > 0
            ? 'template:' . $templateId
            : 'context:' . trim((string)($batch['source_context'] ?? 'dtr'));
    }

    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function uid(string $prefix): string
    {
        return $prefix . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(12));
    }

    private function safeError(Throwable $error): string
    {
        if ($error instanceof InvalidArgumentException || $error instanceof DomainException) {
            return $error->getMessage();
        }
        $message = $error->getMessage();
        if (strpos($message, 'foundation migration') !== false) {
            return $message;
        }
        return 'Unable to evaluate or approve the smart employee cohort.';
    }
}
