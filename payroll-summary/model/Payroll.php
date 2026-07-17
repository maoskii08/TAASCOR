<?php

class Payroll
{
    public $db = null;    

    public function getPayrollSummary(){

        $response = [];

        try {

            $sql = "SELECT 
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
                    ) as x group by client_name order by client_name";

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
            $response['error'] = "An error occurred. Please contact your administrator."; 
                    }

        return $response;
    }
}


?>