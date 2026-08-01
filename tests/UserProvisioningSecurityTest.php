<?php
$signupController = (string)file_get_contents(__DIR__ . '/../login/controller/SignUpController.php');
$loginModel = (string)file_get_contents(__DIR__ . '/../API/login/Login.php');
$usersController = (string)file_get_contents(__DIR__ . '/../users-access/controller/UserController.php');
$usersPage = (string)file_get_contents(__DIR__ . '/../users-access/index.php');
$usersJs = (string)file_get_contents(__DIR__ . '/../users-access/js/index-04.js');
$loginPage = (string)file_get_contents(__DIR__ . '/../login/index.php');
$resetPage = (string)file_get_contents(__DIR__ . '/../login/reset-password.php');

$checks = [
    [str_contains($signupController, 'auth_require_role([1])'), 'only administrators can provision users'],
    [str_contains($signupController, "strlen(\$password) < 12"), 'signup enforces the 12-character password policy'],
    [str_contains($signupController, '$password !== $confirmPassword'), 'signup verifies password confirmation'],
    [str_contains($signupController, '$hasGlobalClientAccess = in_array($accessLevel, [1, 2, 3], true)'), 'Admin, HR, and Payroll provisioning is global across clients'],
    [str_contains($signupController, 'A client assignment is required for Coordinator and C&B users.'), 'signup requires client ownership for scoped roles'],
    [str_contains($signupController, "\$roleNames = [1 => 'Admin', 2 => 'HR', 3 => 'Payroll', 4 => 'Coordinator', 5 => 'C&B']"), 'role descriptions are derived server-side'],
    [!str_contains($signupController, "\$_POST['access_description']"), 'the client cannot forge an access description'],
    [str_contains($loginModel, 'employee_user_name = :employee_user_name OR employee_email = :employee_email'), 'duplicate usernames and email addresses are rejected'],
    [str_contains($loginModel, ':email, 0'), 'new accounts are created in pending status'],
    [str_contains($usersController, 'validated_user_clients'), 'user edits enforce valid client ownership server-side'],
    [str_contains($usersPage, 'Create Pending Account') && str_contains($usersJs, 'sign-up-user'), 'User Access exposes the controlled provisioning workflow'],
    [str_contains($loginPage, 'showLoginPassword'), 'login includes a show-password control'],
    [str_contains($resetPage, 'password-toggle') && str_contains($usersPage, 'password-toggle'), 'reset and signup forms include show-password controls'],
];

foreach ($checks as [$passed, $message]) {
    if (!$passed) {
        throw new RuntimeException('User provisioning security check failed: ' . $message);
    }
    echo "PASS: {$message}\n";
}

echo "RESULT: User provisioning security checks passed.\n";
