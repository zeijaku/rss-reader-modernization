<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');
putenv('LOGIN_RATE_MAX_PAIR=3');
putenv('LOGIN_RATE_MAX_IP=30');
putenv('LOGIN_RATE_BLOCK_SECONDS=60');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/login_throttle.php';
require_once $root . '/app/auth.php';
require_once $root . '/app/account_settings.php';
require_once $root . '/app/api.php';

$checks = 0;
$failures = [];
function v11j_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

final class V11jAccountPDO extends PDO
{
    public array $users = [];
    public bool $failEmailUpdate = false;
    public bool $failPasswordUpdate = false;
    private bool $transaction = false;
    private ?array $snapshot = null;

    public function __construct()
    {
    }

    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new V11jAccountStatement($this, $query);
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            return false;
        }
        $this->transaction = true;
        $this->snapshot = $this->users;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        $this->snapshot = null;
        return true;
    }

    public function rollBack(): bool
    {
        if ($this->snapshot !== null) {
            $this->users = $this->snapshot;
        }
        $this->transaction = false;
        $this->snapshot = null;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}

final class V11jAccountStatement extends PDOStatement
{
    private V11jAccountPDO $pdo;
    private string $sql;
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;

    public function __construct(V11jAccountPDO $pdo, string $sql)
    {
        $this->pdo = $pdo;
        $this->sql = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
    }

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;

