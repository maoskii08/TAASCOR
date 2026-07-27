<?php

/**
 * Shared, fail-closed validation for manual and bulk payroll adjustments.
 *
 * This helper intentionally does not create new business categories or change
 * payroll formulas. It protects the existing Additional/Deduction workflows
 * until those values can be moved to governed, effective-dated reference data.
 */
final class PayrollAdjustmentGuard
{
    private const MAX_AMOUNT = 99999999.99;
    private const MAX_TYPE_LENGTH = 50;
    private const MAX_AUDIT_TEXT_LENGTH = 255;
    public const MAX_ATOMIC_WORKBOOK_ROWS = 1000;
    public const MAX_ATOMIC_WORKBOOK_BYTES = 1048576;

    public static function validateScope(array $input): array
    {
        $client = trim((string)($input['client_name'] ?? $input['client'] ?? ''));
        $cutOff = trim((string)($input['cut_off'] ?? ''));
        $payDay = self::canonicalDate($input['pay_day'] ?? null);
        $startDate = self::canonicalDate($input['start_date'] ?? null);
        $endDate = self::canonicalDate($input['end_date'] ?? null);

        if (
            $client === ''
            || strlen($client) > 190
            || strcasecmp($client, 'No Client') === 0
            || $cutOff === ''
            || strlen($cutOff) > 50
            || $payDay === null
            || $startDate === null
            || $endDate === null
            || $startDate > $endDate
            || $payDay < $endDate
        ) {
            return self::error(
                'invalid_payroll_scope',
                'Select a valid client, payroll period, cutoff, and pay day.'
            );
        }

        return [
            'success' => 1,
            'scope' => [
                'client_name' => $client,
                'cut_off' => $cutOff,
                'pay_day' => $payDay,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }

    public static function validateAdjustment(array $input, string $typeKey): array
    {
        $employeeId = filter_var(
            $input['employee_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $amountRaw = trim((string)($input['amount'] ?? ''));
        $type = self::singleLineText($input[$typeKey] ?? '', self::MAX_TYPE_LENGTH + 1);

        if ($employeeId === false) {
            return self::error('invalid_employee', 'Select an employee from the current DTR population.');
        }
        if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $amountRaw)) {
            return self::error('invalid_amount', 'Amount must be a positive number with no more than two decimal places.');
        }

        $amount = (float)$amountRaw;
        if (!is_finite($amount) || $amount <= 0 || $amount > self::MAX_AMOUNT) {
            return self::error('invalid_amount', 'Amount is outside the supported positive payroll range.');
        }
        if ($type === '' || self::textLength($type) < 2 || self::textLength($type) > self::MAX_TYPE_LENGTH) {
            return self::error('invalid_adjustment_type', 'Enter a clear adjustment type.');
        }

        return [
            'success' => 1,
            'adjustment' => [
                'employee_id' => (int)$employeeId,
                'amount' => number_format($amount, 2, '.', ''),
                $typeKey => $type,
            ],
        ];
    }

    public static function validateAuditContext(array $input, bool $requiresDeleteConfirmation = false): array
    {
        $reason = self::singleLineText($input['change_reason'] ?? '', self::MAX_AUDIT_TEXT_LENGTH + 1);
        $evidence = self::singleLineText($input['evidence_reference'] ?? '', self::MAX_AUDIT_TEXT_LENGTH + 1);
        $confirmation = strtoupper(trim((string)($input['confirmation'] ?? '')));

        if (self::textLength($reason) < 5 || self::textLength($reason) > self::MAX_AUDIT_TEXT_LENGTH) {
            return self::error('reason_required', 'Enter a business reason with at least five characters.');
        }
        if (self::textLength($evidence) < 3 || self::textLength($evidence) > self::MAX_AUDIT_TEXT_LENGTH) {
            return self::error('evidence_required', 'Enter an approval, ticket, or source-file reference.');
        }
        if ($requiresDeleteConfirmation && $confirmation !== 'DELETE') {
            return self::error('delete_confirmation_required', 'Type DELETE to confirm this destructive action.');
        }

        return [
            'success' => 1,
            'audit' => [
                'change_reason' => $reason,
                'evidence_reference' => $evidence,
            ],
        ];
    }

    public static function filterIdOrNull($value, string $label): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException("Invalid {$label} filter.");
        }
        return (int)$id;
    }

