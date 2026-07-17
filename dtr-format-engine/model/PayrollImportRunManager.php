<?php

declare(strict_types=1);

require_once __DIR__ . '/PayrollLegacyScopeHasher.php';
require_once __DIR__ . '/PayslipArtifactStore.php';

/**
 * Owns the immutable boundary between DTR staging and a candidate payroll run.
 *
 * This class deliberately writes only payroll_import_* tables (and the new
 * versioned identity decision/alias tables). It never writes legacy DTR,
 * payroll, contribution, loan, or payslip tables.
 */
class PayrollImportRunManager
{
    public $db = null;

    public const RUN_STATES = [
        'draft',
        'canonicalized',
        'validating',
        'ready_for_approval',
        'approved',
        'released',
        'failed',
        'cancelled',
    ];

    private const STATE_TRANSITIONS = [
        'draft' => ['canonicalized', 'failed', 'cancelled'],
        'canonicalized' => ['validating', 'failed', 'cancelled'],
        'validating' => ['ready_for_approval', 'failed', 'cancelled'],
        'ready_for_approval' => ['approved', 'failed', 'cancelled'],
        'approved' => ['released', 'cancelled'],
        'released' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    private const DEFAULT_RELEASE_CHECKS = [
        ['IDENTITY_RESOLUTION', 'identity', 1],
        ['CANONICAL_ROW_INTEGRITY', 'dtr', 1],
        ['RULE_VERSION_LOCK', 'rules', 1],
        ['PAYROLL_CALCULATION', 'calculation', 1],
        ['PAYROLL_RECONCILIATION', 'reconciliation', 1],
        ['LEGACY_SCOPE_BINDING', 'reconciliation', 1],
        ['PAYSLIP_ARTIFACT_COVERAGE', 'payslip', 1],
        ['MAKER_CHECKER_SEPARATION', 'approval', 1],
    ];

    /**
     * Create one idempotent draft run and persist a byte-independent snapshot
     * of the source batch plus all staged rows.
     *
     * Options:
     *   pay_period_start, pay_period_end, pay_date (Y-m-d; inferred when the
     *   staged payload has one unambiguous value), location_id, run_type,
     *   ruleset_key, ruleset_version, and rules[].
     */
    public function createFromStagedBatch(int $batchId, array $options, string $maker): array
    {
        $maker = trim($maker);
        if ($batchId <= 0 || $maker === '') {
            return $this->failure('INVALID_REQUEST', 'A staged batch and maker are required.');
        }

        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $batch = $this->loadBatch($batchId, true);
            if (!$batch) {
                $this->db->rollBack();
                return $this->failure('BATCH_NOT_FOUND', 'The staged DTR batch was not found.');
            }

            $rows = $this->loadStagingRows($batchId, true);
            $readiness = $this->batchReadiness($batchId, $batch);
            if (!$readiness['ready']) {
                $this->db->rollBack();
                return $this->failure('BATCH_NOT_READY', 'The staged DTR batch has unresolved or invalid rows.', $readiness);
            }

            if (count($rows) === 0) {
                $this->db->rollBack();
                return $this->failure('EMPTY_BATCH', 'The staged DTR batch has no rows.');
            }

            $period = $this->derivePeriod($rows, $options);
            if (!$period['valid']) {
                $this->db->rollBack();
                return $this->failure('INVALID_PERIOD', (string)$period['error']);
            }

            $clientId = (int)($batch['client_id'] ?? 0);
            if ($clientId <= 0) {
                $this->db->rollBack();
                return $this->failure('CLIENT_REQUIRED', 'The DTR template must be assigned to a client.');
            }

            $snapshot = $this->buildInputSnapshot($batch, $rows);
            $snapshot['identity_resolution_snapshot'] = $this->buildIdentitySnapshot(
                $batch,
                $rows,
                $clientId,
                (string)$period['start'],
                (string)$period['end']
            );
            $snapshotJson = self::canonicalJson($snapshot);
            $snapshotHash = hash('sha256', $snapshotJson);
            $rules = $this->normalizeRules((array)($options['rules'] ?? []));
            $rulesetKey = trim((string)($options['ruleset_key'] ?? '')) ?: null;
            $rulesetVersion = trim((string)($options['ruleset_version'] ?? '')) ?: null;
            $rulesetError = $this->ruleCoverageError($rules, (string)$period['pay_date']);
            if ($rulesetError !== null) {
                $this->db->rollBack();
                return $this->failure('RULESET_NOT_EFFECTIVE', $rulesetError);
            }
            $hasLockedRules = count($rules) > 0 && $rulesetKey !== null && $rulesetVersion !== null;
            $rulesHash = count($rules) > 0
                ? hash('sha256', self::canonicalJson([
                    'ruleset_key' => $rulesetKey,
                    'ruleset_version' => $rulesetVersion,
                    'rules' => $rules,
                ]))
                : null;
            $idempotencyKey = self::buildIdempotencyKey([
                'schema' => 'payroll_import_run_v1',
                'batch_id' => $batchId,
                'batch_uid' => (string)$batch['batch_uid'],
                'client_id' => $clientId,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'pay_date' => $period['pay_date'],
                'input_snapshot_hash' => $snapshotHash,
                'ruleset_key' => $rulesetKey,
                'ruleset_version' => $rulesetVersion,
                'ruleset_hash' => $rulesHash,
            ]);

            $existing = $this->findRunByIdempotencyKey($idempotencyKey, true);
            if ($existing) {
                $this->db->commit();
                return [
                    'success' => 1,
                    'idempotent_replay' => true,
                    'run' => $this->castRun($existing),
                ];
            }

            $runUid = $this->uid('PRUN');
            $insert = $this->db->prepare("\n                INSERT INTO payroll_import_runs (\n                    run_uid, source_batch_id, idempotency_key, client_id, location_id,\n                    template_id, source_context, source_file_name, source_file_checksum,\n                    pay_period_start, pay_period_end, pay_date, run_type, status,\n                    identity_status, ruleset_status, calculation_status,\n                    reconciliation_status, release_status, source_row_count,\n                    input_snapshot_hash, ruleset_key, ruleset_version, ruleset_hash,\n                    maker_created_by, maker_created_at\n                ) VALUES (\n                    :run_uid, :source_batch_id, :idempotency_key, :client_id, :location_id,\n                    :template_id, :source_context, :source_file_name, :source_file_checksum,\n                    :pay_period_start, :pay_period_end, :pay_date, :run_type, 'draft',\n                    'pending', :ruleset_status, 'pending', 'pending', 'blocked',\n                    :source_row_count, :input_snapshot_hash, :ruleset_key,\n                    :ruleset_version, :ruleset_hash, :maker_created_by, NOW()\n                )\n            ");
            $insert->execute([
                ':run_uid' => $runUid,
                ':source_batch_id' => $batchId,
                ':idempotency_key' => $idempotencyKey,
                ':client_id' => $clientId,
                ':location_id' => $this->nullablePositiveInt($options['location_id'] ?? null),
                ':template_id' => $this->nullablePositiveInt($batch['template_id'] ?? null),
                ':source_context' => (string)($batch['source_context'] ?? 'dtr'),
                ':source_file_name' => (string)$batch['original_filename'],
                ':source_file_checksum' => trim((string)($batch['checksum'] ?? '')) ?: null,
                ':pay_period_start' => $period['start'],
                ':pay_period_end' => $period['end'],
                ':pay_date' => $period['pay_date'],
                ':run_type' => trim((string)($options['run_type'] ?? 'dtr_import')) ?: 'dtr_import',
                ':ruleset_status' => $hasLockedRules ? 'locked' : 'missing',
                ':source_row_count' => count($rows),
                ':input_snapshot_hash' => $snapshotHash,
                ':ruleset_key' => $rulesetKey,
                ':ruleset_version' => $rulesetVersion,
                ':ruleset_hash' => $rulesHash,
                ':maker_created_by' => $maker,
            ]);
            $runId = (int)$this->db->lastInsertId();

            $input = $this->db->prepare("\n                INSERT INTO payroll_import_run_inputs (\n                    run_id, source_batch_id, input_kind, source_name, source_checksum,\n                    snapshot_payload, snapshot_hash, captured_by, captured_at\n                ) VALUES (\n                    :run_id, :source_batch_id, 'staged_dtr_batch', :source_name,\n                    :source_checksum, :snapshot_payload, :snapshot_hash, :captured_by, NOW()\n                )\n            ");
            $input->execute([
                ':run_id' => $runId,
                ':source_batch_id' => $batchId,
                ':source_name' => (string)$batch['original_filename'],
                ':source_checksum' => trim((string)($batch['checksum'] ?? '')) ?: null,
                ':snapshot_payload' => $snapshotJson,
                ':snapshot_hash' => $snapshotHash,
                ':captured_by' => $maker,
            ]);

            $this->insertRuleSnapshots($runId, $rules, $maker);
            $this->seedReleaseChecks($runId, $hasLockedRules, $maker);
            $this->refreshReleaseBlockers($runId);
            $this->enqueueEvent($runId, 'PAYROLL_IMPORT_RUN_CREATED', [
                'run_uid' => $runUid,
                'source_batch_id' => $batchId,
                'input_snapshot_hash' => $snapshotHash,
            ]);
            $run = $this->loadRun($runId, false);
            $this->db->commit();
            $this->audit('Payroll import run created: ' . $runUid . ', batch ' . $batchId);

            return ['success' => 1, 'idempotent_replay' => false, 'run' => $this->castRun($run)];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import run creation failed: ' . $error->getMessage());
            return $this->failure('RUN_CREATE_FAILED', 'Unable to create the payroll import run.');
        }
    }

    /**
     * Atomically copy a stable, fully resolved staging snapshot into the
     * run-scoped canonical row table. No partial rows survive a failed gate.
     */
    public function canonicalizeFromStaging(int $runId, string $actor): array
    {
        $actor = trim($actor);
        if ($runId <= 0 || $actor === '') {
            return $this->failure('INVALID_REQUEST', 'A run and actor are required.');
        }

        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run) {
                $this->db->rollBack();
                return $this->failure('RUN_NOT_FOUND', 'The payroll import run was not found.');
            }
            if ((string)$run['status'] === 'canonicalized') {
                $this->db->commit();
                return ['success' => 1, 'idempotent_replay' => true, 'run' => $this->castRun($run)];
            }
            if ((string)$run['status'] !== 'draft') {
                $this->db->rollBack();
                return $this->failure('INVALID_STATE', 'Only a draft run can be canonicalized.');
            }

