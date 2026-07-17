<?php
require('../assets/vendor/libs/fpdf/fpdf.php');
require('../config/db_connect.php');

class PDF extends FPDF
{
    function PayslipTable($y_position, $data = array(), $otherAdditional = array(), $otherDeduction = array(), $loanList = array())
    {   
        $employee_id = $data['employee_id'];
        $employee_full_name = utf8_decode($data['employee_full_name']);
        $client_name = $data['client_name'];
        $location_name = $data['location_name'];
        $pay_day = $data['pay_day'];
        $start_date = $data['start_date'];
        $end_date = $data['end_date'];
        $daily_salary = $data['daily_salary'];
        $daily_worked = $data['daily_worked'];
        $basic_pay = $data['basic_pay'];
        $other_earnings = $data['total_additional'];
        $other_deductions = $data['total_deduction'];
        $vacation_leave = $data['vacation_leave'];
        $vacation_leave_hrs = $data['vacation_leave_hrs'];
        $sick_leave = $data['sick_leave'];
        $sick_leave_hrs = $data['sick_leave_hrs'];

        $overtime = $data['overtime'];
        $overtime_hrs = $data['overtime_hrs'];
        $night_diff = $data['night_diff'];
        $night_diff_hrs = $data['night_diff_hrs'];
        $total_reg = $night_diff + $overtime;

        $regular_holiday = $data['regular_holiday'];
        $regular_holiday_hrs = $data['regular_holiday_hrs'];
        $regular_holiday_ot = $data['regular_holiday_ot'];
        $regular_holiday_ot_hrs = $data['regular_holiday_ot_hrs'];
        $regular_holiday_night_diff = $data['regular_holiday_night_diff'];
        $regular_holiday_night_diff_hrs = $data['regular_holiday_night_diff_hrs'];
        $total_reg_hol = $regular_holiday + $regular_holiday_ot + $regular_holiday_night_diff;

        $special_holiday = $data['special_holiday'];
        $special_holiday_hrs = $data['special_holiday_hrs'];
        $special_holiday_ot = $data['special_holiday_ot'];
        $special_holiday_ot_hrs = $data['special_holiday_ot_hrs'];
        $special_holiday_night_diff = $data['special_holiday_night_diff'];
        $special_holiday_night_diff_hrs = $data['special_holiday_night_diff_hrs'];
        $total_special_hol = $regular_holiday + $regular_holiday_ot + $regular_holiday_night_diff;

        $rest_day = $data['rest_day'];
        $rest_day_hrs = $data['rest_day_hrs'];
        $rest_day_ot = $data['rest_day_ot'];
        $rest_day_ot_hrs = $data['rest_day_ot_hrs'];
        $rest_day_night_diff = $data['rest_day_night_diff'];
        $rest_day_night_diff_hrs = $data['rest_day_night_diff_hrs'];
        $total_rest_day = $rest_day + $rest_day_ot + $rest_day_night_diff;

        $rest_day_regular_holiday = $data['rest_day_regular_holiday'];
        $rest_day_regular_holiday_hrs = $data['rest_day_regular_holiday_hrs'];
        $rest_day_regular_holiday_ot = $data['rest_day_regular_holiday_ot'];
        $rest_day_regular_holiday_ot_hrs = $data['rest_day_regular_holiday_ot_hrs'];
        $rest_day_regular_holiday_night_diff = $data['rest_day_regular_holiday_night_diff'];
        $rest_day_regular_holiday_night_diff_hrs = $data['rest_day_regular_holiday_night_diff_hrs'];
        $total_rest_day_regular_hol = $rest_day_regular_holiday + $rest_day_regular_holiday_ot + $rest_day_regular_holiday_night_diff;

        $rest_day_special_holiday = $data['rest_day_special_holiday'];
        $rest_day_special_holiday_hrs = $data['rest_day_special_holiday_hrs'];
        $rest_day_special_holiday_ot = $data['rest_day_special_holiday_ot'];
        $rest_day_special_holiday_ot_hrs = $data['rest_day_special_holiday_ot_hrs'];
        $rest_day_special_holiday_night_diff = $data['rest_day_special_holiday_night_diff'];
        $rest_day_special_holiday_night_diff_hrs = $data['rest_day_special_holiday_night_diff_hrs'];
        $total_rest_day_special_hol = $rest_day_special_holiday + $rest_day_special_holiday_ot + $rest_day_special_holiday_night_diff;

        $total_ot = $data['total_ot'];
        $total_reg_hrs = $regular_holiday_hrs + $special_holiday_hrs + $rest_day_hrs + $rest_day_regular_holiday_hrs + $rest_day_special_holiday_hrs;
        $total_ot_hrs = $overtime_hrs + $regular_holiday_ot_hrs + $special_holiday_ot_hrs + $rest_day_ot_hrs + $rest_day_regular_holiday_ot_hrs + $rest_day_special_holiday_ot_hrs;
        $total_nd_hrs = $night_diff_hrs + $regular_holiday_night_diff_hrs + $special_holiday_night_diff_hrs + $rest_day_night_diff_hrs + $rest_day_regular_holiday_night_diff_hrs + $rest_day_special_holiday_night_diff_hrs;

        $gross_income = $data['gross_income'];
        $taxable_income = $data['taxable_income'];

        $employee_sss = $data['employee_sss'];
        $employee_philhealth = $data['employee_philhealth'];
        $employee_pagibig = $data['employee_pagibig'];
        $employee_tax = $data['employee_tax'];

        $lates = $data['lates'];
        $lates_hrs = $data['lates_hrs'];
        $undertime = $data['undertime'];
        $undertime_hrs = $data['undertime_hrs'];

        $net_pay = $data['net_pay'];

        $addTxt = [' ', ' ', ' ', ' ', ' ', ' ']; 
        $add = [' ', ' ', ' ', ' ', ' ', ' ']; 

        if (!empty($otherAdditional[$employee_id])) {
            $index = 0; 
            foreach ($otherAdditional[$employee_id] as $item) { 
                foreach ($item as $key => $value) {
                    if ($index < 6) { 
                        $addTxt[$index] = $key;
                        $add[$index] = number_format($value,2);
                        $index++; 
                    } else {
                        break; 
                    }
                }
            }
        }

        $dedTxt = [' ', ' ', ' ', ' ', ' ', ' ']; 
        $ded = [' ', ' ', ' ', ' ', ' ', ' ']; 

        if (!empty($otherDeduction[$employee_id])) {
            $index = 0; 
            foreach ($otherDeduction[$employee_id] as $item) { 
                foreach ($item as $key => $value) {
                    if ($index < 6) { 
                        $dedTxt[$index] = $key;
                        $ded[$index] = number_format($value,2);
                        $index++; 
                    } else {
                        break; 
                    }
                }
            }
        }
        $company_loan = 0.00;
        $company_pay_num = 0;
        $company_balance = 0;

        $pagibig_loan = 0.00;
        $pagibig_pay_num = 0;
        $pagibig_balance = 0;

        $pagibig_calamity = 0.00;
        $pagibig_calamity_pay_num = 0;
        $pagibig_calamity_balance = 0;

        $sss_loan = 0.00;
        $sss_pay_num = 0;
        $sss_balance = 0;

        $sss_calamity = 0.00;
        $sss_calamity_pay_num = 0;
        $sss_calamity_balance = 0;

        if (!empty($loanList[$employee_id])) {
            foreach ($loanList[$employee_id] as $item) { 
                foreach ($item as $key => $value) {
                    if($key == 'Company Loan') { 
                        $company_pay_num = $value[0];
                        $company_loan = $value[1];
                        $company_balance = $value[2];
                    }else if($key == 'Pag-ibig Loan') { 
                        $pagibig_pay_num = $value[0];
                        $pagibig_loan = $value[1];
                        $pagibig_balance = $value[2];
                    }else if($key == 'Pag-ibig Calamity Loan'){
                        $pagibig_calamity_pay_num = $value[0];
                        $pagibig_calamity = $value[1];
                        $pagibig_calamity_balance = $value[2];
                    }else if($key == 'SSS Loan'){
                        $sss_pay_num = $value[0];
                        $sss_loan = $value[1];
                        $sss_balance = $value[2];
                    }else if($key == 'SSS Calamity Loan'){
                        $sss_calamity_pay_num = $value[0];
                        $sss_calamity = $value[1];
                        $sss_calamity_balance = $value[2];
                    }
                }
            }
        }
        
        $overall_deduction = $other_deductions + $lates + $undertime + $employee_sss + $employee_philhealth + $employee_pagibig + $employee_tax
                                            + $company_loan + $pagibig_loan + $pagibig_calamity + $sss_loan + $sss_calamity;

        $this->SetXY(3, $y_position);
        $this->SetFont('Arial', 'B', 9);
        $this->MultiCell(110, 5, "TAASCOR MANAGEMENT & GENERAL SERVICES CORPORATION\nClient: $client_name\nEmployee Name: $employee_full_name", 1, 'L', false);

        $this->SetFont('Arial', '', 8);
        $this->SetXY(113, $y_position);
        $this->MultiCell(90, 3.75, "Pay Date $pay_day\nPayroll Period $start_date To $end_date\nBranch: $location_name\n ", 1, 'L', false);    

        $y_table_start = $y_position + 15;
        $this->SetFont('Arial', '', 7);
        $this->SetXY(3, $y_table_start);
        $this->Cell(30, 3, 'EARNINGS', 1, 0, 'C');
        $this->Cell(15, 3, 'DAY/HR', 1, 0, 'C');
        $this->Cell(20, 3, 'AMOUNT', 1, 0, 'C');
        $this->Cell(30, 3, 'DEDUCTIONS', 1, 0, 'C');
        $this->Cell(15, 3, '', 1, 0, 'C');
        $this->Cell(35, 3, 'AMOUNT', 1, 0, 'C');
        $this->Cell(55, 3, 'BREAKDOWN OF OTHER ADDITIONS', 1, 0, 'C');

        $this->SetXY(3, $y_table_start + 3);
        $this->MultiCell(30, 3, "BASIC RATE\nBASIC\nNON-TAX ALLOW\nTAXABLE ALLOW\nOTHER EARNINGS\nECOLA / RETRO\nVL\nSL", 1, 'L',false);
        $this->SetXY(33, $y_table_start + 3);
        $this->MultiCell(15, 3, "DAILY\n".number_format($daily_worked,2)."\n \n \n \n \n".number_format($vacation_leave_hrs,2)."\n".number_format($sick_leave_hrs,2), 1, 'C',false);
        $this->SetXY(48, $y_table_start + 3);
        $this->MultiCell(20, 3, number_format($daily_salary,2)."\n".number_format($basic_pay,2)."\n \n \n".number_format($other_earnings,2).
                                    "\n \n".number_format($vacation_leave,2)."\n".number_format($sick_leave,2), 1, 'R',false);
        $this->SetXY(68, $y_table_start + 3);
        $this->MultiCell(30, 3, "SSS\nPHILHEALTH\nPAGIBIG\nTAX\nLATE\nUNDERTIME\n \n ", 1, 'L',false);
        $this->SetXY(98, $y_table_start + 3);
        $this->MultiCell(15, 3, " \n \n \n \n".number_format($lates_hrs,2)."\n".number_format($undertime_hrs,2)."\n \n ", 1, 'C',false);
        $this->SetXY(113, $y_table_start + 3);
        $this->MultiCell(35, 3, number_format($employee_sss,2)."\n".number_format($employee_philhealth,2)."\n".number_format($employee_pagibig,2).
                            "\n".number_format($employee_tax,2)."\n".number_format($lates,2)."\n".number_format($undertime,2)."\n \n ", 1, 'R',false);
        $this->SetXY(148, $y_table_start + 3);
        $this->MultiCell(40, 3, "{$addTxt[0]}\n{$addTxt[1]}\n{$addTxt[2]}\n{$addTxt[3]}\n{$addTxt[4]}\n{$addTxt[5]}", 1, 'L',false); //total other additional
        $this->SetXY(188, $y_table_start + 3);
        $this->MultiCell(15, 3, "{$add[0]}\n{$add[1]}\n{$add[2]}\n{$add[3]}\n{$add[4]}\n{$add[5]}", 1, 'R'); //total other additional amount

        $this->SetXY(148, $y_table_start + 21);
        $this->Cell(40, 3, 'TOTAL OTHER ADDITIONS', 1, 0, 'L');
        $this->Cell(15, 3, number_format($other_earnings,2), 1, 1, 'R');
        $this->SetXY(148, $y_table_start + 24);
        $this->Cell(55, 3, 'BREAKDOWN OF OTHER DEDUCTIONS', 1, 0, 'C');

        $this->SetXY(3, $y_table_start + 27);
        $this->Cell(20, 3, 'BREAKDOWN', 1, 0, 'C');
        $this->Cell(10, 3, 'REG', 1, 0, 'C');
        $this->Cell(10, 3, 'OT', 1, 0, 'C');
        $this->Cell(10, 3, 'NDIFF', 1, 0, 'C');
        $this->Cell(15, 3, 'TOTAL', 1, 0, 'C');
        $this->Cell(30, 3, 'LOANS', 1, 0, 'C');
        $this->Cell(15, 3, '# PAY', 1, 0, 'C');
        $this->Cell(17.5, 3, 'AMOUNT', 1, 0, 'C');
        $this->Cell(17.5, 3, 'BALANCE(S)', 1, 0, 'C');

        $this->SetXY(148, $y_table_start + 27);
        $this->MultiCell(40, 3, "{$dedTxt[0]}\n{$dedTxt[1]}\n{$dedTxt[2]}\n{$dedTxt[3]}\n{$dedTxt[4]}\n{$dedTxt[5]}", 1, 'L',false); //total other deduction
        $this->SetXY(188, $y_table_start + 27);
        $this->MultiCell(15, 3, "{$ded[0]}\n{$ded[1]}\n{$ded[2]}\n{$ded[3]}\n{$ded[4]}\n{$ded[5]}", 1, 'R',false); //total other deduction amount

        $this->SetXY(148, $y_table_start + 42);
        $this->Cell(40, 3, 'TOTAL OTHER DEDUCTIONS', 1, 0, 'L');
        $this->Cell(15, 3, number_format($other_deductions,2), 1, 1, 'R');
        $this->SetXY(148, $y_table_start + 45);
        $this->SetFont('Arial', 'B', 8);
        $this->MultiCell(40, 4.5, "TOTAL DEDUCTIONS\nNET PAY", 1, 'L');
        $this->SetXY(188, $y_table_start + 45);
        $this->MultiCell(15, 4.5, number_format($overall_deduction,2)."\n".number_format($net_pay,2), 1, 'R');

        $this->SetXY(148, $y_table_start + 54);
        $this->SetFont('Arial', '', 7);
        $this->MultiCell(30, 3, "Signature\n ", 1, 'L');
        $this->SetXY(178, $y_table_start + 54);
        $this->MultiCell(25, 3, "Date Received\n ", 1, 'L');

        $this->SetXY(3, $y_table_start + 30);
        $this->MultiCell(20, 3, "REGULAR OT\nREGULAR HOL\nSPECIAL HOL\nDAY OFF\nDO/REG. HOL\nDO/SPC HOL.", 1, 'L');

        $this->SetXY(3, $y_table_start + 48);
        $this->Cell(20, 3, 'Total OT', 1, 0, 'L');
        $this->Cell(10, 3, number_format($total_reg_hrs,2), 1, 0, 'C');
        $this->Cell(10, 3, number_format($total_ot_hrs,2), 1, 0, 'C');
        $this->Cell(10, 3, number_format($total_nd_hrs,2), 1, 0, 'C');
        $this->Cell(15, 3, number_format($total_ot,2), 1, 0, 'R');
        $this->SetXY(3, $y_table_start + 51);
        $this->SetFont('Arial', 'B', 8);
        $this->MultiCell(20, 4.5, "GROSS\nTAXABLE", 1, 'L');
        $this->SetXY(23, $y_table_start + 51);
        $this->MultiCell(45, 4.5, number_format($gross_income,2)."\n".number_format($taxable_income,2), 1, 'R');
        
        //OT
        $this->SetFont('Arial', '', 7);
        $this->SetXY(23, $y_table_start + 30);
        $this->MultiCell(10, 3, " \n".number_format($regular_holiday_hrs,2)."\n".number_format($special_holiday_hrs,2)."\n".number_format($rest_day_hrs,2)."\n".
                            number_format($rest_day_regular_holiday_hrs,2)."\n".number_format($rest_day_special_holiday_hrs,2), 1, 'C',false); //reg
        $this->SetXY(33, $y_table_start + 30);
        $this->MultiCell(10, 3, number_format($overtime_hrs,2)."\n".number_format($regular_holiday_ot_hrs,2)."\n".number_format($special_holiday_ot_hrs,2)."\n".
                            number_format($rest_day_ot_hrs,2)."\n".number_format($rest_day_regular_holiday_ot_hrs,2)."\n".number_format($rest_day_special_holiday_ot_hrs,2), 1, 'C',false); //ot
        $this->SetXY(43, $y_table_start + 30);
        $this->MultiCell(10, 3, number_format($night_diff_hrs,2)."\n".number_format($regular_holiday_night_diff_hrs,2)."\n".number_format($special_holiday_night_diff_hrs,2)."\n".
                            number_format($rest_day_night_diff_hrs,2)."\n".number_format($rest_day_regular_holiday_night_diff_hrs,2)."\n".number_format($rest_day_special_holiday_night_diff_hrs,2), 1, 'C',false); //nd
        $this->SetXY(53, $y_table_start + 30);
        $this->MultiCell(15, 3, number_format($total_reg,2)."\n".number_format($total_reg_hol,2)."\n".number_format($total_special_hol,2)."\n".number_format($total_rest_day,2)."\n".
                            number_format($total_rest_day_regular_hol,2)."\n".number_format($total_rest_day_special_hol,2), 1, 'R',false); //total
        
        $this->SetXY(68, $y_table_start + 30);
        $this->MultiCell(30, 3, "SSS\nSSS CALAMITY\nPAGIBIG\nPAGIBIG CALAMITY\nCOMPANY\n \n \n \n \n ", 1, 'L',false);
        $this->SetXY(98, $y_table_start + 30);
        $this->MultiCell(15, 3, "$sss_pay_num\n$sss_calamity_pay_num\n$pagibig_pay_num\n$pagibig_calamity_pay_num\n$company_pay_num\n \n \n \n \n ", 1, 'C',false);
        $this->SetXY(113, $y_table_start + 30);
        $this->MultiCell(17.5, 3, number_format($sss_loan,2)."\n".number_format($sss_calamity,2)."\n".number_format($pagibig_loan,2)."\n".number_format($pagibig_calamity,2)."\n".
                            number_format($company_loan,2)."\n \n \n \n \n ", 1, 'R',false);
        $this->SetXY(130.5, $y_table_start + 30);
        $this->MultiCell(17.5, 3, number_format($sss_balance,2)."\n".number_format($sss_calamity_balance,2)."\n".number_format($pagibig_balance,2)."\n".number_format($pagibig_calamity_balance,2)."\n".
                            number_format($company_balance,2)."\n \n \n \n \n ", 1, 'R', false);
        $this->SetFont('Arial', '', 12);
        $this->SetXY(3, $y_table_start + 61);                    
        $this->Cell(200, 3, '---------------------------------------------------------------------------------------------------------------------------------------------', 0, 0, 'L');           
    }
}

