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

require_once(__DIR__ . '/session_security.php');
taascor_start_secure_session();

require_once(__DIR__ . '/csrf.php');

// ── Timeout constants ──────────────────────────────────────────────────────
define('SESSION_IDLE_TIMEOUT', 1800);  // 30 minutes idle
define('SESSION_ABS_TIMEOUT',  28800); // 8 hours absolute

// ── Internal helpers ───────────────────────────────────────────────────────

function _auth_app_base_url(): string {
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $projectRoot = str_replace('\\', '/', (string)(realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
    $scriptFile = str_replace('\\', '/', (string)(realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: ($_SERVER['SCRIPT_FILENAME'] ?? '')));

    if ($scriptFile !== '' && str_starts_with(strtolower($scriptFile), strtolower($projectRoot . '/'))) {
        $relativeScript = substr($scriptFile, strlen($projectRoot));
        if ($relativeScript !== '' && str_ends_with(strtolower($scriptName), strtolower($relativeScript))) {
            $appBasePath = substr($scriptName, 0, -strlen($relativeScript));
            return rtrim($appBasePath, '/');
        }
    }

    $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
    if (strtolower(basename($scriptDirectory)) === 'controller') {
        $scriptDirectory = dirname($scriptDirectory);
    }
    $appBasePath = dirname($scriptDirectory);
    $appBasePath = rtrim($appBasePath, '/.');
    return $appBasePath === '' ? '' : '/' . ltrim($appBasePath, '/');
}

function _auth_is_page_request(): bool {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return false;
    }

    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

    return $requestedWith !== 'xmlhttprequest'
        && !str_contains($accept, 'application/json')
        && (basename($script) === 'index.php' || str_ends_with($script, '/'));
}

function _auth_safe_return_path(): string {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (
        $requestUri === ''
        || !str_starts_with($requestUri, '/')
        || str_starts_with($requestUri, '//')
        || preg_match('/[\r\n]/', $requestUri)
    ) {
        return '';
    }
    return $requestUri;
}

function _auth_login_url(bool $includeReturn = false): string {
    $url = _auth_app_base_url() . '/login/';
    $returnPath = $includeReturn ? _auth_safe_return_path() : '';
    return $returnPath === '' ? $url : $url . '?next=' . rawurlencode($returnPath);
}

function _auth_send_401(string $msg): void {
    $isPageRequest = _auth_is_page_request();
    $loginUrl = _auth_login_url($isPageRequest);
    if ($isPageRequest) {
        header('Location: ' . $loginUrl);
        exit();
    }

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success'  => 0,
        'error'    => $msg,
        'redirect' => $loginUrl,
        'timeout'  => true
    ]);
    exit();
}

function _auth_send_403(string $msg): void {
    if (_auth_is_page_request()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Access denied</title>'
            . '<body><h1>Access denied</h1><p>'
            . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
            . '</p><p><a href="'
            . htmlspecialchars(_auth_app_base_url() . '/dashboard/', ENT_QUOTES, 'UTF-8')
            . '">Return to dashboard</a></p></body></html>';
        exit();
    }

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
        _auth_send_401('Unauthorized. Please log in.');
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
        _auth_send_401('Unauthorized. Please log in.');
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

/** Admin, HR, and Payroll operate across all clients; page permissions separate their duties. */
function auth_has_global_client_access(): bool {
    return in_array(auth_level(), [1, 2, 3], true);
}

/** @return int[] Client IDs assigned to the authenticated user. */
function auth_client_ids(): array {
    $raw = (string)($_SESSION['taascor_client'] ?? '');
    $ids = [];
    foreach (preg_split('/\s*,\s*/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $value) {
        if (ctype_digit($value) && (int)$value > 0) {
            $ids[] = (int)$value;
        }
    }
    return array_values(array_unique($ids));
}

function auth_can_access_client_id(int $clientId): bool {
    if ($clientId <= 0) {
        return false;
    }
    return auth_has_global_client_access()
        || in_array($clientId, auth_client_ids(), true);
}

function auth_require_client_id(int $clientId): void {
    if (!auth_can_access_client_id($clientId)) {
        _auth_send_403('Access denied for the requested client.');
    }
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