            $batchId = (int)$run['source_batch_id'];
            $batch = $this->loadBatch($batchId, true);
            $rows = $this->loadStagingRows($batchId, true);
            $readiness = $this->batchReadiness($batchId, $batch ?: []);
            if (!$batch || !$readiness['ready']) {
                $this->persistCanonicalBlockers($runId, (int)$readiness['open_p0_count'], 0, (int)$readiness['invalid_row_count'], $actor);
                $this->db->commit();
                return $this->failure('BATCH_NOT_READY', 'The staged DTR batch is no longer ready for handoff.', $readiness);
            }

            $currentSnapshot = $this->buildInputSnapshot($batch, $rows);
            $currentSnapshot['identity_resolution_snapshot'] = $this->buildIdentitySnapshot(
                $batch,
                $rows,
                (int)$run['client_id'],
                (string)$run['pay_period_start'],
                (string)$run['pay_period_end']
            );
            $currentSnapshotHash = hash('sha256', self::canonicalJson($currentSnapshot));
            if (!hash_equals((string)$run['input_snapshot_hash'], $currentSnapshotHash)) {
                $this->recordReleaseCheckInternal(
                    $runId,
                    'CANONICAL_ROW_INTEGRITY',
                    'dtr',
                    true,
                    'failed',
                    'Staged input changed after the immutable run snapshot was captured.',
                    ['expected' => (string)$run['input_snapshot_hash'], 'actual' => $currentSnapshotHash],
                    $actor
                );
                $this->refreshReleaseBlockers($runId);
                $this->db->commit();
                return $this->failure('INPUT_SNAPSHOT_DRIFT', 'The staged input changed after run creation; create a new run.');
            }

            $canonicalRows = [];
            $unresolved = [];
            $collisions = [];
            $employeeSources = [];
            foreach ($rows as $row) {
                if ((string)$row['validation_status'] === 'excluded') {
                    continue;
                }
                $parsed = $this->decode((string)$row['parsed_payload']);
                $sourceId = trim((string)($parsed['employee_identifier'] ?? $parsed['employee_identifier_source'] ?? ''));
                $resolution = $this->resolveEmployeeForRow($run, $batch, $row, $sourceId);
                if (($resolution['status'] ?? '') !== 'resolved') {
                    $blockedIdentity = [
                        'source_row_id' => (int)$row['id'],
                        'source_row_number' => (int)$row['source_row_number'],
                        'source_employee_id' => $sourceId,
                        'reason' => (string)($resolution['reason'] ?? 'unresolved_identity'),
                    ];
                    if (($resolution['status'] ?? '') === 'collision') {
                        $collisions[] = $blockedIdentity;
                    } else {
                        $unresolved[] = $blockedIdentity;
                    }
                    continue;
                }

                $employee = $resolution['employee'];
                $employeeId = (int)$employee['employee_id'];
                $normalizedSource = self::normalizeSourceIdentifier($sourceId);
                if (isset($employeeSources[$employeeId]) && $employeeSources[$employeeId] !== $normalizedSource) {
                    $collisions[] = [
                        'employee_id' => $employeeId,
                        'source_employee_ids' => [$employeeSources[$employeeId], $normalizedSource],
                    ];
                    continue;
                }
                $employeeSources[$employeeId] = $normalizedSource;
                $canonicalRows[] = $this->canonicalRow($run, $batch, $row, $parsed, $sourceId, $resolution);
            }

            if (count($unresolved) > 0 || count($collisions) > 0 || count($canonicalRows) === 0) {
                $this->persistCanonicalBlockers($runId, count($unresolved), count($collisions), 0, $actor, [
                    'unresolved' => $unresolved,
                    'collisions' => $collisions,
                ]);
                $this->db->commit();
                return $this->failure(
                    'IDENTITY_GATE_BLOCKED',
                    'Canonical handoff refused unresolved identities or identity collisions.',
                    ['unresolved_count' => count($unresolved), 'collision_count' => count($collisions)]
                );
            }

            $insert = $this->canonicalRowStatement();
            $rowHashes = [];
            foreach ($canonicalRows as $canonicalRow) {
                $insert->execute($canonicalRow);
                $rowHashes[] = (string)$canonicalRow[':input_payload_hash'];
            }
            sort($rowHashes, SORT_STRING);
            $canonicalHash = hash('sha256', self::canonicalJson([
                'run_uid' => (string)$run['run_uid'],
                'input_snapshot_hash' => (string)$run['input_snapshot_hash'],
                'row_hashes' => $rowHashes,
            ]));

