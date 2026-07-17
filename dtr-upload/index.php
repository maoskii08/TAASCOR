<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>DTR Upload</title>
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
                    <li class="breadcrumb-item active" aria-current="page">DTR Upload</li>
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

                  <div class="col-sm-2">
                    <label class="form-label fw-bold">Pay Day:</label>
                    <select class="form-select" id="payDay" data-placeholder="Pay Day">
                    </select>
                  </div>

                  <div class="col-sm-2">
                    <label class="form-label fw-bold">Branch:</label>
                    <select class="form-select" id="branch" data-placeholder="Branch">
                    </select>
                  </div>

                  <div class="col-sm-3">
                    <label class="form-label fw-bold">Client Location:</label>
                    <select class="form-select" id="clientLocation" data-placeholder="Client Location">
                    </select>
                  </div>

                  <div class="col-sm-2">
                    <label class="form-label fw-bold">&nbsp;</label>
                    <br>
                    <button id="filterBtn" class="btn btn-md btn-primary">Filter</button>
                    <button id="clearBtn" class="btn btn-md btn-secondary">Clear</button>
                  </div>
                </div>
              </div>
            </div>

            <div style="display:none" class="card mt-4" id="tblDiv">
              <div class="card-body">
                <div class="card-datatable mt-n2" id="table_container">
                </div>
              </div>
            </div>

            <div style="display:none" class="card mt-4" id="tblDiv2">
              <div class="card-body">
                <div class="card-datatable mt-n2" id="table_container2">
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

    <!-- Modal -->
    <div class="modal fade" id="importModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Upload DTR</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="height:600px; overflow-y: scroll; overflow-x: hidden;">
            <div class="form-group" style="display: none;">
              <label for="accounts">Select Data</label>
              <select class="form-control input-sm" id="dataType">
              <option value="controller/PostImportController.php"></option>
              </select>
            </div>
            <div class="form-group">
              <label for="inputsm">Select excel file</label>
              <input type="file" id="fileUploader" class="btn btn-fill btn-default btn-sm" />
            </div>
            <br>
            <div class="form-group">
              <div id="readingFileStatus"></div>
              <div class="table-responsive">
                  <div id="tableOutput"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>


    <!-- Modal -->
    <div class="modal fade" id="editDTRModal" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Edit Employee DTR</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="height:600px; overflow-y: scroll; overflow-x: hidden;">
            <div class="row mt-n3">
              <div class="col-sm-6">
                <div class="row">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Employee Information</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Employee ID:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="employee-ident" type="text" class="form-control" placeholder="Employee Ident" disabled>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Employee Full Name:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="employee-full-name" type="text" class="form-control" placeholder="Employee Full Name" disabled>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Daily Salary: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="daily-salary" type="number" class="form-control" placeholder="Daily Salary">
                  </div>
                </div>

                <!--DTR DETAILS-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">DTR Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Days Worked: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="days-worked" type="number" class="form-control" placeholder="Days Worked">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Absent:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="absent" type="number" class="form-control" placeholder="Absent">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Lates:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="lates" type="number" class="form-control" placeholder="Lates">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Undertime:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="undertime" type="number" class="form-control" placeholder="Undertime">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Vacation Leave:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="vacation-leave" type="number" class="form-control" placeholder="Vacation Leave">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Sick Leave:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="sick-leave" type="number" class="form-control" placeholder="Sick Leave">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Overtime:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="overtime" type="number" class="form-control" placeholder="Overtime">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Night Differential:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="night-diff" type="number" class="form-control" placeholder="Night Differential">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Night Differential OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="night-diff-ot" type="number" class="form-control" placeholder="Night Differential OT">
                  </div>
                </div>

                <!--Rest Day-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Rest Day Details</small>
                      </div>
                    </div>
                  </div>
                </div>


                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rest-day" type="number" class="form-control" placeholder="Rest Day">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rest-day-ot" type="number" class="form-control" placeholder="Rest Day OT">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day Night Diff:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rest-day-night-diff" type="number" class="form-control" placeholder="Rest Day Night Diff">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day ND OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rest-day-nd-ot" type="number" class="form-control" placeholder="Rest Day ND OT">
                  </div>
                </div>

                </div>

                <div class="col-sm-6">

                <!--Regular Holidays-->
                <div class="row">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Regular Holiday Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Regular Holiday:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="regular-holiday" type="number" class="form-control" placeholder="Regular Holiday">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Regular Holiday OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="regular-holiday-ot" type="number" class="form-control" placeholder="Regular Holiday OT">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Regular Holiday Night Diff:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="regular-holiday-night-diff" type="number" class="form-control" placeholder="Regular Holiday Night Diff">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Regular Holiday ND OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="regular-holiday-nd-ot" type="number" class="form-control" placeholder="Regular Holiday ND OT">
                  </div>
                </div>


                <!--Rest Day Regular Holidays-->
                <div class="row">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Rest Day - Regular Holiday Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day - Regular Holiday:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-regular-holiday" type="number" class="form-control" placeholder="Rest Day - Regular Holiday">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day - Regular Holiday OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-regular-holiday-ot" type="number" class="form-control" placeholder="Rest Day - Regular Holiday OT">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day - Regular Holiday Night Diff:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-regular-holiday-night-diff" type="number" class="form-control" placeholder="Rest Day - Regular Holiday Night Diff">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day - Regular Holiday ND OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-regular-holiday-nd-ot" type="number" class="form-control" placeholder="Rest Day - Regular Holiday ND OT">
                  </div>
                </div>

                <!--Special holiday-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Special Holiday Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Special Holiday:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="special-holiday" type="number" class="form-control" placeholder="Special Holiday">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Special Holiday OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="special-holiday-ot" type="number" class="form-control" placeholder="Special Holiday OT">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Special Holiday Night Diff:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="special-holiday-night-diff" type="number" class="form-control" placeholder="Special Holiday Night Diff">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Special Holiday ND OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="special-holiday-nd-ot" type="number" class="form-control" placeholder="Special Holiday ND OT">
                  </div>
                </div>
                

                <!--Rest Day - Special holiday-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Rest Day - Special Holiday Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day - Special Holiday:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-special-holiday" type="number" class="form-control" placeholder="Rest Day - Special Holiday">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Special Holiday OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-special-holiday-ot" type="number" class="form-control" placeholder="Rest Day - Special Holiday OT">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Special Holiday Night Diff:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-special-holiday-night-diff" type="number" class="form-control" placeholder="Rest Day - Special Holiday Night Diff">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Rest Day - Special Holiday ND OT:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="rd-special-holiday-nd-ot" type="number" class="form-control" placeholder="Rest Day - Special Holiday ND OT">
                  </div>
                </div>

                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="saveChanges" type="button" class="btn btn-md btn-primary">Save Changes</button>
          </div>
        </div>
      </div>
    </div>


    <div class="modal fade" id="dateModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
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
                        <small class="text-uppercase fw-bold">Payroll Dates</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Start Date:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="m-start-date" value="" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">End Date:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="m-end-date" value="" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Pay Day:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="m-pay-date" value="" type="date" class="form-control">
                  </div>
                </div>

              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="proceedBtn" type="button" class="btn btn-md btn-primary">Proceed</button>
          </div>
        </div>
      </div>
    </div>

    <?Php require("../includes/footer.php") ;?>
    <script src="js/index-13.js?v=20260531"></script>
    <script src="js/app-excel-import-v05.js"></script>
    <?Php require("../includes/custom-footer.php") ;?>

    <script>
      $('#client').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client'
      });

      $('#payDay').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Pay Day'
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
    </script>
</body>

</html>