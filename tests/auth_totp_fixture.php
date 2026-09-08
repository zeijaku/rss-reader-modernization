<?php

declare(strict_types=1);

final class AppTotpException extends RuntimeException
{
}

function auth_totp_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function auth_totp_base64url_decode(string $value): ?string
{
    if ($value === '' || preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) !== 1) {
        return null;
    }

    $padding = (4 - (strlen($value) % 4)) % 4;
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
    if (!is_string($decoded) || auth_totp_base64url_encode($decoded) !== $value) {
        return null;
    }

    return $decoded;
}

function auth_totp_base32_encode(string $value): string
{
    if ($value === '') {
        return '';
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $encoded = '';

    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $buffer = ($buffer << 8) | ord($value[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $encoded .= $alphabet[($buffer >> $bits) & 31];
        }
        if ($bits > 0) {
            $buffer &= (1 << $bits) - 1;
        } else {
            $buffer = 0;
        }
    }

    if ($bits > 0) {
        $encoded .= $alphabet[($buffer << (5 - $bits)) & 31];
    }

    return $encoded;
}

function auth_totp_base32_decode(string $value): ?string
{
    $value = strtoupper(trim($value));
    if ($value === '' || preg_match('/\A[A-Z2-7]+\z/D', $value) !== 1) {
        return null;
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $map = array_flip(str_split($alphabet));
    $buffer = 0;
    $bits = 0;
    $decoded = '';

    foreach (str_split($value) as $character) {
        if (!isset($map[$character])) {
            return null;
        }
        $buffer = ($buffer << 5) | $map[$character];
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $decoded .= chr(($buffer >> $bits) & 255);
            if ($bits > 0) {
                $buffer &= (1 << $bits) - 1;
            } else {
                $buffer = 0;
            }
        }
    }

    // Generated TOTP secrets are byte-aligned. Reject non-zero trailing bits.
    if ($bits > 0 && $buffer !== 0) {
        return null;
    }

    return $decoded;
}

function auth_totp_secret_generate(): string
{
    return random_bytes(20);
}

function auth_totp_crypto_key_id(): string
{
    $keyId = defined('APP_TOTP_SECRET_KEY_ID') ? (string) APP_TOTP_SECRET_KEY_ID : '';
    if (preg_match('/\A[A-Za-z0-9_-]{1,32}\z/D', $keyId) !== 1) {
        throw new AppTotpException('TOTP secret key ID is invalid.');
    }
    return $keyId;
}

function auth_totp_crypto_key(): string
{
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        throw new AppTotpException('Sodium extension is unavailable.');
    }

    $encoded = defined('APP_TOTP_SECRET_KEY_B64') ? trim((string) APP_TOTP_SECRET_KEY_B64) : '';
    $key = $encoded !== '' ? base64_decode($encoded, true) : false;
    if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
        throw new AppTotpException('TOTP secret key is not configured.');
    }

    return $key;
}

function auth_totp_crypto_aad(int $userId): string
{
    if ($userId <= 0) {
        throw new AppTotpException('TOTP secret context is invalid.');
    }
    return 'rss-reader:auth-totp:' . $userId . ':v1';
}

function auth_totp_encrypt_secret(int $userId, string $secret): string
{
    if (strlen($secret) !== 20) {
        throw new AppTotpException('TOTP secret is invalid.');
    }

    $key = auth_totp_crypto_key();
    try {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $secret,
            auth_totp_crypto_aad($userId),
            $nonce,
            $key
        );
        return 'v1.' . auth_totp_crypto_key_id() . '.'
            . auth_totp_base64url_encode($nonce) . '.'
            . auth_totp_base64url_encode($ciphertext);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }
}

function auth_totp_decrypt_secret(int $userId, string $envelope): string
{
    $parts = explode('.', $envelope);
    if (count($parts) !== 4 || $parts[0] !== 'v1' || !hash_equals(auth_totp_crypto_key_id(), $parts[1])) {
        throw new AppTotpException('TOTP secret envelope is invalid.');
    }

    $nonce = auth_totp_base64url_decode($parts[2]);
    $ciphertext = auth_totp_base64url_decode($parts[3]);
    if (!is_string($nonce)
        || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
        || !is_string($ciphertext)) {
        throw new AppTotpException('TOTP secret envelope is invalid.');
    }

    $key = auth_totp_crypto_key();
    try {
        $secret = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            auth_totp_crypto_aad($userId),
            $nonce,
            $key
        );
        if (!is_string($secret) || strlen($secret) !== 20) {
            throw new AppTotpException('TOTP secret could not be decrypted.');
        }
        return $secret;
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }
}

