<?php
/**
 * Public password-reset endpoint.
 *
 * Required runtime settings:
 *   TAASCOR_APP_URL      Canonical application origin/base path.
 *   TAASCOR_SECURITY_KEY Random secret of at least 32 characters.
 */
require_once('../../includes/session_security.php');
taascor_start_secure_session();
require_once('../../includes/csrf.php');
csrf_validate();

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

require('../../config/db_connect.php');

function resetSecurityReady(PDO $db): bool
{
    try {
        $columns = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'taascor_user_access'
               AND COLUMN_NAME IN ('reset_token', 'reset_expires')"
        );
        $columns->execute();
        $table = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'password_reset_attempts'"
        );
        $table->execute();
        return (int)$columns->fetchColumn() === 2 && (int)$table->fetchColumn() === 1;
    } catch (Throwable $error) {
        error_log('Password reset schema preflight failed: ' . $error->getMessage());
        return false;
    }
}

function resetSecurityKey(): ?string
{
    $key = trim((string)getenv('TAASCOR_SECURITY_KEY'));
    return strlen($key) >= 32 ? $key : null;
}

function canonicalAppUrl(): ?string
{
    $url = rtrim(trim((string)getenv('TAASCOR_APP_URL')), '/');
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $local = in_array($host, ['127.0.0.1', 'localhost'], true);
    return $scheme === 'https' || ($local && $scheme === 'http') ? $url : null;
}

function resetFingerprint(string $value, string $key): string
{
    return hash_hmac('sha256', strtolower(trim($value)), $key);
}

function recordResetAttempt(
    PDO $db,
    string $usernameHash,
    string $ipHash,
    string $outcome
): void {
    $stmt = $db->prepare(
        "INSERT INTO password_reset_attempts
            (username_hash, ip_hash, outcome, requested_at)
         VALUES (:username_hash, :ip_hash, :outcome, NOW())"
    );
    $stmt->execute([
        ':username_hash' => $usernameHash,
        ':ip_hash' => $ipHash,
        ':outcome' => $outcome,
    ]);
}

function resetRateLimited(PDO $db, string $usernameHash, string $ipHash): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM password_reset_attempts
         WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
           AND (username_hash = :username_hash OR ip_hash = :ip_hash)"
    );
    $stmt->execute([
        ':username_hash' => $usernameHash,
        ':ip_hash' => $ipHash,
    ]);
    return (int)$stmt->fetchColumn() >= 5;
}

function genericResetResponse(): void
{
    echo json_encode([
        'success' => 1,
        'message' => 'If that username exists, a reset link has been sent to the registered email.',
    ]);
    exit;
}

function validNewPassword(string $password): bool
{
    return strlen($password) >= 12
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^a-zA-Z0-9]/', $password);
}

$request = (string)($_POST['request'] ?? '');
if (!resetSecurityReady($pdoConn)) {
    http_response_code(503);
    echo json_encode([
        'success' => 0,
        'error' => 'Password reset is not available right now. Please contact your HRIS administrator.',
    ]);
    exit;
}

