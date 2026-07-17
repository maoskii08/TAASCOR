<?php
/**
 * CSRF token helper.
 * Included by auth_guard.php and nav-bar.php.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

/** Generate (or retrieve) the session CSRF token. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token from either:
 *  - POST body  ($_POST['csrf_token'])
 *  - X-CSRF-Token request header (for AJAX calls using setRequestHeader)
 * Kills the request with 403 JSON if invalid.
 */
function csrf_validate(): void {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!$sessionToken) return; // first load — token not yet generated, skip

    $submitted = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    if (!hash_equals($sessionToken, $submitted)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => 0,
            'error'   => 'Invalid request. Please refresh the page and try again.'
        ]);
        exit();
    }
}
