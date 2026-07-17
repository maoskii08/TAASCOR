<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
require('../../config/db_connect.php');
require('../../API/login/Login.php');
require('../../API/logs/log.php');
header('content-type: application/json');
$pathInPieces = explode('\\', __DIR__); 

if($_SERVER['REQUEST_METHOD'] == 'POST'){
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

            $log->log_action = 'Login';
            $log->username = $_SESSION["taascor_user_name"];
            $log->insertLog();

            if($_SESSION['taascor_access_level'] <= 2){
                header("Location: ../../dashboard/");
            }else{
                header("Location: ../../employee-management/");
            }
            session_write_close();
            exit();
        } else {
            $_SESSION['error'] = 'Invalid Credentials';
            header("Location: ../");
            session_write_close();
            exit();
        }

    }
}
?>