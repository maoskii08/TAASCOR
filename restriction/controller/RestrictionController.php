<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');

require_once('../../config/db_connect.php');
require_once('../../config/page-permissions.php'); // $pages, $page_map, $role_names
require_once('../../API/restriction/Restriction.php');

$restriction = new Restriction;
$restriction->db = $pdoConn;

$restriction->user_name    = $_SESSION['taascor_user_name'];
$session_access_level      = $_SESSION['taascor_access_level'];
session_write_close(); // release lock so parallel data requests don't queue

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $access = false;
    $pathInPieces = explode('\\', __DIR__);
    $user = $restriction->getAccess();
    $access_level = $user['access_level'] ?? "";

    $current_page = $_POST['page'];

    if($access_level != ""){
        $hasAccess = in_array($current_page, $pages[$access_level]);

        if($hasAccess && 
            $access_level == $session_access_level 
            ){
            $access = true;
        }

        $page_id = $pages[$access_level];
    }else{
        $access = false;
    }

    $data = array(
        'page' => $current_page,
        'access_level' => $access_level,
        'session_access_level' => $session_access_level,
        'page_id' => $pages[$access_level], 
        'hasAccess' => $access,
        'url' => '../login/'
    );

    $json = array(
        "recordsTotal" => number_format(count($data)),
        "data" => $data,
    );

    echo json_encode($json);
}
?>