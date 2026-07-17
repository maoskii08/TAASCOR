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
 * Validate the CSRF token.
 *
 * Strategy: ONLY block if a token is submitted AND it's wrong.
 * If no token is submitted, allow through (backward compatible with
 * existing JS that runs before hris-global.js sets up the header).
 * Once hris-global.js is loaded on the page, ALL subsequent AJAX calls
 * will include the token and get fully validated.
 */
function csrf_validate(): void {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!$sessionToken) return; // token not generated yet — skip

    $submitted = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';

    // No token submitted — allow (backward compat with page-load AJAX calls
    // that fire before hris-global.js sets up the X-CSRF-Token header)
    if (!$submitted) return;

    // Token submitted but WRONG — block
    if (!hash_equals($sessionToken, $submitted)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => 0,
            'error'   => 'Invalid request. Please refresh the page and try again.'
        ]);
        exit();
    }
    // Token submitted and correct — allow
}
