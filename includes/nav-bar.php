<?php
session_start();
require_once(__DIR__ . '/csrf.php');

// Derive the application mount from the request URL, not the server's
// filesystem depth. The old fixed path index resolves to "includes" on
// Windows and breaks local login/session redirects.
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$appBasePath = str_replace('\\', '/', dirname(dirname($scriptName)));
$appBasePath = rtrim($appBasePath, '/.');
$appBaseUrl = $appBasePath === '' ? '' : '/' . ltrim($appBasePath, '/');

// Keep the legacy variable available for page-specific links and scripts.
// A dot produces root-relative /./... URLs that browsers safely normalize.
$pathParts = [];
$pathParts[6] = $appBaseUrl === '' ? '.' : trim($appBaseUrl, '/');

// ── Login check ───────────────────────────────────────────────────────────
if (!isset($_SESSION['taascor_access_level'])) {
    header("Location: {$appBaseUrl}/login/");
    exit();
}

// ── Session idle/absolute timeout check (page loads) ─────────────────────
$now = time();
if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > 1800) {
    session_unset(); session_destroy();
    header("Location: {$appBaseUrl}/login/?reason=timeout");
    exit();
}
if (isset($_SESSION['session_start']) && ($now - $_SESSION['session_start']) > 28800) {
    session_unset(); session_destroy();
    header("Location: {$appBaseUrl}/login/?reason=timeout");
    exit();
}
$_SESSION['last_activity'] = $now;
if (!isset($_SESSION['session_start'])) $_SESSION['session_start'] = $now;

// ── Generate CSRF token for this page ─────────────────────────────────────
$csrfToken = csrf_token();

// Release session lock — page render doesn't need it anymore,
// and holding it blocks parallel AJAX calls from the same browser
session_write_close();

?>


