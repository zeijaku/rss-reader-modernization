<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');
putenv('SESSION_COOKIE_NAME=iguguru_v132c5_remember');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/session.php';
require_once $root . '/app/remember_token.php';

$v132c5TwoFactorEnabled = true;
/** @return array{enabled:bool} */
function auth_totp_status(int $userId): array
{
    global $v132c5TwoFactorEnabled;
    return ['enabled' => $userId === 1 && $v132c5TwoFactorEnabled === true];
}

require_once $root . '/app/persistent_login.php';

final class V132c5RememberStatement extends PDOStatement
{
    private array $rows = [];
    private int $position = 0;
    private int $affected = 0;

    public function __construct(private V132c5RememberPDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->position = 0;
        $this->affected = 0;
        $sql = preg_replace('/\s+/', ' ', trim($this->sql)) ?? trim($this->sql);

        if (str_starts_with($sql, 'SELECT user_id FROM `ig_user_info`')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $user = $this->pdo->users[$id] ?? null;
            if (is_array($user) && (int) ($user['user_flag'] ?? 1) === 0) {
                $this->rows[] = ['user_id' => $id];
            }
            return true;
        }
        if (str_starts_with($sql, 'INSERT INTO `ig_remember_token`')) {
            $id = $this->pdo->nextTokenId++;
            $this->pdo->tokens[$id] = [
                'remember_token_id' => $id,
                'remember_token_user_id' => (int) $params[':user_id'],
                'remember_token_selector' => (string) $params[':selector'],
                'remember_token_validator_hash' => (string) $params[':validator_hash'],
                'remember_token_created_at' => (string) $params[':created_at'],
                'remember_token_expires_at' => (string) $params[':expires_at'],
                'remember_token_last_used_at' => null,
            ];
            $this->affected = 1;
            return true;
        }
        if (str_starts_with($sql, 'SELECT rt.remember_token_id')) {
            $selector = (string) ($params[':selector'] ?? '');
            foreach ($this->pdo->tokens as $token) {
                if ($token['remember_token_selector'] === $selector) {
                    $user = $this->pdo->users[(int) $token['remember_token_user_id']] ?? null;
                    $this->rows[] = $token + ['user_flag' => is_array($user) ? $user['user_flag'] : null];
                    break;
                }
            }
            return true;
        }
        if (str_starts_with($sql, 'UPDATE `ig_remember_token`')) {
            $id = (int) ($params[':token_id'] ?? 0);
            if (isset($this->pdo->tokens[$id])
                && hash_equals($this->pdo->tokens[$id]['remember_token_validator_hash'], (string) ($params[':previous_hash'] ?? ''))) {
                $this->pdo->tokens[$id]['remember_token_validator_hash'] = (string) $params[':validator_hash'];
                $this->pdo->tokens[$id]['remember_token_last_used_at'] = (string) $params[':last_used_at'];
                $this->affected = 1;
            }
            return true;
        }
        if (str_starts_with($sql, 'DELETE FROM `ig_remember_token` WHERE remember_token_id')) {
            $id = (int) ($params[':token_id'] ?? 0);
            if (isset($this->pdo->tokens[$id])) {
                unset($this->pdo->tokens[$id]);
                $this->affected = 1;
            }
            return true;
        }
        if (str_starts_with($sql, 'DELETE FROM `ig_remember_token` WHERE remember_token_selector')) {
            $selector = (string) ($params[':selector'] ?? '');
            foreach (array_keys($this->pdo->tokens) as $id) {
                if ($this->pdo->tokens[$id]['remember_token_selector'] === $selector) {
                    unset($this->pdo->tokens[$id]);
                    $this->affected++;
                }
            }
            return true;
        }
        if (str_starts_with($sql, 'DELETE FROM `ig_remember_token` WHERE remember_token_user_id')) {
            $userId = (int) ($params[':user_id'] ?? 0);
            foreach (array_keys($this->pdo->tokens) as $id) {
                if ((int) $this->pdo->tokens[$id]['remember_token_user_id'] === $userId) {
                    unset($this->pdo->tokens[$id]);
                    $this->affected++;
                }
            }
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[$this->position++] ?? false;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? null;
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }

    public function rowCount(): int { return $this->affected; }
}

final class V132c5RememberPDO extends PDO
{
    public array $users = [1 => ['user_id' => 1, 'user_flag' => 0]];
    public array $tokens = [];
    public int $nextTokenId = 1;
    private bool $transaction = false;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V132c5RememberStatement($this, $query); }
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}

$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
};

$pdo = new V132c5RememberPDO();
set_db_connection_for_testing($pdo);
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';

app_session_configure();
session_id('v132c5' . bin2hex(random_bytes(10)));
$check(session_start(), 'C5 Remember test session starts');
app_csrf_token();

$check(persistent_login_issue_for_user(1), 'Remember Token can be issued before restoration test');
$originalCookie = persistent_login_cookie_value();
$check(is_string($originalCookie), 'Remember browser cookie exists before restoration');

app_session_clear_authentication();
$anonymousSessionId = session_id();
$check(persistent_login_restore_session(), 'valid Remember Token is accepted for restoration processing');
$check(app_session_user_id() === null && !app_session_is_authenticated(), '2FA-enabled Remember restoration does not authenticate the session');
$check(app_session_has_pending_auth() && app_session_pending_user_id() === 1, '2FA-enabled Remember restoration enters pending for the token owner');
$check(app_session_pending_source() === 'remember', 'Remember restoration records the pending source');
$check(session_id() !== $anonymousSessionId, 'Remember-to-pending transition rotates the session identifier');
$rotatedCookie = persistent_login_cookie_value();
$check(is_string($rotatedCookie) && $rotatedCookie !== $originalCookie, 'Remember validator rotates before the second-factor challenge');

$pendingSessionId = session_id();
$pendingCookie = persistent_login_cookie_value();
$check(!persistent_login_restore_session(), 'automatic Remember restoration is suppressed while already pending');
$check(session_id() === $pendingSessionId && persistent_login_cookie_value() === $pendingCookie, 'suppressed pending restoration does not rotate session or token repeatedly');

persistent_login_revoke_current();
app_session_cancel_pending_auth();
$check($pdo->tokens === [] && persistent_login_cookie_value() === null, 'cancelling a Remember-origin challenge can revoke its persistent token and cookie');
$check(!app_session_has_pending_auth() && app_session_user_id() === null, 'cancelled Remember challenge returns to anonymous state');

// Compatibility: users without 2FA keep the historical direct Remember restoration path.
$v132c5TwoFactorEnabled = false;
$check(persistent_login_issue_for_user(1), 'non-2FA compatibility token can be issued');
app_session_clear_authentication();
$compatBefore = session_id();
$check(persistent_login_restore_session(), 'non-2FA Remember restoration still succeeds');
$check(app_session_user_id() === 1 && app_session_is_authenticated(), 'non-2FA Remember restoration still grants the authenticated session');
$check(session_id() !== $compatBefore, 'non-2FA Remember restoration still rotates the session identifier');

persistent_login_revoke_current();
app_session_logout();
set_db_connection_for_testing(null);

$passed = 0;
foreach ($results as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) { $passed++; }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
