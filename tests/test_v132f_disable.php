<?php

declare(strict_types=1);

final class FSecurityPDO extends PDO
{
    public array $totp = [1 => ['auth_totp_user_id' => 1, 'auth_totp_enabled_at' => '2026-09-06 20:00:00']];
    public array $recovery = [1 => [1, 2, 3]];
    public array $remember = [1 => ['token-a', 'token-b']];
    private bool $tx = false;
    private array $snapshot = [];
    public function __construct() {}
    public function beginTransaction(): bool { $this->snapshot = [$this->totp, $this->recovery, $this->remember]; $this->tx = true; return true; }
    public function commit(): bool { $this->tx = false; return true; }
    public function rollBack(): bool { [$this->totp, $this->recovery, $this->remember] = $this->snapshot; $this->tx = false; return true; }
    public function inTransaction(): bool { return $this->tx; }
    public function getAttribute(int $attribute): mixed { return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new FSecurityStatement($this, preg_replace('/\s+/', ' ', trim($query)) ?? trim($query)); }
}

final class FSecurityStatement extends PDOStatement
{
    private array|false $row = false;
    private int $affected = 0;
    public function __construct(private FSecurityPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $uid = (int) ($params[':user_id'] ?? 0);
        $this->row = false;
        $this->affected = 0;
        if (str_starts_with($this->sql, 'SELECT auth_totp_user_id, auth_totp_enabled_at FROM ig_auth_totp')) {
            $this->row = $this->pdo->totp[$uid] ?? false;
            return true;
        }
        if (str_starts_with($this->sql, 'DELETE FROM ig_auth_recovery_code')) {
            $this->affected = isset($this->pdo->recovery[$uid]) ? count($this->pdo->recovery[$uid]) : 0;
            unset($this->pdo->recovery[$uid]);
            return true;
        }
        if (str_starts_with($this->sql, 'DELETE FROM ig_auth_totp')) {
            if (isset($this->pdo->totp[$uid]) && $this->pdo->totp[$uid]['auth_totp_enabled_at'] !== null) {
                unset($this->pdo->totp[$uid]);
                $this->affected = 1;
            }
            return true;
        }
        if (str_starts_with($this->sql, 'DELETE FROM ig_remember_token')) {
            $this->affected = isset($this->pdo->remember[$uid]) ? count($this->pdo->remember[$uid]) : 0;
            unset($this->pdo->remember[$uid]);
            return true;
        }
        throw new RuntimeException('Unexpected SQL: ' . $this->sql);
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->row; }
    public function rowCount(): int { return $this->affected; }
}

$GLOBALS['pdo'] = new FSecurityPDO();
function conn_db(string $type = ''): PDO { return $GLOBALS['pdo']; }
function db_table_name(string $name): string { return 'ig_' . $name; }
function db_table_identifier(string $name): string { return 'ig_' . $name; }
function account_settings_supports_for_update(PDO $pdo): bool { return true; }
function remember_token_revoke_user(int $userId, ?PDO $pdo = null): int
{
    $stmt = ($pdo ?? conn_db())->prepare('DELETE FROM ig_remember_token WHERE remember_token_user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount();
}

require_once dirname(__DIR__) . '/app/account_security.php';

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failed++; }
};

$result = account_security_disable_totp(1);
$check(($result['ok'] ?? false) === true, 'enabled TOTP can be disabled after the API has enforced Step-up');
$check(!isset($GLOBALS['pdo']->totp[1]), 'TOTP secret row is removed instead of leaving disabled ciphertext behind');
$check(!isset($GLOBALS['pdo']->recovery[1]), 'all Recovery Codes are removed with 2FA');
$check(!isset($GLOBALS['pdo']->remember[1]), 'all Remember Tokens are revoked when the authentication policy changes');

$result = account_security_disable_totp(1);
$check(($result['ok'] ?? true) === false && ($result['reason'] ?? '') === 'not_enabled', 'repeat disable is fail-closed and idempotent at the API boundary');

exit($failed === 0 ? 0 : 1);
