<?php

declare(strict_types=1);

// Runtime settings follow app/common/common_conf.php precedence via app_env().
if (!defined('APP_API_RATE_LIMIT_ENABLED')) {
    define('APP_API_RATE_LIMIT_ENABLED', app_env_bool('APP_API_RATE_LIMIT_ENABLED', true));
}
if (!defined('APP_API_RATE_WINDOW')) {
    define('APP_API_RATE_WINDOW', max(10, min(3600, (int) app_env('APP_API_RATE_WINDOW', '60'))));
}
if (!defined('APP_API_RATE_TOTAL_MAX')) {
    define('APP_API_RATE_TOTAL_MAX', max(1, min(10000, (int) app_env('APP_API_RATE_TOTAL_MAX', '600'))));
}
if (!defined('APP_API_RATE_NETWORK_MAX')) {
    define('APP_API_RATE_NETWORK_MAX', max(1, min(10000, (int) app_env('APP_API_RATE_NETWORK_MAX', '120'))));
}

/**
 * Private, per-user API request limiter. Counts authenticated, CSRF-valid
 * requests before potentially expensive application and outbound work.
 * State contains only a numeric count and window start, never credentials,
 * IP addresses, request parameters, or message content.
 */
function api_rate_limit_directory(): string
{
    return dirname(__DIR__) . '/var/security/api-throttle';
}

/** @return array{allowed:bool,retry_after:int} */
function api_rate_limit_consume_bucket(int $userId, string $bucket, int $maximum, int $window, ?int $now = null): array
{
    if ($userId <= 0 || !in_array($bucket, ['all', 'network'], true) || $maximum <= 0 || $window <= 0) {
        throw new InvalidArgumentException('Invalid API rate-limit arguments.');
    }
    $now ??= time();
    $directory = api_rate_limit_directory();
    if (is_link($directory)) {
        throw new RuntimeException('API throttle storage must not be a symlink.');
    }
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create API throttle storage.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('API throttle storage is not writable.');
    }

    $path = $directory . '/' . $bucket . '-' . $userId . '.json';
    if (is_link($path)) {
        throw new RuntimeException('API throttle state must not be a symlink.');
    }
    $previousUmask = umask(0077);
    try {
        $handle = @fopen($path, 'c+');
    } finally {
        umask($previousUmask);
    }
    if ($handle === false) {
        throw new RuntimeException('Unable to open API throttle state.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock API throttle state.');
        }
        rewind($handle);
        $decoded = json_decode((string) stream_get_contents($handle), true);
        $start = is_array($decoded) && is_int($decoded['start'] ?? null) ? $decoded['start'] : $now;
        $count = is_array($decoded) && is_int($decoded['count'] ?? null) ? $decoded['count'] : 0;
        if ($start > $now || ($now - $start) >= $window || $count < 0) {
            $start = $now;
            $count = 0;
        }
        if ($count >= $maximum) {
            return ['allowed' => false, 'retry_after' => max(1, $window - ($now - $start))];
        }
        $payload = json_encode(['start' => $start, 'count' => $count + 1], JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $payload) !== strlen($payload) || !fflush($handle)) {
            throw new RuntimeException('Unable to persist API throttle state.');
        }
        return ['allowed' => true, 'retry_after' => 0];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** The network bucket is shared across costly API families, not per action. */
function api_rate_limit_is_network_action(string $action): bool
{
    return in_array($action, [
        'feed.fetch', 'widget.search.fetch', 'blindspot.fetch', 'x.timeline.fetch',
    ], true) || str_starts_with($action, 'remote.')
        || str_starts_with($action, 'mail.account.')
        || str_starts_with($action, 'mail.message.')
        || str_starts_with($action, 'mail.oauth.')
        || str_starts_with($action, 'widget.healthprobe.');
}

/** @return array{allowed:bool,retry_after:int} */
function api_rate_limit_consume(int $userId, string $action, ?int $now = null): array
{
    if (!APP_API_RATE_LIMIT_ENABLED) {
        return ['allowed' => true, 'retry_after' => 0];
    }
    $total = api_rate_limit_consume_bucket($userId, 'all', APP_API_RATE_TOTAL_MAX, APP_API_RATE_WINDOW, $now);
    if (!$total['allowed'] || !api_rate_limit_is_network_action($action)) {
        return $total;
    }
    return api_rate_limit_consume_bucket($userId, 'network', APP_API_RATE_NETWORK_MAX, APP_API_RATE_WINDOW, $now);
}
