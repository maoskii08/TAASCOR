<?php
require_once(__DIR__ . '/../includes/session_security.php');
require_once(__DIR__ . '/../API/login/Login.php');

final class FakeLoginStatement
{
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function bindParam(string $name, mixed &$value, int $type = 0): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchAll(int $mode = 0): array
    {
        return $this->rows;
    }
}

final class FakeLoginDatabase
{
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function prepare(string $sql): FakeLoginStatement
    {
        return new FakeLoginStatement($this->rows);
    }
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
taascor_start_secure_session();

$cookie = session_get_cookie_params();
$assert((bool)$cookie['httponly'], 'session cookie must be HttpOnly');
$assert(($cookie['samesite'] ?? '') === 'Lax', 'session cookie must use SameSite=Lax');
$assert($cookie['path'] === '/', 'session cookie must be available to the full application');
$assert(ini_get('session.use_strict_mode') === '1', 'strict session mode must be enabled');

$user = [
    'employee_user_name' => 'test.user',
    'first_name' => 'Test',
    'employee_full_name' => 'Test User',
    'access_level' => 1,
    'access_description' => 'Admin',
    'employee_email' => 'test@example.invalid',
    'client' => '264',
    'password_hash' => password_hash('Correct-Horse-42!', PASSWORD_BCRYPT),
];

$login = new LoginClass();
$login->db = new FakeLoginDatabase([$user]);
$login->userNT = 'test.user';
$login->password = 'wrong-password';

$assert($login->login() === false, 'wrong password must fail authentication');
$assert(!isset($_SESSION['taascor_user_name']), 'wrong password must not retain a username');
$assert(!isset($_SESSION['taascor_access_level']), 'wrong password must not retain an access level');
$assert(!isset($_SESSION['taascor_client']), 'wrong password must not retain a client scope');

$login->password = 'Correct-Horse-42!';
$assert($login->login() === true, 'correct password must authenticate');
$assert(($_SESSION['taascor_user_name'] ?? '') === 'test.user', 'successful login must set username');
$assert((int)($_SESSION['taascor_access_level'] ?? 0) === 1, 'successful login must set access level');
$assert(($_SESSION['taascor_client'] ?? '') === '264', 'successful login must set client scope');

$sessionBefore = session_id();
taascor_complete_login();
$sessionAfter = session_id();
$assert($sessionBefore !== $sessionAfter, 'successful login must rotate the session id');
$assert(isset($_SESSION['session_start'], $_SESSION['last_activity']), 'successful login must initialize timeouts');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "RESULT: Login authentication and session security checks passed.\n";
