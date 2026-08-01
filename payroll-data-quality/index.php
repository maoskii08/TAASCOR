<?php
require_once('../includes/auth_guard.php');
auth_require_role([1, 3]);
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>Payroll Data Quality</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="css/payroll-data-quality.css?v=20260727b" />
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require('../includes/nav-bar.php'); ?>
      <div class="home">
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)"><i class="bx bx-menu bx-md"></i></a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Payroll Data Quality</li></ol></nav>
            </div>
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online"><img src="../assets/img/avatars/user-icon.png" alt="" class="w-px-40 h-auto rounded-circle" /></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li class="px-3 py-2">
                    <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['taascor_employee_full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h6>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['taascor_access_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                  </li>
                  <li><div class="dropdown-divider my-1"></div></li>
                  <li><a class="dropdown-item" href="../login/logout.php"><i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span></a></li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <header class="dq-intro">
              <div>
                <h1>Payroll Data Quality</h1>
                <p>Trace validation findings to the affected records, open the owning correction workspace, and rerun the checks before payroll release.</p>
              </div>
              <span class="dq-scope-badge"><i class="bx bx-shield-quarter" aria-hidden="true"></i> Client-scoped validation</span>
            </header>

            <div class="dq-status-band" aria-label="Payroll Data Quality status">
              <div class="dq-status-item"><span>Review mode</span><strong>Actionable findings</strong><small>Validation remains read-only</small></div>
              <div class="dq-status-item"><span>Checks</span><strong id="dqCheckCount">-</strong><small>Current validation rules</small></div>
              <div class="dq-status-item"><span>Open findings</span><strong id="dqOpenFindings">-</strong><small>Affected records across rules</small></div>
              <div class="dq-status-item"><span>Corrections</span><strong>Source modules</strong><small>No aggregate editing</small></div>
            </div>

            <section class="dq-panel" aria-labelledby="dqFindingsTitle">
              <div class="dq-panel-header">
                <div>
                  <h2 id="dqFindingsTitle">Findings requiring review</h2>
                  <p>Select <strong>Review issues</strong> to see affected records and the approved correction path.</p>
                </div>
                <small class="text-muted" id="dqGeneratedAt">Loading...</small>
              </div>
              <div class="dq-panel-body">
                <div id="dqAlert" class="alert alert-danger" style="display:none"></div>
                <div class="table-responsive">
                  <table class="table align-middle dq-findings-table" id="dqTable">
                    <thead><tr><th>Finding</th><th>Severity</th><th class="text-end">Count</th><th>Status</th><th>Why it matters</th><th class="text-end">Action</th></tr></thead>
                    <tbody><tr><td colspan="6" class="text-center text-muted py-5">Loading validation results...</td></tr></tbody>
                  </table>
                </div>
              </div>
            </section>
          </div>
          <div class="content-backdrop fade"></div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
  </div>

  <div class="dq-drawer-backdrop" id="dqDrawerBackdrop" hidden></div>
  <aside class="dq-drawer" id="dqDrawer" role="dialog" aria-modal="true" aria-labelledby="dqDrawerTitle" aria-describedby="dqDrawerDescription" hidden>
    <header class="dq-drawer-header">
      <div>
        <span class="dq-drawer-kicker" id="dqDrawerSeverity">Finding review</span>
        <h2 id="dqDrawerTitle">Issue details</h2>
        <p id="dqDrawerDescription">Review the affected records and correction guidance.</p>
      </div>
      <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" id="dqDrawerClose" aria-label="Close issue details">
        <i class="bx bx-x bx-sm" aria-hidden="true"></i>
      </button>
    </header>
    <div class="dq-drawer-toolbar">
      <label for="dqDrawerSearch">Find an employee or client</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bx bx-search" aria-hidden="true"></i></span>
        <input type="search" class="form-control" id="dqDrawerSearch" placeholder="Search loaded records" autocomplete="off" />
      </div>
      <p id="dqDrawerCount" aria-live="polite"></p>
    </div>
    <div class="dq-drawer-body" id="dqDrawerBody">
      <div class="dq-drawer-loading"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i><span>Loading affected records...</span></div>
    </div>
    <footer class="dq-drawer-footer">
      <div class="dq-drawer-owner"><span>Correction owner</span><strong id="dqDrawerOwner">-</strong></div>
      <div class="dq-drawer-actions">
        <button type="button" class="btn btn-outline-secondary" id="dqDrawerRerun"><i class="bx bx-refresh" aria-hidden="true"></i> Rerun checks</button>
        <a class="btn btn-primary" id="dqDrawerPrimaryAction" href="#"><span>Open correction workspace</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
      </div>
    </footer>
  </aside>

  <?php require('../includes/footer.php'); ?>
  <script src="js/index-01.js?v=20260727d"></script>
  <?php require('../includes/custom-footer.php'); ?>
</body>
</html>
