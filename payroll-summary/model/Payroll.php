<?php

class Payroll
{
    public $db = null;

    public function getPayrollFilters()
    {
        try {
            $coverage = $this->getCanonicalCoverage();
            $clients = $this->db
                ->query(
                    "SELECT DISTINCT client_name
                     FROM payroll_summary
                     WHERE client_name IS NOT NULL AND TRIM(client_name) <> ''
                     ORDER BY client_name"
                )
                ->fetchAll(PDO::FETCH_COLUMN);
            $cutoffs = $this->db
                ->query(
                    "SELECT DISTINCT cut_off
                     FROM payroll_summary
                     WHERE cut_off IS NOT NULL AND TRIM(cut_off) <> ''
                     ORDER BY cut_off"
                )
                ->fetchAll(PDO::FETCH_COLUMN);

            return [
                'success' => 1,
                'data' => [
                    'clients' => array_values($clients),
                    'cutoffs' => array_values($cutoffs),
                    'coverage' => $coverage,
                    'historical_archive' => $this->getHistoricalArchiveStatus(),
                ],
            ];
        } catch (\Throwable $th) {
            return [
                'success' => 0,
                'error' => 'Unable to load Payroll Summary filters. Please contact your administrator.',
            ];
        }
    }

    public function getPayrollSummary(array $filters = [])
    {
        try {
            $coverage = $this->getCanonicalCoverage();
            if (empty($coverage['min_pay_day']) || empty($coverage['max_pay_day'])) {
                return [
                    'success' => 1,
                    'data' => [],
                    'summary' => $this->summarizeRows([]),
                    'scope' => [],
                    'source' => [
                        'canonical' => $coverage,
                        'historical_archive' => $this->getHistoricalArchiveStatus(),
                    ],
                ];
            }

            $dateFrom = $this->validDate($filters['date_from'] ?? '')
                ? $filters['date_from']
                : $coverage['max_pay_day'];
            $dateTo = $this->validDate($filters['date_to'] ?? '')
                ? $filters['date_to']
                : $coverage['max_pay_day'];
            if ($dateFrom > $dateTo) {
                return [
                    'success' => 0,
                    'error' => 'The From date cannot be later than the To date.',
                ];
            }

            $criteria = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'client' => trim((string)($filters['client'] ?? '')),
                'cut_off' => trim((string)($filters['cut_off'] ?? '')),
            ];
            $rows = $this->getAggregateRows($criteria);
            $comparisonScope = $this->comparisonScope($criteria);
            $previousRows = [];
            if (
                $criteria['client'] === ''
                && $criteria['date_from'] === $criteria['date_to']
                && $rows !== []
            ) {
                foreach ($rows as $currentRow) {
                    $clientCriteria = $criteria;
                    $clientCriteria['client'] = $currentRow['client_name'];
                    $clientComparisonScope = $this->comparisonScope($clientCriteria);
                    if ($clientComparisonScope !== null) {
                        $previousRows = array_merge(
                            $previousRows,
                            $this->getAggregateRows($clientComparisonScope)
                        );
                    }
                }
                $comparisonScope = [
                    'date_from' => null,
                    'date_to' => null,
                    'client' => '',
                    'cut_off' => $criteria['cut_off'],
                    'varied_by_client' => true,
                ];
            } elseif ($comparisonScope !== null) {
                $previousRows = $this->getAggregateRows($comparisonScope);
            }
            $previousByClient = [];
            foreach ($previousRows as $previousRow) {
                $previousByClient[$previousRow['client_name']] = $previousRow;
            }

            foreach ($rows as &$row) {
                $previous = $previousByClient[$row['client_name']] ?? null;
                $row['gross_variance_pct'] = $this->variancePercent(
                    $row['total_gross_income'],
                    $previous['total_gross_income'] ?? null
                );
                $row['net_variance_pct'] = $this->variancePercent(
                    $row['total_net_pay'],
                    $previous['total_net_pay'] ?? null
                );
                $row['employee_variance_pct'] = $this->variancePercent(
                    $row['employee_count'],
                    $previous['employee_count'] ?? null
                );
            }
            unset($row);

            $summary = $this->summarizeRows($rows);
            $previousSummary = $this->summarizeRows($previousRows);
            $currentPopulation = $this->getScopePopulation($criteria);
            $summary['employee_count'] = $currentPopulation['employee_count'];
            $summary['client_count'] = $currentPopulation['client_count'];
            $summary['payroll_run_count'] = $currentPopulation['payroll_run_count'];
            if (
                $comparisonScope !== null
                && empty($comparisonScope['varied_by_client'])
            ) {
                $previousPopulation = $this->getScopePopulation($comparisonScope);
                $previousSummary['employee_count'] = $previousPopulation['employee_count'];
                $previousSummary['client_count'] = $previousPopulation['client_count'];
                $previousSummary['payroll_run_count'] = $previousPopulation['payroll_run_count'];
            }
            $summary['gross_variance_pct'] = $this->variancePercent(
                $summary['total_gross_income'],
                $previousSummary['total_gross_income']
            );
            $summary['net_variance_pct'] = $this->variancePercent(
                $summary['total_net_pay'],
                $previousSummary['total_net_pay']
            );
            $summary['employee_variance_pct'] = $this->variancePercent(
                $summary['employee_count'],
                $previousSummary['employee_count']
            );
            $summary['employer_variance_pct'] = $this->variancePercent(
                $summary['employer_contributions'],
                $previousSummary['employer_contributions']
            );

            return [
                'success' => 1,
                'data' => $rows,
                'summary' => $summary,
                'previous_summary' => $previousSummary,
                'scope' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'client' => $criteria['client'],
                    'cut_off' => $criteria['cut_off'],
                    'comparison_date_from' => $comparisonScope['date_from'] ?? null,
                    'comparison_date_to' => $comparisonScope['date_to'] ?? null,
                    'comparison_label' => $this->comparisonLabel($comparisonScope),
                ],
                'source' => [
                    'canonical' => $coverage,
                    'historical_archive' => $this->getHistoricalArchiveStatus(),
                    'historical_included' => false,
                ],
            ];
        } catch (\Throwable $th) {
            return [
                'success' => 0,
                'error' => 'Unable to load Payroll Summary. Please contact your administrator.',
            ];
        }
    }

    private function getAggregateRows(array $criteria)
    {
        $where = ['a.pay_day BETWEEN :date_from AND :date_to'];
        $params = [
            ':date_from' => $criteria['date_from'],
            ':date_to' => $criteria['date_to'],
        ];
        if (($criteria['client'] ?? '') !== '') {
            $where[] = 'a.client_name = :client';
            $params[':client'] = $criteria['client'];
        }
        if (($criteria['cut_off'] ?? '') !== '') {
            $where[] = 'a.cut_off = :cut_off';
            $params[':cut_off'] = $criteria['cut_off'];
        }

        $sql = "SELECT
                    x.client_name,
                    COUNT(DISTINCT x.employee_id) AS employee_count,
                    COUNT(DISTINCT CONCAT(x.pay_day, '|', COALESCE(x.cut_off, ''))) AS payroll_run_count,
                    MIN(x.pay_day) AS first_pay_day,
                    MAX(x.pay_day) AS last_pay_day,
                    SUM(x.basic_pay) AS total_basic_pay,
                    SUM(x.total_ot) AS total_ot,
                    SUM(x.total_leaves) AS total_leaves,
                    SUM(x.total_additional) AS total_other_additional,
                    SUM(x.gross_income) AS total_gross_income,
                    SUM(x.taxable_income) AS total_taxable,
                    SUM(x.employee_tax) AS total_tax,
                    SUM(x.total_tardy) AS total_tardy,
                    SUM(x.employee_sss) AS total_employee_sss,
                    SUM(x.employee_sss_mpf) AS total_employee_sss_mpf,
                    SUM(x.employee_philhealth) AS total_employee_philhealth,
                    SUM(x.employee_pagibig) AS total_employee_pagibig,
                    SUM(x.employee_loan) AS total_employee_loan,
                    SUM(x.total_deduction) AS total_other_deduction,
                    SUM(x.net_pay) AS total_net_pay,
                    SUM(x.annual_bonus) AS total_13th_month,
                    SUM(x.employer_sss) AS total_employer_sss,
                    SUM(x.employer_sss_mpf) AS total_employer_sss_mpf,
                    SUM(x.employer_sss_ec) AS total_employer_sss_ec,
                    SUM(x.employer_philhealth) AS total_employer_philhealth,
                    SUM(x.employer_pagibig) AS total_employer_pagibig
                FROM (
                    SELECT
                        a.employee_id,
                        a.client_name,
                        a.pay_day,
                        a.cut_off,
                        COALESCE(r.daily_salary, 0) * COALESCE(r.daily_worked, 0) AS basic_pay,
                        COALESCE(a.gross_income, 0) AS gross_income,
                        COALESCE(a.employee_tax, 0) AS employee_tax,
                        COALESCE(a.employee_sss, 0) AS employee_sss,
                        COALESCE(a.employee_sss_mpf, 0) AS employee_sss_mpf,
                        COALESCE(a.employee_philhealth, 0) AS employee_philhealth,
                        COALESCE(a.employee_pagibig, 0) AS employee_pagibig,
                        COALESCE(a.employer_sss, 0) AS employer_sss,
                        COALESCE(a.employer_sss_mpf, 0) AS employer_sss_mpf,
                        COALESCE(a.employer_sss_ec, 0) AS employer_sss_ec,
                        COALESCE(a.employer_philhealth, 0) AS employer_philhealth,
                        COALESCE(a.employer_pagibig, 0) AS employer_pagibig,
                        COALESCE(a.taxable_income, 0) AS taxable_income,
                        COALESCE(a.net_pay, 0) AS net_pay,
                        COALESCE(a.annual_bonus, 0) AS annual_bonus,
                        COALESCE(a.employee_loan, 0) AS employee_loan,
                        COALESCE(a.total_additional, 0) AS total_additional,
                        COALESCE(a.total_deduction, 0) AS total_deduction,
                        COALESCE(a.total_ot, 0) AS total_ot,
                        COALESCE(a.total_tardy, 0) AS total_tardy,
                        COALESCE(g.vacation_leave, 0) + COALESCE(g.sick_leave, 0) AS total_leaves
                    FROM payroll_summary a
                    INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                    INNER JOIN dtr_upload r ON a.employee_id = r.employee_id
                        AND a.client_name = r.client_name
                        AND a.cut_off = r.cut_off
                        AND a.pay_day = r.pay_day
                    INNER JOIN payroll_gross_variables g ON a.employee_id = g.employee_id
                        AND a.client_name = g.client_name
                        AND a.cut_off = g.cut_off
                        AND a.pay_day = g.pay_day
                    WHERE " . implode(' AND ', $where) . "
                ) x
                GROUP BY x.client_name
                ORDER BY x.client_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizeAggregateRow'], $rows);
    }

    private function getScopePopulation(array $criteria)
    {
        $where = ['a.pay_day BETWEEN :date_from AND :date_to'];
        $params = [
            ':date_from' => $criteria['date_from'],
            ':date_to' => $criteria['date_to'],
        ];
        if (($criteria['client'] ?? '') !== '') {
            $where[] = 'a.client_name = :client';
            $params[':client'] = $criteria['client'];
        }
        if (($criteria['cut_off'] ?? '') !== '') {
            $where[] = 'a.cut_off = :cut_off';
            $params[':cut_off'] = $criteria['cut_off'];
        }

        $sql = "SELECT
                    COUNT(DISTINCT a.employee_id) AS employee_count,
                    COUNT(DISTINCT a.client_name) AS client_count,
                    COUNT(
                        DISTINCT CONCAT(
                            a.client_name,
                            '|',
                            a.pay_day,
                            '|',
                            COALESCE(a.cut_off, '')
                        )
                    ) AS payroll_run_count
                FROM payroll_summary a
                INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                INNER JOIN dtr_upload r ON a.employee_id = r.employee_id
                    AND a.client_name = r.client_name
                    AND a.cut_off = r.cut_off
                    AND a.pay_day = r.pay_day
                INNER JOIN payroll_gross_variables g ON a.employee_id = g.employee_id
                    AND a.client_name = g.client_name
                    AND a.cut_off = g.cut_off
                    AND a.pay_day = g.pay_day
                WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'employee_count' => (int)($row['employee_count'] ?? 0),
            'client_count' => (int)($row['client_count'] ?? 0),
            'payroll_run_count' => (int)($row['payroll_run_count'] ?? 0),
        ];
    }

    private function normalizeAggregateRow(array $row)
    {
        $numericFields = [
            'employee_count',
            'payroll_run_count',
            'total_basic_pay',
            'total_ot',
            'total_leaves',
            'total_other_additional',
            'total_gross_income',
            'total_taxable',
            'total_tax',
            'total_tardy',
            'total_employee_sss',
            'total_employee_sss_mpf',
            'total_employee_philhealth',
            'total_employee_pagibig',
            'total_employee_loan',
            'total_other_deduction',
            'total_net_pay',
            'total_13th_month',
            'total_employer_sss',
            'total_employer_sss_mpf',
            'total_employer_sss_ec',
            'total_employer_philhealth',
            'total_employer_pagibig',
        ];
        foreach ($numericFields as $field) {
            $row[$field] = (float)($row[$field] ?? 0);
        }

        $row['employee_deductions'] =
            $row['total_tax']
            + $row['total_tardy']
            + $row['total_employee_sss']
            + $row['total_employee_sss_mpf']
            + $row['total_employee_philhealth']
            + $row['total_employee_pagibig']
            + $row['total_employee_loan']
            + $row['total_other_deduction'];
        $row['employer_contributions'] =
            $row['total_employer_sss']
            + $row['total_employer_sss_mpf']
            + $row['total_employer_sss_ec']
            + $row['total_employer_philhealth']
            + $row['total_employer_pagibig'];
        $row['total_payroll_cost'] = $row['total_gross_income'] + $row['employer_contributions'];
        $row['net_to_gross_pct'] = $row['total_gross_income'] > 0
            ? ($row['total_net_pay'] / $row['total_gross_income']) * 100
            : null;

        return $row;
    }

    private function summarizeRows(array $rows)
    {
        $summary = [
            'client_count' => count($rows),
            'employee_count' => 0,
            'payroll_run_count' => 0,
            'total_gross_income' => 0.0,
            'total_net_pay' => 0.0,
            'employee_deductions' => 0.0,
            'employer_contributions' => 0.0,
            'total_payroll_cost' => 0.0,
            'net_to_gross_pct' => null,
        ];
        foreach ($rows as $row) {
            $summary['employee_count'] += (int)$row['employee_count'];
            $summary['payroll_run_count'] += (int)$row['payroll_run_count'];
            $summary['total_gross_income'] += (float)$row['total_gross_income'];
            $summary['total_net_pay'] += (float)$row['total_net_pay'];
            $summary['employee_deductions'] += (float)$row['employee_deductions'];
            $summary['employer_contributions'] += (float)$row['employer_contributions'];
            $summary['total_payroll_cost'] += (float)$row['total_payroll_cost'];
        }
        if ($summary['total_gross_income'] > 0) {
            $summary['net_to_gross_pct'] =
                ($summary['total_net_pay'] / $summary['total_gross_income']) * 100;
        }

        return $summary;
    }

    private function comparisonScope(array $criteria)
    {
        if ($criteria['date_from'] === $criteria['date_to']) {
            $where = ['pay_day < :pay_day'];
            $params = [':pay_day' => $criteria['date_from']];
            if ($criteria['client'] !== '') {
                $where[] = 'client_name = :client';
                $params[':client'] = $criteria['client'];
            }
            if ($criteria['cut_off'] !== '') {
                $where[] = 'cut_off = :cut_off';
                $params[':cut_off'] = $criteria['cut_off'];
            }
            $stmt = $this->db->prepare(
                'SELECT MAX(pay_day) FROM payroll_summary WHERE ' . implode(' AND ', $where)
            );
            $stmt->execute($params);
            $previousDate = $stmt->fetchColumn();
            if (!$previousDate) {
                return null;
            }

            return [
                'date_from' => $previousDate,
                'date_to' => $previousDate,
                'client' => $criteria['client'],
                'cut_off' => $criteria['cut_off'],
            ];
        }

        $from = new DateTimeImmutable($criteria['date_from']);
        $to = new DateTimeImmutable($criteria['date_to']);
        $days = (int)$from->diff($to)->days + 1;
        $previousTo = $from->modify('-1 day');
        $previousFrom = $previousTo->modify('-' . ($days - 1) . ' days');

        return [
            'date_from' => $previousFrom->format('Y-m-d'),
            'date_to' => $previousTo->format('Y-m-d'),
            'client' => $criteria['client'],
            'cut_off' => $criteria['cut_off'],
        ];
    }

    private function comparisonLabel($scope)
    {
        if ($scope === null) {
            return 'No earlier comparable payroll is available.';
        }
        if (!empty($scope['varied_by_client'])) {
            return "Each client's previous available payday.";
        }
        if ($scope['date_from'] === $scope['date_to']) {
            return 'Previous available payday: ' . $scope['date_from'];
        }

        return 'Previous comparable period: '
            . $scope['date_from']
            . ' to '
            . $scope['date_to'];
    }

    private function variancePercent($current, $previous)
    {
        if ($previous === null || (float)$previous === 0.0) {
            return null;
        }

        return (((float)$current - (float)$previous) / abs((float)$previous)) * 100;
    }

    private function getCanonicalCoverage()
    {
        $row = $this->db
            ->query(
                'SELECT
                    COUNT(*) AS row_count,
                    COUNT(DISTINCT employee_id) AS employee_count,
                    COUNT(DISTINCT client_name) AS client_count,
                    MIN(pay_day) AS min_pay_day,
                    MAX(pay_day) AS max_pay_day
                 FROM payroll_summary'
            )
            ->fetch(PDO::FETCH_ASSOC);

        return [
            'row_count' => (int)($row['row_count'] ?? 0),
            'employee_count' => (int)($row['employee_count'] ?? 0),
            'client_count' => (int)($row['client_count'] ?? 0),
            'min_pay_day' => $row['min_pay_day'] ?? null,
            'max_pay_day' => $row['max_pay_day'] ?? null,
            'label' => 'Canonical employee-level HRIS payroll',
        ];
    }

    private function getHistoricalArchiveStatus()
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'payroll_historical_summary'"
        );
        $stmt->execute();
        $exists = (int)$stmt->fetchColumn() === 1;

        return [
            'table_exists' => $exists,
            'included_in_summary' => false,
            'label' => 'Shared Google Drive Payroll Summary archive (2017-2026)',
            'status' => $exists ? 'Available separately; not included' : 'Not loaded into HRIS',
        ];
    }

    private function validDate($value)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