function auth_totp_hotp(string $secret, int $counter, int $digits = 6): string
{
    if ($secret === '' || $counter < 0 || $digits < 6 || $digits > 8) {
        throw new InvalidArgumentException('TOTP calculation input is invalid.');
    }

    $high = intdiv($counter, 4294967296);
    $low = $counter % 4294967296;
    $binaryCounter = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
    $offset = ord($hash[19]) & 0x0f;
    $binary = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    $modulo = 10 ** $digits;
    return str_pad((string) ($binary % $modulo), $digits, '0', STR_PAD_LEFT);
}

function auth_totp_step_at(int $timestamp): int
{
    if ($timestamp < 0) {
        throw new InvalidArgumentException('TOTP timestamp is invalid.');
    }
    return intdiv($timestamp, 30);
}

function auth_totp_code_at(string $secret, int $timestamp, int $digits = 6): string
{
    return auth_totp_hotp($secret, auth_totp_step_at($timestamp), $digits);
}

function auth_totp_match_step(string $secret, string $code, ?int $timestamp = null, int $window = 1): ?int
{
    if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1 || $window < 0 || $window > 2) {
        return null;
    }

    $timestamp ??= time();
    $currentStep = auth_totp_step_at($timestamp);
    for ($offset = -$window; $offset <= $window; $offset++) {
        $step = $currentStep + $offset;
        if ($step < 0) {
            continue;
        }
        if (hash_equals(auth_totp_hotp($secret, $step, 6), $code)) {
            return $step;
        }
    }

    return null;
}

function auth_totp_issuer(): string
{
    $issuer = defined('APP_TOTP_ISSUER') ? trim((string) APP_TOTP_ISSUER) : 'iGuguru';
    if ($issuer === '' || strlen($issuer) > 64 || str_contains($issuer, ':') || preg_match('/[\x00-\x1F\x7F]/', $issuer) === 1) {
        throw new AppTotpException('TOTP issuer is invalid.');
    }
    return $issuer;
}

