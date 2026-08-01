<?php
require_once('../../includes/auth_guard.php');
auth_require_role([1]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', '256M');

require('../../config/db_connect.php');
require('../../API/login/Login.php');

$model = new LoginClass;
$model->db = $pdoConn;

function signup_error(string $message): void {
    http_response_code(422);
    echo json_encode(['success' => 0, 'error' => $message]);
    exit();
}

$request = (string)($_POST['request'] ?? '');

if($request == 'sign-up-user'){
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $firstname = trim((string)($_POST['firstname'] ?? ''));
    $lastname = trim((string)($_POST['lastname'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $accessLevel = (int)($_POST['access_level'] ?? 0);
    $roleNames = [1 => 'Admin', 2 => 'HR', 3 => 'Payroll', 4 => 'Coordinator', 5 => 'C&B'];

    if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
        signup_error('Username must be 3 to 64 characters and use only letters, numbers, dots, underscores, or hyphens.');
    }
    if ($firstname === '' || $lastname === '' || mb_strlen($firstname) > 80 || mb_strlen($lastname) > 80) {
        signup_error('A valid first and last name are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        signup_error('A valid email address is required.');
    }
    if (!isset($roleNames[$accessLevel])) {
        signup_error('Select a valid user role.');
    }
    if ($password !== $confirmPassword) {
        signup_error('Passwords do not match.');
    }
    if (
        strlen($password) < 12
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        signup_error('Use at least 12 characters with uppercase, lowercase, number, and symbol.');
    }

    $clientIds = [];
    $rawClients = (string)($_POST['client_location'] ?? '');
    foreach (preg_split('/\s*,\s*/', trim($rawClients), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $clientId) {
        if (!ctype_digit($clientId) || (int)$clientId <= 0) {
            signup_error('One or more client assignments are invalid.');
        }
        $clientIds[] = (int)$clientId;
    }
    $clientIds = array_values(array_unique($clientIds));

    $hasGlobalClientAccess = in_array($accessLevel, [1, 2, 3], true);
    if (!$hasGlobalClientAccess && count($clientIds) === 0) {
        signup_error('A client assignment is required for Coordinator and C&B users.');
    }
    if ($hasGlobalClientAccess) {
        $clientIds = [];
    } elseif ($clientIds) {
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $pdoConn->prepare(
            "SELECT client_id FROM taascor_client
             WHERE client_name <> 'No Client' AND client_id IN ($placeholders)"
        );
        $stmt->execute($clientIds);
        $validClientIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($validClientIds);
        $expectedClientIds = $clientIds;
        sort($expectedClientIds);
        if ($validClientIds !== $expectedClientIds) {
            signup_error('One or more selected clients are unavailable.');
        }
    }

    $model->username = $username;
    $model->password = $password;
    $model->firstname = $firstname;
    $model->lastname = $lastname;
    $model->email = $email;
    $model->access_level = $accessLevel;
    $model->access_description = $roleNames[$accessLevel];
    $model->client = implode(',', $clientIds);
    $result = $model->signUpUser();
    if (($result['success'] ?? 0) === 1) {
        log_action("User Provisioned (Pending): {$username} | Role: {$roleNames[$accessLevel]}");
    }
    echo json_encode($result);
}else if($request == 'get-client-location'){
    echo json_encode($model->getClientLocation());
}else {
    http_response_code(400);
    echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
}

       
    


?>
