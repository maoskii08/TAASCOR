<?php
require_once(__DIR__ . '/../legacy-route-disabled.php');
session_start();
require('../config/db_connect.php');

// Log the logout before destroying the session
$username = $_SESSION['taascor_user_name'] ?? 'unknown';
try {
    $stmt = $pdoConn->prepare(
        "INSERT INTO logs (username, log_action, inserted_date_time_ph) VALUES (?, 'Logout', NOW())"
    );
    $stmt->execute([$username]);
} catch (\Throwable $ignored) {}

session_unset();
session_destroy();
header('Location: ./');
exit();
