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

final class V132bTotpPDO extends PDO
{
    public array $users = [
        1 => ['user_id' => 1, 'user_flag' => 0],
        2 => ['user_id' => 2, 'user_flag' => 0],
        3 => ['user_id' => 3, 'user_flag' => 1],
    ];
    public array $totp = [];
    private bool $transaction = false;
    private ?array $snapshot = null;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new V132bTotpStatement($this, $query);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null;
    }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            return false;
        }
        $this->transaction = true;
        $this->snapshot = $this->totp;
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
            $this->totp = $this->snapshot;
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

final class V132bTotpStatement extends PDOStatement
{
    private array $rows = [];
    private mixed $column = false;
    private int $affected = 0;
    private string $sql;

    public function __construct(private V132bTotpPDO $pdo, string $sql)
    {
        $this->sql = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
    }

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->rows = [];
        $this->column = false;
        $this->affected = 0;

        if (str_starts_with($this->sql, 'SELECT user_id FROM ig_user_info')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $user = $this->pdo->users[$id] ?? null;
            $this->column = is_array($user) && (int) ($user['user_flag'] ?? 1) === 0 ? $id : false;
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT auth_totp_user_id')) {
            $id = (int) ($params[':user_id'] ?? 0);
            if (isset($this->pdo->totp[$id])) {
                $this->rows[] = $this->pdo->totp[$id];
            }
            return true;
        }

