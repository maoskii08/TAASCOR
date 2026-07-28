<?php
require_once('../includes/auth_guard.php');
auth_require_role([1, 2, 3]);
$canConfigureTemplates = auth_level() === 1;
$hostName = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
$hostName = explode(':', $hostName, 2)[0];
$localPreviewOverride = getenv('TAASCOR_ENABLE_LOCAL_PREVIEWS');
$enableLocalPreviewDiagnostics = $localPreviewOverride !== false
  ? filter_var($localPreviewOverride, FILTER_VALIDATE_BOOLEAN)
  : in_array($hostName, ['localhost', '127.0.0.1', '::1'], true);
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>DTR Format Engine</title>
  <meta name="description" content="" />

  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>

<body
  data-can-configure-templates="<?php echo $canConfigureTemplates ? '1' : '0'; ?>"
  data-can-approve-identities="<?php echo in_array(auth_level(), [1, 2], true) ? '1' : '0'; ?>"
  data-enable-local-previews="<?php echo $enableLocalPreviewDiagnostics ? '1' : '0'; ?>">
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require('../includes/nav-bar.php') ?>
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
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item active" aria-current="page">DTR Format Engine</li>
                  </ol>
                </nav>
              </div>
            </div>

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown me-3">
                <a class="nav-link dropdown-toggle hide-arrow position-relative" href="javascript:void(0);"
                  data-bs-toggle="dropdown" id="identityNotificationBell" aria-label="DTR employee notifications">
                  <i class="bx bx-bell bx-md"></i>
                  <span id="identityNotificationBadge"
                    class="badge rounded-pill bg-danger badge-notifications position-absolute top-0 start-100 translate-middle"
                    style="display:none">0</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end p-0" style="width:min(390px, 90vw)">
                  <li class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                    <strong>DTR employee alerts</strong>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="enableBrowserNotificationsBtn">
                      Enable desktop alerts
                    </button>
                  </li>
                  <li>
                    <div id="identityNotificationList" class="list-group list-group-flush" style="max-height:360px;overflow:auto">
                      <div class="px-3 py-4 text-center text-muted">No notifications loaded.</div>
                    </div>
                  </li>
                </ul>
              </li>
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
                          <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['taascor_employee_full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h6>
                          <small class="text-muted"><?php echo htmlspecialchars($_SESSION['taascor_access_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li><div class="dropdown-divider my-1"></div></li>
                  <li>
                    <a class="dropdown-item" href="../login/logout.php">
                      <i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <div class="row g-4 mb-4">
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <span class="fw-medium d-block mb-1">Scope</span>
                    <h5 class="card-title mb-0">Local Only</h5>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <span class="fw-medium d-block mb-1">Templates</span>
                    <h5 class="card-title mb-0" id="templateCount">-</h5>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <span class="fw-medium d-block mb-1">Upload Batches</span>
                    <h5 class="card-title mb-0" id="batchCount">-</h5>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <span class="fw-medium d-block mb-1">Payroll Handoff</span>
                    <h5 class="card-title mb-0" id="payrollHandoffStatus">Guarded</h5>
                  </div>
                </div>
              </div>
            </div>

            <div id="dtrEngineAlert" class="alert alert-danger" style="display:none"></div>

            <div class="card mb-4" id="real-dtr-upload">
              <div class="card-header">
                <h5 class="mb-1">Real DTR Upload</h5>
                <small class="text-muted">Upload any client DTR through an approved, versioned adapter. Files remain in governed staging until identity, validation, reconciliation, and payroll controls pass.</small>
              </div>
              <div class="card-body">
                <form id="realDtrUploadForm" enctype="multipart/form-data">
                  <div class="row g-3 align-items-end">
                    <div class="col-lg-3">
                      <label class="form-label" for="realDtrClientId">Client</label>
                      <select class="form-select" id="realDtrClientId" name="client_id" required>
                        <option value="">Select client</option>
                      </select>
                    </div>
                    <div class="col-lg-4">
                      <label class="form-label" for="realDtrAdapterProfileId">Approved format</label>
                      <select class="form-select" id="realDtrAdapterProfileId" name="adapter_profile_id" disabled>
                        <option value="">Select client first</option>
                      </select>
                      <small class="text-muted">Auto-detect is recommended; select a version only when formats are ambiguous.</small>
                    </div>
                    <div class="col-lg-5">
                      <label class="form-label" for="realDtrFile">DTR file</label>
                      <input class="form-control" type="file" id="realDtrFile" name="dtr_file" accept=".csv,.xlsx" required>
                    </div>
                    <div class="col-sm-4 col-lg-3">
                      <label class="form-label" for="realDtrPeriodStart">Period start</label>
                      <input class="form-control" type="date" id="realDtrPeriodStart" name="period_start" required>
                    </div>
                    <div class="col-sm-4 col-lg-3">
                      <label class="form-label" for="realDtrPeriodEnd">Period end</label>
                      <input class="form-control" type="date" id="realDtrPeriodEnd" name="period_end" required>
                    </div>
                    <div class="col-sm-4 col-lg-3">
                      <label class="form-label" for="realDtrPayDate">Pay date</label>
                      <input class="form-control" type="date" id="realDtrPayDate" name="pay_date" required>
                    </div>
                    <div class="col-lg-3 d-grid">
                      <button class="btn btn-primary" type="submit" id="stageRealDtrBtn">
                        <i class="bx bx-upload me-1"></i>Stage for review
                      </button>
                    </div>
                  </div>
                </form>
                <div class="alert alert-info mt-3 mb-0" id="realDtrUploadStatus" style="display:none"></div>
              </div>
            </div>

            <div class="card mb-4" id="adapter-registry">
              <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                  <h5 class="mb-1">Governed DTR Format Registry</h5>
                  <small class="text-muted">Create immutable adapter versions from client templates. Maker-checker approval is required before a format becomes available for real uploads.</small>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <button type="button" class="btn btn-sm btn-outline-primary" id="openDtrTemplateDrawerBtn"
                    data-bs-toggle="offcanvas" data-bs-target="#dtrTemplateDrawer"
                    aria-controls="dtrTemplateDrawer">
                    <i class="bx bx-table me-1"></i>
                    <?php echo $canConfigureTemplates ? 'Manage' : 'View'; ?> DTR templates
                  </button>
                  <span class="badge bg-label-primary">Admin controlled</span>
                </div>
              </div>
              <div class="card-body">
                <form id="adapterProfileForm" class="mb-4">
                  <div class="row g-3 align-items-end">
                    <div class="col-lg-3">
                      <label class="form-label" for="adapterTemplateId">Client template</label>
                      <select class="form-select" id="adapterTemplateId" name="template_id" required>
                        <option value="">Select template</option>
                      </select>
                    </div>
                    <div class="col-lg-2">
                      <label class="form-label" for="adapterKey">Adapter key</label>
                      <input class="form-control" id="adapterKey" name="adapter_key" maxlength="120" placeholder="CLIENT_DTR" required>
                    </div>
                    <div class="col-lg-2">
                      <label class="form-label" for="adapterVersion">Version</label>
                      <input class="form-control" id="adapterVersion" name="adapter_version" maxlength="80" placeholder="v1.0.0" required>
                    </div>
                    <div class="col-lg-3">
                      <label class="form-label" for="adapterDisplayName">Display name</label>
                      <input class="form-control" id="adapterDisplayName" name="display_name" maxlength="180" placeholder="Client Biometric Export" required>
                    </div>
                    <div class="col-lg-2">
                      <label class="form-label" for="adapterEffectiveFrom">Effective from</label>
                      <input class="form-control" type="date" id="adapterEffectiveFrom" name="effective_from" required>
                    </div>
                    <div class="col-lg-3">
                      <label class="form-label" for="adapterParserKey">Parser</label>
                      <select class="form-select" id="adapterParserKey" name="parser_key">
                        <option value="template_tabular_v1">Standard CSV/XLSX table</option>
                        <option value="period_summary_workbook_v1">Multi-sheet period summary</option>
                        <option value="fuji_payroll_summary_v1">Fuji payroll summary</option>
                      </select>
                    </div>
                    <div class="col-lg-3">
                      <label class="form-label" for="adapterIdentityPolicy">Employee ID policy</label>
                      <select class="form-select" id="adapterIdentityPolicy" name="identity_policy">
                        <option value="approved_mapping_required">Approved mapping required</option>
                        <option value="trusted_hris_identifier">Trusted HRIS identifier</option>
                      </select>
                    </div>
                    <div class="col-lg-4">
                      <small class="text-muted d-block">Use “trusted” only when the source contains governed HRIS IDs. Vendor IDs must require approved mappings.</small>
                    </div>
                    <div class="col-lg-2 d-grid">
                      <button class="btn btn-outline-primary" type="submit" id="createAdapterProfileBtn">Create draft</button>
                    </div>
                  </div>
                </form>

                <div class="row g-3 align-items-end mb-4">
                  <div class="col-lg-4">
                    <label class="form-label" for="adapterApprovalProfileId">Draft awaiting checker</label>
                    <select class="form-select" id="adapterApprovalProfileId">
                      <option value="">Select draft</option>
                    </select>
                  </div>
                  <div class="col-lg-6">
                    <label class="form-label" for="adapterApprovalReason">Approval reason and test evidence</label>
                    <input class="form-control" id="adapterApprovalReason" maxlength="1000" placeholder="Reviewed sample, mapping, identity policy, totals and row reconciliation">
                  </div>
                  <div class="col-lg-2 d-grid">
                    <button class="btn btn-success" type="button" id="approveAdapterProfileBtn">Approve</button>
                  </div>
                </div>

                <div class="alert alert-info py-2" id="adapterRegistryStatus" style="display:none"></div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle" id="adapterRegistryTable">
                    <thead>
                      <tr>
                        <th>Client</th>
                        <th>Format</th>
                        <th>Version</th>
                        <th>Parser</th>
                        <th>Employee ID policy</th>
                        <th>Effective</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr><td colspan="7" class="text-center text-muted">Loading adapter registry...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="card mb-4" id="smart-employee-resolution">
              <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                  <h5 class="mb-1">Smart Employee Alignment <span class="badge bg-label-primary ms-1">Step 1</span></h5>
                  <small class="text-muted">Automated candidate workbench: compare DTR identities with HRIS, select supported matches, then record one governed owner approval.</small>
                </div>
                <div class="d-flex gap-2">
                  <select class="form-select form-select-sm" id="smartBatchFilter" style="min-width:260px">
                    <option value="0">Select a staged batch</option>
                  </select>
                  <button type="button" class="btn btn-sm btn-primary" id="analyzeSmartCohortBtn">
                    <i class="bx bx-scan me-1"></i>Analyze batch
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div class="alert alert-info py-2" id="smartResolutionStatus">
                  The resolver runs in shadow mode. Nothing is mapped until an authorized identity owner explicitly approves selected proposals.
                </div>
                <div class="row g-3 mb-3">
                  <div class="col-sm-6 col-xl"><span class="text-muted d-block">Unique source employees</span><h6 id="smartSourceCount" class="mb-0">-</h6></div>
                  <div class="col-sm-6 col-xl"><span class="text-muted d-block">Approved mappings</span><h6 id="smartApprovedCount" class="mb-0">-</h6></div>
                  <div class="col-sm-6 col-xl"><span class="text-muted d-block">Safe shadow matches</span><h6 id="smartSafeCount" class="mb-0">-</h6></div>
                  <div class="col-sm-6 col-xl"><span class="text-muted d-block">Needs review</span><h6 id="smartReviewCount" class="mb-0">-</h6></div>
                  <div class="col-sm-6 col-xl"><span class="text-muted d-block">Hard blocked</span><h6 id="smartBlockCount" class="mb-0">-</h6></div>
                  <div class="col-sm-6 col-xl"><span class="text-muted d-block">Target collisions</span><h6 id="smartCollisionCount" class="mb-0">-</h6></div>
                </div>
                <div class="row g-2 align-items-end mb-3">
                  <div class="col-lg-7">
                    <label for="smartCohortApprovalReason" class="form-label">Owner approval reason</label>
                    <input type="text" class="form-control form-control-sm" id="smartCohortApprovalReason"
                      maxlength="500" placeholder="Example: Reviewed client, payroll period, identity evidence, and collisions">
                  </div>
                  <div class="col-lg-3">
                    <label for="smartResolutionFilter" class="form-label">View</label>
                    <select class="form-select form-select-sm" id="smartResolutionFilter">
                      <option value="all">All decisions</option>
                      <option value="approved">Approved mappings</option>
                      <option value="auto_eligible_shadow">Safe shadow matches</option>
                      <option value="review">Needs review</option>
                      <option value="block">Hard blocked</option>
                    </select>
                  </div>
                  <div class="col-lg-2 d-grid">
                    <button type="button" class="btn btn-sm btn-success" id="approveSmartCohortBtn" disabled>
                      <i class="bx bx-check-shield me-1"></i>Approve selected
                    </button>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle" id="smartResolutionTable">
                    <thead>
                      <tr>
                        <th class="text-center" style="width:48px">
                          <input type="checkbox" class="form-check-input" id="smartResolutionSelectAll"
                            aria-label="Select all eligible mappings in the current view" disabled>
                        </th>
                        <th>DTR employee</th>
                        <th>Best HRIS candidate</th>
                        <th>Evidence</th>
                        <th>Decision</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr><td colspan="5" class="text-center text-muted">Select and analyze a staged batch.</td></tr>
                    </tbody>
                  </table>
                </div>
                <small class="text-muted d-block" id="smartResolutionTableSummary"></small>
                <small class="text-muted">Only rows with a proposed HRIS employee can be selected. Blocked rows remain unavailable and must be corrected under Employee Identity Exceptions.</small>
                <div class="border rounded p-3 mt-3" id="payrollImportRunPanel">
                  <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <div>
                      <h6 class="mb-1">Guarded payroll import run</h6>
                      <small class="text-muted">Creates one immutable run-scoped DTR snapshot only after every staged identity and row passes. Legacy payroll remains untouched.</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="createPayrollImportRunBtn" disabled>
                      <i class="bx bx-layer-plus me-1"></i>Create canonical snapshot
                    </button>
                  </div>
                  <div class="row g-3 mt-1">
                    <div class="col-sm-6 col-xl-3"><span class="text-muted d-block">Run</span><strong id="payrollImportRunUid">Not created</strong></div>
                    <div class="col-sm-6 col-xl-3"><span class="text-muted d-block">State</span><strong id="payrollImportRunState">-</strong></div>
                    <div class="col-sm-6 col-xl-3"><span class="text-muted d-block">Rules</span><strong id="payrollImportRulesState">-</strong></div>
                    <div class="col-sm-6 col-xl-3"><span class="text-muted d-block">Release gate</span><strong id="payrollImportReleaseState">-</strong></div>
                  </div>
                  <div class="alert alert-warning py-2 mt-3 mb-0" id="payrollImportRunStatus">
                    Resolve every identity first. A versioned client ruleset and exact approved-reference reconciliation are still required before approval or payslip release.
                  </div>
                </div>
              </div>
            </div>

            <div class="card mb-4" id="employee-identity-review">
              <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                  <h5 class="mb-1">Employee Identity Exceptions <span class="badge bg-label-warning ms-1">Step 2</span></h5>
                  <small class="text-muted">Manual exception queue for records that still need master-data correction, exclusion evidence, or an individual mapping decision.</small>
                </div>
                <div class="d-flex gap-2">
                  <select class="form-select form-select-sm" id="identityBatchFilter" style="min-width:220px">
                    <option value="0">Select a staged batch</option>
                  </select>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshIdentityExceptionsBtn">
                    <i class="bx bx-refresh me-1"></i>Refresh
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-primary" id="exportIdentityDecisionPacketBtn" disabled>
                    <i class="bx bx-download me-1"></i>Export HR decision packet
                  </button>
                  <button type="button" class="btn btn-sm btn-primary" id="syncIdentityBatchBtn">
                    <i class="bx bx-shield-quarter me-1"></i>Validate selected batch
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div class="row g-3 mb-3">
                  <div class="col-sm-3"><span class="text-muted d-block">Payroll gate</span><h6 id="identityGateStatus" class="mb-0">Checking...</h6></div>
                  <div class="col-sm-3"><span class="text-muted d-block">Missing / no confident HRIS</span><h6 id="identityMissingCount" class="mb-0">0</h6></div>
                  <div class="col-sm-3"><span class="text-muted d-block">Mapping review</span><h6 id="identityReviewCount" class="mb-0">0</h6></div>
                  <div class="col-sm-3"><span class="text-muted d-block">Status/client conflict</span><h6 id="identityConflictCount" class="mb-0">0</h6></div>
                </div>
                <div id="identityGateAlert" class="alert alert-warning py-2" style="display:none"></div>
                <div class="row g-2 mb-3">
                  <div class="col-lg-8">
                    <input type="search" class="form-control form-control-sm" id="identityExceptionSearch"
                      placeholder="Search source ID, employee name, batch, or suggested HRIS match">
                  </div>
                  <div class="col-lg-4">
                    <select class="form-select form-select-sm" id="identityExceptionTypeFilter">
                      <option value="all">All exception types</option>
                      <option value="MISSING_HRIS_EMPLOYEE">Missing HRIS employee</option>
                      <option value="EMPLOYEE_MAPPING_REVIEW">Mapping review</option>
                      <option value="HRIS_STATUS_CONFLICT">Status/client conflict</option>
                    </select>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle" id="identityExceptionsTable">
                    <thead>
                      <tr>
                        <th>Batch / row</th>
                        <th>DTR employee</th>
                        <th>Exception</th>
                        <th>Suggested HRIS match</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr><td colspan="6" class="text-center text-muted">No employee identity exceptions loaded.</td></tr>
                    </tbody>
                  </table>
                </div>
                <small class="text-muted" id="identityExceptionTableSummary"></small>
              </div>
            </div>

            <div class="card mb-4" id="payroll-population-review">
              <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div>
                  <h5 class="mb-1">DTR and Payslip Population Review</h5>
                  <small class="text-muted">Employees found in the expected payslip set but absent from this DTR stay blocked until an owner records the supported disposition.</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPopulationExceptionsBtn">
                    <i class="bx bx-refresh me-1"></i>Refresh
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-warning" id="notifyPopulationOwnersBtn">
                    <i class="bx bx-bell me-1"></i>Notify HR &amp; Payroll
                  </button>
                </div>
              </div>
              <div class="card-body">
                <div class="row g-3 mb-3">
                  <div class="col-sm-4">
                    <span class="text-muted d-block">Population gate</span>
                    <h6 id="populationGateStatus" class="mb-0">Select a staged batch</h6>
                  </div>
                  <div class="col-sm-4">
                    <span class="text-muted d-block">Open P0 exceptions</span>
                    <h6 id="populationOpenCount" class="mb-0">0</h6>
                  </div>
                  <div class="col-sm-4">
                    <span class="text-muted d-block">Resolved with evidence</span>
                    <h6 id="populationResolvedCount" class="mb-0">0</h6>
                  </div>
                </div>
                <div class="alert alert-info py-2" id="populationGateAlert">
                  Select a staged client batch above to review payslip-only employees. These records do not create or alter employee, DTR, payroll, loan, deduction, or payslip data.
                </div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle" id="populationExceptionsTable">
                    <thead>
                      <tr>
                        <th>Payslip employee</th>
                        <th>Matched HRIS employee</th>
                        <th>Reference evidence</th>
                        <th>Status / disposition</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr><td colspan="5" class="text-center text-muted">No population exceptions loaded.</td></tr>
                    </tbody>
                  </table>
                </div>
                <small class="text-muted" id="populationExceptionTableSummary"></small>
              </div>
            </div>

            <div class="row g-4">
              <div class="col-12">
                <?php if ($enableLocalPreviewDiagnostics): ?>
                <div class="card mb-4">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Synthetic Validation Preview</h5>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="clearSyntheticBtn">
                      Clear Synthetic
                    </button>
                  </div>
                  <div class="card-body">
                    <form id="syntheticUploadForm" enctype="multipart/form-data">
                      <div class="row g-3">
                        <div class="col-md-4">
                          <label for="syntheticTemplateId" class="form-label">Template</label>
                          <select class="form-select" id="syntheticTemplateId" name="template_id" required>
                            <option value="">Select template</option>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <label for="syntheticFile" class="form-label">Synthetic File</label>
                          <input type="file" class="form-control" id="syntheticFile" name="synthetic_file" accept=".csv,.xlsx" required />
                        </div>
                        <div class="col-md-2">
                          <label for="periodStart" class="form-label">Period Start</label>
                          <input type="date" class="form-control" id="periodStart" name="period_start" />
                        </div>
                        <div class="col-md-2">
                          <label for="periodEnd" class="form-label">Period End</label>
                          <input type="date" class="form-control" id="periodEnd" name="period_end" />
                        </div>
                      </div>
                      <div class="mt-3">
                        <button type="submit" class="btn btn-primary" id="uploadSyntheticBtn">
                          <i class="bx bx-upload"></i>
                          Preview
                        </button>
                      </div>
                    </form>

                    <div class="row g-3 mt-3" id="syntheticSummary" style="display:none">
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Rows</span>
                        <h6 class="mb-0" id="syntheticRows">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Valid</span>
                        <h6 class="mb-0" id="syntheticValidRows">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Issues</span>
                        <h6 class="mb-0" id="syntheticErrorRows">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Batch</span>
                        <h6 class="mb-0" id="syntheticBatch">-</h6>
                      </div>
                    </div>

                    <div class="table-responsive mt-3">
                      <table class="table table-sm table-bordered align-middle" id="previewTable">
                        <thead>
                          <tr>
                            <th>Row</th>
                            <th>Employee ID</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                            <th>Validation</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="7" class="text-center text-muted">No synthetic preview loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div class="card mb-4">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Real Sample Adapter Preview</h5>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="profileRealSamplesBtn">
                        Profile
                      </button>
                      <button type="button" class="btn btn-sm btn-primary" id="runRealSampleAdaptersBtn">
                        Run Adapters
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-warning" id="clearRealSampleAdaptersBtn">
                        Clear
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive mb-3">
                      <table class="table table-sm table-bordered align-middle" id="realSampleProfileTable">
                        <thead>
                          <tr>
                            <th>Workbook</th>
                            <th>Sheet</th>
                            <th>Headers</th>
                            <th>Identifier</th>
                            <th>Mode</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="5" class="text-center text-muted">No real sample profile loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered align-middle" id="realSampleAdapterTable">
                        <thead>
                          <tr>
                            <th>Workbook</th>
                            <th>Batch</th>
                            <th>Rows</th>
                            <th>Valid</th>
                            <th>Issues</th>
                            <th>Hours</th>
                            <th>Diagnostics</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="7" class="text-center text-muted">No real sample adapter run loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div class="card mb-4">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Adapter Approval Workflow</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshAdapterApprovalBtn">
                      <i class="bx bx-refresh"></i>
                    </button>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive mb-3">
                      <table class="table table-sm table-bordered align-middle" id="adapterApprovalTable">
                        <thead>
                          <tr>
                            <th>Profile</th>
                            <th>Workbook</th>
                            <th>Status</th>
                            <th>Scope</th>
                            <th>Preview Flags</th>
                            <th>Payroll Handoff</th>
                            <th>Mapping Gaps</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="8" class="text-center text-muted">No adapter approval workflow loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>

                    <form id="adapterApprovalForm" autocomplete="off">
                      <input type="hidden" id="approvalProfileKey" name="profile_key" value="" />
                      <div class="row g-3">
                        <div class="col-md-4">
                          <label for="approvalProfileName" class="form-label">Profile</label>
                          <input type="text" class="form-control" id="approvalProfileName" readonly />
                        </div>
                        <div class="col-md-4">
                          <label for="approvalStatus" class="form-label">Approval Status</label>
                          <select class="form-select" id="approvalStatus" name="approval_status">
                            <option value="draft">Draft</option>
                            <option value="under_review">Under Review</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="needs_owner_mapping">Needs Owner Mapping</option>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <label for="approvalScope" class="form-label">Approval Scope</label>
                          <select class="form-select" id="approvalScope" name="approval_scope">
                            <option value="preview_only">Preview Only</option>
                            <option value="staging_only">Staging Only</option>
                            <option value="blocked">Blocked</option>
                          </select>
                        </div>
                      </div>

                      <div class="row g-3 mt-1">
                        <div class="col-md-4">
                          <label for="approvalGateStatus" class="form-label">Gate</label>
                          <input type="text" class="form-control" id="approvalGateStatus" readonly />
                        </div>
                        <div class="col-md-4">
                          <label for="payrollHandoffBlocked" class="form-label">Payroll Handoff</label>
                          <input type="text" class="form-control" id="payrollHandoffBlocked" readonly />
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                          <div class="form-check mb-2">
                            <input class="form-check-input approval-check" type="checkbox" id="riskAccepted" name="risk_accepted" value="1" />
                            <label class="form-check-label" for="riskAccepted">Risk Accepted For Selected Scope</label>
                          </div>
                        </div>
                      </div>

                      <div class="row g-3 mt-1">
                        <div class="col-md-4">
                          <div class="form-check">
                            <input class="form-check-input approval-check" type="checkbox" id="employeeMatchingApproved" name="employee_matching_approved" value="1" />
                            <label class="form-check-label" for="employeeMatchingApproved">Employee Matching</label>
                          </div>
                          <div class="form-check">
                            <input class="form-check-input approval-check" type="checkbox" id="statusDictionaryApproved" name="status_dictionary_approved" value="1" />
                            <label class="form-check-label" for="statusDictionaryApproved">Status Dictionary</label>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-check">
                            <input class="form-check-input approval-check" type="checkbox" id="clientSiteBindingApproved" name="client_site_binding_approved" value="1" />
                            <label class="form-check-label" for="clientSiteBindingApproved">Client/Site Binding</label>
                          </div>
                          <div class="form-check">
                            <input class="form-check-input approval-check" type="checkbox" id="payPeriodExtractionApproved" name="pay_period_extraction_approved" value="1" />
                            <label class="form-check-label" for="payPeriodExtractionApproved">Pay Period Extraction</label>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-check">
                            <input class="form-check-input approval-check" type="checkbox" id="suspiciousPreviewReviewed" name="suspicious_preview_reviewed" value="1" />
                            <label class="form-check-label" for="suspiciousPreviewReviewed">Suspicious Preview Reviewed</label>
                          </div>
                          <div class="form-check">
                            <input class="form-check-input approval-check" type="checkbox" id="ownerApprovalCaptured" name="owner_approval_captured" value="1" />
                            <label class="form-check-label" for="ownerApprovalCaptured">Owner Approval Captured</label>
                          </div>
                        </div>
                      </div>

                      <div class="mt-3">
                        <label for="approvalNotes" class="form-label">Approval Notes</label>
                        <textarea class="form-control" id="approvalNotes" name="approval_notes" rows="3"></textarea>
                      </div>

                      <div class="mt-3">
                        <label for="mappingGaps" class="form-label">Mapping Gaps</label>
                        <textarea class="form-control" id="mappingGaps" name="mapping_gaps" rows="3"></textarea>
                      </div>

                      <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle" id="approvalChecklistTable">
                          <thead>
                            <tr>
                              <th>Checklist</th>
                              <th>Required Field</th>
                              <th>Status</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr><td colspan="3" class="text-center text-muted">Select a profile.</td></tr>
                          </tbody>
                        </table>
                      </div>

                      <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary" id="saveAdapterApprovalBtn">
                          <i class="bx bx-save"></i>
                          Save Review
                        </button>
                      </div>
                    </form>
                  </div>
                </div>

                <div class="card mb-4">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">E2E Local Preview Workflow</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPreviewWorkflowBtn">
                      <i class="bx bx-refresh"></i>
                    </button>
                  </div>
                  <div class="card-body">
                    <div class="row g-3 mb-3">
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Intake Rows</span>
                        <h6 class="mb-0" id="previewWorkflowRows">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Normalized</span>
                        <h6 class="mb-0" id="previewWorkflowNormalized">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Timekeeping</span>
                        <h6 class="mb-0" id="previewWorkflowTimekeeping">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Payroll Handoff</span>
                        <h6 class="mb-0" id="previewWorkflowHandoff">Blocked</h6>
                      </div>
                    </div>
                    <div class="alert alert-warning mb-3">
                      Preview-only values are not payroll-approved. Canonical DTR writes, payroll writes, payroll generation, and payroll handoff are blocked.
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered align-middle" id="previewWorkflowTable">
                        <thead>
                          <tr>
                            <th>Step</th>
                            <th>Status</th>
                            <th>Detail</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="3" class="text-center text-muted">No workflow preview loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div class="card mb-4">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Payroll Basis Preview</h5>
                    <div class="d-flex gap-2">
                      <button type="button" class="btn btn-sm btn-primary" id="runPayrollBasisPreviewBtn">
                        Build Preview
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPayrollBasisPreviewBtn">
                        <i class="bx bx-refresh"></i>
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="alert alert-warning mb-3">
                      Payroll basis preview is local-only. COXON and DELTA preview rows may be grouped for owner review; CYA remains blocked. Payroll handoff, payroll writes, payroll generation, and payroll amount calculation are disabled.
                    </div>
                    <div class="row g-3 mb-3">
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Headers</span>
                        <h6 class="mb-0" id="payrollBasisHeaders">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Rows</span>
                        <h6 class="mb-0" id="payrollBasisRows">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Preview Hours</span>
                        <h6 class="mb-0" id="payrollBasisHours">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Handoff</span>
                        <h6 class="mb-0" id="payrollBasisHandoff">Blocked</h6>
                      </div>
                    </div>

                    <div class="table-responsive mb-3">
                      <table class="table table-sm table-bordered align-middle" id="payrollBasisHeaderTable">
                        <thead>
                          <tr>
                            <th>Profile</th>
                            <th>Client/Site</th>
                            <th>Pay Period</th>
                            <th>Rows</th>
                            <th>Hours</th>
                            <th>Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="6" class="text-center text-muted">No payroll basis preview loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>

                    <div class="table-responsive">
                      <table class="table table-sm table-bordered align-middle" id="payrollBasisRowTable">
                        <thead>
                          <tr>
                            <th>Profile</th>
                            <th>Employee</th>
                            <th>Client/Site</th>
                            <th>Date</th>
                            <th>Hours</th>
                            <th>Eligibility</th>
                            <th>Source</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="7" class="text-center text-muted">No payroll basis preview rows loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <div class="card">
                  <div class="card-header">
                    <h5 class="mb-0">Upload Staging Status</h5>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered align-middle" id="batchesTable">
                        <thead>
                          <tr>
                            <th>Batch</th>
                            <th>Template</th>
                            <th>Filename</th>
                            <th>Rows</th>
                            <th>Validation</th>
                            <th>Processing</th>
                            <th>Uploaded</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div class="card mt-4">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Normalization Preview</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshNormalizationBtn">
                      <i class="bx bx-refresh"></i>
                    </button>
                  </div>
                  <div class="card-body">
                    <div class="row g-3 mb-3" id="normalizationSummary">
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Rows</span>
                        <h6 class="mb-0" id="normalizationRows">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Ready</span>
                        <h6 class="mb-0" id="normalizationReady">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Blocked</span>
                        <h6 class="mb-0" id="normalizationBlocked">-</h6>
                      </div>
                      <div class="col-sm-3">
                        <span class="fw-medium d-block mb-1">Conflicts</span>
                        <h6 class="mb-0" id="normalizationConflicts">-</h6>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered align-middle" id="normalizationTable">
                        <thead>
                          <tr>
                            <th>Employee</th>
                            <th>Client/Site</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Conflict</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="7" class="text-center text-muted">No normalized preview loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div class="card mt-4">
                  <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Timekeeping Conversion Preview</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshTimekeepingBtn">
                      <i class="bx bx-refresh"></i>
                    </button>
                  </div>
                  <div class="card-body">
                    <div class="row g-3 mb-3" id="timekeepingSummary">
                      <div class="col-sm-2">
                        <span class="fw-medium d-block mb-1">Staged</span>
                        <h6 class="mb-0" id="timekeepingStaged">-</h6>
                      </div>
                      <div class="col-sm-2">
                        <span class="fw-medium d-block mb-1">Eligible</span>
                        <h6 class="mb-0" id="timekeepingEligible">-</h6>
                      </div>
                      <div class="col-sm-2">
                        <span class="fw-medium d-block mb-1">Excluded</span>
                        <h6 class="mb-0" id="timekeepingExcluded">-</h6>
                      </div>
                      <div class="col-sm-2">
                        <span class="fw-medium d-block mb-1">Conflicts</span>
                        <h6 class="mb-0" id="timekeepingConflicts">-</h6>
                      </div>
                      <div class="col-sm-2">
                        <span class="fw-medium d-block mb-1">Hours</span>
                        <h6 class="mb-0" id="timekeepingHours">-</h6>
                      </div>
                      <div class="col-sm-2">
                        <span class="fw-medium d-block mb-1">Issues</span>
                        <h6 class="mb-0" id="timekeepingIssues">-</h6>
                      </div>
                    </div>
                    <div class="table-responsive mb-3">
                      <table class="table table-sm table-bordered align-middle" id="timekeepingTable">
                        <thead>
                          <tr>
                            <th>Employee</th>
                            <th>Client/Site</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Hours</th>
                            <th>Eligibility</th>
                            <th>Exclusion</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="7" class="text-center text-muted">No timekeeping preview loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered align-middle" id="timekeepingIssuesTable">
                        <thead>
                          <tr>
                            <th>Issue</th>
                            <th class="text-end">Count</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr><td colspan="2" class="text-center text-muted">No issue breakdown loaded.</td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="content-backdrop fade"></div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
  </div>

  <div class="offcanvas offcanvas-end" tabindex="-1" id="dtrTemplateDrawer"
    aria-labelledby="dtrTemplateDrawerTitle" style="width:min(1100px, 96vw)">
    <div class="offcanvas-header border-bottom align-items-start">
      <div class="me-3">
        <h5 class="offcanvas-title mb-1" id="dtrTemplateDrawerTitle">DTR template library</h5>
        <p class="text-muted mb-0 small">
          Review client and site formats without interrupting the active payroll workflow.
        </p>
      </div>
      <div class="d-flex align-items-center gap-2 ms-auto">
        <button type="button" class="btn btn-sm btn-outline-primary" id="newTemplateBtn">
          <i class="bx bx-plus me-1"></i>New template
        </button>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close DTR template library"></button>
      </div>
    </div>
    <div class="offcanvas-body">
      <div class="alert alert-info py-2">
        Templates define source structure and column mapping. Approved adapter versions remain governed separately in the DTR Format Registry.
      </div>

      <div class="card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <h5 class="mb-1">DTR Templates</h5>
            <small class="text-muted">Choose Edit to review or update an existing template.</small>
          </div>
          <span class="badge bg-label-secondary">Configuration library</span>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle" id="templatesTable">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Client/Site</th>
                  <th>Source</th>
                  <th>Fields</th>
                  <th>Status</th>
                  <th>Updated</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card" id="templateEditorCard">
        <div class="card-header">
          <h5 class="mb-1">Template Details</h5>
          <small class="text-muted">Create a new mapping or edit the selected template.</small>
        </div>
        <div class="card-body">
          <form id="templateForm" autocomplete="off">
            <input type="hidden" id="templateId" name="id" value="0" />
            <div class="mb-3">
              <label for="templateName" class="form-label">Template Name</label>
              <input type="text" class="form-control" id="templateName" name="template_name" maxlength="150" required />
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="clientId" class="form-label">Client</label>
                <select class="form-select" id="clientId" name="client_id">
                  <option value="">Any client</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="locationId" class="form-label">Site</label>
                <select class="form-select" id="locationId" name="location_id">
                  <option value="">Any site</option>
                </select>
              </div>
            </div>
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <label for="sourceType" class="form-label">Source Type</label>
                <select class="form-select" id="sourceType" name="source_type" required></select>
              </div>
              <div class="col-md-6">
                <label for="fileType" class="form-label">File Type</label>
                <select class="form-select" id="fileType" name="file_type" required></select>
              </div>
            </div>
            <div class="row g-3 mt-0">
              <div class="col-md-6">
                <label for="dateFormat" class="form-label">Date Format</label>
                <input type="text" class="form-control" id="dateFormat" name="date_format" maxlength="50" placeholder="Y-m-d" required />
              </div>
              <div class="col-md-6">
                <label for="timeFormat" class="form-label">Time Format</label>
                <input type="text" class="form-control" id="timeFormat" name="time_format" maxlength="50" placeholder="H:i" required />
              </div>
            </div>
            <div class="mt-3">
              <label for="employeeIdentifierField" class="form-label">Employee Identifier Field</label>
              <input type="text" class="form-control" id="employeeIdentifierField" name="employee_identifier_field" maxlength="120" required />
            </div>
            <div class="mt-3">
              <label for="expectedHeaders" class="form-label">Expected Headers</label>
              <textarea class="form-control" id="expectedHeaders" name="expected_headers" rows="4" required></textarea>
            </div>

            <div class="d-flex align-items-center justify-content-between mt-4">
              <h6 class="mb-0">Column Mapping</h6>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="addMappingBtn"
                aria-label="Add column mapping">
                <i class="bx bx-plus"></i>
              </button>
            </div>
            <div class="table-responsive mt-2">
              <table class="table table-sm align-middle" id="mappingTable">
                <thead>
                  <tr>
                    <th>Source Header</th>
                    <th>Canonical Field</th>
                    <th>Type</th>
                    <th>Transform</th>
                    <th>Required</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
              <label class="form-check-label" for="isActive">Active</label>
            </div>
            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary" id="saveTemplateBtn">
                <i class="bx bx-save me-1"></i>Save template
              </button>
              <button type="button" class="btn btn-outline-secondary" id="resetTemplateBtn">Reset</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-end" tabindex="-1" id="employeeWorkspaceDrawer"
    aria-labelledby="employeeWorkspaceDrawerTitle" style="width:min(960px, 96vw)">
    <div class="offcanvas-header border-bottom">
      <div>
        <h5 class="offcanvas-title mb-1" id="employeeWorkspaceDrawerTitle">Employee workspace</h5>
        <p class="text-muted mb-0 small" id="employeeWorkspaceDrawerContext">
          Update the HRIS employee record without leaving the active payroll batch.
        </p>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close employee workspace"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
      <div class="alert alert-info rounded-0 border-0 border-bottom mb-0 py-2 px-3">
        Your Payroll Workflow batch, search, and filters stay unchanged. Saved employee changes trigger a fresh identity check.
      </div>
      <iframe id="employeeWorkspaceFrame" title="Employee management workspace" class="border-0 flex-grow-1"
        style="width:100%;min-height:0" src="about:blank"></iframe>
    </div>
  </div>

  <div class="modal fade" id="identityResolutionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Resolve DTR Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="identityExceptionId" value="0" />
          <div class="alert alert-light border" id="identityResolutionSource"></div>
          <div class="mb-3">
            <label for="identityResolutionEmployeeId" class="form-label">HRIS employee ID</label>
            <input type="number" min="1" class="form-control" id="identityResolutionEmployeeId" />
            <div class="form-text" id="identityResolutionSuggestion"></div>
          </div>
          <div class="mb-0">
            <label for="identityResolutionReason" class="form-label">Owner decision reason</label>
            <textarea class="form-control" id="identityResolutionReason" rows="3" maxlength="500" required></textarea>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-outline-danger" id="excludeIdentityExceptionBtn">Exclude with reason</button>
          <div>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="mapIdentityExceptionBtn">Approve mapping</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="populationResolutionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Resolve Payslip-Only Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="populationExceptionId" value="0" />
          <div class="alert alert-light border" id="populationResolutionSource"></div>
          <div class="mb-3">
            <label for="populationResolutionDisposition" class="form-label">Owner disposition</label>
            <select class="form-select" id="populationResolutionDisposition" required>
              <option value="">Select the supported outcome</option>
              <option value="dtr_added_to_superseding_batch">DTR added to a superseding batch</option>
              <option value="approved_off_cycle">Approved off-cycle payroll</option>
              <option value="approved_adjustment">Approved adjustment payroll</option>
              <option value="reference_exclusion">Exclude from this reference comparison</option>
            </select>
          </div>
          <div class="mb-0">
            <label for="populationResolutionReason" class="form-label">Evidence-backed owner reason</label>
            <textarea class="form-control" id="populationResolutionReason" rows="4" minlength="20" maxlength="1000"
              placeholder="Reference the approved DTR correction, off-cycle instruction, adjustment approval, or exclusion evidence." required></textarea>
            <div class="form-text">This decision is audited. It does not manufacture a missing DTR row.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="resolvePopulationExceptionBtn">Record disposition</button>
        </div>
      </div>
    </div>
  </div>

  <?php require("../includes/footer.php"); ?>
  <script src="js/index-01.js?v=20260728b"></script>
  <?php require("../includes/custom-footer.php"); ?>
</body>

</html>
