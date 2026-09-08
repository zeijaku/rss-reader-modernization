<?php

declare(strict_types=1);

function login_throttle_directory(): string
{
    return dirname(__DIR__) . '/var/security/login-throttle';
}

function login_throttle_prepare_directory(): string
{
    $directory = login_throttle_directory();
    if (!is_dir($directory)) {
        if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create login throttle storage.');
        }
    }

    if (!is_writable($directory)) {
        throw new RuntimeException('Login throttle storage is not writable.');
    }

    return $directory;
}

function login_throttle_key(string $scope, string $value): string
{
    return hash_hmac('sha256', $scope . "\0" . $value, (string) INI_HASH_KEY);
}

function login_throttle_path(string $scope, string $value): string
{
    return login_throttle_prepare_directory() . '/' . login_throttle_key($scope, $value) . '.json';
}

/**
 * Atomically read/modify/write one throttle bucket.
 *
 * @param callable(array{failures:list<int>,blocked_until:int}):array{failures:list<int>,blocked_until:int} $mutator
 * @return array{failures:list<int>,blocked_until:int}
 */
function login_throttle_mutate(string $scope, string $value, callable $mutator): array
{
    $path = login_throttle_path($scope, $value);
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open login throttle state.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock login throttle state.');
        }

        rewind($handle);
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $state = [
            'failures' => is_array($decoded['failures'] ?? null)
                ? array_values(array_map('intval', $decoded['failures']))
                : [],
            'blocked_until' => (int) ($decoded['blocked_until'] ?? 0),
        ];

        $state = $mutator($state);
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        fflush($handle);
        flock($handle, LOCK_UN);
        return $state;
    } finally {
        fclose($handle);
    }
}

/** @return array{blocked:bool,retry_after:int} */
function login_throttle_status(string $identityKey, string $ipAddress, ?int $now = null): array
{
    $now ??= time();
    $pairValue = $identityKey . "\0" . $ipAddress;

    $pair = login_throttle_mutate('pair', $pairValue, static function (array $state) use ($now): array {
        $state['failures'] = array_values(array_filter(
            $state['failures'],
            static fn(int $timestamp): bool => $timestamp >= ($now - LOGIN_RATE_WINDOW)
        ));
        if ($state['blocked_until'] <= $now) {
            $state['blocked_until'] = 0;
        }
        return $state;
    });

    $ip = login_throttle_mutate('ip', $ipAddress, static function (array $state) use ($now): array {
        $state['failures'] = array_values(array_filter(
            $state['failures'],
            static fn(int $timestamp): bool => $timestamp >= ($now - LOGIN_RATE_WINDOW)
        ));
        if ($state['blocked_until'] <= $now) {
            $state['blocked_until'] = 0;
        }
        return $state;
    });

    $blockedUntil = max($pair['blocked_until'], $ip['blocked_until']);
    return [
        'blocked' => $blockedUntil > $now,
        'retry_after' => max(0, $blockedUntil - $now),
    ];
}

function login_throttle_record_failure(string $identityKey, string $ipAddress, ?int $now = null): void
{
    $now ??= time();
    $pairValue = $identityKey . "\0" . $ipAddress;

    login_throttle_record_bucket('pair', $pairValue, LOGIN_RATE_MAX_PAIR, $now);
    login_throttle_record_bucket('ip', $ipAddress, LOGIN_RATE_MAX_IP, $now);
}

function login_throttle_record_bucket(string $scope, string $value, int $maximum, int $now): void
{
    login_throttle_mutate($scope, $value, static function (array $state) use ($now, $maximum): array {
        $state['failures'] = array_values(array_filter(
            $state['failures'],
            static fn(int $timestamp): bool => $timestamp >= ($now - LOGIN_RATE_WINDOW)
        ));
        $state['failures'][] = $now;
        if (count($state['failures']) >= $maximum) {
            $state['blocked_until'] = max($state['blocked_until'], $now + LOGIN_RATE_BLOCK_SECONDS);
        }
        return $state;
    });
}

function login_throttle_record_success(string $identityKey, string $ipAddress): void
{
    $pairPath = login_throttle_path('pair', $identityKey . "\0" . $ipAddress);
    if (is_file($pairPath)) {
        @unlink($pairPath);
    }
}

