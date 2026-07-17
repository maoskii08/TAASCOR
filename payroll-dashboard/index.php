<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Payroll Dashboard</title>
  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />

  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />

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
<style>
  .home {
    overflow-x: hidden;
  }

  .stats {
    font-size: 0.88rem;
  }

  .carousel-control-prev,
  .carousel-control-next {
    width: 4%;
    top: -86%;
  }

  .client-name {
    font-weight: 700;
    padding-left: 30px;
  }

  #payrollrate,
  #taxrate {
    margin-top: -10px;
  }

  #payroll-value,
  #tax-value,
  #perf-value,
  #abs-value {
    bottom: 60px;
    position: absolute;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 1.3em;
    font-weight: 600;
    color: #303030;
    font-family: "Public Sans", sans-serif;
  }

  #payroll-value-sub,
  #tax-value-sub,
  #perf-value-sub,
  #abs-value-sub {
    bottom: 40px;
    position: absolute;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 0.70em;
    font-weight: 500;
    color: #303030;
    font-family: "Public Sans", sans-serif;
    text-transform: uppercase;
  }

  #payroll-value::after,
  #tax-value::after,
  #perf-value::after,
  #abs-value::after {
    content: "%";
  }

  .netpay {
    border-right: 1px solid #cccccc;
  }

  @media screen and (max-width: 500px) {
    .client-name {
      font-size: 1.1rem;
      font-weight: 600;
      padding-left: 20px;
    }

    .netpay {
      border-right: 0;
      border-bottom: 1px solid #cccccc;
      padding-bottom: 2rem;
    }

    .viewpay-register{
      width: 100%;
    }
  }
