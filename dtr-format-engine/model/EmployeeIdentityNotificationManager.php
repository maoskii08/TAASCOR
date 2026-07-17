<?php

class EmployeeIdentityNotificationManager
{
    public $db = null;

    private const P0_CODES = [
        'MISSING_HRIS_EMPLOYEE',
        'EMPLOYEE_MAPPING_REVIEW',
        'HRIS_STATUS_CONFLICT',
    ];

    public function syncBatch(int $batchId, string $user): array
    {
        try {
            $batch = $this->loadBatch($batchId);
            if (!$batch) {
                return ['success' => 0, 'error' => 'Staged DTR batch was not found.'];
            }

            $rows = $this->loadBatchRows($batchId);
            $preserved = $this->preservedRowStatuses($batchId);
            $currentKeys = [];

            foreach ($rows as $row) {
                $stagingRowId = (int)$row['staging_row_id'];
                if (($preserved[$stagingRowId] ?? '') === 'excluded') {
                    continue;
                }

                $parsed = $this->decodePayload((string)$row['parsed_payload']);
                $raw = $this->decodePayload((string)$row['raw_payload']);
                $sourceId = trim((string)($parsed['employee_identifier'] ?? ''));
                $sourceName = $this->extractEmployeeName($parsed, $raw);
                $resolution = $this->resolveIdentity($batch, $sourceId, $sourceName);

                if ($resolution['status'] === 'matched') {
                    $this->clearIdentityValidationErrors($stagingRowId);
                    continue;
                }

                $code = (string)$resolution['exception_code'];
                $currentKeys[$stagingRowId . '|' . $code] = true;
                $this->upsertException(
                    $batchId,
                    $stagingRowId,
                    $code,
                    $sourceId,
                    $sourceName,
                    $resolution,
                    $user
                );
            }

            $this->autoResolveStaleExceptions($batchId, $currentKeys, $user);
            $counts = $this->openCounts($batchId);
            $openP0 = array_sum($counts);
            $this->updateBatchGate($batchId, $openP0);
            $notification = $this->syncNotification($batch, $counts, $openP0);
            $this->syncRecipients((int)$notification['notification_id'], (string)$batch['uploaded_by']);

            if (function_exists('log_action')) {
                log_action(
                    'DTR employee identity sync: batch ' . $batchId . ', open P0 ' . $openP0,
                    $this->db
                );
            }

            return [
                'success' => 1,
                'batch_id' => $batchId,
                'batch_uid' => (string)$batch['batch_uid'],
                'gate_status' => $openP0 > 0 ? 'blocked' : 'ready',
                'open_p0_count' => $openP0,
                'counts' => $counts,
                'notification_id' => (int)$notification['notification_id'],
            ];
        } catch (Throwable $error) {
            error_log('Employee identity batch sync failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to validate DTR employee identities.'];
        }
    }

