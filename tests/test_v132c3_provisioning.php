<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
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

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/auth_totp.php';

final class V132c3ProvisioningPDO extends PDO
{
    public ?array $row = null;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new V132c3ProvisioningStatement($this, $query);
    }
    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null;
    }
}

final class V132c3ProvisioningStatement extends PDOStatement
{
    private mixed $result = false;
    public function __construct(private V132c3ProvisioningPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (!str_contains($this->sql, 'SELECT auth_totp_user_id')) {
            throw new RuntimeException('Unexpected SQL in C3 provisioning fixture.');
        }
        $id = (int) (($params ?? [])[':user_id'] ?? 0);
        $row = $this->pdo->row;
        $this->result = is_array($row) && (int) ($row['auth_totp_user_id'] ?? 0) === $id ? $row : false;
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $result = $this->result;
        $this->result = false;
        return $result;
    }
}

$pdo = new V132c3ProvisioningPDO();
set_db_connection_for_testing($pdo);
$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
    if (!$condition) { $failures++; }
};

$rawSecret = '12345678901234567890';
$base32 = auth_totp_base32_encode($rawSecret);
$envelope = auth_totp_encrypt_secret(1, $rawSecret);
$pdo->row = [
    'auth_totp_user_id' => 1,
    'auth_totp_secret' => $envelope,
    'auth_totp_created_at' => '2026-09-06 00:00:00',
    'auth_totp_updated_at' => '2026-09-06 00:00:00',
    'auth_totp_enabled_at' => null,
    'auth_totp_last_used_step' => null,
];

$result = auth_totp_pending_provisioning(1);
$check(($result['ok'] ?? false) === true, 'pending enrollment can be opened for provisioning');
$check(($result['secret'] ?? '') === $base32, 'provisioning decrypts the same stored secret');
$check(str_starts_with((string) ($result['otpauth_uri'] ?? ''), 'otpauth://totp/'), 'provisioning returns a TOTP otpauth URI');
$check(str_contains((string) ($result['otpauth_uri'] ?? ''), 'secret=' . $base32), 'otpauth URI carries the pending Base32 secret');
$check((string) $pdo->row['auth_totp_secret'] === $envelope, 'provisioning does not rewrite encrypted DB material');

$pdo->row['auth_totp_enabled_at'] = '2026-09-06 00:01:00';
$result = auth_totp_pending_provisioning(1);
$check(($result['reason'] ?? '') === 'already_enabled', 'enabled TOTP cannot expose enrollment provisioning material');

$pdo->row = null;
$result = auth_totp_pending_provisioning(1);
$check(($result['reason'] ?? '') === 'not_configured', 'missing enrollment does not expose provisioning material');

set_db_connection_for_testing(null);
exit($failures === 0 ? 0 : 1);
