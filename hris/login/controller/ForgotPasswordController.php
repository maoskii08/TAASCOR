<?php
require_once(__DIR__ . '/../../legacy-route-disabled.php');
/**
 * ForgotPasswordController.php
 * Handles: forgot-password (send email) + reset-password (set new pass)
 * Public endpoint — no auth required.
 */
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header('content-type: application/json');

require('../../config/db_connect.php');

$request = $_POST['request'] ?? '';

// ── Ensure reset columns exist (run once, safe on repeat) ─────────────────
function ensureResetColumns($pdo): void {
    try { $pdo->exec("ALTER TABLE taascor_user_access ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL"); } catch (\Throwable $e) {}
    try { $pdo->exec("ALTER TABLE taascor_user_access ADD COLUMN reset_expires DATETIME DEFAULT NULL"); } catch (\Throwable $e) {}
}

// ── REQUEST: send reset email ──────────────────────────────────────────────
if ($request === 'forgot-password') {
    $username = trim($_POST['username'] ?? '');

    if (!$username) {
        echo json_encode(['success' => 0, 'error' => 'Please enter your username.']);
        exit();
    }

    ensureResetColumns($pdoConn);

    // Look up user
    $stmt = $pdoConn->prepare(
        "SELECT id, employee_user_name, employee_email, is_active
         FROM taascor_user_access
         WHERE employee_user_name = :u LIMIT 1"
    );
    $stmt->bindParam(':u', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always show success (don't reveal if username exists)
    if (!$user || !$user['is_active']) {
        echo json_encode([
            'success' => 1,
            'message' => 'If that username exists, a reset link has been sent to the registered email.'
        ]);
        exit();
    }

    if (empty($user['employee_email'])) {
        echo json_encode([
            'success' => 0,
            'error'   => 'No email address is registered for this account. Please contact your Administrator.'
        ]);
        exit();
    }

    // Generate token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $upd = $pdoConn->prepare(
        "UPDATE taascor_user_access SET reset_token = :t, reset_expires = :e WHERE id = :id"
    );
    $upd->execute([':t' => $token, ':e' => $expires, ':id' => $user['id']]);

    // Build reset link
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $pathInfo = dirname(dirname($_SERVER['PHP_SELF'])); // → /hris
    $link     = "$protocol://$host$pathInfo/login/reset-password.php?token=$token";

    // Send email
    $to      = $user['employee_email'];
    $subject = 'TAASCOR HRIS — Password Reset Request';
    $body    = "Hello {$user['employee_user_name']},\n\n"
             . "A password reset was requested for your TAASCOR HRIS account.\n\n"
             . "Click the link below to set a new password (valid for 1 hour):\n\n"
             . "$link\n\n"
             . "If you did not request this, please ignore this email.\n\n"
             . "— TAASCOR HRIS";
    $headers = "From: noreply@taascor.visiotechsolutions.com\r\n"
             . "Reply-To: noreply@taascor.visiotechsolutions.com\r\n"
             . "X-Mailer: PHP/" . PHP_VERSION;

    @mail($to, $subject, $body, $headers);

    echo json_encode([
        'success' => 1,
        'message' => 'If that username exists, a reset link has been sent to the registered email.'
    ]);
    exit();
}

// ── REQUEST: validate token ────────────────────────────────────────────────
if ($request === 'validate-token') {
    $token = trim($_POST['token'] ?? '');
    if (!$token) { echo json_encode(['valid' => false]); exit(); }

    ensureResetColumns($pdoConn);

    $stmt = $pdoConn->prepare(
        "SELECT id FROM taascor_user_access
         WHERE reset_token = :t AND reset_expires > NOW() AND is_active = 1 LIMIT 1"
    );
    $stmt->bindParam(':t', $token);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['valid' => (bool)$row]);
    exit();
}

// ── REQUEST: reset password ────────────────────────────────────────────────
if ($request === 'reset-password') {
    $token   = trim($_POST['token']    ?? '');
    $pass    = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm']  ?? '');

    if (!$token || !$pass || !$confirm) {
        echo json_encode(['success' => 0, 'error' => 'All fields are required.']);
        exit();
    }
    if ($pass !== $confirm) {
        echo json_encode(['success' => 0, 'error' => 'Passwords do not match.']);
        exit();
    }
    if (strlen($pass) < 8) {
        echo json_encode(['success' => 0, 'error' => 'Password must be at least 8 characters.']);
        exit();
    }

    ensureResetColumns($pdoConn);

    $stmt = $pdoConn->prepare(
        "SELECT id, employee_user_name FROM taascor_user_access
         WHERE reset_token = :t AND reset_expires > NOW() AND is_active = 1 LIMIT 1"
    );
    $stmt->bindParam(':t', $token);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => 0, 'error' => 'This reset link is invalid or has expired.']);
        exit();
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $upd  = $pdoConn->prepare(
        "UPDATE taascor_user_access SET password_hash = :h, reset_token = NULL, reset_expires = NULL WHERE id = :id"
    );
    $upd->execute([':h' => $hash, ':id' => $user['id']]);

    // Log it
    try {
        $pdoConn->prepare("INSERT INTO logs (username, log_action, inserted_date_time_ph) VALUES (?,?,NOW())")
                ->execute([$user['employee_user_name'], 'Password Reset']);
    } catch (\Throwable $ignored) {}

    echo json_encode(['success' => 1, 'message' => 'Password updated successfully. You can now log in.']);
    exit();
}

echo json_encode(['success' => 0, 'error' => 'Unknown request.']);