if ($request === 'forgot-password') {
    $username = trim((string)($_POST['username'] ?? ''));
    $securityKey = resetSecurityKey();
    $appUrl = canonicalAppUrl();
    if ($username === '' || $securityKey === null || $appUrl === null) {
        if ($securityKey === null || $appUrl === null) {
            error_log('Password reset runtime configuration is incomplete.');
        }
        genericResetResponse();
    }

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $usernameHash = resetFingerprint($username, $securityKey);
    $ipHash = resetFingerprint($ip, $securityKey);
    if (resetRateLimited($pdoConn, $usernameHash, $ipHash)) {
        recordResetAttempt($pdoConn, $usernameHash, $ipHash, 'rate_limited');
        http_response_code(429);
        echo json_encode([
            'success' => 0,
            'error' => 'Too many reset requests. Please wait 15 minutes and try again.',
        ]);
        exit;
    }

    $stmt = $pdoConn->prepare(
        "SELECT id, employee_user_name, employee_email
         FROM taascor_user_access
         WHERE employee_user_name = :username AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || trim((string)$user['employee_email']) === '') {
        recordResetAttempt($pdoConn, $usernameHash, $ipHash, 'not_deliverable');
        genericResetResponse();
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $update = $pdoConn->prepare(
        "UPDATE taascor_user_access
         SET reset_token = :token_hash, reset_expires = :expires
         WHERE id = :id"
    );
    $update->execute([
        ':token_hash' => $tokenHash,
        ':expires' => $expires,
        ':id' => (int)$user['id'],
    ]);

    $link = $appUrl . '/login/reset-password.php?token=' . rawurlencode($token);
    $to = (string)$user['employee_email'];
    $subject = 'TAASCOR HRIS - Password Reset Request';
    $body = "Hello {$user['employee_user_name']},\n\n"
        . "A password reset was requested for your TAASCOR HRIS account.\n\n"
        . "Open this one-time link within 1 hour:\n{$link}\n\n"
        . "If you did not request this, ignore this email and notify your administrator.\n";
    $from = trim((string)getenv('TAASCOR_MAIL_FROM')) ?: 'noreply@taascor.visiotechsolutions.com';
    $headers = "From: {$from}\r\nReply-To: {$from}\r\nX-Mailer: PHP/" . PHP_VERSION;

    if (!mail($to, $subject, $body, $headers)) {
        $pdoConn->prepare(
            'UPDATE taascor_user_access SET reset_token = NULL, reset_expires = NULL WHERE id = :id'
        )->execute([':id' => (int)$user['id']]);
        recordResetAttempt($pdoConn, $usernameHash, $ipHash, 'delivery_failed');
        error_log('Password reset email delivery failed for account id ' . (int)$user['id']);
        genericResetResponse();
    }

    recordResetAttempt($pdoConn, $usernameHash, $ipHash, 'sent');
    genericResetResponse();
}

if ($request === 'validate-token') {
    $token = trim((string)($_POST['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        echo json_encode(['valid' => false]);
        exit;
    }
    $stmt = $pdoConn->prepare(
        "SELECT id FROM taascor_user_access
         WHERE reset_token = :token_hash
           AND reset_expires > NOW()
           AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([':token_hash' => hash('sha256', $token)]);
    echo json_encode(['valid' => (bool)$stmt->fetchColumn()]);
    exit;
}

if ($request === 'reset-password') {
    $token = trim((string)($_POST['token'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || $password !== $confirm) {
        echo json_encode(['success' => 0, 'error' => 'The reset request is invalid.']);
        exit;
    }
    if (!validNewPassword($password)) {
        echo json_encode([
            'success' => 0,
            'error' => 'Use at least 12 characters with uppercase, lowercase, number, and symbol.',
        ]);
        exit;
    }

    $tokenHash = hash('sha256', $token);
    try {
        $pdoConn->beginTransaction();
        $stmt = $pdoConn->prepare(
            "SELECT id, employee_user_name
             FROM taascor_user_access
             WHERE reset_token = :token_hash
               AND reset_expires > NOW()
               AND is_active = 1
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $pdoConn->rollBack();
            echo json_encode(['success' => 0, 'error' => 'This reset link is invalid or has expired.']);
            exit;
        }

        $update = $pdoConn->prepare(
            "UPDATE taascor_user_access
             SET password_hash = :password_hash, reset_token = NULL, reset_expires = NULL
             WHERE id = :id AND reset_token = :token_hash"
        );
        $update->execute([
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => (int)$user['id'],
            ':token_hash' => $tokenHash,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('The reset token changed before password update.');
        }
        $pdoConn->prepare(
            "INSERT INTO logs (username, log_action, inserted_date_time_ph)
             VALUES (:username, 'Password Reset', NOW())"
        )->execute([':username' => (string)$user['employee_user_name']]);
        $pdoConn->commit();
        echo json_encode([
            'success' => 1,
            'message' => 'Password updated successfully. You can now log in.',
        ]);
    } catch (Throwable $error) {
        if ($pdoConn->inTransaction()) {
            $pdoConn->rollBack();
        }
        error_log('Password reset failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => 0,
            'error' => 'The password could not be updated. Please request a new reset link.',
        ]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => 0, 'error' => 'The password reset request could not be processed.']);
