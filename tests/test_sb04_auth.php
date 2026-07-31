<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('REGISTRATION_ENABLED=true');
putenv('AUTH_PASSWORD_MIN_LENGTH=12');

require $root . '/app/common/common_conf.php';
require $root . '/app/common/common_db.php';
require $root . '/app/auth.php';

final class AuthFakeStatement extends PDOStatement
{
    public array $resultRows = [];
    public mixed $columnValue = false;
    public int $affected = 0;

    public function __construct(private AuthFakePDO $pdo, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $sql = $this->sql;

        if (str_starts_with($sql, 'INSERT INTO ig_user_info')) {
            $id = ++$this->pdo->sequence;
            $this->pdo->users[$id] = [
                'user_id' => $id,
                'user_date' => (string) ($params[':date'] ?? ''),
                'user_flag' => 0,
                'user_email' => (string) ($params[':email'] ?? ''),
                'user_password' => (string) ($params[':password'] ?? ''),
            ];
            $this->pdo->lastId = $id;
            $this->affected = 1;
            return true;
        }
        if (str_starts_with($sql, 'INSERT INTO ig_user_conf')) {
            $this->pdo->confs[] = $params;
            $this->affected = 1;
            return true;
        }
        if (str_starts_with($sql, 'SELECT user_id, user_email, user_password, user_flag FROM ig_user_info')) {
            $identity = (string) ($params[':email'] ?? '');
            $rows = array_values(array_filter($this->pdo->users, static fn(array $row): bool => $row['user_email'] === $identity && (int) $row['user_flag'] === 0));
            usort($rows, static fn(array $a, array $b): int => $a['user_id'] <=> $b['user_id']);
            $this->resultRows = array_slice($rows, 0, 2);
            return true;
        }
        if (str_starts_with($sql, 'SELECT user_id FROM ig_user_info')) {
            $identity = (string) ($params[':email'] ?? '');
            foreach ($this->pdo->users as $row) {
                if ($row['user_email'] === $identity) {
                    $this->columnValue = $row['user_id'];
                    return true;
                }
            }
            $this->columnValue = false;
            return true;
        }
        if (str_starts_with($sql, 'UPDATE ig_user_info SET user_password')) {
            $id = (int) ($params[':user_id'] ?? 0);
            if (isset($this->pdo->users[$id]) && (int) $this->pdo->users[$id]['user_flag'] === 0) {
                $this->pdo->users[$id]['user_password'] = (string) ($params[':password'] ?? '');
                $this->affected = 1;
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in auth fake: ' . $sql);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->resultRows; }
    public function fetchColumn(int $column = 0): mixed { return $this->columnValue; }
    public function rowCount(): int { return $this->affected; }
}

final class AuthFakePDO extends PDO
{
    public array $users = [];
    public array $confs = [];
    public int $sequence = 0;
    public int $lastId = 0;
    private bool $transaction = false;

    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new AuthFakeStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
}

$pdo = new AuthFakePDO();
set_db_connection_for_testing($pdo);
$failures = [];
function acheck(bool $condition, string $message): void
{
    global $failures;
    if ($condition) echo "PASS: {$message}\n";
    else { $failures[] = $message; echo "FAIL: {$message}\n"; }
}

$email = 'User.Example+RSS@Example.COM';
$password = 'correct horse battery staple';
$identity = auth_identity_key($email);
acheck(strlen($identity) === 64 && ctype_xdigit($identity), 'identity is a 64-character keyed hash');
acheck($identity === auth_identity_key('user.example+rss@example.com'), 'email identity normalization is case-insensitive');
acheck(auth_email_is_valid($email), 'valid email accepted');
acheck(!auth_email_is_valid('not-an-email'), 'invalid email rejected');
acheck(!auth_password_is_valid_for_registration('short'), 'short registration password rejected');
acheck(auth_password_is_valid_for_registration($password), 'valid registration password accepted');
acheck(!auth_password_is_valid_for_registration(str_repeat('x', AUTH_PASSWORD_MAX_LENGTH + 1)), 'overlong registration password rejected');

$registration = auth_register($email, $password);
acheck(($registration['ok'] ?? false) === true, 'new user registration succeeds');
$userId = (int) ($registration['user_id'] ?? 0);
$stored = $pdo->users[$userId]['user_password'] ?? '';
acheck($stored !== $password, 'raw password is not stored');
acheck(auth_is_password_hash((string) $stored), 'stored password uses password_hash format');
acheck(password_verify($password, (string) $stored), 'stored password verifies with password_verify');
acheck(($pdo->users[$userId]['user_email'] ?? '') === $identity, 'raw email is not stored in the login identity column');
acheck(count($pdo->confs) === 1, 'registration creates configuration row in same operation');

$duplicate = auth_register('USER.EXAMPLE+RSS@EXAMPLE.COM', $password);
acheck(($duplicate['ok'] ?? true) === false && ($duplicate['reason'] ?? '') === 'identity_exists', 'duplicate normalized identity is rejected');

$login = auth_authenticate($email, $password);
acheck(($login['ok'] ?? false) === true && (int) ($login['user_id'] ?? 0) === $userId, 'correct password authenticates');
acheck((auth_authenticate($email, 'this password is wrong')['ok'] ?? true) === false, 'wrong password is rejected');
acheck((auth_authenticate('missing@example.com', $password)['ok'] ?? true) === false, 'unknown identity is rejected');

$pdo->users[$userId]['user_flag'] = 1;
acheck((auth_authenticate($email, $password)['ok'] ?? true) === false, 'disabled user is rejected');
$pdo->users[$userId]['user_flag'] = 0;

$pdo->users[$userId]['user_password'] = str_repeat('a', 64);
acheck((auth_authenticate($email, $password)['ok'] ?? true) === false, 'legacy/non-password_hash credential fails closed');

$pdo->users[$userId]['user_password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
acheck(password_needs_rehash($pdo->users[$userId]['user_password'], PASSWORD_DEFAULT), 'low-cost fixture needs rehash');
acheck((auth_authenticate($email, $password)['ok'] ?? false) === true, 'rehash fixture authenticates');
acheck(!password_needs_rehash($pdo->users[$userId]['user_password'], PASSWORD_DEFAULT), 'successful login rehashes outdated password hash');

// duplicate active identity must fail closed even when one password matches
$pdo->users[++$pdo->sequence] = [
    'user_id' => $pdo->sequence,
    'user_date' => app_now(),
    'user_flag' => 0,
    'user_email' => $identity,
    'user_password' => auth_password_hash($password),
];
$result = auth_authenticate($email, $password);
acheck(($result['ok'] ?? true) === false && ($result['reason'] ?? '') === 'ambiguous_identity', 'duplicate active identity fails closed');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " SB-04 auth checks failed.\n");
    exit(1);
}

echo "All SB-04 authentication checks passed.\n";
