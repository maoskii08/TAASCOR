<?php
/**
 * billing/invoice.php
 * Generates a printable payroll billing invoice for one client+period.
 * Accessed via GET: ?client=...&pay_day=...&cut_off=...
 */
session_start();
if (!isset($_SESSION['taascor_access_level'])) {
    header("Location: ../login/"); exit();
}
if ((int)$_SESSION['taascor_access_level'] !== 1) {
    http_response_code(403); echo "Access denied."; exit();
}

require('../config/db_connect.php');

$client  = $_GET['client']  ?? '';
$pay_day = $_GET['pay_day'] ?? '';
$cut_off = $_GET['cut_off'] ?? '';

if (!$client || !$pay_day) {
    echo "Missing parameters."; exit();
}

// ── Data ──────────────────────────────────────────────────────────────────
$sql = "SELECT
            a.employee_id,
            CONCAT(b.last_name, ', ', b.first_name) AS employee_name,
            r.daily_salary,
            r.daily_worked,
            r.daily_salary * r.daily_worked  AS basic_pay,
            a.total_ot,
            a.total_additional,
            a.gross_income,
            a.employee_tax,
            a.total_tardy,
            a.employee_sss,
            a.employee_sss_mpf,
            a.employee_philhealth,
            a.employee_pagibig,
            a.employee_loan,
            a.total_deduction,
            a.net_pay,
            a.employer_sss,
            a.employer_sss_mpf,
            a.employer_sss_ec,
            a.employer_philhealth,
            a.employer_pagibig
        FROM payroll_summary a
        INNER JOIN employee_list b ON a.employee_id = b.employee_id
        INNER JOIN dtr_upload r
            ON  a.employee_id = r.employee_id
            AND a.client_name = r.client_name
            AND a.cut_off     = r.cut_off
            AND a.pay_day     = r.pay_day
        WHERE a.client_name = :client
          AND a.cut_off     = :cut_off
          AND a.pay_day     = :pay_day
        ORDER BY b.last_name, b.first_name";

