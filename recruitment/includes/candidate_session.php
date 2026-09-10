<?php

declare(strict_types=1);

const RECRUITMENT_CANDIDATE_IDLE_TIMEOUT = 1800;
const RECRUITMENT_CANDIDATE_ABSOLUTE_TIMEOUT = 14400;

function recruitment_candidate_cookie_name(): string
{
    return 'TAASCOR_CANDIDATE_SESSION';
}

function recruitment_candidate_cookie_path(string $scriptName): string
{
    $script = '/' . ltrim(str_replace('\\', '/', $scriptName), '/');
    $marker = '/recruitment/candidate/';
    $position = stripos($script, $marker);
    if ($position === false) {
        return '/recruitment/candidate/';
    }

    return substr($script, 0, $position) . $marker;
}

/** @return array{lifetime:int,path:string,secure:bool,httponly:bool,samesite:string} */
function recruitment_candidate_cookie_options(string $scriptName, bool $secure): array
{
    return [
        'lifetime' => 0,
        'path' => recruitment_candidate_cookie_path($scriptName),
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function recruitment_candidate_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== recruitment_candidate_cookie_name()) {
            throw new RuntimeException('A different authentication realm is already active.');
        }
        return;
    }

    $secure = filter_var(getenv('TAASCOR_SESSION_SECURE') ?: false, FILTER_VALIDATE_BOOLEAN)
        || strtolower((string)($_SERVER['HTTPS'] ?? '')) === 'on'
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(recruitment_candidate_cookie_name());
    session_set_cookie_params(recruitment_candidate_cookie_options(
        (string)($_SERVER['SCRIPT_NAME'] ?? '/recruitment/candidate/index.php'),
        $secure
    ));
    session_start();
}

function recruitment_candidate_complete_login(int $candidateId, int $sessionVersion): void
{
    if ($candidateId <= 0 || $sessionVersion <= 0) {
        throw new InvalidArgumentException('A valid candidate and session version are required.');
    }

    recruitment_candidate_start_session();
    session_regenerate_id(true);
    $_SESSION = [
        'recruitment_candidate_id' => $candidateId,
        'recruitment_session_version' => $sessionVersion,
        'recruitment_session_started_at' => time(),
        'recruitment_last_activity_at' => time(),
    ];
}

function recruitment_candidate_is_authenticated(): bool
{
    recruitment_candidate_start_session();
    $now = time();
    $candidateId = (int)($_SESSION['recruitment_candidate_id'] ?? 0);
    $startedAt = (int)($_SESSION['recruitment_session_started_at'] ?? 0);
    $lastActivityAt = (int)($_SESSION['recruitment_last_activity_at'] ?? 0);

    if (
        $candidateId <= 0
        || $startedAt <= 0
        || $lastActivityAt <= 0
        || ($now - $lastActivityAt) > RECRUITMENT_CANDIDATE_IDLE_TIMEOUT
        || ($now - $startedAt) > RECRUITMENT_CANDIDATE_ABSOLUTE_TIMEOUT
    ) {
        return false;
    }

    $_SESSION['recruitment_last_activity_at'] = $now;
    return true;
}

function recruitment_candidate_destroy_session(): void
{
    recruitment_candidate_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string)$cookie['path'],
            'secure' => (bool)$cookie['secure'],
            'httponly' => true,
            'samesite' => (string)($cookie['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
}
