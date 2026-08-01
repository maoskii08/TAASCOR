<?php
require_once __DIR__ . '/../release-gate-rules.php';
require_once __DIR__ . '/../../dtr-upload/model/PayrollLockGuard.php';
require_once __DIR__ . '/../../dtr-format-engine/model/PayrollLegacyScopeHasher.php';
require_once __DIR__ . '/../../dtr-format-engine/model/PayslipArtifactStore.php';

class Payslip
{
    public $db = null;
    public $client = null;
    public $pay_type = null;
    public $pay_day = null;
    public $start_date = null;
    public $end_date = null;
    public $cut_off = null;
    public $bank_name = null;
    public $client_location = null;
    public $branch = null;
    public $actor = null;
    public $run_id = null;
    public array $allowed_client_ids = [];
    public bool $allow_all_clients = false;

    public $cutoffArray = array();

    public function getPayrollSummary(){

        $response = [];

        try {
            $where = "";
            $join = "";
            $filterParams = [];
            if($this->client_location != 'null' || $this->branch != 'null'){
                $join = "INNER JOIN employee_list b ON a.employee_id = b.employee_id";
            }

            if($this->pay_type != 'null'){
                $where .= " AND s.pay_type = :pay_type";
                $filterParams[':pay_type'] = $this->pay_type;
            }

            if($this->bank_name != 'null'){
                $where .= " AND s.bank_name = :bank_name";
                $filterParams[':bank_name'] = $this->bank_name;
            }

            if($this->client_location != 'null'){
                $where .= " AND b.client_location_id = :client_location_id";
                $filterParams[':client_location_id'] = (int)$this->client_location;
            }

            if($this->branch != 'null'){
                $where .= " AND b.branch_id = :branch_id";
                $filterParams[':branch_id'] = (int)$this->branch;
            }

            $additionalData = $this->addDeducColumns($this->db, 'payroll_other_additional', 'type_of_addition', $this->client, $this->cut_off, $this->pay_day);
            $deductionData = $this->addDeducColumns($this->db, 'payroll_other_deduction', 'type_of_deduction', $this->client, $this->cut_off, $this->pay_day);

            $selectAdditionals = $additionalData['select'];
            $addColumns = $additionalData['columns'];

            $selectDeductions = $deductionData['select'];
            $dedColumns = $deductionData['columns'];

            $query = "SELECT DISTINCT loan_type FROM loans_payment
                    WHERE client_name = :client
                        AND pay_day = :pay_day";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();
            $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $loanTypeCol = [];
            $loanStatements = [];
            foreach ($loans as $loan) {
                $loanTypeName = $loan['loan_type'];
                $escapedType = str_replace('`', '``', $loanTypeName); 
                $loanTypeCol[] = $escapedType;
                $loanStatements[] = "SUM(CASE WHEN loan_type = " . $this->db->quote($loanTypeName) . " THEN payment_amount ELSE 0 END) AS `$escapedType`";
            }

            $selectLoans = !empty($loanStatements) ? implode(", ", $loanStatements) : "0 AS `No_Loan`";

            $loanWithBackTicks = array_map(function ($column) {
                return "`$column`";
            }, $loanTypeCol);

            $loanColumns = !empty($loanWithBackTicks) ? implode(", ", array_map(function ($column) { return "COALESCE($column, 0) as $column"; }, $loanWithBackTicks)) : "0 AS `No_Loan`";

            $selectClause = "
                SELECT 
                    a.employee_id as Employee_ID,
                    CONCAT(b.last_name, ', ', b.first_name) AS Employee_Full_Name,
                    r.daily_salary as Daily_Salary,
                    r.daily_worked as Days_Worked,
                    r.daily_salary * r.daily_worked as Basic_Salary,
                    COALESCE(r.overtime, 0) as Overtime_Hours,
                    g.Overtime,
                    COALESCE(r.night_diff, 0) as Night_Diff_Hours,
                    g.Night_Diff,
                    COALESCE(r.night_diff_ot, 0) as Night_Diff_OT_Hours,
                    g.Night_Diff_OT,
                    COALESCE(r.regular_holiday, 0) as Regular_Holiday_Hours,
                    g.Regular_Holiday,
                    COALESCE(r.regular_holiday_ot, 0) as Regular_Holiday_OT_Hours,
                    g.Regular_Holiday_OT,
                    COALESCE(r.regular_holiday_night_diff, 0) as Regular_Holiday_Night_Diff_Hours,
                    g.Regular_Holiday_Night_Diff,
                    COALESCE(r.regular_holiday_nd_ot, 0) as Regular_Holiday_ND_OT_Hours,
                    g.Regular_Holiday_ND_OT,
                    COALESCE(r.special_holiday, 0) as Special_Holiday_Hours,
                    g.Special_Holiday,
                    COALESCE(r.special_holiday_ot, 0) as Special_Holiday_OT_Hours,
                    g.Special_Holiday_OT,
                    COALESCE(r.special_holiday_night_diff, 0) as Special_Holiday_Night_Diff_Hours,
                    g.Special_Holiday_Night_Diff,
                    COALESCE(r.special_holiday_nd_ot, 0) as Special_Holiday_ND_OT_Hours,
                    g.Special_Holiday_ND_OT,
                    COALESCE(r.rest_day, 0) as Rest_Day_Hours,
                    g.Rest_Day,
                    COALESCE(r.rest_day_ot, 0) as Rest_Day_OT_Hours,
                    g.Rest_Day_OT,
                    COALESCE(r.rest_day_night_diff, 0) as Rest_Day_Night_Diff_Hours,
                    g.Rest_Day_Night_Diff,
                    COALESCE(r.rest_day_nd_ot, 0) as Rest_Day_ND_OT_Hours,
                    g.Rest_Day_ND_OT,
                    COALESCE(r.rest_day_regular_holiday, 0) as Rest_Day_Regular_Holiday_Hours,
                    g.Rest_Day_Regular_Holiday,
                    COALESCE(r.rest_day_regular_holiday_ot, 0) as Rest_Day_Regular_Holiday_OT_Hours,
                    g.Rest_Day_Regular_Holiday_OT,
                    COALESCE(r.rest_day_regular_holiday_night_diff, 0) as Rest_Day_Regular_Holiday_Night_Diff_Hours,
                    g.Rest_Day_Regular_Holiday_Night_Diff,
                    COALESCE(r.rest_day_regular_holiday_nd_ot, 0) as Rest_Day_Regular_Holiday_ND_OT_Hours,
                    g.Rest_Day_Regular_Holiday_ND_OT,
                    COALESCE(r.rest_day_special_holiday, 0) as Rest_Day_Special_Holiday_Hours,
                    g.Rest_Day_Special_Holiday,
                    COALESCE(r.rest_day_special_holiday_ot, 0) as Rest_Day_Special_Holiday_OT_Hours,
                    g.Rest_Day_Special_Holiday_OT,
                    COALESCE(r.rest_day_special_holiday_night_diff, 0) as Rest_Day_Special_Holiday_Night_Diff_Hours,
                    g.Rest_Day_Special_Holiday_Night_Diff,
                    COALESCE(r.rest_day_special_holiday_nd_ot, 0) as Rest_Day_Special_Holiday_ND_OT_Hours,
                    g.Rest_Day_Special_Holiday_ND_OT,
                    a.total_ot as Total_OT,
                    COALESCE(r.vacation_leave, 0) as Vacation_Leave_Days,
                    g.Vacation_Leave,
                    COALESCE(r.sick_leave, 0) as Sick_Leave_Days,
                    g.Sick_Leave,
                    g.vacation_leave + g.sick_leave as Total_Leaves,
                    $addColumns,
                    a.total_additional as Total_Other_Addtional,
                    a.gross_income as Gross,
                    a.taxable_income as Taxable,
                    a.employee_tax as Tax,
                    COALESCE(r.lates, 0) as Lates_Min,
                    g.Lates,
                    COALESCE(r.undertime, 0) as Undertime_Hours,
                    g.Undertime,
                    a.total_tardy as Total_Tardy,
                    a.Employee_SSS,
                    a.Employee_SSS_MPF,
                    a.Employee_Philhealth,
                    a.Employee_Pagibig,
                    $loanColumns,
                    a.employee_loan as Total_Employee_Loan,
                    $dedColumns,
                    a.total_deduction as Total_Other_Deduction,
                    a.net_pay as Net_Pay,
                    a.annual_bonus as 13th_Month,
                    a.Employer_SSS,
                    a.Employer_SSS_MPF,
                    a.Employer_SSS_EC,
                    a.Employer_Philhealth,
                    a.Employer_Pagibig
            ";

            $fromClause = "
                FROM payroll_summary a
                INNER JOIN employee_list b ON a.employee_id = b.employee_id
                INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                INNER JOIN dtr_upload r ON a.employee_id = r.employee_id
                    AND a.client_name = r.client_name
                    AND a.cut_off = r.cut_off
                    AND a.pay_day = r.pay_day
                INNER JOIN payroll_gross_variables g ON a.employee_id = g.employee_id
                    AND a.client_name = g.client_name
                    AND a.cut_off = g.cut_off
                    AND a.pay_day = g.pay_day
                LEFT JOIN (
                    SELECT 
                        employee_id,
                        client_name,
                        pay_day,
                        cut_off,
                        $selectAdditionals
                    FROM payroll_other_additional
                    GROUP BY employee_id, client_name, pay_day, cut_off
                ) as ad ON a.employee_id = ad.employee_id
                    AND a.client_name = ad.client_name
                    AND a.cut_off = ad.cut_off
                    AND a.pay_day = ad.pay_day
                LEFT JOIN (
                    SELECT 
                        employee_id,
                        client_name,
                        pay_day,
                        cut_off,
                        $selectDeductions
                    FROM payroll_other_deduction
                    GROUP BY employee_id, client_name, pay_day, cut_off
                ) as dd ON a.employee_id = dd.employee_id
                    AND a.client_name = dd.client_name
                    AND a.cut_off = dd.cut_off
                    AND a.pay_day = dd.pay_day
                LEFT JOIN (
                    SELECT 
                        employee_id,
                        client_name,
                        pay_day,
                        $selectLoans
                    FROM loans_payment
                    GROUP BY employee_id, client_name, pay_day
                ) as ll ON a.employee_id = ll.employee_id
                    AND a.client_name = ll.client_name
                    AND a.pay_day = ll.pay_day
            ";

            $whereClause = "
                WHERE a.client_name = :client
                AND a.cut_off = :cut_off
                AND a.pay_day = :pay_day
                $where
            ";

            $sql1 = $selectClause . $fromClause . $whereClause;

            $stmt = $this->db->prepare($sql1);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) {
                $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];    
            }


