<?php
require_once __DIR__ . '/PayrollLockGuard.php';
require_once __DIR__ . '/DTRMutationRules.php';
require_once __DIR__ . '/DTRCalculationSafetyGate.php';

class DTR
{
    private const DELETE_SNAPSHOT_MAX_ROWS = 25000;
    private const DELETE_SNAPSHOT_MAX_BYTES = 524288;

    public $db = null;
    public $client = null;
    public $pay_day = null;
    public $start_date = null;
    public $end_date = null;
    public $cut_off = null;

    public $access_level = null;
    public $client_access = null; 

    public $cutoffArray = array();
    public $client_location = null;
    public $branch = null;

    public $employee_ident = null;
    public $daily_salary = null;
    public $days_worked = null;
    public $absent = null;
    public $lates = null;
    public $undertime = null;
    public $vacation_leave = null;
    public $sick_leave = null;
    public $overtime = null;
    public $night_diff = null;
    public $night_diff_ot = null;
    public $regular_holiday = null;
    public $regular_holiday_ot = null;
    public $regular_holiday_night_diff = null;
    public $special_holiday = null;
    public $special_holiday_ot = null;
    public $special_holiday_night_diff = null;
    public $rest_day = null;
    public $rest_day_ot = null;
    public $rest_day_night_diff = null;
    public $rd_regular_holiday = null;
    public $rd_regular_holiday_ot = null;
    public $rd_regular_holiday_night_diff = null;
    public $rd_special_holiday = null;
    public $rd_special_holiday_ot = null;
    public $rd_special_holiday_night_diff = null;

    public $regular_holiday_nd_ot = null;
    public $special_holiday_nd_ot = null;
    public $rest_day_nd_ot = null;
    public $rd_regular_holiday_nd_ot = null;
    public $rd_special_holiday_nd_ot = null;
    public $deletion_confirmation = null;
    public $deletion_reason = null;
    public $deletion_evidence = null;
    public $deletion_review_token = null;
    public $deletion_review_secret = null;
    public $actor = null;
    public $change_reason = null;
    public $change_evidence = null;
    public $benefits_confirmation = null;
    private $locked_before_snapshot = null;

