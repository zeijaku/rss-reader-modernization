<?php

declare(strict_types=1);

putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('APP_TOTP_SECRET_KEY_ID=test-key');
putenv('APP_TOTP_SECRET_KEY_B64=AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=');
putenv('APP_TOTP_ISSUER=iGuguru-Test');
putenv('DB_DRIVER=mysql');
putenv('DB_HOST=test');
putenv('DB_NAME=test');
putenv('DB_USER=test');
putenv('DB_PASSWORD=test');
putenv('DB_TABLE_PREFIX=ig_');

require_once __DIR__ . '/common_conf_fixture.php';

final class C2PDO extends PDO
{
    public array $users = [1 => ['user_id' => 1, 'user_flag' => 0]];
    public array $totp = [];
    private bool $tx = false;
    private array $snapshot = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new C2Statement($this, $query); }
    public function beginTransaction(): bool { $this->snapshot = $this->totp; $this->tx = true; return true; }
    public function commit(): bool { $this->tx = false; return true; }
    public function rollBack(): bool { $this->totp = $this->snapshot; $this->tx = false; return true; }
    public function inTransaction(): bool { return $this->tx; }
}

final class C2Statement extends PDOStatement
{
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;
    public function __construct(private C2PDO $pdo, private string $sql) { $this->sql = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql); }
    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;
        if (str_starts_with($this->sql, 'SELECT user_id FROM ig_user_info')) {
            $id = (int)($params[':user_id'] ?? 0);
            $u = $this->pdo->users[$id] ?? null;
            $this->column = is_array($u) && ($u['user_flag'] ?? 1) === 0 ? $id : false;
            return true;
        }
        if (str_starts_with($this->sql, 'SELECT auth_totp_user_id')) {
            $id = (int)($params[':user_id'] ?? 0);
            if (isset($this->pdo->totp[$id])) { $this->rows[] = $this->pdo->totp[$id]; }
            return true;
        }
        if (str_starts_with($this->sql, 'INSERT INTO ig_auth_totp')) {
            $id = (int)$params[':user_id'];
            $this->pdo->totp[$id] = [
                'auth_totp_user_id' => $id,
                'auth_totp_secret' => (string)$params[':secret'],
                'auth_totp_created_at' => (string)$params[':created_at'],
                'auth_totp_updated_at' => (string)$params[':updated_at'],
                'auth_totp_enabled_at' => null,
                'auth_totp_last_used_step' => null,
            ];
            $this->affected = 1;
            return true;
        }
        if (str_starts_with($this->sql, 'UPDATE ig_auth_totp SET auth_totp_secret')) {
            $id = (int)$params[':user_id'];
            if (isset($this->pdo->totp[$id]) && $this->pdo->totp[$id]['auth_totp_enabled_at'] === null) {
                $this->pdo->totp[$id]['auth_totp_secret'] = (string)$params[':secret'];
                $this->pdo->totp[$id]['auth_totp_updated_at'] = (string)$params[':updated_at'];
                $this->pdo->totp[$id]['auth_totp_last_used_step'] = null;
                $this->affected = 1;
            }
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
    public function rowCount(): int { return $this->affected; }
}

$GLOBALS['c2pdo'] = new C2PDO();
function conn_db(string $type = ''): PDO { return $GLOBALS['c2pdo']; }
function app_now(): string { return '2026-09-06 00:00:00'; }

require_once __DIR__ . '/auth_totp_fixture.php';

$failures = 0;
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$ok) { $failures++; }
};

$result = auth_totp_begin_enrollment(1);
$check(($result['ok'] ?? false) === true, 'active user can create pending enrollment');
$check(isset($GLOBALS['c2pdo']->totp[1]), 'pending enrollment is inserted into auth_totp');
$row = $GLOBALS['c2pdo']->totp[1];
$check($row['auth_totp_enabled_at'] === null, 'C2 does not enable 2FA');
$check(is_string($row['auth_totp_secret']) && str_starts_with($row['auth_totp_secret'], 'v1.test-key.'), 'database stores encrypted versioned secret envelope');
$check(!str_contains($row['auth_totp_secret'], (string)($result['secret'] ?? '')), 'database does not store Base32 plaintext secret');
$check(auth_totp_status(1)['configured'] === true && auth_totp_status(1)['enabled'] === false, 'status becomes configured/pending after begin');

exit($failures === 0 ? 0 : 1);