$stmt = $pdoConn->prepare($sql);
$stmt->bindParam(':client',  $client,  PDO::PARAM_STR);
$stmt->bindParam(':cut_off', $cut_off, PDO::PARAM_STR);
$stmt->bindParam(':pay_day', $pay_day, PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totals = array_fill_keys([
    'basic_pay','total_ot','total_additional','gross_income','employee_tax',
    'total_tardy','employee_sss','employee_sss_mpf','employee_philhealth',
    'employee_pagibig','employee_loan','total_deduction','net_pay',
    'employer_sss','employer_sss_mpf','employer_sss_ec','employer_philhealth','employer_pagibig'
], 0);
foreach ($rows as $r) { foreach ($totals as $k => &$v) { $v += (float)($r[$k] ?? 0); } }
unset($v);

$fmt = fn($n) => number_format((float)$n, 2);
$headcount = count($rows);
$invoiceNo = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $client), 0, 4))
           . '-' . date('Ymd', strtotime($pay_day));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?= htmlspecialchars($invoiceNo) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; margin: 20px; }
  h2 { margin: 0; font-size: 16px; }
  h4 { margin: 0 0 4px; }
  .header-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
  .logo-text { font-size: 20px; font-weight: bold; color: #1a237e; }
  .invoice-meta { text-align: right; }
  .invoice-meta td { padding: 1px 4px; }
  .divider { border-top: 2px solid #1a237e; margin: 8px 0; }
  table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.data th { background: #1a237e; color: #fff; padding: 4px 5px; text-align: center; white-space: nowrap; }
  table.data td { padding: 3px 5px; border-bottom: 1px solid #ddd; white-space: nowrap; }
  table.data tr:nth-child(even) td { background: #f5f5f5; }
  .text-end { text-align: right; }
  .text-center { text-align: center; }
  .totals-row td { font-weight: bold; background: #e8eaf6 !important; border-top: 2px solid #1a237e; }
  .section-header { background: #3949ab !important; }
  .summary-box { border: 1px solid #bbb; border-radius: 4px; padding: 10px 14px; margin-top: 16px; display: inline-block; min-width: 280px; }
  .summary-box table td { padding: 2px 8px; }
  .summary-box .label { color: #555; }
  .summary-box .amount { font-weight: bold; text-align: right; }
  .print-btn { position: fixed; top: 10px; right: 10px; padding: 8px 18px; background: #1a237e; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
  @media print { .print-btn { display: none; } body { margin: 10px; } }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Print / Save PDF</button>

<div class="header-row">
  <div>
    <div class="logo-text">TAASCOR HRIS</div>
    <div style="color:#555; font-size:11px;">Powered by VisioTech Solutions</div>
    <br>
    <h4>PAYROLL BILLING INVOICE</h4>
  </div>
  <table class="invoice-meta">
    <tr><td class="label">Invoice No.:</td><td><strong><?= htmlspecialchars($invoiceNo) ?></strong></td></tr>
    <tr><td class="label">Date Generated:</td><td><?= date('F d, Y') ?></td></tr>
    <tr><td class="label">Client:</td><td><strong><?= htmlspecialchars($client) ?></strong></td></tr>
    <tr><td class="label">Pay Day:</td><td><?= htmlspecialchars($pay_day) ?></td></tr>
    <tr><td class="label">Cut-off:</td><td><?= htmlspecialchars($cut_off) ?></td></tr>
    <tr><td class="label">Headcount:</td><td><?= $headcount ?> employees</td></tr>
  </table>
</div>
<div class="divider"></div>

<!-- Per-employee detail -->
<table class="data">
  <thead>
    <tr>
      <th rowspan="2">ID</th>
      <th rowspan="2">Employee Name</th>
      <th rowspan="2">Daily Rate</th>
      <th rowspan="2">Days</th>
      <th rowspan="2">Basic Pay</th>
      <th rowspan="2">OT</th>
      <th rowspan="2">Additions</th>
      <th rowspan="2">Gross</th>
      <th colspan="6" class="section-header">Employee Deductions</th>
      <th rowspan="2">Net Pay</th>
      <th colspan="5" class="section-header">Employer Contributions</th>
    </tr>
    <tr>
      <th>Tax</th><th>SSS</th><th>PhilHealth</th><th>Pag-IBIG</th><th>Loan</th><th>Other</th>
      <th>SSS</th><th>SSS-MPF</th><th>EC</th><th>PhilHealth</th><th>Pag-IBIG</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="text-center"><?= $r['employee_id'] ?></td>
      <td><?= htmlspecialchars($r['employee_name']) ?></td>
      <td class="text-end"><?= $fmt($r['daily_salary']) ?></td>
      <td class="text-center"><?= $r['daily_worked'] ?></td>
      <td class="text-end"><?= $fmt($r['basic_pay']) ?></td>
      <td class="text-end"><?= $fmt($r['total_ot']) ?></td>
      <td class="text-end"><?= $fmt($r['total_additional']) ?></td>
      <td class="text-end"><?= $fmt($r['gross_income']) ?></td>
      <td class="text-end"><?= $fmt($r['employee_tax']) ?></td>
      <td class="text-end"><?= $fmt($r['employee_sss']) ?></td>
      <td class="text-end"><?= $fmt($r['employee_philhealth']) ?></td>
      <td class="text-end"><?= $fmt($r['employee_pagibig']) ?></td>
      <td class="text-end"><?= $fmt($r['employee_loan']) ?></td>
      <td class="text-end"><?= $fmt($r['total_deduction']) ?></td>
      <td class="text-end"><strong><?= $fmt($r['net_pay']) ?></strong></td>
      <td class="text-end"><?= $fmt($r['employer_sss']) ?></td>
      <td class="text-end"><?= $fmt($r['employer_sss_mpf']) ?></td>
      <td class="text-end"><?= $fmt($r['employer_sss_ec']) ?></td>
      <td class="text-end"><?= $fmt($r['employer_philhealth']) ?></td>
      <td class="text-end"><?= $fmt($r['employer_pagibig']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="totals-row">
      <td colspan="4" class="text-center">TOTALS (<?= $headcount ?> employees)</td>
      <td class="text-end"><?= $fmt($totals['basic_pay']) ?></td>
      <td class="text-end"><?= $fmt($totals['total_ot']) ?></td>
      <td class="text-end"><?= $fmt($totals['total_additional']) ?></td>
      <td class="text-end"><?= $fmt($totals['gross_income']) ?></td>
      <td class="text-end"><?= $fmt($totals['employee_tax']) ?></td>
      <td class="text-end"><?= $fmt($totals['employee_sss']) ?></td>
      <td class="text-end"><?= $fmt($totals['employee_philhealth']) ?></td>
      <td class="text-end"><?= $fmt($totals['employee_pagibig']) ?></td>
      <td class="text-end"><?= $fmt($totals['employee_loan']) ?></td>
      <td class="text-end"><?= $fmt($totals['total_deduction']) ?></td>
      <td class="text-end"><?= $fmt($totals['net_pay']) ?></td>
      <td class="text-end"><?= $fmt($totals['employer_sss']) ?></td>
      <td class="text-end"><?= $fmt($totals['employer_sss_mpf']) ?></td>
      <td class="text-end"><?= $fmt($totals['employer_sss_ec']) ?></td>
      <td class="text-end"><?= $fmt($totals['employer_philhealth']) ?></td>
      <td class="text-end"><?= $fmt($totals['employer_pagibig']) ?></td>
    </tr>
  </tfoot>
</table>

<!-- Summary box -->
<br>
<div class="summary-box">
  <h4 style="margin-bottom:8px; color:#1a237e;">Billing Summary</h4>
  <table>
    <tr><td class="label">Total Gross Income:</td>   <td class="amount">₱ <?= $fmt($totals['gross_income']) ?></td></tr>
    <tr><td class="label">Total Net Pay:</td>         <td class="amount">₱ <?= $fmt($totals['net_pay']) ?></td></tr>
    <tr><td class="label" colspan="2"><hr style="margin:4px 0"></td></tr>
    <tr><td class="label">Employer SSS:</td>          <td class="amount">₱ <?= $fmt($totals['employer_sss']) ?></td></tr>
    <tr><td class="label">Employer SSS-MPF:</td>      <td class="amount">₱ <?= $fmt($totals['employer_sss_mpf']) ?></td></tr>
    <tr><td class="label">Employer EC:</td>           <td class="amount">₱ <?= $fmt($totals['employer_sss_ec']) ?></td></tr>
    <tr><td class="label">Employer PhilHealth:</td>   <td class="amount">₱ <?= $fmt($totals['employer_philhealth']) ?></td></tr>
    <tr><td class="label">Employer Pag-IBIG:</td>     <td class="amount">₱ <?= $fmt($totals['employer_pagibig']) ?></td></tr>
    <tr><td class="label" colspan="2"><hr style="margin:4px 0"></td></tr>
    <tr>
      <td class="label"><strong>Total Employer Contributions:</strong></td>
      <td class="amount"><strong>₱ <?= $fmt($totals['employer_sss']+$totals['employer_sss_mpf']+$totals['employer_sss_ec']+$totals['employer_philhealth']+$totals['employer_pagibig']) ?></strong></td>
    </tr>
  </table>
</div>

<div style="margin-top:40px; font-size:10px; color:#888; border-top:1px solid #ddd; padding-top:6px;">
  Generated by TAASCOR HRIS — <?= date('F d, Y h:i A') ?> &nbsp;|&nbsp;
  User: <?= htmlspecialchars($_SESSION['taascor_employee_full_name'] ?? 'N/A') ?>
</div>
</body>
</html>
