<?php
require_once('../../includes/session_security.php');
taascor_start_secure_session();
require_once('../../includes/csrf.php');

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
require('../../config/db_connect.php');
require('../../API/login/Login.php');
require('../../API/logs/log.php');
header('content-type: application/json');
$pathInPieces = explode('\\', __DIR__); 

function safe_login_return_path(mixed $value): string
{
    $path = is_string($value) ? trim($value) : '';
    if (
        $path === ''
        || !str_starts_with($path, '/')
        || str_starts_with($path, '//')
        || preg_match('/[\r\n]/', $path)
    ) {
        return '';
    }
    return $path;
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    csrf_validate();
    if(isset($_POST)){
        $response = false;
        $login = new LoginClass;
        $login->db = $pdoConn;

        $log = new Logs;
        $log->db = $pdoConn;

        $login->userNT = $_POST['user_name'];
        $login->password = $_POST['user_pass'];
        $response = $login->login();
        if($response == true){
            taascor_complete_login();

            $log->log_action = 'Login';
            $log->username = $_SESSION["taascor_user_name"];
            $log->insertLog();

            $nextPath = safe_login_return_path($_POST['next_path'] ?? '');
            if ($nextPath !== '') {
                header('Location: ' . $nextPath);
            } elseif($_SESSION['taascor_access_level'] <= 2){
                header("Location: ../../dashboard/");
            }else{
                header("Location: ../../employee-management/");
            }
            session_write_close();
            exit();
        } else {
            taascor_clear_authentication();
            $_SESSION['error'] = 'Invalid Credentials';
            header("Location: ../");
            session_write_close();
            exit();
        }

    }
}
?>