$db = $pdoConn;
$clientName = $_GET["cn"];
$cutOff = $_GET["co"];
$payDay = $_GET["pd"];
$payType = $_GET["pt"];
$bankName = $_GET["bn"];

$pdf = new PDF();
$pdf->AddPage();

$where = "";
if($payType != 'null'){
    $where .= " AND pay_type = '{$payType}'";
}

if($bankName != 'null'){
    $where .= " AND bank_name = '{$bankName}'";
}

$otherAdditional = [];
$sql = "SELECT 
            sum(amount) as total_additional,
            a.employee_id,
            type_of_addition
        from payroll_other_additional a
        inner join employee_salary s on a.employee_id = s.employee_id
        where client_name = '{$clientName}'
            and cut_off = '{$cutOff}' 
            and pay_day = '{$payDay}'
            $where
        group by a.employee_id,
            type_of_addition";

$stmt = $db->prepare($sql);
$stmt->execute();
$additional = $stmt->fetchAll(PDO::FETCH_ASSOC);

if($stmt->rowCount() > 0){
    foreach ($additional as $row) {
        $otherAdditional[$row['employee_id']][] = 
        array(
            $row['type_of_addition'] => $row['total_additional']
        );  
    } 
}

$otherDeduction = [];
$sql = "SELECT 
            sum(amount) as total_deduction,
            a.employee_id,
            type_of_deduction
        from payroll_other_deduction a
        inner join employee_salary s on a.employee_id = s.employee_id
        where client_name = '{$clientName}'
            and cut_off = '{$cutOff}' 
            and pay_day = '{$payDay}'
            $where
        group by a.employee_id,
            type_of_deduction";