            $update = $this->db->prepare("\n                UPDATE payroll_import_runs\n                SET status = 'canonicalized', identity_status = 'resolved',\n                    canonical_row_count = :canonical_row_count, employee_count = :employee_count,\n                    unresolved_identity_count = 0, identity_collision_count = 0,\n                    validation_error_count = 0, canonical_snapshot_hash = :canonical_snapshot_hash,\n                    lock_version = lock_version + 1\n                WHERE id = :id AND status = 'draft'\n            ");
            $update->execute([
                ':canonical_row_count' => count($canonicalRows),
                ':employee_count' => count($employeeSources),
                ':canonical_snapshot_hash' => $canonicalHash,
                ':id' => $runId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Concurrent run state change detected.');
            }

            $this->recordReleaseCheckInternal($runId, 'IDENTITY_RESOLUTION', 'identity', true, 'passed', 'Every included source identity resolved without a target collision.', [
                'canonical_rows' => count($canonicalRows),
                'employees' => count($employeeSources),
            ], $actor);
            $this->recordReleaseCheckInternal($runId, 'CANONICAL_ROW_INTEGRITY', 'dtr', true, 'passed', 'The canonical snapshot matches the captured staged input.', [
                'canonical_snapshot_hash' => $canonicalHash,
            ], $actor);
            $this->refreshReleaseBlockers($runId);
            $this->enqueueEvent($runId, 'PAYROLL_IMPORT_RUN_CANONICALIZED', [
                'canonical_row_count' => count($canonicalRows),
                'employee_count' => count($employeeSources),
                'canonical_snapshot_hash' => $canonicalHash,
            ]);
            $updated = $this->loadRun($runId, false);
            $this->db->commit();
            $this->audit('Payroll import run canonicalized: ' . (string)$run['run_uid']);
            return ['success' => 1, 'idempotent_replay' => false, 'run' => $this->castRun($updated)];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import canonical handoff failed: ' . $error->getMessage());
            return $this->failure('CANONICAL_HANDOFF_FAILED', 'Unable to canonicalize the staged DTR batch.');
        }
    }

    public function createAndCanonicalizeFromStagedBatch(
        int $batchId,
        array $options,
        string $actor
    ): array {
        $created = $this->createFromStagedBatch($batchId, $options, $actor);
        if (($created['success'] ?? 0) !== 1) {
            return $created;
        }
        $runId = (int)($created['run']['id'] ?? 0);
        $canonical = $this->canonicalizeFromStaging($runId, $actor);
        $canonical['run_created'] = empty($created['idempotent_replay']);
        return $canonical;
    }

    public function recordControlStatuses(
        int $runId,
        string $calculationStatus,
        string $reconciliationStatus,
        array $evidence,
        string $actor
    ): array {
        $allowed = ['pending', 'running', 'passed', 'failed'];
        if (!in_array($calculationStatus, $allowed, true) || !in_array($reconciliationStatus, $allowed, true)) {
            return $this->failure('INVALID_CONTROL_STATUS', 'Unsupported calculation or reconciliation status.');
        }
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run || !in_array((string)$run['status'], ['canonicalized', 'validating'], true)) {
                $this->db->rollBack();
                return $this->failure('INVALID_STATE', 'Controls may run only after canonicalization.');
            }
            $nextStatus = (string)$run['status'] === 'canonicalized' ? 'validating' : 'validating';
            $stmt = $this->db->prepare("\n                UPDATE payroll_import_runs\n                SET status = :status, calculation_status = :calculation_status,\n                    reconciliation_status = :reconciliation_status, lock_version = lock_version + 1\n                WHERE id = :id\n            ");
            $stmt->execute([
                ':status' => $nextStatus,
                ':calculation_status' => $calculationStatus,
                ':reconciliation_status' => $reconciliationStatus,
                ':id' => $runId,
            ]);
            $this->recordReleaseCheckInternal(
                $runId,
                'PAYROLL_CALCULATION',
                'calculation',
                true,
                $this->controlCheckStatus($calculationStatus),
                'Versioned payroll calculation control status: ' . $calculationStatus . '.',
                $evidence['calculation'] ?? [],
                $actor
            );
            $this->recordReleaseCheckInternal(
                $runId,
                'PAYROLL_RECONCILIATION',
                'reconciliation',
                true,
                $this->controlCheckStatus($reconciliationStatus),
                'Payroll reconciliation control status: ' . $reconciliationStatus . '.',
                $evidence['reconciliation'] ?? [],
                $actor
            );
            $this->refreshReleaseBlockers($runId);
            $this->enqueueEvent($runId, 'PAYROLL_IMPORT_CONTROLS_UPDATED', [
                'calculation_status' => $calculationStatus,
                'reconciliation_status' => $reconciliationStatus,
            ]);
            $updated = $this->loadRun($runId, false);
            $this->db->commit();
            return ['success' => 1, 'run' => $this->castRun($updated)];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import control update failed: ' . $error->getMessage());
            return $this->failure('CONTROL_UPDATE_FAILED', 'Unable to record payroll controls.');
        }
    }

    /** Seal the exact mutable legacy payroll scope after calculation. */
    public function bindLegacyPayrollScope(int $runId, string $actor): array
    {
        $actor = trim($actor);
        if ($runId <= 0 || $actor === '') {
            return $this->failure('INVALID_REQUEST', 'A run and binding owner are required.');
        }
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run || (string)$run['status'] !== 'validating'
                || (string)$run['calculation_status'] !== 'passed'
                || (string)$run['reconciliation_status'] !== 'passed') {
                $this->db->rollBack();
                return $this->failure(
                    'INVALID_STATE',
                    'The legacy payroll scope can be sealed only after calculation and reconciliation pass.'
                );
            }
            $client = $this->db->prepare('SELECT client_name FROM taascor_client WHERE client_id = :client_id LIMIT 1');
            $client->execute([':client_id' => (int)$run['client_id']]);
            $clientName = trim((string)$client->fetchColumn());
            if ($clientName === '') {
                throw new RuntimeException('Payroll client lookup failed.');
            }

            $snapshot = (new PayrollLegacyScopeHasher($this->db))->snapshot($clientName, (string)$run['pay_date']);
            $runEmployees = $this->db->prepare(
                'SELECT DISTINCT employee_id FROM payroll_import_run_rows WHERE run_id = :run_id ORDER BY employee_id'
            );
            $runEmployees->execute([':run_id' => $runId]);
            $expectedEmployees = array_map('intval', $runEmployees->fetchAll(PDO::FETCH_COLUMN));
            $actualEmployees = array_map('intval', $snapshot['employee_ids']);
            if ($expectedEmployees !== $actualEmployees || count($expectedEmployees) === 0
                || (int)$snapshot['payroll_row_count'] <= 0) {
                $this->db->rollBack();
                return $this->failure(
                    'LEGACY_SCOPE_POPULATION_MISMATCH',
                    'The calculated payroll population does not exactly match the canonical run.',
                    [
                        'canonical_employee_count' => count($expectedEmployees),
                        'legacy_employee_count' => count($actualEmployees),
                        'legacy_payroll_row_count' => (int)$snapshot['payroll_row_count'],
                    ]
                );
            }

            $existing = $this->db->prepare(
                'SELECT live_snapshot_hash FROM payroll_import_legacy_scope_bindings WHERE run_id = :run_id FOR UPDATE'
            );
            $existing->execute([':run_id' => $runId]);
            $existingHash = $existing->fetchColumn();
            if ($existingHash !== false && !hash_equals((string)$existingHash, (string)$snapshot['live_snapshot_hash'])) {
                $this->db->rollBack();
                return $this->failure(
                    'LEGACY_SCOPE_ALREADY_CHANGED',
                    'A different payroll snapshot was already sealed. Create a superseding run.'
                );
            }
            if ($existingHash === false) {
                $binding = $this->db->prepare("\n                    INSERT INTO payroll_import_legacy_scope_bindings (
                        run_id, client_name, pay_day, live_snapshot_hash, employee_count,
                        payroll_row_count, snapshot_payload, bound_by, bound_at
                    ) VALUES (
                        :run_id, :client_name, :pay_day, :live_snapshot_hash, :employee_count,
                        :payroll_row_count, :snapshot_payload, :bound_by, NOW()
                    )
                ");
                $binding->execute([
                    ':run_id' => $runId,
                    ':client_name' => $clientName,
                    ':pay_day' => (string)$run['pay_date'],
                    ':live_snapshot_hash' => (string)$snapshot['live_snapshot_hash'],
                    ':employee_count' => (int)$snapshot['employee_count'],
                    ':payroll_row_count' => (int)$snapshot['payroll_row_count'],
                    ':snapshot_payload' => (string)$snapshot['snapshot_payload'],
                    ':bound_by' => $actor,
                ]);
            }
            $this->recordReleaseCheckInternal(
                $runId,
                'LEGACY_SCOPE_BINDING',
                'reconciliation',
                true,
                'passed',
                'The live payroll population and rows are sealed to this run.',
                [
                    'live_snapshot_hash' => (string)$snapshot['live_snapshot_hash'],
                    'employee_count' => (int)$snapshot['employee_count'],
                    'payroll_row_count' => (int)$snapshot['payroll_row_count'],
                ],
                $actor
            );
            $this->refreshReleaseBlockers($runId);
            $this->enqueueEvent($runId, 'PAYROLL_IMPORT_LEGACY_SCOPE_BOUND', [
                'live_snapshot_hash' => (string)$snapshot['live_snapshot_hash'],
            ]);
            $updated = $this->loadRun($runId, false);
            $this->db->commit();
            return [
                'success' => 1,
                'idempotent_replay' => $existingHash !== false,
                'run' => $this->castRun($updated),
                'binding' => [
                    'live_snapshot_hash' => (string)$snapshot['live_snapshot_hash'],
                    'employee_count' => (int)$snapshot['employee_count'],
                    'payroll_row_count' => (int)$snapshot['payroll_row_count'],
                ],
            ];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll legacy scope binding failed: ' . $error->getMessage());
            return $this->failure('LEGACY_SCOPE_BINDING_FAILED', 'Unable to seal the calculated payroll scope.');
        }
    }

    public function recordReleaseCheck(
        int $runId,
        string $checkCode,
        string $category,
        bool $blocking,
        string $status,
        string $summary,
        array $evidence,
        string $actor
    ): array {
        $checkCode = strtoupper(trim($checkCode));
        $status = strtolower(trim($status));
        if ($checkCode === '' || !in_array($status, ['pending', 'passed', 'failed', 'waived'], true)) {
            return $this->failure('INVALID_CHECK', 'A valid release check code and status are required.');
        }
        if ($blocking && $status === 'waived') {
            return $this->failure('BLOCKING_CHECK_CANNOT_BE_WAIVED', 'A blocking release check must pass.');
        }
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run || in_array((string)$run['status'], ['released', 'cancelled', 'failed'], true)) {
                $this->db->rollBack();
                return $this->failure('INVALID_STATE', 'Release checks cannot change for this run.');
            }
            $this->recordReleaseCheckInternal($runId, $checkCode, $category, $blocking, $status, $summary, $evidence, $actor);
            $this->refreshReleaseBlockers($runId);
            $updated = $this->loadRun($runId, false);
            $this->db->commit();
            return ['success' => 1, 'run' => $this->castRun($updated)];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import release check update failed: ' . $error->getMessage());
            return $this->failure('RELEASE_CHECK_UPDATE_FAILED', 'Unable to record the release check.');
        }
    }

    public function markReadyForApproval(int $runId, string $actor): array
    {
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run || (string)$run['status'] !== 'validating') {
                $this->db->rollBack();
                return $this->failure('INVALID_STATE', 'The run is not eligible for approval review.');
            }
            $facts = $this->loadGateFacts($run, 'approval');
            $gate = self::evaluateGate($facts, 'approval');
            if (!$gate['eligible']) {
                $this->refreshReleaseBlockers($runId);
                $this->db->commit();
                return $this->failure('APPROVAL_GATE_BLOCKED', 'The run has unresolved approval blockers.', $gate);
            }
            $this->changeStateInternal($run, 'ready_for_approval', $actor, 'Approval gate passed.');
            $this->enqueueEvent($runId, 'PAYROLL_IMPORT_READY_FOR_APPROVAL', ['actor' => $actor]);
            $updated = $this->loadRun($runId, false);
            $this->db->commit();
            return ['success' => 1, 'run' => $this->castRun($updated), 'gate' => $gate];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import approval-ready transition failed: ' . $error->getMessage());
            return $this->failure('APPROVAL_READY_FAILED', 'Unable to mark the run ready for approval.');
        }
    }

    public function approveRun(int $runId, string $checker): array
    {
        $checker = trim($checker);
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run || (string)$run['status'] !== 'ready_for_approval') {
                $this->db->rollBack();
                return $this->failure('INVALID_STATE', 'Only a ready run can be approved.');
            }
            if ($checker === '' || strcasecmp($checker, (string)$run['maker_created_by']) === 0) {
                $this->db->rollBack();
                return $this->failure('MAKER_CHECKER_CONFLICT', 'The checker must be different from the run maker.');
            }
            $gate = self::evaluateGate($this->loadGateFacts($run, 'approval'), 'approval');
            if (!$gate['eligible']) {
                $this->db->rollBack();
                return $this->failure('APPROVAL_GATE_BLOCKED', 'The run has unresolved approval blockers.', $gate);
            }
            $stmt = $this->db->prepare("\n                UPDATE payroll_import_runs\n                SET status = 'approved', checker_approved_by = :checker,\n                    checker_approved_at = NOW(), lock_version = lock_version + 1\n                WHERE id = :id AND status = 'ready_for_approval'\n            ");
            $stmt->execute([':checker' => $checker, ':id' => $runId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Concurrent approval state change detected.');
            }
            $this->recordReleaseCheckInternal($runId, 'MAKER_CHECKER_SEPARATION', 'approval', true, 'passed', 'Maker and checker are distinct.', [
                'maker' => (string)$run['maker_created_by'],
                'checker' => $checker,
            ], $checker);
            $this->refreshReleaseBlockers($runId);
            $this->enqueueEvent($runId, 'PAYROLL_IMPORT_RUN_APPROVED', ['checker' => $checker]);
            $updated = $this->loadRun($runId, false);
            $this->db->commit();
            $this->audit('Payroll import run approved: ' . (string)$run['run_uid']);
            return ['success' => 1, 'run' => $this->castRun($updated)];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import approval failed: ' . $error->getMessage());
            return $this->failure('APPROVAL_FAILED', 'Unable to approve the payroll import run.');
        }
    }

    public function registerPayslipArtifact(int $runId, array $artifact, string $actor): array
    {
        $employeeId = (int)($artifact['employee_id'] ?? 0);
        $path = trim((string)($artifact['storage_path'] ?? ''));
        $hash = strtolower(trim((string)($artifact['content_hash'] ?? '')));
        $status = strtolower(trim((string)($artifact['artifact_status'] ?? 'generated')));
        $type = trim((string)($artifact['artifact_type'] ?? 'payslip_pdf')) ?: 'payslip_pdf';
        if ($employeeId <= 0 || $path === '' || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return $this->failure('INVALID_ARTIFACT', 'Employee, storage path, and SHA-256 content hash are required.');
        }
        if (!in_array($status, ['generated', 'verified', 'failed'], true)) {
            return $this->failure('INVALID_ARTIFACT_STATUS', 'Unsupported payslip artifact status.');
        }
        $verifiedFile = null;
        if ($status !== 'failed') {
            $verifiedFile = $this->verifiedPayslipArtifactFile($path, $hash);
            if ($verifiedFile === null) {
                return $this->failure(
                    'ARTIFACT_INTEGRITY_FAILED',
                    'The payslip file must exist in the configured private artifact store and match its SHA-256 hash.'
                );
            }
            $path = (string)$verifiedFile['storage_path'];
            $hash = (string)$verifiedFile['content_hash'];
        }
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run || !in_array((string)$run['status'], ['approved'], true)) {
                $this->db->rollBack();
                return $this->failure('INVALID_STATE', 'Payslip artifacts may be registered only for an approved run.');
            }
            $employee = $this->db->prepare('SELECT COUNT(*) FROM payroll_import_run_rows WHERE run_id = :run_id AND employee_id = :employee_id');
            $employee->execute([':run_id' => $runId, ':employee_id' => $employeeId]);
            if ((int)$employee->fetchColumn() === 0) {
                $this->db->rollBack();
                return $this->failure('EMPLOYEE_NOT_IN_RUN', 'The payslip employee is not part of this run.');
            }
            $stmt = $this->db->prepare("\n                INSERT INTO payroll_import_payslip_artifacts (\n                    artifact_uid, run_id, employee_id, artifact_type, storage_path,\n                    content_hash, byte_size, artifact_status, generated_by, generated_at,\n                    verified_by, verified_at, published_at\n                ) VALUES (\n                    :artifact_uid, :run_id, :employee_id, :artifact_type, :storage_path,\n                    :content_hash, :byte_size, :artifact_status, :generated_by, NOW(),\n                    :verified_by, :verified_at, :published_at\n                )\n                ON DUPLICATE KEY UPDATE\n                    storage_path = VALUES(storage_path), content_hash = VALUES(content_hash),\n                    byte_size = VALUES(byte_size), artifact_status = VALUES(artifact_status),\n                    verified_by = VALUES(verified_by), verified_at = VALUES(verified_at),\n                    published_at = VALUES(published_at)\n            ");
            $verified = $status === 'verified';
            $stmt->execute([
                ':artifact_uid' => $this->uid('PART'),
                ':run_id' => $runId,
                ':employee_id' => $employeeId,
                ':artifact_type' => $type,
                ':storage_path' => $path,
                ':content_hash' => $hash,
                ':byte_size' => $verifiedFile !== null
                    ? (int)$verifiedFile['byte_size']
                    : (isset($artifact['byte_size']) ? max(0, (int)$artifact['byte_size']) : null),
                ':artifact_status' => $status,
                ':generated_by' => $actor,
                ':verified_by' => $verified ? $actor : null,
                ':verified_at' => $verified ? date('Y-m-d H:i:s') : null,
                ':published_at' => null,
            ]);
            $coverage = $this->artifactCoverage($runId);
            $this->recordReleaseCheckInternal(
                $runId,
                'PAYSLIP_ARTIFACT_COVERAGE',
                'payslip',
                true,
                $coverage['complete'] ? 'passed' : 'failed',
                $coverage['complete'] ? 'Every run employee has a verified payslip artifact.' : 'Verified payslip artifact coverage is incomplete.',
                $coverage,
                $actor
            );
            $this->refreshReleaseBlockers($runId);
            $this->enqueueEvent($runId, 'PAYSLIP_ARTIFACT_REGISTERED', [
                'employee_id' => $employeeId,
                'artifact_type' => $type,
                'artifact_status' => $status,
                'content_hash' => $hash,
            ]);
            $this->db->commit();
            return ['success' => 1, 'coverage' => $coverage];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import artifact registration failed: ' . $error->getMessage());
            return $this->failure('ARTIFACT_REGISTRATION_FAILED', 'Unable to register the payslip artifact.');
        }
    }

    public function transitionRun(int $runId, string $toState, string $actor, string $reason): array
    {
        $toState = strtolower(trim($toState));
        if (!in_array($toState, ['validating', 'failed', 'cancelled'], true)) {
            return $this->failure('GUARDED_TRANSITION', 'Use the dedicated canonicalize or approval operation; payroll posting owns release.');
        }
        if (in_array($toState, ['failed', 'cancelled'], true) && trim($reason) === '') {
            return $this->failure('REASON_REQUIRED', 'A failure or cancellation reason is required.');
        }
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            $run = $this->loadRun($runId, true);
            if (!$run || !self::canTransition((string)$run['status'], $toState)) {
                $this->db->rollBack();
                return $this->failure('INVALID_STATE_TRANSITION', 'The requested run state transition is not allowed.');
            }
            $this->changeStateInternal($run, $toState, $actor, $reason);
            $this->enqueueEvent($runId, 'PAYROLL_IMPORT_RUN_STATE_CHANGED', [
                'from' => (string)$run['status'],
                'to' => $toState,
                'actor' => $actor,
                'reason' => $reason,
            ]);
            $updated = $this->loadRun($runId, false);
            $this->db->commit();
            return ['success' => 1, 'run' => $this->castRun($updated)];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Payroll import state transition failed: ' . $error->getMessage());
            return $this->failure('STATE_TRANSITION_FAILED', 'Unable to change the payroll import run state.');
        }
    }

    /** Record an immutable identity decision and optionally activate its alias. */
    public function recordIdentityDecision(array $decision, string $actor): array
    {
        $clientId = (int)($decision['client_id'] ?? 0);
        $namespace = trim((string)($decision['source_namespace'] ?? ''));
        $sourceId = trim((string)($decision['source_employee_id'] ?? ''));
        $employeeId = $this->nullablePositiveInt($decision['employee_id'] ?? null);
        $type = trim((string)($decision['decision_type'] ?? 'manual_match'));
        $reason = trim((string)($decision['reason'] ?? ''));
        if ($clientId <= 0 || $namespace === '' || $sourceId === '' || $reason === '' || trim($actor) === '') {
            return $this->failure('INVALID_IDENTITY_DECISION', 'Client, namespace, source ID, reason, and actor are required.');
        }
        if ($type !== 'exclude' && $employeeId === null) {
            return $this->failure('EMPLOYEE_REQUIRED', 'A mapped identity decision requires an employee.');
        }
        try {
            $this->assertDatabase();
            $this->db->beginTransaction();
            if ($employeeId !== null) {
                $employee = $this->loadEmployee($employeeId, true);
                if (!$employee || (int)$employee['client_id'] !== $clientId || strcasecmp((string)$employee['status'], 'Active') !== 0) {
                    $this->db->rollBack();
                    return $this->failure('INVALID_EMPLOYEE', 'The identity target must be an active employee in the same client.');
                }
            }
            $normalized = self::normalizeSourceIdentifier($sourceId);
            $latest = $this->latestIdentityAlias($clientId, $namespace, $normalized, true);
            $decisionUid = $this->uid('IDEC');
            $insert = $this->db->prepare("\n                INSERT INTO employee_identity_decisions (\n                    decision_uid, client_id, source_namespace, source_employee_id,\n                    normalized_source_employee_id, employee_id, decision_type, decision_status,\n                    confidence_score, evidence_payload, reason, decided_by, decided_at,\n                    supersedes_decision_id\n                ) VALUES (\n                    :decision_uid, :client_id, :source_namespace, :source_employee_id,\n                    :normalized_source_employee_id, :employee_id, :decision_type, 'approved',\n                    :confidence_score, :evidence_payload, :reason, :decided_by, NOW(),\n                    :supersedes_decision_id\n                )\n            ");
            $insert->execute([
                ':decision_uid' => $decisionUid,
                ':client_id' => $clientId,
                ':source_namespace' => $namespace,
                ':source_employee_id' => $sourceId,
                ':normalized_source_employee_id' => $normalized,
                ':employee_id' => $employeeId,
                ':decision_type' => $type,
                ':confidence_score' => isset($decision['confidence_score']) ? max(0, min(1, (float)$decision['confidence_score'])) : null,
                ':evidence_payload' => self::canonicalJson((array)($decision['evidence'] ?? [])),
                ':reason' => $reason,
                ':decided_by' => $actor,
                ':supersedes_decision_id' => $latest ? (int)$latest['decision_id'] : null,
            ]);
            $decisionId = (int)$this->db->lastInsertId();

            if ($latest) {
                $revoke = $this->db->prepare("\n                    UPDATE employee_identity_aliases\n                    SET alias_status = 'revoked', revoked_by = :revoked_by, revoked_at = NOW(),\n                        revocation_reason = :reason, effective_to = LEAST(COALESCE(effective_to, :effective_to), :effective_to)\n                    WHERE id = :id AND alias_status = 'active'\n                ");
                $effectiveFrom = $this->validDateOrDefault((string)($decision['effective_from'] ?? ''), date('Y-m-d'));
                $previousEnd = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));
                $revoke->execute([
                    ':revoked_by' => $actor,
                    ':reason' => 'Superseded by identity decision ' . $decisionUid,
                    ':effective_to' => $previousEnd,
                    ':id' => (int)$latest['id'],
                ]);
            }

            $aliasId = null;
            if ($employeeId !== null && $type !== 'exclude') {
                $version = $latest ? ((int)$latest['version_no'] + 1) : 1;
                $alias = $this->db->prepare("\n                    INSERT INTO employee_identity_aliases (\n                        alias_uid, client_id, source_namespace, source_employee_id,\n                        normalized_source_employee_id, employee_id, decision_id, version_no,\n                        alias_status, effective_from, effective_to, created_by, created_at\n                    ) VALUES (\n                        :alias_uid, :client_id, :source_namespace, :source_employee_id,\n                        :normalized_source_employee_id, :employee_id, :decision_id, :version_no,\n                        'active', :effective_from, :effective_to, :created_by, NOW()\n                    )\n                ");
                $alias->execute([
                    ':alias_uid' => $this->uid('IALS'),
                    ':client_id' => $clientId,
                    ':source_namespace' => $namespace,
                    ':source_employee_id' => $sourceId,
                    ':normalized_source_employee_id' => $normalized,
                    ':employee_id' => $employeeId,
                    ':decision_id' => $decisionId,
                    ':version_no' => $version,
                    ':effective_from' => $this->validDateOrDefault((string)($decision['effective_from'] ?? ''), date('Y-m-d')),
                    ':effective_to' => $this->validDateOrNull((string)($decision['effective_to'] ?? '')),
                    ':created_by' => $actor,
                ]);
                $aliasId = (int)$this->db->lastInsertId();
            }
            $this->db->commit();
            $this->audit('Employee identity decision recorded: ' . $decisionUid);
            return ['success' => 1, 'decision_id' => $decisionId, 'decision_uid' => $decisionUid, 'alias_id' => $aliasId];
        } catch (Throwable $error) {
            $this->rollback();
            error_log('Identity decision recording failed: ' . $error->getMessage());
            return $this->failure('IDENTITY_DECISION_FAILED', 'Unable to record the identity decision.');
        }
    }

    public function getRun(int $runId): array
    {
        try {
            $this->assertDatabase();
            $run = $this->loadRun($runId, false);
            if (!$run) {
                return $this->failure('RUN_NOT_FOUND', 'The payroll import run was not found.');
            }
            return ['success' => 1, 'run' => $this->castRun($run)];
        } catch (Throwable $error) {
            return $this->failure('RUN_LOOKUP_FAILED', 'Unable to load the payroll import run.');
        }
    }

    public function getReleaseStatus(int $runId): array
    {
        try {
            $this->assertDatabase();
            $run = $this->loadRun($runId, false);
            if (!$run) {
                return $this->failure('RUN_NOT_FOUND', 'The payroll import run was not found.');
            }
            $checks = $this->releaseChecks($runId);
            $coverage = $this->artifactCoverage($runId);
            $facts = $this->loadGateFacts($run, 'release');
            return [
                'success' => 1,
                'run' => $this->castRun($run),
                'gate' => self::evaluateGate($facts, 'release'),
                'checks' => $checks,
                'artifact_coverage' => $coverage,
            ];
        } catch (Throwable $error) {
            error_log('Payroll import release status failed: ' . $error->getMessage());
            return $this->failure('RELEASE_STATUS_FAILED', 'Unable to load the release status.');
        }
    }

    public function getLatestRunForBatch(int $batchId): array
    {
        if ($batchId <= 0) {
            return $this->failure('INVALID_REQUEST', 'A staged batch is required.');
        }
        try {
            $this->assertDatabase();
            $stmt = $this->db->prepare("
                SELECT id
                FROM payroll_import_runs
                WHERE source_batch_id = :source_batch_id
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([':source_batch_id' => $batchId]);
            $runId = (int)$stmt->fetchColumn();
            if ($runId <= 0) {
                return $this->failure('RUN_NOT_FOUND', 'No payroll import run exists for this staged batch.');
            }
            return $this->getReleaseStatus($runId);
        } catch (Throwable $error) {
            error_log('Latest payroll import run lookup failed: ' . $error->getMessage());
            return $this->failure('RUN_LOOKUP_FAILED', 'Unable to load the latest payroll import run.');
        }
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::STATE_TRANSITIONS[$from] ?? [], true);
    }

    public static function buildIdempotencyKey(array $material): string
    {
        return hash('sha256', self::canonicalJson($material));
    }

    public static function normalizeSourceIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }
        $value = function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
        return trim((string)preg_replace('/\s+/', ' ', $value));
    }

    public static function canonicalJson($value): string
    {
        $normalized = self::canonicalValue($value);
        $encoded = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($encoded === false) {
            throw new InvalidArgumentException('Unable to encode canonical JSON.');
        }
        return $encoded;
    }

    public static function evaluateGate(array $facts, string $phase = 'release'): array
    {
        $blockers = [];
        $identityStatus = (string)($facts['identity_status'] ?? 'pending');
        if ($identityStatus !== 'resolved') {
            $blockers[] = 'identity_not_resolved';
        }
        if ((int)($facts['unresolved_identity_count'] ?? 0) > 0) {
            $blockers[] = 'unresolved_identities';
        }
        if ((int)($facts['identity_collision_count'] ?? 0) > 0) {
            $blockers[] = 'identity_collisions';
        }
        if ((int)($facts['validation_error_count'] ?? 0) > 0) {
            $blockers[] = 'validation_errors';
        }
        if ((string)($facts['ruleset_status'] ?? 'pending') !== 'locked') {
            $blockers[] = 'ruleset_not_locked';
        }
        if ((string)($facts['calculation_status'] ?? 'pending') !== 'passed') {
            $blockers[] = 'calculation_not_passed';
        }
        if ((string)($facts['reconciliation_status'] ?? 'pending') !== 'passed') {
            $blockers[] = 'reconciliation_not_passed';
        }
        foreach ((array)($facts['blocking_checks'] ?? []) as $check) {
            if ((string)($check['status'] ?? '') !== 'passed') {
                $blockers[] = 'check:' . (string)($check['code'] ?? 'unknown');
            }
        }

        if ($phase === 'release') {
            if (!in_array((string)($facts['run_status'] ?? ''), ['approved', 'released'], true)) {
                $blockers[] = 'run_not_approved';
            }
            $maker = trim((string)($facts['maker'] ?? ''));
            $checker = trim((string)($facts['checker'] ?? ''));
            if ($maker === '' || $checker === '' || strcasecmp($maker, $checker) === 0) {
                $blockers[] = 'maker_checker_not_separated';
            }
            if ((int)($facts['verified_artifact_count'] ?? 0) < (int)($facts['employee_count'] ?? 0)
                || (int)($facts['employee_count'] ?? 0) <= 0) {
                $blockers[] = 'payslip_artifact_coverage_incomplete';
            }
        }

        $blockers = array_values(array_unique($blockers));
        return ['eligible' => count($blockers) === 0, 'blocker_count' => count($blockers), 'blockers' => $blockers];
    }

    public static function evaluateReleaseEligibility(array $facts): array
    {
        return self::evaluateGate($facts, 'release');
    }

    private static function canonicalValue($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $keys = array_keys($value);
        $isList = $keys === range(0, count($value) - 1);
        if (!$isList) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalValue($item);
        }
        return $value;
    }

    private function loadBatch(int $batchId, bool $forUpdate): ?array
    {
        $stmt = $this->db->prepare("\n            SELECT b.*, t.client_id, t.template_name, t.source_type\n            FROM dtr_upload_batches b\n            LEFT JOIN dtr_format_templates t ON t.id = b.template_id\n            WHERE b.id = :id\n            LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '') . "\n        ");
        $stmt->execute([':id' => $batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function loadStagingRows(int $batchId, bool $forUpdate = false): array
    {
        $stmt = $this->db->prepare("\n            SELECT id, batch_id, source_row_number, raw_payload, parsed_payload,\n                   validation_status, error_summary, is_synthetic, created_at\n            FROM dtr_upload_staging_rows\n            WHERE batch_id = :batch_id\n            ORDER BY source_row_number ASC, id ASC"
            . ($forUpdate ? "\n            FOR UPDATE" : '')
            . "\n        ");
        $stmt->execute([':batch_id' => $batchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadRun(int $runId, bool $forUpdate): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payroll_import_runs WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findRunByIdempotencyKey(string $key, bool $forUpdate): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payroll_import_runs WHERE idempotency_key = :idempotency_key LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':idempotency_key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function batchReadiness(int $batchId, array $batch): array
    {
        $exceptions = $this->db->prepare("\n            SELECT COUNT(*) FROM dtr_employee_exceptions\n            WHERE batch_id = :batch_id AND severity = 'P0' AND status = 'open'\n        ");
        $exceptions->execute([':batch_id' => $batchId]);
        $openP0 = (int)$exceptions->fetchColumn();
        $invalid = $this->db->prepare("\n            SELECT COUNT(*) FROM dtr_upload_staging_rows\n            WHERE batch_id = :batch_id AND validation_status NOT IN ('valid', 'excluded')\n        ");
        $invalid->execute([':batch_id' => $batchId]);
        $invalidRows = (int)$invalid->fetchColumn();
        $batchReady = (string)($batch['validation_status'] ?? '') === 'passed'
            && (string)($batch['processing_status'] ?? '') === 'identity_ready';
        return [
            'ready' => $batchReady && $openP0 === 0 && $invalidRows === 0,
            'batch_status_ready' => $batchReady,
            'open_p0_count' => $openP0,
            'invalid_row_count' => $invalidRows,
        ];
    }

    private function derivePeriod(array $rows, array $options): array
    {
        $values = ['start' => [], 'end' => [], 'pay_date' => []];
        $workDates = [];
        foreach ($rows as $row) {
            if ((string)$row['validation_status'] === 'excluded') {
                continue;
            }
            $parsed = $this->decode((string)$row['parsed_payload']);
            $workDate = trim((string)($parsed['work_date'] ?? ''));
            if ($this->validDateOrNull($workDate) !== null) {
                $workDates[$workDate] = true;
            }
            foreach (['period_start' => 'start', 'period_end' => 'end', 'pay_date' => 'pay_date'] as $payloadKey => $key) {
                $value = trim((string)($parsed[$payloadKey] ?? ''));
                if ($value !== '') {
                    $values[$key][$value] = true;
                }
            }
        }
        $workDates = array_keys($workDates);
        sort($workDates, SORT_STRING);
        if (count($values['start']) === 0 && count($workDates) > 0) {
            $values['start'][$workDates[0]] = true;
        }
        if (count($values['end']) === 0 && count($workDates) > 0) {
            $values['end'][$workDates[count($workDates) - 1]] = true;
        }
        $resolved = [];
        $optionKeys = ['start' => 'pay_period_start', 'end' => 'pay_period_end', 'pay_date' => 'pay_date'];
        foreach ($optionKeys as $key => $optionKey) {
            $explicit = trim((string)($options[$optionKey] ?? ''));
            $candidates = array_keys($values[$key]);
            if ($explicit !== '') {
                foreach ($candidates as $candidate) {
                    if ($candidate !== $explicit) {
                        return [
                            'valid' => false,
                            'error' => 'The selected payroll period does not match the staged DTR payload.',
                        ];
                    }
                }
                $resolved[$key] = $explicit;
                continue;
            }
            if (count($candidates) !== 1) {
                return ['valid' => false, 'error' => 'The payroll period and pay date must be explicit and unambiguous.'];
            }
            $resolved[$key] = $candidates[0];
        }
        foreach ($resolved as $value) {
            if ($this->validDateOrNull($value) === null) {
                return ['valid' => false, 'error' => 'The payroll period contains an invalid date.'];
            }
        }
        if ($resolved['end'] < $resolved['start']) {
            return ['valid' => false, 'error' => 'Payroll period end cannot be before its start.'];
        }
        return ['valid' => true] + $resolved;
    }

    private function buildInputSnapshot(array $batch, array $rows): array
    {
        $snapshotRows = [];
        foreach ($rows as $row) {
            $snapshotRows[] = [
                'id' => (int)$row['id'],
                'source_row_number' => (int)$row['source_row_number'],
                'raw_payload' => $this->decode((string)$row['raw_payload']),
                'parsed_payload' => $this->decode((string)$row['parsed_payload']),
                'validation_status' => (string)$row['validation_status'],
                'error_summary' => $this->decode((string)$row['error_summary']),
                'is_synthetic' => (int)$row['is_synthetic'],
            ];
        }
        return [
            'snapshot_schema' => 'dtr_staging_snapshot_v1',
            'batch' => [
                'id' => (int)$batch['id'],
                'batch_uid' => (string)$batch['batch_uid'],
                'template_id' => $this->nullablePositiveInt($batch['template_id'] ?? null),
                'client_id' => $this->nullablePositiveInt($batch['client_id'] ?? null),
                'source_context' => (string)($batch['source_context'] ?? ''),
                'original_filename' => (string)$batch['original_filename'],
                'checksum' => (string)($batch['checksum'] ?? ''),
                'row_count' => (int)$batch['row_count'],
                'validation_status' => (string)$batch['validation_status'],
                'processing_status' => (string)$batch['processing_status'],
            ],
            'rows' => $snapshotRows,
        ];
    }

    private function buildIdentitySnapshot(
        array $batch,
        array $rows,
        int $clientId,
        string $periodStart,
        string $periodEnd
    ): array {
        $namespace = (int)($batch['template_id'] ?? 0) > 0
            ? 'template:' . (int)$batch['template_id']
            : 'context:' . (string)$batch['source_context'];
        $rawIds = [];
        $normalizedIds = [];
        foreach ($rows as $row) {
            if ((string)$row['validation_status'] === 'excluded') {
                continue;
            }
            $parsed = $this->decode((string)$row['parsed_payload']);
            $raw = trim((string)($parsed['employee_identifier'] ?? $parsed['employee_identifier_source'] ?? ''));
            if ($raw !== '') {
                $rawIds[$raw] = true;
                $normalizedIds[self::normalizeSourceIdentifier($raw)] = true;
            }
        }
        $rawIds = array_keys($rawIds);
        $normalizedIds = array_keys($normalizedIds);
        sort($rawIds, SORT_STRING);
        sort($normalizedIds, SORT_STRING);

        $aliases = [];
        if (count($normalizedIds) > 0) {
            $placeholders = [];
            $params = [
                ':client_id' => $clientId,
                ':source_namespace' => $namespace,
                ':period_start' => $periodStart,
                ':period_end' => $periodEnd,
            ];
            foreach ($normalizedIds as $index => $identifier) {
                $key = ':normalized_' . $index;
                $placeholders[] = $key;
                $params[$key] = $identifier;
            }
            $stmt = $this->db->prepare(
                'SELECT id, decision_id, normalized_source_employee_id, employee_id, version_no, '
                . 'alias_status, effective_from, effective_to '
                . 'FROM employee_identity_aliases WHERE client_id = :client_id '
                . 'AND source_namespace = :source_namespace '
                . 'AND normalized_source_employee_id IN (' . implode(',', $placeholders) . ') '
                . "AND alias_status = 'active' AND effective_from <= :period_start "
                . 'AND (effective_to IS NULL OR effective_to >= :period_end) '
                . 'ORDER BY normalized_source_employee_id, version_no, id'
            );
            $stmt->execute($params);
            $aliases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $legacy = [];
        if (count($rawIds) > 0) {
            $placeholders = [];
            $params = [
                ':client_id' => $clientId,
                ':source_namespace' => $namespace,
                ':period_start' => $periodStart,
                ':period_end' => $periodEnd,
            ];
            foreach ($rawIds as $index => $identifier) {
                $key = ':raw_' . $index;
                $placeholders[] = $key;
                $params[$key] = $identifier;
            }
            $stmt = $this->db->prepare(
                'SELECT id, source_employee_id, employee_id, status, effective_from, effective_to '
                . 'FROM employee_identity_map WHERE client_id = :client_id '
                . 'AND source_namespace = :source_namespace '
                . 'AND source_employee_id IN (' . implode(',', $placeholders) . ") AND status = 'approved' "
                . 'AND (effective_from IS NULL OR effective_from <= :period_start) '
                . 'AND (effective_to IS NULL OR effective_to >= :period_end) '
                . 'ORDER BY source_employee_id, id'
            );
            $stmt->execute($params);
            $legacy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $exception = $this->db->prepare(
            'SELECT staging_row_id, status, resolution_type, resolved_employee_id, resolved_by, resolved_at '
            . 'FROM dtr_employee_exceptions WHERE batch_id = :batch_id ORDER BY staging_row_id, id'
        );
        $exception->execute([':batch_id' => (int)$batch['id']]);
        return [
            'schema' => 'identity_resolution_snapshot_v1',
            'source_namespace' => $namespace,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'versioned_aliases' => $aliases,
            'legacy_approved_maps' => $legacy,
            'exceptions' => $exception->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function normalizeRules(array $rules): array
    {
        $normalized = [];
        $seen = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                throw new InvalidArgumentException('Each rule snapshot must be an object.');
            }
            $type = trim((string)($rule['rule_type'] ?? ''));
            $key = trim((string)($rule['rule_key'] ?? ''));
            $version = trim((string)($rule['rule_version'] ?? ''));
            if ($type === '' || $key === '' || $version === '' || !array_key_exists('snapshot', $rule)) {
                throw new InvalidArgumentException('Rule type, key, version, and snapshot are required.');
            }
            $effectiveFromRaw = trim((string)($rule['effective_from'] ?? ''));
            $effectiveToRaw = trim((string)($rule['effective_to'] ?? ''));
            $effectiveFrom = $this->validDateOrNull($effectiveFromRaw);
            $effectiveTo = $this->validDateOrNull($effectiveToRaw);
            if (($effectiveFromRaw !== '' && $effectiveFrom === null)
                || ($effectiveToRaw !== '' && $effectiveTo === null)) {
                throw new InvalidArgumentException('Rule effective dates must use Y-m-d.');
            }
            if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveTo < $effectiveFrom) {
                throw new InvalidArgumentException('Rule effective end cannot be before its start.');
            }
            $identity = $type . '|' . $key;
            if (isset($seen[$identity])) {
                throw new InvalidArgumentException('A rule type and key may appear only once per run.');
            }
            $seen[$identity] = true;
            $normalized[] = [
                'rule_type' => $type,
                'rule_key' => $key,
                'rule_version' => $version,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'snapshot' => $rule['snapshot'],
            ];
        }
        usort($normalized, static function (array $left, array $right): int {
            return strcmp($left['rule_type'] . '|' . $left['rule_key'], $right['rule_type'] . '|' . $right['rule_key']);
        });
        return $normalized;
    }

    private function ruleCoverageError(array $rules, string $payDate): ?string
    {
        foreach ($rules as $rule) {
            if ($rule['effective_from'] === null) {
                return 'Every payroll rule snapshot requires an effective start date.';
            }
            if ((string)$rule['effective_from'] > $payDate
                || ($rule['effective_to'] !== null && (string)$rule['effective_to'] < $payDate)) {
                return sprintf(
                    'Payroll rule %s/%s is not effective for pay date %s.',
                    (string)$rule['rule_type'],
                    (string)$rule['rule_key'],
                    $payDate
                );
            }
        }
        return null;
    }

    private function insertRuleSnapshots(int $runId, array $rules, string $actor): void
    {
        $stmt = $this->db->prepare("\n            INSERT INTO payroll_import_run_rule_versions (\n                run_id, rule_type, rule_key, rule_version, effective_from, effective_to,\n                rule_snapshot, rule_hash, captured_by, captured_at\n            ) VALUES (\n                :run_id, :rule_type, :rule_key, :rule_version, :effective_from, :effective_to,\n                :rule_snapshot, :rule_hash, :captured_by, NOW()\n            )\n        ");
        foreach ($rules as $rule) {
            $snapshot = self::canonicalJson($rule['snapshot']);
            $stmt->execute([
                ':run_id' => $runId,
                ':rule_type' => $rule['rule_type'],
                ':rule_key' => $rule['rule_key'],
                ':rule_version' => $rule['rule_version'],
                ':effective_from' => $rule['effective_from'],
                ':effective_to' => $rule['effective_to'],
                ':rule_snapshot' => $snapshot,
                ':rule_hash' => hash('sha256', $snapshot),
                ':captured_by' => $actor,
            ]);
        }
    }

    private function seedReleaseChecks(int $runId, bool $hasRules, string $actor): void
    {
        foreach (self::DEFAULT_RELEASE_CHECKS as [$code, $category, $blocking]) {
            $status = 'pending';
            $summary = 'Check has not run.';
            if ($code === 'RULE_VERSION_LOCK') {
                $status = $hasRules ? 'passed' : 'failed';
                $summary = $hasRules ? 'Immutable rule snapshots were captured.' : 'No versioned payroll rules were captured.';
            }
            $this->recordReleaseCheckInternal($runId, $code, $category, (bool)$blocking, $status, $summary, [], $actor);
        }
    }

    private function resolveEmployeeForRow(array $run, array $batch, array $row, string $sourceId): array
    {
        if ($sourceId === '') {
            return ['status' => 'unresolved', 'reason' => 'missing_source_employee_id'];
        }
        $clientId = (int)$run['client_id'];
        $payDate = (string)$run['pay_date'];
        $periodStart = (string)$run['pay_period_start'];
        $periodEnd = (string)$run['pay_period_end'];
        $namespace = (int)($batch['template_id'] ?? 0) > 0
            ? 'template:' . (int)$batch['template_id']
            : 'context:' . (string)$batch['source_context'];

        $exception = $this->db->prepare("\n            SELECT e.id AS exception_id, e.resolved_employee_id\n            FROM dtr_employee_exceptions e\n            WHERE e.staging_row_id = :staging_row_id\n              AND e.batch_id = :batch_id\n              AND e.status = 'resolved'\n              AND e.resolution_type = 'map_existing'\n              AND e.resolved_employee_id IS NOT NULL\n            ORDER BY e.resolved_at DESC, e.id DESC\n            LIMIT 2\n        ");
        $exception->execute([':staging_row_id' => (int)$row['id'], ':batch_id' => (int)$batch['id']]);
        $exceptionRows = $exception->fetchAll(PDO::FETCH_ASSOC);
        if (count($exceptionRows) === 1) {
            $employee = $this->loadEmployee((int)$exceptionRows[0]['resolved_employee_id'], false);
            if ($this->eligibleEmployee($employee, $clientId, $periodStart, $periodEnd)) {
                return ['status' => 'resolved', 'employee' => $employee, 'decision_id' => null, 'method' => 'resolved_exception'];
            }
            return ['status' => 'unresolved', 'reason' => 'resolved_employee_not_eligible_for_payroll_period'];
        }
        if (count($exceptionRows) > 1) {
            return ['status' => 'collision', 'reason' => 'multiple_resolved_exceptions'];
        }

        $normalized = self::normalizeSourceIdentifier($sourceId);
        $alias = $this->db->prepare("\n            SELECT a.decision_id, e.*\n            FROM employee_identity_aliases a\n            INNER JOIN employee_list e ON e.employee_id = a.employee_id\n            WHERE a.client_id = :client_id\n              AND a.source_namespace = :source_namespace\n              AND a.normalized_source_employee_id = :normalized_source_employee_id\n              AND a.alias_status = 'active'\n              AND a.effective_from <= :period_start\n              AND (a.effective_to IS NULL OR a.effective_to >= :period_end)\n            ORDER BY a.version_no DESC, a.id DESC\n            LIMIT 2\n        ");
        $alias->execute([
            ':client_id' => $clientId,
            ':source_namespace' => $namespace,
            ':normalized_source_employee_id' => $normalized,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd,
        ]);
        $aliases = $alias->fetchAll(PDO::FETCH_ASSOC);
        if (count($aliases) === 1 && $this->eligibleEmployee($aliases[0], $clientId, $periodStart, $periodEnd)) {
            return ['status' => 'resolved', 'employee' => $aliases[0], 'decision_id' => (int)$aliases[0]['decision_id'], 'method' => 'versioned_alias'];
        }
        if (count($aliases) > 1) {
            return ['status' => 'collision', 'reason' => 'overlapping_active_aliases'];
        }

        $legacy = $this->db->prepare("\n            SELECT e.*\n            FROM employee_identity_map m\n            INNER JOIN employee_list e ON e.employee_id = m.employee_id\n            WHERE m.client_id = :client_id\n              AND m.source_namespace = :source_namespace\n              AND m.source_employee_id = :source_employee_id\n              AND m.status = 'approved'\n              AND (m.effective_from IS NULL OR m.effective_from <= :period_start)\n              AND (m.effective_to IS NULL OR m.effective_to >= :period_end)\n            LIMIT 2\n        ");
        $legacy->execute([
            ':client_id' => $clientId,
            ':source_namespace' => $namespace,
            ':source_employee_id' => $sourceId,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd,
        ]);
        $legacyRows = $legacy->fetchAll(PDO::FETCH_ASSOC);
        if (count($legacyRows) === 1 && $this->eligibleEmployee($legacyRows[0], $clientId, $periodStart, $periodEnd)) {
            return ['status' => 'resolved', 'employee' => $legacyRows[0], 'decision_id' => null, 'method' => 'legacy_approved_map'];
        }
        if (count($legacyRows) > 1) {
            return ['status' => 'collision', 'reason' => 'multiple_legacy_maps'];
        }

        $sourceType = strtolower(trim((string)($batch['source_type'] ?? '')));
        $sourceContext = strtolower(trim((string)($batch['source_context'] ?? '')));
        if ($sourceType === 'fuji_payroll_summary' || $sourceContext === 'fuji_payroll_summary') {
            return [
                'status' => 'unresolved',
                'reason' => 'approved_identity_mapping_required_for_fuji_vendor_id',
            ];
        }

        $direct = $this->db->prepare("\n            SELECT * FROM employee_list\n            WHERE client_id = :client_id\n              AND (CAST(employee_id AS CHAR) = :source_employee_id\n                   OR payroll_employee_id = :source_employee_id\n                   OR old_employee_id = :source_employee_id)\n            ORDER BY employee_id ASC\n            LIMIT 2\n        ");
        $direct->execute([':client_id' => $clientId, ':source_employee_id' => $sourceId]);
        $directRows = array_values(array_filter($direct->fetchAll(PDO::FETCH_ASSOC), function (array $employee) use ($clientId, $periodStart, $periodEnd): bool {
            return $this->eligibleEmployee($employee, $clientId, $periodStart, $periodEnd);
        }));
        if (count($directRows) === 1) {
            return ['status' => 'resolved', 'employee' => $directRows[0], 'decision_id' => null, 'method' => 'direct_identifier'];
        }
        return [
            'status' => count($directRows) > 1 ? 'collision' : 'unresolved',
            'reason' => count($directRows) > 1 ? 'ambiguous_direct_identifier' : 'no_active_client_scoped_identity',
        ];
    }

    private function canonicalRow(array $run, array $batch, array $row, array $parsed, string $sourceId, array $resolution): array
    {
        $employee = $resolution['employee'];
        $payload = [
            'schema' => 'canonical_dtr_basis_v1',
            'source_adapter' => (string)($parsed['source_adapter'] ?? $batch['source_context']),
            'identity' => [
                'method' => (string)$resolution['method'],
                'decision_id' => $resolution['decision_id'] ? (int)$resolution['decision_id'] : null,
                'source_employee_id' => $sourceId,
                'employee_id' => (int)$employee['employee_id'],
                'payroll_employee_id' => trim((string)($employee['payroll_employee_id'] ?? '')) ?: null,
                'client_id' => (int)$run['client_id'],
            ],
            'values' => $parsed,
        ];
        $payloadJson = self::canonicalJson($payload);
        $name = trim((string)($employee['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($employee['last_name'] ?? '') . ', ' . (string)($employee['first_name'] ?? ''), ', ');
        }
        $sourceName = trim((string)($parsed['employee_name'] ?? $parsed['employee_name_source'] ?? ''));
        $workedHours = $this->numericFrom($parsed, ['hours_worked', 'worked_hours', 'regular_hours']);
        $workedDays = $this->numericFrom($parsed, ['worked_days', 'days_worked']);
        return [
            ':run_id' => (int)$run['id'],
            ':source_batch_id' => (int)$batch['id'],
            ':source_staging_row_id' => (int)$row['id'],
            ':source_row_number' => (int)$row['source_row_number'],
            ':employee_id' => (int)$employee['employee_id'],
            ':identity_decision_id' => $resolution['decision_id'] ?: null,
            ':source_employee_id' => $sourceId,
            ':source_employee_name_snapshot' => $sourceName ?: null,
            ':payroll_employee_id_snapshot' => trim((string)($employee['payroll_employee_id'] ?? '')) ?: null,
            ':employee_name_snapshot' => $name,
            ':client_id' => (int)$run['client_id'],
            ':location_id' => $this->nullablePositiveInt($employee['client_location_id'] ?? $run['location_id'] ?? null),
            ':pay_period_start' => (string)$run['pay_period_start'],
            ':pay_period_end' => (string)$run['pay_period_end'],
            ':pay_date' => (string)$run['pay_date'],
            ':work_date' => $this->validDateOrNull((string)($parsed['work_date'] ?? '')),
            ':time_in' => trim((string)($parsed['time_in'] ?? '')) ?: null,
            ':time_out' => trim((string)($parsed['time_out'] ?? '')) ?: null,
            ':worked_hours' => round($workedHours, 4),
            ':worked_days' => round($workedDays, 4),
            ':normalized_payload' => $payloadJson,
            ':input_payload_hash' => hash('sha256', $payloadJson),
        ];
    }

    private function canonicalRowStatement()
    {
        return $this->db->prepare("\n            INSERT INTO payroll_import_run_rows (\n                run_id, source_batch_id, source_staging_row_id, source_row_number,\n                employee_id, identity_decision_id, source_employee_id,\n                source_employee_name_snapshot, payroll_employee_id_snapshot, employee_name_snapshot,\n                client_id, location_id, pay_period_start, pay_period_end, pay_date,\n                work_date, time_in, time_out, worked_hours, worked_days, row_status,\n                identity_status, validation_status, conflict_status, normalized_payload, input_payload_hash\n            ) VALUES (\n                :run_id, :source_batch_id, :source_staging_row_id, :source_row_number,\n                :employee_id, :identity_decision_id, :source_employee_id,\n                :source_employee_name_snapshot, :payroll_employee_id_snapshot, :employee_name_snapshot,\n                :client_id, :location_id, :pay_period_start, :pay_period_end, :pay_date,\n                :work_date, :time_in, :time_out, :worked_hours, :worked_days, 'canonical',\n                'resolved', 'valid', 'clear', :normalized_payload, :input_payload_hash\n            )\n        ");
    }

    private function persistCanonicalBlockers(
        int $runId,
        int $unresolved,
        int $collisions,
        int $validationErrors,
        string $actor,
        array $evidence = []
    ): void {
        $stmt = $this->db->prepare("\n            UPDATE payroll_import_runs\n            SET identity_status = 'blocked', unresolved_identity_count = :unresolved,\n                identity_collision_count = :collisions, validation_error_count = :validation_errors,\n                release_status = 'blocked', lock_version = lock_version + 1\n            WHERE id = :id\n        ");
        $stmt->execute([
            ':unresolved' => max(0, $unresolved),
            ':collisions' => max(0, $collisions),
            ':validation_errors' => max(0, $validationErrors),
            ':id' => $runId,
        ]);
        $this->recordReleaseCheckInternal($runId, 'IDENTITY_RESOLUTION', 'identity', true, 'failed', 'Unresolved or colliding employee identities block canonical handoff.', $evidence, $actor);
        if ($validationErrors > 0) {
            $this->recordReleaseCheckInternal($runId, 'CANONICAL_ROW_INTEGRITY', 'dtr', true, 'failed', 'Invalid staged rows block canonical handoff.', ['invalid_row_count' => $validationErrors], $actor);
        }
        $this->refreshReleaseBlockers($runId);
    }

    private function recordReleaseCheckInternal(
        int $runId,
        string $code,
        string $category,
        bool $blocking,
        string $status,
        string $summary,
        array $evidence,
        string $actor
    ): void {
        $stmt = $this->db->prepare("\n            INSERT INTO payroll_import_release_checks (\n                run_id, check_code, check_category, is_blocking, check_status,\n                summary, evidence_payload, executed_by, executed_at, resolved_by, resolved_at\n            ) VALUES (\n                :run_id, :check_code, :check_category, :is_blocking, :check_status,\n                :summary, :evidence_payload, :executed_by, NOW(), :resolved_by, :resolved_at\n            )\n            ON DUPLICATE KEY UPDATE\n                check_category = VALUES(check_category), is_blocking = VALUES(is_blocking),\n                check_status = VALUES(check_status), summary = VALUES(summary),\n                evidence_payload = VALUES(evidence_payload), executed_by = VALUES(executed_by),\n                executed_at = VALUES(executed_at), resolved_by = VALUES(resolved_by),\n                resolved_at = VALUES(resolved_at)\n        ");
        $resolved = in_array($status, ['passed', 'waived'], true);
        $stmt->execute([
            ':run_id' => $runId,
            ':check_code' => strtoupper($code),
            ':check_category' => $category,
            ':is_blocking' => $blocking ? 1 : 0,
            ':check_status' => $status,
            ':summary' => trim($summary) ?: null,
            ':evidence_payload' => self::canonicalJson($evidence),
            ':executed_by' => trim($actor) ?: null,
            ':resolved_by' => $resolved ? (trim($actor) ?: null) : null,
            ':resolved_at' => $resolved ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private function refreshReleaseBlockers(int $runId): void
    {
        $stmt = $this->db->prepare("\n            SELECT COUNT(*) FROM payroll_import_release_checks\n            WHERE run_id = :run_id AND is_blocking = 1 AND check_status <> 'passed'\n        ");
        $stmt->execute([':run_id' => $runId]);
        $count = (int)$stmt->fetchColumn();
        $update = $this->db->prepare("\n            UPDATE payroll_import_runs\n            SET release_blocker_count = :blocker_count,\n                release_status = CASE\n                    WHEN status = 'released' THEN 'released'\n                    WHEN status = 'approved' AND :blocker_count = 0 THEN 'ready'\n                    ELSE 'blocked'\n                END\n            WHERE id = :run_id\n        ");
        $update->execute([':blocker_count' => $count, ':run_id' => $runId]);
    }

    private function artifactCoverage(int $runId): array
    {
        $employees = $this->db->prepare('SELECT COUNT(DISTINCT employee_id) FROM payroll_import_run_rows WHERE run_id = :run_id');
        $employees->execute([':run_id' => $runId]);
        $employeeCount = (int)$employees->fetchColumn();
        $artifacts = $this->db->prepare("\n            SELECT COUNT(DISTINCT employee_id)\n            FROM payroll_import_payslip_artifacts\n            WHERE run_id = :run_id\n              AND artifact_type = 'payslip_pdf'\n              AND artifact_status IN ('verified', 'published')\n        ");
        $artifacts->execute([':run_id' => $runId]);
        $verified = (int)$artifacts->fetchColumn();
        return [
            'employee_count' => $employeeCount,
            'verified_artifact_count' => $verified,
            'missing_artifact_count' => max(0, $employeeCount - $verified),
            'complete' => $employeeCount > 0 && $verified === $employeeCount,
        ];
    }

    private function loadGateFacts(array $run, string $phase): array
    {
        $checks = $this->releaseChecks((int)$run['id']);
        $excluded = $phase === 'approval'
            ? ['PAYSLIP_ARTIFACT_COVERAGE', 'MAKER_CHECKER_SEPARATION']
            : [];
        $blocking = [];
        foreach ($checks as $check) {
            if ((int)$check['is_blocking'] === 1 && !in_array((string)$check['check_code'], $excluded, true)) {
                $blocking[] = ['code' => (string)$check['check_code'], 'status' => (string)$check['check_status']];
            }
        }
        $coverage = $this->artifactCoverage((int)$run['id']);
        return [
            'run_status' => (string)$run['status'],
            'identity_status' => (string)$run['identity_status'],
            'ruleset_status' => (string)$run['ruleset_status'],
            'calculation_status' => (string)$run['calculation_status'],
            'reconciliation_status' => (string)$run['reconciliation_status'],
            'unresolved_identity_count' => (int)$run['unresolved_identity_count'],
            'identity_collision_count' => (int)$run['identity_collision_count'],
            'validation_error_count' => (int)$run['validation_error_count'],
            'employee_count' => (int)$run['employee_count'],
            'verified_artifact_count' => (int)$coverage['verified_artifact_count'],
            'maker' => (string)$run['maker_created_by'],
            'checker' => (string)($run['checker_approved_by'] ?? ''),
            'blocking_checks' => $blocking,
        ];
    }

    private function releaseChecks(int $runId): array
    {
        $stmt = $this->db->prepare("\n            SELECT check_code, check_category, is_blocking, check_status, summary,\n                   evidence_payload, executed_by, executed_at, resolved_by, resolved_at\n            FROM payroll_import_release_checks\n            WHERE run_id = :run_id\n            ORDER BY check_category, check_code\n        ");
        $stmt->execute([':run_id' => $runId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['is_blocking'] = (int)$row['is_blocking'];
            $row['evidence'] = $this->decode((string)$row['evidence_payload']);
            unset($row['evidence_payload']);
            $rows[] = $row;
        }
        return $rows;
    }

    private function changeStateInternal(array $run, string $toState, string $actor, string $reason): void
    {
        $from = (string)$run['status'];
        if (!self::canTransition($from, $toState)) {
            throw new DomainException('Invalid run transition from ' . $from . ' to ' . $toState . '.');
        }
        $fields = ['status = :status', 'lock_version = lock_version + 1'];
        $params = [':status' => $toState, ':id' => (int)$run['id'], ':from_status' => $from];
        if ($toState === 'failed') {
            $fields[] = 'failure_reason = :reason';
            $params[':reason'] = $reason;
        } elseif ($toState === 'cancelled') {
            $fields[] = 'cancellation_reason = :reason';
            $fields[] = 'cancelled_by = :actor';
            $fields[] = 'cancelled_at = NOW()';
            $params[':reason'] = $reason;
            $params[':actor'] = $actor;
        }
        $stmt = $this->db->prepare('UPDATE payroll_import_runs SET ' . implode(', ', $fields) . ' WHERE id = :id AND status = :from_status');
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Concurrent run state change detected.');
        }
    }

    private function enqueueEvent(int $runId, string $eventType, array $payload): void
    {
        $stmt = $this->db->prepare("\n            INSERT INTO payroll_import_outbox (\n                event_uid, aggregate_type, aggregate_id, event_type, event_payload, event_status, available_at\n            ) VALUES (\n                :event_uid, 'payroll_import_run', :aggregate_id, :event_type, :event_payload, 'pending', NOW()\n            )\n        ");
        $stmt->execute([
            ':event_uid' => $this->uid('PEVT'),
            ':aggregate_id' => $runId,
            ':event_type' => $eventType,
            ':event_payload' => self::canonicalJson($payload),
        ]);
    }

    private function latestIdentityAlias(int $clientId, string $namespace, string $normalized, bool $forUpdate): ?array
    {
        $stmt = $this->db->prepare("\n            SELECT * FROM employee_identity_aliases\n            WHERE client_id = :client_id AND source_namespace = :source_namespace\n              AND normalized_source_employee_id = :normalized_source_employee_id\n            ORDER BY version_no DESC, id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '') . "\n        ");
        $stmt->execute([
            ':client_id' => $clientId,
            ':source_namespace' => $namespace,
            ':normalized_source_employee_id' => $normalized,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function loadEmployee(int $employeeId, bool $forUpdate): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM employee_list WHERE employee_id = :employee_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':employee_id' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function eligibleEmployee(?array $employee, int $clientId, string $periodStart, string $periodEnd): bool
    {
        if ($employee === null
            || (int)$employee['client_id'] !== $clientId
            || $this->validDateOrNull($periodStart) === null
            || $this->validDateOrNull($periodEnd) === null) {
            return false;
        }

        $hireDate = $this->validDateOrNull((string)($employee['hire_date'] ?? ''));
        $separationDate = $this->validDateOrNull((string)(
            $employee['separation_date'] ?? $employee['termination_date'] ?? ''
        ));
        if ($hireDate !== null && $hireDate > $periodEnd) {
            return false;
        }
        if ($separationDate !== null && $separationDate < $periodStart) {
            return false;
        }

        $status = strtolower(trim((string)($employee['status'] ?? '')));
        return $status === 'active'
            || ($status === 'terminated' && $separationDate !== null && $separationDate >= $periodStart);
    }

    private function controlCheckStatus(string $status): string
    {
        return $status === 'passed' ? 'passed' : ($status === 'failed' ? 'failed' : 'pending');
    }

    private function numericFrom(array $payload, array $keys): float
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (float)$payload[$key];
            }
        }
        return 0.0;
    }

    private function decode(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function castRun(?array $run): array
    {
        if (!$run) {
            return [];
        }
        foreach ([
            'id', 'source_batch_id', 'client_id', 'location_id', 'template_id',
            'source_row_count', 'canonical_row_count', 'employee_count',
            'unresolved_identity_count', 'identity_collision_count',
            'validation_error_count', 'release_blocker_count', 'lock_version',
        ] as $field) {
            if (array_key_exists($field, $run) && $run[$field] !== null) {
                $run[$field] = (int)$run[$field];
            }
        }
        return $run;
    }

    private function nullablePositiveInt($value): ?int
    {
        $value = (int)$value;
        return $value > 0 ? $value : null;
    }

    private function validDateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))) {
            return null;
        }
        return $date->format('Y-m-d') === $value ? $value : null;
    }

    private function validDateOrDefault(string $value, string $default): string
    {
        return $this->validDateOrNull($value) ?? $default;
    }

    private function verifiedPayslipArtifactFile(string $configuredPath, string $expectedHash): ?array
    {
        return (new PayslipArtifactStore())->verifyPdf($configuredPath, $expectedHash);
    }

    private function uid(string $prefix): string
    {
        return $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(10));
    }

    private function assertDatabase(): void
    {
        if (!$this->db || !is_object($this->db)) {
            throw new RuntimeException('Database connection is not configured.');
        }
    }

    private function rollback(): void
    {
        if ($this->db && method_exists($this->db, 'inTransaction') && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function audit(string $message): void
    {
        if (function_exists('log_action')) {
            log_action($message, $this->db);
        }
    }

    private function failure(string $code, string $message, array $details = []): array
    {
        $response = ['success' => 0, 'error_code' => $code, 'error' => $message];
        if (count($details) > 0) {
            $response['details'] = $details;
        }
        return $response;
    }
}