</style>

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
                    <li class="breadcrumb-item active" aria-current="page">Payroll Dashboard</li>
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
                    <a class="dropdown-item" href="/<?php echo $pathParts[6];?>/login/logout.php">
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

          <div class="container-xxl flex-grow-1 container-p-y">
            <!--view register btn-->
            <div style="display:none" id="monthYearDiv" class="row g-2 mb-3">              
              <div class="col-sm-12 col-lg-8">
                <!-- <button class="btn btn-primary viewpay-register btn-md"><i class='bx bx-file me-1'></i> View Pay Register</button> -->
              </div>
              <div class="col-6 col-sm-6 col-lg-2">
                <select id="payrollMonth" class="form-select" data-placeholder="Month">
                  <option value=""></option>
                  <option value="1">January</option>
                  <option value="2">February</option>
                  <option value="3">March</option>
                  <option value="4">April</option>
                  <option value="5">May</option>
                  <option value="6">June</option>
                  <option value="7">July</option>
                  <option value="8">August</option>
                  <option value="9">September</option>
                  <option value="10">October</option>
                  <option value="11">November</option>
                  <option value="12">December</option>
                </select>
              </div>
              <div class="col-6 col-sm-6 col-lg-2">
                <select id="payrollYear" class="form-select" data-placeholder="Year">
                  <option value=""></option>
                  <option value="2025">2025</option>
                  <option value="2026">2026</option>
                </select>
              </div>
            </div>

            <div class="row">
              <!--net pay-->
              <div class="col-lg-4 col-sm-12 mb-3">
                <div class="card h-100 mb-3">
                  <div class="card-header d-flex align-items-center justify-content-between mb-n3">
                    <h5 class="card-title mb-0">Net Pay</h5>
                  </div>
                  <div class="card-body text-center">
                    <div class="row">
                      <div class="col-sm-12 border-bottom pb-5">
                        <div class="d-flex justify-content-center mb-3">
                          <div class="avatar avatar-xl flex-shrink-0">
                            <span class="avatar-initial avatar-shadow-success  rounded-circle">
                              <i id="trendingArrow" class="icon-base bx bx-trending-up icon-40px"></i></span>
                          </div>
                        </div>
                        <h4 id="currentNetPay" class="card-title mb-1">0</h4>
                        <p class="mb-0">Current Month</p>
                        <!-- <span id="netPayRate" class="text-success">0.0% <i class="icon-base bx bx-chevron-up icon-lg"></i></span> -->
                      </div>
                      <div class="col-sm-12 pb-1">
                        <h4 id="prevNetPay" class="text-muted mt-5 mb-0">0</h4>
                        <p class="text-muted mb-n1">Previous Month</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--upcoming payroll carousel-->
              <div class="col-lg-8 col-sm-12 mb-3">
                <div class="card h-100">
                  <div class="card-header">
                    <h5 class="card-title mb-0">Upcoming Payroll</h5>
                  </div>
                  <div class="card-body">
                    <!-- <span class="h5 client-name">Delta Milling Industries Inc.</span> -->
                     <select class="form-select" id="client" data-placeholder="Client">
                    </select>
                    <span id="dueTxt" style="display:none" class="badge rounded-pill bg-label-secondary ms-1">Due in 5 days</span>
                    <div class="row mt-3">
                      <div class="col-lg-6 col-sm-12 mb-3">
                        <div class="alert alert-dark mb-0 pb-0" role="alert">
                          <p><i class='bx bx-calendar mt-n1'></i> Payroll Period:</p>
                          <p id="payrollPeriodTxt" class="h5 alert-link mt-n1">-</p>
                        </div>
                      </div>
                      <div class="col-lg-6 col-sm-12">
                        <div class="alert alert-dark mb-0 pb-0" role="alert">
                          <p><i class='bx bx-calendar-event mt-n1'></i> Pay Date:</p>
                          <p id="payrollDateTxt" class="h5 alert-link mt-n1">-</p>
                        </div>
                      </div>
                    </div>

                    <div class="row mt-2">
                      <div class="col-sm-12">
                        <div class="d-grid gap-2">
                          <a href="/<?php echo $pathParts[6];?>/payslip/" target="_blank" class="btn btn-primary mb-1" style="z-index: 10">View
                            Payroll</a>
                          <a href="/<?php echo $pathParts[6];?>/dtr-upload/" target="_blank" class="btn btn-outline-secondary"
                            style="z-index: 10">Create New Payroll Cycle</a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <!--payroll cost breakdown-->
              <div class="col-lg-4 col-sm-12">
                <div class="card mb-3">
                  <div class="card-header">
                    <h5 class="card-title mb-1">Payroll Cost Breakdown</h5>
                  </div>
                  <div class="card-body pb-0">
                    <div id="payrollCostBreakdown"></div>
                  </div>
                </div>

                <!--new hires-->
                <div class="card mb-3">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">New Hires</h5>
                  </div>
                  <div class="card-body pb-6">
                    <div class="row">
                      <div class="col-lg-6 col-6 border-end text-center">
                        <h4 class="card-title mb-0">18</h4>
                        <p class="mb-0">Current Month</p>
                      </div>
                      <div class="col-lg-6 col-6 text-center">
                        <h4 class="text-muted mb-0">23</h4>
                        <p class="text-muted mb-0">Previous Month</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-8 col-sm-12">
                <div class="row">
                  <!--payroll accuracy rate-->
                  <div class="col-lg-6 col-sm-12">
                    <div class="card mb-3">
                      <div class="card-header">
                        <h5 class="card-title mb-1">Payroll Accuracy Rate</h5>
                      </div>
                      <div class="card-body d-flex justify-content-center">
                        <canvas id="payrollrate" width="400" height="135"></canvas>
                        <div id="payroll-value"></div>
                        <small id="payroll-value-sub">Accuracy Rate</small>
                      </div>
                    </div>
                  </div>

                  <!--tax and contribution compliance rate-->
                  <div class="col-lg-6 col-sm-12">
                    <div class="card mb-3">
                      <div class="card-header">
                        <h5 class="card-title mb-1">Tax and Contribution Compliance Rate</h5>
                      </div>
                      <div class="card-body d-flex justify-content-center">
                        <canvas id="taxrate" width="400" height="135"></canvas>
                        <div id="tax-value"></div>
                        <small id="tax-value-sub">Compliance Rate</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <!--employee performance rate-->
                  <div class="col-lg-6 col-sm-12">
                    <div class="card mb-3">
                      <div class="card-header">
                        <h5 class="card-title mb-1">Employee Performance Rate</h5>
                      </div>
                      <div class="card-body d-flex justify-content-center">
                        <canvas id="perfrate" width="400" height="135"></canvas>
                        <div id="perf-value"></div>
                        <small id="perf-value-sub">Performance Rate</small>
                      </div>
                    </div>
                  </div>

                  <!--absenteeism rate-->
                  <div class="col-lg-6 col-sm-12">
                    <div class="card mb-3">
                      <div class="card-header">
                        <h5 class="card-title mb-1">Absenteeism Rate</h5>
                      </div>
                      <div class="card-body d-flex justify-content-center">
                        <canvas id="absrate" width="400" height="135"></canvas>
                        <div id="abs-value"></div>
                        <small id="abs-value-sub">Absenteeism Rate</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <!--deductions-->
              <div class="col-lg-4 col-sm-12 mb-3">
                <div class="card h-100">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Deduction Breakdown</h5>
                  </div>
                  <div class="card-body">
                    <div id="deductionBreakdown"></div>
                  </div>
                </div>
              </div>

              <!--average gross salary-->
              <div class="col-lg-8 col-sm-12 mb-3">
                <div class="card h-100">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Average Gross Salary</h5>
                  </div>
                  <div class="card-body pb-0">
                    <div id="averageGrossSalary"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <!--worked hours vs overtime hours-->
              <div class="col-lg-4 col-sm-12">
                <div class="card mb-3">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Worked Hours vs Overtime Hours</h5>
                    <div class="ms-3" id="branchDIV">
                      <select class="form-select" id="branch" data-placeholder="Branch">
                        <option></option>
                        <option>Bulacan</option>
                        <option>Cabuyao</option>
                        <option>Cainta</option>
                        <option>Cavite</option>
                        <option>Greenwoods</option>
                        <option>Parian</option>
                        <option>San Pedro</option>
                      </select>
                    </div>
                  </div>
                  <div class="card-body pb-5 ">
                    <div id="workedVsOvertime"></div>
                  </div>
                </div>
              </div>

              <!--contributions-->
              <div class="col-lg-8 col-sm-12">
                <div class="card mb-3">
                  <div class="card-header">
                    <div class="row">
                      <div class="col-lg-6 col-sm-12">
                        <h5 class="card-title mb-0">Contributions: Paid vs Not Paid</h5>
                      </div>
                      <div class="col-lg-6 col-sm-12 d-flex justify-content-end">
                        <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                          <input type="radio" class="btn-check" name="btn-contri" id="ssscontri" checked="">
                          <label class="btn btn-outline-primary btn-sm" for="ssscontri">SSS</label>
                          <input type="radio" class="btn-check" name="btn-contri" id="philcontri">
                          <label class="btn btn-outline-primary btn-sm" for="philcontri">Philhealth</label>
                          <input type="radio" class="btn-check" name="btn-contri" id="pagibigcontri">
                          <label class="btn btn-outline-primary btn-sm" for="pagibigcontri">Pag-Ibig</label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="card-body pb-0">
                    <div id="sss-contributions"></div>
                    <div id="philhealth-contributions"></div>
                    <div id="pagibig-contributions"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-4 col-sm-12">
                <!--absences-->
                <div class="card mb-3">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Absences</h5>
                  </div>
                  <div class="card-body pb-6">
                    <div class="row">
                      <div class="col-lg-6 col-6 border-end text-center">
                        <h4 class="card-title mb-0">30</h4>
                        <p class="mb-0">Sick Leave</p>
                      </div>
                      <div class="col-lg-6 col-6 text-center">
                        <h4 class="card-title mb-0">67</h4>
                        <p class="mb-0">Vacation Leave</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!--termination-->
                <div class="card mb-3">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Terminations</h5>
                  </div>
                  <div class="card-body pb-6">
                    <div class="row">
                      <div class="col-lg-6 col-6 border-end text-center">
                        <h4 class="card-title mb-0">12</h4>
                        <p class="mb-0">Current Month</p>
                      </div>
                      <div class="col-lg-6 col-6  text-center">
                        <h4 class="text-muted mb-0">5</h4>
                        <p class="text-muted mb-0">Last Month</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--gender pay analysis-->
              <div class="col-lg-8 col-sm-12 mb-3">
                <div class="card h-100">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Gender Pay Analysis</h5>
                  </div>
                  <div class="card-body pb-0">
                    <div id="genderPayAnalysis"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">

            </div>

          </div>

          <!-- / Content -->

          <div class="content-backdrop fade"></div>
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
      </div>
    </div>
  </div>
  <!-- / Layout wrapper -->

  <?Php require("../includes/footer.php") ;?>
    <script src="js/index-01.js?v=20260531"></script>
  <?Php require("../includes/custom-footer.php") ;?>

  <!-- Apex Charts -->
  <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
  <script src="../assets/vendor/libs/gauge-js/gauge.min.js"></script>

  <!-- endbuild -->

  <!-- Main JS -->
  <!-- <script src="../assets/js/main.js"></script> -->
  <script src="../assets/js/payrolldashboard-graphs.js"></script>

  <script>
      $('#client').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client'
      });

      $('#payrollMonth').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Month'
      });

      $('#payrollYear').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Year'
      });
  </script>
</body>

</html>