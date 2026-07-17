<?php
/**
 * auth_guard.php — server-side authentication, authorization,
 * session timeout, CSRF validation, and audit logging.
 *
 * Include at the TOP of every controller/API.
 *
 * Usage:
 *   require_once('../../includes/auth_guard.php');
 *   auth_require_login();              // must be logged in
 *   auth_require_role([1, 2]);         // must be logged in + correct role
 *
 * Access levels: 1=Admin  2=HR  3=Payroll  4=Coordinator  5=C&B
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/csrf.php');

// ── Timeout constants ──────────────────────────────────────────────────────
define('SESSION_IDLE_TIMEOUT', 1800);  // 30 minutes idle
define('SESSION_ABS_TIMEOUT',  28800); // 8 hours absolute

// ── Internal helpers ───────────────────────────────────────────────────────

function _auth_send_401(string $msg): void {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success'  => 0,
        'error'    => $msg,
        'redirect' => '../login/',
        'timeout'  => true
    ]);
    exit();
}

function _auth_send_403(string $msg): void {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => 0, 'error' => $msg]);
    exit();
}

// ── Session timeout check ──────────────────────────────────────────────────
function auth_check_timeout(): void {
    $now = time();

    if (isset($_SESSION['last_activity'])) {
        if (($now - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
            session_unset(); session_destroy();
            _auth_send_401('Your session has expired due to inactivity. Please log in again.');
        }
    }

    if (isset($_SESSION['session_start'])) {
        if (($now - $_SESSION['session_start']) > SESSION_ABS_TIMEOUT) {
            session_unset(); session_destroy();
            _auth_send_401('Your session has expired. Please log in again.');
        }
    }

    // Refresh idle timer
    $_SESSION['last_activity'] = $now;
    if (!isset($_SESSION['session_start'])) {
        $_SESSION['session_start'] = $now;
    }
}

// ── Public API ─────────────────────────────────────────────────────────────

/** Abort with 403 if not logged in. */
function auth_require_login(): void {
    if (
        !isset($_SESSION['taascor_access_level']) ||
        !isset($_SESSION['taascor_user_name'])
    ) {
        _auth_send_403('Unauthorized. Please log in.');
    }
    auth_check_timeout();
    csrf_validate();
    // Release session lock so parallel AJAX requests don't queue behind each other
    session_write_close();
}

/**
 * Abort with 403 if role not in $allowed_levels.
 * @param int[] $allowed_levels  e.g. [1, 3]
 */
function auth_require_role(array $allowed_levels): void {
    if (
        !isset($_SESSION['taascor_access_level']) ||
        !isset($_SESSION['taascor_user_name'])
    ) {
        _auth_send_403('Unauthorized. Please log in.');
    }
    auth_check_timeout();
    csrf_validate();

    $level = (int) $_SESSION['taascor_access_level'];
    if (!in_array($level, $allowed_levels, true)) {
        _auth_send_403('Access denied. You do not have permission for this action.');
    }
    // Release session lock so parallel AJAX requests don't queue behind each other
    session_write_close();
}

/** Current user's access level (int). */
function auth_level(): int {
    return (int) ($_SESSION['taascor_access_level'] ?? 0);
}

/** Current username. */
function auth_user(): string {
    return (string) ($_SESSION['taascor_user_name'] ?? '');
}

/**
 * Write an audit log entry.
 * Uses the global $pdoConn if no $db provided.
 * Silent on failure — never breaks the main request.
 */
function log_action(string $action, $db = null): void {
    global $pdoConn;
    $conn = $db ?? $pdoConn ?? null;
    if (!$conn) return;
    $user = auth_user() ?: 'system';
    try {
        $stmt = $conn->prepare(
            "INSERT INTO logs (username, log_action, inserted_date_time_ph) VALUES (?, ?, NOW())"
        );
        $stmt->execute([$user, $action]);
    } catch (\Throwable $ignored) {}
}
