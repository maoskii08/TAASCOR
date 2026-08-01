<?php
require_once('../includes/auth_guard.php');
auth_require_role([1, 2, 3]);

$faqs = [
    ['Getting Started', 'Where should I start when preparing payroll?', 'Start with Employee Management and Pay Day setup. Confirm the client, employee status, hire or separation dates, payroll identifiers, cutoff, and payday. Then load the DTR and resolve every identity and population blocker before adding adjustments or generating payroll.', '../employee-management/'],
    ['Getting Started', 'What information should I confirm before uploading anything?', 'Confirm the correct client, payroll period start and end, payday, source filename, employee population, and approving owner. Using the wrong client or period can place otherwise correct records into the wrong payroll cycle.', '../payday/'],
    ['Getting Started', 'What is the recommended end-to-end payroll sequence?', 'Prepare employee master data, load or stage the DTR, align employees, reconcile the DTR and expected payslip population, load additions and deductions, validate loans and rules, generate payroll, reconcile totals, generate payslips, obtain approval, then post and distribute.', '../dtr-format-engine/'],
    ['Getting Started', 'Can I skip directly to Generate Payroll?', 'No. Generate Payroll should be used only after the employee, DTR, identity, population, addition, deduction, loan, and payroll-rule inputs are complete. Skipping validation can create inaccurate results that look finished.', '../payslip/'],

    ['DTR and Timekeeping', 'Which page should I use for a new or corrected DTR?', 'Use the DTR Format Engine. It stages the source, aligns employees, and keeps canonical payroll unchanged until the governed checks pass. DTR Upload remains available for reviewing canonical values and explicitly guarded cleanup actions.', '../dtr-format-engine/'],
    ['DTR and Timekeeping', 'Why are legacy DTR Upload and manual Save blocked?', 'The installed legacy calculator starts and commits its own database transaction, so the application cannot guarantee rollback and audit if calculation fails. The safety block prevents raw DTR and derived payroll from drifting. Correct the source and stage it through the DTR Format Engine.', '../dtr-format-engine/'],
    ['DTR and Timekeeping', 'When should I use the DTR Format Engine?', 'Use the DTR Format Engine for every new or corrected payroll input during the transaction-safe cutover, especially inconsistent structures, vendor employee identifiers, staging, and employee alignment. The Format Engine keeps the source in a guarded review flow.', '../dtr-format-engine/'],
    ['DTR and Timekeeping', 'What does Stage mean?', 'Stage loads the source into a review batch. It does not write a final DTR or payroll run. Staged data can be analyzed, rejected, or corrected without silently changing canonical payroll.', '../dtr-format-engine/'],
    ['DTR and Timekeeping', 'Why is my DTR upload rejected?', 'Common causes include an incorrect file layout, missing employee identifiers, invalid dates or times, duplicate rows, unsupported status codes, or a client and payroll-period mismatch. Read the validation message and correct the source rather than repeatedly uploading the same file.', '../dtr-upload/'],

    ['Employee Alignment', 'What is a safe employee match?', 'A safe match is a collision-free resolver result that meets the configured evidence score and margin. It still requires an authorized owner to enter a reason and approve the cohort. The system does not silently create the alias.', '../dtr-format-engine/#smart-employee-resolution'],
    ['Employee Alignment', 'What is an owner-review match?', 'The resolver found a plausible employee, but the evidence did not meet the safe threshold. Review the employee master, source name, hire date, client, and status. Approve or reject only when supporting evidence is available.', '../dtr-format-engine/#employee-identity-review'],
    ['Employee Alignment', 'What is a hard-blocked employee?', 'A hard block has a contradiction such as no client-scoped employee, conflicting hire date, inactive status, or competing source identities. A hard block must be corrected or explicitly resolved; it cannot be auto-approved.', '../dtr-format-engine/#employee-identity-review'],
    ['Employee Alignment', 'What does employee collision mean?', 'Two source identities are proposing the same HRIS employee. The stronger deterministic claim may retain the employee, but the weaker source remains blocked. Confirm whether the weak source is a genuinely missing employee, an incorrect name, or a duplicate source record.', '../dtr-format-engine/#smart-employee-resolution'],
    ['Employee Alignment', 'What should I do when the employee is missing from HRIS?', 'Search active and terminated employees first. If the person is genuinely missing, use Create employee from the exception, verify official personal and employment evidence, and complete the employee master. Never create an employee only to remove a payroll blocker.', '../employee-management/'],
    ['Employee Alignment', 'Why is an employee blocked by status or dates?', 'The employee may be inactive for the payroll period, assigned to another client, hired after the period, or separated before it. HR must confirm and correct the effective employment record before Payroll continues.', '../terminated-employees/'],
    ['Employee Alignment', 'Where can HR review all remaining employee cases?', 'Open Employee Identity Resolution and use Export HR decision packet. The file includes each DTR identity, suggested HRIS employee, recommended action, and blank Owner decision and Owner reason fields.', '../dtr-format-engine/#employee-identity-review'],

    ['Population Reconciliation', 'What does payslip without DTR mean?', 'The expected payslip reference includes an employee who is absent from the uploaded DTR. Payroll must obtain a corrected DTR or an evidence-backed owner disposition. The system must not invent timekeeping rows.', '../dtr-format-engine/#payroll-population-review'],
    ['Population Reconciliation', 'Can I exclude a payslip-only employee?', 'Only when the owner provides evidence that the employee should not be in this payroll population. Record the exact reason and evidence. Paid days or earnings in the reference are a warning that exclusion may be incorrect.', '../dtr-format-engine/#payroll-population-review'],
    ['Population Reconciliation', 'Why does the population gate remain blocked?', 'Every open P0 population exception blocks the canonical payroll snapshot. Resolve all payslip-only or population-difference records with supported owner evidence, then refresh the gate.', '../dtr-format-engine/#payroll-population-review'],

    ['Payroll Inputs', 'Where do I add a one-time earning?', 'Use Other Additional. Confirm the exact client, employee, cutoff, period, and payday; choose the authorized earning category; and enter the positive amount, business reason, and evidence reference. The adjustment, payroll recalculation, and audit evidence commit together.', '../other-additional/'],
    ['Payroll Inputs', 'Where do I add a one-time deduction?', 'Use Other Deduction. Confirm the exact payroll scope and enter the authorized category, positive amount, business reason, and evidence reference. Do not use a generic deduction to hide an attendance, statutory, or loan difference.', '../other-deduction/'],
    ['Payroll Inputs', 'What happens if one row in an adjustment workbook fails?', 'The complete bounded upload rolls back, including adjustment rows, payroll recalculation, and audit evidence. Correct the source and upload the complete file once again. No earlier browser batch is retained and no rejected row is silently skipped.', '../payroll-data-quality/'],
    ['Payroll Inputs', 'How large can an adjustment workbook be?', 'Each Other Additional or Other Deduction upload is limited to 1,000 normalized rows and 1 MiB so it can commit as one all-or-nothing request. Larger files must be split into separately approved atomic uploads.', '../other-additional/'],
    ['Payroll Inputs', 'How are employee loans included?', 'Create and maintain approved loan terms under Loans. Payroll should reconcile the scheduled deduction and remaining balance through Loans Report before release.', '../loans/'],
    ['Payroll Inputs', 'What should I do if net pay does not match?', 'Trace gross earnings, printed deduction subtotal, attendance deductions, loans, statutory deductions, additions, and effective deductions separately. Correct the owning source component rather than manually forcing net pay.', '../payroll-summary/'],

    ['Generation and Release', 'Why is Create canonical snapshot disabled?', 'At least one required gate is not ready. Common blockers are unresolved identity exceptions, population differences, missing approved rules, invalid staged rows, or an incomplete reconciliation. The button enables only when all required checks pass.', '../dtr-format-engine/'],
    ['Generation and Release', 'What is the difference between Generate Payroll and Generate Payslip?', 'Generate Payroll calculates employee payroll results for review. Generate Payslip creates the employee-facing artifact from approved results. Neither action by itself means payroll is posted or safe to distribute.', '../payslip/'],
    ['Generation and Release', 'When can I post payroll?', 'Post only after the complete population, employee identities, payroll rules, totals, sample payslips, release gate, and owner approval are confirmed. Posting should create an auditable final state and should not be used as a test.', '../payslip/'],
    ['Generation and Release', 'Can I change payroll after posting?', 'Treat changes after posting as a controlled correction, reversal, or adjustment with approval. Do not silently overwrite a posted cycle because that breaks payroll and audit traceability.', '../audit-log/'],
    ['Generation and Release', 'What should be checked before releasing payslips?', 'Check employee count, gross and net totals, additions, deductions, attendance deductions, statutory and loan components, sample employee payslips, artifact storage, and final owner approval. Confirm no P0 release blocker remains.', '../payroll-data-quality/'],

    ['Notifications and Troubleshooting', 'Where do HR and Payroll see blockers?', 'Open the notification bell for in-app events. Identity notifications link to Employee Identity Resolution, while payslip-population notifications link to DTR and Payslip Population Review. Counts update when the batch is revalidated.', '../dtr-format-engine/'],
    ['Notifications and Troubleshooting', 'Why did Employee Upload say finalization is blocked?', 'The workbook passed staging validation, but the installed legacy employee transfer controls its own commit and is not safe for application rollback. The staged import is preserved. Keep the import reference, use individual Employee Management actions for urgent verified changes, and wait for the transaction-neutral transfer v2 migration.', '../employee-management/'],
    ['Notifications and Troubleshooting', 'Does an in-app notification mean an email was sent?', 'Not necessarily. In-app delivery and email delivery are separate. The email worker must be configured and run successfully; a pending email should not be reported as sent.', '../dtr-format-engine/'],
    ['Notifications and Troubleshooting', 'Why do I see Unauthorized or get returned to Login?', 'Your session may have expired, the page may not be allowed for your role, or the request may have an invalid security token. Log in again, use the navigation menu, and ask Admin to verify role access if the problem continues.', '../login/'],
    ['Notifications and Troubleshooting', 'What should I include when reporting a payroll problem?', 'Provide the page, client, batch or payday, employee ID when appropriate, exact error message, expected result, actual result, and supporting source document. Do not send passwords or confidential payroll files through an unsecured channel.', '../audit-log/'],
];

