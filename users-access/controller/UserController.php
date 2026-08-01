<?php

require_once('../../includes/auth_guard.php');
auth_require_role([1]);
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');
ini_set('memory_limit', -1);

require('../../config/db_connect.php');
require('../model/User.php');

$model = new User;
$model->db = $pdoConn;

function user_access_error(string $message): void {
    http_response_code(422);
    echo json_encode(['success' => 0, 'error' => $message]);
    exit();
}

function validated_user_clients(PDO $db, int $role, string $rawClients): string {
    $ids = [];
    foreach (preg_split('/\s*,\s*/', trim($rawClients), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $value) {
        if (!ctype_digit($value) || (int)$value <= 0) {
            user_access_error('One or more client assignments are invalid.');
        }
        $ids[] = (int)$value;
    }
    $ids = array_values(array_unique($ids));
    if (in_array($role, [1, 2, 3], true)) {
        return '';
    }
    if (!$ids) {
        user_access_error('A client assignment is required for Coordinator and C&B users.');
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT client_id FROM taascor_client
         WHERE client_name <> 'No Client' AND client_id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($validIds);
    sort($ids);
    if ($validIds !== $ids) {
        user_access_error('One or more selected clients are unavailable.');
    }
    return implode(',', $ids);
}

$request = (string)($_POST['request'] ?? '');

if($request == 'get-user-list'){
    $response['data'] = [];

    $getList = $model->getUserList();

    if(isset($getList['error']) == false){

        if(count($getList['data']) > 0){
            foreach ($getList['data'] as $key => $row) {
                $access_level = $row['access_level'];
                $is_active = $row['is_active'];
                $id = $row['id'];
                $client = $row['client'];

                $status = "ACTIVATED";
                if(!$is_active){
                    $status = "PENDING";
                }

                $action = "<button id='updateBtn'  class='btn btn-sm btn-primary' value='$id'
                                data-al='$access_level' data-ia='$is_active' data-ci='$client'><i class='bx bx-pencil'></i></button>
                          <button id='deleteBtn' class='btn btn-sm btn-danger' value='$id'><i class='bx bx-trash-alt'></i></button>";

                $response['data'][] = array(
                    $action
                    ,htmlspecialchars((string)$row['employee_user_name'], ENT_QUOTES, 'UTF-8')
                    ,htmlspecialchars((string)$row['employee_full_name'], ENT_QUOTES, 'UTF-8')
                    ,htmlspecialchars((string)$row['employee_email'], ENT_QUOTES, 'UTF-8')
                    ,htmlspecialchars((string)$row['access_description'], ENT_QUOTES, 'UTF-8')
                    ,$status
                );
            }
        }   
    } else{
        $response['error'] = $getList['error'];
    }

    echo json_encode($response);

}else if($request == 'delete-user'){
    $model->id = $_POST['id'];
    $result = $model->deleteUser();
    if(($result['success'] ?? 0) == 1) log_action("User Deleted: ID {$_POST['id']}");
    echo json_encode($result);
}else if($request == 'update-user'){
    $roles = [1 => 'Admin', 2 => 'HR', 3 => 'Payroll', 4 => 'Coordinator', 5 => 'C&B'];
    $id = (int)($_POST['id'] ?? 0);
    $isActive = (string)($_POST['is_active'] ?? '');
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $role = (int)($_POST['user_role'] ?? 0);
    if ($id <= 0 || !in_array($isActive, ['0', '1'], true)) {
        user_access_error('The user record is invalid.');
    }
    if ($fullName === '' || mb_strlen($fullName) > 160) {
        user_access_error('A valid full name is required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        user_access_error('A valid email address is required.');
    }
    if (!isset($roles[$role])) {
        user_access_error('Select a valid user role.');
    }
    $model->id = $id;
    $model->is_active = $isActive;
    $model->full_name = $fullName;
    $model->email = $email;
    $model->user_role = $role;
    $model->user_role_txt = $roles[$role];
    $model->client = validated_user_clients($pdoConn, $role, (string)($_POST['client'] ?? ''));
    $result = $model->updateUser();
    if(($result['success'] ?? 0) == 1) {
        $action = $model->is_active == '0' ? 'User Activated' : 'User Updated';
        log_action("$action: ID {$model->id} | Role: {$model->user_role_txt}");
    }
    echo json_encode($result);
}else if($request == 'get-client-location'){
    echo json_encode($model->getClientLocation());
}else {
    http_response_code(400);
    echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
}

       
    


?>
