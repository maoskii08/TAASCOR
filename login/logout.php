<?php
require_once('../includes/session_security.php');
taascor_start_secure_session();
require('../config/db_connect.php');

// Log the logout before destroying the session
$username = $_SESSION['taascor_user_name'] ?? 'unknown';
try {
    $stmt = $pdoConn->prepare(
        "INSERT INTO logs (username, log_action, inserted_date_time_ph) VALUES (?, 'Logout', NOW())"
    );
    $stmt->execute([$username]);
} catch (\Throwable $ignored) {}

taascor_destroy_session();
header('Location: ./');
exit();
