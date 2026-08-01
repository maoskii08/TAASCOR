<?php
require_once('../includes/auth_guard.php');
auth_require_role([1,3]);
require('../assets/vendor/libs/fpdf/fpdf.php');
require('../config/db_connect.php');
require_once(__DIR__ . '/fuji-reference-rules.php');
require_once(__DIR__ . '/report-filter-rules.php');
require_once(__DIR__ . '/preview-binding-rules.php');
require_once(__DIR__ . '/../dtr-format-engine/model/PayrollLegacyScopeHasher.php');

function pdf_text($value): string
{
    $text = (string)($value ?? '');
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            return $converted;
        }
    }
    return $text;
}

class PDF extends FPDF
{
    public bool $fujiReferenceLayout = false;
    public bool $preReleasePreview = false;
    public string $previewWatermark = '';

    public function Header()
    {
        if (!$this->preReleasePreview || $this->previewWatermark === '') {
            return;
        }
        $this->SetXY(3, 1);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(150, 15, 55);
        $this->SetFillColor(255, 225, 235);
        $this->Cell(204, 6, $this->previewWatermark, 1, 0, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer()
    {
        if (!$this->preReleasePreview || $this->previewWatermark === '') {
            return;
        }
        $this->SetY(-8);
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor(170, 25, 80);
        $this->Cell(0, 4, $this->previewWatermark, 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    function PayslipTable($y_position, $data = array(), $otherAdditional = array(), $otherDeduction = array(), $loanList = array())
    {   
        $employee_id = $data['employee_id'];
        $employee_full_name = pdf_text(
            $this->fujiReferenceLayout
                ? strtoupper((string)($data['employee_reference_name'] ?? $data['employee_full_name']))
                : $data['employee_full_name']
        );
        $client_name = $data['client_name'];
        $location_name = $data['location_name'];
        $pay_day = date("m/d/Y", strtotime($data['pay_day']));
        $start_date = date("m/d/Y", strtotime($data['start_date']));
        $end_date = date("m/d/Y", strtotime($data['end_date']));
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
        $night_diff = $data['night_diff'] + $data['night_diff_ot'];
        $night_diff_hrs = $data['night_diff_hrs'] + $data['night_diff_ot_hrs'];
        $total_reg = $night_diff + $overtime;

        $regular_holiday = $data['regular_holiday'];
        $regular_holiday_hrs = $data['regular_holiday_hrs'];
        $regular_holiday_ot = $data['regular_holiday_ot'];
        $regular_holiday_ot_hrs = $data['regular_holiday_ot_hrs'];
        $regular_holiday_night_diff = $data['regular_holiday_night_diff'] + $data['regular_holiday_nd_ot'];
        $regular_holiday_night_diff_hrs = $data['regular_holiday_night_diff_hrs'] + $data['regular_holiday_nd_ot_hrs'];
        $total_reg_hol = $regular_holiday + $regular_holiday_ot + $regular_holiday_night_diff;

        $special_holiday = $data['special_holiday'];
        $special_holiday_hrs = $data['special_holiday_hrs'];
        $special_holiday_ot = $data['special_holiday_ot'];
        $special_holiday_ot_hrs = $data['special_holiday_ot_hrs'];
        $special_holiday_night_diff = $data['special_holiday_night_diff'] + $data['special_holiday_nd_ot'];
        $special_holiday_night_diff_hrs = $data['special_holiday_night_diff_hrs'] + $data['special_holiday_nd_ot_hrs'];
        $total_special_hol = $special_holiday + $special_holiday_ot + $special_holiday_night_diff;

        $rest_day = $data['rest_day'];
        $rest_day_hrs = $data['rest_day_hrs'];
        $rest_day_ot = $data['rest_day_ot'];
        $rest_day_ot_hrs = $data['rest_day_ot_hrs'];
        $rest_day_night_diff = $data['rest_day_night_diff'] + $data['rest_day_nd_ot'];
        $rest_day_night_diff_hrs = $data['rest_day_night_diff_hrs'] + $data['rest_day_nd_ot_hrs'];
        $total_rest_day = $rest_day + $rest_day_ot + $rest_day_night_diff;

        $rest_day_regular_holiday = $data['rest_day_regular_holiday'];
        $rest_day_regular_holiday_hrs = $data['rest_day_regular_holiday_hrs'];
        $rest_day_regular_holiday_ot = $data['rest_day_regular_holiday_ot'];
        $rest_day_regular_holiday_ot_hrs = $data['rest_day_regular_holiday_ot_hrs'];
        $rest_day_regular_holiday_night_diff = $data['rest_day_regular_holiday_night_diff'] + $data['rest_day_regular_holiday_nd_ot'];
        $rest_day_regular_holiday_night_diff_hrs = $data['rest_day_regular_holiday_night_diff_hrs'] + $data['rest_day_regular_holiday_nd_ot_hrs'];
        $total_rest_day_regular_hol = $rest_day_regular_holiday + $rest_day_regular_holiday_ot + $rest_day_regular_holiday_night_diff;

        $rest_day_special_holiday = $data['rest_day_special_holiday'];
        $rest_day_special_holiday_hrs = $data['rest_day_special_holiday_hrs'];
        $rest_day_special_holiday_ot = $data['rest_day_special_holiday_ot'];
        $rest_day_special_holiday_ot_hrs = $data['rest_day_special_holiday_ot_hrs'];
        $rest_day_special_holiday_night_diff = $data['rest_day_special_holiday_night_diff'] + $data['rest_day_special_holiday_nd_ot'];
        $rest_day_special_holiday_night_diff_hrs = $data['rest_day_special_holiday_night_diff_hrs'] + $data['rest_day_special_holiday_nd_ot_hrs'];
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
        $printed_total_deduction = $this->fujiReferenceLayout
            ? fuji_reference_printed_total_deductions($overall_deduction, $lates, $undertime)
            : $overall_deduction;

        $formatValue = function ($value, int $decimals = 2) {
            if ($this->fujiReferenceLayout && abs((float)$value) < 0.0000001) {
                return ' ';
            }
            return number_format((float)$value, $decimals);
        };

        $this->SetXY(3, $y_position);
        if ($this->fujiReferenceLayout) {
            $branchName = stripos($client_name, 'Fujifilm') !== false
                ? 'FUJIFILM OPTICS PHILS., CORP'
                : strtoupper($client_name);
            $this->SetFont('Arial', 'B', 8);
            $this->MultiCell(100, 7.5, "TAASCOR MANAGEMENT & GENERAL SERVICES CORPORATION\nEmployee Name   $employee_full_name", 1, 'L', false);
            $this->SetFont('Arial', '', 7);
            $this->SetXY(103, $y_position);
            $this->MultiCell(100, 5, "Payroll Period   $start_date      To   $end_date        Pay Date   $pay_day\nBranch             $branchName\nDepartment       NO DEPARTMENT", 1, 'L', false);
        } else {
            $this->SetFont('Arial', 'B', 9);
            $this->MultiCell(110, 5, "TAASCOR MANAGEMENT & GENERAL SERVICES CORPORATION\nClient: $client_name\nEmployee Name: $employee_full_name", 1, 'L', false);

            $this->SetFont('Arial', '', 8);
            $this->SetXY(113, $y_position);
            $this->MultiCell(90, 3.75, "Pay Date $pay_day\nPayroll Period $start_date To $end_date\nClient Location: $location_name\n ", 1, 'L', false);
        }

        $y_table_start = $y_position + 15;
        $this->SetFont('Arial', '', 7);
        $this->SetXY(3, $y_table_start);
        $this->Cell(30, 3.5, 'EARNINGS', 1, 0, 'C');
        $this->Cell(15, 3.5, 'DAY/HR', 1, 0, 'C');
        $this->Cell(20, 3.5, 'AMOUNT', 1, 0, 'C');
        $this->Cell(30, 3.5, 'DEDUCTIONS', 1, 0, 'C');
        $this->Cell(15, 3.5, '', 1, 0, 'C');
        $this->Cell(35, 3.5, 'AMOUNT', 1, 0, 'C');
        $this->Cell(55, 3.5, 'BREAKDOWN OF OTHER ADDITIONS', 1, 0, 'C');

        $this->SetXY(3, $y_table_start + 3.5);
        $this->MultiCell(30, 3.5, "BASIC RATE\nBASIC\nNON-TAX ALLOW\nTAXABLE ALLOW\nOTHER EARNINGS\nECOLA / RETRO\nVL\nSL", 1, 'L',false);
        $this->SetXY(33, $y_table_start + 3.5);
        $this->MultiCell(15, 3.5, "DAILY\n".number_format($daily_worked,2)."\n \n \n \n \n".$formatValue($vacation_leave_hrs)."\n".$formatValue($sick_leave_hrs), 1, 'C',false);
        $this->SetXY(48, $y_table_start + 3.5);
        $this->MultiCell(20, 3.5, number_format($daily_salary,2)."\n".number_format($basic_pay,2)."\n \n \n".$formatValue($other_earnings).
                                    "\n \n".$formatValue($vacation_leave)."\n".$formatValue($sick_leave), 1, 'R',false);
        $this->SetXY(68, $y_table_start + 3.5);
        $deductionLabels = $this->fujiReferenceLayout
            ? "SSS\nPHILHEALTH\nPAGIBIG\nWTAX (tax code Z)\nABSENT\nLATE/UNDERTIME\n \n "
            : "SSS\nPHILHEALTH\nPAGIBIG\nTAX\nLATE\nUNDERTIME\n \n ";
        $this->MultiCell(30, 3.5, $deductionLabels, 1, 'L',false);
        $this->SetXY(98, $y_table_start + 3.5);
        $deductionBasis = $this->fujiReferenceLayout
            ? " \n \n \n \n0.00\n".number_format(fuji_reference_late_undertime_hours($lates_hrs, $undertime_hrs), 4)."\n \n "
            : " \n \n \n \n".number_format($lates_hrs,2)."\n".number_format($undertime_hrs,2)."\n \n ";
        $this->MultiCell(15, 3.5, $deductionBasis, 1, 'C',false);
        $this->SetXY(113, $y_table_start + 3.5);
        $deductionAmounts = $this->fujiReferenceLayout
            ? $formatValue($employee_sss)."\n".$formatValue($employee_philhealth)."\n".$formatValue($employee_pagibig).
                "\n".$formatValue($employee_tax)."\n \n".$formatValue(fuji_reference_late_undertime_amount($lates, $undertime))."\n \n "
            : $formatValue($employee_sss)."\n".$formatValue($employee_philhealth)."\n".$formatValue($employee_pagibig).
                "\n".$formatValue($employee_tax)."\n".$formatValue($lates)."\n".$formatValue($undertime)."\n \n ";
        $this->MultiCell(35, 3.5, $deductionAmounts, 1, 'R',false);
        $this->SetXY(148, $y_table_start + 3.5);
        $this->MultiCell(40, 3.5, "{$addTxt[0]}\n{$addTxt[1]}\n{$addTxt[2]}\n{$addTxt[3]}\n{$addTxt[4]}\n{$addTxt[5]}", 1, 'L',false); //total other additional
        $this->SetXY(188, $y_table_start + 3.5);
        $this->MultiCell(15, 3.5, "{$add[0]}\n{$add[1]}\n{$add[2]}\n{$add[3]}\n{$add[4]}\n{$add[5]}", 1, 'R'); //total other additional amount

        $this->SetXY(148, $y_table_start + 24.5);
        $this->Cell(40, 3.5, 'TOTAL OTHER ADDITIONS', 1, 0, 'L');
        $this->Cell(15, 3.5, $formatValue($other_earnings), 1, 1, 'R');
        $this->SetXY(148, $y_table_start + 28);
        $this->Cell(55, 3.5, 'BREAKDOWN OF OTHER DEDUCTIONS', 1, 0, 'C');

        $this->SetXY(3, $y_table_start + 31.5);
        $this->Cell(20, 3.5, 'BREAKDOWN', 1, 0, 'C');
        $this->Cell(10, 3.5, 'REG', 1, 0, 'C');
        $this->Cell(10, 3.5, 'OT', 1, 0, 'C');
        $this->Cell(10, 3.5, 'NDIFF', 1, 0, 'C');
        $this->Cell(15, 3.5, 'TOTAL', 1, 0, 'C');
        $this->Cell(30, 3.5, 'LOANS', 1, 0, 'C');
        $this->Cell(15, 3.5, '# PAY', 1, 0, 'C');
        $this->Cell(17.5, 3.5, 'AMOUNT', 1, 0, 'C');
        $this->Cell(17.5, 3.5, 'BALANCE(S)', 1, 0, 'C');

        $this->SetXY(148, $y_table_start + 31.5);
        $this->MultiCell(40, 3.5, "{$dedTxt[0]}\n{$dedTxt[1]}\n{$dedTxt[2]}\n{$dedTxt[3]}\n{$dedTxt[4]}\n{$dedTxt[5]}", 1, 'L',false); //total other deduction
        $this->SetXY(188, $y_table_start + 31.5);
        $this->MultiCell(15, 3.5, "{$ded[0]}\n{$ded[1]}\n{$ded[2]}\n{$ded[3]}\n{$ded[4]}\n{$ded[5]}", 1, 'R',false); //total other deduction amount

        $this->SetXY(148, $y_table_start + 52.5);
        $this->Cell(40, 3.5, 'TOTAL OTHER DEDUCTIONS', 1, 0, 'L');
        $this->Cell(15, 3.5, $formatValue($other_deductions), 1, 1, 'R');
        $this->SetXY(148, $y_table_start + 56);
        $this->SetFont('Arial', 'B', 8);
        $this->MultiCell(40, 4, "TOTAL DEDUCTIONS\nNET PAY", 1, 'L');
        $this->SetXY(188, $y_table_start + 56);
        $this->MultiCell(15, 4, number_format($printed_total_deduction,2)."\n".number_format($net_pay,2), 1, 'R');

        $this->SetXY(148, $y_table_start + 64);
        $this->SetFont('Arial', '', 7);
        $this->MultiCell(30, 3, "Signature\n ", 1, 'L');
        $this->SetXY(178, $y_table_start + 64);
        $this->MultiCell(25, 3, "Date Received\n ", 1, 'L');

        $this->SetXY(3, $y_table_start + 35);
        $this->MultiCell(20, 3.5, "REGULAR OT\nREGULAR HOL\nSPECIAL HOL\nDAY OFF\nDO/REG. HOL\nDO/SPC HOL.", 1, 'L');

        $this->SetXY(3, $y_table_start + 56);
        $this->Cell(20, 3.5, $this->fujiReferenceLayout ? 'TOTAL OT' : 'Total OT', 1, 0, 'L');
        $this->Cell(10, 3.5, $formatValue($total_reg_hrs), 1, 0, 'C');
        $this->Cell(10, 3.5, $formatValue($total_ot_hrs), 1, 0, 'C');
        $this->Cell(10, 3.5, $formatValue($total_nd_hrs), 1, 0, 'C');
        $this->Cell(15, 3.5, number_format($total_ot,2), 1, 0, 'R');
        $this->SetXY(3, $y_table_start + 59.5);
        $this->SetFont('Arial', 'B', 8);
        $this->MultiCell(20, 5.25, "GROSS\nTAXABLE", 1, 'L');
        $this->SetXY(23, $y_table_start + 59.5);
        $this->MultiCell(45, 5.25, number_format($gross_income,2)."\n".number_format($taxable_income,2), 1, 'R');
        
        // //OT
        $this->SetFont('Arial', '', 7);
        $this->SetXY(23, $y_table_start + 35);
        $this->MultiCell(10, 3.5, " \n".$formatValue($regular_holiday_hrs)."\n".$formatValue($special_holiday_hrs)."\n".$formatValue($rest_day_hrs)."\n".
                            $formatValue($rest_day_regular_holiday_hrs)."\n".$formatValue($rest_day_special_holiday_hrs), 1, 'C',false); //reg
        $this->SetXY(33, $y_table_start + 35);
        $this->MultiCell(10, 3.5, $formatValue($overtime_hrs)."\n".$formatValue($regular_holiday_ot_hrs)."\n".$formatValue($special_holiday_ot_hrs)."\n".
                            $formatValue($rest_day_ot_hrs)."\n".$formatValue($rest_day_regular_holiday_ot_hrs)."\n".$formatValue($rest_day_special_holiday_ot_hrs), 1, 'C',false); //ot
        $this->SetXY(43, $y_table_start + 35);
        $this->MultiCell(10, 3.5, $formatValue($night_diff_hrs)."\n".$formatValue($regular_holiday_night_diff_hrs)."\n".$formatValue($special_holiday_night_diff_hrs)."\n".
                            $formatValue($rest_day_night_diff_hrs)."\n".$formatValue($rest_day_regular_holiday_night_diff_hrs)."\n".$formatValue($rest_day_special_holiday_night_diff_hrs), 1, 'C',false); //nd
        $this->SetXY(53, $y_table_start + 35);
        $this->MultiCell(15, 3.5, $formatValue($total_reg)."\n".$formatValue($total_reg_hol)."\n".$formatValue($total_special_hol)."\n".$formatValue($total_rest_day)."\n".
                            $formatValue($total_rest_day_regular_hol)."\n".$formatValue($total_rest_day_special_hol), 1, 'R',false); //total
        
        $this->SetXY(68, $y_table_start + 35);
        $loanLabels = $this->fujiReferenceLayout
            ? "SSS\nPAGIBIG\nCOMPANY\n \n \n \n \n \n \n "
            : "SSS\nSSS CALAMITY\nPAGIBIG\nPAGIBIG CALAMITY\nCOMPANY\n \n \n \n \n ";
        $this->MultiCell(30, 3.5, $loanLabels, 1, 'L',false);
        $this->SetXY(98, $y_table_start + 35);
        $loanPayNumbers = $this->fujiReferenceLayout
            ? $formatValue($sss_pay_num, 0)."\n".$formatValue($pagibig_pay_num, 0)."\n".$formatValue($company_pay_num, 0)."\n \n \n \n \n \n \n "
            : "$sss_pay_num\n$sss_calamity_pay_num\n$pagibig_pay_num\n$pagibig_calamity_pay_num\n$company_pay_num\n \n \n \n \n ";
        $this->MultiCell(15, 3.5, $loanPayNumbers, 1, 'C',false);
        $this->SetXY(113, $y_table_start + 35);
        $loanAmounts = $this->fujiReferenceLayout
            ? $formatValue($sss_loan)."\n".$formatValue($pagibig_loan)."\n".$formatValue($company_loan)."\n \n \n \n \n \n \n "
            : number_format($sss_loan,2)."\n".number_format($sss_calamity,2)."\n".number_format($pagibig_loan,2)."\n".number_format($pagibig_calamity,2)."\n".number_format($company_loan,2)."\n \n \n \n \n ";
        $this->MultiCell(17.5, 3.5, $loanAmounts, 1, 'R',false);
        $this->SetXY(130.5, $y_table_start + 35);
        $loanBalances = $this->fujiReferenceLayout
            ? $formatValue($sss_balance)."\n".$formatValue($pagibig_balance)."\n".$formatValue($company_balance)."\n \n \n \n \n \n \n "
            : number_format($sss_balance,2)."\n".number_format($sss_calamity_balance,2)."\n".number_format($pagibig_balance,2)."\n".number_format($pagibig_calamity_balance,2)."\n".number_format($company_balance,2)."\n \n \n \n \n ";
        $this->MultiCell(17.5, 3.5, $loanBalances, 1, 'R', false);
        $this->SetFont('Arial', '', 12);
        $this->SetXY(3, $y_table_start + 71);                    
        $this->Cell(200, 3, '---------------------------------------------------------------------------------------------------------------------------------------------', 0, 0, 'L');           
    }
}

$db = $pdoConn;
$clientName = $_GET["cn"] ?? '';
$cutOff = $_GET["co"] ?? '';
$payDay = $_GET["pd"] ?? '';
$clientConfig = $db->prepare('SELECT client_id FROM taascor_client WHERE client_name = :client LIMIT 1');
$clientConfig->execute([':client' => $clientName]);
$configuredClientId = $clientConfig->fetchColumn();
$layout = payslip_layout_for_client(
    $clientName,
    $configuredClientId === false ? null : (int)$configuredClientId
);

$bankName = 'null';
$payType = 'null';
$clientLocation = 'null';

$where = "";
$mainWhere = "";
$join = "";
$filterParams = [];

try {
    $employee_ident = payslip_positive_int_filter($_GET, 'ei');
    $clientLocationId = payslip_positive_int_filter($_GET, 'cl');
    $selectedRunId = payslip_positive_int_filter($_GET, 'run_id');
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid payslip filter.');
}

$serverRequiresPreview = false;
$legacyPreviewMode = false;
try {
    $settingsTable = $db->prepare("\n        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'payroll_import_client_settings'
    ");
    $settingsTable->execute();
    $settingsSchemaExists = (int)$settingsTable->fetchColumn() > 0;
    if (!$settingsSchemaExists || $configuredClientId === false) {
        $legacyPreviewMode = true;
    } else {
        $enrollment = $db->prepare("\n            SELECT smart_flow_enabled
            FROM payroll_import_client_settings
            WHERE client_id = :client_id
        ");
        $enrollment->execute([':client_id' => (int)$configuredClientId]);
        $smartFlowEnabled = (int)$enrollment->fetchColumn() === 1;
        if (!$smartFlowEnabled) {
            $legacyPreviewMode = true;
        } else {
            $serverRequiresPreview = true;
            $released = $db->prepare("\n                SELECT l.run_id
                FROM payroll_import_release_locks l
                INNER JOIN payroll_import_runs r ON r.id = l.run_id
                WHERE l.client_name = :client_name
                  AND l.pay_day = :pay_day
                  AND r.status = 'released'
                  AND r.release_status = 'released'
                LIMIT 1
            ");
            $released->execute([':client_name' => $clientName, ':pay_day' => $payDay]);
            $releasedRunId = (int)$released->fetchColumn();

            $selectedRun = [];
            if ($selectedRunId !== null) {
                $runQuery = $db->prepare("\n                    SELECT r.id, r.status, r.release_status, r.pay_date,
                           c.client_name,
                           b.run_id AS binding_run_id,
                           b.client_name AS binding_client_name,
                           b.pay_day AS binding_pay_day,
                           b.live_snapshot_hash AS binding_snapshot_hash,
                           b.employee_count AS binding_employee_count,
                           b.payroll_row_count AS binding_payroll_row_count,
                           b.snapshot_payload AS binding_snapshot_payload,
                           (
                               SELECT COUNT(*)
                               FROM payroll_import_release_checks rc
                               WHERE rc.run_id = r.id
                                 AND rc.check_code = 'LEGACY_SCOPE_BINDING'
                                 AND rc.is_blocking = 1
                                 AND rc.check_status = 'passed'
                           ) AS legacy_binding_check_passed
                    FROM payroll_import_runs r
                    INNER JOIN taascor_client c ON c.client_id = r.client_id
                    LEFT JOIN payroll_import_legacy_scope_bindings b ON b.run_id = r.id
                    WHERE r.id = :run_id
                      AND r.client_id = :client_id
                      AND c.client_name = :client_name
                      AND r.pay_date = :pay_day
                    LIMIT 1
                ");
                $runQuery->execute([
                    ':run_id' => $selectedRunId,
                    ':client_id' => (int)$configuredClientId,
                    ':client_name' => $clientName,
                    ':pay_day' => $payDay,
                ]);
                $selectedRun = $runQuery->fetch(PDO::FETCH_ASSOC) ?: [];
            }

            $liveScope = [];
            if ($releasedRunId <= 0 && count($selectedRun) > 0) {
                $liveScope = (new PayrollLegacyScopeHasher($db))->snapshot($clientName, $payDay);
            }
            $previewDecision = payslip_preview_binding_policy([
                'selected_run_id' => $selectedRunId,
                'released_run_id' => $releasedRunId,
                'client_name' => $clientName,
                'pay_day' => $payDay,
                'run' => $selectedRun,
                'live' => $liveScope,
            ]);
            if (($previewDecision['success'] ?? 0) !== 1) {
                http_response_code(409);
                header('Content-Type: text/plain; charset=utf-8');
                exit('Payslip preview blocked: ' . (string)($previewDecision['error'] ?? 'The selected run could not be verified.'));
            }

            if (($previewDecision['mode'] ?? '') === 'sealed_artifact') {
                $sealedQuery = ['run_id' => (int)$previewDecision['authoritative_run_id']];
                if ($employee_ident !== null) {
                    $sealedQuery['employee_id'] = $employee_ident;
                }
                header('Location: payslip-sealed.php?' . http_build_query($sealedQuery), true, 302);
                exit();
            }
        }
    }
} catch (Throwable $error) {
    error_log('Unable to determine sealed payslip route: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Payslip release status could not be verified. Please try again.');
}

if(isset($_GET['pt']) == true){
    $payType = $_GET["pt"];
}

if(isset($_GET['bn']) == true){
    $bankName = $_GET["bn"];
}

if($clientLocationId !== null){
    $clientLocation = (string)$clientLocationId;
}

if($employee_ident !== null){
    $where .= " AND a.employee_id = :employee_id";
    $mainWhere .= " AND a.employee_id = :employee_id";
    $filterParams[':employee_id'] = $employee_ident;
}

if($payType != 'null'){
    $where .= " AND s.pay_type = :pay_type";
    $mainWhere .= " AND s.pay_type = :pay_type";
    $filterParams[':pay_type'] = $payType;
}

if($bankName != 'null'){
    $where .= " AND s.bank_name = :bank_name";
    $mainWhere .= " AND s.bank_name = :bank_name";
    $filterParams[':bank_name'] = $bankName;
}

if($clientLocation != 'null'){
    $join .= "INNER JOIN employee_list b ON a.employee_id = b.employee_id";
    $where .= " AND b.client_location_id = :client_location_id";
    $mainWhere .= " AND c.client_location_id = :client_location_id";
    $filterParams[':client_location_id'] = $clientLocationId;
}

function bind_report_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $name => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($name, $value, $type);
    }
}

$otherAdditional = [];
$sql = "SELECT 
            sum(amount) as total_additional,
            a.employee_id,
            type_of_addition
        from payroll_other_additional a
        inner join employee_salary s on a.employee_id = s.employee_id
        $join
        where client_name = :client
            and cut_off = :cut_off
            and pay_day = :pay_day
            $where
        group by a.employee_id,
            type_of_addition";

$stmt = $db->prepare($sql);
bind_report_params($stmt, array_merge([
    ':client' => $clientName,
    ':cut_off' => $cutOff,
    ':pay_day' => $payDay
], $filterParams));
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
        $join
        where client_name = :client
            and cut_off = :cut_off
            and pay_day = :pay_day
            $where
        group by a.employee_id,
            type_of_deduction";

$stmt = $db->prepare($sql);
bind_report_params($stmt, array_merge([
    ':client' => $clientName,
    ':cut_off' => $cutOff,
    ':pay_day' => $payDay
], $filterParams));
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
        $join
        where client_name = :client
            and pay_day = :pay_day
            $where";

$stmt = $db->prepare($sql);
bind_report_params($stmt, array_merge([
    ':client' => $clientName,
    ':pay_day' => $payDay
], $filterParams));
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
            CONCAT(last_name, ', ', first_name) AS employee_reference_name,
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
            a.regular_holiday_nd_ot,
            a.special_holiday,
            a.special_holiday_ot,
            a.special_holiday_night_diff,
            a.special_holiday_nd_ot,
            a.rest_day,
            a.rest_day_ot,
            a.rest_day_night_diff,
            a.rest_day_nd_ot,
            a.rest_day_regular_holiday,
            a.rest_day_regular_holiday_ot,
            a.rest_day_regular_holiday_night_diff,
            a.rest_day_regular_holiday_nd_ot,
            a.rest_day_special_holiday,
            a.rest_day_special_holiday_ot,
            a.rest_day_special_holiday_night_diff,
            a.rest_day_special_holiday_nd_ot,
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
            COALESCE(b.regular_holiday_nd_ot,0) as regular_holiday_nd_ot_hrs,
            COALESCE(b.special_holiday_nd_ot,0) as special_holiday_nd_ot_hrs,
            COALESCE(b.rest_day_nd_ot,0) as rest_day_nd_ot_hrs,
            COALESCE(b.rest_day_regular_holiday_nd_ot,0) as rest_day_regular_holiday_nd_ot_hrs,
            COALESCE(b.rest_day_special_holiday_nd_ot,0) as rest_day_special_holiday_nd_ot_hrs,
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
        where a.client_name = :client
            and a.cut_off = :cut_off
            and a.pay_day = :pay_day
            $mainWhere";

$stmt = $db->prepare($sql);
bind_report_params($stmt, array_merge([
    ':client' => $clientName,
    ':cut_off' => $cutOff,
    ':pay_day' => $payDay
], $filterParams));
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$counter = 0;

$watermarkPolicy = payslip_dynamic_watermark_policy(
    $legacyPreviewMode,
    $serverRequiresPreview,
    (string)($_GET['preview'] ?? '') === '1'
);
$pdf = new PDF();
$pdf->fujiReferenceLayout = $layout === 'fuji-reference';
$pdf->preReleasePreview = (bool)$watermarkPolicy['required'];
$pdf->previewWatermark = (string)$watermarkPolicy['label'];
$pdf->AddPage();

$tables_per_page = 3; 
$table_spacing = 90;  
$y_start = $pdf->preReleasePreview ? 9 : 3;

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