    public function getDTRList(){

        $response = [];

        try {
            $where = "";
            $where2 = "";
            $queryParams = [
                ':active_client' => $this->client,
                ':orphan_client' => $this->client,
                ':orphan_pay_day' => $this->pay_day,
                ':orphan_cut_off' => $this->cut_off,
                ':join_client' => $this->client,
                ':join_pay_day' => $this->pay_day,
                ':join_cut_off' => $this->cut_off,
                ':scope_client' => $this->client,
            ];

            $where .= " AND e.client_name = :active_client";
            $where2 .= " AND c.client_name = :orphan_client";
            $where2 .= " AND c.pay_day = :orphan_pay_day";
            $where2 .= " AND c.cut_off = :orphan_cut_off";

            if($this->client_location != 'null'){
                $locationId = filter_var($this->client_location, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($locationId === false) {
                    throw new InvalidArgumentException('Invalid client location filter.');
                }
                $where .= " AND a.client_location_id = :active_location_id";
                $where2 .= " AND a.client_location_id = :orphan_location_id";
                $queryParams[':active_location_id'] = (int)$locationId;
                $queryParams[':orphan_location_id'] = (int)$locationId;
            }

            if($this->branch != 'null'){
                $branchId = filter_var($this->branch, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($branchId === false) {
                    throw new InvalidArgumentException('Invalid branch filter.');
                }
                $where .= " AND a.branch_id = :active_branch_id";
                $where2 .= " AND a.branch_id = :orphan_branch_id";
                $queryParams[':active_branch_id'] = (int)$branchId;
                $queryParams[':orphan_branch_id'] = (int)$branchId;
            }

            $sql = "SELECT 
                        a.payroll_employee_id,
                        a.employee_id,
                        CONCAT(a.last_name, ', ', a.first_name) AS employee_full_name,
                        COALESCE(c.daily_salary, b.daily_salary) AS daily_salary,
                        c.daily_worked,
                        c.absent,
                        c.lates,
                        c.undertime,
                        c.vacation_leave,
                        c.sick_leave,
                        c.overtime,
                        c.night_diff,
                        c.night_diff_ot,
                        c.regular_holiday,
                        c.regular_holiday_ot,
                        c.regular_holiday_night_diff,
                        c.special_holiday,
                        c.special_holiday_ot,
                        c.special_holiday_night_diff,
                        c.rest_day,
                        c.rest_day_ot,
                        c.rest_day_night_diff,
                        c.rest_day_regular_holiday,
                        c.rest_day_regular_holiday_ot,
                        c.rest_day_regular_holiday_night_diff,
                        c.rest_day_special_holiday,
                        c.rest_day_special_holiday_ot,
                        c.rest_day_special_holiday_night_diff,
                        c.regular_holiday_nd_ot,
                        c.special_holiday_nd_ot,
                        c.rest_day_nd_ot,
                        c.rest_day_regular_holiday_nd_ot,
                        c.rest_day_special_holiday_nd_ot
                    FROM employee_list a
                    INNER JOIN employee_salary b ON a.employee_id = b.employee_id
                    LEFT JOIN dtr_upload c ON a.employee_id = c.employee_id
                                    AND c.client_name = :join_client
                                    AND c.pay_day = :join_pay_day
                                    AND c.cut_off = :join_cut_off
                    LEFT JOIN taascor_client e ON a.client_id = e.client_id
                    WHERE a.status = 'Active' $where

                    UNION

                    SELECT 
                        a.payroll_employee_id,
                        c.employee_id,
                        CONCAT(a.last_name, ', ', a.first_name) AS employee_full_name,
                        c.daily_salary,
                        c.daily_worked,
                        c.absent,
                        c.lates,
                        c.undertime,
                        c.vacation_leave,
                        c.sick_leave,
                        c.overtime,
                        c.night_diff,
                        c.night_diff_ot,
                        c.regular_holiday,
                        c.regular_holiday_ot,
                        c.regular_holiday_night_diff,
                        c.special_holiday,
                        c.special_holiday_ot,
                        c.special_holiday_night_diff,
                        c.rest_day,
                        c.rest_day_ot,
                        c.rest_day_night_diff,
                        c.rest_day_regular_holiday,
                        c.rest_day_regular_holiday_ot,
                        c.rest_day_regular_holiday_night_diff,
                        c.rest_day_special_holiday,
                        c.rest_day_special_holiday_ot,
                        c.rest_day_special_holiday_night_diff,
                        c.regular_holiday_nd_ot,
                        c.special_holiday_nd_ot,
                        c.rest_day_nd_ot,
                        c.rest_day_regular_holiday_nd_ot,
                        c.rest_day_special_holiday_nd_ot
                    FROM dtr_upload c
                    LEFT JOIN employee_list a ON c.employee_id = a.employee_id 
                    LEFT JOIN employee_salary b on c.employee_id = b.employee_id
                    WHERE NOT EXISTS (
                            SELECT 1 FROM employee_list a
                            LEFT JOIN taascor_client e ON a.client_id = e.client_id
                            WHERE a.employee_id = c.employee_id
                                AND a.status = 'Active'
                                AND e.client_name = :scope_client
                        ) $where2
                    ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($queryParams);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['data'] = $data;                 
            } else {
                $response['data'] = [];       
            }

            $sql = "SELECT 
                        a.employee_id,
                        CONCAT(a.last_name, ', ', a.first_name) AS employee_full_name,
                        c.basic_pay,
                        c.lates,
                        c.undertime,
                        c.vacation_leave,
                        c.sick_leave,
                        c.overtime,
                        c.night_diff,
                        c.night_diff_ot,
                        c.regular_holiday,
                        c.regular_holiday_ot,
                        c.regular_holiday_night_diff,
                        c.special_holiday,
                        c.special_holiday_ot,
                        c.special_holiday_night_diff,
                        c.rest_day,
                        c.rest_day_ot,
                        c.rest_day_night_diff,
                        c.rest_day_regular_holiday,
                        c.rest_day_regular_holiday_ot,
                        c.rest_day_regular_holiday_night_diff,
                        c.rest_day_special_holiday,
                        c.rest_day_special_holiday_ot,
                        c.rest_day_special_holiday_night_diff,
                        c.regular_holiday_nd_ot,
                        c.special_holiday_nd_ot,
                        c.rest_day_nd_ot,
                        c.rest_day_regular_holiday_nd_ot,
                        c.rest_day_special_holiday_nd_ot
                    FROM employee_list a
                    INNER JOIN employee_salary b ON a.employee_id = b.employee_id
                    LEFT JOIN payroll_gross_variables c ON a.employee_id = c.employee_id
                                    AND c.client_name = :join_client
                                    AND c.pay_day = :join_pay_day
                                    AND c.cut_off = :join_cut_off
                    LEFT JOIN taascor_client e ON a.client_id = e.client_id
                    WHERE a.status = 'Active' $where

                    UNION

                    SELECT 
                        c.employee_id,
                        CONCAT(a.last_name, ', ', a.first_name) AS employee_full_name,
                        c.basic_pay,
                        c.lates,
                        c.undertime,
                        c.vacation_leave,
                        c.sick_leave,
                        c.overtime,
                        c.night_diff,
                        c.night_diff_ot,
                        c.regular_holiday,
                        c.regular_holiday_ot,
                        c.regular_holiday_night_diff,
                        c.special_holiday,
                        c.special_holiday_ot,
                        c.special_holiday_night_diff,
                        c.rest_day,
                        c.rest_day_ot,
                        c.rest_day_night_diff,
                        c.rest_day_regular_holiday,
                        c.rest_day_regular_holiday_ot,
                        c.rest_day_regular_holiday_night_diff,
                        c.rest_day_special_holiday,
                        c.rest_day_special_holiday_ot,
                        c.rest_day_special_holiday_night_diff,
                        c.regular_holiday_nd_ot,
                        c.special_holiday_nd_ot,
                        c.rest_day_nd_ot,
                        c.rest_day_regular_holiday_nd_ot,
                        c.rest_day_special_holiday_nd_ot
                    FROM payroll_gross_variables c
                    LEFT JOIN employee_list a ON c.employee_id = a.employee_id 
                    LEFT JOIN employee_salary b on c.employee_id = b.employee_id
                    WHERE NOT EXISTS (
                            SELECT 1 FROM employee_list a
                            LEFT JOIN taascor_client e ON a.client_id = e.client_id
                            WHERE a.employee_id = c.employee_id
                                AND a.status = 'Active'
                                AND e.client_name = :scope_client
                        ) $where2
                    ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($queryParams);
            $data2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if($stmt->rowCount() > 0){
                $response['data3'] = $data2;                 
            } else {
                $response['data3'] = [];       
            }

            $response['success'] = 1;
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }


    public function calculatorSafetyEvidence(): array
    {
        return (new DTRCalculationSafetyGate($this->db))->verifyForCutoff(
            (string)$this->cut_off,
            'individual'
        );
    }

    public function spCalculateDTR(){
        $safety = $this->calculatorSafetyEvidence();
        if (($safety['success'] ?? 0) !== 1) {
            return $safety;
        }

        try {
          $routine = (string)$safety['routine'];
          $sql = "CALL {$routine}(
                        :client
                        ,:pay_day
                        ,:cut_off
                        ,:employee_id
                  )";
          $stmt = $this->db->prepare($sql);
          $stmt->execute([
            ':client' => $this->client,
            ':pay_day' => $this->pay_day,
            ':cut_off' => $this->cut_off,
            ':employee_id' => $this->employee_ident,
          ]);
          if (method_exists($stmt, 'closeCursor')) {
              $stmt->closeCursor();
          }

          return [
            'success' => 1,
            'code' => 'dtr_calculated_transaction_safe',
            'calculator_safety' => $safety,
          ];
        } catch (Throwable $e) {
          error_log('DTR::spCalculateDTR failed: ' . $e->getMessage());
          return [
            'success' => 0,
            'code' => 'dtr_calculation_failed',
            'error' => 'Unable to calculate DTR. The update was rolled back.',
          ];
        }
      }



    public function validateExistingDTRUpdateScope(bool $lockRow = false): array
    {
        try {
            $sql = "SELECT d.*
                    FROM dtr_upload d
                    INNER JOIN taascor_client c
                        ON c.client_name = d.client_name
                    INNER JOIN employee_list e
                        ON e.employee_id = d.employee_id
                       AND e.client_id = c.client_id
                    WHERE d.employee_id = :employee_id
                      AND d.client_name = :client
                      AND d.pay_day = :pay_day
                      AND d.cut_off = :cut_off
                      AND d.start_date = :start_date
                      AND d.end_date = :end_date
                    ORDER BY d.employee_id
                    LIMIT 2";
            if ($lockRow) {
                $sql .= ' FOR UPDATE';
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':employee_id' => (int)$this->employee_ident,
                ':client' => (string)$this->client,
                ':pay_day' => (string)$this->pay_day,
                ':cut_off' => (string)$this->cut_off,
                ':start_date' => (string)$this->start_date,
                ':end_date' => (string)$this->end_date,
            ]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($matches) !== 1) {
                return [
                    'success' => 0,
                    'code' => 'dtr_update_scope_not_found',
                    'error' => 'The employee no longer belongs to the exact reviewed client and payroll period. Reload the DTR before editing.',
                ];
            }
            $this->locked_before_snapshot = [
                'dtr_upload' => $matches[0],
                'payroll_summary' => $this->payrollSummaryFullSnapshot($lockRow, false),
            ];
            return [
                'success' => 1,
                'code' => 'dtr_update_scope_verified',
            ];
        } catch (\Throwable $error) {
            error_log('DTR::validateExistingDTRUpdateScope failed: ' . $error->getMessage());
            return [
                'success' => 0,
                'code' => 'dtr_update_scope_check_failed',
                'error' => 'Unable to verify the employee payroll scope. No data was changed.',
            ];
        }
    }

