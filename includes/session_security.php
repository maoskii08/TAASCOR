<?php
/**
 * Central session bootstrap and authentication-session lifecycle helpers.
 *
 * All maintained entry points must call taascor_start_secure_session() instead
 * of calling session_start() directly.
 */

function taascor_request_is_https(): bool
{
    $forced = getenv('TAASCOR_SESSION_SECURE');
    if ($forced !== false && trim((string)$forced) !== '') {
        return filter_var($forced, FILTER_VALIDATE_BOOLEAN);
    }

    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    return ($https !== '' && $https !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** @return array{lifetime:int,path:string,secure:bool,httponly:bool,samesite:string} */
function taascor_session_cookie_options(): array
{
    return [
        'lifetime' => 0,
        'path' => '/',
        'secure' => taascor_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function taascor_start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params(taascor_session_cookie_options());
    session_start();
}

function taascor_clear_authentication(): void
{
    foreach ([
        'taascor_user_name',
        'taascor_first_name',
        'taascor_employee_full_name',
        'taascor_access_level',
        'taascor_access_description',
        'taascor_employee_email',
        'taascor_client',
        'last_activity',
        'session_start',
    ] as $key) {
        unset($_SESSION[$key]);
    }
}

function taascor_complete_login(): void
{
    taascor_start_secure_session();
    session_regenerate_id(true);
    $_SESSION['session_start'] = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
}

function taascor_destroy_session(): void
{
    taascor_start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $options = taascor_session_cookie_options();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $options['path'],
                'secure' => $options['secure'],
                'httponly' => $options['httponly'],
                'samesite' => $options['samesite'],
            ]
        );
    }

    session_destroy();
}
