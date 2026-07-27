<?php

class DataQuality
{
    public $db = null;
    public array $allowed_client_ids = [];
    public bool $allow_all_clients = false;

    public function getSummary(): array
    {
        try {
            $items = [
                $this->item('payroll_without_dtr_basis', 'Payroll without DTR basis', 'High', $this->countPayrollWithoutDtr(), 'Payroll rows with no matching DTR row by employee, client, pay day, cutoff, and period.'),
                $this->item('negative_net_pay', 'Negative net pay', 'High', $this->countNegativeNetPay(), 'Payroll rows where net pay is below zero.'),
                $this->item('orphan_additions', 'Orphan additions', 'Medium', $this->countOrphanAdditions(), 'Additional-pay rows without a matching payroll summary row.'),
                $this->item('orphan_deductions', 'Orphan deductions', 'Medium', $this->countOrphanDeductions(), 'Deduction rows without a matching payroll summary row.'),
                $this->item('payroll_period_mismatch', 'Payroll-period mismatch', 'Medium', $this->countPayrollPeriodMismatch(), 'Payroll rows whose period differs from matching DTR or gross-variable rows.'),
                $this->item('employee_client_mismatch', 'Employee/client mismatch', 'Medium', $this->countEmployeeClientMismatch(), 'Payroll rows whose employee client assignment does not match the payroll client.'),
                $this->item('unroutable_notification_owners', 'Unroutable payroll notification owners', 'High', $this->countUnroutableNotificationOwners(), 'Active Admin, HR, and Payroll notification owners need a valid email address; all three roles receive cross-client events.'),
                $this->item('user_client_access_mismatch', 'User-client access mismatch', 'Medium', $this->countUserClientAccessMismatch(), 'Active Coordinator or C&B access rows containing missing or inactive client assignments.'),
                $this->item('locked_payroll_inconsistency', 'Locked payroll inconsistency', 'Medium', $this->countLockedPayrollInconsistency(), 'Locked payroll records without matching payroll summary rows.'),
                $this->item('missing_dtr_provenance', 'Missing DTR provenance', 'Medium', $this->countMissingDtrProvenance(), 'DTR rows missing import lineage fields.'),
            ];

            return [
                'success' => 1,
                'generated_at' => date('Y-m-d H:i:s'),
                'mode' => 'read_only',
                'exports_enabled' => false,
                'data' => $items,
                'totals' => [
                    'checks' => count($items),
                    'open_findings' => array_sum(array_column($items, 'count')),
                ],
            ];
        } catch (Throwable $error) {
            error_log('Payroll Data Quality summary failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to load Payroll Data Quality summary. Please contact your administrator.'];
        }
    }

    public function getDetails(string $key, int $limit = 100): array
    {
        $catalog = $this->correctionCatalog();
        if (!isset($catalog[$key])) {
            throw new InvalidArgumentException('Unknown Payroll Data Quality finding.');
        }

        $limit = max(1, min(200, $limit));
        $method = 'details' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException('Details are not available for this finding.');
        }