function auth_totp_otpauth_uri(int $userId, string $base32Secret): string
{
    if ($userId <= 0 || preg_match('/\A[A-Z2-7]{32}\z/D', $base32Secret) !== 1) {
        throw new InvalidArgumentException('TOTP provisioning input is invalid.');
    }

    $issuer = auth_totp_issuer();
    $account = 'Account';
    $label = rawurlencode($issuer . ':' . $account);
    $query = http_build_query([
        'secret' => $base32Secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => '6',
        'period' => '30',
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . $label . '?' . $query;
}

/** @return array<string,mixed>|null */
function auth_totp_record(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = conn_db()->prepare(
        'SELECT auth_totp_user_id, auth_totp_secret, auth_totp_created_at, auth_totp_updated_at, '
        . 'auth_totp_enabled_at, auth_totp_last_used_step FROM ' . db_table_name('auth_totp') . ' '
        . 'WHERE auth_totp_user_id = :user_id LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function auth_totp_active_user_exists(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $stmt = conn_db()->prepare(
        'SELECT user_id FROM ' . db_table_name('user_info') . ' WHERE user_id = :user_id AND user_flag = 0 LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchColumn() !== false;
}

/** @return array{configured:bool,enabled:bool,created_at:?string,updated_at:?string,enabled_at:?string} */
function auth_totp_status(int $userId): array
{
    $row = auth_totp_record($userId);
    return [
        'configured' => is_array($row),
        'enabled' => is_array($row) && is_string($row['auth_totp_enabled_at'] ?? null) && $row['auth_totp_enabled_at'] !== '',
        'created_at' => is_array($row) && is_string($row['auth_totp_created_at'] ?? null) ? $row['auth_totp_created_at'] : null,
        'updated_at' => is_array($row) && is_string($row['auth_totp_updated_at'] ?? null) ? $row['auth_totp_updated_at'] : null,
        'enabled_at' => is_array($row) && is_string($row['auth_totp_enabled_at'] ?? null) ? $row['auth_totp_enabled_at'] : null,
    ];
}

/** @return array{ok:bool,reason?:string,secret?:string,otpauth_uri?:string} */
function auth_totp_begin_enrollment(int $userId): array
{
    if (!auth_totp_active_user_exists($userId)) {
        return ['ok' => false, 'reason' => 'invalid_user'];
    }

    $secret = auth_totp_secret_generate();
    $base32Secret = auth_totp_base32_encode($secret);
    $envelope = auth_totp_encrypt_secret($userId, $secret);
    $now = app_now();
    $pdo = conn_db();

    try {
        $pdo->beginTransaction();
        $current = auth_totp_record($userId);
        if (is_array($current) && is_string($current['auth_totp_enabled_at'] ?? null) && $current['auth_totp_enabled_at'] !== '') {
            $pdo->rollBack();
            return ['ok' => false, 'reason' => 'already_enabled'];
        }

        if (is_array($current)) {
            $stmt = $pdo->prepare(
                'UPDATE ' . db_table_name('auth_totp') . ' SET auth_totp_secret = :secret, '
                . 'auth_totp_updated_at = :updated_at, auth_totp_last_used_step = NULL '
                . 'WHERE auth_totp_user_id = :user_id AND auth_totp_enabled_at IS NULL'
            );
            $stmt->execute([
                ':secret' => $envelope,
                ':updated_at' => $now,
                ':user_id' => $userId,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('TOTP pending enrollment could not be replaced.');
            }
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . db_table_name('auth_totp') . ' '
                . '(auth_totp_user_id, auth_totp_secret, auth_totp_created_at, auth_totp_updated_at, auth_totp_enabled_at, auth_totp_last_used_step) '
                . 'VALUES (:user_id, :secret, :created_at, :updated_at, NULL, NULL)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':secret' => $envelope,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
        $pdo->commit();

        return [
            'ok' => true,
            'secret' => $base32Secret,
            'otpauth_uri' => auth_totp_otpauth_uri($userId, $base32Secret),
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($secret);
        }
    }
}

/** @return array{ok:bool,reason?:string} */
function auth_totp_enable_with_code(int $userId, string $code, ?int $timestamp = null): array
{
    $row = auth_totp_record($userId);
    if (!is_array($row)) {
        return ['ok' => false, 'reason' => 'not_configured'];
    }
    if (is_string($row['auth_totp_enabled_at'] ?? null) && $row['auth_totp_enabled_at'] !== '') {
        return ['ok' => false, 'reason' => 'already_enabled'];
    }

    $secret = auth_totp_decrypt_secret($userId, (string) ($row['auth_totp_secret'] ?? ''));
    try {
        $step = auth_totp_match_step($secret, $code, $timestamp, 1);
        if ($step === null) {
            return ['ok' => false, 'reason' => 'invalid_code'];
        }

        $now = app_now();
        $stmt = conn_db()->prepare(
            'UPDATE ' . db_table_name('auth_totp') . ' SET auth_totp_enabled_at = :enabled_at, '
            . 'auth_totp_updated_at = :updated_at, auth_totp_last_used_step = :last_used_step '
            . 'WHERE auth_totp_user_id = :user_id AND auth_totp_enabled_at IS NULL'
        );
        $stmt->execute([
            ':enabled_at' => $now,
            ':updated_at' => $now,
            ':last_used_step' => $step,
            ':user_id' => $userId,
        ]);
        if ($stmt->rowCount() !== 1) {
            return ['ok' => false, 'reason' => 'state_changed'];
        }

        return ['ok' => true];
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($secret);
        }
    }
}

function auth_totp_verify_enabled_code(int $userId, string $code, ?int $timestamp = null): bool
{
    $row = auth_totp_record($userId);
    if (!is_array($row) || !is_string($row['auth_totp_enabled_at'] ?? null) || $row['auth_totp_enabled_at'] === '') {
        return false;
    }

    $secret = auth_totp_decrypt_secret($userId, (string) ($row['auth_totp_secret'] ?? ''));
    try {
        $step = auth_totp_match_step($secret, $code, $timestamp, 1);
        if ($step === null) {
            return false;
        }

        $stmt = conn_db()->prepare(
            'UPDATE ' . db_table_name('auth_totp') . ' SET auth_totp_last_used_step = :last_used_step, '
            . 'auth_totp_updated_at = :updated_at WHERE auth_totp_user_id = :user_id '
            . 'AND auth_totp_enabled_at IS NOT NULL '
            . 'AND (auth_totp_last_used_step IS NULL OR auth_totp_last_used_step < :compare_step)'
        );
        $stmt->execute([
            ':last_used_step' => $step,
            ':updated_at' => app_now(),
            ':user_id' => $userId,
            ':compare_step' => $step,
        ]);
        return $stmt->rowCount() === 1;
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($secret);
        }
    }
}

function auth_totp_cancel_pending_enrollment(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $stmt = conn_db()->prepare(
        'DELETE FROM ' . db_table_name('auth_totp') . ' WHERE auth_totp_user_id = :user_id AND auth_totp_enabled_at IS NULL'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount() === 1;
}
