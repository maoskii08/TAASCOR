<?php
$routes = [
    'hris/login/index.php' => "../legacy-route-disabled.php",
    'hris/login/forgot-password.php' => "../legacy-route-disabled.php",
    'hris/login/reset-password.php' => "../legacy-route-disabled.php",
    'hris/login/logout.php' => "../legacy-route-disabled.php",
    'hris/login/controller/ForgotPasswordController.php' => "../../legacy-route-disabled.php",
    'hris/employee-management/controller/EmployeeController.php' => "../../legacy-route-disabled.php",
    'hris/payslip/controller/PayslipController.php' => "../../legacy-route-disabled.php",
    'hris/restriction/controller/RestrictionController.php' => "../../legacy-route-disabled.php",
    'hris/users-access/controller/UserController.php' => "../../legacy-route-disabled.php",
];

foreach ($routes as $route => $guard) {
    $content = (string)file_get_contents(__DIR__ . '/../' . $route);
    if (!str_contains($content, $guard)) {
        throw new RuntimeException("Legacy route is not quarantined: {$route}");
    }
}

$guard = (string)file_get_contents(__DIR__ . '/../hris/legacy-route-disabled.php');
if (!str_contains($guard, 'http_response_code(410)')) {
    throw new RuntimeException('Legacy route guard must return HTTP 410.');
}

echo "RESULT: Legacy /hris route quarantine checks passed.\n";