            $sql2 = "SELECT 
                        Client_Name,
                        SUM(basic_pay) as Total_Basic_Pay,
                        SUM(total_ot) as Total_OT,
                        SUM(total_leaves) as Total_Leaves,
                        SUM(total_additional) as Total_Other_Additional, 
                        SUM(gross_income) as Total_Gross_Income,
                        SUM(taxable_income) as Total_Taxable,
                        SUM(employee_tax) as Total_Tax,
                        SUM(total_tardy) as Total_tardy,
                        SUM(employee_sss) as Total_Employee_SSS,
                        SUM(employee_sss_mpf) as Total_Employee_SSS_MPF,
                        SUM(employee_philhealth) as Total_Employee_Philhealth,
                        SUM(employee_pagibig) as Total_Employee_Pagibig,
                        SUM(employee_loan) as Total_Employee_Loan,
                        SUM(total_deduction) as Total_Other_Deduction,
                        SUM(net_pay) as Total_Net_Pay,
                        SUM(annual_bonus) as Total_13th_Month,
                        SUM(employer_sss) as Total_Employer_SSS,
                        SUM(employer_sss_mpf) as Total_Employer_SSS_MPF,
                        SUM(employer_sss_ec) as Total_Employer_SSS_EC,
                        SUM(employer_philhealth) as Total_Employer_Philhealth,
                        SUM(employer_pagibig) as Total_Employer_Pagibig
                    FROM (
                            SELECT a.client_name,
                            r.daily_salary * r.daily_worked as basic_pay,
                            a.gross_income,
                            a.employee_tax,
                            a.employee_sss,
                            a.employee_sss_mpf,
                            a.employee_philhealth,
                            a.employee_pagibig,
                            a.employer_sss,
                            a.employer_sss_mpf,
                            a.employer_sss_ec,
                            a.employer_philhealth,
                            a.employer_pagibig,
                            a.taxable_income,
                            a.net_pay,
                            a.annual_bonus,
                            a.employee_loan,
                            a.total_additional, 
                            a.total_deduction,
                            a.total_ot,
                            a.total_tardy,
                            g.vacation_leave + g.sick_leave as total_leaves
                        FROM payroll_summary a 
                        INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                        INNER JOIN dtr_upload r ON a.employee_id = r.employee_id
                            and a.client_name = r.client_name
                            and a.cut_off = r.cut_off
                            and a.pay_day = r.pay_day 
                        INNER JOIN payroll_gross_variables g ON a.employee_id = g.employee_id
                            and a.client_name = g.client_name
                            and a.cut_off = g.cut_off
                            and a.pay_day = g.pay_day 
                        $join
                        where a.client_name = :client
                            and a.cut_off = :cut_off
                            and a.pay_day = :pay_day
                        $where
                    ) as x group by client_name";

