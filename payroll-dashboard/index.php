<?php
require_once('../includes/auth_guard.php');
auth_require_role([1, 3]);

$employeeName = htmlspecialchars((string)($_SESSION['taascor_employee_full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$accessDescription = htmlspecialchars((string)($_SESSION['taascor_access_description'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Payroll Operations Dashboard</title>
  <meta name="description" content="Source-backed payroll run status, financial totals, blockers, and approvals." />
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="css/dashboard.css?v=20260727a" />
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require('../includes/nav-bar.php'); ?>

      <div class="home">
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
          id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)" aria-label="Open navigation">
              <i class="bx bx-menu bx-md" aria-hidden="true"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">Payroll</li>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                  </ol>
                </nav>
              </div>
            </div>

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown"
                  aria-label="Open profile menu">
                  <div class="avatar avatar-online">
                    <img src="../assets/img/avatars/user-icon.png" alt="" class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <div class="dropdown-item-text">
                      <h6 class="mb-0"><?php echo $employeeName; ?></h6>
                      <small class="text-muted"><?php echo $accessDescription; ?></small>
                    </div>
                  </li>
                  <li><div class="dropdown-divider my-1"></div></li>
                  <li>
                    <a class="dropdown-item" href="../login/logout.php">
                      <i class="bx bx-power-off bx-md me-3" aria-hidden="true"></i><span>Log Out</span>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>

        <div class="content-wrapper">
          <main class="container-xxl flex-grow-1 container-p-y payroll-dashboard" id="payrollDashboardMain">
            <section class="payroll-hero" aria-labelledby="payrollDashboardTitle">
              <div>
                <span class="payroll-eyebrow">Payroll control center</span>
                <h1 id="payrollDashboardTitle">Payroll Operations Dashboard</h1>
                <p>
                  Review one exact client and pay date. Every amount and status below comes from the selected
                  payroll results, DTR basis, governed run, release checks, or posting lock.
                </p>
              </div>
              <div class="payroll-hero__notice" aria-label="Data policy">
                <i class="bx bx-shield-quarter" aria-hidden="true"></i>
                <span>No estimated accuracy, compliance, or performance scores are shown.</span>
              </div>
            </section>

            <section class="card payroll-filter-card" aria-labelledby="payrollScopeHeading">
              <div class="card-body">
                <div class="payroll-section-heading">
                  <div>
                    <span class="payroll-step">Scope</span>
                    <h2 id="payrollScopeHeading">Choose the payroll cycle to review</h2>
                  </div>
                  <button type="button" class="btn btn-outline-primary btn-sm" id="refreshDashboardBtn" disabled>
                    <i class="bx bx-refresh" aria-hidden="true"></i>
                    Refresh
                  </button>
                </div>
                <form class="row g-3 align-items-end" id="payrollDashboardFilters">
                  <div class="col-12 col-lg-6">
                    <label class="form-label" for="payrollClientFilter">Client</label>
                    <select class="form-select" id="payrollClientFilter" required disabled>
                      <option value="">Loading clients…</option>
                    </select>
                    <small class="form-text">Client access follows your authenticated payroll role.</small>
                  </div>
                  <div class="col-12 col-lg-6">
                    <label class="form-label" for="payrollDateFilter">Pay date</label>
                    <select class="form-select" id="payrollDateFilter" required disabled>
                      <option value="">Select a client first</option>
                    </select>
                    <small class="form-text">Dates come from DTR, payroll results, smart runs, or posting locks.</small>
                  </div>
                </form>
              </div>
            </section>

            <div class="payroll-page-state payroll-page-state--loading" id="payrollDashboardState"
              role="status" aria-live="polite">
              <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
              <div>
                <strong>Loading payroll access</strong>
                <span>Checking the clients available to your role.</span>
              </div>
            </div>

            <div id="payrollDashboardContent" hidden>
              <section class="payroll-scope-banner" aria-labelledby="selectedScopeTitle">
                <div>
                  <span class="payroll-eyebrow" id="selectedScopeMode">Payroll scope</span>
                  <h2 id="selectedScopeTitle">—</h2>
                  <p id="selectedScopePeriod">—</p>
                </div>
                <div class="payroll-scope-banner__status">
                  <span class="payroll-status-pill" id="readinessPill">—</span>
                  <span id="freshnessText">—</span>
                </div>
              </section>

              <section aria-labelledby="financialOverviewHeading">
                <div class="payroll-section-heading">
                  <div>
                    <span class="payroll-step">Financial results</span>
                    <h2 id="financialOverviewHeading">Current scope totals</h2>
                  </div>
                  <span class="payroll-source-label" id="financialSourceLabel">Source: —</span>
                </div>
                <div class="row g-3 payroll-metric-grid">
                  <div class="col-12 col-sm-6 col-xl-3">
                    <article class="card payroll-metric-card h-100">
                      <div class="card-body">
                        <span class="payroll-metric-card__label">Employees</span>
                        <strong id="metricEmployeeCount">—</strong>
                        <small id="metricEmployeeContext">Selected payroll population</small>
                      </div>
                    </article>
                  </div>
                  <div class="col-12 col-sm-6 col-xl-3">
                    <article class="card payroll-metric-card h-100">
                      <div class="card-body">
                        <span class="payroll-metric-card__label">Gross income</span>
                        <strong id="metricGrossIncome">—</strong>
                        <small>From payroll_summary</small>
                      </div>
                    </article>
                  </div>
                  <div class="col-12 col-sm-6 col-xl-3">
                    <article class="card payroll-metric-card payroll-metric-card--accent h-100">
                      <div class="card-body">
                        <span class="payroll-metric-card__label">Net pay</span>
                        <strong id="metricNetPay">—</strong>
                        <small>From payroll_summary</small>
                      </div>
                    </article>
                  </div>
                  <div class="col-12 col-sm-6 col-xl-3">
                    <article class="card payroll-metric-card h-100">
                      <div class="card-body">
                        <span class="payroll-metric-card__label">Employee deductions</span>
                        <strong id="metricEmployeeDeductions">—</strong>
                        <small>Statutory, tax, tardy, loan, and other deductions</small>
                      </div>
                    </article>
                  </div>
                </div>
              </section>

              <div class="row g-3 mt-1">
                <div class="col-12 col-xl-7">
                  <section class="card h-100" aria-labelledby="runControlHeading">
                    <div class="card-body">
                      <div class="payroll-section-heading">
                        <div>
                          <span class="payroll-step">Control status</span>
                          <h2 id="runControlHeading">Run, approval, and release</h2>
                        </div>
                        <span class="payroll-status-pill" id="postingPill">—</span>
                      </div>

                      <dl class="payroll-control-list">
                        <div>
                          <dt>Data mode</dt>
                          <dd id="controlDataMode">—</dd>
                        </div>
                        <div>
                          <dt>Run state</dt>
                          <dd id="controlRunState">—</dd>
                        </div>
                        <div>
                          <dt>Approval</dt>
                          <dd id="controlApprovalState">—</dd>
                        </div>
                        <div>
                          <dt>Release state</dt>
                          <dd id="controlReleaseState">—</dd>
                        </div>
                      </dl>

                      <div class="payroll-stage-grid" id="smartRunStages" aria-label="Governed run stages"></div>

                      <div class="payroll-approval-panel" id="approvalPanel">
                        <div>
                          <span>Maker</span>
                          <strong id="approvalMaker">—</strong>
                          <small id="approvalMakerAt">—</small>
                        </div>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                        <div>
                          <span>Checker</span>
                          <strong id="approvalChecker">—</strong>
                          <small id="approvalCheckerAt">—</small>
                        </div>
                      </div>
                      <p class="payroll-inline-note" id="approvalMessage">—</p>
                    </div>
                  </section>
                </div>

                <div class="col-12 col-xl-5">
                  <section class="card h-100" aria-labelledby="readinessHeading">
                    <div class="card-body">
                      <div class="payroll-section-heading">
                        <div>
                          <span class="payroll-step">Exception gate</span>
                          <h2 id="readinessHeading">Release readiness</h2>
                        </div>
                        <strong class="payroll-blocker-total" id="blockerTotal">—</strong>
                      </div>
                      <div id="blockerList" class="payroll-blocker-list"></div>
                      <div class="payroll-clear-state" id="blockerClearState" hidden>
                        <i class="bx bx-check-circle" aria-hidden="true"></i>
                        <div>
                          <strong>No open blocker signals</strong>
                          <span>All dashboard-visible controls for this run are clear.</span>
                        </div>
                      </div>
                    </div>
                  </section>
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-12 col-xl-6">
                  <section class="card h-100" aria-labelledby="inputCoverageHeading">
                    <div class="card-body">
                      <div class="payroll-section-heading">
                        <div>
                          <span class="payroll-step">Input coverage</span>
                          <h2 id="inputCoverageHeading">Population and source lineage</h2>
                        </div>
                      </div>
                      <dl class="payroll-lineage-list">
                        <div><dt>DTR rows</dt><dd id="lineageDtrRows">—</dd></div>
                        <div><dt>DTR employees</dt><dd id="lineageDtrEmployees">—</dd></div>
                        <div><dt>Payroll result rows</dt><dd id="lineagePayrollRows">—</dd></div>
                        <div><dt>Population exceptions</dt><dd id="lineagePopulationExceptions">—</dd></div>
                        <div><dt>Run UID</dt><dd id="lineageRunUid">—</dd></div>
                        <div><dt>Source file</dt><dd id="lineageSourceFile">—</dd></div>
                        <div><dt>Ruleset</dt><dd id="lineageRuleset">—</dd></div>
                        <div><dt>Canonical rows</dt><dd id="lineageCanonicalRows">—</dd></div>
                      </dl>
                    </div>
                  </section>
                </div>

                <div class="col-12 col-xl-6">
                  <section class="card h-100" aria-labelledby="contractHeading">
                    <div class="card-body">
                      <div class="payroll-section-heading">
                        <div>
                          <span class="payroll-step">Evidence contracts</span>
                          <h2 id="contractHeading">What the dashboard could verify</h2>
                        </div>
                      </div>
                      <div class="payroll-contract-list" id="contractList"></div>
                      <p class="payroll-inline-note mb-0" id="freshnessDetail">—</p>
                    </div>
                  </section>
                </div>
              </div>

              <section class="card mt-3" aria-labelledby="deductionHeading">
                <div class="card-body">
                  <div class="payroll-section-heading">
                    <div>
                      <span class="payroll-step">Financial composition</span>
                      <h2 id="deductionHeading">Employee deduction breakdown</h2>
                    </div>
                    <span class="payroll-source-label">Amounts in PHP</span>
                  </div>
                  <div class="payroll-breakdown" id="deductionBreakdown">
                    <div>
                      <span>Statutory and tax</span>
                      <strong id="deductionStatutory">—</strong>
                    </div>
                    <div>
                      <span>Employee loans</span>
                      <strong id="deductionLoans">—</strong>
                    </div>
                    <div>
                      <span>Other deductions</span>
                      <strong id="deductionOther">—</strong>
                    </div>
                    <div>
                      <span>Additional pay</span>
                      <strong id="additionalPay">—</strong>
                    </div>
                    <div>
                      <span>Overtime pay</span>
                      <strong id="overtimePay">—</strong>
                    </div>
                    <div>
                      <span>Employer contributions</span>
                      <strong id="employerContributions">—</strong>
                    </div>
                  </div>
                </div>
              </section>

              <section class="payroll-next-actions mt-3" aria-labelledby="nextActionsHeading">
                <div>
                  <span class="payroll-step">Act on the selected scope</span>
                  <h2 id="nextActionsHeading">Next actions</h2>
                  <p>Resolve upstream issues before opening release actions.</p>
                </div>
                <div class="payroll-next-actions__links">
                  <a class="btn btn-primary" href="../dtr-format-engine/">
                    Open Payroll Workflow
                  </a>
                  <a class="btn btn-outline-primary" href="../payroll-data-quality/">
                    Review Data Quality
                  </a>
                  <a class="btn btn-outline-secondary" href="../payslip/">
                    Open Payslip
                  </a>
                </div>
              </section>
            </div>
          </main>

          <div class="content-backdrop fade"></div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
      </div>
    </div>
  </div>

  <?php require('../includes/footer.php'); ?>
  <?php require('../includes/custom-footer.php'); ?>
  <script src="js/index-01.js?v=20260727b"></script>
</body>
</html>