        if (str_starts_with($this->sql, 'SELECT user_id, user_email, user_password, user_flag FROM ig_user_info')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $row = $this->pdo->users[$id] ?? null;
            if (is_array($row) && (int) $row['user_flag'] === 0) {
                $this->rows = [$row];
            }
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT user_id FROM ig_user_info')) {
            $identity = (string) ($params[':email'] ?? '');
            $exclude = (int) ($params[':user_id'] ?? 0);
            foreach ($this->pdo->users as $id => $row) {
                if ((int) $id !== $exclude && hash_equals((string) $row['user_email'], $identity)) {
                    $this->column = (int) $id;
                    break;
                }
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE ig_user_info SET user_email')) {
            if ($this->pdo->failEmailUpdate) {
                throw new PDOException('forced email update failure');
            }
            $id = (int) ($params[':user_id'] ?? 0);
            $row = $this->pdo->users[$id] ?? null;
            if (is_array($row) && (int) $row['user_flag'] === 0) {
                $this->pdo->users[$id]['user_email'] = (string) $params[':email'];
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE ig_user_info SET user_password')) {
            if ($this->pdo->failPasswordUpdate) {
                throw new PDOException('forced password update failure');
            }
            $id = (int) ($params[':user_id'] ?? 0);
            $row = $this->pdo->users[$id] ?? null;
            if (is_array($row) && (int) $row['user_flag'] === 0) {
                $this->pdo->users[$id]['user_password'] = (string) $params[':password'];
                $this->affected = 1;
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in V1.1-J fixture: ' . $this->sql);
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->column;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

$pdo = new V11jAccountPDO();
$pdo->users = [
    7 => [
        'user_id' => 7,
        'user_email' => auth_identity_key('old@example.com'),
        'user_password' => auth_password_hash('CurrentPass123!'),
        'user_flag' => 0,
    ],
    8 => [
        'user_id' => 8,
        'user_email' => auth_identity_key('used@example.com'),
        'user_password' => auth_password_hash('OtherUserPass123!'),
        'user_flag' => 0,
    ],
    9 => [
        'user_id' => 9,
        'user_email' => auth_identity_key('inactive@example.com'),
        'user_password' => auth_password_hash('InactivePass123!'),
        'user_flag' => 1,
    ],
    10 => [
        'user_id' => 10,
        'user_email' => auth_identity_key('rate@example.com'),
        'user_password' => auth_password_hash('RateLimitPass123!'),
        'user_flag' => 0,
    ],
];
set_db_connection_for_testing($pdo);

v11j_check(account_settings_current_password_is_valid('CurrentPass123!'), 'current password accepts a bounded non-empty value');
v11j_check(!account_settings_current_password_is_valid(''), 'empty current password is rejected');
v11j_check(!account_settings_current_password_is_valid(str_repeat('a', AUTH_PASSWORD_MAX_LENGTH + 1)), 'oversized current password is rejected');
v11j_check(!account_settings_current_password_is_valid("abc\0def"), 'NUL byte in current password is rejected');
v11j_check(account_settings_throttle_identity(7) === account_settings_throttle_identity(7), 'account throttle identity is deterministic');
v11j_check(account_settings_throttle_identity(7) !== account_settings_throttle_identity(8), 'account throttle identity is scoped to the authenticated user');

$invalidEmail = account_settings_change_email(7, 'not-an-email', 'CurrentPass123!');
v11j_check(($invalidEmail['reason'] ?? '') === 'invalid_email', 'invalid email is rejected before database mutation');
$beforeEmail = $pdo->users[7]['user_email'];
$wrongEmailPassword = account_settings_change_email(7, 'new@example.com', 'wrong-password');
v11j_check(($wrongEmailPassword['reason'] ?? '') === 'invalid_current_password', 'email change requires the current password');
v11j_check($pdo->users[7]['user_email'] === $beforeEmail, 'wrong password leaves the email identity unchanged');

$emailResult = account_settings_change_email(7, ' New@Example.COM ', 'CurrentPass123!');
v11j_check(($emailResult['ok'] ?? false) === true, 'email identity can be changed with the current password');
v11j_check($pdo->users[7]['user_email'] === auth_identity_key('new@example.com'), 'new email is normalized and stored as the keyed identity');
v11j_check($pdo->users[7]['user_email'] !== 'new@example.com', 'raw email is not stored in user_info');

$_SERVER['REMOTE_ADDR'] = '192.0.2.11';
$apiOwner = api_dispatch('account.email.update', 7, [
    'user_id' => '8',
    'new_email' => 'owner-check@example.com',
    'current_password' => 'CurrentPass123!',
]);
v11j_check($apiOwner['status'] === 200 && $pdo->users[7]['user_email'] === auth_identity_key('owner-check@example.com'), 'API ignores client user_id and updates only the authenticated user');

$sameEmail = account_settings_change_email(7, 'owner-check@example.com', 'CurrentPass123!');
v11j_check(($sameEmail['reason'] ?? '') === 'email_unchanged', 'same email identity is rejected');
$duplicate = account_settings_change_email(7, 'used@example.com', 'CurrentPass123!');
v11j_check(($duplicate['reason'] ?? '') === 'identity_exists', 'email identity already used by another account is rejected');

$shortPassword = account_settings_change_password(7, 'CurrentPass123!', 'short', 'short');
v11j_check(($shortPassword['reason'] ?? '') === 'invalid_password', 'new password must satisfy the registration bounds');
$mismatch = account_settings_change_password(7, 'CurrentPass123!', 'NewPassword123!', 'DifferentPassword123!');
v11j_check(($mismatch['reason'] ?? '') === 'password_mismatch', 'new password confirmation must match');
$wrongCurrent = account_settings_change_password(7, 'wrong-password', 'NewPassword123!', 'NewPassword123!');
v11j_check(($wrongCurrent['reason'] ?? '') === 'invalid_current_password', 'password change verifies the current password');
$unchanged = account_settings_change_password(7, 'CurrentPass123!', 'CurrentPass123!', 'CurrentPass123!');
v11j_check(($unchanged['reason'] ?? '') === 'password_unchanged', 'password change rejects the current password as the new password');

$otherHashBefore = $pdo->users[8]['user_password'];
$passwordResult = account_settings_change_password(7, 'CurrentPass123!', 'NewPassword123!', 'NewPassword123!');
v11j_check(($passwordResult['ok'] ?? false) === true, 'password can be changed with valid confirmation');
v11j_check(password_verify('NewPassword123!', $pdo->users[7]['user_password']), 'new password is stored with password_hash-compatible output');
v11j_check(!password_verify('CurrentPass123!', $pdo->users[7]['user_password']), 'old password no longer verifies after a change');
v11j_check($pdo->users[8]['user_password'] === $otherHashBefore, 'password change does not affect another user');

$emailBeforeFailure = $pdo->users[7]['user_email'];
$pdo->failEmailUpdate = true;
try {
    account_settings_change_email(7, 'rollback-email@example.com', 'NewPassword123!');
    v11j_check(false, 'email database failure is surfaced');
} catch (PDOException) {
    v11j_check(true, 'email database failure is surfaced');
}
v11j_check($pdo->users[7]['user_email'] === $emailBeforeFailure, 'email transaction rolls back on database failure');
$pdo->failEmailUpdate = false;

$passwordBeforeFailure = $pdo->users[7]['user_password'];
$pdo->failPasswordUpdate = true;
try {
    account_settings_change_password(7, 'NewPassword123!', 'AnotherPassword123!', 'AnotherPassword123!');
    v11j_check(false, 'password database failure is surfaced');
} catch (PDOException) {
    v11j_check(true, 'password database failure is surfaced');
}
v11j_check($pdo->users[7]['user_password'] === $passwordBeforeFailure, 'password transaction rolls back on database failure');
$pdo->failPasswordUpdate = false;

$inactive = account_settings_change_email(9, 'active-now@example.com', 'InactivePass123!');
v11j_check(($inactive['reason'] ?? '') === 'not_found', 'inactive account cannot be changed');
$unauth = api_dispatch('account.email.update', 0, []);
v11j_check($unauth['status'] === 401, 'Account Settings API requires authentication');

// Account Settings failures reuse the existing locked throttle storage.
$_SERVER['REMOTE_ADDR'] = '192.0.2.77';
$throttleIdentity = account_settings_throttle_identity(10);
foreach (['pair' => $throttleIdentity . "\0" . $_SERVER['REMOTE_ADDR'], 'ip' => $_SERVER['REMOTE_ADDR']] as $scope => $value) {
    $path = login_throttle_path($scope, $value);
    if (is_file($path)) {
        @unlink($path);
    }
}
for ($i = 0; $i < LOGIN_RATE_MAX_PAIR; $i++) {
    $response = api_dispatch('account.password.update', 10, [
        'current_password' => 'incorrect',
        'new_password' => 'RateLimitNew123!',
        'new_password_confirmation' => 'RateLimitNew123!',
    ]);
}
$blocked = api_dispatch('account.password.update', 10, [
    'current_password' => 'RateLimitPass123!',
    'new_password' => 'RateLimitNew123!',
    'new_password_confirmation' => 'RateLimitNew123!',
]);
v11j_check($blocked['status'] === 429, 'repeated current-password failures temporarily block account changes');
v11j_check(password_verify('RateLimitPass123!', $pdo->users[10]['user_password']), 'blocked request does not update the password');

foreach (['pair' => $throttleIdentity . "\0" . $_SERVER['REMOTE_ADDR'], 'ip' => $_SERVER['REMOTE_ADDR']] as $scope => $value) {
    $path = login_throttle_path($scope, $value);
    if (is_file($path)) {
        @unlink($path);
    }
}
set_db_connection_for_testing(null);

if ($failures !== []) {
    echo count($failures) . "/{$checks} V1.1-J checks failed.\n";
    exit(1);
}
echo "All {$checks} V1.1-J Account Settings checks passed.\n";
