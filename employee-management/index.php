<?php
require_once('../includes/auth_guard.php');
auth_require_role([1,2,3,4]);
$employeeWorkspaceEmbed = ($_GET['embed'] ?? '') === '1';
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Employee Management</title>
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
  <link rel="stylesheet"  href="https://cdn.datatables.net/select/3.0.0/css/select.dataTables.css"/>

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
      box-shadow: inset 0 0 0 9999px rgb(251, 236, 152) !important;
      color: #343a40;
    }

    body.employee-workspace-embed { background: #fff; overflow: hidden; }
    body.employee-workspace-embed .layout-wrapper > .layout-container { display: none !important; }
    body.employee-workspace-embed .modal-backdrop { display: none !important; }
    body.employee-workspace-embed .modal { background: #fff; }
    body.employee-workspace-embed .modal-dialog {
      max-width: 100%;
      width: 100%;
      height: 100%;
      min-height: 100%;
      margin: 0;
    }
    body.employee-workspace-embed .modal-content {
      min-height: 100%;
      border: 0;
      border-radius: 0;
      box-shadow: none;
    }
    body.employee-workspace-embed .modal-body {
      height: auto !important;
      flex: 1 1 auto;
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

<body class="<?php echo $employeeWorkspaceEmbed ? 'employee-workspace-embed' : ''; ?>">
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
                    <li class="breadcrumb-item active" aria-current="page">Employee Management</li>
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

                  <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label fw-bold">Employee Ident:</label>
                    <input id="employee" type="number" class="form-control" placeholder="Enter Employee Ident">
                  </div>

                  <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label fw-bold">Branch:</label>
                    <select class="form-select" id="branch" data-placeholder="Branch">
                    </select>
                  </div>

                  <div class="col-lg-2 col-md-4 col-sm-6">
                    <label class="form-label fw-bold">Client:</label>
                    <select class="form-select" id="client" data-placeholder="Client">
                    </select>
                  </div>

                  <div class="col-lg-3 col-md-4 col-sm-6">
                    <label class="form-label fw-bold">Client Location:</label>
                    <select class="form-select" id="client-location-filter" data-placeholder="Client Location">
                    </select>
                  </div>

                  <div class="col-lg-3 col-md-8 col-sm-12">
                    <label class="form-label fw-bold">&nbsp;</label>
                    <br>
                    <button id="filterBtn" class="btn btn-md btn-primary">Filter</button>
                    <button id="clearBtn" class="btn btn-md btn-secondary">Clear</button>
                    <button style="display:none" id="addEmployeeBtn" class="btn btn-md btn-primary float-end" data-bs-toggle="modal"
                      data-bs-target="#addEmployeeModal">
                      <i class='bx bx-user-plus me-1'></i> Employee
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="card mt-4">
              <div class="card-body">
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

    <div class="modal fade" id="terminateModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Terminate Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" >
            <div class="row">
              <div class="col-sm-12">

                <div class="row mt-n3">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Employee Separation Date</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Employee ID:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="term-employee-id" type="text" class="form-control" disabled>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Separation Date:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="term-date" type="date" class="form-control">
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="terminateBtn" type="button" class="btn btn-md btn-primary">Terminate</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="importModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Upload Employee Data</h5>
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
              <label for="import-client">Import client scope</label>
              <select class="form-select" id="import-client" data-placeholder="Select client" required>
                <option value="" disabled selected>Select Client</option>
              </select>
              <div class="form-text">
                Every workbook row must match this client. The scope is bound to a server-issued import reference.
              </div>
            </div>
            <div class="form-group mt-3">
              <label for="inputsm">Select excel file</label>
              <input type="file" id="fileUploader" class="btn btn-fill btn-default btn-sm"
                accept=".xlsx,.xls,.csv" />
              <div class="form-text">Maximum 1,000 rows and 5 MiB per workbook.</div>
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
    <div class="modal fade" id="addEmployeeModal" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Add Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="height:600px; overflow-y: scroll; overflow-x: hidden;">
            <div class="alert alert-info d-none" id="identity-prefill-alert" role="alert"></div>
            <div class="row mt-n3">
              <div class="col-sm-6">
                <!--Employee Information-->
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
                    <label class="modal-label mt-2">Old Employee Ident:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-old-employee-ident" type="text" class="form-control" placeholder="Old Employee Ident">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Employee Type: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-employee-type" data-placeholder="Department" required>
                      <option></option>
                      <option value="Long Term">Long Term</option>
                      <option value="Seasonal">Seasonal</option>
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Full Name: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-full-name" type="text" class="form-control" placeholder="Full Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Last Name: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-last-name" type="text" class="form-control" placeholder="Last Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">First Name: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-first-name" type="text" class="form-control" placeholder="First Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Middle Name:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-middle-name" type="text" class="form-control" placeholder="Middle Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Gender: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select id="add-gender" class="form-select">
                      <option value="" selected disabled>Select Gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Civil Status: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select id="add-civil-status" class="form-select">
                      <option value="" selected disabled>Select Civil Status</option>
                      <option value="Single">Single</option>
                      <option value="Married">Married</option>
                      <option value="Widowed">Widowed</option>
                      <option value="Separated">Separated</option>
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Hire Date: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-hire-date" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Present Address: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <textarea id="add-present-address" class="form-control" rows="2" placeholder="Present Address"></textarea>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Permanent Address:</label>
                  </div>
                  <div class="col-sm-8">
                    <textarea id="add-permanent-address" class="form-control" rows="2" placeholder="Permanent Address"></textarea>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Contact Number: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-contact-number" type="number" class="form-control" placeholder="Contact Number">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Email:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-email" type="email" class="form-control" placeholder="Email">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Birthday: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-birthday" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Birth Place: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-birth-place" type="text" class="form-control" placeholder="Birth Place">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Nationality:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-nationality" type="text" class="form-control" placeholder="Nationality">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Pay Type: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-pay-type" type="text" class="form-control" placeholder="Pay Type">
                  </div>
                </div>

                <!--Emergency Contact Person-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Emergency Contact Person</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Emergency Person:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-emergency-person" type="text" class="form-control" placeholder="Emergency Person">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Emergency Contact No.:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-emergency-contact-number" type="text" class="form-control" placeholder="Emergency Contact No.">
                  </div>
                </div>

                </div>

                <div class="col-sm-6">

                <!--Employee Status-->
                <div class="row">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Employee Status</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Branch: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-branch" data-placeholder="Branch">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-client" data-placeholder="Client">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client Date: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-client-date" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client Location: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-client-location" data-placeholder="Client Location">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Department:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-department" data-placeholder="Department">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Position: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="add-position" data-placeholder="Position">
                    </select>
                  </div>
                </div>

                <!--Employee Government Account-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Employee Government Account</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">TIN: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-tin" type="text" class="form-control" placeholder="TIN">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">SSS: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-sss" type="text" class="form-control" placeholder="SSS">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">PHILHEALTH: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-philhealth" type="text" class="form-control" placeholder="PHILHEALTH">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">PAG-IBIG: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-pag-ibig" type="text" class="form-control" placeholder="PAG-IBIG">
                  </div>
                </div>

                <!--Salary and Bank Details-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Salary and Bank Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Daily Salary: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-daily-salary" type="number" class="form-control" placeholder="Daily Salary">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Bank Name:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-bank-name" type="text" class="form-control" placeholder="Bank Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Bank Account Number: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-bank-account-number" type="text" class="form-control" placeholder="Bank Account Number">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Insurance:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-insurance" type="text" class="form-control" placeholder="Insurance">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Annual Leaves: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-annual-leaves" type="number" class="form-control" placeholder="Annual Leaves">
                  </div>
                </div>

                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Payroll Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Payroll Employee ID:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-payroll-employee-ident" type="text" class="form-control" placeholder="Payroll Employee ID">
                  </div>
                </div>

                <!-- <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Payroll Branch Code:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="add-payroll-branch-code" type="text" class="form-control" placeholder="Payroll Branch Code">
                  </div>
                </div> -->

                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button id="addBtn" type="button" class="btn btn-md btn-primary">Add Employee</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="editEmployeeModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Update Employee Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="height:600px; overflow-y: scroll; overflow-x: hidden;">
            <div class="row mt-n3">
              <div class="col-sm-6">

                <!--Employee Information-->
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
                    <label class="modal-label mt-2">Employee Ident:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-employee-ident" type="text" class="form-control" value="1001" disabled>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Old Employee Ident:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-old-employee-ident" type="text" class="form-control" placeholder="Old Employee Ident">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Employee Type: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-employee-type" data-placeholder="Department" required>
                      <option></option>
                      <option value="Long Term">Long Term</option>
                      <option value="Seasonal">Seasonal</option>
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Full Name: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-full-name" type="text" class="form-control" placeholder="Full Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Last Name: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-last-name" type="text" class="form-control" placeholder="Last Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">First Name: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-first-name" type="text" class="form-control" placeholder="First Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Middle Name:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-middle-name" type="text" class="form-control" placeholder="Middle Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Gender: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select id="edit-gender" class="form-select">
                      <option value="" selected disabled>Select Gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Civil Status: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select id="edit-civil-status" class="form-select">
                      <option value="" selected disabled>Select Civil Status</option>
                      <option value="Single">Single</option>
                      <option value="Married">Married</option>
                      <option value="Widowed">Widowed</option>
                      <option value="Separated">Separated</option>
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Hire Date: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-hire-date" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Present Address: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <textarea id="edit-present-address" class="form-control" rows="2" placeholder="Present Address"></textarea>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Permanent Address:</label>
                  </div>
                  <div class="col-sm-8">
                    <textarea id="edit-permanent-address" class="form-control" rows="2" placeholder="Permanent Address"></textarea>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Contact Number: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-contact-number" type="number" class="form-control" placeholder="Contact Number">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Email:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-email" type="email" class="form-control" placeholder="Email">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Birthday: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-birthday" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Birth Place: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-birth-place" type="text" class="form-control" placeholder="Birth Place">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Nationality:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-nationality" type="text" class="form-control" placeholder="Nationality">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Pay Type: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-pay-type" type="text" class="form-control" placeholder="Pay Type">
                  </div>
                </div>

                <!--Emergency Contact Person-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Emergency Contact Person</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Emergency Person:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-emergency-person" type="text" class="form-control" placeholder="Emergency Person">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Emergency Contact No.:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-emergency-contact-number" type="text" class="form-control" placeholder="Emergency Contact No.">
                  </div>
                </div>

              </div>

              <div class="col-sm-6">

                <!--Employee Status-->
                <div class="row">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Employee Status</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Branch: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-branch" data-placeholder="Branch">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-client" data-placeholder="Client">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client Date: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-client-date" type="date" class="form-control">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Client Location: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-client-location" data-placeholder="Client Location">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Department:</label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-department" data-placeholder="Department">
                    </select>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Position: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <select class="form-select" id="edit-position" data-placeholder="Position">
                    </select>
                  </div>
                </div>

                <!--Employee Government Account-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Employee Government Account</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">TIN: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-tin" type="text" class="form-control" placeholder="TIN">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">SSS: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-sss" type="text" class="form-control" placeholder="SSS">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">PHILHEALTH: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-philhealth" type="text" class="form-control" placeholder="PHILHEALTH">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">PAG-IBIG: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-pag-ibig" type="text" class="form-control" placeholder="PAG-IBIG">
                  </div>
                </div>

                <!--Salary and Bank Details-->
                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Salary and Bank Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Daily Salary: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-daily-salary" type="number" class="form-control" placeholder="Daily Salary">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Bank Name:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-bank-name" type="text" class="form-control" placeholder="Bank Name">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Bank Account Number: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-bank-account-number" type="text" class="form-control" placeholder="Bank Account Number">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Insurance:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-insurance" type="text" class="form-control" placeholder="Insurance">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Annual Leaves: <span class="text-danger font-weight-bold">*</span></label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-annual-leaves" type="number" class="form-control" placeholder="Annual Leaves">
                  </div>
                </div>

                <div class="row mt-5">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">Payroll Details</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Payroll Employee ID:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-payroll-employee-ident" type="text" class="form-control" placeholder="Payroll Employee ID">
                  </div>
                </div>

                <!-- <div class="row mt-2">
                  <div class="col-sm-4">
                    <label class="modal-label mt-2">Payroll Branch Code:</label>
                  </div>
                  <div class="col-sm-8">
                    <input id="edit-payroll-branch-code" type="text" class="form-control" placeholder="Payroll Branch Code">
                  </div>
                </div> -->

              </div>
            </div>
          </div>
          <div class="modal-footer">
            <?php if ($employeeWorkspaceEmbed): ?>
            <div class="me-auto d-flex gap-2">
              <button id="workspaceTerminateBtn" type="button" class="btn btn-outline-warning">
                <i class="bx bx-user-x me-1" aria-hidden="true"></i>Terminate
              </button>
              <?php if (auth_level() === 1): ?>
              <button id="workspaceRemoveBtn" type="button" class="btn btn-outline-danger">
                <i class="bx bx-trash me-1" aria-hidden="true"></i>Remove from HRIS
              </button>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <button id="saveBtn" type="button" class="btn btn-md btn-primary">Save Changes</button>
          </div>
        </div>
      </div>
    </div>

    
    <?Php require("../includes/footer.php") ;?>
    <script src="https://cdn.datatables.net/select/3.0.0/js/dataTables.select.js"></script>
    <script src="https://cdn.datatables.net/select/3.0.0/js/select.dataTables.js"></script>

    <script src="js/index-18.js?v=20260727d"></script>
    <script src="js/app-excel-import-v04.js?v=20260727b"></script>
    <?php if (!$employeeWorkspaceEmbed) require("../includes/custom-footer.php"); ?>

    <script>
      // $(document).ready(function () {
      //   $('.collapse').collapse({
      //     toggle: false
      //   });
      // });

      $('#client').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client'
      });

      $('#client-location-filter').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client Location'
      });

      $('#branch').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Branch'
      });

      $('#add-client').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client',
        dropdownParent: $('#addEmployeeModal')
      });

      $('#add-client-location').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client Location',
        dropdownParent: $('#addEmployeeModal')
      });

      $('#add-branch').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Branch',
        dropdownParent: $('#addEmployeeModal')
      });

      $('#add-department').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Department',
        dropdownParent: $('#addEmployeeModal')
      });

      $('#add-position').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Position',
        dropdownParent: $('#addEmployeeModal')
      });

      $('#add-employee-type').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Employee Type',
        dropdownParent: $('#addEmployeeModal')
      });

      $('#edit-client').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client',
        dropdownParent: $('#editEmployeeModal')
      });

      $('#edit-client-location').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Client Location',
        dropdownParent: $('#editEmployeeModal')
      });

      $('#edit-branch').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Branch',
        dropdownParent: $('#editEmployeeModal')
      });

      $('#edit-department').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Department',
        dropdownParent: $('#editEmployeeModal')
      });

      $('#edit-position').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Position',
        dropdownParent: $('#editEmployeeModal')
      });

      $('#edit-employee-type').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select Employee Type',
        dropdownParent: $('#editEmployeeModal')
      });
    </script>
</body>

</html>
