<?php
require_once('../includes/auth_guard.php');
auth_require_role([1,3]);
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Payroll Summary</title>
  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />

  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="css/payroll-summary.css?v=20260727b" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <!-- Select2 -->
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2.min.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2-bootstrap-5-theme.min.css" />

  <!-- Datatable -->
  <link rel="stylesheet" href="https://cdn.datatables.net/2.1.6/css/dataTables.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/css/datatables.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-responsive-bs5/responsive.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-buttons-bs5/buttons.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-rowgroup-bs5/rowgroup.bootstrap5.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <!-- Layout wrapper -->
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require('../includes/nav-bar.php') ?>    
      <!--home-->
      <div class="home">
        <nav
          class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
          id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
              <i class="bx bx-menu bx-md"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <!-- breadcrumb -->
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item active" aria-current="page">Payroll Summary</li>
                  </ol>
                </nav>
              </div>
            </div>
            <!-- /breadcrumb -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <h6 class="mb-0"><?php echo $_SESSION['taascor_employee_full_name'] ?></h6>
                          <small class="text-muted"><?php echo $_SESSION['taascor_access_description'] ?></small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider my-1"></div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="<?php echo htmlspecialchars($appBaseUrl . '/login/logout.php', ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span>
                    </a>
                  </li>
                </ul>
              </li>
              <!--/ User -->
            </ul>
          </div>
        </nav>

        <div class="content-wrapper">
          <!-- Content -->
          
          <div class="container-xxl flex-grow-1 container-p-y" id="payrollSummaryApp">
            <section class="payroll-summary-intro" aria-labelledby="payrollSummaryTitle">
              <div>
                <h1 id="payrollSummaryTitle">Payroll Summary</h1>
                <p>Review payroll cost, workforce, deductions, and employer contributions within one explicit payroll scope.</p>
              </div>
              <div class="payroll-summary-intro-badges" aria-label="Report governance">
                <span><i class="bx bx-shield-quarter" aria-hidden="true"></i> Canonical HRIS payroll</span>
                <span><i class="bx bx-lock-alt" aria-hidden="true"></i> Read-only review</span>
              </div>
            </section>

            <div class="payroll-source-notice" id="payrollSourceNotice" role="status" aria-live="polite">
              <div class="payroll-source-notice-icon" aria-hidden="true"><i class="bx bx-data"></i></div>
              <div>
                <strong>Checking payroll source coverage…</strong>
                <p>Confirming which payroll records are included in this report.</p>
              </div>
            </div>

            <section class="payroll-filter-panel" aria-labelledby="payrollFiltersTitle">
              <div class="payroll-section-heading">
                <div>
                  <h2 id="payrollFiltersTitle">Review scope</h2>
                  <p>Use a consistent client, cutoff, and pay-date scope before comparing totals.</p>
                </div>
                <fieldset class="payroll-view-switch">
                  <legend class="visually-hidden">Review mode</legend>
                  <label>
                    <input type="radio" name="review_mode" value="executive">
                    <span><i class="bx bx-line-chart" aria-hidden="true"></i> Executive overview</span>
                  </label>
                  <label>
                    <input type="radio" name="review_mode" value="payroll">
                    <span><i class="bx bx-detail" aria-hidden="true"></i> Payroll review</span>
                  </label>
                </fieldset>
              </div>

              <form id="payrollSummaryFilters">
                <div class="payroll-filter-grid">
                  <div class="payroll-field">
                    <label for="payrollQuickPeriod">Period</label>
                    <select class="form-select" id="payrollQuickPeriod">
                      <option value="latest">Latest available payday</option>
                      <option value="30">Last 30 days of available data</option>
                      <option value="90">Last 90 days of available data</option>
                      <option value="ytd">Year to date</option>
                      <option value="all">All canonical payroll</option>
                      <option value="custom">Custom dates</option>
                    </select>
                  </div>
                  <div class="payroll-field">
                    <label for="payrollClient">Client</label>
                    <select class="form-select" id="payrollClient">
                      <option value="">All clients</option>
                    </select>
                  </div>
                  <div class="payroll-field">
                    <label for="payrollCutoff">Cutoff</label>
                    <select class="form-select" id="payrollCutoff">
                      <option value="">All cutoffs</option>
                    </select>
                  </div>
                  <div class="payroll-field">
                    <label for="payrollDateFrom">From</label>
                    <input class="form-control" type="date" id="payrollDateFrom" required>
                  </div>
                  <div class="payroll-field">
                    <label for="payrollDateTo">To</label>
                    <input class="form-control" type="date" id="payrollDateTo" required>
                  </div>
                  <div class="payroll-filter-actions">
                    <button class="btn btn-primary" type="submit" id="applyPayrollFilters">
                      <i class="bx bx-filter-alt" aria-hidden="true"></i> Apply filters
                    </button>
                    <button class="btn btn-outline-secondary" type="button" id="resetPayrollFilters">
                      Reset
                    </button>
                  </div>
                </div>
              </form>
            </section>

            <section class="payroll-kpi-band" id="payrollKpiBand" aria-label="Payroll summary metrics">
              <div class="payroll-kpi-loading">Apply a payroll scope to load summary metrics.</div>
            </section>

            <section class="payroll-review-layout">
              <div class="payroll-chart-panel" id="payrollChartPanel">
                <div class="payroll-section-heading">
                  <div>
                    <h2>Gross and net pay by client</h2>
                    <p id="payrollChartSubtitle">Top clients in the selected scope.</p>
                  </div>
                </div>
                <div id="payrollClientChart" aria-label="Gross and net payroll comparison by client"></div>
              </div>
              <aside class="payroll-review-context" aria-labelledby="payrollReviewContextTitle">
                <h2 id="payrollReviewContextTitle">Review context</h2>
                <div id="payrollReviewContext">
                  <p>Apply a scope to see the period, comparison basis, and review reminders.</p>
                </div>
              </aside>
            </section>

            <section class="payroll-table-panel" aria-labelledby="payrollTableTitle">
              <div class="payroll-section-heading">
                <div>
                  <h2 id="payrollTableTitle">Executive payroll overview</h2>
                  <p id="payrollTableSubtitle">Client-level payroll cost and variance for the selected scope.</p>
                </div>
                <span class="payroll-result-count" id="payrollResultCount" aria-live="polite"></span>
              </div>
              <div class="card-datatable" id="table_container">
                <div class="payroll-empty-state">
                  <i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>
                  <strong>Preparing Payroll Summary</strong>
                  <span>Loading report filters and source coverage.</span>
                </div>
              </div>
            </section>
          </div>
        </div>

        <!-- / Content -->

        <div class="content-backdrop fade"></div>
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    

    <?Php require("../includes/footer.php") ;?>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="js/index-01.js?v=20260727b"></script>
    <?Php require("../includes/custom-footer.php") ;?>
</body>

</html>