    public function listExceptions(int $batchId = 0, string $status = 'open'): array
    {
        try {
            $where = [];
            $params = [];
            if ($batchId > 0) {
                $where[] = 'e.batch_id = :batch_id';
                $params[':batch_id'] = $batchId;
            }
            if ($status !== 'all') {
                $where[] = 'e.status = :status';
                $params[':status'] = $status;
            }
            $filter = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $this->db->prepare("\n                SELECT\n                    e.id, e.batch_id, e.staging_row_id, e.exception_code, e.severity,\n                    e.source_employee_id, e.source_employee_name,\n                    e.suggested_employee_id, e.suggested_employee_name, e.candidate_payload,\n                    e.status, e.resolution_type, e.resolved_employee_id,\n                    e.resolution_reason, e.created_at, e.resolved_by, e.resolved_at,\n                    r.source_row_number, b.batch_uid, b.original_filename, b.uploaded_at,\n                    b.validation_status AS batch_validation_status,\n                    b.processing_status AS batch_processing_status,\n                    t.template_name, t.client_id, c.client_name\n                FROM dtr_employee_exceptions e\n                INNER JOIN dtr_upload_batches b ON b.id = e.batch_id\n                INNER JOIN dtr_upload_staging_rows r ON r.id = e.staging_row_id\n                LEFT JOIN dtr_format_templates t ON t.id = b.template_id\n                LEFT JOIN taascor_client c ON c.client_id = t.client_id\n                $filter\n                ORDER BY\n                    FIELD(e.severity, 'P0', 'P1', 'P2'),\n                    e.batch_id DESC, r.source_row_number ASC\n                LIMIT 2000\n            ");
            $stmt->execute($params);
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row['id'] = (int)$row['id'];
                $row['batch_id'] = (int)$row['batch_id'];
                $row['staging_row_id'] = (int)$row['staging_row_id'];
                $row['source_row_number'] = (int)$row['source_row_number'];
                $row['suggested_employee_id'] = $row['suggested_employee_id'] === null
                    ? null
                    : (int)$row['suggested_employee_id'];
                $row['candidates'] = $this->decodePayload((string)$row['candidate_payload']);
                unset($row['candidate_payload']);
                $rows[] = $row;
            }

            return ['success' => 1, 'data' => $rows, 'summary' => $this->getGateSummary($batchId)];
        } catch (Throwable $error) {
            error_log('Employee identity exception listing failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to load employee identity exceptions.'];
        }
    }

    public function listNotifications(string $user, int $limit = 20): array
    {
        try {
            $limit = max(1, min(50, $limit));
            $stmt = $this->db->prepare("\n                SELECT\n                    n.id, n.event_type, n.severity, n.title, n.message,\n                    n.batch_id, n.client_id, n.open_count, n.target_url, n.payload,\n                    n.status, n.created_at, n.updated_at, n.resolved_at,\n                    r.read_at, r.acknowledged_at\n                FROM notification_recipients r\n                INNER JOIN notification_events n ON n.id = r.notification_id\n                WHERE r.user_name = :user_name\n                ORDER BY (r.read_at IS NULL) DESC, n.updated_at DESC, n.created_at DESC\n                LIMIT $limit\n            ");
            $stmt->execute([':user_name' => $user]);
            $rows = [];
            $unread = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row['id'] = (int)$row['id'];
                $row['batch_id'] = $row['batch_id'] === null ? null : (int)$row['batch_id'];
                $row['client_id'] = $row['client_id'] === null ? null : (int)$row['client_id'];
                $row['open_count'] = (int)$row['open_count'];
                $row['payload'] = $this->decodePayload((string)$row['payload']);
                if ($row['read_at'] === null) {
                    $unread++;
                }
                $rows[] = $row;
            }
            return ['success' => 1, 'unread_count' => $unread, 'data' => $rows];
        } catch (Throwable $error) {
            error_log('Notification listing failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to load notifications.'];
        }
    }

    public function markNotificationRead(int $notificationId, string $user): array
    {
        try {
            $stmt = $this->db->prepare("\n                UPDATE notification_recipients\n                SET read_at = COALESCE(read_at, NOW())\n                WHERE notification_id = :notification_id\n                  AND user_name = :user_name\n            ");
            $stmt->execute([
                ':notification_id' => $notificationId,
                ':user_name' => $user,
            ]);
            return ['success' => 1];
        } catch (Throwable $error) {
            error_log('Notification read update failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to update the notification.'];
        }
    }

    public function resolveException(
        int $exceptionId,
        string $action,
        int $employeeId,
        string $reason,
        string $user
    ): array {
        try {
            $exception = $this->loadException($exceptionId);
            if (!$exception) {
                return ['success' => 0, 'error' => 'Employee identity exception was not found.'];
            }
            if (!in_array($action, ['map_existing', 'exclude'], true)) {
                return ['success' => 0, 'error' => 'Select a supported resolution action.'];
            }
            if ($action === 'exclude' && trim($reason) === '') {
                return ['success' => 0, 'error' => 'An exclusion reason is required.'];
            }

            $this->db->beginTransaction();
            if ($action === 'map_existing') {
                $employee = $this->employeeById($employeeId);
                if (!$employee) {
                    $this->db->rollBack();
                    return ['success' => 0, 'error' => 'The selected HRIS employee was not found.'];
                }
                if (strcasecmp((string)$employee['status'], 'Active') !== 0) {
                    $this->db->rollBack();
                    return ['success' => 0, 'error' => 'Only an active HRIS employee can be mapped.'];
                }
                $clientId = (int)$exception['client_id'];
                if ($clientId > 0 && (int)$employee['client_id'] !== $clientId) {
                    $this->db->rollBack();
                    return ['success' => 0, 'error' => 'The HRIS employee belongs to a different client.'];
                }
                $sourceId = trim((string)$exception['source_employee_id']);
                if ($sourceId === '') {
                    $this->db->rollBack();
                    return ['success' => 0, 'error' => 'A missing source employee ID cannot be mapped.'];
                }
                $map = $this->db->prepare("\n                    INSERT INTO employee_identity_map (\n                        client_id, source_namespace, source_employee_id, employee_id,\n                        status, approved_by, approved_at\n                    ) VALUES (\n                        :client_id, :source_namespace, :source_employee_id, :employee_id,\n                        'approved', :approved_by, NOW()\n                    )\n                    ON DUPLICATE KEY UPDATE\n                        employee_id = VALUES(employee_id),\n                        status = 'approved',\n                        approved_by = VALUES(approved_by),\n                        approved_at = NOW()\n                ");
                $map->execute([
                    ':client_id' => $clientId,
                    ':source_namespace' => $this->sourceNamespace($exception),
                    ':source_employee_id' => $sourceId,
                    ':employee_id' => $employeeId,
                    ':approved_by' => $user,
                ]);
            }

            $update = $this->db->prepare("\n                UPDATE dtr_employee_exceptions\n                SET status = :status,\n                    resolution_type = :resolution_type,\n                    resolved_employee_id = :resolved_employee_id,\n                    resolution_reason = :resolution_reason,\n                    resolved_by = :resolved_by,\n                    resolved_at = NOW()\n                WHERE id = :id\n            ");
            $update->execute([
                ':status' => $action === 'exclude' ? 'excluded' : 'resolved',
                ':resolution_type' => $action,
                ':resolved_employee_id' => $action === 'map_existing' ? $employeeId : null,
                ':resolution_reason' => trim($reason),
                ':resolved_by' => $user,
                ':id' => $exceptionId,
            ]);
            if ($action === 'exclude') {
                $excludeRow = $this->db->prepare("\n                    UPDATE dtr_upload_staging_rows\n                    SET validation_status = 'excluded'\n                    WHERE id = :staging_row_id\n                ");
                $excludeRow->execute([':staging_row_id' => (int)$exception['staging_row_id']]);
            }
            $this->db->commit();

            if (function_exists('log_action')) {
                log_action(
                    'DTR employee identity resolution: exception ' . $exceptionId . ', action ' . $action,
                    $this->db
                );
            }
            return $this->syncBatch((int)$exception['batch_id'], $user);
        } catch (Throwable $error) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Employee identity resolution failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to resolve the employee identity exception.'];
        }
    }

    public function getGateSummary(int $batchId = 0): array
    {
        try {
            $where = "status = 'open' AND severity = 'P0'";
            $params = [];
            if ($batchId > 0) {
                $where .= ' AND batch_id = :batch_id';
                $params[':batch_id'] = $batchId;
            }
            $stmt = $this->db->prepare("\n                SELECT exception_code, COUNT(*) AS exception_count\n                FROM dtr_employee_exceptions\n                WHERE $where\n                GROUP BY exception_code\n                ORDER BY exception_code\n            ");
            $stmt->execute($params);
            $counts = [];
            $open = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $count = (int)$row['exception_count'];
                $counts[(string)$row['exception_code']] = $count;
                $open += $count;
            }
            return [
                'gate_status' => $open > 0 ? 'blocked' : 'ready',
                'open_p0_count' => $open,
                'counts' => $counts,
            ];
        } catch (Throwable $error) {
            error_log('Employee identity gate summary failed: ' . $error->getMessage());
            return [
                'gate_status' => 'blocked',
                'open_p0_count' => 0,
                'counts' => [],
                'error' => 'Unable to verify the employee identity gate.',
            ];
        }
    }

    private function resolveIdentity(array $batch, string $sourceId, string $sourceName): array
    {
        $clientId = (int)$batch['client_id'];
        if ($sourceId === '') {
            return $this->resolution('missing', 'MISSING_HRIS_EMPLOYEE', []);
        }

        $mapped = $this->approvedMap($clientId, $this->sourceNamespace($batch), $sourceId);
        if ($mapped) {
            if (strcasecmp((string)$mapped['status'], 'Active') !== 0 ||
                ($clientId > 0 && (int)$mapped['client_id'] !== $clientId)) {
                return $this->resolution('status_conflict', 'HRIS_STATUS_CONFLICT', [$mapped]);
            }
            return $this->resolution('matched', '', [$mapped]);
        }

        $direct = ['scoped' => [], 'other_client' => []];
        if ((string)($batch['source_type'] ?? '') !== 'fuji_payroll_summary') {
            $direct = $this->directEmployees($sourceId, $clientId);
            foreach ($direct['scoped'] as $employee) {
                if (strcasecmp((string)$employee['status'], 'Active') !== 0) {
                    return $this->resolution('status_conflict', 'HRIS_STATUS_CONFLICT', [$employee]);
                }
                return $this->resolution('matched', '', [$employee]);
            }
        }

        $nameCandidates = $this->nameCandidates($sourceName, $clientId);
        if ($nameCandidates) {
            $top = (float)$nameCandidates[0]['score'];
            $second = isset($nameCandidates[1]) ? (float)$nameCandidates[1]['score'] : 0.0;
            if ($top >= 70.0) {
                $nameCandidates[0]['ambiguous'] = ($top - $second) < 8.0;
                if (strcasecmp((string)$nameCandidates[0]['status'], 'Active') !== 0) {
                    return $this->resolution('status_conflict', 'HRIS_STATUS_CONFLICT', $nameCandidates);
                }
                return $this->resolution('mapping_review', 'EMPLOYEE_MAPPING_REVIEW', $nameCandidates);
            }
        }

        if ($direct['other_client']) {
            return $this->resolution('status_conflict', 'HRIS_STATUS_CONFLICT', $direct['other_client']);
        }

        return $this->resolution('missing', 'MISSING_HRIS_EMPLOYEE', $nameCandidates);
    }

    private function resolution(string $status, string $code, array $candidates): array
    {
        $suggested = $candidates[0] ?? null;
        return [
            'status' => $status,
            'exception_code' => $code,
            'suggested_employee_id' => $suggested ? (int)$suggested['employee_id'] : null,
            'suggested_employee_name' => $suggested ? $this->employeeName($suggested) : null,
            'candidates' => array_map(function ($employee) {
                return [
                    'employee_id' => (int)$employee['employee_id'],
                    'employee_name' => $this->employeeName($employee),
                    'status' => (string)$employee['status'],
                    'client_id' => (int)$employee['client_id'],
                    'score' => isset($employee['score']) ? round((float)$employee['score'], 2) : null,
                    'ambiguous' => !empty($employee['ambiguous']),
                ];
            }, array_slice($candidates, 0, 3)),
        ];
    }

    private function upsertException(
        int $batchId,
        int $stagingRowId,
        string $code,
        string $sourceId,
        string $sourceName,
        array $resolution,
        string $user
    ): void {
        $stmt = $this->db->prepare("\n            INSERT INTO dtr_employee_exceptions (\n                batch_id, staging_row_id, exception_code, severity,\n                source_employee_id, source_employee_name,\n                suggested_employee_id, suggested_employee_name, candidate_payload,\n                status, created_by\n            ) VALUES (\n                :batch_id, :staging_row_id, :exception_code, 'P0',\n                :source_employee_id, :source_employee_name,\n                :suggested_employee_id, :suggested_employee_name, :candidate_payload,\n                'open', :created_by\n            )\n            ON DUPLICATE KEY UPDATE\n                source_employee_id = VALUES(source_employee_id),\n                source_employee_name = VALUES(source_employee_name),\n                suggested_employee_id = VALUES(suggested_employee_id),\n                suggested_employee_name = VALUES(suggested_employee_name),\n                candidate_payload = VALUES(candidate_payload),\n                status = IF(status = 'excluded', status, 'open'),\n                resolution_type = IF(status = 'excluded', resolution_type, NULL),\n                resolved_employee_id = IF(status = 'excluded', resolved_employee_id, NULL),\n                resolution_reason = IF(status = 'excluded', resolution_reason, NULL),\n                resolved_by = IF(status = 'excluded', resolved_by, NULL),\n                resolved_at = IF(status = 'excluded', resolved_at, NULL)\n        ");
        $stmt->execute([
            ':batch_id' => $batchId,
            ':staging_row_id' => $stagingRowId,
            ':exception_code' => $code,
            ':source_employee_id' => $sourceId,
            ':source_employee_name' => $sourceName,
            ':suggested_employee_id' => $resolution['suggested_employee_id'],
            ':suggested_employee_name' => $resolution['suggested_employee_name'],
            ':candidate_payload' => json_encode($resolution['candidates'], JSON_UNESCAPED_UNICODE),
            ':created_by' => $user,
        ]);
    }

    private function autoResolveStaleExceptions(int $batchId, array $currentKeys, string $user): void
    {
        $stmt = $this->db->prepare("\n            SELECT id, staging_row_id, exception_code\n            FROM dtr_employee_exceptions\n            WHERE batch_id = :batch_id AND status = 'open'\n        ");
        $stmt->execute([':batch_id' => $batchId]);
        $update = $this->db->prepare("\n            UPDATE dtr_employee_exceptions\n            SET status = 'auto_resolved', resolution_type = 'revalidated',\n                resolved_by = :resolved_by, resolved_at = NOW()\n            WHERE id = :id\n        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (int)$row['staging_row_id'] . '|' . (string)$row['exception_code'];
            if (!isset($currentKeys[$key])) {
                $update->execute([':resolved_by' => $user, ':id' => (int)$row['id']]);
            }
        }
    }

    private function clearIdentityValidationErrors(int $stagingRowId): void
    {
        $stmt = $this->db->prepare("\n            SELECT error_summary\n            FROM dtr_upload_staging_rows\n            WHERE id = :staging_row_id\n        ");
        $stmt->execute([':staging_row_id' => $stagingRowId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            return;
        }

        $errors = json_decode((string)$raw, true);
        if (!is_array($errors)) {
            $errors = [];
        }
        $errors = array_values(array_filter($errors, static function ($error) {
            return !in_array((string)$error, [
                'missing_employee_identifier',
                'unknown_employee_identifier',
            ], true);
        }));

        $update = $this->db->prepare("\n            UPDATE dtr_upload_staging_rows\n            SET validation_status = :validation_status,\n                error_summary = :error_summary\n            WHERE id = :staging_row_id\n        ");
        $update->execute([
            ':validation_status' => count($errors) === 0 ? 'valid' : 'error',
            ':error_summary' => json_encode($errors, JSON_UNESCAPED_UNICODE),
            ':staging_row_id' => $stagingRowId,
        ]);
    }

    private function updateBatchGate(int $batchId, int $openP0): void
    {
        if ($openP0 > 0) {
            $stmt = $this->db->prepare("\n                UPDATE dtr_upload_batches\n                SET validation_status = 'blocked_employee_identity',\n                    processing_status = 'identity_review_required',\n                    error_count = GREATEST(error_count, :open_count)\n                WHERE id = :batch_id\n            ");
            $stmt->execute([':open_count' => $openP0, ':batch_id' => $batchId]);
            return;
        }

        $errors = $this->db->prepare("\n            SELECT COUNT(*)\n            FROM dtr_upload_staging_rows\n            WHERE batch_id = :batch_id\n              AND validation_status NOT IN ('valid', 'excluded')\n        ");
        $errors->execute([':batch_id' => $batchId]);
        $errorCount = (int)$errors->fetchColumn();
        $stmt = $this->db->prepare("\n            UPDATE dtr_upload_batches\n            SET validation_status = :validation_status,\n                processing_status = :processing_status,\n                error_count = :error_count\n            WHERE id = :batch_id\n        ");
        $stmt->execute([
            ':validation_status' => $errorCount > 0 ? 'failed' : 'passed',
            ':processing_status' => $errorCount > 0 ? 'validation_preview' : 'identity_ready',
            ':error_count' => $errorCount,
            ':batch_id' => $batchId,
        ]);
    }

    private function syncNotification(array $batch, array $counts, int $openP0): array
    {
        $missing = (int)($counts['MISSING_HRIS_EMPLOYEE'] ?? 0);
        $review = (int)($counts['EMPLOYEE_MAPPING_REVIEW'] ?? 0);
        $conflict = (int)($counts['HRIS_STATUS_CONFLICT'] ?? 0);
        $clientName = trim((string)($batch['client_name'] ?? '')) ?: 'DTR';
        $title = $openP0 > 0
            ? $clientName . ' payroll requires employee review'
            : $clientName . ' employee identity review resolved';
        $message = $openP0 > 0
            ? sprintf(
                'Batch %s is blocked: %d missing/no-confident HRIS, %d mapping review, %d status/client conflict.',
                (string)$batch['batch_uid'],
                $missing,
                $review,
                $conflict
            )
            : sprintf('Batch %s has no unresolved employee identity blockers.', (string)$batch['batch_uid']);
        $status = $openP0 > 0 ? 'open' : 'resolved';
        $payload = json_encode([
            'batch_uid' => (string)$batch['batch_uid'],
            'missing_hris' => $missing,
            'mapping_review' => $review,
            'status_conflict' => $conflict,
        ], JSON_UNESCAPED_UNICODE);
        $stmt = $this->db->prepare("\n            INSERT INTO notification_events (\n                fingerprint, event_type, severity, title, message,\n                batch_id, client_id, open_count, target_url, payload, status, resolved_at\n            ) VALUES (\n                :fingerprint, 'DTR_EMPLOYEE_IDENTITY', 'P0', :title, :message,\n                :batch_id, :client_id, :open_count, :target_url, :payload, :status, :resolved_at\n            )\n            ON DUPLICATE KEY UPDATE\n                title = VALUES(title), message = VALUES(message),\n                client_id = VALUES(client_id), open_count = VALUES(open_count),\n                target_url = VALUES(target_url), payload = VALUES(payload),\n                status = VALUES(status), resolved_at = VALUES(resolved_at), updated_at = NOW()\n        ");
        $stmt->execute([
            ':fingerprint' => 'DTR_IDENTITY_BATCH_' . (int)$batch['id'],
            ':title' => $title,
            ':message' => $message,
            ':batch_id' => (int)$batch['id'],
            ':client_id' => (int)$batch['client_id'] > 0 ? (int)$batch['client_id'] : null,
            ':open_count' => $openP0,
            ':target_url' => '../dtr-format-engine/?identity_batch=' . (int)$batch['id'] . '#employee-identity-review',
            ':payload' => $payload,
            ':status' => $status,
            ':resolved_at' => $openP0 > 0 ? null : date('Y-m-d H:i:s'),
        ]);
        $id = (int)$this->db->lastInsertId();
        if ($id === 0) {
            $find = $this->db->prepare('SELECT id FROM notification_events WHERE fingerprint = :fingerprint');
            $find->execute([':fingerprint' => 'DTR_IDENTITY_BATCH_' . (int)$batch['id']]);
            $id = (int)$find->fetchColumn();
        }
        return ['notification_id' => $id];
    }

    private function syncRecipients(int $notificationId, string $uploader): void
    {
        if ($notificationId <= 0) {
            return;
        }
        $users = $this->db->query("\n            SELECT employee_user_name\n            FROM taascor_user_access\n            WHERE access_level = 1 AND is_active = b'1'\n        ")->fetchAll(PDO::FETCH_COLUMN);
        if (trim($uploader) !== '') {
            $users[] = trim($uploader);
        }
        $users = array_values(array_unique(array_filter(array_map('strval', $users))));
        $stmt = $this->db->prepare("\n            INSERT IGNORE INTO notification_recipients (notification_id, user_name)\n            VALUES (:notification_id, :user_name)\n        ");
        foreach ($users as $user) {
            $stmt->execute([':notification_id' => $notificationId, ':user_name' => $user]);
        }
    }

    private function openCounts(int $batchId): array
    {
        $stmt = $this->db->prepare("\n            SELECT exception_code, COUNT(*) AS exception_count\n            FROM dtr_employee_exceptions\n            WHERE batch_id = :batch_id AND status = 'open' AND severity = 'P0'\n            GROUP BY exception_code\n        ");
        $stmt->execute([':batch_id' => $batchId]);
        $counts = array_fill_keys(self::P0_CODES, 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string)$row['exception_code']] = (int)$row['exception_count'];
        }
        return $counts;
    }

    private function approvedMap(int $clientId, string $namespace, string $sourceId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare("\n            SELECT e.*\n            FROM employee_identity_map m\n            INNER JOIN employee_list e ON e.employee_id = m.employee_id\n            WHERE m.client_id = :client_id\n              AND m.source_namespace = :source_namespace\n              AND m.source_employee_id = :source_employee_id\n              AND m.status = 'approved'\n              AND (m.effective_from IS NULL OR m.effective_from <= CURDATE())\n              AND (m.effective_to IS NULL OR m.effective_to >= CURDATE())\n            LIMIT 1\n        ");
        $stmt->execute([
            ':client_id' => $clientId,
            ':source_namespace' => $namespace,
            ':source_employee_id' => $sourceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function directEmployees(string $sourceId, int $clientId): array
    {
        $stmt = $this->db->prepare("\n            SELECT *\n            FROM employee_list\n            WHERE CAST(employee_id AS CHAR) = :identifier\n               OR payroll_employee_id = :identifier\n               OR old_employee_id = :identifier\n            ORDER BY employee_id ASC\n            LIMIT 10\n        ");
        $stmt->execute([':identifier' => $sourceId]);
        $scoped = [];
        $other = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $employee) {
            if ($clientId <= 0 || (int)$employee['client_id'] === $clientId) {
                $scoped[] = $employee;
            } else {
                $other[] = $employee;
            }
        }
        return ['scoped' => $scoped, 'other_client' => $other];
    }

    private function nameCandidates(string $sourceName, int $clientId): array
    {
        $sourceName = trim($sourceName);
        if ($sourceName === '' || $clientId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare("\n            SELECT * FROM employee_list\n            WHERE client_id = :client_id\n            ORDER BY employee_id ASC\n        ");
        $stmt->execute([':client_id' => $clientId]);
        $sourceOrdered = $this->normalizeName($sourceName);
        $sourceTokens = $this->tokenSort($sourceName);
        $candidates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $employee) {
            $variants = [
                trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']),
                trim((string)$employee['last_name'] . ' ' . (string)$employee['first_name']),
                (string)$employee['full_name'],
            ];
            $score = 0.0;
            foreach ($variants as $variant) {
                if (trim($variant) === '') {
                    continue;
                }
                similar_text($sourceOrdered, $this->normalizeName($variant), $orderedScore);
                similar_text($sourceTokens, $this->tokenSort($variant), $tokenScore);
                $score = max($score, $orderedScore, $tokenScore);
            }
            if ($score >= 55.0) {
                $employee['score'] = $score;
                $candidates[] = $employee;
            }
        }
        usort($candidates, static function ($left, $right) {
            return ($right['score'] <=> $left['score']) ?: ((int)$left['employee_id'] <=> (int)$right['employee_id']);
        });
        return array_slice($candidates, 0, 3);
    }

    private function normalizeName(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = $ascii === false ? $value : $ascii;
        $ascii = strtoupper($ascii);
        $ascii = preg_replace('/[^A-Z0-9]+/', ' ', $ascii);
        return trim(preg_replace('/\s+/', ' ', (string)$ascii));
    }

    private function tokenSort(string $value): string
    {
        $parts = array_values(array_filter(explode(' ', $this->normalizeName($value))));
        sort($parts, SORT_STRING);
        return implode(' ', $parts);
    }

    private function employeeName(array $employee): string
    {
        $name = trim((string)($employee['last_name'] ?? '') . ', ' . (string)($employee['first_name'] ?? ''));
        return trim($name, ', ');
    }

    private function extractEmployeeName(array $parsed, array $raw): string
    {
        foreach (['employee_name', 'employee_name_source', 'source_employee_name'] as $key) {
            if (trim((string)($parsed[$key] ?? '')) !== '') {
                return trim((string)$parsed[$key]);
            }
        }
        foreach ($raw as $key => $value) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', (string)$key));
            if (strpos($normalized, 'employee') !== false && strpos($normalized, 'name') !== false) {
                return trim((string)$value);
            }
        }
        return '';
    }

    private function loadBatch(int $batchId): ?array
    {
        $stmt = $this->db->prepare("\n            SELECT b.*, t.client_id, t.template_name, t.source_type, c.client_name\n            FROM dtr_upload_batches b\n            LEFT JOIN dtr_format_templates t ON t.id = b.template_id\n            LEFT JOIN taascor_client c ON c.client_id = t.client_id\n            WHERE b.id = :batch_id\n            LIMIT 1\n        ");
        $stmt->execute([':batch_id' => $batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function loadBatchRows(int $batchId): array
    {
        $stmt = $this->db->prepare("\n            SELECT id AS staging_row_id, source_row_number, raw_payload, parsed_payload\n            FROM dtr_upload_staging_rows\n            WHERE batch_id = :batch_id\n            ORDER BY source_row_number ASC, id ASC\n        ");
        $stmt->execute([':batch_id' => $batchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadException(int $exceptionId): ?array
    {
        $stmt = $this->db->prepare("\n            SELECT e.*, b.template_id, b.source_context, t.client_id\n            FROM dtr_employee_exceptions e\n            INNER JOIN dtr_upload_batches b ON b.id = e.batch_id\n            LEFT JOIN dtr_format_templates t ON t.id = b.template_id\n            WHERE e.id = :id\n            LIMIT 1\n        ");
        $stmt->execute([':id' => $exceptionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function employeeById(int $employeeId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM employee_list WHERE employee_id = :employee_id LIMIT 1');
        $stmt->execute([':employee_id' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function preservedRowStatuses(int $batchId): array
    {
        $stmt = $this->db->prepare("\n            SELECT staging_row_id, status\n            FROM dtr_employee_exceptions\n            WHERE batch_id = :batch_id AND status = 'excluded'\n        ");
        $stmt->execute([':batch_id' => $batchId]);
        $statuses = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $statuses[(int)$row['staging_row_id']] = (string)$row['status'];
        }
        return $statuses;
    }

    private function sourceNamespace(array $batch): string
    {
        $templateId = (int)($batch['template_id'] ?? 0);
        if ($templateId > 0) {
            return 'template:' . $templateId;
        }
        return 'context:' . trim((string)($batch['source_context'] ?? 'dtr'));
    }

    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}
