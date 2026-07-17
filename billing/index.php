<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr"
  data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Billing</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2.min.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2-bootstrap-5-theme.min.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/css/datatables.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-buttons-bs5/buttons.bootstrap5.css" />
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <?php require('../includes/nav-bar.php') ?>

    <div class="home">
      <!-- Top navbar -->
      <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
          <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="bx bx-menu bx-md"></i>
          </a>
        </div>
        <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
          <div class="navbar-nav align-items-center">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active">Billing</li>
              </ol>
            </nav>
          </div>
          <ul class="navbar-nav flex-row align-items-center ms-auto">
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
                        <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['taascor_employee_full_name'] ?? ''); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['taascor_access_description'] ?? ''); ?></small>
                      </div>
                    </div>
                  </a>
                </li>
                <li><div class="dropdown-divider my-1"></div></li>
                <li>
                  <a class="dropdown-item" href="/<?php echo $pathParts[6]; ?>/login/logout.php">
                    <i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span>
                  </a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>
      <!-- /Top navbar -->

      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

          <!-- Filters -->
          <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bx bx-receipt me-2"></i>Billing Summary</h5></div>
            <div class="card-body">
              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label class="form-label">Client</label>
                  <select id="filter_client" class="form-select select2">
                    <option value="">All Clients</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Pay Day</label>
                  <select id="filter_payday" class="form-select select2">
                    <option value="">All Pay Days</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <button id="btnSearch" class="btn btn-primary w-100">
                    <i class="bx bx-search me-1"></i> Search
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Billing table -->
          <div class="card">
            <div class="card-body">
              <div class="table-responsive">
                <table id="billingTable" class="table table-bordered table-hover w-100"></table>
              </div>
            </div>
          </div>

        </div><!-- /container -->
      </div><!-- /content-wrapper -->

      <!-- Detail Modal -->
      <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                Employee Billing Detail — <span id="modalClient" class="text-primary"></span>
                &nbsp;|&nbsp; Pay Day: <span id="modalPayDay"></span>
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="table-responsive">
                <table id="detailTable" class="table table-sm table-bordered w-100"></table>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- /Detail Modal -->

      <div class="content-backdrop fade"></div>
    </div><!-- /home -->
  </div><!-- /layout-container -->
</div><!-- /layout-wrapper -->

<!-- Core JS -->
<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/libs/popper/popper.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../assets/vendor/libs/select2/select2.full.min.js"></script>
<script src="../assets/vendor/js/menu.js"></script>
<!-- Datatables -->
<script src="../assets/vendor/libs/datatable-bs5/js/dataTables.js"></script>
<script src="../assets/vendor/libs/datatable-bs5/js/dataTables.bootstrap5.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/dataTables.buttons.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.2/js/buttons.html5.min.js"></script>
<script src="../assets/js/main.js"></script>
<!-- Select2 init -->
<script>
  $(document).ready(function(){
    $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
  });
</script>
<script src="js/billing.js?v=20260531"></script>
<?php require('../includes/custom-footer.php'); ?>
</body>
</html>
