<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Payslip</title>
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

  <style>
    div.dt-top-container {
      display: grid;
      grid-template-columns: auto auto auto;
    }

    div.dt-center-in-div {
      margin: 0 auto;
    }

    div.dt-filter-spacer {
      margin: 20px 0;
    }

    .dt-info {
      margin-top: 15px;
    }

    .pagination {
      justify-content: end;
      margin-top: -20px;
      margin-bottom: 0;
    }

    table.dataTable.table>tbody>tr.selected>* {
      box-shadow: inset 0 0 0 9999px #F6A20F !important;
      color: #343a40;
    }

    @media screen and (max-width: 500px) {
      div.dt-top-container {
        grid-template-columns: none;
        margin-bottom: 10px;
      }

      div.dt-center-in-div {
        margin-top: 5px;
      }

      .dt-info {
        margin-top: 0;
      }

      .pagination {
        justify-content: center;
        margin-top: 10px;
        margin-bottom: 0;
      }
    }
  </style>
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
                    <li class="breadcrumb-item active" aria-current="page">Payslip</li>
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
            <div class="card">
              <div class="card-body">
                <div class="row">

                  <div class="col-sm-3">
                    <label class="form-label fw-bold">Client:</label>
                    <select class="form-select" id="client" data-placeholder="Client">
                    </select>
                  </div>

                  <div class="col-sm-3">
                    <label class="form-label fw-bold">Pay Day:</label>
                    <select class="form-select" id="payDay" data-placeholder="Pay Day">
                    </select>
                  </div>

                  <div class="col-sm-3">
                    <label class="form-label fw-bold">Branch:</label>
                    <select class="form-select" id="branch" data-placeholder="Branch">
                    </select>
                  </div>

                  <div class="col-sm-3">
                    <label class="form-label fw-bold">Client Location:</label>
                    <select class="form-select" id="clientLocation" data-placeholder="Client Location">
                    </select>
                  </div>

                </div>

                <div class="row mt-2">

                  <div class="col-sm-3">
                    <label class="form-label fw-bold">Pay Type:</label>
                    <select class="form-select" id="payType" data-placeholder="Pay Type">
                    </select>
                  </div>

                  <div class="col-sm-3">
                    <label class="form-label fw-bold">Bank Name:</label>
                    <select class="form-select" id="bankName" data-placeholder="Bank Name">
                    </select>
                  </div>

                  <div class="col-sm-6">
                    <label class="form-label fw-bold">&nbsp;</label>
                    <br>
                    <button id="filterBtn" class="btn btn-md btn-primary">Generate Payroll</button>
                    <button id="payslipBtn" class="btn btn-md btn-primary">Generate Payslip</button>
                    <button id="postBtn" class="btn btn-md btn-danger">Post Payroll</button>
                    <button id="clearBtn" class="btn btn-md btn-secondary">Clear</button>
                  </div>
                </div>

              </div>
            </div>

            <div style="display:none" class="card mt-4" id="tblDiv2">
              <div class="card-body">
                <div class="card-datatable mt-n2" id="table_container2">
                </div>
              </div>
            </div>

            <div style="display:none" class="card mt-4" id="tblDiv">
              <div class="card-body">
                <div class="card-datatable mt-n2" id="table_container">
                </div>
              </div>
            </div>

            <div style="display:none" class="card mt-4">
              <div class="card-body">
                <div class="card-datatable mt-n2" id="bankTransfer">
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
    <!-- / Layout wrapper -->

    <?Php require("../includes/footer.php") ;?>
    <script src="js/index-09.js?v=20260531"></script>
    <?Php require("../includes/custom-footer.php") ;?>

    <script>
      $('#client').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client'
      });

      $('#branch').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Branch'
      });

      $('#clientLocation').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client Location'
      });

      $('#payDay').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Pay Day'
      });

      $('#payType').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Pay Type'
      });

      $('#bankName').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Bank Name'
      });
    </script>
</body>

</html>