            $stmt = $this->db->prepare($sql2);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) {
                $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($stmt->rowCount() > 0){
                $response['data2'] = $data;                 
            } else {
                $response['data2'] = [];       
            }
            $response['success'] = 1;
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }


    public function addDeducColumns($db, $table, $typeColumn, $client, $cutOff, $payDay) {
        $query = "SELECT DISTINCT $typeColumn FROM $table
                  WHERE client_name = :client AND cut_off = :cut_off AND pay_day = :pay_day";
    
        $stmt = $db->prepare($query);
        $stmt->bindParam(':client', $client);
        $stmt->bindParam(':cut_off', $cutOff);
        $stmt->bindParam(':pay_day', $payDay);
        $stmt->execute();
    
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $columns = [];
        $caseStatements = [];
        foreach ($results as $row) {
            $typeName = $row[$typeColumn];
            $escaped = str_replace('`', '``', $typeName);
            $columns[] = $escaped;
            $caseStatements[] = "SUM(CASE WHEN $typeColumn = " . $db->quote($typeName) . " THEN amount ELSE 0 END) AS `{$escaped}`";
        }

        $alias = "ad";
        if($typeColumn == "type_of_deduction"){
            $alias = "dd";
        }
    
        return [
            'select' => !empty($caseStatements) ? implode(", ", $caseStatements) : "0 AS `No_Column`",
            'columns' => !empty($columns) ? implode(", ", array_map(fn($col) => "COALESCE($alias.`$col`, 0) AS `$col`", $columns)) : "0 AS `No_Column`"
        ];
    }


    public function postPayroll(){

        $response = [];
        $preflightGate = $this->releaseGate();
        if (($preflightGate['success'] ?? 0) !== 1) {
            return $preflightGate;
        }

        $lockGuard = new PayrollLockGuard($this->db);
        $lease = $lockGuard->acquireMutationLease((string)$this->client, (string)$this->pay_day, 10);
        if (($lease['success'] ?? 0) !== 1) {
            return $lease;
        }

        try {
            $this->db->beginTransaction();
            $releaseGate = $this->releaseGate(true);
            if (($releaseGate['success'] ?? 0) !== 1) {
                $this->db->rollBack();
                return $releaseGate;
            }

            $sql = "INSERT INTO locked_payroll(client_name, pay_day)
                    values(:client,:pay_day)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Payroll lock was not created.');
            }

            $sql = "UPDATE employee_loans el
                    JOIN (
                        SELECT 
                            a.employee_id, 
                            a.loan_type, 
                            SUM(a.payment_amount) AS total_payment, 
                            (SELECT loan_running_balance 
                            FROM loans_payment lp 
                            WHERE lp.employee_id = a.employee_id
                            AND lp.loan_type = a.loan_type
                            AND lp.client_name = :latest_client
                            AND lp.pay_day <= :latest_pay_day
                            ORDER BY lp.pay_day DESC, lp.id DESC
                            LIMIT 1) AS latest_balance
                        FROM loans_payment a
                        WHERE a.client_name = :client
                          AND a.pay_day <= :payment_pay_day
                        GROUP BY a.employee_id, a.loan_type
                    ) AS x
                    ON el.employee_id = x.employee_id
                    AND el.loan_type = x.loan_type
                    SET in_system_payment = total_payment,
                        loan_running_balance = latest_balance";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':client' => $this->client,
                ':latest_client' => $this->client,
                ':latest_pay_day' => $this->pay_day,
                ':payment_pay_day' => $this->pay_day,
            ]);

            if (($releaseGate['mode'] ?? '') === 'smart_run') {
                $runIds = array_values(array_filter(array_map('intval', $releaseGate['run_ids'] ?? [])));
                if (count($runIds) === 0) {
                    throw new RuntimeException('Approved smart payroll run IDs are missing.');
                }
                if (count($runIds) !== 1 || (int)$runIds[0] !== (int)$this->run_id) {
                    throw new RuntimeException('Exactly one authoritative payroll run is required.');
                }
                $releaseLock = $this->db->prepare("\n                    INSERT INTO payroll_import_release_locks (
                        run_id, client_name, pay_day, locked_by, locked_at
                    ) VALUES (:run_id, :client_name, :pay_day, :locked_by, NOW())
                ");
                $releaseLock->execute([
                    ':run_id' => (int)$this->run_id,
                    ':client_name' => (string)$this->client,
                    ':pay_day' => (string)$this->pay_day,
                    ':locked_by' => (string)$this->actor,
                ]);
                $placeholders = implode(',', array_fill(0, count($runIds), '?'));
                $runUpdate = $this->db->prepare(
                    "UPDATE payroll_import_runs
                     SET status = 'released',
                         release_status = 'released',
                         released_by = ?,
                         released_at = NOW(),
                         lock_version = lock_version + 1
                     WHERE id IN ($placeholders)
                       AND status = 'approved'
                       AND release_status = 'ready'"
                );
                $runUpdate->execute(array_merge([(string)$this->actor], $runIds));
                if ($runUpdate->rowCount() !== count($runIds)) {
                    throw new RuntimeException('Smart payroll run state changed before release.');
                }

                $artifactPlaceholders = implode(',', array_fill(0, count($runIds), '?'));
                $publishArtifacts = $this->db->prepare(
                    "UPDATE payroll_import_payslip_artifacts
                     SET artifact_status = 'published',
                         published_at = COALESCE(published_at, NOW())
                     WHERE run_id IN ($artifactPlaceholders)
                       AND artifact_status = 'verified'"
                );
                $publishArtifacts->execute($runIds);

                $outbox = $this->db->prepare(
                    "INSERT INTO payroll_import_outbox (
                        event_uid, aggregate_type, aggregate_id, event_type,
                        event_payload, event_status, available_at, created_at
                     ) VALUES (
                        :event_uid, 'payroll_import_run', :run_id,
                        'PAYROLL_IMPORT_RUN_RELEASED', :event_payload,
                        'pending', NOW(), NOW()
                     )"
                );
                foreach ($runIds as $runId) {
                    $outbox->execute([
                        ':event_uid' => 'PEVT-' . strtoupper(bin2hex(random_bytes(16))),
                        ':run_id' => $runId,
                        ':event_payload' => json_encode([
                            'run_id' => $runId,
                            'released_by' => (string)$this->actor,
                            'pay_date' => (string)$this->pay_day,
                        ], JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }
            
            $this->db->commit();

            $response['success'] = 1;
            $response['release_gate'] = $releaseGate;
                    } catch (\Throwable $th) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Payslip::postPayroll failed: ' . $th->getMessage());
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    } finally {
            $lockGuard->releaseMutationLease((string)$lease['lease_name']);
        }

        return $response;
    }

    public function releaseGate(bool $lockRows = false): array
    {
        $releaseContext = [
            'release_attempt' => true,
            'actor' => (string)$this->actor,
        ];
        try {
            if (!$this->tableExists('payroll_import_runs')
                || !$this->tableExists('payroll_import_client_settings')) {
                return payroll_release_gate_policy(array_merge($releaseContext, [
                    'smart_run_schema_exists' => false,
                ]));
            }

            $enrollment = $this->db->prepare("\n                SELECT COALESCE(s.smart_flow_enabled, 0)
                FROM taascor_client c
                LEFT JOIN payroll_import_client_settings s ON s.client_id = c.client_id
                WHERE c.client_name = :client_name
                LIMIT 1
            ");
            $enrollment->execute([':client_name' => $this->client]);
            $smartFlowEnrolled = (int)$enrollment->fetchColumn() === 1;
            if (!$smartFlowEnrolled) {
                return payroll_release_gate_policy(array_merge($releaseContext, [
                    'smart_run_schema_exists' => true,
                    'smart_flow_enrolled' => false,
                ]));
            }

            $payrollRows = $this->db->prepare(
                'SELECT COUNT(*) FROM payroll_summary '
                . 'WHERE client_name = :client_name AND pay_day = :pay_day'
            );
            $payrollRows->execute([
                ':client_name' => (string)$this->client,
                ':pay_day' => (string)$this->pay_day,
            ]);
            $livePayrollRowCount = (int)$payrollRows->fetchColumn();

            if (!$this->tableExists('payroll_import_release_checks')
                || !$this->tableExists('payroll_import_payslip_artifacts')
                || !$this->tableExists('payroll_import_outbox')
                || !$this->tableExists('payroll_import_legacy_scope_bindings')
                || !$this->tableExists('payroll_import_release_locks')) {
                return payroll_release_gate_blocked(
                    ['smart_run_schema_incomplete'],
                    array_merge($releaseContext, ['smart_flow_enrolled' => true])
                );
            }

            $runId = (int)$this->run_id;
            if ($runId <= 0) {
                $releasedRun = $this->db->prepare("\n                    SELECT run_id FROM payroll_import_release_locks
                    WHERE client_name = :client_name AND pay_day = :pay_day
                    LIMIT 1
                ");
                $releasedRun->execute([
                    ':client_name' => $this->client,
                    ':pay_day' => $this->pay_day,
                ]);
                $runId = (int)$releasedRun->fetchColumn();
            }
            if ($runId <= 0) {
                $blocked = payroll_release_gate_blocked(
                    ['authoritative_run_selection_required'],
                    array_merge($releaseContext, ['smart_flow_enrolled' => true])
                );
                $blocked['smart_flow_enrolled'] = true;
                $blocked['candidates'] = $this->smartRunCandidates();
                return $blocked;
            }

            $sql = "SELECT r.*,
                        (
                            SELECT COUNT(*)
                            FROM payroll_import_release_checks rc
                            WHERE rc.run_id = r.id
                              AND rc.is_blocking = 1
                        ) AS blocking_check_count,
                        (
                            SELECT COUNT(*)
                            FROM payroll_import_release_checks rc
                            WHERE rc.run_id = r.id
                              AND rc.is_blocking = 1
                              AND rc.check_status = 'passed'
                        ) AS passed_blocking_check_count,
                        (
                            SELECT COUNT(*)
                            FROM payroll_import_release_checks rc
                            WHERE rc.run_id = r.id
                              AND rc.check_code IN (
                                'IDENTITY_RESOLUTION', 'CANONICAL_ROW_INTEGRITY',
                                'RULE_VERSION_LOCK', 'PAYROLL_CALCULATION',
                                'PAYROLL_RECONCILIATION', 'LEGACY_SCOPE_BINDING',
                                'PAYSLIP_ARTIFACT_COVERAGE', 'OWNER_APPROVAL_EVIDENCE'
                              )
                        ) AS mandatory_check_count,
                        (
                            SELECT COUNT(*)
                            FROM payroll_import_release_checks rc
                            WHERE rc.run_id = r.id
                              AND rc.check_status = 'passed'
                              AND rc.check_code IN (
                                'IDENTITY_RESOLUTION', 'CANONICAL_ROW_INTEGRITY',
                                'RULE_VERSION_LOCK', 'PAYROLL_CALCULATION',
                                'PAYROLL_RECONCILIATION', 'LEGACY_SCOPE_BINDING',
                                'PAYSLIP_ARTIFACT_COVERAGE', 'OWNER_APPROVAL_EVIDENCE'
                              )
                        ) AS passed_mandatory_check_count,
                        (
                            SELECT COUNT(DISTINCT pa.employee_id)
                            FROM payroll_import_payslip_artifacts pa
                            WHERE pa.run_id = r.id
                              AND pa.artifact_type = 'payslip_pdf'
                              AND pa.artifact_status IN ('verified', 'published')
                        ) AS ready_artifact_count,
                        b.live_snapshot_hash AS binding_snapshot_hash,
                        b.snapshot_payload AS binding_snapshot_payload,
                        b.employee_count AS binding_employee_count,
                        b.payroll_row_count AS binding_payroll_row_count,
                        b.client_name AS binding_client_name,
                        b.pay_day AS binding_pay_day
                    FROM payroll_import_runs r
                    INNER JOIN taascor_client c ON c.client_id = r.client_id
                    LEFT JOIN payroll_import_legacy_scope_bindings b ON b.run_id = r.id
                    WHERE c.client_name = :client
                      AND r.pay_date = :pay_day
                      AND r.id = :run_id
                    LIMIT 1";
            if ($lockRows) {
                $sql .= ' FOR UPDATE';
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':client' => $this->client,
                ':pay_day' => $this->pay_day,
                ':run_id' => $runId,
            ]);
            $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($runs) === 1) {
                $runs[0] = $this->verifyRunSeals($runs[0]);
            }

            return payroll_release_gate_policy(array_merge($releaseContext, [
                'smart_run_schema_exists' => true,
                'smart_flow_enrolled' => true,
                'authoritative_run_id' => $runId,
                'live_payroll_row_count' => $livePayrollRowCount,
                'runs' => $runs,
            ]));
        } catch (Throwable $error) {
            error_log('Payslip::releaseGate failed: ' . $error->getMessage());
            return payroll_release_gate_blocked(
                ['smart_run_gate_check_failed'],
                $releaseContext
            );
        }
    }

    private function smartRunCandidates(): array
    {
        $stmt = $this->db->prepare("\n            SELECT r.id, r.run_uid, r.status, r.release_status,
                   r.maker_created_by, r.checker_approved_by, r.created_at
            FROM payroll_import_runs r
            INNER JOIN taascor_client c ON c.client_id = r.client_id
            WHERE c.client_name = :client AND r.pay_date = :pay_day
              AND r.status NOT IN ('failed', 'cancelled', 'released')
            ORDER BY (r.status = 'approved' AND r.release_status = 'ready') DESC, r.id DESC
            LIMIT 20
        ");
        $stmt->execute([':client' => $this->client, ':pay_day' => $this->pay_day]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function verifyRunSeals(array $run): array
    {
        $runId = (int)$run['id'];
        $input = $this->db->prepare("\n            SELECT snapshot_payload, snapshot_hash
            FROM payroll_import_run_inputs
            WHERE run_id = :run_id AND input_kind = 'staged_dtr_batch'
            ORDER BY id
        ");
        $input->execute([':run_id' => $runId]);
        $inputs = $input->fetchAll(PDO::FETCH_ASSOC);
        $run['input_hash_verified'] = count($inputs) === 1
            && hash_equals((string)$inputs[0]['snapshot_hash'], hash('sha256', (string)$inputs[0]['snapshot_payload']))
            && hash_equals((string)$run['input_snapshot_hash'], (string)$inputs[0]['snapshot_hash']);

        $ruleQuery = $this->db->prepare("\n            SELECT rule_type, rule_key, rule_version, effective_from, effective_to,
                   rule_snapshot, rule_hash
            FROM payroll_import_run_rule_versions
            WHERE run_id = :run_id
            ORDER BY rule_type, rule_key
        ");
        $ruleQuery->execute([':run_id' => $runId]);
        $rules = [];
        $ruleRowsValid = true;
        foreach ($ruleQuery->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $ruleRowsValid = $ruleRowsValid
                && hash_equals((string)$rule['rule_hash'], hash('sha256', (string)$rule['rule_snapshot']));
            $snapshot = json_decode((string)$rule['rule_snapshot'], true);
            if (!is_array($snapshot)) {
                $ruleRowsValid = false;
                $snapshot = [];
            }
            $rules[] = [
                'rule_type' => (string)$rule['rule_type'],
                'rule_key' => (string)$rule['rule_key'],
                'rule_version' => (string)$rule['rule_version'],
                'effective_from' => $rule['effective_from'],
                'effective_to' => $rule['effective_to'],
                'snapshot' => $snapshot,
            ];
        }
        $rulesHash = hash('sha256', PayrollLegacyScopeHasher::canonicalJson([
            'ruleset_key' => $run['ruleset_key'],
            'ruleset_version' => $run['ruleset_version'],
            'rules' => $rules,
        ]));
        $run['rules_hash_verified'] = $ruleRowsValid && count($rules) > 0
            && hash_equals((string)$run['ruleset_hash'], $rulesHash);

        $rowQuery = $this->db->prepare("\n            SELECT normalized_payload, input_payload_hash
            FROM payroll_import_run_rows WHERE run_id = :run_id ORDER BY id
        ");
        $rowQuery->execute([':run_id' => $runId]);
        $rowHashes = [];
        $rowHashesValid = true;
        foreach ($rowQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actual = hash('sha256', (string)$row['normalized_payload']);
            $rowHashesValid = $rowHashesValid && hash_equals((string)$row['input_payload_hash'], $actual);
            $rowHashes[] = (string)$row['input_payload_hash'];
        }
        sort($rowHashes, SORT_STRING);
        $canonicalHash = hash('sha256', PayrollLegacyScopeHasher::canonicalJson([
            'run_uid' => (string)$run['run_uid'],
            'input_snapshot_hash' => (string)$run['input_snapshot_hash'],
            'row_hashes' => $rowHashes,
        ]));
        $run['canonical_hash_verified'] = $rowHashesValid && count($rowHashes) > 0
            && hash_equals((string)$run['canonical_snapshot_hash'], $canonicalHash);

        $bindingPayload = (string)($run['binding_snapshot_payload'] ?? '');
        $bindingHash = (string)($run['binding_snapshot_hash'] ?? '');
        $bindingStored = payroll_release_gate_sha256($bindingHash)
            && $bindingPayload !== ''
            && hash_equals($bindingHash, hash('sha256', $bindingPayload));
        $live = (new PayrollLegacyScopeHasher($this->db))->snapshot(
            (string)$this->client,
            (string)$this->pay_day
        );
        $run['legacy_binding_verified'] = $bindingStored
            && hash_equals($bindingHash, (string)$live['live_snapshot_hash'])
            && (int)($run['binding_employee_count'] ?? -1) === (int)$live['employee_count']
            && (int)($run['binding_payroll_row_count'] ?? -1) === (int)$live['payroll_row_count']
            && (string)($run['binding_client_name'] ?? '') === (string)$this->client
            && (string)($run['binding_pay_day'] ?? '') === (string)$this->pay_day;

        $artifactQuery = $this->db->prepare("\n            SELECT storage_path, content_hash, byte_size
            FROM payroll_import_payslip_artifacts
            WHERE run_id = :run_id
              AND artifact_type = 'payslip_pdf'
              AND artifact_status IN ('verified', 'published')
            ORDER BY employee_id, id
        ");
        $artifactQuery->execute([':run_id' => $runId]);
        $artifactRows = $artifactQuery->fetchAll(PDO::FETCH_ASSOC);
        $artifactStore = new PayslipArtifactStore();
        $artifactFilesValid = count($artifactRows) === (int)($run['employee_count'] ?? 0);
        foreach ($artifactRows as $artifact) {
            $verifiedArtifact = $artifactStore->verifyPdf(
                (string)$artifact['storage_path'],
                (string)$artifact['content_hash']
            );
            if ($verifiedArtifact === null
                || (int)$verifiedArtifact['byte_size'] !== (int)$artifact['byte_size']) {
                $artifactFilesValid = false;
                break;
            }
        }
        $run['artifact_files_verified'] = $artifactFilesValid && count($artifactRows) > 0;
        return $run;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = :table_name LIMIT 1'
        );
        $stmt->execute([':table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }


    public function isLocked(){

        $response = [];

        try {
            $sql = "SELECT 1 FROM locked_payroll 
                        WHERE client_name = :client
                        and pay_day = :pay_day";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':client' => $this->client,
                ':pay_day' => $this->pay_day,
            ]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($stmt->rowCount() > 0) {    
                $response['locked'] = true;
            } else {
                $response['locked'] = false;
            }
    
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['locked'] = true;
            $response['error'] = "An error occurred. Please contact your administrator.";
                    }
    
        return $response;
    }


    public function getMetroBank(){

        $response = [];

        try {

            $sql = "SELECT b.last_name,
                        b.first_name,
                        b.middle_name,
                        s.atm_number,
                        a.net_pay
                    FROM payroll_summary a 
                    INNER JOIN employee_list b ON a.employee_id = b.employee_id
                    INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                    where a.client_name = :client
                        and a.cut_off = :cut_off
                        and a.pay_day = :pay_day
                        and bank_name = 'METROBANK'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];    
            }

            $response['success'] = 1;
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }


    public function getGcash(){

        $response = [];

        try {

            $sql = "SELECT s.atm_number,
                        concat(b.first_name,' ', b.last_name) as full_name,
                        a.net_pay, '' as remarks
                    FROM payroll_summary a 
                    INNER JOIN employee_list b ON a.employee_id = b.employee_id
                    INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                    where a.client_name = :client
                        and a.cut_off = :cut_off
                        and a.pay_day = :pay_day
                        and bank_name = 'Gcash'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];    
            }

            $response['success'] = 1;
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }


    public function getPNB(){

        $response = [];

        try {

            $sql = "SELECT sum(a.net_pay) as total_amount
                    FROM payroll_summary a 
                    INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                    where a.client_name = :client
                        and a.cut_off = :cut_off
                        and a.pay_day = :pay_day
                        and bank_name = 'PNB'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($stmt->rowCount() > 0){
                $response['total_amount'] = $data[0]['total_amount'];          
            } else {
                $response['total_amount'] = '0.00';    
            }

            $sql = "SELECT s.atm_number,
                        concat(b.first_name,' ', b.last_name) as full_name,
                        a.net_pay, '' as remarks
                    FROM payroll_summary a 
                    INNER JOIN employee_list b ON a.employee_id = b.employee_id
                    INNER JOIN employee_salary s ON a.employee_id = s.employee_id
                    where a.client_name = :client
                        and a.cut_off = :cut_off
                        and a.pay_day = :pay_day
                        and bank_name = 'PNB'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];    
            }

            $response['success'] = 1;
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }

    public function getPayDay(){

        $response = [];

        try {
            $sql = "SELECT 1 FROM client_payday 
                        WHERE client_name = :client
                    ORDER BY cut_off";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($stmt->rowCount() > 0) {    
                $response['success'] = 1;
            } else {
                $response['success'] = 2;
            }
    
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator.";
                    }
    
        return $response;
    }

    public function getPayDayFilter(){

        $response = [];

        try {

            $sql = "SELECT DISTINCT start_date, end_date, pay_date, cut_off
                    FROM (
                        SELECT DISTINCT 
                            start_date,
                            end_date,
                            cut_off,
                            pay_day as pay_date
                        FROM dtr_upload 
                        WHERE client_name = :client

                        UNION ALL
                        SELECT * FROM (
                        SELECT CASE 
                                    WHEN cut_off = 'Weekly' THEN DATE_SUB(pay_date, INTERVAL (WEEKDAY(pay_date) + 7) DAY)
                                    ELSE STR_TO_DATE(CONCAT(
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', 1) > pay_day
                                            THEN YEAR(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE YEAR(pay_date)
                                        END, '-', 
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', 1) > pay_day
                                            THEN MONTH(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE MONTH(pay_date)
                                        END, '-', 
                                        SUBSTRING_INDEX(cut_off, '-', 1)
                                    ), '%Y-%m-%d')
                                END AS start_date,
                                CASE WHEN cut_off = 'Weekly' THEN DATE_SUB(pay_date, INTERVAL (WEEKDAY(pay_date) + 1) DAY)
                                    ELSE STR_TO_DATE(CONCAT(
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', -1) > pay_day
                                            THEN YEAR(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE YEAR(pay_date)
                                        END, '-', 
                                        CASE 
                                            WHEN SUBSTRING_INDEX(cut_off, '-', -1) > pay_day
                                            THEN MONTH(DATE_SUB(pay_date, INTERVAL 1 MONTH))
                                            ELSE MONTH(pay_date)
                                        END, '-', 
                                        SUBSTRING_INDEX(cut_off, '-', -1)
                                    ), '%Y-%m-%d')
                                END AS end_date,
                                cut_off,
                                pay_date
                        FROM (
                            SELECT DISTINCT cut_off, pay_day,
                                -- Compute pay day 
                                CASE 
                                    WHEN cut_off = 'Weekly' THEN 
                                        -- Next Friday
                                        DATE_ADD(curdate(), INTERVAL (CASE 
                                            WHEN WEEKDAY(curdate()) = 4 THEN 0  
                                            WHEN WEEKDAY(curdate()) < 4 THEN (4 - WEEKDAY(curdate())) 
                                            ELSE (11 - WEEKDAY(curdate()))  
                                        END) DAY)
                                    ELSE 
                                        -- payday calculation
                                        STR_TO_DATE(CONCAT(
                                            CASE 
                                                WHEN pay_day < DAY(curdate()) THEN YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH))
                                                ELSE YEAR(curdate())
                                            END, '-', 
                                            CASE 
                                                WHEN pay_day < DAY(curdate()) THEN MONTH(DATE_ADD(curdate(), INTERVAL 1 MONTH))
                                                ELSE MONTH(curdate())
                                            END, '-', 
                                            CASE 
                                                -- Feb
                                                WHEN MONTH(DATE_ADD(curdate(), INTERVAL 1 MONTH)) = 2 AND pay_day > 28 THEN 
                                                    CASE 
                                                        WHEN YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH)) % 4 = 0 
                                                            AND (YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH)) % 100 <> 0 
                                                            OR YEAR(DATE_ADD(curdate(), INTERVAL 1 MONTH)) % 400 = 0) 
                                                        THEN 29 
                                                        ELSE 28
                                                    END
                                                ELSE pay_day 
                                            END
                                        ), '%Y-%m-%d')
                                END AS pay_date
                            FROM client_payday 
                            WHERE client_name = :client1
                        ) AS adjusted_paydays
                        WHERE pay_date >= curdate()  -- Only future pay days
                        ORDER BY pay_date ASC
                        LIMIT 1) as future_paydays
                    ) AS final_result ORDER BY pay_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':client1', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function getClientLocation(){

        $response = [];

        try {
            $sql = "SELECT distinct location_id, location_name from employee_list a 
                    inner join taascor_client b on a.client_id = b.client_id
                    inner join taascor_client_location c on a.client_location_id = c.location_id
                    WHERE client_name = :client
                    order by location_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function getBranch(){

        $response = [];

        try {
            $sql = "SELECT distinct a.branch_id, branch_name from employee_list a 
                    inner join taascor_branch b on a.branch_id = b.branch_id
                    inner join taascor_client c on a.client_id = c.client_id
                    WHERE client_name = :client
                    order by branch_name";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }
    

    public function getClientFilter(){

        $response = [];

        try {
            $params = [];
            $where = '';
            if (!$this->allow_all_clients) {
                if ($this->allowed_client_ids === []) {
                    return ['success' => 1, 'data' => []];
                }
                $placeholders = [];
                foreach (array_values($this->allowed_client_ids) as $index => $clientId) {
                    $name = ':client_scope_' . $index;
                    $placeholders[] = $name;
                    $params[$name] = (int)$clientId;
                }
                $where = ' WHERE client_id IN (' . implode(',', $placeholders) . ')';
            }
            $sql = "SELECT DISTINCT client_name FROM taascor_client"
                . $where
                . " ORDER BY client_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function getPayType(){

        $response = [];

        try {

            $sql = "SELECT distinct pay_type from employee_salary
                    where pay_type is not null and trim(pay_type) <> ''
                    order by pay_type";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function getBankName(){

        $response = [];

        try {

            $sql = "SELECT distinct bank_name from employee_salary
                    where bank_name is not null and trim(bank_name) <> ''
                    order by bank_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['success'] = 1;
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
                 
            }

            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    
}


?>