$categories = array_values(array_unique(array_column($faqs, 0)));
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payroll Officer Help and FAQ</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="css/index.css?v=20260727a" />
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require('../includes/nav-bar.php'); ?>
      <div class="home">
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)"><i class="bx bx-menu bx-md"></i></a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item">Payroll</li><li class="breadcrumb-item active">Help & FAQ</li></ol></nav>
            </div>
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online"><img src="../assets/img/avatars/user-icon.png" alt="" class="w-px-40 h-auto rounded-circle" /></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li class="px-3 py-2">
                    <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['taascor_employee_full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h6>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['taascor_access_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  </li>
                  <li><div class="dropdown-divider my-1"></div></li>
                  <li><a class="dropdown-item" href="../login/logout.php"><i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span></a></li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <section class="payroll-help-hero mb-4">
              <div class="row align-items-center g-4">
                <div class="col-lg-7">
                  <span class="payroll-help-eyebrow">BEGINNER-FRIENDLY PAYROLL GUIDE</span>
                  <h1>Payroll Officer Help & FAQ</h1>
                  <p>Follow the complete payroll sequence, understand blockers, and find the right page for every action.</p>
                  <div class="payroll-help-search">
                    <i class="bx bx-search" aria-hidden="true"></i>
                    <input type="search" id="payrollFaqSearch" class="form-control" placeholder="Search DTR, employee match, deduction, payslip, posting..." aria-label="Search payroll questions">
                  </div>
                </div>
                <div class="col-lg-5">
                  <div class="payroll-help-start-card">
                    <span>Start here</span>
                    <strong>Do not generate payroll until employee, DTR, population, and rule gates are ready.</strong>
                    <a href="../dtr-format-engine/" class="btn btn-light btn-sm">Open Payroll Workflow <i class="bx bx-right-arrow-alt ms-1"></i></a>
                  </div>
                </div>
              </div>
            </section>

            <section class="card mb-4">
              <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div><h5 class="mb-1">End-to-end payroll process</h5><small class="text-muted">Use this sequence for every payroll cycle.</small></div>
                <span class="payroll-faq-process-hint"><i class="bx bx-fullscreen"></i> Click diagram to maximize</span>
              </div>
              <div class="card-body">
                <button type="button" class="payroll-faq-process-trigger" id="payrollFaqProcessTrigger" aria-label="Maximize the end-to-end payroll process flow">
                  <img id="payrollFaqProcessImage" class="payroll-faq-process-image" alt="End-to-end payroll process flow">
                  <span class="payroll-faq-process-overlay"><i class="bx bx-fullscreen"></i> Maximize</span>
                </button>
              </div>
            </section>

            <div class="payroll-faq-category-bar mb-4" role="group" aria-label="Filter FAQ category">
              <button type="button" class="btn btn-primary payroll-faq-category active" data-category="all">All questions</button>
              <?php foreach ($categories as $category): ?>
                <button type="button" class="btn btn-outline-secondary payroll-faq-category" data-category="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
                </button>
              <?php endforeach; ?>
            </div>
            <p class="text-muted small mb-3" id="payrollFaqResultCount" aria-live="polite"></p>

            <div class="row g-4">
              <div class="col-xl-8">
                <div id="payrollFaqList">
                  <?php foreach ($faqs as $index => $faq): ?>
                    <details class="payroll-faq-item" data-category="<?php echo htmlspecialchars($faq[0], ENT_QUOTES, 'UTF-8'); ?>"
                      data-search="<?php echo htmlspecialchars(strtolower($faq[0] . ' ' . $faq[1] . ' ' . $faq[2]), ENT_QUOTES, 'UTF-8'); ?>">
                      <summary>
                        <span class="payroll-faq-number"><?php echo $index + 1; ?></span>
                        <span class="payroll-faq-question"><small><?php echo htmlspecialchars($faq[0], ENT_QUOTES, 'UTF-8'); ?></small><?php echo htmlspecialchars($faq[1], ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="bx bx-chevron-down" aria-hidden="true"></i>
                      </summary>
                      <div class="payroll-faq-answer">
                        <p><?php echo htmlspecialchars($faq[2], ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo htmlspecialchars($faq[3], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">Open related page <i class="bx bx-right-arrow-alt ms-1"></i></a>
                      </div>
                    </details>
                  <?php endforeach; ?>
                </div>
                <div class="card text-center py-5" id="payrollFaqEmpty" hidden>
                  <div class="card-body"><i class="bx bx-search-alt payroll-faq-empty-icon"></i><h5>No matching question</h5><p class="text-muted mb-0">Try a simpler word such as DTR, employee, deduction, or payslip.</p></div>
                </div>
              </div>
              <aside class="col-xl-4">
                <div class="card payroll-help-side-card mb-4">
                  <div class="card-body">
                    <div class="payroll-help-side-icon"><i class="bx bx-shield-quarter"></i></div>
                    <h5>Payroll release rule</h5>
                    <p>No unresolved P0 identity, population, calculation, reconciliation, or artifact blocker may remain before payslip release.</p>
                    <a href="../payroll-data-quality/" class="btn btn-sm btn-primary">Review data quality</a>
                  </div>
                </div>
                <div class="card payroll-help-side-card">
                  <div class="card-body">
                    <div class="payroll-help-side-icon secondary"><i class="bx bx-file"></i></div>
                    <h5>Need HR decisions?</h5>
                    <p>Export the HR decision packet from Employee Identity Resolution and ask the owner to complete the decision and reason columns.</p>
                    <a href="../dtr-format-engine/#employee-identity-review" class="btn btn-sm btn-outline-primary">Open identity review</a>
                  </div>
                </div>
              </aside>
            </div>
          </div>
          <div class="content-backdrop fade"></div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
  </div>
  <?php require('../includes/footer.php'); ?>
  <?php require('../includes/custom-footer.php'); ?>
  <script src="js/index.js?v=20260727b"></script>
</body>
</html>
