<?php

declare(strict_types=1);

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$authGuard = (string)file_get_contents(__DIR__ . '/../includes/auth_guard.php');
$loginPage = (string)file_get_contents(__DIR__ . '/../login/index.php');
$loginController = (string)file_get_contents(__DIR__ . '/../login/controller/LoginController.php');
$navigation = (string)file_get_contents(__DIR__ . '/../includes/nav-bar.php');
$footer = (string)file_get_contents(__DIR__ . '/../includes/custom-footer.php');
$restrictionJs = (string)file_get_contents(__DIR__ . '/../restriction/js/page-restriction-03.js');
$restrictionController = (string)file_get_contents(__DIR__ . '/../restriction/controller/RestrictionController.php');
$dtrEnginePage = (string)file_get_contents(__DIR__ . '/../dtr-format-engine/index.php');
$dtrEngineScript = (string)file_get_contents(__DIR__ . '/../dtr-format-engine/js/index-01.js');
$dtrTemplateController = (string)file_get_contents(__DIR__ . '/../dtr-format-engine/controller/TemplateController.php');
$smartResolutionService = (string)file_get_contents(__DIR__ . '/../dtr-format-engine/model/SmartEmployeeResolutionService.php');
$employeePage = (string)file_get_contents(__DIR__ . '/../employee-management/index.php');
$employeeScript = (string)file_get_contents(__DIR__ . '/../employee-management/js/index-18.js');
$employeeModel = (string)file_get_contents(__DIR__ . '/../employee-management/model/Employee.php');
$terminatedModel = (string)file_get_contents(__DIR__ . '/../terminated-employees/model/Employee.php');
$terminatedScript = (string)file_get_contents(__DIR__ . '/../terminated-employees/js/index-07.js');

$check(str_contains($authGuard, "header('Location: ' . \$loginUrl)"), 'unauthenticated page requests redirect to Login');
$check(str_contains($authGuard, "'redirect' => \$loginUrl"), 'API authentication failures return the mounted Login URL');
$check(str_contains($authGuard, "\$requestUri . '?next='") === false, 'return URLs are encoded rather than concatenated unsafely');
$check(str_contains($loginPage, 'name="next_path"'), 'Login preserves the intended in-app destination');
$check(str_contains($loginController, 'safe_login_return_path'), 'Login controller validates the return destination');
$check(str_contains($loginController, "header('Location: ' . \$nextPath)"), 'successful Login resumes the intended page');

foreach ([
    'Payroll Workflow',
    '#smart-employee-resolution',
    '#employee-identity-review',
    '#payroll-population-review',
] as $expectedNavigation) {
    $check(str_contains($navigation, $expectedNavigation), "navigation includes {$expectedNavigation}");
}

