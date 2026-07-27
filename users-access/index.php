<?php require_once('../config/page-permissions.php'); // $pages, $page_map, $role_names ?>
<?php
require_once('../includes/auth_guard.php');
auth_require_role([1]);
?>
<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Users Access</title>
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
                    <li class="breadcrumb-item active" aria-current="page">Users Access</li>
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

            <!-- Tab navigation -->
            <ul class="nav nav-tabs mb-3" id="usersAccessTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab">
                  <i class="bx bx-user me-1"></i> Users
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-guide" type="button" role="tab">
                  <i class="bx bx-shield-quarter me-1"></i> Role &amp; Access Guide
                </button>
              </li>
            </ul>

            <div class="tab-content">

              <!-- ── Users tab ──────────────────────────────────────── -->
              <div class="tab-pane fade show active" id="tab-users" role="tabpanel">
                <div class="card">
                  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                      <h5 class="mb-1">User Accounts</h5>
                      <small class="text-muted">Provision new accounts, assign ownership, then activate after review.</small>
                    </div>
                    <button id="openCreateUserBtn" type="button" class="btn btn-primary">
                      <i class="bx bx-user-plus me-1"></i>Create User
                    </button>
                  </div>
                  <div class="card-body">
                    <div class="card-datatable mt-n2" id="table_container"></div>
                  </div>
                </div>
              </div>

              <!-- ── Role & Access Guide tab (Admin only) ───────────── -->
              <div class="tab-pane fade" id="tab-guide" role="tabpanel">

                <!-- Role descriptions -->
                <div class="card mb-4">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bx bx-group me-2"></i>Role Descriptions</h5>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <?php
                      $roleDescs = [
                        '1' => ['Admin',       'danger',  'Full access to all modules including user management, audit log, and all financial reports.'],
                        '2' => ['HR',          'primary', 'Dashboard, employee data, data quality tools, client info, terminated employees, and forms.'],
                        '3' => ['Payroll',     'success', 'Dashboard, payroll processing, billing, accounting, loans, data quality tools, maintenance tables, and employee data.'],
                        '4' => ['Coordinator', 'warning', 'Employee management and terminated employees only.'],
                        '5' => ['C&amp;B',     'info',    'Dashboard, data quality tools, loans, loans report, and forms.'],
                      ];
                      foreach ($roleDescs as $rid => $rd): ?>
                      <div class="col-sm-6 col-lg-4">
                        <div class="d-flex align-items-start gap-3 p-3 border rounded">
                          <span class="badge bg-<?= $rd[1] ?> fs-6 mt-1"><?= $rid ?></span>
                          <div><strong><?= $rd[0] ?></strong><br><small class="text-muted"><?= $rd[2] ?></small></div>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <!-- Permission matrix — driven by config/page-permissions.php -->
                <div class="card">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bx bx-table me-2"></i>Module Access Matrix</h5>
                    <small class="text-muted">Auto-generated from <code>config/page-permissions.php</code></small>
                  </div>
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-bordered table-hover mb-0 text-center align-middle">
                        <thead class="table-dark">
                          <tr>
                            <th class="text-start ps-3" style="min-width:220px">Module / View</th>
                            <?php
                            $roleBadgeColors = ['1'=>'danger','2'=>'primary','3'=>'success','4'=>'warning text-dark','5'=>'info text-dark'];
                            foreach ($role_names as $rid => $rname): ?>
                            <th><span class="badge bg-<?= $roleBadgeColors[$rid] ?? 'secondary' ?>"><?= htmlspecialchars($rname) ?></span></th>
                            <?php endforeach; ?>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $yes = '<i class="bx bx-check-circle text-success fs-5"></i>';
                          $no  = '<span class="text-muted" style="opacity:.3">—</span>';
                          $lastGroup = null;
                          foreach ($page_map as $pid => $entry):
                              $label  = $entry[0];
                              $grp    = $entry[1];
                              $is_sub = $entry[2];
                              if ($grp !== $lastGroup):
                                  $lastGroup = $grp;
                          ?>
                          <tr class="table-secondary">
                            <td colspan="<?= count($role_names) + 1 ?>"
                                class="fw-bold text-uppercase small py-1 px-3"
                                style="letter-spacing:.05em;font-size:.72rem">
                              <?= htmlspecialchars($grp) ?>
                            </td>
                          </tr>
                          <?php endif; ?>
                          <tr>
                            <td class="text-start <?= $is_sub ? 'ps-4 text-muted' : 'ps-3 fw-semibold' ?>">
                              <?= $is_sub ? '&nbsp;&nbsp;' . htmlspecialchars($label) : htmlspecialchars($label) ?>
                            </td>
                            <?php foreach ($role_names as $rid => $rname): ?>
                            <td><?= in_array((int)$pid, $pages[$rid]) ? $yes : $no ?></td>
                            <?php endforeach; ?>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

              </div><!-- /tab-guide -->

            </div><!-- /tab-content -->

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

    <div class="modal fade" id="editUserModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">User Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" >
            <div class="row">
              <div class="col-sm-12">
                <div class="row mt-n3">
                  <div class="col-sm-12">
                    <div class="divider text-start">
                      <div class="divider-text">
                        <small class="text-uppercase fw-bold">User Information</small>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-sm-3">
                    <label class="modal-label mt-2">User Name:</label>
                  </div>
                  <div class="col-sm-9">
                    <input id="user-name" type="text" class="form-control" disabled>
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-3">
                    <label class="modal-label mt-2">Full Name:</label>
                  </div>
                  <div class="col-sm-9">
                    <input id="full-name" type="text" class="form-control" placeholder="Old Employee Ident">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-3">
                    <label class="modal-label mt-2">Email:</label>
                  </div>
                  <div class="col-sm-9">
                    <input id="email" type="text" class="form-control" placeholder="Employee Type">
                  </div>
                </div>

                <div class="row mt-2">
                  <div class="col-sm-3">
                    <label class="modal-label mt-2">User Role:</label>
                  </div>
                  <div class="col-sm-9">
                    <select class="form-select" id="user-role" data-placeholder="Department" required>
                      <option></option>
                      <option value="1">Admin</option>
                      <option value="2">HR</option>
                      <option value="3">Payroll</option>
                      <option value="4">Coordinator</option>
                      <option value="5">C&amp;B</option>
                    </select>
                  </div>
                </div>

                <div style="display:none" class="row mt-2" id="client_container">
                  <div class="col-sm-3">
                    <label class="modal-label mt-2">Client:</label>
                  </div>
                  <div class="col-sm-9">
                    <select class="form-select" id="client_location"
                      data-placeholder="Choose Client" multiple>
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

    <div class="modal fade" id="createUserModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
      aria-labelledby="createUserModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title" id="createUserModalLabel">Create User Account</h5>
              <small class="text-muted">New accounts are created as Pending and cannot log in until activated.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="create-user-alert" class="alert alert-danger d-none" role="alert"></div>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="signup-firstname" class="form-label">First name</label>
                <input id="signup-firstname" type="text" class="form-control" maxlength="80" autocomplete="off" required>
              </div>
              <div class="col-md-6">
                <label for="signup-lastname" class="form-label">Last name</label>
                <input id="signup-lastname" type="text" class="form-control" maxlength="80" autocomplete="off" required>
              </div>
              <div class="col-md-6">
                <label for="signup-username" class="form-label">Username</label>
                <input id="signup-username" type="text" class="form-control" maxlength="64"
                       pattern="[A-Za-z0-9._-]{3,64}" autocomplete="off" required>
                <div class="form-text">3–64 characters: letters, numbers, dot, underscore, or hyphen.</div>
              </div>
              <div class="col-md-6">
                <label for="signup-email" class="form-label">Work email</label>
                <input id="signup-email" type="email" class="form-control" maxlength="190" autocomplete="off" required>
              </div>
              <div class="col-md-6">
                <label for="signup-role" class="form-label">Role</label>
                <select id="signup-role" class="form-select" required>
                  <option value="">Select role</option>
                  <option value="1">Admin</option>
                  <option value="2">HR</option>
                  <option value="3">Payroll</option>
                  <option value="4">Coordinator</option>
                  <option value="5">C&amp;B</option>
                </select>
              </div>
              <div class="col-md-6 d-none" id="signup-client-container">
                <label for="signup-client-location" class="form-label">Client assignment</label>
                <select id="signup-client-location" class="form-select" multiple></select>
                <div class="form-text">Required for Coordinator and C&amp;B. Admin, HR, and Payroll are global.</div>
              </div>
              <div class="col-md-6">
                <label for="signup-password" class="form-label">Temporary password</label>
                <div class="input-group">
                  <input id="signup-password" type="password" class="form-control" autocomplete="new-password" required>
                  <button class="btn btn-outline-secondary password-toggle" type="button"
                          data-target="signup-password" aria-label="Show temporary password" aria-pressed="false">
                    <i class="bx bx-show" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-6">
                <label for="signup-confirm-password" class="form-label">Confirm temporary password</label>
                <div class="input-group">
                  <input id="signup-confirm-password" type="password" class="form-control" autocomplete="new-password" required>
                  <button class="btn btn-outline-secondary password-toggle" type="button"
                          data-target="signup-confirm-password" aria-label="Show confirmed password" aria-pressed="false">
                    <i class="bx bx-show" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
              <div class="col-12">
                <div class="alert alert-info mb-0">
                  <i class="bx bx-shield-quarter me-1"></i>
                  Use at least 12 characters with uppercase, lowercase, number, and symbol. Share the temporary
                  password through an approved secure channel only. After activation, have the user use
                  Forgot Password to set a private password before performing HR or payroll work.
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
            <button id="createUserBtn" type="button" class="btn btn-primary">
              <i class="bx bx-user-plus me-1"></i>Create Pending Account
            </button>
          </div>
        </div>
      </div>
    </div>

    <?Php require("../includes/footer.php") ;?>
    <script src="js/index-04.js?v=20260726a"></script>
    <?Php require("../includes/custom-footer.php") ;?>

    <script>
      $('#user-role').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select User Role',
        dropdownParent: $('#editUserModal')
      });

      $('#client_location').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Clients',
        allowClear: true,
        dropdownParent: $('#editUserModal')
      });

      $('#signup-client-location').select2({
        theme: "bootstrap-5",
        width: '100%',
        placeholder: 'Select one or more clients',
        allowClear: true,
        dropdownParent: $('#createUserModal')
      });
  </script>
</body>

</html>
