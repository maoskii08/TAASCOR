<?php
require_once('../includes/auth_guard.php');
auth_require_role([1, 2, 3]);
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr"
  data-theme="theme-default" data-assets-path="../assets/"
  data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>
  <title>Audit Log</title>
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
              <li class="breadcrumb-item active">Audit Log</li>
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

          <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabLog"><i class="bx bx-list-ul me-1"></i>Activity Log</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabSummary" id="tabSumLink"><i class="bx bx-user-check me-1"></i>Login Summary</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPayrollEvidence" id="tabPayrollEvidenceLink"><i class="bx bx-shield-quarter me-1"></i>Payroll Change Evidence</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabDtrEvidence" id="tabDtrEvidenceLink"><i class="bx bx-time-five me-1"></i>DTR Change Evidence</a></li>
          </ul>

          <div class="tab-content">

            <!-- Tab 1: Activity Log -->
            <div class="tab-pane fade show active" id="tabLog">
              <div class="card mb-3"><div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-md-3"><label class="form-label">Username</label>
                    <input type="text" id="f_username" class="form-control" placeholder="Search username..."></div>
                  <div class="col-md-2"><label class="form-label">Action</label>
                    <select id="f_action" class="form-select"><option value="">All Actions</option></select></div>
                  <div class="col-md-2"><label class="form-label">Date From</label>
                    <input type="date" id="f_date_from" class="form-control"></div>
                  <div class="col-md-2"><label class="form-label">Date To</label>
                    <input type="date" id="f_date_to" class="form-control"></div>
                  <div class="col-md-2">
                    <button id="btnSearch" class="btn btn-primary w-100"><i class="bx bx-search me-1"></i>Search</button>
                  </div>
                </div>
              </div></div>
              <div class="card"><div class="card-body">
                <div class="table-responsive">
                  <table id="logTable" class="table table-bordered table-hover w-100"></table>
                </div>
              </div></div>
            </div>

            <!-- Tab 2: Login Summary -->
            <div class="tab-pane fade" id="tabSummary">
              <div class="card"><div class="card-body">
                <div class="table-responsive">
                  <table id="sumTable" class="table table-bordered table-hover w-100"></table>
                </div>
              </div></div>
            </div>

            <div class="tab-pane fade" id="tabPayrollEvidence">
              <div class="alert alert-primary" role="note">
                Every Additional or Deduction change shown here was committed with its payroll recalculation and exact row evidence in one database transaction.
              </div>
              <div class="card mb-3"><div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-lg-3"><label class="form-label" for="pa_event_uid">Audit Event ID</label>
                    <input type="text" id="pa_event_uid" class="form-control" maxlength="24" placeholder="24-character event ID"></div>
                  <div class="col-lg-3"><label class="form-label" for="pa_client_name">Client</label>
                    <input type="text" id="pa_client_name" class="form-control" maxlength="190" placeholder="Client name"></div>
                  <div class="col-lg-2"><label class="form-label" for="pa_pay_day">Pay Day</label>
                    <input type="date" id="pa_pay_day" class="form-control"></div>
                  <div class="col-lg-2"><label class="form-label" for="pa_kind">Kind</label>
                    <select id="pa_kind" class="form-select"><option value="">All</option><option value="addition">Addition</option><option value="deduction">Deduction</option></select></div>
                  <div class="col-lg-2"><button id="btnPayrollEvidenceSearch" class="btn btn-primary w-100"><i class="bx bx-search me-1"></i>Search</button></div>
                </div>
              </div></div>
              <div id="payrollEvidenceState" class="alert alert-secondary" role="status">Choose filters or search to review payroll change evidence.</div>
              <div class="card"><div class="card-body">
                <div class="table-responsive">
                  <table id="payrollEvidenceTable" class="table table-bordered table-hover w-100"></table>
                </div>
              </div></div>
            </div>

            <div class="tab-pane fade" id="tabDtrEvidence">
              <div class="alert alert-primary" role="note">
                Review immutable evidence for governed DTR edits, benefit changes, and deletions. Open an Event ID to verify the canonical before, after, and exact-scope SHA-256 hashes on the server.
              </div>
              <div class="card mb-3"><div class="card-body">
                <div class="row g-3 align-items-end">
                  <div class="col-xl-3 col-md-6"><label class="form-label" for="dtr_event_uid">Audit Event ID</label>
                    <input type="text" id="dtr_event_uid" class="form-control" maxlength="37" placeholder="DTRM- plus 32 characters"></div>
                  <div class="col-xl-3 col-md-6"><label class="form-label" for="dtr_client_name">Client</label>
                    <input type="text" id="dtr_client_name" class="form-control" maxlength="190" placeholder="Client name"></div>
                  <div class="col-xl-2 col-md-4"><label class="form-label" for="dtr_pay_day">Pay Day</label>
                    <input type="date" id="dtr_pay_day" class="form-control"></div>
                  <div class="col-xl-2 col-md-4"><label class="form-label" for="dtr_operation">Operation</label>
                    <select id="dtr_operation" class="form-select">
                      <option value="">All operations</option>
                      <option value="EDIT">Manual edit</option>
                      <option value="BENEFIT">Benefit removal</option>
                      <option value="DELETE_EMPLOYEE">Employee deletion</option>
                      <option value="DELETE_BULK">Scoped bulk deletion</option>
                    </select></div>
                  <div class="col-xl-2 col-md-4"><button id="btnDtrEvidenceSearch" class="btn btn-primary w-100"><i class="bx bx-search me-1"></i>Search</button></div>
                  <div class="col-xl-3 col-md-6"><label class="form-label" for="dtr_employee_id">Employee ID</label>
                    <input type="text" inputmode="numeric" id="dtr_employee_id" class="form-control" maxlength="19" placeholder="Exact employee ID"></div>
                  <div class="col-xl-2 col-md-4"><label class="form-label" for="dtr_scope_kind">Scope</label>
                    <select id="dtr_scope_kind" class="form-select">
                      <option value="">All scopes</option>
                      <option value="EMPLOYEE">Employee</option>
                      <option value="PAYROLL_SCOPE">Payroll scope</option>
                    </select></div>
                  <div class="col-xl-2 col-md-4"><label class="form-label" for="dtr_page_size">Rows per page</label>
                    <select id="dtr_page_size" class="form-select"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></div>
                  <div class="col-xl-5 col-md-8">
                    <div class="d-flex justify-content-md-end gap-2">
                      <button type="button" id="btnDtrEvidencePrevious" class="btn btn-outline-secondary" disabled><i class="bx bx-chevron-left me-1"></i>Previous</button>
                      <button type="button" id="btnDtrEvidenceNext" class="btn btn-outline-secondary" disabled>Next<i class="bx bx-chevron-right ms-1"></i></button>
                    </div>
                  </div>
                </div>
              </div></div>
              <div id="dtrEvidenceState" class="alert alert-secondary" role="status" aria-live="polite">Choose filters or search to review DTR change evidence.</div>
              <div class="card"><div class="card-body">
                <div class="table-responsive">
                  <table id="dtrEvidenceTable" class="table table-bordered table-hover w-100"></table>
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

<div class="modal fade" id="payrollEvidenceModal" tabindex="-1" aria-labelledby="payrollEvidenceModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div><small class="text-uppercase text-muted">Reconstructable payroll evidence</small><h5 class="modal-title" id="payrollEvidenceModalTitle">Payroll change audit event</h5></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="payrollEvidenceDetailState" class="alert alert-secondary" role="status">Loading evidence…</div>
        <div id="payrollEvidenceDetail"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="dtrEvidenceModal" tabindex="-1" aria-labelledby="dtrEvidenceModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div><small class="text-uppercase text-muted">Reconstructable DTR evidence</small><h5 class="modal-title" id="dtrEvidenceModalTitle">DTR change audit event</h5></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="dtrEvidenceDetailState" class="alert alert-secondary" role="status" aria-live="polite">Loading and verifying evidence…</div>
        <div id="dtrEvidenceDetail"></div>
      </div>
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
<script src="js/audit-log.js?v=20260727b"></script>
<?php require('../includes/custom-footer.php'); ?>
</body>
</html>
