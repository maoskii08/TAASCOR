<?php
require_once('../includes/auth_guard.php');
auth_require_role([1,2,3]);
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr"
  data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>
  <title>Client Management</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png"/>
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css"/>
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css"/>
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css"/>
  <link rel="stylesheet" href="../assets/css/demo.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/css/datatables.bootstrap5.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-buttons-bs5/buttons.bootstrap5.css"/>
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
  <style>
    .client-card{cursor:pointer;transition:box-shadow .2s;}
    .client-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.15);}
    .stat-badge{font-size:1.6rem;font-weight:700;color:#1a237e;}
  </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <?php require('../includes/nav-bar.php') ?>
    <div class="home">
      <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
          <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)"><i class="bx bx-menu bx-md"></i></a>
        </div>
        <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
          <div class="navbar-nav align-items-center">
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
              <li class="breadcrumb-item active">Client Management</li>
            </ol></nav>
          </div>
          <ul class="navbar-nav flex-row align-items-center ms-auto">
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
              <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online"><img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle"/></div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3"><div class="avatar avatar-online"><img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle"/></div></div>
                    <div class="flex-grow-1">
                      <h6 class="mb-0"><?= htmlspecialchars($_SESSION['taascor_employee_full_name'] ?? '') ?></h6>
                      <small class="text-muted"><?= htmlspecialchars($_SESSION['taascor_access_description'] ?? '') ?></small>
                    </div>
                  </div>
                </a></li>
                <li><div class="dropdown-divider my-1"></div></li>
                <li><a class="dropdown-item" href="/<?= $pathParts[6] ?>/login/logout.php"><i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span></a></li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

          <div class="card mb-4"><div class="card-body py-3">
            <div class="row align-items-center">
              <div class="col-md-6">
                <input type="text" id="clientSearch" class="form-control" placeholder="🔍  Search clients...">
              </div>
              <div class="col-md-6 text-end">
                <span id="clientCount" class="text-muted small"></span>
              </div>
            </div>
          </div></div>

          <div id="clientGrid" class="row g-4"></div>

        </div>
      </div>

      <!-- Client Detail Modal -->
      <div class="modal fade" id="clientModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bx bx-buildings me-2 text-primary"></i><span id="modalClientName"></span></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <ul class="nav nav-tabs mb-3">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabProfile"><i class="bx bx-info-circle me-1"></i>Profile</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabEmployees" id="tabEmpLink"><i class="bx bx-group me-1"></i>Employees</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPayroll" id="tabPayLink"><i class="bx bx-wallet me-1"></i>Payroll History</a></li>
              </ul>
              <div class="tab-content">
                <div class="tab-pane fade show active" id="tabProfile">
                  <input type="hidden" id="pf_client_id">
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Address</label><input type="text" id="pf_address" class="form-control" placeholder="Office address"></div>
                    <div class="col-md-6"><label class="form-label">Industry</label><input type="text" id="pf_industry" class="form-control" placeholder="e.g. Retail, BPO, Manufacturing"></div>
                    <div class="col-md-4"><label class="form-label">Contact Person</label><input type="text" id="pf_contact_person" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Contact Number</label><input type="text" id="pf_contact_number" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Email</label><input type="email" id="pf_email" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea id="pf_notes" class="form-control" rows="3" placeholder="Internal notes..."></textarea></div>
                  </div>
                  <div id="pfAdminOnly" class="mt-3" style="display:none">
                    <button type="button" id="btnSaveProfile" class="btn btn-primary"><i class="bx bx-save me-1"></i>Save</button>
                    <span id="pfMsg" class="ms-3 small text-success"></span>
                  </div>
                </div>
                <div class="tab-pane fade" id="tabEmployees">
                  <div class="table-responsive"><table id="empDetailTable" class="table table-sm table-bordered w-100"></table></div>
                </div>
                <div class="tab-pane fade" id="tabPayroll">
                  <div class="table-responsive"><table id="payrollHistTable" class="table table-sm table-bordered w-100"></table></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="content-backdrop fade"></div>
    </div>
  </div>
</div>

<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/libs/popper/popper.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/vendor/libs/datatable-bs5/js/dataTables.js"></script>
<script src="../assets/vendor/libs/datatable-bs5/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.html5.min.js"></script>
<script src="../assets/js/main.js"></script>
<script src="js/client-management.js?v=20260531"></script>
<?php require('../includes/custom-footer.php'); ?>
</body>
</html>