    public function updateDTR(){

        $response = [];

        try {

            $sql = "UPDATE dtr_upload
                    SET daily_salary = :daily_salary,
                        daily_worked = :daily_worked,
                        absent = :absent,
                        lates = :lates,
                        undertime = :undertime,
                        vacation_leave = :vacation_leave,
                        sick_leave = :sick_leave,
                        overtime = :overtime,
                        night_diff = :night_diff,
                        night_diff_ot = :night_diff_ot,
                        regular_holiday = :regular_holiday,
                        regular_holiday_ot = :regular_holiday_ot,
                        regular_holiday_night_diff = :regular_holiday_night_diff,
                        special_holiday = :special_holiday,
                        special_holiday_ot = :special_holiday_ot,
                        special_holiday_night_diff = :special_holiday_night_diff,
                        rest_day = :rest_day,
                        rest_day_ot = :rest_day_ot,
                        rest_day_night_diff = :rest_day_night_diff,
                        rest_day_regular_holiday = :rest_day_regular_holiday,
                        rest_day_regular_holiday_ot = :rest_day_regular_holiday_ot,
                        rest_day_regular_holiday_night_diff = :rest_day_regular_holiday_night_diff,
                        rest_day_special_holiday = :rest_day_special_holiday,
                        rest_day_special_holiday_ot = :rest_day_special_holiday_ot,
                        rest_day_special_holiday_night_diff = :rest_day_special_holiday_night_diff,
                        regular_holiday_nd_ot = :regular_holiday_nd_ot,
                        special_holiday_nd_ot = :special_holiday_nd_ot,
                        rest_day_nd_ot = :rest_day_nd_ot,
                        rest_day_regular_holiday_nd_ot = :rest_day_regular_holiday_nd_ot,
                        rest_day_special_holiday_nd_ot = :rest_day_special_holiday_nd_ot
                    WHERE employee_id = :employee_id
                      AND client_name = :client_name
                      AND pay_day = :pay_day
                      AND cut_off = :cut_off
                      AND start_date = :start_date
                      AND end_date = :end_date";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            $stmt->bindParam(':client_name', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':daily_salary', $this->daily_salary, PDO::PARAM_STR);
            $stmt->bindParam(':daily_worked', $this->days_worked, PDO::PARAM_STR);
            $stmt->bindParam(':absent', $this->absent, PDO::PARAM_STR);
            $stmt->bindParam(':lates', $this->lates, PDO::PARAM_STR);
            $stmt->bindParam(':undertime', $this->undertime, PDO::PARAM_STR);
            $stmt->bindParam(':vacation_leave', $this->vacation_leave, PDO::PARAM_STR);
            $stmt->bindParam(':sick_leave', $this->sick_leave, PDO::PARAM_STR);
            $stmt->bindParam(':overtime', $this->overtime, PDO::PARAM_STR);
            $stmt->bindParam(':night_diff', $this->night_diff, PDO::PARAM_STR);
            $stmt->bindParam(':night_diff_ot', $this->night_diff_ot, PDO::PARAM_STR);
            $stmt->bindParam(':regular_holiday', $this->regular_holiday, PDO::PARAM_STR);
            $stmt->bindParam(':regular_holiday_ot', $this->regular_holiday_ot, PDO::PARAM_STR);
            $stmt->bindParam(':regular_holiday_night_diff', $this->regular_holiday_night_diff, PDO::PARAM_STR);
            $stmt->bindParam(':special_holiday', $this->special_holiday, PDO::PARAM_STR);
            $stmt->bindParam(':special_holiday_ot', $this->special_holiday_ot, PDO::PARAM_STR);
            $stmt->bindParam(':special_holiday_night_diff', $this->special_holiday_night_diff, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day', $this->rest_day, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_ot', $this->rest_day_ot, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_night_diff', $this->rest_day_night_diff, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_regular_holiday', $this->rd_regular_holiday, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_regular_holiday_ot', $this->rd_regular_holiday_ot, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_regular_holiday_night_diff', $this->rd_regular_holiday_night_diff, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_special_holiday', $this->rd_special_holiday, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_special_holiday_ot', $this->rd_special_holiday_ot, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_special_holiday_night_diff', $this->rd_special_holiday_night_diff, PDO::PARAM_STR);
            $stmt->bindParam(':start_date', $this->start_date, PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $this->end_date, PDO::PARAM_STR);

            $stmt->bindParam(':regular_holiday_nd_ot', $this->regular_holiday_nd_ot, PDO::PARAM_STR);
            $stmt->bindParam(':special_holiday_nd_ot', $this->special_holiday_nd_ot, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_nd_ot', $this->rest_day_nd_ot, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_regular_holiday_nd_ot', $this->rd_regular_holiday_nd_ot, PDO::PARAM_STR);
            $stmt->bindParam(':rest_day_special_holiday_nd_ot', $this->rd_special_holiday_nd_ot, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
            
        } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }

    public function manualMutationAfterSnapshot(): array
    {
        if (!is_array($this->locked_before_snapshot)) {
            throw new RuntimeException('The locked DTR before snapshot is unavailable.');
        }
        $dtr = $this->dtrFullSnapshot(false);
        $payroll = $this->payrollSummaryFullSnapshot(false, true);
        if ($dtr === null || $payroll === null) {
            throw new RuntimeException('The complete DTR after snapshot is unavailable.');
        }
        return [
            'dtr_upload' => $dtr,
            'payroll_summary' => $payroll,
        ];
    }

    public function writeManualUpdateAudit(
        array $calculatorSafety,
        array $afterSnapshot
    ): string
    {
        if (
            ($calculatorSafety['success'] ?? 0) !== 1
            || trim((string)($calculatorSafety['routine'] ?? '')) === ''
            || preg_match('/^[a-f0-9]{64}$/', (string)($calculatorSafety['routine_hash'] ?? '')) !== 1
        ) {
            throw new RuntimeException('A verified calculator proof is required for the DTR audit.');
        }
        if (!is_array($this->locked_before_snapshot) || $afterSnapshot === []) {
            throw new RuntimeException('Complete DTR before and after snapshots are required.');
        }
        return $this->writeMutationAudit(
            'EDIT',
            (string)$this->change_reason,
            (string)$this->change_evidence,
            $this->locked_before_snapshot,
            $afterSnapshot,
            (string)$calculatorSafety['routine'],
            (string)$calculatorSafety['routine_hash']
        );
    }

    public function removeGovtBenefits(){
        $authorization = DTRMutationRules::validateGovernmentBenefitsRemoval(
            $this->employee_ident,
            trim((string)$this->client),
            trim((string)$this->pay_day),
            trim((string)$this->cut_off),
            (string)$this->benefits_confirmation,
            (string)$this->change_reason,
            (string)$this->change_evidence
        );
        if (($authorization['success'] ?? 0) !== 1) {
            return $authorization;
        }

        try {
            $this->db->beginTransaction();
            $before = $this->payrollSummaryFullSnapshot(true, true);
            if ($before === null) {
                $this->db->rollBack();
                return [
                    'success' => 0,
                    'code' => 'dtr_benefits_scope_not_found',
                    'error' => 'The employee no longer belongs to the exact reviewed client, pay date, and cutoff.',
                ];
            }
            $params = [
                ':employee_id' => (int)$authorization['employee_id'],
                ':client' => (string)$this->client,
                ':pay_day' => (string)$this->pay_day,
                ':cut_off' => (string)$this->cut_off,
            ];

            $statements = [
                "UPDATE payroll_summary SET
                    employee_sss = 0,
                    employee_philhealth = 0,
                    employee_pagibig = 0,
                    employer_sss = 0,
                    employer_philhealth = 0,
                    employer_pagibig = 0,
                    employee_sss_mpf = 0,
                    employer_sss_mpf = 0,
                    employer_sss_ec = 0
                 WHERE employee_id = :employee_id
                   AND client_name = :client
                   AND pay_day = :pay_day
                   AND cut_off = :cut_off",
                "UPDATE payroll_summary SET
                    taxable_income = CASE
                        WHEN gross_income <= 10417
                            THEN ROUND((gross_income - (total_additional + total_ot)), 2)
                        ELSE ROUND((gross_income - total_additional), 2)
                    END
                 WHERE employee_id = :employee_id
                   AND client_name = :client
                   AND pay_day = :pay_day
                   AND cut_off = :cut_off",
                "UPDATE payroll_summary SET employee_tax =
                    CASE
                        WHEN cut_off != 'Weekly' THEN
                            CASE
                                WHEN taxable_income <= 10417 THEN 0
                                WHEN taxable_income > 10417 AND taxable_income <= 16666
                                    THEN ROUND((taxable_income - 10417) * 0.15, 2)
                                WHEN taxable_income > 16666 AND taxable_income <= 33332
                                    THEN ROUND(1250 + ((taxable_income - 16667) * 0.20), 2)
                                WHEN taxable_income > 33332 AND taxable_income <= 83332
                                    THEN ROUND(5416.67 + ((taxable_income - 33333) * 0.25), 2)
                                WHEN taxable_income > 83332 AND taxable_income <= 333332
                                    THEN ROUND(20416.67 + ((taxable_income - 83333) * 0.30), 2)
                                ELSE ROUND(100416.67 + ((taxable_income - 333333) * 0.35), 2)
                            END
                        ELSE
                            CASE
                                WHEN taxable_income <= 4808 THEN 0
                                WHEN taxable_income > 4808 AND taxable_income <= 7691
                                    THEN ROUND((taxable_income - 4808) * 0.15, 2)
                                WHEN taxable_income > 7691 AND taxable_income <= 15384
                                    THEN ROUND(576.92 + ((taxable_income - 7692) * 0.20), 2)
                                WHEN taxable_income > 15384 AND taxable_income <= 38461
                                    THEN ROUND(2500 + ((taxable_income - 15385) * 0.25), 2)
                                WHEN taxable_income > 38461 AND taxable_income <= 153845
                                    THEN ROUND(9423.08 + ((taxable_income - 38462) * 0.30), 2)
                                ELSE ROUND(46346.15 + ((taxable_income - 153846) * 0.35), 2)
                            END
                    END
                 WHERE employee_id = :employee_id
                   AND client_name = :client
                   AND pay_day = :pay_day
                   AND cut_off = :cut_off",
                "UPDATE payroll_summary SET
                    net_pay = ROUND(
                        gross_income
                        - ROUND((employee_tax + total_deduction + total_tardy + employee_loan), 2),
                        2
                    )
                 WHERE employee_id = :employee_id
                   AND client_name = :client
                   AND pay_day = :pay_day
                   AND cut_off = :cut_off",
            ];
            foreach ($statements as $sql) {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $after = $this->payrollSummaryFullSnapshot(false, true);
            if ($after === null) {
                throw new RuntimeException('Unable to verify the updated payroll summary.');
            }
            $auditEvent = $this->writeMutationAudit(
                'BENEFIT',
                (string)$authorization['reason'],
                (string)$authorization['evidence'],
                ['payroll_summary' => $before],
                ['payroll_summary' => $after],
                null,
                null
            );
            $this->db->commit();
            return [
                'success' => 1,
                'code' => 'dtr_benefits_removed',
                'audit_recorded' => true,
                'audit_event' => $auditEvent,
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR::removeGovtBenefits failed: ' . $error->getMessage());
            return [
                'success' => 0,
                'code' => 'dtr_benefits_removal_failed',
                'error' => 'The government-benefits change was rolled back because it could not be completed and audited.',
            ];
        }
    }


    public function deleteEmployeeDTR(){
        $authorization = DTRMutationRules::validateEmployeeDeleteAuthorization(
            $this->employee_ident,
            trim((string)$this->client),
            trim((string)$this->pay_day),
            trim((string)$this->cut_off),
            (string)$this->deletion_confirmation,
            (string)$this->deletion_reason,
            (string)$this->deletion_evidence
        );
        if (($authorization['success'] ?? 0) !== 1) {
            return $authorization;
        }
        try {
            $this->db->beginTransaction();
            $scope = $this->validateEmployeeDeleteScope(true);
            if (($scope['success'] ?? 0) !== 1) {
                $this->db->rollBack();
                return $scope;
            }

            $expectedCounts = $this->employeeDeleteCounts(
                (int)$authorization['employee_id']
            );
            $this->assertDeleteCountsBounded($expectedCounts);
            if (array_sum($expectedCounts) < 1) {
                throw new RuntimeException('The employee payroll scope changed before deletion.');
            }

            $beforeSnapshot = $this->captureEmployeeDeleteSnapshot(
                (int)$authorization['employee_id']
            );
            $capturedCounts = $this->deleteSnapshotCounts($beforeSnapshot);
            $this->assertDeleteCountsMatchSnapshot($expectedCounts, $capturedCounts);

            $deletedCounts = [];
            foreach ($this->bulkDeleteTables() as $key => $table) {
                $stmt = $this->db->prepare(
                    "DELETE FROM {$table}
                     WHERE employee_id = :employee_id
                       AND client_name = :client
                       AND pay_day = :pay_day
                       AND cut_off = :cut_off"
                );
                $stmt->execute([
                    ':employee_id' => (int)$authorization['employee_id'],
                    ':client' => (string)$this->client,
                    ':pay_day' => (string)$this->pay_day,
                    ':cut_off' => (string)$this->cut_off,
                ]);
                $deletedCounts[$key] = (int)$stmt->rowCount();
            }
            $this->assertDeleteCountsMatchSnapshot($capturedCounts, $deletedCounts);

            $auditEvent = $this->writeMutationAudit(
                'DELETE_EMPLOYEE',
                (string)$authorization['reason'],
                (string)$authorization['evidence'],
                $beforeSnapshot,
                $this->deleteTombstone($capturedCounts),
                null,
                null,
                [
                    'scope_kind' => 'EMPLOYEE',
                    'employee_id' => (int)$authorization['employee_id'],
                    'client_name' => trim((string)$this->client),
                    'cut_off' => trim((string)$this->cut_off),
                    'branch_id' => null,
                    'client_location_id' => null,
                    'period_start' => null,
                    'period_end' => null,
                    'pay_day' => trim((string)$this->pay_day),
                    'table_counts' => $capturedCounts,
                ]
            );
            $this->db->commit();
            return [
                'success' => 1,
                'code' => 'dtr_employee_delete_completed',
                'deleted_counts' => $deletedCounts,
                'total_deleted' => array_sum($deletedCounts),
                'audit_recorded' => true,
                'audit_event' => $auditEvent,
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR::deleteEmployeeDTR failed: ' . $error->getMessage());
            return [
                'success' => 0,
                'code' => 'dtr_employee_delete_failed',
                'error' => 'The employee deletion was rolled back because it could not be completed and audited.',
            ];
        }
    }


    public function getDeleteDTRUploadPreflight(): array
    {
        try {
            $scope = $this->bulkDeleteScope();
            $counts = $this->bulkDeleteCounts($scope);
            $this->assertDeleteCountsBounded($counts);
            $totalRows = array_sum($counts);
            $confirmationPhrase = DTRMutationRules::expectedBulkDeleteConfirmation(
                (string)$this->client,
                (string)$this->pay_day,
                $scope['branch'],
                $scope['client_location']
            );
            $reviewScope = $this->bulkDeleteReviewScope($scope);
            $reviewToken = DTRMutationRules::issueBulkDeleteReviewToken(
                $reviewScope,
                $counts,
                (string)$this->actor,
                (string)$this->deletion_review_secret
            );

            return [
                'success' => 1,
                'can_delete' => $totalRows > 0,
                'code' => $totalRows > 0 ? 'dtr_delete_preflight_ready' : 'dtr_delete_scope_empty',
                'error' => $totalRows > 0 ? null : 'No payroll records exist in the selected scope.',
                'scope' => [
                    'client' => (string)$this->client,
                    'pay_day' => (string)$this->pay_day,
                    'branch' => $scope['branch'],
                    'client_location' => $scope['client_location'],
                ],
                'counts' => $counts,
                'total_rows' => $totalRows,
                'employee_count' => $this->bulkDeleteEmployeeCount($scope),
                'confirmation_phrase' => $confirmationPhrase,
                'review_token' => $reviewToken,
            ];
        } catch (\Throwable $error) {
            error_log('DTR::getDeleteDTRUploadPreflight failed: ' . $error->getMessage());
            return [
                'success' => 0,
                'can_delete' => false,
                'code' => 'dtr_delete_preflight_failed',
                'error' => 'Unable to verify the selected deletion scope. No data was changed.',
            ];
        }
    }

    public function deleteDTRUpload(): array
    {
        try {
            $this->db->beginTransaction();
            $scope = $this->bulkDeleteScope();
            $beforeCounts = $this->bulkDeleteCounts($scope);
            $this->assertDeleteCountsBounded($beforeCounts);
            if (array_sum($beforeCounts) < 1) {
                $this->db->rollBack();
                return [
                    'success' => 0,
                    'code' => 'dtr_delete_scope_empty',
                    'error' => 'No payroll records exist in the selected scope. No data was changed.',
                ];
            }
            $review = DTRMutationRules::validateBulkDeleteReviewToken(
                (string)$this->deletion_review_token,
                $this->bulkDeleteReviewScope($scope),
                $beforeCounts,
                (string)$this->actor,
                (string)$this->deletion_review_secret
            );
            if (($review['success'] ?? 0) !== 1) {
                $this->db->rollBack();
                return $review;
            }
            $authorization = DTRMutationRules::validateBulkDeleteAuthorization(
                trim((string)$this->client),
                trim((string)$this->pay_day),
                (string)$this->deletion_confirmation,
                (string)$this->deletion_reason,
                (string)$this->deletion_evidence,
                $scope['branch'],
                $scope['client_location']
            );
            if (($authorization['success'] ?? 0) !== 1) {
                $this->db->rollBack();
                return $authorization;
            }

            $beforeSnapshot = $this->captureBulkDeleteSnapshot($scope);
            $capturedCounts = $this->deleteSnapshotCounts($beforeSnapshot);
            $this->assertDeleteCountsMatchSnapshot($beforeCounts, $capturedCounts);

            $deletedCounts = [];
            foreach ($this->bulkDeleteTables() as $key => $table) {
                $sql = "DELETE a FROM {$table} a
                        {$scope['join']}
                        WHERE a.client_name = :client
                        AND a.pay_day = :pay_day
                        {$scope['where']}";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($scope['params']);
                $deletedCounts[$key] = (int)$stmt->rowCount();
            }
            $this->assertDeleteCountsMatchSnapshot($capturedCounts, $deletedCounts);

            $auditEvent = $this->writeMutationAudit(
                'DELETE_BULK',
                (string)$authorization['reason'],
                (string)$authorization['evidence'],
                $beforeSnapshot,
                $this->deleteTombstone($capturedCounts),
                null,
                null,
                [
                    'scope_kind' => 'PAYROLL_SCOPE',
                    'employee_id' => null,
                    'client_name' => trim((string)$this->client),
                    'cut_off' => null,
                    'branch_id' => $scope['branch'],
                    'client_location_id' => $scope['client_location'],
                    'period_start' => null,
                    'period_end' => null,
                    'pay_day' => trim((string)$this->pay_day),
                    'table_counts' => $capturedCounts,
                ]
            );

            $this->db->commit();
            return [
                'success' => 1,
                'code' => 'dtr_delete_completed',
                'deleted_counts' => $deletedCounts,
                'total_deleted' => array_sum($deletedCounts),
                'audit_recorded' => true,
                'audit_event' => $auditEvent,
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DTR::deleteDTRUpload failed: ' . $error->getMessage());
            return [
                'success' => 0,
                'code' => 'dtr_delete_failed',
                'error' => 'The deletion was rolled back because it could not be completed and audited.',
            ];
        }
    }

    private function bulkDeleteScope(): array
    {
        $client = trim((string)$this->client);
        $payDay = trim((string)$this->pay_day);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $payDay);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if (
            $client === ''
            || !$date
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $date->format('Y-m-d') !== $payDay
        ) {
            throw new InvalidArgumentException('Invalid DTR deletion scope.');
        }

        $where = '';
        $join = '';
        $filtersEmployeeList = false;
        $branch = null;
        $clientLocation = null;
        $params = [
            ':client' => $client,
            ':pay_day' => $payDay,
        ];

        $rawLocation = trim((string)$this->client_location);
        if ($rawLocation !== '' && strtolower($rawLocation) !== 'null') {
            $locationId = filter_var(
                $rawLocation,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($locationId === false) {
                throw new InvalidArgumentException('Invalid client location filter.');
            }
            $filtersEmployeeList = true;
            $clientLocation = (int)$locationId;
            $where .= ' AND b.client_location_id = :client_location_id';
            $params[':client_location_id'] = $clientLocation;
        }

        $rawBranch = trim((string)$this->branch);
        if ($rawBranch !== '' && strtolower($rawBranch) !== 'null') {
            $branchId = filter_var(
                $rawBranch,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($branchId === false) {
                throw new InvalidArgumentException('Invalid branch filter.');
            }
            $filtersEmployeeList = true;
            $branch = (int)$branchId;
            $where .= ' AND b.branch_id = :branch_id';
            $params[':branch_id'] = $branch;
        }

        if ($filtersEmployeeList) {
            $join = ' INNER JOIN employee_list b ON a.employee_id = b.employee_id';
        }

        return [
            'join' => $join,
            'where' => $where,
            'params' => $params,
            'branch' => $branch,
            'client_location' => $clientLocation,
        ];
    }

    private function bulkDeleteReviewScope(array $scope): array
    {
        return [
            'client' => trim((string)$this->client),
            'pay_day' => trim((string)$this->pay_day),
            'branch' => $scope['branch'],
            'client_location' => $scope['client_location'],
        ];
    }

    private function dtrFullSnapshot(bool $lockRow): ?array
    {
        $sql = "SELECT d.*
                FROM dtr_upload d
                INNER JOIN taascor_client c
                    ON c.client_name = d.client_name
                INNER JOIN employee_list e
                    ON e.employee_id = d.employee_id
                   AND e.client_id = c.client_id
                WHERE d.employee_id = :employee_id
                  AND d.client_name = :client
                  AND d.pay_day = :pay_day
                  AND d.cut_off = :cut_off
                  AND d.start_date = :start_date
                  AND d.end_date = :end_date
                ORDER BY d.employee_id
                LIMIT 2";
        if ($lockRow) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => (int)$this->employee_ident,
            ':client' => (string)$this->client,
            ':pay_day' => (string)$this->pay_day,
            ':cut_off' => (string)$this->cut_off,
            ':start_date' => (string)$this->start_date,
            ':end_date' => (string)$this->end_date,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) {
            throw new RuntimeException('The exact DTR scope is ambiguous.');
        }
        return $rows[0] ?? null;
    }

    private function payrollSummaryFullSnapshot(bool $lockRow, bool $required): ?array
    {
        $sql = "SELECT p.*
                FROM payroll_summary p
                INNER JOIN taascor_client c
                    ON c.client_name = p.client_name
                INNER JOIN employee_list e
                    ON e.employee_id = p.employee_id
                   AND e.client_id = c.client_id
                WHERE p.employee_id = :employee_id
                  AND p.client_name = :client
                  AND p.pay_day = :pay_day
                  AND p.cut_off = :cut_off
                ORDER BY p.employee_id
                LIMIT 2";
        if ($lockRow) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => (int)$this->employee_ident,
            ':client' => (string)$this->client,
            ':pay_day' => (string)$this->pay_day,
            ':cut_off' => (string)$this->cut_off,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) {
            throw new RuntimeException('The exact payroll-summary scope is ambiguous.');
        }
        if ($required && count($rows) !== 1) {
            return null;
        }
        return $rows[0] ?? null;
    }

    private function validateEmployeeDeleteScope(bool $lockRow): array
    {
        $sql = "SELECT d.employee_id
                FROM dtr_upload d
                INNER JOIN taascor_client c
                    ON c.client_name = d.client_name
                INNER JOIN employee_list e
                    ON e.employee_id = d.employee_id
                   AND e.client_id = c.client_id
                WHERE d.employee_id = :employee_id
                  AND d.client_name = :client
                  AND d.pay_day = :pay_day
                  AND d.cut_off = :cut_off
                ORDER BY d.employee_id
                LIMIT 2";
        if ($lockRow) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => (int)$this->employee_ident,
            ':client' => (string)$this->client,
            ':pay_day' => (string)$this->pay_day,
            ':cut_off' => (string)$this->cut_off,
        ]);
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($matches) !== 1) {
            return [
                'success' => 0,
                'code' => 'dtr_employee_delete_scope_not_found',
                'error' => 'The employee no longer belongs to the exact reviewed client, pay date, and cutoff.',
            ];
        }
        return [
            'success' => 1,
            'code' => 'dtr_employee_delete_scope_verified',
        ];
    }

    private function captureEmployeeDeleteSnapshot(int $employeeId): array
    {
        $snapshot = [];
        $capturedRows = 0;
        $params = [
            ':employee_id' => $employeeId,
            ':client' => trim((string)$this->client),
            ':pay_day' => trim((string)$this->pay_day),
            ':cut_off' => trim((string)$this->cut_off),
        ];
        foreach ($this->bulkDeleteTables() as $key => $table) {
            $remainingRows = self::DELETE_SNAPSHOT_MAX_ROWS - $capturedRows;
            $rowLimit = $remainingRows + 1;
            $stmt = $this->db->prepare(
                "SELECT a.*
                 FROM {$table} a
                 WHERE a.employee_id = :employee_id
                   AND a.client_name = :client
                   AND a.pay_day = :pay_day
                   AND a.cut_off = :cut_off
                 LIMIT {$rowLimit}
                 FOR UPDATE"
            );
            $stmt->execute($params);
            $snapshot[$key] = $this->normalizeDeleteSnapshotRows(
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
            $capturedRows += count($snapshot[$key]);
            $this->assertPartialDeleteSnapshotBounded($snapshot, $capturedRows);
        }
        $this->assertDeleteSnapshotBounded($snapshot);
        return $snapshot;
    }

    private function captureBulkDeleteSnapshot(array $scope): array
    {
        $snapshot = [];
        $capturedRows = 0;
        foreach ($this->bulkDeleteTables() as $key => $table) {
            $remainingRows = self::DELETE_SNAPSHOT_MAX_ROWS - $capturedRows;
            $rowLimit = $remainingRows + 1;
            $stmt = $this->db->prepare(
                "SELECT a.*
                 FROM {$table} a
                 {$scope['join']}
                 WHERE a.client_name = :client
                   AND a.pay_day = :pay_day
                   {$scope['where']}
                 LIMIT {$rowLimit}
                 FOR UPDATE"
            );
            $stmt->execute($scope['params']);
            $snapshot[$key] = $this->normalizeDeleteSnapshotRows(
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
            $capturedRows += count($snapshot[$key]);
            $this->assertPartialDeleteSnapshotBounded($snapshot, $capturedRows);
        }
        $this->assertDeleteSnapshotBounded($snapshot);
        return $snapshot;
    }

    private function normalizeDeleteSnapshotRows(array $rows): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('A destructive DTR snapshot returned an invalid row.');
            }
        }
        usort(
            $rows,
            function (array $left, array $right): int {
                return strcmp(
                    $this->canonicalAuditJson($left),
                    $this->canonicalAuditJson($right)
                );
            }
        );
        return array_values($rows);
    }

    private function assertDeleteSnapshotBounded(array $snapshot): void
    {
        $counts = $this->deleteSnapshotCounts($snapshot);
        $this->assertDeleteCountsBounded($counts);
        if (strlen($this->canonicalAuditJson($snapshot)) > self::DELETE_SNAPSHOT_MAX_BYTES) {
            throw new RuntimeException(
                'The destructive DTR snapshot exceeds the governed evidence size. Narrow the deletion scope.'
            );
        }
    }

    private function assertPartialDeleteSnapshotBounded(array $snapshot, int $capturedRows): void
    {
        if ($capturedRows > self::DELETE_SNAPSHOT_MAX_ROWS) {
            throw new RuntimeException(
                'The destructive DTR snapshot exceeds the governed row limit. Narrow the deletion scope.'
            );
        }
        if (strlen($this->canonicalAuditJson($snapshot)) > self::DELETE_SNAPSHOT_MAX_BYTES) {
            throw new RuntimeException(
                'The destructive DTR snapshot exceeds the governed evidence size. Narrow the deletion scope.'
            );
        }
    }

    private function assertDeleteCountsBounded(array $counts): void
    {
        $normalized = $this->normalizedDeleteCounts($counts);
        if (array_sum($normalized) > self::DELETE_SNAPSHOT_MAX_ROWS) {
            throw new RuntimeException(
                'The destructive DTR snapshot exceeds the governed row limit. Narrow the deletion scope.'
            );
        }
    }

    private function deleteSnapshotCounts(array $snapshot): array
    {
        $counts = [];
        foreach ($this->bulkDeleteTables() as $key => $_table) {
            if (!array_key_exists($key, $snapshot) || !is_array($snapshot[$key])) {
                throw new RuntimeException('The destructive DTR snapshot is incomplete.');
            }
            $counts[$key] = count($snapshot[$key]);
        }
        return $counts;
    }

    private function normalizedDeleteCounts(array $counts): array
    {
        $normalized = [];
        foreach ($this->bulkDeleteTables() as $key => $_table) {
            if (!array_key_exists($key, $counts) || (int)$counts[$key] < 0) {
                throw new RuntimeException('The destructive DTR table counts are incomplete.');
            }
            $normalized[$key] = (int)$counts[$key];
        }
        return $normalized;
    }

    private function assertDeleteCountsMatchSnapshot(array $expected, array $actual): void
    {
        foreach ($this->bulkDeleteTables() as $key => $_table) {
            if ((int)($expected[$key] ?? -1) !== (int)($actual[$key] ?? -2)) {
                throw new RuntimeException(
                    'The destructive DTR scope changed while exact evidence was being captured.'
                );
            }
        }
    }

    private function deleteTombstone(array $counts): array
    {
        $emptyRows = [];
        foreach ($this->bulkDeleteTables() as $key => $_table) {
            $emptyRows[$key] = [];
        }
        return [
            'deleted' => true,
            'tombstone' => true,
            'table_counts' => $counts,
            'rows' => $emptyRows,
        ];
    }

    private function bulkDeleteCounts(array $scope): array
    {
        $counts = [];
        foreach ($this->bulkDeleteTables() as $key => $table) {
            $sql = "SELECT COUNT(*) FROM {$table} a
                    {$scope['join']}
                    WHERE a.client_name = :client
                    AND a.pay_day = :pay_day
                    {$scope['where']}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($scope['params']);
            $counts[$key] = (int)$stmt->fetchColumn();
        }
        return $counts;
    }

    private function employeeDeleteCounts(int $employeeId): array
    {
        $counts = [];
        $params = [
            ':employee_id' => $employeeId,
            ':client' => trim((string)$this->client),
            ':pay_day' => trim((string)$this->pay_day),
            ':cut_off' => trim((string)$this->cut_off),
        ];
        foreach ($this->bulkDeleteTables() as $key => $table) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM {$table}
                 WHERE employee_id = :employee_id
                   AND client_name = :client
                   AND pay_day = :pay_day
                   AND cut_off = :cut_off"
            );
            $stmt->execute($params);
            $counts[$key] = (int)$stmt->fetchColumn();
        }
        return $counts;
    }

    private function bulkDeleteEmployeeCount(array $scope): int
    {
        $sql = "SELECT COUNT(DISTINCT a.employee_id) FROM dtr_upload a
                {$scope['join']}
                WHERE a.client_name = :client
                AND a.pay_day = :pay_day
                {$scope['where']}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($scope['params']);
        return (int)$stmt->fetchColumn();
    }

    private function bulkDeleteTables(): array
    {
        return [
            'dtr_upload' => 'dtr_upload',
            'payroll_gross_variables' => 'payroll_gross_variables',
            'payroll_other_additional' => 'payroll_other_additional',
            'payroll_other_deduction' => 'payroll_other_deduction',
            'payroll_summary' => 'payroll_summary',
        ];
    }

    private function writeMutationAudit(
        string $operation,
        string $reason,
        string $evidence,
        array $beforeSnapshot,
        array $afterSnapshot,
        ?string $calculatorRoutine,
        ?string $calculatorHash,
        array $scopeOverride = []
    ): string {
        $username = trim((string)$this->actor);
        if ($username === '') {
            throw new RuntimeException('DTR mutation audit actor is required.');
        }
        if (method_exists($this->db, 'inTransaction') && !$this->db->inTransaction()) {
            throw new RuntimeException('DTR mutation audit must share the payroll transaction.');
        }
        $operation = strtoupper(trim($operation));
        if (!in_array(
            $operation,
            ['EDIT', 'BENEFIT', 'DELETE_EMPLOYEE', 'DELETE_BULK'],
            true
        )) {
            throw new RuntimeException('Unsupported DTR mutation audit operation.');
        }
        $reason = trim($reason);
        $evidence = trim($evidence);
        if ($reason === '' || $evidence === '' || $beforeSnapshot === [] || $afterSnapshot === []) {
            throw new RuntimeException('Complete DTR mutation evidence is required.');
        }
        if (
            $calculatorHash !== null
            && preg_match('/^[a-f0-9]{64}$/', $calculatorHash) !== 1
        ) {
            throw new RuntimeException('The calculator audit hash is invalid.');
        }

        $defaultScope = [
            'scope_kind' => 'EMPLOYEE',
            'employee_id' => (int)$this->employee_ident,
            'client_name' => trim((string)$this->client),
            'cut_off' => trim((string)$this->cut_off) ?: null,
            'branch_id' => null,
            'client_location_id' => null,
            'period_start' => trim((string)$this->start_date) ?: null,
            'period_end' => trim((string)$this->end_date) ?: null,
            'pay_day' => trim((string)$this->pay_day),
        ];
        $auditScope = $scopeOverride === []
            ? $defaultScope
            : array_merge($defaultScope, $scopeOverride);
        $auditScope['scope_kind'] = strtoupper(trim((string)$auditScope['scope_kind']));
        if (!in_array($auditScope['scope_kind'], ['EMPLOYEE', 'PAYROLL_SCOPE'], true)) {
            throw new RuntimeException('The DTR mutation audit scope kind is invalid.');
        }
        if (
            $operation === 'DELETE_BULK'
            && $auditScope['scope_kind'] !== 'PAYROLL_SCOPE'
        ) {
            throw new RuntimeException('Bulk deletion requires a payroll-scope audit.');
        }
        if (
            $operation !== 'DELETE_BULK'
            && $auditScope['scope_kind'] !== 'EMPLOYEE'
        ) {
            throw new RuntimeException('Employee DTR mutations require an employee-scope audit.');
        }
        $auditScope['employee_id'] = $auditScope['employee_id'] === null
            ? null
            : (int)$auditScope['employee_id'];
        if (
            $auditScope['scope_kind'] === 'EMPLOYEE'
            && (int)$auditScope['employee_id'] < 1
        ) {
            throw new RuntimeException('The employee audit scope is invalid.');
        }
        if ($auditScope['scope_kind'] === 'PAYROLL_SCOPE') {
            $auditScope['employee_id'] = null;
            $auditScope['cut_off'] = null;
        }
        $auditScope['client_name'] = trim((string)$auditScope['client_name']);
        $auditScope['pay_day'] = trim((string)$auditScope['pay_day']);
        if ($auditScope['client_name'] === '' || $auditScope['pay_day'] === '') {
            throw new RuntimeException('The DTR mutation audit payroll scope is incomplete.');
        }
        foreach (['branch_id', 'client_location_id'] as $scopeId) {
            $auditScope[$scopeId] = $auditScope[$scopeId] === null
                ? null
                : (int)$auditScope[$scopeId];
            if ($auditScope[$scopeId] !== null && $auditScope[$scopeId] < 1) {
                throw new RuntimeException('The DTR mutation audit filter scope is invalid.');
            }
        }
        if (str_starts_with($operation, 'DELETE_')) {
            if (!isset($auditScope['table_counts']) || !is_array($auditScope['table_counts'])) {
                throw new RuntimeException('Exact destructive DTR table counts are required.');
            }
            $auditScope['table_counts'] = $this->normalizedDeleteCounts(
                $auditScope['table_counts']
            );
        }

        $beforePayload = $this->canonicalAuditJson($beforeSnapshot);
        $afterPayload = $this->canonicalAuditJson($afterSnapshot);
        $scopePayload = $this->canonicalAuditJson($auditScope);
        $beforeHash = hash('sha256', $beforePayload);
        $afterHash = hash('sha256', $afterPayload);
        $scopeHash = hash('sha256', $scopePayload);
        $eventId = 'DTRM-' . strtoupper(bin2hex(random_bytes(16)));

        $eventStmt = $this->db->prepare(
            'INSERT INTO dtr_mutation_audit_events ('
            . 'event_uid, operation, scope_kind, employee_id, client_name, cut_off, '
            . 'branch_id, client_location_id, period_start, period_end, pay_day, '
            . 'actor, change_reason, '
            . 'evidence_reference, before_payload, after_payload, before_hash, '
            . 'after_hash, scope_payload, scope_hash, calculator_routine, '
            . 'calculator_hash, created_at'
            . ') VALUES ('
            . ':event_uid, :operation, :scope_kind, :employee_id, :client_name, '
            . ':cut_off, :branch_id, :client_location_id, :period_start, '
            . ':period_end, :pay_day, :actor, :change_reason, '
            . ':evidence_reference, :before_payload, :after_payload, :before_hash, '
            . ':after_hash, :scope_payload, :scope_hash, :calculator_routine, '
            . ':calculator_hash, NOW()'
            . ')'
        );
        $eventStmt->execute([
            ':event_uid' => $eventId,
            ':operation' => $operation,
            ':scope_kind' => $auditScope['scope_kind'],
            ':employee_id' => $auditScope['employee_id'],
            ':client_name' => $auditScope['client_name'],
            ':cut_off' => $auditScope['cut_off'],
            ':branch_id' => $auditScope['branch_id'],
            ':client_location_id' => $auditScope['client_location_id'],
            ':period_start' => $auditScope['period_start'],
            ':period_end' => $auditScope['period_end'],
            ':pay_day' => $auditScope['pay_day'],
            ':actor' => $username,
            ':change_reason' => $reason,
            ':evidence_reference' => $evidence,
            ':before_payload' => $beforePayload,
            ':after_payload' => $afterPayload,
            ':before_hash' => $beforeHash,
            ':after_hash' => $afterHash,
            ':scope_payload' => $scopePayload,
            ':scope_hash' => $scopeHash,
            ':calculator_routine' => $calculatorRoutine,
            ':calculator_hash' => $calculatorHash,
        ]);
        if ($eventStmt->rowCount() !== 1) {
            throw new RuntimeException('The reconstructable DTR mutation audit was not persisted.');
        }

        $pointer = 'DTRMUT ' . $eventId . ' REF dtr_mutation_audit_events';
        if ($this->auditTextLength($pointer) > 100) {
            throw new RuntimeException('The legacy DTR audit pointer exceeds the log contract.');
        }
        $pointerStmt = $this->db->prepare(
            'INSERT INTO logs (username, log_action, inserted_date_time_ph) '
            . 'VALUES (:username, :log_action, NOW())'
        );
        $pointerStmt->execute([
            ':username' => $username,
            ':log_action' => $pointer,
        ]);
        if ($pointerStmt->rowCount() !== 1) {
            throw new RuntimeException('The legacy DTR audit pointer was not persisted.');
        }
        return $eventId;
    }

    private function canonicalAuditJson(array $snapshot): string
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
            $normalize($snapshot),
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
        );
    }

    private function auditTextLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    public function isLocked(){
        return (new PayrollLockGuard($this->db))->check(
            (string)$this->client,
            (string)$this->pay_day
        );
    }

    public function getPayDay(){

        $response = [];

        try {
            $sql = "SELECT 1 FROM client_payday 
                        WHERE client_name = :client";
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

    public function validateManualDates(){

        $response = [];

        try {
            $where = "";
            if($this->cut_off != "Weekly"){
                $where = " AND pay_day = :pay_day";
            }
            $sql = "SELECT 1 FROM client_payday 
                        WHERE client_name = :client
                        AND cut_off = :cut_off
                        $where";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':cut_off', $this->cut_off, PDO::PARAM_STR);
            if($this->cut_off != "Weekly"){
                $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($stmt->rowCount() > 0) {    
                $response['exists'] = true;
            } else {
                $response['exists'] = false;
            }
    
                    } catch (\Throwable $th) {
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator.";
                    }
    
        return $response;
    }

    public function getClientFilter(){

        $response = [];

        try {
            $sql = "SELECT distinct client_name from taascor_client
                    where client_name <> 'No Client' 
                    order by client_name";

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
                        LIMIT 1 ) as future_paydays
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


    
}


?>