$check(str_contains($restrictionJs, 'formdata.append("csrf_token"'), 'page access check explicitly includes CSRF');
$check(str_contains($restrictionJs, 'window.location.pathname'), 'page access check matches routes independently of hash anchors');
$check(!str_contains($restrictionJs, "parentUL.style.display = 'block'"), 'selected navigation modules do not force submenus open with inline styles');
$check(str_contains($restrictionJs, "document.querySelectorAll('#side-ul .menu-item.master')"), 'collapse behavior applies to every collapsible navigation module');
$check(str_contains($restrictionJs, "submenu.style.removeProperty('display')"), 'legacy submenu display overrides are cleared');
$check(str_contains($restrictionJs, "toggle.setAttribute('aria-expanded'"), 'collapsible modules expose their current expanded state');
$check(str_contains($restrictionJs, 'new MutationObserver(syncExpandedState)'), 'expanded-state accessibility stays synchronized after user toggles');
$check(str_contains($restrictionJs, 'while (parentMenu)'), 'selected nested routes open each required ancestor module');
$check(str_contains($footer, 'page-restriction-03.js?v=20260727c'), 'navigation access script is cache-busted after the secondary-help navigation fix');
$check(str_contains($restrictionController, "require_once('../../includes/auth_guard.php')"), 'restriction controller uses centralized authentication');
$check(str_contains($restrictionController, 'auth_require_login();'), 'restriction controller validates login and CSRF');
$check(str_contains($restrictionController, "\$pages[\$accessLevel] ?? []"), 'navigation authorization uses the centralized role-permission map');
$check(!str_contains($restrictionController, 'getAccess()'), 'navigation authorization cannot disagree with the authenticated session');
$check(str_contains($dtrEnginePage, 'exportIdentityDecisionPacketBtn'), 'identity review exposes an HR decision-packet export');
$check(str_contains($dtrEngineScript, 'function exportIdentityDecisionPacket()'), 'identity review can export all unresolved cases for owner action');
$check(str_contains($dtrEngineScript, "'Owner decision'"), 'decision packet includes an explicit owner-decision field');
$check(str_contains($dtrEngineScript, "'Owner reason'"), 'decision packet includes an auditable owner-reason field');
$check(str_contains($dtrEngineScript, '/^[=+\\-@]/'), 'decision packet neutralizes spreadsheet formula prefixes');
$check(str_contains($dtrEnginePage, 'smartResolutionSelectAll'), 'Smart Employee Alignment exposes select-all for eligible mappings');
$check(str_contains($dtrEngineScript, 'smart-resolution-select'), 'individual employee mapping checkboxes are rendered');
$check(str_contains($dtrEngineScript, 'source_keys: JSON.stringify(selectedSourceKeys)'), 'bulk approval submits only explicitly selected source identities');
$check(str_contains($smartResolutionService, "['auto_eligible_shadow', 'review']"), 'server revalidates selected safe and owner-review proposals');
$check(str_contains($smartResolutionService, 'invalid_selection_count'), 'stale or blocked bulk selections fail closed');
$check(str_contains($dtrEnginePage, 'id="openDtrTemplateDrawerBtn"'), 'Payroll Workflow exposes the compact DTR template library control');
$check(str_contains($dtrEnginePage, 'data-bs-target="#dtrTemplateDrawer"'), 'DTR template control opens the in-page drawer');
$check(str_contains($dtrEnginePage, 'id="dtrTemplateDrawer"') && str_contains($dtrEnginePage, 'aria-labelledby="dtrTemplateDrawerTitle"'), 'DTR template library uses a labeled offcanvas drawer');
$check(str_contains($dtrEnginePage, 'id="templatesTable"') && str_contains($dtrEnginePage, 'id="templateForm"'), 'template list and editor remain available inside the drawer');
$check(str_contains($dtrEngineScript, "$('#dtrTemplateDrawer').on('shown.bs.offcanvas'"), 'opening the template drawer refreshes the current library');
$check(str_contains($dtrEngineScript, 'function focusTemplateEditor()'), 'template edit actions move users to the drawer editor');
$check(str_contains($dtrTemplateController, '$clientId === null || (int)$clientId <= 0'), 'shared any-client templates remain viewable to global HRIS roles');
$check(str_contains($dtrEnginePage, 'employeeWorkspaceDrawer'), 'identity exceptions include an in-page employee workspace drawer');
$check(str_contains($dtrEngineScript, "event.data.source !== 'taascor-employee-workspace'"), 'drawer saves trigger same-page identity refresh');
$check(str_contains($employeePage, 'employee-workspace-embed'), 'Employee Management supports the embedded drawer workspace');
$check(str_contains($employeeScript, 'finishEmployeeWorkspace'), 'embedded employee changes return to Payroll Workflow without page navigation');
$check(str_contains($employeePage, 'workspaceTerminateBtn'), 'employee drawer exposes the governed termination flow');
$check(str_contains($employeePage, 'workspaceRemoveBtn') && str_contains($employeeScript, 'removeWorkspaceEmployee'), 'Admin employee drawer exposes retained HRIS removal');
$check(str_contains($employeeModel, "status = 'Removed'"), 'employee removal retains the master record instead of physically deleting it');
$check(str_contains($terminatedModel, "status in ('Terminated', 'Removed')"), 'terminated list includes retained removed employees');
$check(str_contains($terminatedScript, 'Terminated / Removed On'), 'terminated list exposes the lifecycle effective date near the employee ID');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Payroll workflow navigation and authentication checks passed.\n";