$stmt = $db->prepare($sql);
$stmt->execute();
$deduction = $stmt->fetchAll(PDO::FETCH_ASSOC);

if($stmt->rowCount() > 0){
    foreach ($deduction as $row) {
        $otherDeduction[$row['employee_id']][] = 
        array(
            $row['type_of_deduction'] => $row['total_deduction']
        );  
    } 
}

$loanList = [];
$sql = "SELECT a.employee_id, 
                loan_type,
                payment_number,
                payment_amount,
                loan_running_balance
        from loans_payment a
        inner join employee_salary s on a.employee_id = s.employee_id
        where client_name = '{$clientName}'
            and pay_day = '{$payDay}' $where";

$stmt = $db->prepare($sql);
$stmt->execute();
$loan = $stmt->fetchAll(PDO::FETCH_ASSOC);

if($stmt->rowCount() > 0){
    foreach ($loan as $row) {
        $loanList[$row['employee_id']][] = 
        array(
            $row['loan_type'] => array($row['payment_number'], $row['payment_amount'], $row['loan_running_balance'])
        );  
    } 
}

$sql = "SELECT 
            a.employee_id,
            CONCAT(first_name, ' ', last_name) AS employee_full_name,    
            a.start_date,
            a.end_date,
            a.pay_day,
            b.daily_salary,
            daily_worked,
            basic_pay,
            a.night_diff,
            a.night_diff_ot,
            a.lates,
            a.undertime,
            a.vacation_leave,
            a.sick_leave,
            a.overtime,
            a.regular_holiday,
            a.regular_holiday_ot,
            a.regular_holiday_night_diff,
            a.special_holiday,
            a.special_holiday_ot,
            a.special_holiday_night_diff,
            a.rest_day,
            a.rest_day_ot,
            a.rest_day_night_diff,
            a.rest_day_regular_holiday,
            a.rest_day_regular_holiday_ot,
            a.rest_day_regular_holiday_night_diff,
            a.rest_day_special_holiday,
            a.rest_day_special_holiday_ot,
            a.rest_day_special_holiday_night_diff,
            a.lates,
            a.undertime,
            COALESCE(b.night_diff,0) as night_diff_hrs,
            COALESCE(b.night_diff_ot,0) as night_diff_ot_hrs,
            COALESCE(b.lates,0) as lates_min,
            COALESCE(b.undertime,0) as undertime_hrs,
            COALESCE(b.vacation_leave,0) as vacation_leave_hrs,
            COALESCE(b.sick_leave,0) as sick_leave_hrs,
            COALESCE(b.overtime,0) as overtime_hrs,
            COALESCE(b.regular_holiday,0) as regular_holiday_hrs,
            COALESCE(b.regular_holiday_ot,0) as regular_holiday_ot_hrs,
            COALESCE(b.regular_holiday_night_diff,0) as regular_holiday_night_diff_hrs,
            COALESCE(b.special_holiday,0) as special_holiday_hrs,
            COALESCE(b.special_holiday_ot,0) as special_holiday_ot_hrs,
            COALESCE(b.special_holiday_night_diff,0) as special_holiday_night_diff_hrs,
            COALESCE(b.rest_day,0) as rest_day_hrs,
            COALESCE(b.rest_day_ot,0) as rest_day_ot_hrs,
            COALESCE(b.rest_day_night_diff,0) as rest_day_night_diff_hrs,
            COALESCE(b.rest_day_regular_holiday,0) as rest_day_regular_holiday_hrs,
            COALESCE(b.rest_day_regular_holiday_ot,0) as rest_day_regular_holiday_ot_hrs,
            COALESCE(b.rest_day_regular_holiday_night_diff,0) as rest_day_regular_holiday_night_diff_hrs,
            COALESCE(b.rest_day_special_holiday,0) as rest_day_special_holiday_hrs,
            COALESCE(b.rest_day_special_holiday_ot,0) as rest_day_special_holiday_ot_hrs,
            COALESCE(b.rest_day_special_holiday_night_diff,0) as rest_day_special_holiday_night_diff_hrs,
            COALESCE(b.lates,0) as lates_hrs,
            COALESCE(b.undertime,0) as undertime_hrs,
            d.total_additional,
            d.total_deduction,
            d.total_ot,
            d.gross_income,
            d.taxable_income,
            ROUND((d.employee_sss + d.employee_sss_mpf),2) as employee_sss,
            d.employee_philhealth,
            d.employee_pagibig,
            d.employee_tax,
            d.net_pay,
            COALESCE(cl.client_name,'') as client_name,
            COALESCE(cll.location_name,'') as location_name
        from payroll_gross_variables a 
        inner join dtr_upload b on a.client_name = b.client_name
            and a.employee_id = b.employee_id
            and a.cut_off = b.cut_off
            and a.pay_day = b.pay_day
        inner join employee_list c on a.employee_id = c.employee_id
        inner join payroll_summary d on a.employee_id = d.employee_id
            and a.client_name = d.client_name
            and a.cut_off = d.cut_off
            and a.pay_day = d.pay_day
        inner join employee_salary s on a.employee_id = s.employee_id
        left join taascor_client cl on c.client_id = cl.client_id
        left join taascor_client_location cll on c.client_location_id = cll.location_id
        where a.client_name = '{$clientName}'
            and a.cut_off = '{$cutOff}' 
            and a.pay_day = '{$payDay}' 
            $where";

$stmt = $db->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$counter = 0;

$tables_per_page = 3; 
$table_spacing = 80;  
$y_start = 3; 

foreach ($data as $row) {
    $y_position = $y_start + ($counter % $tables_per_page) * $table_spacing; 
    if ($counter > 0 && $counter % $tables_per_page == 0) {
        $pdf->AddPage();
        $y_position = $y_start; 
    }
    $pdf->PayslipTable($y_position, $row, $otherAdditional, $otherDeduction, $loanList);
    $counter++;
}



$pdf->Output();