    public static function eligibleEmployeeIds($db, array $scope, array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_filter(
            array_map('intval', $employeeIds),
            static fn(int $id): bool => $id > 0
        )));
        if (count($employeeIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $sql = "SELECT DISTINCT d.employee_id
                FROM dtr_upload d
                INNER JOIN employee_list e ON e.employee_id = d.employee_id
                INNER JOIN taascor_client c
                    ON c.client_id = e.client_id
                   AND c.client_name = d.client_name
                   AND c.is_active = 1
                WHERE d.client_name = ?
                  AND d.cut_off = ?
                  AND d.pay_day = ?
                  AND d.start_date = ?
                  AND d.end_date = ?
                  AND e.status = 'Active'
                  AND d.employee_id IN ({$placeholders})";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([
            $scope['client_name'],
            $scope['cut_off'],
            $scope['pay_day'],
            $scope['start_date'],
            $scope['end_date'],
        ], $employeeIds));

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function existingFingerprints(
        $db,
        string $table,
        string $typeColumn,
        array $scope
    ): array {
        $allowed = [
            'payroll_other_additional' => 'type_of_addition',
            'payroll_other_deduction' => 'type_of_deduction',
        ];
        if (($allowed[$table] ?? null) !== $typeColumn) {
            throw new InvalidArgumentException('Unsupported payroll adjustment source.');
        }

        $sql = "SELECT employee_id, amount, {$typeColumn} AS adjustment_type
                FROM {$table}
                WHERE client_name = :client_name
                  AND cut_off = :cut_off
                  AND pay_day = :pay_day
                  AND start_date = :start_date
                  AND end_date = :end_date";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':client_name' => $scope['client_name'],
            ':cut_off' => $scope['cut_off'],
            ':pay_day' => $scope['pay_day'],
            ':start_date' => $scope['start_date'],
            ':end_date' => $scope['end_date'],
        ]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[self::fingerprint(
                (int)$row['employee_id'],
                (string)$row['amount'],
                (string)$row['adjustment_type']
            )] = true;
        }
        return $result;
    }

    /**
     * Recalculate the impacted payroll rows without calling legacy procedures
     * that issue their own COMMIT. The caller's transaction therefore owns the
     * adjustment rows, payroll summary changes, and audit event atomically.
     */
    public static function recalculateScope($db, array $scopeInput, array $employeeIds = []): array
    {
        if (!is_object($db) || !method_exists($db, 'prepare')) {
            return self::error('payroll_recalculation_database_unavailable', 'Payroll recalculation is unavailable.');
        }
        if (method_exists($db, 'inTransaction') && !$db->inTransaction()) {
            return self::error(
                'payroll_recalculation_transaction_required',
                'Payroll recalculation must run inside the adjustment transaction.'
            );
        }
        $scopeResult = self::validateScope($scopeInput);
        if (($scopeResult['success'] ?? 0) !== 1) {
            return $scopeResult;
        }
        $scope = $scopeResult['scope'];
        $employeeIds = array_values(array_unique(array_filter(
            array_map('intval', $employeeIds),
            static fn(int $id): bool => $id > 0
        )));

        try {
            $coverageParams = [
                ':coverage_client' => $scope['client_name'],
                ':coverage_cut_off' => $scope['cut_off'],
                ':coverage_pay_day' => $scope['pay_day'],
                ':coverage_start_date' => $scope['start_date'],
                ':coverage_end_date' => $scope['end_date'],
            ];
            $coverageFilter = '';
            if ($employeeIds !== []) {
                $coveragePlaceholders = [];
                foreach ($employeeIds as $index => $employeeId) {
                    $placeholder = ':coverage_employee_' . $index;
                    $coveragePlaceholders[] = $placeholder;
                    $coverageParams[$placeholder] = $employeeId;
                }
                $coverageFilter = ' AND employee_id IN (' . implode(',', $coveragePlaceholders) . ')';
            }
            $coverageStmt = $db->prepare(
                'SELECT COUNT(DISTINCT employee_id) FROM payroll_summary '
                . 'WHERE client_name = :coverage_client '
                . 'AND cut_off = :coverage_cut_off '
                . 'AND pay_day = :coverage_pay_day '
                . 'AND start_date = :coverage_start_date '
                . 'AND end_date = :coverage_end_date'
                . $coverageFilter
            );
            $coverageStmt->execute($coverageParams);
            $coveredEmployees = (int)$coverageStmt->fetchColumn();
            if ($employeeIds !== [] && $coveredEmployees !== count($employeeIds)) {
                return self::error(
                    'payroll_summary_population_missing',
                    'One or more adjustment employees do not have an exact payroll summary row. No adjustments were applied.'
                );
            }

            $params = [
                ':summary_client' => $scope['client_name'],
                ':summary_cut_off' => $scope['cut_off'],
                ':summary_pay_day' => $scope['pay_day'],
                ':summary_start_date' => $scope['start_date'],
                ':summary_end_date' => $scope['end_date'],
                ':addition_client' => $scope['client_name'],
                ':addition_cut_off' => $scope['cut_off'],
                ':addition_pay_day' => $scope['pay_day'],
                ':addition_start_date' => $scope['start_date'],
                ':addition_end_date' => $scope['end_date'],
                ':deduction_client' => $scope['client_name'],
                ':deduction_cut_off' => $scope['cut_off'],
                ':deduction_pay_day' => $scope['pay_day'],
                ':deduction_start_date' => $scope['start_date'],
                ':deduction_end_date' => $scope['end_date'],
            ];
            $employeeFilter = '';
            if ($employeeIds !== []) {
                $placeholders = [];
                foreach ($employeeIds as $index => $employeeId) {
                    $placeholder = ':summary_employee_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $employeeId;
                }
                $employeeFilter = ' AND p.employee_id IN (' . implode(',', $placeholders) . ')';
            }

            // DISTINCT makes the payroll_summary-derived calculation
            // non-mergeable, so MySQL materializes it before updating the
            // target table instead of raising target-table error 1093.
            $sql = "
                UPDATE payroll_summary ps
                INNER JOIN (
                    SELECT DISTINCT
                        p.employee_id,
                        p.client_name,
                        p.cut_off,
                        p.pay_day,
                        p.start_date,
                        p.end_date,
                        ROUND(
                            (COALESCE(p.gross_income, 0) - COALESCE(p.total_additional, 0))
                            + COALESCE(pa.total_additional, 0),
                            2
                        ) AS new_gross_income,
                        COALESCE(pa.total_additional, 0) AS new_total_additional,
                        COALESCE(pd.total_deduction, 0) AS new_total_deduction,
                        ROUND(
                            ROUND(
                                (COALESCE(p.gross_income, 0) - COALESCE(p.total_additional, 0))
                                + COALESCE(pa.total_additional, 0),
                                2
                            )
                            - ROUND(
                                COALESCE(p.employee_sss, 0)
                                + COALESCE(p.employee_sss_mpf, 0)
                                + COALESCE(p.employee_philhealth, 0)
                                + COALESCE(p.employee_pagibig, 0)
                                + COALESCE(p.employee_tax, 0)
                                + COALESCE(pd.total_deduction, 0)
                                + COALESCE(p.total_tardy, 0)
                                + COALESCE(p.employee_loan, 0),
                                2
                            ),
                            2
                        ) AS new_net_pay
                    FROM payroll_summary p
                    LEFT JOIN (
                        SELECT client_name, employee_id, cut_off, pay_day, start_date, end_date,
                               SUM(amount) AS total_additional
                        FROM payroll_other_additional
                        WHERE client_name = :addition_client
                          AND cut_off = :addition_cut_off
                          AND pay_day = :addition_pay_day
                          AND start_date = :addition_start_date
                          AND end_date = :addition_end_date
                        GROUP BY client_name, employee_id, cut_off, pay_day, start_date, end_date
                    ) pa
                      ON pa.client_name = p.client_name
                     AND pa.employee_id = p.employee_id
                     AND pa.cut_off = p.cut_off
                     AND pa.pay_day = p.pay_day
                     AND pa.start_date = p.start_date
                     AND pa.end_date = p.end_date
                    LEFT JOIN (
                        SELECT client_name, employee_id, cut_off, pay_day, start_date, end_date,
                               SUM(amount) AS total_deduction
                        FROM payroll_other_deduction
                        WHERE client_name = :deduction_client
                          AND cut_off = :deduction_cut_off
                          AND pay_day = :deduction_pay_day
                          AND start_date = :deduction_start_date
                          AND end_date = :deduction_end_date
                        GROUP BY client_name, employee_id, cut_off, pay_day, start_date, end_date
                    ) pd
                      ON pd.client_name = p.client_name
                     AND pd.employee_id = p.employee_id
                     AND pd.cut_off = p.cut_off
                     AND pd.pay_day = p.pay_day
                     AND pd.start_date = p.start_date
                     AND pd.end_date = p.end_date
                    WHERE p.client_name = :summary_client
                      AND p.cut_off = :summary_cut_off
                      AND p.pay_day = :summary_pay_day
                      AND p.start_date = :summary_start_date
                      AND p.end_date = :summary_end_date
                      {$employeeFilter}
                ) calculation
                  ON calculation.client_name = ps.client_name
                 AND calculation.employee_id = ps.employee_id
                 AND calculation.cut_off = ps.cut_off
                 AND calculation.pay_day = ps.pay_day
                 AND calculation.start_date = ps.start_date
                 AND calculation.end_date = ps.end_date
                SET ps.gross_income = calculation.new_gross_income,
                    ps.total_additional = calculation.new_total_additional,
                    ps.total_deduction = calculation.new_total_deduction,
                    ps.net_pay = calculation.new_net_pay
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($employeeIds !== [] && $stmt->rowCount() < 1) {
                return self::error(
                    'payroll_recalculation_no_change',
                    'Payroll recalculation did not update the expected employee rows. No adjustments were applied.'
                );
            }
            return [
                'success' => 1,
                'recalculated_employee_count' => $employeeIds !== [] ? count($employeeIds) : $coveredEmployees,
            ];
        } catch (Throwable $error) {
            error_log('PayrollAdjustmentGuard::recalculateScope failed: ' . $error->getMessage());
            return self::error(
                'payroll_recalculation_failed',
                'Payroll recalculation failed. No adjustments were applied.'
            );
        }
    }

    public static function fingerprint(int $employeeId, string $amount, string $type): string
    {
        $normalizedType = trim(preg_replace('/\s+/', ' ', $type));
        $normalizedType = function_exists('mb_strtolower')
            ? mb_strtolower($normalizedType, 'UTF-8')
            : strtolower($normalizedType);
        return implode('|', [
            $employeeId,
            number_format((float)$amount, 2, '.', ''),
            $normalizedType,
        ]);
    }

    public static function auditValue($value): string
    {
        return self::singleLineText($value, self::MAX_AUDIT_TEXT_LENGTH);
    }

    public static function decodeAtomicWorkbookPayload(string $rawBody): array
    {
        $byteCount = strlen($rawBody);
        if ($byteCount === 0) {
            return self::error('empty_workbook_request', 'The workbook upload request is empty.') + ['http_status' => 400];
        }
        if ($byteCount > self::MAX_ATOMIC_WORKBOOK_BYTES) {
            return self::error(
                'workbook_request_too_large',
                'The workbook exceeds the 1 MiB atomic upload limit. Split it into separately approved workbooks.'
            ) + ['http_status' => 413];
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            return self::error('invalid_workbook_json', 'The workbook upload payload is invalid.') + ['http_status' => 400];
        }
        if (!is_array($payload) || ($payload['upload_mode'] ?? '') !== 'atomic-v1') {
            return self::error(
                'atomic_workbook_required',
                'Refresh the page and upload the complete workbook as one atomic request.'
            ) + ['http_status' => 409];
        }

        $data = $payload['data'] ?? null;
        $columnMap = $payload['column_map'] ?? null;
        $expectedRows = filter_var(
            $payload['expected_row_count'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => self::MAX_ATOMIC_WORKBOOK_ROWS]]
        );
        if (
            !is_array($data)
            || !is_array($columnMap)
            || $expectedRows === false
            || count($data) !== (int)$expectedRows
        ) {
            return self::error(
                'incomplete_atomic_workbook',
                'The complete workbook row set and required column mapping must be submitted together.'
            ) + ['http_status' => 422];
        }
        $sourceFilename = self::singleLineText(
            $payload['source_filename'] ?? '',
            self::MAX_AUDIT_TEXT_LENGTH + 1
        );
        if ($sourceFilename === '' || self::textLength($sourceFilename) > self::MAX_AUDIT_TEXT_LENGTH) {
            return self::error('source_filename_required', 'The source workbook filename is required.') + ['http_status' => 422];
        }

        $payload['data'] = array_values($data);
        $payload['column_map'] = $columnMap;
        $payload['expected_row_count'] = (int)$expectedRows;
        $payload['source_filename'] = $sourceFilename;
        return ['success' => 1, 'payload' => $payload, 'request_bytes' => $byteCount];
    }

    public static function validateAtomicWorkbookRows(array $rows): array
    {
        $count = count($rows);
        if ($count < 1 || $count > self::MAX_ATOMIC_WORKBOOK_ROWS) {
            return self::error(
                'workbook_row_limit',
                'The workbook must contain between 1 and ' . self::MAX_ATOMIC_WORKBOOK_ROWS . ' adjustment rows.'
            );
        }
        return ['success' => 1, 'row_count' => $count];
    }

    /**
     * Persist the exact event and normalized row snapshot in the dedicated audit
     * table, plus one compact legacy-log pointer. Both writes occur in the
     * caller's transaction; any failure rolls back the payroll mutation.
     */
    public static function writeAuditRows($db, string $operation, array $context, ?string $username = null): string
    {
        if (!is_object($db) || !method_exists($db, 'prepare')) {
            throw new RuntimeException('Payroll audit database is unavailable.');
        }
        if (method_exists($db, 'inTransaction') && !$db->inTransaction()) {
            throw new RuntimeException('Payroll audit must be written inside the payroll mutation transaction.');
        }

        $allowedOperations = [
            'ADDITION_CREATE' => 'addition',
            'ADDITION_DELETE' => 'addition',
            'ADDITION_BULK' => 'addition',
            'ADDITION_FINALIZE' => 'addition',
            'DEDUCTION_CREATE' => 'deduction',
            'DEDUCTION_DELETE' => 'deduction',
            'DEDUCTION_BULK' => 'deduction',
            'DEDUCTION_FINALIZE' => 'deduction',
        ];
        $operation = strtoupper(trim($operation));
        $adjustmentKind = $allowedOperations[$operation] ?? null;
        if ($adjustmentKind === null) {
            throw new InvalidArgumentException('Unsupported payroll audit operation.');
        }
        $scopeResult = self::validateScope($context);
        $auditResult = self::validateAuditContext($context);
        if (($scopeResult['success'] ?? 0) !== 1 || ($auditResult['success'] ?? 0) !== 1) {
            throw new InvalidArgumentException('Payroll audit scope and evidence must be complete.');
        }
        $scope = $scopeResult['scope'];
        $audit = $auditResult['audit'];
        $eventId = strtoupper(bin2hex(random_bytes(12)));
        $user = self::singleLineText(
            $username ?? (function_exists('auth_user') ? auth_user() : 'system'),
            120
        );
        if ($user === '') {
            $user = 'system';
        }

        $rowRecords = $context['row_records'] ?? [];
        if (!is_array($rowRecords)) {
            throw new InvalidArgumentException('Payroll audit row records are invalid.');
        }
        if ($rowRecords === [] && isset($context['employee_id'])) {
            $rowRecords = [[
                'adjustment_id' => $context['adjustment_id'] ?? null,
                'employee_id' => $context['employee_id'],
                'amount' => $context['amount'] ?? null,
                'type' => $context['type'] ?? null,
            ]];
        }

        $normalizedRows = [];
        foreach (array_values($rowRecords) as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Payroll audit row record is invalid.');
            }
            $validated = self::validateAdjustment($row, 'type');
            if (($validated['success'] ?? 0) !== 1) {
                throw new InvalidArgumentException('Payroll audit row does not match a valid inserted adjustment.');
            }
            $adjustmentId = $row['adjustment_id'] ?? null;
            if ($adjustmentId !== null && $adjustmentId !== '') {
                $adjustmentId = filter_var(
                    $adjustmentId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                if ($adjustmentId === false) {
                    throw new InvalidArgumentException('Payroll audit adjustment identifier is invalid.');
                }
            } else {
                $adjustmentId = null;
            }
            $sourceRowNumber = filter_var(
                $row['source_row_number'] ?? ($index + 1),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($sourceRowNumber === false) {
                throw new InvalidArgumentException('Payroll audit source row number is invalid.');
            }
            $normalizedRows[] = [
                'source_row_number' => (int)$sourceRowNumber,
                'adjustment_id' => $adjustmentId === null ? null : (int)$adjustmentId,
                'employee_id' => $validated['adjustment']['employee_id'],
                'amount' => $validated['adjustment']['amount'],
                'type' => $validated['adjustment']['type'],
            ];
        }
        if (str_ends_with($operation, '_CREATE') || str_ends_with($operation, '_DELETE') || str_ends_with($operation, '_BULK')) {
            if ($normalizedRows === []) {
                throw new InvalidArgumentException('Payroll adjustment audit requires at least one exact row record.');
            }
            foreach ($normalizedRows as $row) {
                if ($row['adjustment_id'] === null) {
                    throw new InvalidArgumentException('Payroll adjustment audit requires each exact database row identifier.');
                }
            }
        }

        $sourceFilename = self::singleLineText(
            $context['source_filename'] ?? '',
            self::MAX_AUDIT_TEXT_LENGTH + 1
        );
        if (self::textLength($sourceFilename) > self::MAX_AUDIT_TEXT_LENGTH) {
            throw new InvalidArgumentException('Payroll audit source filename is too long.');
        }
        $rowsPayload = json_encode(
            $normalizedRows,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $hashPayload = json_encode([
            'adjustment_kind' => $adjustmentKind,
            'operation' => $operation,
            'client_name' => $scope['client_name'],
            'cut_off' => $scope['cut_off'],
            'start_date' => $scope['start_date'],
            'end_date' => $scope['end_date'],
            'pay_day' => $scope['pay_day'],
            'change_reason' => $audit['change_reason'],
            'evidence_reference' => $audit['evidence_reference'],
            'source_filename' => $sourceFilename,
            'actor' => $user,
            'rows' => $normalizedRows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payloadHash = hash('sha256', $hashPayload);

        $eventStmt = $db->prepare(
            'INSERT INTO payroll_adjustment_audit_events ('
            . 'event_uid, adjustment_kind, operation, client_name, cut_off, '
            . 'period_start, period_end, pay_day, change_reason, evidence_reference, '
            . 'source_filename, row_count, rows_payload, payload_hash, actor, created_at'
            . ') VALUES ('
            . ':event_uid, :adjustment_kind, :operation, :client_name, :cut_off, '
            . ':period_start, :period_end, :pay_day, :change_reason, :evidence_reference, '
            . ':source_filename, :row_count, :rows_payload, :payload_hash, :actor, NOW()'
            . ')'
        );
        $eventStmt->execute([
            ':event_uid' => $eventId,
            ':adjustment_kind' => $adjustmentKind,
            ':operation' => $operation,
            ':client_name' => $scope['client_name'],
            ':cut_off' => $scope['cut_off'],
            ':period_start' => $scope['start_date'],
            ':period_end' => $scope['end_date'],
            ':pay_day' => $scope['pay_day'],
            ':change_reason' => $audit['change_reason'],
            ':evidence_reference' => $audit['evidence_reference'],
            ':source_filename' => $sourceFilename !== '' ? $sourceFilename : null,
            ':row_count' => count($normalizedRows),
            ':rows_payload' => $rowsPayload,
            ':payload_hash' => $payloadHash,
            ':actor' => $user,
        ]);
        if (method_exists($eventStmt, 'rowCount') && $eventStmt->rowCount() !== 1) {
            throw new RuntimeException('Payroll audit event was not persisted.');
        }

        $legacyAction = "PA|{$eventId}|{$operation}|rows=" . count($normalizedRows);
        if (strlen($legacyAction) > 100) {
            throw new RuntimeException('Payroll legacy audit pointer exceeds the supported storage limit.');
        }
        $legacyStmt = $db->prepare(
            'INSERT INTO logs (username, log_action, inserted_date_time_ph) VALUES (?, ?, NOW())'
        );
        $legacyStmt->execute([$user, $legacyAction]);
        if (method_exists($legacyStmt, 'rowCount') && $legacyStmt->rowCount() !== 1) {
            throw new RuntimeException('Payroll legacy audit pointer was not persisted.');
        }

        return $eventId;
    }

    private static function canonicalDate($value): ?string
    {
        $raw = trim((string)$value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$date
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d') !== $raw
        ) {
            return null;
        }
        return $raw;
    }

    private static function singleLineText($value, int $maxLength): string
    {
        $text = trim((string)$value);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength, 'UTF-8');
        }
        return substr($text, 0, $maxLength);
    }

    private static function textLength(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private static function error(string $code, string $message): array
    {
        return [
            'success' => 0,
            'code' => $code,
            'error' => $message,
        ];
    }
}
