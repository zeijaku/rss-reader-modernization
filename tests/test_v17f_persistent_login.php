<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/session.php';
require_once $root . '/app/remember_token.php';
require_once $root . '/app/persistent_login.php';

final class V17fStatement extends PDOStatement
{
    private array $rows = [];
    private int $position = 0;
    private int $affected = 0;

    public function __construct(private V17fPDO $pdo, private string $sql) {}

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

final class V17fPDO extends PDO
{
    public array $users = [1 => ['user_id' => 1, 'user_flag' => 0]];
    public array $tokens = [];
    public int $nextTokenId = 1;
    private bool $transaction = false;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new V17fStatement($this, $query); }
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

$pdo = new V17fPDO();
set_db_connection_for_testing($pdo);
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';

$check(persistent_login_is_requested('1'), 'Explicit checkbox value enables persistent login');
$check(!persistent_login_is_requested('on') && !persistent_login_is_requested(['1']), 'Unexpected or malformed checkbox values are rejected');
$options = persistent_login_cookie_options(time() + 60);
$check($options['secure'] === true && $options['httponly'] === true && $options['samesite'] === 'Lax', 'Remember cookie is Secure on HTTPS, HttpOnly and SameSite=Lax');
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
$check(persistent_login_cookie_options(time() + 60)['secure'] === false, 'Local HTTP development does not emit an unusable Secure cookie');
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';

$sessionPath = app_session_storage_path();
if (!is_dir($sessionPath)) { mkdir($sessionPath, 0700, true); }
app_session_configure();
session_id('v17f' . bin2hex(random_bytes(12)));
$check(session_start(), 'Test session starts with application cookie settings');
app_csrf_token();

$check(persistent_login_issue_for_user(1), 'Credential login can issue a persistent token and cookie');
$firstCookie = persistent_login_cookie_value();
$check(is_string($firstCookie) && remember_token_parse($firstCookie) !== null, 'Issued browser cookie uses strict selector.validator format');
$check(count($pdo->tokens) === 1, 'Issued persistent login creates one database token');

app_session_clear_authentication();
$oldSessionId = session_id();
$check(persistent_login_restore_session(), 'Valid Remember Token restores an anonymous session');
$check(app_session_user_id() === 1, 'Restored session is authenticated as the token owner');
$check(session_id() !== $oldSessionId, 'Automatic login regenerates the session identifier');
$rotatedCookie = persistent_login_cookie_value();
$check(is_string($rotatedCookie) && $rotatedCookie !== $firstCookie, 'Automatic login rotates the browser validator');
$check(count($pdo->tokens) === 1, 'Validator rotation keeps one current-browser token');

$check(persistent_login_issue_for_user(1), 'Opted-in credential login can replace the current-browser token');
$check(count($pdo->tokens) === 1, 'Replacing a valid current-browser token does not accumulate tokens');
persistent_login_revoke_current();
$check($pdo->tokens === [] && persistent_login_cookie_value() === null, 'Logout-style revocation removes the database token and browser cookie');

app_session_clear_authentication();
$_COOKIE[PERSISTENT_LOGIN_COOKIE_NAME] = 'invalid-cookie';
$check(!persistent_login_restore_session(), 'Malformed Remember Cookie fails closed');
$check(persistent_login_cookie_value() === null && app_session_user_id() === null, 'Malformed cookie is cleared without authenticating');

session_write_close();
set_db_connection_for_testing(null);

$passed = 0;
foreach ($results as [$ok, $message]) {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if ($ok) { $passed++; }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
