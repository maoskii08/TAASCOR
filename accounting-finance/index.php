<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr"
  data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>
  <title>Accounting / Finance</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png"/>
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css"/>
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css"/>
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css"/>
  <link rel="stylesheet" href="../assets/css/demo.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2.min.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2-bootstrap-5-theme.min.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/css/datatables.bootstrap5.css"/>
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-buttons-bs5/buttons.bootstrap5.css"/>
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
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
              <li class="breadcrumb-item active">Accounting / Finance</li>
            </ol></nav>
          </div>
          <ul class="navbar-nav flex-row align-items-center ms-auto">
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
              <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online">
                  <img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle"/>
                </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3"><div class="avatar avatar-online">
                      <img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle"/>
                    </div></div>
                    <div class="flex-grow-1">
                      <h6 class="mb-0"><?= htmlspecialchars($_SESSION['taascor_employee_full_name'] ?? '') ?></h6>
                      <small class="text-muted"><?= htmlspecialchars($_SESSION['taascor_access_description'] ?? '') ?></small>
                    </div>
                  </div>
                </a></li>
                <li><div class="dropdown-divider my-1"></div></li>
                <li><a class="dropdown-item" href="/<?= $pathParts[6] ?>/login/logout.php">
                  <i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span>
                </a></li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

          <ul class="nav nav-tabs mb-4" id="accTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabRemittance">
              <i class="bx bx-building-house me-1"></i>Gov't Remittances</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAnnual">
              <i class="bx bx-calendar me-1"></i>Annual Summary</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabEmployee">
              <i class="bx bx-user me-1"></i>Employee Annual (BIR 2316)</a></li>
          </ul>

          <div class="tab-content">

            <!-- TAB 1: Government Remittances -->
            <div class="tab-pane fade show active" id="tabRemittance">
              <div class="card mb-3"><div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-3"><label class="form-label">Client</label>
                    <select id="rem_client" class="form-select select2"><option value="">All Clients</option></select>
                  </div>
                  <div class="col-md-3"><label class="form-label">Pay Day</label>
                    <select id="rem_payday" class="form-select select2"><option value="">All Pay Days</option></select>
                  </div>
                  <div class="col-md-2">
                    <button id="btnRemSearch" class="btn btn-primary w-100"><i class="bx bx-search me-1"></i>Search</button>
                  </div>
                </div>
              </div></div>
              <div class="card"><div class="card-body">
                <div class="table-responsive">
                  <table id="remTable" class="table table-bordered table-hover w-100"></table>
                </div>
              </div></div>
            </div>

            <!-- TAB 2: Annual Summary -->
            <div class="tab-pane fade" id="tabAnnual">
              <div class="card mb-3"><div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-3"><label class="form-label">Client</label>
                    <select id="ann_client" class="form-select select2"><option value="">All Clients</option></select>
                  </div>
                  <div class="col-md-2"><label class="form-label">Year</label>
                    <select id="ann_year" class="form-select select2"><option value="">All Years</option></select>
                  </div>
                  <div class="col-md-2">
                    <button id="btnAnnSearch" class="btn btn-primary w-100"><i class="bx bx-search me-1"></i>Search</button>
                  </div>
                </div>
              </div></div>
              <div class="card"><div class="card-body">
                <div class="table-responsive">
                  <table id="annTable" class="table table-bordered table-hover w-100"></table>
                </div>
              </div></div>
            </div>

            <!-- TAB 3: Employee Annual (BIR 2316) -->
            <div class="tab-pane fade" id="tabEmployee">
              <div class="card mb-3"><div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-3"><label class="form-label">Client</label>
                    <select id="emp_client" class="form-select select2"><option value="">All Clients</option></select>
                  </div>
                  <div class="col-md-2"><label class="form-label">Year</label>
                    <select id="emp_year" class="form-select select2"><option value="">All Years</option></select>
                  </div>
                  <div class="col-md-2">
                    <button id="btnEmpSearch" class="btn btn-primary w-100"><i class="bx bx-search me-1"></i>Search</button>
                  </div>
                </div>
              </div></div>
              <div class="card"><div class="card-body">
                <div class="table-responsive">
                  <table id="empTable" class="table table-bordered table-hover w-100"></table>
                </div>
              </div></div>
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
<script src="../assets/vendor/libs/select2/select2.full.min.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<script src="../assets/vendor/libs/datatable-bs5/js/dataTables.js"></script>
<script src="../assets/vendor/libs/datatable-bs5/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.html5.min.js"></script>
<script src="../assets/js/main.js"></script>
<script src="js/accounting.js?v=20260531"></script>
<?php require('../includes/custom-footer.php'); ?>
</body>
</html>
