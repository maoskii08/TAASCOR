<?php
require_once('../includes/auth_guard.php');
auth_require_role([1,3,5]);
require('../assets/vendor/libs/fpdf/fpdf.php');
require('../config/db_connect.php');

function pdf_amount($value): string
{
    return number_format((float)($value ?? 0), 2);
}



class PDF extends FPDF {
    function Header() {
        $loan_type = strtoupper($GLOBALS['loan_type'] ?? '');
        $loan_date = $GLOBALS['loan_date'] ?? '';

        $date = new DateTime($loan_date);
        $formattedDate = strtoupper($date->format("F, Y"));

        // Company Name
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, 'TAASCOR MANAGEMENT & GENERAL SERVICES CORPORATION', 0, 1, 'L');

        // Report Title
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, "LOANS COLLECTION REPORT FOR THE MONTH OF $formattedDate", 0, 1, 'L');

        // Loan Type
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, "LOAN TYPE : $loan_type", 0, 1);

        // Table Header
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(10, 7, '', 1, 0, 'C');  // Dummy ID column
        $this->Cell(50, 7, 'Employee Name', 1, 0, 'C');
        $this->Cell(30, 7, 'Date Awarded', 1, 0, 'C');
        $this->Cell(30, 7, '1st Cut-Off', 1, 0, 'C');
        $this->Cell(30, 7, '2nd Cut-Off', 1, 0, 'C');
        $this->Cell(40, 7, 'Collected This Month', 1, 1, 'C');
    }

    function Footer() {
        // Page Number
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page No. ' . $this->PageNo(), 0, 0, 'R');
    }
}


$loan_type = $_GET["lt"] ?? '';
$loan_date = $_GET["ld"] ?? '';
$loanDateObject = DateTime::createFromFormat('Y-m-d', $loan_date);
if (!$loan_type || !$loanDateObject || $loanDateObject->format('Y-m-d') !== $loan_date) {
    http_response_code(400);
    exit('Invalid report parameters.');
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);
$db = $pdoConn;
$sql = "WITH ranked_payments AS (
            SELECT 
                a.employee_id,
                a.payment_amount,
                a.pay_day,
                c.loan_date,
                b.first_name,
                b.last_name,
                ROW_NUMBER() OVER (
                    PARTITION BY a.employee_id ORDER BY a.payment_number
                ) AS rn
            FROM loans_payment a
            INNER JOIN employee_list b 
                ON a.employee_id = b.employee_id
            INNER JOIN employee_loans c 
                ON a.employee_id = c.employee_id 
                AND a.loan_type = c.loan_type
            WHERE MONTH(a.pay_day) = MONTH(:loan_date)
                AND YEAR(a.pay_day) = YEAR(:loan_date)
                AND a.loan_type = :loan_type
        )

        SELECT 
            CONCAT(last_name, ', ', first_name) AS employee_full_name,
            DATE_FORMAT(loan_date, '%m/%d/%Y') AS date_awarded,
            MAX(CASE WHEN rn = 1 THEN payment_amount ELSE NULL END) AS first_cutoff,
            MAX(CASE WHEN rn = 2 THEN payment_amount ELSE NULL END) AS second_cutoff,
            SUM(payment_amount) AS total_collected
        FROM ranked_payments
        GROUP BY employee_id, loan_date";

$stmt = $db->prepare($sql);
$stmt->bindValue(':loan_date', $loan_date, PDO::PARAM_STR);
$stmt->bindValue(':loan_type', $loan_type, PDO::PARAM_STR);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counter = 1;
foreach ($data as $row) {
    $pdf->Cell(10, 7, $counter++, 1, 0, 'C');
    $pdf->Cell(50, 7, $row['employee_full_name'], 1);
    $pdf->Cell(30, 7, $row['date_awarded'], 1, 0, 'C');
    $pdf->Cell(30, 7, pdf_amount($row['first_cutoff']), 1, 0, 'R');
    $pdf->Cell(30, 7, pdf_amount($row['second_cutoff']), 1, 0, 'R');
    $pdf->Cell(40, 7, pdf_amount($row['total_collected']), 1, 1, 'R');
}

$pdf->Output();
