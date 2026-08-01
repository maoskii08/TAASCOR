<?php
/**
 * CSRF token helper.
 * Included by auth_guard.php and nav-bar.php.
 */

require_once(__DIR__ . '/session_security.php');
taascor_start_secure_session();

/** Generate or retrieve the session CSRF token. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Validate the CSRF token for unsafe requests. */
function csrf_validate(): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $submitted = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (!$sessionToken || !$submitted || !hash_equals($sessionToken, $submitted)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => 0,
            'error' => 'Invalid request. Please refresh the page and try again.'
        ]);
        exit();
    }
}
