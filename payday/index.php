<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Pay Day</title>
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
    table.dataTable th.dt-type-numeric, 
    table.dataTable th.dt-type-date, 
    table.dataTable td.dt-type-numeric, 
    table.dataTable td.dt-type-date {
      text-align: left;
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
                    <li class="breadcrumb-item active" aria-current="page">Pay Day</li>
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
            <div class="card mt-4">
              <div class="card-body">
              <div class="row">
                  <div class="col-sm-12">
                    <button class="btn btn-md btn-primary" data-bs-toggle="modal"
                      data-bs-target="#addModal">
                      <i class='bx bx-plus me-1'></i> Add
                    </button>
                  </div>
                </div>
                <div class="card-datatable mt-n2" id="table_container">
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

    <div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Pay Day Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" >
            <div class="row">
              <div class="col-sm-12">

                <div class="row mt-n3">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Pay Day Information</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client Name:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-client-name" data-placeholder="Client Name">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Cut Off:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-cut-off" data-placeholder="Cut Off">
                      <option value="">SELECT CUT OFF</option>
                      <option value="1-15">1-15</option>
                      <option value="6-20">6-20</option>
                      <option value="10-25">10-25</option>
                      <option value="11-25">11-25</option>
                      <option value="13-27">13-27</option>
                      <option value="16-30">16-30</option>
                      <option value="21-5">21-5</option>
                      <option value="26-10">26-10</option>
                      <option value="26-30">26-30</option>
                      <option value="28-12">28-12</option>
                      <option value="Weekly">Weekly</option>
                    </select>
                  </div>
                </div>

                <div style="display:none" class="row mt-2" id="add-pay-container">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Pay Day:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-pay-day" data-placeholder="Pay Day">
                      <option value="">SELECT PAY DAY</option>
                      <option value="5">5</option>
                      <option value="7">7</option>
                      <option value="10">10</option>
                      <option value="15">15</option>
                      <option value="20">20</option>
                      <option value="22">22</option>
                      <option value="25">25</option>
                      <option value="30">30</option>
                    </select>
                  </div>
                </div>

              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="addBtn" type="button" class="btn btn-md btn-primary">Add</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Pay Day Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" >
            <div class="row">
              <div class="col-sm-12">

                <div class="row mt-n3">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Pay Day Information</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client Name:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-client-name" data-placeholder="Client Name">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Cut Off:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-cut-off" data-placeholder="Cut Off">
                      <option value="">SELECT CUT OFF</option>
                      <option value="1-15">1-15</option>
                      <option value="6-20">6-20</option>
                      <option value="10-25">10-25</option>
                      <option value="11-25">11-25</option>
                      <option value="13-27">13-27</option>
                      <option value="16-30">16-30</option>
                      <option value="21-5">21-5</option>
                      <option value="26-10">26-10</option>
                      <option value="26-30">26-30</option>
                      <option value="28-12">28-12</option>
                      <option value="Weekly">Weekly</option>
                    </select>
                  </div>
                </div>

                <div style="display:none" class="row mt-2" id="edit-pay-container">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Pay Day:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-pay-day" data-placeholder="Pay Day">
                      <option value="">SELECT PAY DAY</option>
                      <option value="5">5</option>
                      <option value="7">7</option>
                      <option value="10">10</option>
                      <option value="15">15</option>
                      <option value="20">20</option>
                      <option value="22">22</option>
                      <option value="25">25</option>
                      <option value="30">30</option>
                    </select>
                  </div>
                </div>

              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="saveBtn" type="button" class="btn btn-md btn-primary">Save Changes</button>
          </div>
        </div>
      </div>
    </div>

    <?Php require("../includes/footer.php") ;?>
    <script src="js/index-01.js?v=20260731a"></script>
    <?Php require("../includes/custom-footer.php") ;?>

    <script>
      $('#add-client-name').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client Name',
        dropdownParent: $('#addModal')
      });

      $('#add-cut-off').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Cut Off',
        dropdownParent: $('#addModal')
      });

      $('#add-pay-day').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Pay Day',
        dropdownParent: $('#addModal')
      });

      $('#edit-client-name').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client Name',
        dropdownParent: $('#editModal'),
      });

      $('#edit-cut-off').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Cut Off',
        dropdownParent: $('#editModal'),
      });

      $('#edit-pay-day').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Pay Day',
        dropdownParent: $('#editModal'),
      });
    </script>
</body>

</html>
