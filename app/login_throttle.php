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
