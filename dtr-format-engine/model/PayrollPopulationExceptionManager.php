<?php

declare(strict_types=1);

final class PayrollPopulationExceptionManager
{
    public $db = null;

    private const RESOLVED_DISPOSITIONS = [
        'dtr_added_to_superseding_batch',
        'approved_off_cycle',
        'approved_adjustment',
        'reference_exclusion',
    ];

    public function listForBatch(int $batchId, string $status = 'all'): array
    {
        if ($batchId <= 0 || !in_array($status, ['all', 'open', 'resolved'], true)) {
            return $this->failure('INVALID_REQUEST', 'Select a valid batch and exception status.');
        }
        try {
            $sql = "\n                SELECT p.id, p.exception_uid, p.batch_id, p.client_id, p.reference_type,
                       p.reference_employee_name, p.matched_employee_id, p.exception_code,
                       p.severity, p.status, p.disposition, p.evidence_payload,
                       p.resolution_reason, p.created_by, p.created_at, p.resolved_by,
                       p.resolved_at, c.client_name, e.payroll_employee_id,
                       e.first_name, e.last_name, e.status AS employee_status
                FROM payroll_population_exceptions p
                INNER JOIN taascor_client c ON c.client_id = p.client_id
                LEFT JOIN employee_list e ON e.employee_id = p.matched_employee_id
                WHERE p.batch_id = :batch_id"
                . ($status === 'all' ? '' : "\n                  AND p.status = :status")
                . "\n                ORDER BY FIELD(p.status, 'open', 'resolved'), p.reference_employee_name, p.id";
            $stmt = $this->db->prepare($sql);
            $params = [':batch_id' => $batchId];
            if ($status !== 'all') {
                $params[':status'] = $status;
            }
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['id'] = (int)$row['id'];
                $row['batch_id'] = (int)$row['batch_id'];
                $row['client_id'] = (int)$row['client_id'];
                $row['matched_employee_id'] = $row['matched_employee_id'] === null
                    ? null
                    : (int)$row['matched_employee_id'];
                $row['evidence'] = $this->decode((string)($row['evidence_payload'] ?? ''));
                unset($row['evidence_payload']);
            }
            unset($row);
            return [
                'success' => 1,
                'data' => $rows,
                'summary' => [
                    'total' => count($rows),
                    'open' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'open')),
                    'resolved' => count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'resolved')),
                ],
            ];
        } catch (Throwable $error) {
            error_log('Payroll population exception listing failed: ' . $error->getMessage());
            return $this->failure('POPULATION_EXCEPTION_LIST_FAILED', 'Unable to load payroll population exceptions.');
        }
    }

    public function importForBatch(int $batchId, array $records, string $actor): array
    {
        $actor = trim($actor);
        if ($batchId <= 0 || $actor === '' || count($records) === 0 || count($records) > 5000) {
            return $this->failure('INVALID_REQUEST', 'A batch, import owner, and bounded exception list are required.');
        }
        try {
            $this->db->beginTransaction();
            $batch = $this->db->prepare("\n                SELECT b.id, COALESCE(b.client_id, t.client_id) AS client_id
                FROM dtr_upload_batches b
                INNER JOIN dtr_format_templates t ON t.id = b.template_id
                WHERE b.id = :batch_id
                LIMIT 1
                FOR UPDATE
            ");
            $batch->execute([':batch_id' => $batchId]);
            $batchRow = $batch->fetch(PDO::FETCH_ASSOC);
            if (!$batchRow || (int)$batchRow['client_id'] <= 0) {
                $this->db->rollBack();
                return $this->failure('BATCH_NOT_FOUND', 'The selected staged batch was not found.');
            }
            $clientId = (int)$batchRow['client_id'];
            $employeeCheck = $this->db->prepare(
                'SELECT COUNT(*) FROM employee_list WHERE employee_id = :employee_id AND client_id = :client_id'
            );
            $insert = $this->db->prepare("\n                INSERT INTO payroll_population_exceptions (
                    exception_uid, batch_id, client_id, reference_type,
                    reference_employee_name, normalized_reference_name,
                    matched_employee_id, exception_code, severity, status,
                    evidence_payload, created_by
                ) VALUES (
                    :exception_uid, :batch_id, :client_id, 'expected_payslip',
                    :reference_employee_name, :normalized_reference_name,
                    :matched_employee_id, 'PAYSLIP_WITHOUT_DTR', 'P0', 'open',
                    :evidence_payload, :created_by
                )
                ON DUPLICATE KEY UPDATE
                    matched_employee_id = VALUES(matched_employee_id),
                    evidence_payload = VALUES(evidence_payload),
                    updated_at = NOW()
            ");
            $imported = 0;
            foreach ($records as $record) {
                if (!is_array($record)) {
                    throw new InvalidArgumentException('Every population exception must be an object.');
                }
                $name = trim((string)($record['reference_employee_name'] ?? ''));
                $normalized = self::normalizeName($name);
                $employeeId = (int)($record['matched_employee_id'] ?? 0);
                if ($name === '' || mb_strlen($name) > 255 || $normalized === '') {
                    throw new InvalidArgumentException('Every population exception needs a valid reference employee name.');
                }
                if ($employeeId > 0) {
                    $employeeCheck->execute([':employee_id' => $employeeId, ':client_id' => $clientId]);
                    if ((int)$employeeCheck->fetchColumn() !== 1) {
                        throw new DomainException('A matched employee is missing or belongs to another client.');
                    }
                }
                $evidence = is_array($record['evidence'] ?? null) ? $record['evidence'] : [];
                $evidence['source'] = (string)($evidence['source'] ?? 'expected_payslip_reconciliation');
                $identity = implode('|', [$batchId, $clientId, 'expected_payslip', $normalized, 'PAYSLIP_WITHOUT_DTR']);
                $insert->execute([
                    ':exception_uid' => 'PPEX-' . strtoupper(substr(hash('sha256', $identity), 0, 24)),
                    ':batch_id' => $batchId,
                    ':client_id' => $clientId,
                    ':reference_employee_name' => $name,
                    ':normalized_reference_name' => $normalized,
                    ':matched_employee_id' => $employeeId > 0 ? $employeeId : null,
                    ':evidence_payload' => json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':created_by' => $actor,
                ]);
                $imported++;
            }
            $this->db->commit();
            return ['success' => 1, 'imported' => $imported] + $this->listForBatch($batchId);
        } catch (Throwable $error) {
            if ($this->db instanceof PDO && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Payroll population exception import failed: ' . $error->getMessage());
            return $this->failure(
                'POPULATION_EXCEPTION_IMPORT_FAILED',
                $error instanceof InvalidArgumentException || $error instanceof DomainException
                    ? $error->getMessage()
                    : 'Unable to import payroll population exceptions.'
            );
        }
    }

    public function resolve(int $exceptionId, string $disposition, string $reason, string $actor): array
    {
        $disposition = trim($disposition);
        $reason = trim($reason);
        $actor = trim($actor);
        if ($exceptionId <= 0 || !in_array($disposition, self::RESOLVED_DISPOSITIONS, true)) {
            return $this->failure('INVALID_DISPOSITION', 'Select a valid population disposition.');
        }
        if (mb_strlen($reason) < 20 || mb_strlen($reason) > 1000 || $actor === '') {
            return $this->failure('INVALID_REASON', 'Enter an owner decision reason of 20 to 1000 characters.');
        }
        try {
            $stmt = $this->db->prepare("\n                UPDATE payroll_population_exceptions
                SET status = 'resolved', disposition = :disposition,
                    resolution_reason = :resolution_reason,
                    resolved_by = :resolved_by, resolved_at = NOW(), updated_at = NOW()
                WHERE id = :id AND status = 'open'
            ");
            $stmt->execute([
                ':disposition' => $disposition,
                ':resolution_reason' => $reason,
                ':resolved_by' => $actor,
                ':id' => $exceptionId,
            ]);
            if ($stmt->rowCount() !== 1) {
                return $this->failure('EXCEPTION_NOT_OPEN', 'Only an open population exception can be resolved.');
            }
            return ['success' => 1, 'exception_id' => $exceptionId, 'status' => 'resolved'];
        } catch (Throwable $error) {
            error_log('Payroll population exception resolution failed: ' . $error->getMessage());
            return $this->failure('POPULATION_EXCEPTION_RESOLUTION_FAILED', 'Unable to resolve the population exception.');
        }
    }

    public function openCountForBatch(int $batchId): int
    {
        $stmt = $this->db->prepare("\n            SELECT COUNT(*) FROM payroll_population_exceptions
            WHERE batch_id = :batch_id AND severity = 'P0' AND status = 'open'
        ");
        $stmt->execute([':batch_id' => $batchId]);
        return (int)$stmt->fetchColumn();
    }

    public static function normalizeName(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function failure(string $code, string $error): array
    {
        return ['success' => 0, 'error_code' => $code, 'error' => $error];
    }
}
