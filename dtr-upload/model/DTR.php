<?php
require_once __DIR__ . '/PayrollLockGuard.php';

class DTR
{
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


    public function spCalculateDTR(){
        
        try {
          $sql = "CALL sp_calculate_indv_dtr(
                        :client
                        ,:pay_day
                        ,:cut_off
                        ,:employee_id
                  )";
          if($this->cut_off == 'Weekly'){
            $sql = "CALL sp_calculate_indv_dtr_weekly(
              :client
              ,:pay_day
              ,:cut_off
              ,:employee_id
            )";
          }
          $stmt = $this->db->prepare($sql);
          $stmt->execute([
            ':client' => $this->client,
            ':pay_day' => $this->pay_day,
            ':cut_off' => $this->cut_off,
            ':employee_id' => $this->employee_ident,
          ]);
    
          $response = array(
            "success" => 1
          );
    
        } catch (PDOException $e) {
          error_log('DTR::spCalculateDTR failed: ' . $e->getMessage());
          $response = array(
            "success" => 0,
            "error" => "Unable to calculate DTR."
          );
     
        }
    
        return $response;
      }



    public function updateDTR(){

        $response = [];

        try {

            $sql = "INSERT INTO dtr_upload (
                employee_id,
                pay_day,
                cut_off,
                client_name,
                daily_salary,
                daily_worked,
                absent,
                lates,
                undertime,
                vacation_leave,
                sick_leave,
                overtime,
                night_diff,
                night_diff_ot,
                regular_holiday,
                regular_holiday_ot,
                regular_holiday_night_diff,
                special_holiday,
                special_holiday_ot,
                special_holiday_night_diff,
                rest_day,
                rest_day_ot,
                rest_day_night_diff,
                rest_day_regular_holiday,
                rest_day_regular_holiday_ot,
                rest_day_regular_holiday_night_diff,
                rest_day_special_holiday,
                rest_day_special_holiday_ot,
                rest_day_special_holiday_night_diff,
                start_date,
                end_date,
                regular_holiday_nd_ot,
                special_holiday_nd_ot,
                rest_day_nd_ot,
                rest_day_regular_holiday_nd_ot,
                rest_day_special_holiday_nd_ot
            ) VALUES (
                :employee_id,
                :pay_day,
                :cut_off,
                :client_name,
                :daily_salary,
                :daily_worked,
                :absent,
                :lates,
                :undertime,
                :vacation_leave,
                :sick_leave,
                :overtime,
                :night_diff,
                :night_diff_ot,
                :regular_holiday,
                :regular_holiday_ot,
                :regular_holiday_night_diff,
                :special_holiday,
                :special_holiday_ot,
                :special_holiday_night_diff,
                :rest_day,
                :rest_day_ot,
                :rest_day_night_diff,
                :rest_day_regular_holiday,
                :rest_day_regular_holiday_ot,
                :rest_day_regular_holiday_night_diff,
                :rest_day_special_holiday,
                :rest_day_special_holiday_ot,
                :rest_day_special_holiday_night_diff,
                :start_date,
                :end_date,
                :regular_holiday_nd_ot,
                :special_holiday_nd_ot,
                :rest_day_nd_ot,
                :rest_day_regular_holiday_nd_ot,
                :rest_day_special_holiday_nd_ot
            )
            ON DUPLICATE KEY UPDATE
                daily_salary = VALUES(daily_salary),
                daily_worked = VALUES(daily_worked),
                absent = VALUES(absent),
                lates = VALUES(lates),
                undertime = VALUES(undertime),
                vacation_leave = VALUES(vacation_leave),
                sick_leave = VALUES(sick_leave),
                overtime = VALUES(overtime),
                night_diff = VALUES(night_diff),
                night_diff_ot = VALUES(night_diff_ot),
                regular_holiday = VALUES(regular_holiday),
                regular_holiday_ot = VALUES(regular_holiday_ot),
                regular_holiday_night_diff = VALUES(regular_holiday_night_diff),
                special_holiday = VALUES(special_holiday),
                special_holiday_ot = VALUES(special_holiday_ot),
                special_holiday_night_diff = VALUES(special_holiday_night_diff),
                rest_day = VALUES(rest_day),
                rest_day_ot = VALUES(rest_day_ot),
                rest_day_night_diff = VALUES(rest_day_night_diff),
                rest_day_regular_holiday = VALUES(rest_day_regular_holiday),
                rest_day_regular_holiday_ot = VALUES(rest_day_regular_holiday_ot),
                rest_day_regular_holiday_night_diff = VALUES(rest_day_regular_holiday_night_diff),
                rest_day_special_holiday = VALUES(rest_day_special_holiday),
                rest_day_special_holiday_ot = VALUES(rest_day_special_holiday_ot),
                rest_day_special_holiday_night_diff = VALUES(rest_day_special_holiday_night_diff),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                regular_holiday_nd_ot = VALUES(regular_holiday_nd_ot),
                special_holiday_nd_ot = VALUES(special_holiday_nd_ot),
                rest_day_nd_ot = VALUES(rest_day_nd_ot),
                rest_day_regular_holiday_nd_ot = VALUES(rest_day_regular_holiday_nd_ot),
                rest_day_special_holiday_nd_ot = VALUES(rest_day_special_holiday_nd_ot)";

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

    public function removeGovtBenefits(){

        $response = [];

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE payroll_summary set employee_sss = 0,
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
                    AND pay_day = :pay_day";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();


            $sql = "UPDATE payroll_summary set 
                        taxable_income = CASE 
                            WHEN gross_income <= 10417 THEN ROUND((gross_income - (total_additional + total_ot)),2)
                            ELSE ROUND((gross_income - total_additional),2)
                        END
                    WHERE employee_id = :employee_id
                    AND client_name = :client
                    AND pay_day = :pay_day";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $sql = "UPDATE payroll_summary set employee_tax = 
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
                    AND pay_day = :pay_day";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $sql = "UPDATE payroll_summary set 
                        net_pay = ROUND(gross_income - 
                                        ROUND((employee_tax + total_deduction + total_tardy + employee_loan),2),2) 
                    WHERE employee_id = :employee_id
                    AND client_name = :client
                    AND pay_day = :pay_day";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $response['success'] = 1;
                        $this->db->commit(); 
        } catch (\Throwable $th) {
            $this->db->rollBack();
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function deleteEmployeeDTR(){

        $response = [];

        try {
            $this->db->beginTransaction();

            $sql = "DELETE FROM dtr_upload 
                    WHERE employee_id = :employee_id
                    AND client_name = :client
                    AND pay_day = :pay_day";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $sql = "DELETE FROM payroll_gross_variables 
                    WHERE employee_id = :employee_id
                    AND client_name = :client
                    AND pay_day = :pay_day";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $sql = "DELETE FROM payroll_other_additional
                    WHERE employee_id = :employee_id
                    AND client_name = :client
                    AND pay_day = :pay_day";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();


            $sql = "DELETE FROM payroll_other_deduction
                    WHERE employee_id = :employee_id
                    AND client_name = :client
                    AND pay_day = :pay_day";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

            $sql = "DELETE FROM payroll_summary
                    WHERE employee_id = :employee_id
                    AND client_name = :client
                    AND pay_day = :pay_day";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':employee_id', $this->employee_ident, PDO::PARAM_STR);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();


            $response['success'] = 1;
                        $this->db->commit(); 
        } catch (\Throwable $th) {
            $this->db->rollBack();
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
    }


    public function deleteDTRUpload(){

        $response = [];

        try {
            $this->db->beginTransaction();
            $where = "";
            $join = "";
            $empList = false;
            $filterParams = [];
            if($this->client_location != 'null'){
                $locationId = filter_var($this->client_location, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($locationId === false) {
                    throw new InvalidArgumentException('Invalid client location filter.');
                }
                $empList = true;
                $where .= " AND b.client_location_id = :client_location_id";
                $filterParams[':client_location_id'] = (int)$locationId;
            }

            if($this->branch != 'null'){
                $branchId = filter_var($this->branch, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($branchId === false) {
                    throw new InvalidArgumentException('Invalid branch filter.');
                }
                $empList = true;
                $where .= " AND b.branch_id = :branch_id";
                $filterParams[':branch_id'] = (int)$branchId;
            }

            if($empList){
                $join .= " INNER JOIN employee_list b on a.employee_id = b.employee_id";
            }

            $sql = "DELETE a FROM dtr_upload a 
                    $join
                    WHERE a.client_name = :client
                    AND a.pay_day = :pay_day 
                    $where";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
            }
            $stmt->execute();

            $sql = "DELETE a FROM payroll_gross_variables a
                    $join
                    WHERE a.client_name = :client
                    AND a.pay_day = :pay_day
                    $where";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
            }
            $stmt->execute();

            $sql = "DELETE a FROM payroll_other_additional a
                    $join
                    WHERE a.client_name = :client
                    AND a.pay_day = :pay_day
                    $where";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
            }
            $stmt->execute();


            $sql = "DELETE a FROM payroll_other_deduction a
                    $join
                    WHERE a.client_name = :client
                    AND a.pay_day = :pay_day
                    $where";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
            }
            $stmt->execute();

            $sql = "DELETE a FROM payroll_summary a
                    $join
                    WHERE a.client_name = :client
                    AND a.pay_day = :pay_day
                    $where";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            foreach ($filterParams as $name => $value) {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
            }
            $stmt->execute();


            $response['success'] = 1;
                        $this->db->commit(); 
        } catch (\Throwable $th) {
            $this->db->rollBack();
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; //dev
                    }

        return $response;
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
