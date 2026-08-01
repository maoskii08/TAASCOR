<?php
require_once(__DIR__ . '/../../legacy-route-disabled.php');
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');

require_once('../../config/db_connect.php');
require_once('../../API/restriction/Restriction.php');
 
$restriction = new Restriction;
$restriction->db = $pdoConn;

$restriction->user_name = $_SESSION['taascor_user_name'];
$session_access_level = $_SESSION['taascor_access_level'];

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $access = false;
    $pathInPieces = explode('\\', __DIR__); 
    $user = $restriction->getAccess();
    $access_level = $user['access_level'] ?? "";

    $current_page = $_POST['page'];
    $pages = array(
        "1" => array(
                        1,11,12,13,14,15,16,17,18,19,20,
                        80,81,82,83,84,85,86,87,88,
                        5,51,52,53,54,55,56,
                        3,31,32,33,34,35,36,
                        70,71,72
                    ), //admin — all pages incl. client-management(84) + audit-log(85)
        "2" => array(1,11,12,13,14,15,16,17,18,19,20,
                    80,81,84,87,88), //HR — added client-management(84)
        "3" => array(81,82,83,84,87,3,31,32,33,34,35,36,70,71,72,88,
                    5,51,52,53,54,55,56), //Payroll — added billing(82),accounting(83),client-mgmt(84)
        "4" => array(81,87), //Coordinator
        "5" => array(70,71,72,88) //C&B
    );

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