/**
 * Consume one registration attempt for an IP address.
 *
 * Registration uses an IP-only bucket intentionally: the goal is to limit
 * automated account creation without persisting submitted email addresses.
 * Successful registrations are also counted so the control cannot be bypassed
 * by creating many valid accounts.
 *
 * @return array{allowed:bool,retry_after:int}
 */
function registration_throttle_consume(string $ipAddress, ?int $now = null): array
{
    $now ??= time();
    $allowed = true;
    $retryAfter = 0;

    login_throttle_mutate('registration-ip', $ipAddress, static function (array $state) use ($now, &$allowed, &$retryAfter): array {
        $state['failures'] = array_values(array_filter(
            $state['failures'],
            static fn(int $timestamp): bool => $timestamp >= ($now - REGISTRATION_RATE_WINDOW)
        ));

        if ($state['blocked_until'] > $now) {
            $allowed = false;
            $retryAfter = $state['blocked_until'] - $now;
            return $state;
        }

        $state['blocked_until'] = 0;
        if (count($state['failures']) >= REGISTRATION_RATE_MAX_IP) {
            $state['blocked_until'] = $now + REGISTRATION_RATE_BLOCK_SECONDS;
            $allowed = false;
            $retryAfter = REGISTRATION_RATE_BLOCK_SECONDS;
            return $state;
        }

        $state['failures'][] = $now;
        return $state;
    });

    return [
        'allowed' => $allowed,
        'retry_after' => max(0, $retryAfter),
    ];
}


// V1.32-C: second-factor attempts use separate buckets from password Login.
function auth_2fa_throttle_identity(int $userId): string
{
    if ($userId <= 0) {
        throw new InvalidArgumentException('A positive user id is required for 2FA throttle.');
    }
    return hash_hmac('sha256', 'auth-2fa' . "\0" . (string) $userId, (string) INI_HASH_KEY);
}

/** @return array{blocked:bool,retry_after:int} */
function auth_2fa_throttle_status(int $userId, string $ipAddress, ?int $now = null): array
{
    $now ??= time();
    $identity = auth_2fa_throttle_identity($userId);
    $pairValue = $identity . "\0" . $ipAddress;

    $prune = static function (array $state) use ($now): array {
        $state['failures'] = array_values(array_filter(
            $state['failures'],
            static fn(int $timestamp): bool => $timestamp >= ($now - AUTH_2FA_RATE_WINDOW)
        ));
        if ($state['blocked_until'] <= $now) {
            $state['blocked_until'] = 0;
        }
        return $state;
    };

    $pair = login_throttle_mutate('2fa-pair', $pairValue, $prune);
    $ip = login_throttle_mutate('2fa-ip', $ipAddress, $prune);
    $blockedUntil = max($pair['blocked_until'], $ip['blocked_until']);
    return [
        'blocked' => $blockedUntil > $now,
        'retry_after' => max(0, $blockedUntil - $now),
    ];
}

function auth_2fa_throttle_record_failure(int $userId, string $ipAddress, ?int $now = null): void
{
    $now ??= time();
    $identity = auth_2fa_throttle_identity($userId);
    auth_2fa_throttle_record_bucket('2fa-pair', $identity . "\0" . $ipAddress, AUTH_2FA_RATE_MAX_PAIR, $now);
    auth_2fa_throttle_record_bucket('2fa-ip', $ipAddress, AUTH_2FA_RATE_MAX_IP, $now);
}

function auth_2fa_throttle_record_bucket(string $scope, string $value, int $maximum, int $now): void
{
    login_throttle_mutate($scope, $value, static function (array $state) use ($now, $maximum): array {
        $state['failures'] = array_values(array_filter(
            $state['failures'],
            static fn(int $timestamp): bool => $timestamp >= ($now - AUTH_2FA_RATE_WINDOW)
        ));
        $state['failures'][] = $now;
        if (count($state['failures']) >= $maximum) {
            $state['blocked_until'] = max($state['blocked_until'], $now + AUTH_2FA_RATE_BLOCK_SECONDS);
        }
        return $state;
    });
}

function auth_2fa_throttle_record_success(int $userId, string $ipAddress): void
{
    $identity = auth_2fa_throttle_identity($userId);
    $path = login_throttle_path('2fa-pair', $identity . "\0" . $ipAddress);
    if (is_file($path)) {
        @unlink($path);
    }
}