<!-- sidebar -->
<nav id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme sidebar">
        <div class="app-brand demo">
          <a href="/<?php echo $pathParts[6];?>/employee-management/" class="app-brand-link">
            <span class="app-brand-logo demo">
              <img src="../assets/img/png/logo.png" class="img-fluid" width="35px">

            </span>
            <span class="app-brand-text demo menu-text text-center fw-bold mt-1 ms-2">TAASCOR HRIS</span>
          </a>

          <a href="javascript:void(0);" class="layout-menu-toggle toggle menu-link text-large ms-auto">
            <i class="bx bx-menu bx-sm d-flex align-items-center justify-content-center sidebar-toggle"></i>
          </a>
        </div>
  
        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-3" id="side-ul">
          <input hidden type="text" id="url_page"     value="<?php echo htmlspecialchars($pathParts[6]); ?>">
          <input hidden type="text" id="access_level" value="<?php echo htmlspecialchars($_SESSION['taascor_access_level']); ?>">
          <input hidden type="text" id="csrf_token"   value="<?php echo htmlspecialchars($csrfToken); ?>">
          
          <li style="display:none" class="menu-item" value="80" id="a80">
            <a href="/<?php echo $pathParts[6];?>/dashboard/" class="menu-link">
              <i class='menu-icon tf-icons bx bx-bar-chart-square'></i>
              <div class="text-truncate" data-i18n="Employee Management">Dashboard</div>
            </a>
          </li>
          
          <li style="display:none" class="menu-item" value="81" id="a81">
            <a href="/<?php echo $pathParts[6];?>/employee-management/" class="menu-link">
              <i class='menu-icon tf-icons bx bx-group'></i>
              <div class="text-truncate" data-i18n="Employee Management">Employee Management</div>
            </a>
          </li>
          
          <li style="display:none" class="menu-item master" value="1" id="a1">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-error"></i>
              <div class="text-truncate" data-i18n="Data Issue Tracker">Data Issue Tracker</div>
            </a>
            <ul class="menu-sub">
              <li style="display:none" class="menu-item" value="11" id="a11">
                <a href="/<?php echo $pathParts[6];?>/incomplete-details/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Incomplete Details">Incomplete Details</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="12" id="a12">
                <a href="/<?php echo $pathParts[6];?>/sss-format/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="SSS Format">SSS Format</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="13" id="a13">
                <a href="/<?php echo $pathParts[6];?>/duplicate-sss/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Duplicate SSS">Duplicate SSS</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="14" id="a14">
                <a href="/<?php echo $pathParts[6];?>/duplicate-philhealth/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Duplicate Philhealth">Duplicate Philhealth</div>
                </a>
              </li>
              
              <li style="display:none" class="menu-item" value="20" id="a20">
                <a href="/<?php echo $pathParts[6];?>/duplicate-pagibig/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Duplicate Pag-ibig">Duplicate Pag-ibig</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="15" id="a15">
                <a href="/<?php echo $pathParts[6];?>/duplicate-tin/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Duplicate TIN">Duplicate TIN</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="16" id="a16">
                <a href="/<?php echo $pathParts[6];?>/duplicate-bank-account/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Duplicate Bank Account">Duplicate Bank Account</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="17" id="a17">
                <a href="/<?php echo $pathParts[6];?>/invalid-salary/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Invalid Salary">Invalid Salary</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="18" id="a18">
                <a href="/<?php echo $pathParts[6];?>/invalid-contact-number/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Invalid Contact Number">Invalid Contact Number</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="19" id="a19">
                <a href="/<?php echo $pathParts[6];?>/invalid-employee-type/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Invalid Employee Type">Invalid Employee Type</div>
                </a>
              </li>
            </ul>
          </li>

          <li style="display:none" class="menu-item master" value="3" id="a3">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-wallet"></i>
              <div class="text-truncate" data-i18n="Payroll">Payroll</div>
            </a>
            <ul class="menu-sub">
              <li style="display:none" class="menu-item" value="35" id="a35">
                <a href="/<?php echo $pathParts[6];?>/payroll-dashboard/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Payroll Dashboard">Payroll Dashboard
                  </div>
                </a>
              </li>        
              <li style="display:none" class="menu-item" value="36" id="a36">
                <a href="/<?php echo $pathParts[6];?>/payroll-summary/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Payroll Summary">Payroll Summary
                  </div>
                </a>
              </li>
              <li style="display:none" class="menu-item" value="38" id="a38">
                <a href="/<?php echo $pathParts[6];?>/dtr-format-engine/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="DTR Format Engine">DTR Format Engine
                  </div>
                </a>
              </li>
              <li style="display:none" class="menu-item" value="31" id="a31">
                <a href="/<?php echo $pathParts[6];?>/dtr-upload/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="DTR Upload">DTR Upload</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="32" id="a32">
                <a href="/<?php echo $pathParts[6];?>/other-additional/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Additional">Other Additional</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="33" id="a33">
                <a href="/<?php echo $pathParts[6];?>/other-deduction/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Other Deduction">Other Deduction</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="34" id="a34">
                <a href="/<?php echo $pathParts[6];?>/payslip/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Payslip">Payslip</div>
                </a>
              </li>
            </ul>
          </li>
          
          <li style="display:none" class="menu-item master" value="70" id="a70">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div class="text-truncate" data-i18n="HR C&B">HR C&B</div>
            </a>
            <ul class="menu-sub">
              <li style="display:none" class="menu-item" value="71" id="a71">
                <a href="/<?php echo $pathParts[6];?>/loans/"  class="menu-link">
                  <i class="menu-icon tf-icons bx bx-money"></i>
                  <div class="text-truncate" data-i18n="Loans">Loans</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="72" id="a72">
                <a href="/<?php echo $pathParts[6];?>/loans-report/"  class="menu-link">
                  <i class="menu-icon tf-icons bx bx-clipboard"></i>
                  <div class="text-truncate" data-i18n="Loans Report">Loans Report</div>
                </a>
              </li>
            </ul>
          </li>

          <li style="display:none" class="menu-item" value="82" id="a82">
            <a href="/<?php echo $pathParts[6];?>/billing/"  class="menu-link">
              <i class="menu-icon tf-icons bx bx-receipt"></i>
              <div class="text-truncate" data-i18n="Billing">Billing</div>
            </a>
          </li>

          <li style="display:none" class="menu-item" value="83" id="a83">
            <a href="/<?php echo $pathParts[6];?>/accounting-finance/" class="menu-link">
              <i class="menu-icon tf-icons bx bx-calculator"></i>
              <div class="text-truncate" data-i18n="Accounting Finance">Accounting/Finance</div>
            </a>
          </li>

          <li style="display:none" class="menu-item" value="84" id="a84">
            <a href="/<?php echo $pathParts[6];?>/client-management/" class="menu-link">
              <i class="menu-icon tf-icons bx bx-buildings"></i>
              <div class="text-truncate" data-i18n="Client Management">Client Management</div>
            </a>
          </li>

          <li style="display:none" class="menu-item" value="85" id="a85">
            <a href="/<?php echo $pathParts[6];?>/audit-log/" class="menu-link">
              <i class="menu-icon tf-icons bx bx-shield-quarter"></i>
              <div class="text-truncate" data-i18n="Audit Log">Audit Log</div>
            </a>
          </li>

          <li style="display:none" class="menu-item" value="87" id="a87">
            <a href="/<?php echo $pathParts[6];?>/terminated-employees/" class="menu-link">
              <i class='menu-icon tf-icons bx bx-user-x'></i>
              <div class="text-truncate" data-i18n="Terminated Employees">Terminated Employees</div>
            </a>
          </li>

          <li style="display:none" class="menu-item" value="86" id="a86">
            <a href="/<?php echo $pathParts[6];?>/users-access/" class="menu-link">
              <i class='menu-icon tf-icons bx bx-user'></i>
              <div class="text-truncate" data-i18n="Users Access">Users Access</div>
            </a>
          </li>

          <li style="display:none" class="menu-item" value="88" id="a88">
            <a href="/<?php echo $pathParts[6];?>/forms-templates/" class="menu-link">
              <i class='menu-icon tf-icons bx bx-file'></i>
              <div class="text-truncate" data-i18n="Forms and Templates">Forms and Templates</div>
            </a>
          </li>

          <li style="display:none" class="menu-item master" value="5" id="a5">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-cog"></i>
              <div class="text-truncate" data-i18n="Maintenance">Maintenance</div>
            </a>
            <ul class="menu-sub">
              <li style="display:none" class="menu-item" value="51" id="a51">
                <a href="/<?php echo $pathParts[6];?>/branch-maintenance/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Branch Maintenance">Branch Maintenance</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="52" id="a52">
                <a href="/<?php echo $pathParts[6];?>/client-maintenance/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Client Maintenance">Client Maintenance</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="53" id="a53">
                <a href="/<?php echo $pathParts[6];?>/department-maintenance/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Department Maintenance">Department Maintenance</div>
                </a>
              </li>

              <li style="display:none" class="menu-item" value="54" id="a54">
                <a href="/<?php echo $pathParts[6];?>/position-maintenance/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Position Maintenance">Position Maintenance</div>
                </a>
              </li>
              
              <li style="display:none" class="menu-item" value="56" id="a56">
                <a href="/<?php echo $pathParts[6];?>/client-location-maintenance/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Client Location Maintenance">Client Location Maintenance</div>
                </a>
              </li>
              
              <li style="display:none" class="menu-item" value="55" id="a55">
                <a href="/<?php echo $pathParts[6];?>/payday/" class="menu-link">
                  <div class="text-truncate menu-sub-title" data-i18n="Payday">Pay Day</div>
                </a>
              </li>
            </ul>
          </li>

        </ul>
        <div class="footer">
          powered by VisioTech Solutions
        </div>
      </nav>
      <!-- / sidebar -->
