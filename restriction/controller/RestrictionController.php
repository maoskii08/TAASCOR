<?php
require_once('../../includes/auth_guard.php');
auth_require_login();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
header('content-type: application/json');

require_once('../../config/page-permissions.php'); // $pages, $page_map, $role_names

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $accessLevel = (string)auth_level();
    $currentPage = filter_var($_POST['page'] ?? null, FILTER_VALIDATE_INT);
    $pageIds = $pages[$accessLevel] ?? [];
    $hasAccess = $currentPage !== false
        && $currentPage !== null
        && in_array((int)$currentPage, $pageIds, true);

    $data = array(
        'page' => $currentPage,
        'access_level' => (int)$accessLevel,
        'session_access_level' => auth_level(),
        'page_id' => $pageIds,
        'hasAccess' => $hasAccess,
        'url' => '../login/'
    );

    $json = array(
        "recordsTotal" => number_format(count($data)),
        "data" => $data,
    );

    echo json_encode($json);
}
?>
