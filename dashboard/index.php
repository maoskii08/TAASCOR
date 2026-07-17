<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Dashboard</title>
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
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
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
            <div class="row mb-2">
              <div class="col-sm-12 col-lg-4 mb-3">
                <div class="card h-100">
                  <div class="d-flex align-items-end row g-0">
                    <div class="col-6">
                      <div class="card-body">
                        <h4 class="card-title mb-1 text-nowrap">Hello, <?php echo $_SESSION['taascor_first_name'] ?></h4>
                        <p class="card-subtitle text-nowrap mb-3">Welcome to your dashboard.</p>

                        <p class="card-sub-title text-primary fw-bold mb-0" id="newHires">0 hires</p>
                        <p class="card-sub-title mb-3">this month.</p>


                        <a href="/<?php echo $pathParts[6];?>/employee-management/" class="btn btn-sm btn-primary mb-3">View employees</a>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="card-body pb-2 text-end">
                        <img src="../assets/img/svg/analytics.svg" class="dashboard-img rounded-start" alt="View Sales">
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-sm-12 col-lg-8">
                <div class="card">
                  <div class="card-body row g-0 p-0">
                    <div class="col-sm-6 col-md-6 col-lg-6 card-separator">
                      <div class="p-4">
                        <div class="card-title d-flex align-items-start justify-content-between">
                          <h5 class="mb-2">Gender</h5>
                        </div>

                        <div class="row mb-n2">
                          <div class="col-sm-6 col-lg-6 text-center border-end">
                            <div class="d-flex justify-content-center mb-3">
                              <div class="avatar avatar-md-dash flex-shrink-0">
                                <span class="avatar-initial avatar-shadow-primary rounded-circle"><i
                                    class="icon-base bx bx-male bx-lg-dash"></i></span>
                              </div>
                            </div>
                            <h4 class="card-title mb-0 mt-n1" id="male">0</h4>
                            <p class="mb-2">Male</p>
                          </div>

                          <div class="col-sm-6 col-lg-6 text-center">
                            <div class="d-flex justify-content-center mb-3">
                              <div class="avatar avatar-md-dash flex-shrink-0">
                                <span class="avatar-initial avatar-shadow-danger rounded-circle"><i
                                    class="icon-base bx bx-female bx-lg-dash"></i></span>
                              </div>
                            </div>
                            <h4 class="card-title mb-0 mt-n1" id="female">0</h4>
                            <p class="mb-2">Female</p>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="col-sm-6 col-md-6 col-lg-6">
                      <div class="p-4">
                        <div class="card-title d-flex align-items-start justify-content-between">
                          <h5 class="mb-0">Civil Status</h5>
                        </div>
                        <div class="card-body p-0">
                          <div id="civilstatuschart" class="mb-n6"></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row mb-2">
              <div class="col-sm-12 col-lg-4">
                <div class="row">
                  <div class="col-sm-6 col-lg-6 mb-3">
                    <div class="card h-100">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                          <div class="avatar">
                            <span class="avatar-initial icon-bg rounded bg-label-primary">
                              <i class="icon-base bx bx-user icon-dashboard"></i>
                            </span>
                          </div>
                          <div class="dropdown">
                            <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                              <a class="dropdown-item" href="/<?php echo $pathParts[6];?>/employee-management/">View Employees</a>
                            </div>
                          </div>
                        </div>
                        <p class="count-card-title mb-1">Employees</p>
                        <h4 class="card-title mb-n1" id="employees">0</h4>
                      </div>
                    </div>
                  </div>

                  <div class="col-sm-6 col-lg-6 mb-3">
                    <div class="card h-100">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                          <div class="avatar">
                            <span class="avatar-initial icon-bg rounded bg-label-success">
                              <i class="icon-base bx bx-group icon-dashboard"></i>
                            </span>
                          </div>
                          <div class="dropdown">
                            <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                              <a class="dropdown-item" href="/<?php echo $pathParts[6];?>/client-maintenance/">View Clients</a>
                            </div>
                          </div>
                        </div>
                        <p class="count-card-title mb-1">Clients</p>
                        <h4 class="card-title mb-n1" id="clients">0</h4>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-6 col-lg-6 mb-3">
                    <div class="card h-100">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                          <div class="avatar">
                            <span class="avatar-initial icon-bg rounded bg-label-warning">
                              <i class="icon-base bx bx-sitemap icon-dashboard"></i>
                            </span>
                          </div>
                          <div class="dropdown">
                            <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                              <a class="dropdown-item" href="/<?php echo $pathParts[6];?>/branch-maintenance/">View Branch</a>
                            </div>
                          </div>
                        </div>
                        <p class="count-card-title mb-1">Branches</p>
                        <h4 class="card-title mb-n1" id="branches">0</h4>
                      </div>
                    </div>
                  </div>

                  <div class="col-sm-6 col-lg-6 mb-3">
                    <div class="card h-100">
                      <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                          <div class="avatar">
                            <span class="avatar-initial icon-bg rounded bg-label-danger">
                              <i class="icon-base bx bx-map-pin icon-dashboard"></i>
                            </span>
                          </div>
                          <div class="dropdown">
                            <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                              aria-haspopup="true" aria-expanded="false">
                              <i class="icon-base bx bx-dots-vertical-rounded text-body-secondary"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                              <a class="dropdown-item" href="/<?php echo $pathParts[6];?>/client-maintenance/">View Client Location</a>
                            </div>
                          </div>
                        </div>
                        <p class="count-card-title mb-1">Client Location</p>
                        <h4 class="card-title mb-n1" id="locations">0</h4>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-sm-12 col-lg-8 mb-2">
                <div class="card">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                      <h5 class="mb-1">Employee per Branch</h5>
                    </div>
                  </div>
                  <div class="card-body pb-0">
                    <div id="employeePerBranchChart"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm-12 col-lg-4 mb-2">
                <div class="row mb-3">
                  <div class="col-sm-12 col-lg-12">
                    <div class="card h-100">
                      <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="card-title mb-0">
                          <h5 class="mb-1">Employee Type</h5>
                        </div>
                      </div>
                      <div class="card-body pb-0">
                        <div id="employeeTypeChart"></div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm-12 col-lg-12 mb-2">
                    <div class="card h-100">
                      <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="card-title mb-0">
                          <h5 class="mb-1">Age Bracket</h5>
                        </div>
                      </div>
                      <div class="card-body pb-0">
                        <div id="ageBracketChart"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-sm-12 col-lg-8 mb-3">
                <div class="card">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                      <h5 class="mb-1">Employee per Client</h5>
                    </div>
                  </div>
                  <div class="card-body pb-0">
                    <div id="employeePerClientChart"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-sm-12 col-lg-12">
                <div class="card h-100">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title mb-0">
                      <h5 class="mb-1">Employee per Client Location</h5>
                    </div>
                  </div>
                  <div class="card-body pb-0">
                    <div id="employeePerClientLocChart"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm-12 col-lg-12 mb-2">
                <div class="card">
                  <div class="card-header">
                    <div class="row">
                      <div class="col-sm-8">
                        <div class="card-title mb-0">
                          <h5 class="mb-1">Total Number of Hires per Month/Year</h5>
                        </div>
                      </div>

                      <div class="col-sm-4 d-flex justify-content-end">
                        <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                          <input type="radio" class="btn-check" name="btnradio" id="btnmonthly" checked="">
                          <label class="btn btn-outline-primary btn-md" for="btnmonthly">Monthly</label>
                          <input type="radio" class="btn-check" name="btnradio" id="btnyearly">
                          <label class="btn btn-outline-primary btn-md" for="btnyearly">Yearly</label>
                        </div>

                        <div style="display:none" class="btn-group ms-3" id="yearDIV">
                          <select class="form-select" id="year" data-placeholder="Branch">
                          </select>
                        </div>

                        <div class="btn-group ms-3">
                          <select class="form-select" id="branch" data-placeholder="Branch">
                          </select>
                          <!-- <button type="button"
                            class="btn btn-secondary btn-md dropdown-toggle overflow-hidden d-sm-inline-flex d-block text-truncate show"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                            Branch
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end" data-popper-placement="bottom-end">
                            <li><button class="dropdown-item" type="button">Bulacan</button></li>
                            <li><button class="dropdown-item" type="button">Cabuyao</button></li>
                            <li><button class="dropdown-item" type="button">Cainta</button></li>
                            <li><button class="dropdown-item" type="button">Cavite</button></li>
                            <li><button class="dropdown-item" type="button">Greenwoods</button></li>
                            <li><button class="dropdown-item" type="button">Parian</button></li>
                            <li><button class="dropdown-item" type="button">San Pedro</button></li>
                          </ul> -->
                        </div>

                        <div class="btn-group ms-3">
                          <button id="clearBtn" class="btn btn-sm btn-secondary">Clear</button>
                        </div>

                      </div>
                    </div>

                  </div>
                  <div class="card-body pb-0">
                    <div class="totalhires"  id="totalHiresMonthly"></div>
                    <div class="totalhires" id="totalHiresYearly"></div>
                  </div>
                </div>
              </div>
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
  <script src="js/index-01.js?v=20260601e"></script>
  <?Php require("../includes/custom-footer.php") ;?>

  <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
  <!-- <script src="../assets/js/dashboard-graphs.js"></script> -->

  <script>
    $('#branch').select2({
      theme: "bootstrap-5",
      width: '100%',
      placeholder: 'Select Branch'
    });

    $('#year').select2({
      theme: "bootstrap-5",
      width: '100%',
      placeholder: 'Select Year'
    });
  </script>
</body>

</html>