        try {
            $rows = $this->{$method}($limit);
            $countMethod = 'count' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
            $total = method_exists($this, $countMethod) ? (int)$this->{$countMethod}() : count($rows);
            $meta = $catalog[$key];

            if ($key === 'missing_dtr_provenance' && !$this->tableHasColumns(
                'dtr_upload',
                ['import_batch_id', 'source_filename', 'uploaded_by', 'uploaded_at', 'source_checksum']
            )) {
                $meta['blocked'] = true;
                $meta['guidance'] = 'The local DTR table does not yet contain the required lineage columns. Review the DTR workflow and apply the approved schema migration before attempting a governed re-import.';
            }

            return [
                'success' => 1,
                'key' => $key,
                'total' => $total,
                'returned' => count($rows),
                'truncated' => $total > count($rows),
                'correction' => $meta,
                'data' => $rows,
            ];
        } catch (Throwable $error) {
            error_log('Payroll Data Quality details failed: ' . $error->getMessage());
            return ['success' => 0, 'error' => 'Unable to load finding details. Please contact your administrator.'];
        }
    }

    private function item(string $key, string $label, string $severity, int $count, string $note): array
    {
        return ['key' => $key, 'label' => $label, 'severity' => $severity, 'count' => $count, 'status' => $count > 0 ? 'Needs Review' : 'Clear', 'note' => $note];
    }

    private function scalarCount(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function fetchRows(string $sql, array $params, int $limit): array
    {
        $stmt = $this->db->prepare($sql . ' LIMIT ' . max(1, min(200, $limit)));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function correctionCatalog(): array
    {
        return [
            'payroll_without_dtr_basis' => [
                'owner' => 'Payroll',
                'action_label' => 'Open DTR Upload',
                'url' => '../dtr-upload/',
                'guidance' => 'Confirm the employee, client, cutoff, payday, and period. Correct or upload the missing DTR basis, regenerate payroll, then rerun this validation.',
                'admin_only' => false,
                'blocked' => false,
            ],
            'negative_net_pay' => [
                'owner' => 'Payroll',
                'action_label' => 'Review Other Deduction',
                'url' => '../other-deduction/',
                'guidance' => 'Review DTR deductions, other deductions, loans, and statutory amounts against approved evidence. Correct the owning source and regenerate payroll.',
                'admin_only' => false,
                'blocked' => false,
            ],
            'orphan_additions' => [
                'owner' => 'Payroll',
                'action_label' => 'Open Other Additional',
                'url' => '../other-additional/',
                'guidance' => 'Confirm whether the addition belongs to the selected payroll period. Remove or re-import the unsupported entry, or generate the missing payroll row through the normal workflow.',
                'admin_only' => false,
                'blocked' => false,
            ],
            'orphan_deductions' => [
                'owner' => 'Payroll',
                'action_label' => 'Open Other Deduction',
                'url' => '../other-deduction/',
                'guidance' => 'Confirm whether the deduction belongs to the selected payroll period. Remove or re-import the unsupported entry, or generate the missing payroll row through the normal workflow.',
                'admin_only' => false,
                'blocked' => false,
            ],
            'payroll_period_mismatch' => [
                'owner' => 'Payroll',
                'action_label' => 'Open DTR Upload',
                'url' => '../dtr-upload/',
                'guidance' => 'Compare the payroll period with DTR and gross-variable periods. Correct the source period, regenerate payroll, and do not edit aggregate totals directly.',
                'admin_only' => false,
                'blocked' => false,
            ],
            'employee_client_mismatch' => [
                'owner' => 'HR / Payroll',
                'action_label' => 'Update employee',
                'url' => '',
                'guidance' => 'Open the employee record from the affected row and verify the effective client assignment against HR-approved evidence before saving.',
                'admin_only' => false,
                'blocked' => false,
            ],
            'unroutable_notification_owners' => [
                'owner' => 'Admin',
                'action_label' => 'Open User Access',
                'url' => '../users-access/',
                'guidance' => 'An Admin must add a valid business email address to the active notification owner, then rerun validation.',
                'admin_only' => true,
                'blocked' => false,
            ],
            'user_client_access_mismatch' => [
                'owner' => 'Admin',
                'action_label' => 'Open User Access',
                'url' => '../users-access/',
                'guidance' => 'An Admin must correct the user client assignment using active client IDs. Payroll officers can review and escalate this finding but cannot change access.',
                'admin_only' => true,
                'blocked' => false,
            ],
            'locked_payroll_inconsistency' => [
                'owner' => 'Payroll / Admin',
                'action_label' => 'Open DTR Upload',
                'url' => '../dtr-upload/',
                'guidance' => 'Do not remove a payroll lock casually. Confirm the expected payroll summary first, then follow the controlled unlock and regeneration procedure.',
                'admin_only' => false,
                'blocked' => false,
            ],
            'missing_dtr_provenance' => [
                'owner' => 'Payroll / Admin',
                'action_label' => 'Open Payroll Workflow',
                'url' => '../dtr-format-engine/',
                'guidance' => 'Review the source filename, batch, checksum, uploader, and timestamp. Re-stage the DTR through the governed workflow when lineage is incomplete.',
                'admin_only' => false,
                'blocked' => false,
            ],
        ];
    }

    private function clientScope(string $alias): array
    {
        if ($this->allow_all_clients) {
            return ['1 = 1', []];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->allowed_client_ids), static fn(int $id): bool => $id > 0)));
        if (count($ids) === 0) {
            return ['1 = 0', []];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return ["{$alias}.client_name IN (SELECT client_name FROM taascor_client WHERE client_id IN ({$placeholders}))", $ids];
    }

    private function countPayrollWithoutDtr(): int
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->scalarCount("SELECT COUNT(*) FROM payroll_summary ps
            LEFT JOIN dtr_upload d ON d.employee_id = ps.employee_id AND d.client_name = ps.client_name
             AND d.pay_day = ps.pay_day AND d.cut_off = ps.cut_off AND d.start_date = ps.start_date AND d.end_date = ps.end_date
            WHERE d.employee_id IS NULL AND {$scope}", $params);
    }

    private function countNegativeNetPay(): int
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->scalarCount("SELECT COUNT(*) FROM payroll_summary ps WHERE ps.net_pay < 0 AND {$scope}", $params);
    }

    private function countOrphanAdditions(): int
    {
        [$scope, $params] = $this->clientScope('a');
        return $this->scalarCount("SELECT COUNT(*) FROM payroll_other_additional a
            LEFT JOIN payroll_summary ps ON ps.employee_id = a.employee_id AND ps.client_name = a.client_name
             AND ps.pay_day = a.pay_day AND ps.cut_off = a.cut_off AND ps.start_date = a.start_date AND ps.end_date = a.end_date
            WHERE ps.employee_id IS NULL AND {$scope}", $params);
    }

    private function countOrphanDeductions(): int
    {
        [$scope, $params] = $this->clientScope('d');
        return $this->scalarCount("SELECT COUNT(*) FROM payroll_other_deduction d
            LEFT JOIN payroll_summary ps ON ps.employee_id = d.employee_id AND ps.client_name = d.client_name
             AND ps.pay_day = d.pay_day AND ps.cut_off = d.cut_off AND ps.start_date = d.start_date AND ps.end_date = d.end_date
            WHERE ps.employee_id IS NULL AND {$scope}", $params);
    }

    private function countPayrollPeriodMismatch(): int
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->scalarCount("SELECT COUNT(*) FROM payroll_summary ps WHERE {$scope} AND (
            EXISTS (SELECT 1 FROM dtr_upload d WHERE d.employee_id = ps.employee_id AND d.client_name = ps.client_name
              AND d.pay_day = ps.pay_day AND d.cut_off = ps.cut_off AND (d.start_date <> ps.start_date OR d.end_date <> ps.end_date))
            OR EXISTS (SELECT 1 FROM payroll_gross_variables g WHERE g.employee_id = ps.employee_id AND g.client_name = ps.client_name
              AND g.pay_day = ps.pay_day AND g.cut_off = ps.cut_off AND (g.start_date <> ps.start_date OR g.end_date <> ps.end_date))
        )", $params);
    }

    private function countEmployeeClientMismatch(): int
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->scalarCount("SELECT COUNT(*) FROM payroll_summary ps
            LEFT JOIN employee_list e ON e.employee_id = ps.employee_id
            LEFT JOIN taascor_client c ON c.client_id = e.client_id
            WHERE {$scope} AND (e.employee_id IS NULL OR c.client_id IS NULL OR c.client_name <> ps.client_name)", $params);
    }

    private function countUserClientAccessMismatch(): int
    {
        $params = [];
        $userScope = '1 = 1';
        if (!$this->allow_all_clients) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $this->allowed_client_ids), static fn(int $id): bool => $id > 0)));
            $parts = [];
            foreach ($ids as $id) {
                $parts[] = "FIND_IN_SET(CAST(? AS CHAR) COLLATE utf8mb4_general_ci, REPLACE(COALESCE(ua.client, ''), ' ', '')) > 0";
                $params[] = $id;
            }
            $userScope = count($parts) > 0 ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
        }
        return $this->scalarCount("SELECT COUNT(*) FROM taascor_user_access ua
            WHERE ua.is_active = 1 AND ua.access_level IN (4, 5) AND {$userScope}
              AND (
                TRIM(COALESCE(ua.client, '')) = ''
                OR REPLACE(ua.client, ' ', '') NOT REGEXP '^[0-9]+(,[0-9]+)*$'
                OR (
                    1 + LENGTH(REPLACE(ua.client, ' ', ''))
                      - LENGTH(REPLACE(REPLACE(ua.client, ' ', ''), ',', ''))
                ) <> (
                    SELECT COUNT(*)
                    FROM taascor_client c
                    WHERE c.is_active = 1
                      AND FIND_IN_SET(CAST(c.client_id AS CHAR) COLLATE utf8mb4_general_ci, REPLACE(ua.client, ' ', '')) > 0
                )
              )", $params);
    }

    private function countUnroutableNotificationOwners(): int
    {
        $params = [];
        $userScope = $this->userClientScope($params);
        return $this->scalarCount("SELECT COUNT(*) FROM taascor_user_access ua
            WHERE ua.is_active = 1
              AND ua.access_level IN (1, 2, 3)
              AND {$userScope}
              AND (
                TRIM(COALESCE(ua.employee_email, '')) = ''
                OR ua.employee_email NOT LIKE '%_@_%._%'
              )", $params);
    }

    private function userClientScope(array &$params): string
    {
        if ($this->allow_all_clients) {
            return '1 = 1';
        }
        $parts = [];
        foreach ($this->allowed_client_ids as $clientId) {
            if ((int)$clientId <= 0) {
                continue;
            }
            $parts[] = "FIND_IN_SET(CAST(? AS CHAR) COLLATE utf8mb4_general_ci, REPLACE(COALESCE(ua.client, ''), ' ', '')) > 0";
            $params[] = (int)$clientId;
        }
        return count($parts) > 0 ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
    }

    private function countLockedPayrollInconsistency(): int
    {
        [$scope, $params] = $this->clientScope('lp');
        return $this->scalarCount("SELECT COUNT(*) FROM locked_payroll lp WHERE {$scope}
            AND NOT EXISTS (SELECT 1 FROM payroll_summary ps WHERE ps.client_name = lp.client_name AND ps.pay_day = lp.pay_day)", $params);
    }

    private function countMissingDtrProvenance(): int
    {
        [$scope, $params] = $this->clientScope('d');
        $columns = ['import_batch_id', 'source_filename', 'uploaded_by', 'uploaded_at', 'source_checksum'];
        if (!$this->tableHasColumns('dtr_upload', $columns)) {
            return $this->scalarCount("SELECT COUNT(*) FROM dtr_upload d WHERE {$scope}", $params);
        }
        return $this->scalarCount("SELECT COUNT(*) FROM dtr_upload d WHERE {$scope} AND (
            d.import_batch_id IS NULL OR d.source_filename IS NULL OR d.source_filename = ''
            OR d.uploaded_by IS NULL OR d.uploaded_at IS NULL OR d.source_checksum IS NULL OR d.source_checksum = ''
        )", $params);
    }

    private function detailsPayrollWithoutDtrBasis(int $limit): array
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->fetchRows("SELECT ps.employee_id, e.status AS employee_status, COALESCE(NULLIF(e.full_name, ''), 'Employee not found') AS employee_name,
                ps.client_name, ps.pay_day, ps.cut_off, ps.start_date, ps.end_date,
                'No matching DTR basis' AS issue_value,
                'No DTR row matches employee, client, payday, cutoff, and period.' AS evidence
            FROM payroll_summary ps
            LEFT JOIN dtr_upload d ON d.employee_id = ps.employee_id AND d.client_name = ps.client_name
             AND d.pay_day = ps.pay_day AND d.cut_off = ps.cut_off AND d.start_date = ps.start_date AND d.end_date = ps.end_date
            LEFT JOIN employee_list e ON e.employee_id = ps.employee_id
            WHERE d.employee_id IS NULL AND {$scope}
            ORDER BY ps.pay_day DESC, ps.client_name, ps.employee_id", $params, $limit);
    }

    private function detailsNegativeNetPay(int $limit): array
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->fetchRows("SELECT ps.employee_id, e.status AS employee_status, COALESCE(NULLIF(e.full_name, ''), 'Employee not found') AS employee_name,
                ps.client_name, ps.pay_day, ps.cut_off, ps.start_date, ps.end_date,
                CONCAT('Net pay: ', FORMAT(ps.net_pay, 2)) AS issue_value,
                CONCAT('Gross ', FORMAT(ps.gross_income, 2), '; other deductions ', FORMAT(ps.total_deduction, 2),
                    '; loans ', FORMAT(ps.employee_loan, 2), '; tardy ', FORMAT(ps.total_tardy, 2)) AS evidence
            FROM payroll_summary ps
            LEFT JOIN employee_list e ON e.employee_id = ps.employee_id
            WHERE ps.net_pay < 0 AND {$scope}
            ORDER BY ps.pay_day DESC, ps.client_name, ps.net_pay ASC", $params, $limit);
    }

    private function detailsOrphanAdditions(int $limit): array
    {
        [$scope, $params] = $this->clientScope('a');
        return $this->fetchRows("SELECT a.employee_id, e.status AS employee_status, COALESCE(NULLIF(e.full_name, ''), 'Employee not found') AS employee_name,
                a.client_name, a.pay_day, a.cut_off, a.start_date, a.end_date,
                CONCAT(COALESCE(NULLIF(a.type_of_addition, ''), 'Additional pay'), ': ', FORMAT(a.amount, 2)) AS issue_value,
                CONCAT('Additional row #', a.id, ' has no matching payroll summary row.') AS evidence
            FROM payroll_other_additional a
            LEFT JOIN payroll_summary ps ON ps.employee_id = a.employee_id AND ps.client_name = a.client_name
             AND ps.pay_day = a.pay_day AND ps.cut_off = a.cut_off AND ps.start_date = a.start_date AND ps.end_date = a.end_date
            LEFT JOIN employee_list e ON e.employee_id = a.employee_id
            WHERE ps.employee_id IS NULL AND {$scope}
            ORDER BY a.pay_day DESC, a.client_name, a.employee_id", $params, $limit);
    }

    private function detailsOrphanDeductions(int $limit): array
    {
        [$scope, $params] = $this->clientScope('d');
        return $this->fetchRows("SELECT d.employee_id, e.status AS employee_status, COALESCE(NULLIF(e.full_name, ''), 'Employee not found') AS employee_name,
                d.client_name, d.pay_day, d.cut_off, d.start_date, d.end_date,
                CONCAT(COALESCE(NULLIF(d.type_of_deduction, ''), 'Deduction'), ': ', FORMAT(d.amount, 2)) AS issue_value,
                CONCAT('Deduction row #', d.id, ' has no matching payroll summary row.') AS evidence
            FROM payroll_other_deduction d
            LEFT JOIN payroll_summary ps ON ps.employee_id = d.employee_id AND ps.client_name = d.client_name
             AND ps.pay_day = d.pay_day AND ps.cut_off = d.cut_off AND ps.start_date = d.start_date AND ps.end_date = d.end_date
            LEFT JOIN employee_list e ON e.employee_id = d.employee_id
            WHERE ps.employee_id IS NULL AND {$scope}
            ORDER BY d.pay_day DESC, d.client_name, d.employee_id", $params, $limit);
    }

    private function detailsPayrollPeriodMismatch(int $limit): array
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->fetchRows("SELECT ps.employee_id, e.status AS employee_status, COALESCE(NULLIF(e.full_name, ''), 'Employee not found') AS employee_name,
                ps.client_name, ps.pay_day, ps.cut_off, ps.start_date, ps.end_date,
                CONCAT('Payroll period ', ps.start_date, ' to ', ps.end_date) AS issue_value,
                CONCAT(
                    'DTR: ', COALESCE((SELECT GROUP_CONCAT(DISTINCT CONCAT(d.start_date, ' to ', d.end_date) SEPARATOR ', ')
                        FROM dtr_upload d WHERE d.employee_id = ps.employee_id AND d.client_name = ps.client_name
                        AND d.pay_day = ps.pay_day AND d.cut_off = ps.cut_off), 'none'),
                    '; gross variables: ', COALESCE((SELECT GROUP_CONCAT(DISTINCT CONCAT(g.start_date, ' to ', g.end_date) SEPARATOR ', ')
                        FROM payroll_gross_variables g WHERE g.employee_id = ps.employee_id AND g.client_name = ps.client_name
                        AND g.pay_day = ps.pay_day AND g.cut_off = ps.cut_off), 'none')
                ) AS evidence
            FROM payroll_summary ps
            LEFT JOIN employee_list e ON e.employee_id = ps.employee_id
            WHERE {$scope} AND (
                EXISTS (SELECT 1 FROM dtr_upload d WHERE d.employee_id = ps.employee_id AND d.client_name = ps.client_name
                    AND d.pay_day = ps.pay_day AND d.cut_off = ps.cut_off AND (d.start_date <> ps.start_date OR d.end_date <> ps.end_date))
                OR EXISTS (SELECT 1 FROM payroll_gross_variables g WHERE g.employee_id = ps.employee_id AND g.client_name = ps.client_name
                    AND g.pay_day = ps.pay_day AND g.cut_off = ps.cut_off AND (g.start_date <> ps.start_date OR g.end_date <> ps.end_date))
            )
            ORDER BY ps.pay_day DESC, ps.client_name, ps.employee_id", $params, $limit);
    }

    private function detailsEmployeeClientMismatch(int $limit): array
    {
        [$scope, $params] = $this->clientScope('ps');
        return $this->fetchRows("SELECT ps.employee_id, e.status AS employee_status, COALESCE(NULLIF(e.full_name, ''), 'Employee not found') AS employee_name,
                ps.client_name, ps.pay_day, ps.cut_off, ps.start_date, ps.end_date,
                CONCAT('Payroll client: ', ps.client_name) AS issue_value,
                CONCAT('Employee master client: ', COALESCE(c.client_name, 'missing or inactive')) AS evidence
            FROM payroll_summary ps
            LEFT JOIN employee_list e ON e.employee_id = ps.employee_id
            LEFT JOIN taascor_client c ON c.client_id = e.client_id
            WHERE {$scope} AND (e.employee_id IS NULL OR c.client_id IS NULL OR c.client_name <> ps.client_name)
            ORDER BY ps.pay_day DESC, ps.client_name, ps.employee_id", $params, $limit);
    }

    private function detailsUnroutableNotificationOwners(int $limit): array
    {
        $params = [];
        $scope = $this->userClientScope($params);
        return $this->fetchRows("SELECT NULL AS employee_id, COALESCE(NULLIF(ua.employee_full_name, ''), ua.employee_user_name) AS employee_name,
                '' AS client_name, NULL AS pay_day, '' AS cut_off, NULL AS start_date, NULL AS end_date,
                CONCAT(ua.access_description, ' notification owner') AS issue_value,
                CONCAT('Username ', ua.employee_user_name, '; email ', COALESCE(NULLIF(ua.employee_email, ''), 'missing')) AS evidence
            FROM taascor_user_access ua
            WHERE ua.is_active = 1 AND ua.access_level IN (1, 2, 3) AND {$scope}
              AND (TRIM(COALESCE(ua.employee_email, '')) = '' OR ua.employee_email NOT LIKE '%_@_%._%')
            ORDER BY ua.access_level, ua.employee_full_name", $params, $limit);
    }

    private function detailsUserClientAccessMismatch(int $limit): array
    {
        $params = [];
        $scope = '1 = 1';
        if (!$this->allow_all_clients) {
            $parts = [];
            foreach ($this->allowed_client_ids as $clientId) {
                if ((int)$clientId > 0) {
                    $parts[] = "FIND_IN_SET(CAST(? AS CHAR) COLLATE utf8mb4_general_ci, REPLACE(COALESCE(ua.client, ''), ' ', '')) > 0";
                    $params[] = (int)$clientId;
                }
            }
            $scope = count($parts) > 0 ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
        }
        return $this->fetchRows("SELECT NULL AS employee_id, COALESCE(NULLIF(ua.employee_full_name, ''), ua.employee_user_name) AS employee_name,
                '' AS client_name, NULL AS pay_day, '' AS cut_off, NULL AS start_date, NULL AS end_date,
                CONCAT(ua.access_description, ' client scope') AS issue_value,
                CONCAT('Username ', ua.employee_user_name, '; stored client IDs: ', COALESCE(NULLIF(ua.client, ''), 'missing')) AS evidence
            FROM taascor_user_access ua
            WHERE ua.is_active = 1 AND ua.access_level IN (4, 5) AND {$scope}
              AND (
                TRIM(COALESCE(ua.client, '')) = ''
                OR REPLACE(ua.client, ' ', '') NOT REGEXP '^[0-9]+(,[0-9]+)*$'
                OR (1 + LENGTH(REPLACE(ua.client, ' ', '')) - LENGTH(REPLACE(REPLACE(ua.client, ' ', ''), ',', '')))
                   <> (SELECT COUNT(*) FROM taascor_client c WHERE c.is_active = 1
                       AND FIND_IN_SET(CAST(c.client_id AS CHAR) COLLATE utf8mb4_general_ci, REPLACE(ua.client, ' ', '')) > 0)
              )
            ORDER BY ua.employee_full_name", $params, $limit);
    }

    private function detailsLockedPayrollInconsistency(int $limit): array
    {
        [$scope, $params] = $this->clientScope('lp');
        return $this->fetchRows("SELECT NULL AS employee_id, 'Payroll cycle' AS employee_name,
                lp.client_name, lp.pay_day, '' AS cut_off, NULL AS start_date, NULL AS end_date,
                'Locked cycle without payroll summary' AS issue_value,
                CONCAT('Lock row #', lp.id, ' created ', lp.inserted_date_time_ph) AS evidence
            FROM locked_payroll lp
            WHERE {$scope} AND NOT EXISTS (
                SELECT 1 FROM payroll_summary ps WHERE ps.client_name = lp.client_name AND ps.pay_day = lp.pay_day
            )
            ORDER BY lp.pay_day DESC, lp.client_name", $params, $limit);
    }

    private function detailsMissingDtrProvenance(int $limit): array
    {
        [$scope, $params] = $this->clientScope('d');
        $hasColumns = $this->tableHasColumns(
            'dtr_upload',
            ['import_batch_id', 'source_filename', 'uploaded_by', 'uploaded_at', 'source_checksum']
        );
        $where = $hasColumns
            ? " AND (d.import_batch_id IS NULL OR d.source_filename IS NULL OR d.source_filename = ''
                OR d.uploaded_by IS NULL OR d.uploaded_at IS NULL OR d.source_checksum IS NULL OR d.source_checksum = '')"
            : '';
        $issue = $hasColumns
            ? "CONCAT_WS(', ',
                IF(d.import_batch_id IS NULL, 'batch', NULL),
                IF(d.source_filename IS NULL OR d.source_filename = '', 'filename', NULL),
                IF(d.uploaded_by IS NULL, 'uploader', NULL),
                IF(d.uploaded_at IS NULL, 'timestamp', NULL),
                IF(d.source_checksum IS NULL OR d.source_checksum = '', 'checksum', NULL))"
            : "'Required provenance columns are unavailable'";
        $evidence = $hasColumns
            ? "CONCAT('Missing lineage fields: ', {$issue})"
            : "'The current DTR schema cannot record batch, filename, uploader, timestamp, and checksum lineage.'";
        return $this->fetchRows("SELECT d.employee_id, e.status AS employee_status, COALESCE(NULLIF(e.full_name, ''), 'Employee not found') AS employee_name,
                d.client_name, d.pay_day, d.cut_off, d.start_date, d.end_date,
                {$issue} AS issue_value, {$evidence} AS evidence
            FROM dtr_upload d
            LEFT JOIN employee_list e ON e.employee_id = d.employee_id
            WHERE {$scope}{$where}
            ORDER BY d.pay_day DESC, d.client_name, d.employee_id", $params, $limit);
    }

    private function tableHasColumns(string $table, array $columns): bool
    {
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        return $this->scalarCount("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ({$placeholders})", array_merge([$table], $columns)) === count($columns);
    }
}
