<?php

declare(strict_types=1);

require_once __DIR__ . '/../../dtr-format-engine/model/PayrollLegacyScopeHasher.php';

class Dashboard
{
    private const MANDATORY_RELEASE_CHECKS = [
        'IDENTITY_RESOLUTION',
        'CANONICAL_ROW_INTEGRITY',
        'RULE_VERSION_LOCK',
        'PAYROLL_CALCULATION',
        'PAYROLL_RECONCILIATION',
        'LEGACY_SCOPE_BINDING',
        'PAYSLIP_ARTIFACT_COVERAGE',
        'MAKER_CHECKER_SEPARATION',
    ];

    /** @var PDO|null */
    public $db = null;

    /** @var array<string,bool> */
    private array $tableAvailability = [];

    public function getClientFilters(): array
    {
        try {
            $activeColumn = $this->columnExists('taascor_client', 'is_active');
            $activeSelect = $activeColumn ? 'COALESCE(is_active, 0) AS is_active' : '1 AS is_active';
            $activeOrder = $activeColumn ? 'COALESCE(is_active, 0) DESC, ' : '';
            $stmt = $this->db->query(
                "SELECT client_id, client_name, {$activeSelect}
                 FROM taascor_client
                 WHERE TRIM(COALESCE(client_name, '')) <> ''
                   AND client_name <> 'No Client'
                 ORDER BY {$activeOrder}client_name"
            );

            return [
                'success' => 1,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
        } catch (Throwable $error) {
            return $this->failure('Unable to load the payroll client filter.');
        }
    }

    public function getPayDateFilters(int $clientId): array
    {
        if ($clientId <= 0) {
            return $this->failure('Select a valid payroll client.');
        }

        try {
            $client = $this->getClient($clientId);
            if ($client === null) {
                return $this->failure('The selected payroll client was not found.');
            }

            $scopes = [];
            $clientName = (string)$client['client_name'];

            if ($this->tableExists('payroll_import_runs')) {
                $stmt = $this->db->prepare(
                    "SELECT pay_date, status, release_status
                     FROM payroll_import_runs
                     WHERE client_id = :client_id
                     ORDER BY pay_date DESC, id DESC"
                );
                $stmt->execute([':client_id' => $clientId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $date = (string)($row['pay_date'] ?? '');
                    $this->registerScopeDate($scopes, $date, 'smart_run');
                    if (
                        $date !== ''
                        && isset($scopes[$date])
                        && $scopes[$date]['run_status'] === null
                    ) {
                        $scopes[$date]['run_status'] = (string)($row['status'] ?? '');
                        $scopes[$date]['release_status'] = (string)($row['release_status'] ?? '');
                    }
                }
            }

            if ($this->tableExists('payroll_summary')) {
                $stmt = $this->db->prepare(
                    'SELECT DISTINCT pay_day FROM payroll_summary WHERE client_name = :client_name'
                );
                $stmt->execute([':client_name' => $clientName]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
                    $this->registerScopeDate($scopes, (string)$date, 'payroll_results');
                }
            }

            if ($this->tableExists('dtr_upload')) {
                $stmt = $this->db->prepare(
                    'SELECT DISTINCT pay_day FROM dtr_upload WHERE client_name = :client_name'
                );
                $stmt->execute([':client_name' => $clientName]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
                    $this->registerScopeDate($scopes, (string)$date, 'dtr_basis');
                }
            }

            if ($this->tableExists('locked_payroll')) {
                $stmt = $this->db->prepare(
                    'SELECT DISTINCT pay_day FROM locked_payroll WHERE client_name = :client_name'
                );
                $stmt->execute([':client_name' => $clientName]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
                    $this->registerScopeDate($scopes, (string)$date, 'posting_lock');
                }
            }

            krsort($scopes);
            $rows = array_values($scopes);

            return [
                'success' => 1,
                'data' => $rows,
                'default_pay_date' => $this->defaultPayDate($rows),
                'client' => $client,
            ];
        } catch (Throwable $error) {
            return $this->failure('Unable to load available payroll dates.');
        }
    }

    public function getSnapshot(int $clientId, string $payDate): array
    {
        if ($clientId <= 0 || !$this->isIsoDate($payDate)) {
            return $this->failure('Select a valid payroll client and pay date.');
        }

        try {
            $client = $this->getClient($clientId);
            if ($client === null) {
                return $this->failure('The selected payroll client was not found.');
            }

            $clientName = (string)$client['client_name'];
            $legacy = $this->legacySummary($clientName, $payDate);
            $dtr = $this->dtrSummary($clientName, $payDate);
            $run = $this->smartRun($clientId, $payDate);
            $lock = $this->postingLock($clientName, $payDate);
            $enrollment = $this->smartEnrollment($clientId);
            $binding = $run === null
                ? self::notApplicableBinding()
                : $this->legacyBindingStatus((int)$run['id'], $clientName, $payDate);
            $checks = $run === null ? [] : $this->releaseChecks((int)$run['id']);
            $populationExceptions = $run === null
                ? 0
                : $this->populationExceptionCount((int)$run['source_batch_id']);
            $quality = $this->scopeQuality($clientName, $payDate, (int)$legacy['row_count']);

            $mode = 'empty';
            if ($run !== null) {
                $mode = 'smart_run';
            } elseif ((int)$legacy['row_count'] > 0) {
                $mode = 'legacy_scope';
            } elseif ((int)$dtr['row_count'] > 0) {
                $mode = 'dtr_only';
            } elseif ($lock !== null) {
                $mode = 'lock_only';
            }

            $blockers = $this->buildBlockers(
                $mode,
                $enrollment,
                $run,
                $binding,
                $checks,
                $populationExceptions,
                $quality,
                $legacy,
                $dtr,
                $lock
            );
            $blockerCount = array_sum(array_map(
                static fn(array $item): int => max(1, (int)($item['count'] ?? 1)),
                $blockers
            ));

            $financialsAvailable = self::financialsAvailableForScope(
                (int)$legacy['row_count'],
                $run !== null,
                $binding
            );
            $financialsStatus = $financialsAvailable
                ? ($run === null ? 'legacy_unverified' : 'governed_binding_verified')
                : ($run !== null && (int)$legacy['row_count'] > 0
                    ? 'governed_binding_invalid'
                    : 'not_available');
            $runState = $this->runState($mode, $run, $lock);
            $releaseState = $this->releaseState($mode, $run, $lock);
            $approval = $this->approvalState($mode, $run);
            $readiness = $this->readinessState($mode, $run, $blockerCount);

            return [
                'success' => 1,
                'state' => $mode === 'empty' ? 'empty' : 'ready',
                'scope' => [
                    'client_id' => $clientId,
                    'client_name' => $clientName,
                    'pay_date' => $payDate,
                    'period_start' => $run['pay_period_start'] ?? $legacy['period_start'] ?? $dtr['period_start'],
                    'period_end' => $run['pay_period_end'] ?? $legacy['period_end'] ?? $dtr['period_end'],
                    'cut_off' => $legacy['cut_off'] ?? $dtr['cut_off'],
                    'mode' => $mode,
                ],
                'metrics' => [
                    'financials_available' => $financialsAvailable,
                    'financials_status' => $financialsStatus,
                    'employee_count' => $financialsAvailable
                        ? (int)$legacy['employee_count']
                        : ((int)$dtr['employee_count'] > 0 ? (int)$dtr['employee_count'] : ($run['employee_count'] ?? null)),
                    'gross_income' => $financialsAvailable ? (float)$legacy['gross_income'] : null,
                    'net_pay' => $financialsAvailable ? (float)$legacy['net_pay'] : null,
                    'employee_deductions' => $financialsAvailable ? (float)$legacy['employee_deductions'] : null,
                    'employer_contributions' => $financialsAvailable ? (float)$legacy['employer_contributions'] : null,
                    'additional_pay' => $financialsAvailable ? (float)$legacy['additional_pay'] : null,
                    'overtime_pay' => $financialsAvailable ? (float)$legacy['overtime_pay'] : null,
                    'deduction_breakdown' => [
                        'statutory_and_tax' => $financialsAvailable ? (float)$legacy['statutory_and_tax'] : null,
                        'employee_loans' => $financialsAvailable ? (float)$legacy['employee_loans'] : null,
                        'other_deductions' => $financialsAvailable ? (float)$legacy['other_deductions'] : null,
                    ],
                ],
                'governance' => [
                    'smart_flow_enabled' => (bool)$enrollment['enabled'],
                    'smart_flow_record_exists' => (bool)$enrollment['record_exists'],
                    'run_state' => $runState,
                    'release_state' => $releaseState,
                    'readiness_state' => $readiness,
                    'is_posted' => $lock !== null,
                    'posted_at' => $lock['inserted_date_time_ph'] ?? null,
                    'approval' => $approval,
                    'legacy_binding' => $binding,
                    'blocker_count' => $blockerCount,
                    'blockers' => $blockers,
                ],
                'run' => $run === null ? null : [
                    'id' => (int)$run['id'],
                    'run_uid' => (string)$run['run_uid'],
                    'status' => (string)$run['status'],
                    'identity_status' => (string)$run['identity_status'],
                    'ruleset_status' => (string)$run['ruleset_status'],
                    'calculation_status' => (string)$run['calculation_status'],
                    'reconciliation_status' => (string)$run['reconciliation_status'],
                    'release_status' => (string)$run['release_status'],
                    'source_row_count' => (int)$run['source_row_count'],
                    'canonical_row_count' => (int)$run['canonical_row_count'],
                    'employee_count' => (int)$run['employee_count'],
                    'source_context' => (string)$run['source_context'],
                    'source_file_name' => (string)$run['source_file_name'],
                    'ruleset_key' => $run['ruleset_key'],
                    'ruleset_version' => $run['ruleset_version'],
                    'maker_created_at' => $run['maker_created_at'],
                    'checker_approved_at' => $run['checker_approved_at'],
                    'released_at' => $run['released_at'],
                    'updated_at' => $run['updated_at'] ?? $run['created_at'],
                ],
                'release_checks' => $checks,
                'quality' => $quality,
                'input' => [
                    'dtr_row_count' => (int)$dtr['row_count'],
                    'dtr_employee_count' => (int)$dtr['employee_count'],
                    'worked_days' => (float)$dtr['worked_days'],
                    'legacy_payroll_row_count' => (int)$legacy['row_count'],
                    'population_exception_count' => $populationExceptions,
                ],
                'contracts' => $this->dataContracts($run, $legacy, $dtr, $lock, $checks, $binding),
                'freshness' => $this->freshness($run, $lock, $financialsAvailable),
            ];
        } catch (Throwable $error) {
            return $this->failure('Unable to load the selected payroll scope.');
        }
    }

    private function getClient(int $clientId): ?array
    {
        $activeSelect = $this->columnExists('taascor_client', 'is_active')
            ? 'COALESCE(is_active, 0) AS is_active'
            : '1 AS is_active';
        $stmt = $this->db->prepare(
            "SELECT client_id, client_name, {$activeSelect}
             FROM taascor_client
             WHERE client_id = :client_id
             LIMIT 1"
        );
        $stmt->execute([':client_id' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function legacySummary(string $clientName, string $payDate): array
    {
        $empty = [
            'row_count' => 0,
            'employee_count' => 0,
            'period_start' => null,
            'period_end' => null,
            'cut_off' => null,
            'gross_income' => 0,
            'net_pay' => 0,
            'employee_deductions' => 0,
            'employer_contributions' => 0,
            'additional_pay' => 0,
            'overtime_pay' => 0,
            'statutory_and_tax' => 0,
            'employee_loans' => 0,
            'other_deductions' => 0,
        ];
        if (!$this->tableExists('payroll_summary')) {
            return $empty;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS row_count,
                    COUNT(DISTINCT employee_id) AS employee_count,
                    MIN(start_date) AS period_start,
                    MAX(end_date) AS period_end,
                    MAX(cut_off) AS cut_off,
                    COALESCE(SUM(gross_income), 0) AS gross_income,
                    COALESCE(SUM(net_pay), 0) AS net_pay,
                    COALESCE(SUM(total_additional), 0) AS additional_pay,
                    COALESCE(SUM(total_ot), 0) AS overtime_pay,
                    COALESCE(SUM(
                        COALESCE(employee_sss, 0)
                        + COALESCE(employee_philhealth, 0)
                        + COALESCE(employee_pagibig, 0)
                        + COALESCE(employee_tax, 0)
                        + COALESCE(employee_sss_mpf, 0)
                        + COALESCE(employee_loan, 0)
                        + COALESCE(total_deduction, 0)
                        + COALESCE(total_tardy, 0)
                    ), 0) AS employee_deductions,
                    COALESCE(SUM(
                        COALESCE(employer_sss, 0)
                        + COALESCE(employer_philhealth, 0)
                        + COALESCE(employer_pagibig, 0)
                        + COALESCE(employer_sss_mpf, 0)
                        + COALESCE(employer_sss_ec, 0)
                    ), 0) AS employer_contributions,
                    COALESCE(SUM(
                        COALESCE(employee_sss, 0)
                        + COALESCE(employee_philhealth, 0)
                        + COALESCE(employee_pagibig, 0)
                        + COALESCE(employee_tax, 0)
                        + COALESCE(employee_sss_mpf, 0)
                    ), 0) AS statutory_and_tax,
                    COALESCE(SUM(employee_loan), 0) AS employee_loans,
                    COALESCE(SUM(total_deduction), 0) AS other_deductions
             FROM payroll_summary
             WHERE client_name = :client_name
               AND pay_day = :pay_date"
        );
        $stmt->execute([
            ':client_name' => $clientName,
            ':pay_date' => $payDate,
        ]);

        return array_merge($empty, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    private function dtrSummary(string $clientName, string $payDate): array
    {
        $empty = [
            'row_count' => 0,
            'employee_count' => 0,
            'period_start' => null,
            'period_end' => null,
            'cut_off' => null,
            'worked_days' => 0,
        ];
        if (!$this->tableExists('dtr_upload')) {
            return $empty;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS row_count,
                    COUNT(DISTINCT employee_id) AS employee_count,
                    MIN(start_date) AS period_start,
                    MAX(end_date) AS period_end,
                    MAX(cut_off) AS cut_off,
                    COALESCE(SUM(daily_worked), 0) AS worked_days
             FROM dtr_upload
             WHERE client_name = :client_name
               AND pay_day = :pay_date"
        );
        $stmt->execute([
            ':client_name' => $clientName,
            ':pay_date' => $payDate,
        ]);

        return array_merge($empty, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    private function smartRun(int $clientId, string $payDate): ?array
    {
        if (!$this->tableExists('payroll_import_runs')) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT *
             FROM payroll_import_runs
             WHERE client_id = :client_id
               AND pay_date = :pay_date
             ORDER BY CASE
                        WHEN status = 'released' AND release_status = 'released' THEN 0
                        WHEN status = 'approved' AND release_status = 'ready' THEN 1
                        ELSE 2
                      END,
                      id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':client_id' => $clientId,
            ':pay_date' => $payDate,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function postingLock(string $clientName, string $payDate): ?array
    {
        if (!$this->tableExists('locked_payroll')) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT id, inserted_date_time_ph
             FROM locked_payroll
             WHERE client_name = :client_name
               AND pay_day = :pay_date
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':client_name' => $clientName,
            ':pay_date' => $payDate,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function smartEnrollment(int $clientId): array
    {
        if (!$this->tableExists('payroll_import_client_settings')) {
            return ['enabled' => false, 'record_exists' => false];
        }

        $stmt = $this->db->prepare(
            'SELECT smart_flow_enabled FROM payroll_import_client_settings WHERE client_id = :client_id LIMIT 1'
        );
        $stmt->execute([':client_id' => $clientId]);
        $value = $stmt->fetchColumn();

        return [
            'enabled' => $value !== false && (int)$value === 1,
            'record_exists' => $value !== false,
        ];
    }

    public function legacyBindingStatus(int $runId, string $clientName, string $payDate): array
    {
        if ($runId <= 0 || trim($clientName) === '' || !$this->isIsoDate($payDate)) {
            return self::bindingFailure(
                'invalid_scope',
                'The governed payroll binding scope is invalid.'
            );
        }
        if (!$this->tableExists('payroll_import_legacy_scope_bindings')) {
            return self::bindingFailure(
                'schema_unavailable',
                'The governed payroll binding schema is unavailable.'
            );
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT run_id, client_name, pay_day, live_snapshot_hash,
                        employee_count, payroll_row_count, snapshot_payload,
                        bound_by, bound_at
                 FROM payroll_import_legacy_scope_bindings
                 WHERE run_id = :run_id
                 LIMIT 1"
            );
            $stmt->execute([':run_id' => $runId]);
            $binding = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($binding)) {
                return self::bindingFailure(
                    'missing',
                    'The selected governed run has no sealed legacy payroll scope.'
                );
            }

            $live = (new PayrollLegacyScopeHasher($this->db))->snapshot($clientName, $payDate);
            return self::evaluateLegacyBindingEvidence($binding, $live, $runId, $clientName, $payDate);
        } catch (Throwable $error) {
            error_log('Dashboard legacy binding verification failed: ' . $error->getMessage());
            return self::bindingFailure(
                'verification_failed',
                'The selected governed run binding could not be verified.'
            );
        }
    }

    public static function evaluateLegacyBindingEvidence(
        array $binding,
        array $live,
        int $runId,
        string $clientName,
        string $payDate
    ): array {
        $storedHash = strtolower(trim((string)($binding['live_snapshot_hash'] ?? '')));
        $payload = (string)($binding['snapshot_payload'] ?? '');
        $liveHash = strtolower(trim((string)($live['live_snapshot_hash'] ?? '')));
        $storedEvidenceValid = preg_match('/^[a-f0-9]{64}$/', $storedHash) === 1
            && $payload !== ''
            && hash_equals($storedHash, hash('sha256', $payload));
        if (!$storedEvidenceValid) {
            return self::bindingFailure(
                'stored_evidence_invalid',
                'The selected governed run has an invalid stored payroll seal.'
            );
        }

        $scopeMatches = (int)($binding['run_id'] ?? 0) === $runId
            && hash_equals(trim((string)($binding['client_name'] ?? '')), trim($clientName))
            && (string)($binding['pay_day'] ?? '') === $payDate;
        if (!$scopeMatches) {
            return self::bindingFailure(
                'scope_mismatch',
                'The governed run seal belongs to a different payroll scope.'
            );
        }

        $liveEvidenceValid = preg_match('/^[a-f0-9]{64}$/', $liveHash) === 1
            && (int)($live['payroll_row_count'] ?? 0) > 0
            && (int)($live['employee_count'] ?? 0) > 0;
        $countsMatch = (int)($binding['employee_count'] ?? -1) === (int)($live['employee_count'] ?? -2)
            && (int)($binding['payroll_row_count'] ?? -1) === (int)($live['payroll_row_count'] ?? -2);
        if (!$liveEvidenceValid || !$countsMatch || !hash_equals($storedHash, $liveHash)) {
            return self::bindingFailure(
                'stale',
                'The live payroll rows no longer match the selected governed run seal.'
            );
        }

        return [
            'verified' => true,
            'state' => 'verified',
            'message' => 'The displayed payroll financials match the selected governed run seal.',
            'bound_by' => $binding['bound_by'] ?? null,
            'bound_at' => $binding['bound_at'] ?? null,
            'employee_count' => (int)$live['employee_count'],
            'payroll_row_count' => (int)$live['payroll_row_count'],
        ];
    }

    public static function financialsAvailableForScope(
        int $legacyRowCount,
        bool $hasGovernedRun,
        array $binding
    ): bool {
        return $legacyRowCount > 0
            && (!$hasGovernedRun || !empty($binding['verified']));
    }

    private function releaseChecks(int $runId): array
    {
        if (!$this->tableExists('payroll_import_release_checks')) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT check_code, check_category, is_blocking, check_status, summary, executed_at, resolved_at
             FROM payroll_import_release_checks
             WHERE run_id = :run_id
             ORDER BY is_blocking DESC, check_category, check_code"
        );
        $stmt->execute([':run_id' => $runId]);

        return array_map(static function (array $row): array {
            return [
                'check_code' => (string)$row['check_code'],
                'check_category' => (string)$row['check_category'],
                'is_blocking' => (bool)$row['is_blocking'],
                'status' => (string)$row['check_status'],
                'summary' => (string)($row['summary'] ?? ''),
                'executed_at' => $row['executed_at'],
                'resolved_at' => $row['resolved_at'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function populationExceptionCount(int $batchId): int
    {
        if ($batchId <= 0 || !$this->tableExists('payroll_population_exceptions')) {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM payroll_population_exceptions
             WHERE batch_id = :batch_id
               AND status NOT IN ('resolved', 'closed')"
        );
        $stmt->execute([':batch_id' => $batchId]);

        return (int)$stmt->fetchColumn();
    }

    private function scopeQuality(string $clientName, string $payDate, int $legacyRowCount): array
    {
        $quality = [
            'payroll_without_dtr_basis' => 0,
            'negative_net_pay' => 0,
            'orphan_additions' => 0,
            'orphan_deductions' => 0,
            'employee_client_mismatch' => 0,
            'locked_payroll_inconsistency' => 0,
        ];

        if ($legacyRowCount > 0 && $this->tableExists('payroll_summary')) {
            if ($this->tableExists('dtr_upload')) {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*)
                     FROM payroll_summary ps
                     LEFT JOIN dtr_upload d
                       ON d.employee_id = ps.employee_id
                      AND d.client_name = ps.client_name
                      AND d.pay_day = ps.pay_day
                      AND d.cut_off = ps.cut_off
                      AND d.start_date = ps.start_date
                      AND d.end_date = ps.end_date
                     WHERE ps.client_name = :client_name
                       AND ps.pay_day = :pay_date
                       AND d.employee_id IS NULL"
                );
                $stmt->execute([':client_name' => $clientName, ':pay_date' => $payDate]);
                $quality['payroll_without_dtr_basis'] = (int)$stmt->fetchColumn();
            }

            $stmt = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM payroll_summary
                 WHERE client_name = :client_name
                   AND pay_day = :pay_date
                   AND net_pay < 0"
            );
            $stmt->execute([':client_name' => $clientName, ':pay_date' => $payDate]);
            $quality['negative_net_pay'] = (int)$stmt->fetchColumn();

            if ($this->tableExists('employee_list')) {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*)
                     FROM payroll_summary ps
                     LEFT JOIN employee_list e ON e.employee_id = ps.employee_id
                     LEFT JOIN taascor_client c ON c.client_id = e.client_id
                     WHERE ps.client_name = :client_name
                       AND ps.pay_day = :pay_date
                       AND (e.employee_id IS NULL OR c.client_id IS NULL OR c.client_name <> ps.client_name)"
                );
                $stmt->execute([':client_name' => $clientName, ':pay_date' => $payDate]);
                $quality['employee_client_mismatch'] = (int)$stmt->fetchColumn();
            }
        }

        foreach ([
            'payroll_other_additional' => 'orphan_additions',
            'payroll_other_deduction' => 'orphan_deductions',
        ] as $table => $key) {
            if (!$this->tableExists($table) || !$this->tableExists('payroll_summary')) {
                continue;
            }
            $stmt = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM `{$table}` a
                 LEFT JOIN payroll_summary ps
                   ON ps.employee_id = a.employee_id
                  AND ps.client_name = a.client_name
                  AND ps.pay_day = a.pay_day
                  AND ps.cut_off = a.cut_off
                  AND ps.start_date = a.start_date
                  AND ps.end_date = a.end_date
                 WHERE a.client_name = :client_name
                   AND a.pay_day = :pay_date
                   AND ps.employee_id IS NULL"
            );
            $stmt->execute([':client_name' => $clientName, ':pay_date' => $payDate]);
            $quality[$key] = (int)$stmt->fetchColumn();
        }

        if ($this->tableExists('locked_payroll') && $this->tableExists('payroll_summary')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM locked_payroll lp
                 WHERE lp.client_name = :client_name
                   AND lp.pay_day = :pay_date
                   AND NOT EXISTS (
                       SELECT 1
                       FROM payroll_summary ps
                       WHERE ps.client_name = lp.client_name
                         AND ps.pay_day = lp.pay_day
                   )"
            );
            $stmt->execute([':client_name' => $clientName, ':pay_date' => $payDate]);
            $quality['locked_payroll_inconsistency'] = (int)$stmt->fetchColumn();
        }

        return $quality;
    }

    private function buildBlockers(
        string $mode,
        array $enrollment,
        ?array $run,
        array $binding,
        array $checks,
        int $populationExceptions,
        array $quality,
        array $legacy,
        array $dtr,
        ?array $lock
    ): array {
        $blockers = [];
        $add = static function (
            array &$items,
            string $code,
            string $label,
            string $severity,
            int $count,
            string $route
        ): void {
            if ($count <= 0) {
                return;
            }
            $items[] = compact('code', 'label', 'severity', 'count', 'route');
        };

        if ($mode !== 'empty' && !$enrollment['enabled']) {
            $add(
                $blockers,
                'SMART_FLOW_NOT_ENABLED',
                'Governed payroll runs are not enabled for this client.',
                'P0',
                1,
                '../dtr-format-engine/'
            );
        } elseif ($mode !== 'empty' && $run === null) {
            $add(
                $blockers,
                'AUTHORITATIVE_RUN_MISSING',
                'No authoritative payroll run exists for this pay date.',
                'P0',
                1,
                '../dtr-format-engine/'
            );
        }

        if ($run !== null) {
            if (empty($binding['verified'])) {
                $add(
                    $blockers,
                    'LEGACY_SCOPE_BINDING_INVALID',
                    (string)($binding['message'] ?? 'The governed run financial binding is missing or stale.'),
                    'P0',
                    1,
                    '../dtr-format-engine/'
                );
            }
            $add($blockers, 'UNRESOLVED_IDENTITIES', 'Employee identities remain unresolved.', 'P0', (int)$run['unresolved_identity_count'], '../dtr-format-engine/#employee-identity-review');
            $add($blockers, 'IDENTITY_COLLISIONS', 'Multiple source employees contend for the same HRIS employee.', 'P0', (int)$run['identity_collision_count'], '../dtr-format-engine/#employee-identity-review');
            $add($blockers, 'VALIDATION_ERRORS', 'Canonical payroll validation errors remain open.', 'P0', (int)$run['validation_error_count'], '../dtr-format-engine/');
            $add($blockers, 'POPULATION_EXCEPTIONS', 'DTR and expected payslip populations do not reconcile.', 'P0', $populationExceptions, '../dtr-format-engine/#payroll-population-review');

            $maker = trim((string)($run['maker_created_by'] ?? ''));
            $checker = trim((string)($run['checker_approved_by'] ?? ''));
            $add(
                $blockers,
                'CHECKER_APPROVAL_MISSING',
                'Independent checker approval is not recorded.',
                'P0',
                $checker === '' ? 1 : 0,
                '../dtr-format-engine/'
            );
            $add(
                $blockers,
                'MAKER_CHECKER_NOT_INDEPENDENT',
                'The payroll maker and checker must be different users.',
                'P0',
                $maker !== '' && $checker !== '' && strcasecmp($maker, $checker) === 0 ? 1 : 0,
                '../dtr-format-engine/'
            );

            $checkStatuses = [];
            $additionalFailedChecks = 0;
            foreach ($checks as $check) {
                $code = strtoupper((string)($check['check_code'] ?? ''));
                $status = strtolower((string)($check['status'] ?? ''));
                if ($code !== '') {
                    $checkStatuses[$code] = $status;
                }
                if (
                    !empty($check['is_blocking'])
                    && $status !== 'passed'
                    && !in_array($code, self::MANDATORY_RELEASE_CHECKS, true)
                ) {
                    $additionalFailedChecks++;
                }
            }
            $mandatoryChecksOpen = 0;
            foreach (self::MANDATORY_RELEASE_CHECKS as $mandatoryCode) {
                if ($mandatoryCode === 'LEGACY_SCOPE_BINDING' && empty($binding['verified'])) {
                    continue;
                }
                if (($checkStatuses[$mandatoryCode] ?? '') !== 'passed') {
                    $mandatoryChecksOpen++;
                }
            }
            $add($blockers, 'MANDATORY_CHECKS_OPEN', 'Mandatory release checks are missing or not passed.', 'P0', $mandatoryChecksOpen, '../dtr-format-engine/');
            $add($blockers, 'OTHER_BLOCKING_CHECKS_OPEN', 'Additional blocking release checks are not passed.', 'P0', $additionalFailedChecks, '../dtr-format-engine/');

            if (
                (int)$run['release_blocker_count'] > 0
                && $mandatoryChecksOpen === 0
                && $additionalFailedChecks === 0
                && !empty($binding['verified'])
            ) {
                $add($blockers, 'RUN_RELEASE_BLOCKERS', 'The payroll run reports unresolved release blockers.', 'P0', (int)$run['release_blocker_count'], '../dtr-format-engine/');
            }
        }

        $add($blockers, 'PAYROLL_WITHOUT_DTR', 'Payroll results have no matching DTR basis.', 'P0', (int)$quality['payroll_without_dtr_basis'], '../payroll-data-quality/');
        $add($blockers, 'NEGATIVE_NET_PAY', 'Employees have negative net pay.', 'P0', (int)$quality['negative_net_pay'], '../payroll-data-quality/');
        $add($blockers, 'EMPLOYEE_CLIENT_MISMATCH', 'Payroll employees do not match the selected client.', 'P0', (int)$quality['employee_client_mismatch'], '../payroll-data-quality/');
        $add($blockers, 'ORPHAN_ADDITIONS', 'Additional-pay rows have no matching payroll result.', 'P1', (int)$quality['orphan_additions'], '../payroll-data-quality/');
        $add($blockers, 'ORPHAN_DEDUCTIONS', 'Deduction rows have no matching payroll result.', 'P1', (int)$quality['orphan_deductions'], '../payroll-data-quality/');
        $add($blockers, 'LOCK_WITHOUT_RESULTS', 'A posting lock exists without payroll results.', 'P0', (int)$quality['locked_payroll_inconsistency'], '../payroll-data-quality/');

        if ($mode === 'dtr_only' && (int)$dtr['row_count'] > 0) {
            $add(
                $blockers,
                'PAYROLL_RESULTS_MISSING',
                'DTR inputs exist, but payroll financial results have not been produced.',
                'P0',
                1,
                '../dtr-format-engine/'
            );
        }

        if ($mode === 'legacy_scope' && (int)$legacy['row_count'] > 0) {
            $add(
                $blockers,
                'LEGACY_RELEASE_EVIDENCE_UNAVAILABLE',
                $lock === null
                    ? 'Legacy payroll is not posted and has no run-level approval evidence.'
                    : 'Legacy posting has no run-level maker/checker or sealed-artifact evidence.',
                'P0',
                1,
                '../dtr-format-engine/'
            );
        }

        return $blockers;
    }

    private function runState(string $mode, ?array $run, ?array $lock): string
    {
        if ($run !== null) {
            return (string)$run['status'];
        }
        if ($lock !== null) {
            return 'posted_legacy';
        }
        if ($mode === 'legacy_scope') {
            return 'calculated_legacy';
        }
        if ($mode === 'dtr_only') {
            return 'inputs_staged';
        }

        return 'not_started';
    }

    private function releaseState(string $mode, ?array $run, ?array $lock): string
    {
        if ($run !== null) {
            return (string)$run['release_status'];
        }
        if ($lock !== null) {
            return 'posted_legacy';
        }
        if ($mode === 'legacy_scope') {
            return 'not_posted';
        }

        return 'not_ready';
    }

    private function approvalState(string $mode, ?array $run): array
    {
        if ($run === null) {
            return [
                'state' => $mode === 'empty' ? 'not_applicable' : 'unavailable',
                'maker' => null,
                'checker' => null,
                'maker_at' => null,
                'checker_at' => null,
                'message' => $mode === 'empty'
                    ? 'No payroll scope is available.'
                    : 'Legacy data does not carry run-level maker/checker evidence.',
            ];
        }

        $maker = trim((string)($run['maker_created_by'] ?? ''));
        $checker = trim((string)($run['checker_approved_by'] ?? ''));
        $independent = $maker !== ''
            && $checker !== ''
            && strcasecmp($maker, $checker) !== 0;
        return [
            'state' => $checker === '' ? 'pending' : ($independent ? 'approved' : 'invalid'),
            'maker' => $maker !== '' ? $maker : null,
            'checker' => $checker !== '' ? $checker : null,
            'maker_at' => $run['maker_created_at'],
            'checker_at' => $run['checker_approved_at'],
            'message' => $checker === ''
                ? 'Independent checker approval is not yet recorded.'
                : ($independent
                    ? 'An independent checker is recorded for this run.'
                    : 'The recorded checker is the payroll maker; approval is invalid.'),
        ];
    }

    private function readinessState(string $mode, ?array $run, int $blockerCount): string
    {
        if ($mode === 'empty') {
            return 'empty';
        }
        if ($mode !== 'smart_run') {
            return 'unverified';
        }
        if ($blockerCount > 0) {
            return 'blocked';
        }
        if (
            strtolower((string)($run['status'] ?? '')) === 'released'
            && strtolower((string)($run['release_status'] ?? '')) === 'released'
        ) {
            return 'released';
        }
        if (
            strtolower((string)($run['status'] ?? '')) === 'approved'
            && strtolower((string)($run['release_status'] ?? '')) === 'ready'
        ) {
            return 'ready';
        }

        return 'blocked';
    }

    private function dataContracts(
        ?array $run,
        array $legacy,
        array $dtr,
        ?array $lock,
        array $checks,
        array $binding
    ): array {
        return [
            [
                'key' => 'governed_run',
                'label' => 'Governed payroll run',
                'available' => $this->tableExists('payroll_import_runs'),
                'record_count' => $run === null ? 0 : 1,
            ],
            [
                'key' => 'legacy_financials',
                'label' => 'Payroll financial results',
                'available' => $this->tableExists('payroll_summary'),
                'record_count' => (int)$legacy['row_count'],
                'verified' => $run === null ? null : !empty($binding['verified']),
                'status' => $run === null ? 'legacy_unverified' : (string)($binding['state'] ?? 'invalid'),
            ],
            [
                'key' => 'dtr_basis',
                'label' => 'DTR basis',
                'available' => $this->tableExists('dtr_upload'),
                'record_count' => (int)$dtr['row_count'],
            ],
            [
                'key' => 'release_checks',
                'label' => 'Mandatory release checks',
                'available' => $this->tableExists('payroll_import_release_checks'),
                'record_count' => count($checks),
            ],
            [
                'key' => 'posting_lock',
                'label' => 'Posting lock',
                'available' => $this->tableExists('locked_payroll'),
                'record_count' => $lock === null ? 0 : 1,
            ],
            [
                'key' => 'population_reconciliation',
                'label' => 'Population reconciliation',
                'available' => $this->tableExists('payroll_population_exceptions'),
                'record_count' => $run === null ? 0 : $this->populationExceptionCount((int)$run['source_batch_id']),
            ],
        ];
    }

    private function freshness(?array $run, ?array $lock, bool $financialsAvailable): array
    {
        if ($run !== null) {
            return [
                'timestamp' => $run['updated_at'] ?? $run['created_at'],
                'source' => 'governed payroll run',
                'message' => 'Run timestamps come from the governed payroll workflow.',
            ];
        }
        if ($lock !== null) {
            return [
                'timestamp' => $lock['inserted_date_time_ph'] ?? null,
                'source' => 'legacy posting lock',
                'message' => 'The timestamp reflects posting, not when payroll inputs were last changed.',
            ];
        }
        if ($financialsAvailable) {
            return [
                'timestamp' => null,
                'source' => 'legacy payroll summary',
                'message' => 'Legacy payroll summary rows do not expose a reliable updated timestamp.',
            ];
        }

        return [
            'timestamp' => null,
            'source' => 'none',
            'message' => 'No payroll run or financial result exists for this scope.',
        ];
    }

    private function registerScopeDate(array &$scopes, string $date, string $source): void
    {
        if (!$this->isIsoDate($date)) {
            return;
        }
        if (!isset($scopes[$date])) {
            $scopes[$date] = [
                'pay_date' => $date,
                'sources' => [],
                'run_status' => null,
                'release_status' => null,
            ];
        }
        if (!in_array($source, $scopes[$date]['sources'], true)) {
            $scopes[$date]['sources'][] = $source;
        }
    }

    private static function notApplicableBinding(): array
    {
        return [
            'verified' => false,
            'state' => 'not_applicable',
            'message' => 'Legacy payroll scopes do not carry governed run binding evidence.',
            'bound_by' => null,
            'bound_at' => null,
            'employee_count' => null,
            'payroll_row_count' => null,
        ];
    }

    private static function bindingFailure(string $state, string $message): array
    {
        return [
            'verified' => false,
            'state' => $state,
            'message' => $message,
            'bound_by' => null,
            'bound_at' => null,
            'employee_count' => null,
            'payroll_row_count' => null,
        ];
    }

    private function defaultPayDate(array $rows): ?string
    {
        if ($rows === []) {
            return null;
        }

        $today = date('Y-m-d');
        $future = array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string)$row['pay_date'] >= $today
        ));
        if ($future !== []) {
            usort($future, static fn(array $a, array $b): int => strcmp((string)$a['pay_date'], (string)$b['pay_date']));
            return (string)$future[0]['pay_date'];
        }

        return (string)$rows[0]['pay_date'];
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableAvailability)) {
            return $this->tableAvailability[$table];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $table]);
        $this->tableAvailability[$table] = (int)$stmt->fetchColumn() > 0;

        return $this->tableAvailability[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name'
        );
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function isIsoDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function failure(string $message): array
    {
        return [
            'success' => 0,
            'error' => $message,
        ];
    }
}
