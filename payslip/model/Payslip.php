<?php
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

    public $cutoffArray = array();

    public function getPayrollSummary(){

        $response = [];

        try {
            $where = "";
            $join = "";
            if($this->client_location != 'null' || $this->branch != 'null'){
                $join = "INNER JOIN employee_list b ON a.employee_id = b.employee_id";
            }

            if($this->pay_type != 'null'){
                $where .= " AND pay_type = '{$this->pay_type}'";
            }

            if($this->bank_name != 'null'){
                $where .= " AND bank_name = '{$this->bank_name}'";
            }

            if($this->client_location != 'null'){
                $where .= " AND client_location_id = {$this->client_location}";
            }

            if($this->branch != 'null'){
                $where .= " AND branch_id = {$this->branch}";
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
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($stmt->rowCount() > 0){
                $response['data2'] = $data;                 
            } else {
                $response['data2'] = [];       
            }
            $response['success'] = 1;
            $response['sql1'] = $sql1;
            $response['sql2'] = $sql2;     
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

        try {
            $this->db->beginTransaction();
            $sql = "INSERT IGNORE INTO locked_payroll(client_name, pay_day) 
                    values(:client,:pay_day)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->bindParam(':pay_day', $this->pay_day, PDO::PARAM_STR);
            $stmt->execute();

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
                            ORDER BY lp.pay_day DESC 
                            LIMIT 1) AS latest_balance
                        FROM loans_payment a
                        WHERE a.client_name = :client
                        GROUP BY a.employee_id, a.loan_type
                    ) AS x
                    ON el.employee_id = x.employee_id
                    AND el.loan_type = x.loan_type
                    SET in_system_payment = total_payment,
                        loan_running_balance = latest_balance";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':client', $this->client, PDO::PARAM_STR);
            $stmt->execute();
            
            $this->db->commit();

            $response['success'] = 1;
                    } catch (\Throwable $th) {
            $this->db->rollBack();
            $response['success'] = 0;
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }


    public function isLocked(){

        $response = [];

        try {
            $sql = "SELECT 1 FROM locked_payroll 
                        WHERE client_name = '{$this->client}'
                        and pay_day = '{$this->pay_day}'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($stmt->rowCount() > 0) {    
                $response['locked'] = true;
            } else {
                $response['locked'] = false;
            }
    
                    } catch (\Throwable $th) {
            $response['success'] = 0;
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
            $sql = "SELECT distinct client_name from taascor_client
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