        if (str_starts_with($this->sql, 'INSERT INTO ig_auth_totp')) {
            $id = (int) ($params[':user_id'] ?? 0);
            if ($id <= 0 || isset($this->pdo->totp[$id])) {
                throw new PDOException('duplicate or invalid TOTP fixture row');
            }
            $this->pdo->totp[$id] = [
                'auth_totp_user_id' => $id,
                'auth_totp_secret' => (string) ($params[':secret'] ?? ''),
                'auth_totp_created_at' => (string) ($params[':created_at'] ?? ''),
                'auth_totp_updated_at' => (string) ($params[':updated_at'] ?? ''),
                'auth_totp_enabled_at' => null,
                'auth_totp_last_used_step' => null,
            ];
            $this->affected = 1;
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE ig_auth_totp SET auth_totp_secret')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $row = $this->pdo->totp[$id] ?? null;
            if (is_array($row) && $row['auth_totp_enabled_at'] === null) {
                $this->pdo->totp[$id]['auth_totp_secret'] = (string) ($params[':secret'] ?? '');
                $this->pdo->totp[$id]['auth_totp_updated_at'] = (string) ($params[':updated_at'] ?? '');
                $this->pdo->totp[$id]['auth_totp_last_used_step'] = null;
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE ig_auth_totp SET auth_totp_enabled_at')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $row = $this->pdo->totp[$id] ?? null;
            if (is_array($row) && $row['auth_totp_enabled_at'] === null) {
                $this->pdo->totp[$id]['auth_totp_enabled_at'] = (string) ($params[':enabled_at'] ?? '');
                $this->pdo->totp[$id]['auth_totp_updated_at'] = (string) ($params[':updated_at'] ?? '');
                $this->pdo->totp[$id]['auth_totp_last_used_step'] = (int) ($params[':last_used_step'] ?? -1);
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE ig_auth_totp SET auth_totp_last_used_step')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $step = (int) ($params[':compare_step'] ?? -1);
            $row = $this->pdo->totp[$id] ?? null;
            $last = is_array($row) ? $row['auth_totp_last_used_step'] : null;
            if (is_array($row)
                && is_string($row['auth_totp_enabled_at'])
                && $row['auth_totp_enabled_at'] !== ''
                && ($last === null || (int) $last < $step)) {
                $this->pdo->totp[$id]['auth_totp_last_used_step'] = $step;
                $this->pdo->totp[$id]['auth_totp_updated_at'] = (string) ($params[':updated_at'] ?? '');
                $this->affected = 1;
            }
            return true;
        }

        if (str_starts_with($this->sql, 'DELETE FROM ig_auth_totp')) {
            $id = (int) ($params[':user_id'] ?? 0);
            $row = $this->pdo->totp[$id] ?? null;
            if (is_array($row) && $row['auth_totp_enabled_at'] === null) {
                unset($this->pdo->totp[$id]);
                $this->affected = 1;
            }
            return true;
        }

        throw new RuntimeException('Unexpected SQL in V1.32-B TOTP fixture: ' . $this->sql);
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

$pdo = new V132bTotpPDO();
set_db_connection_for_testing($pdo);
$results = [];
$check = static function (bool $condition, string $message) use (&$results): void {
    $results[] = [$condition, $message];
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . PHP_EOL;
};

$check(auth_totp_base32_encode('foo') === 'MZXW6', 'Base32 RFC 4648 encoding is correct');
$check(auth_totp_base32_decode('MZXW6') === 'foo', 'Base32 generated form decodes correctly');
$check(auth_totp_base32_decode('MZXW7') === null, 'Base32 rejects non-zero trailing bits');

$rfcSecret = '12345678901234567890';
$check(auth_totp_hotp($rfcSecret, 0, 6) === '755224', 'HOTP RFC 4226 counter 0 vector matches');
$check(auth_totp_hotp($rfcSecret, 1, 6) === '287082', 'HOTP RFC 4226 counter 1 vector matches');
$check(auth_totp_code_at($rfcSecret, 59, 8) === '94287082', 'TOTP RFC 6238 SHA-1 time 59 vector matches');

$secret = auth_totp_secret_generate();
$secretB32 = auth_totp_base32_encode($secret);
$check(strlen($secret) === 20 && strlen($secretB32) === 32, 'Generated TOTP secret is 160-bit and 32 Base32 characters');
$envelope = auth_totp_encrypt_secret(1, $secret);
$check(str_starts_with($envelope, 'v1.test-key.'), 'Encrypted envelope is versioned and key-ID scoped');
$check(!str_contains($envelope, $secretB32), 'Encrypted envelope does not contain the displayed Base32 secret');
$check(auth_totp_decrypt_secret(1, $envelope) === $secret, 'TOTP secret decrypts only in the correct user context');
$wrongContextRejected = false;
try { auth_totp_decrypt_secret(2, $envelope); } catch (AppTotpException) { $wrongContextRejected = true; }
$check($wrongContextRejected, 'TOTP ciphertext fails closed under a different user AAD context');

$uri = auth_totp_otpauth_uri(1, $secretB32);
$check(str_starts_with($uri, 'otpauth://totp/'), 'Provisioning URI uses otpauth TOTP scheme');
$check(str_contains($uri, 'secret=' . $secretB32) && str_contains($uri, 'algorithm=SHA1') && str_contains($uri, 'digits=6') && str_contains($uri, 'period=30'), 'Provisioning URI declares interoperable TOTP parameters');
$check(!str_contains($uri, '@'), 'Provisioning label does not expose a raw email address');
$check(str_contains($uri, rawurlencode('iGuguru-Test:Account')) && !str_contains($uri, 'user-1'), 'Provisioning label uses a neutral Account name instead of the internal user ID');

$disabled = auth_totp_begin_enrollment(3);
$check(($disabled['reason'] ?? '') === 'invalid_user', 'Disabled user cannot start TOTP enrollment');

$begin = auth_totp_begin_enrollment(1);
$check(($begin['ok'] ?? false) === true, 'Active user can start TOTP enrollment');
$check(isset($begin['secret'], $begin['otpauth_uri']) && strlen((string) $begin['secret']) === 32, 'Enrollment returns a one-time Base32 secret and provisioning URI');
$status = auth_totp_status(1);
$check($status['configured'] === true && $status['enabled'] === false && $status['enabled_at'] === null, 'Enrollment remains pending until first successful verification');
$row = auth_totp_record(1);
$check(is_array($row) && !str_contains((string) $row['auth_totp_secret'], (string) $begin['secret']), 'Database stores only encrypted TOTP secret material');

$timestamp = 1788512400;
$rawEnrollmentSecret = auth_totp_base32_decode((string) $begin['secret']);
$check(is_string($rawEnrollmentSecret) && strlen($rawEnrollmentSecret) === 20, 'Displayed enrollment secret round-trips to 20 bytes');
$correctCode = auth_totp_code_at((string) $rawEnrollmentSecret, $timestamp);
$wrongCode = $correctCode === '000000' ? '000001' : '000000';
$wrongEnable = auth_totp_enable_with_code(1, $wrongCode, $timestamp);
$check(($wrongEnable['reason'] ?? '') === 'invalid_code' && auth_totp_status(1)['enabled'] === false, 'Wrong enrollment code does not enable 2FA');
$enabled = auth_totp_enable_with_code(1, $correctCode, $timestamp);
$check(($enabled['ok'] ?? false) === true && auth_totp_status(1)['enabled'] === true, 'First valid code atomically enables 2FA');
$check(auth_totp_verify_enabled_code(1, $correctCode, $timestamp) === false, 'Enrollment code cannot be replayed immediately after enablement');

$nextTimestamp = $timestamp + 30;
$nextCode = auth_totp_code_at((string) $rawEnrollmentSecret, $nextTimestamp);
$check(auth_totp_verify_enabled_code(1, $nextCode, $nextTimestamp), 'Next valid TOTP code is accepted');
$check(!auth_totp_verify_enabled_code(1, $nextCode, $nextTimestamp), 'Accepted TOTP time-step cannot be replayed');
$futureCode = auth_totp_code_at((string) $rawEnrollmentSecret, $nextTimestamp + 30);
$check(auth_totp_match_step((string) $rawEnrollmentSecret, $futureCode, $nextTimestamp, 1) !== null, 'Verification accepts one adjacent time-step for clock skew');

$beforeEnabledEnvelope = (string) (auth_totp_record(1)['auth_totp_secret'] ?? '');
$again = auth_totp_begin_enrollment(1);
$check(($again['reason'] ?? '') === 'already_enabled', 'Enabled TOTP cannot be overwritten by a fresh enrollment foundation call');
$check((string) (auth_totp_record(1)['auth_totp_secret'] ?? '') === $beforeEnabledEnvelope, 'Rejected re-enrollment leaves enabled secret unchanged');

$pending = auth_totp_begin_enrollment(2);
$check(($pending['ok'] ?? false) === true && auth_totp_status(2)['enabled'] === false, 'Second user can hold an independent pending enrollment');
$check(auth_totp_cancel_pending_enrollment(2) && auth_totp_status(2)['configured'] === false, 'Pending enrollment can be cancelled without affecting an enabled user');
$check(auth_totp_status(1)['enabled'] === true, 'Cancelling another pending enrollment leaves enabled user intact');

set_db_connection_for_testing(null);
$passed = 0;
foreach ($results as [$ok, $message]) {
    if ($ok) { $passed++; }
}
$failed = count($results) - $passed;
echo "RESULT: PASS {$passed} / FAIL {$failed} / SKIP 0" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
