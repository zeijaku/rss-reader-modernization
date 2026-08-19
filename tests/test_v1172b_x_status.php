<?php

declare(strict_types=1);

$cacheRoot = sys_get_temp_dir() . '/rss-reader-v1172b-x-status-' . bin2hex(random_bytes(6));
define('APP_X_BEARER_TOKEN', 'v1172b-placeholder-token');
define('APP_X_CACHE_TTL_SECONDS', 300);
define('APP_X_STALE_MAX_AGE_SECONDS', 3600);
define('APP_X_TIMEOUT_MS', 5000);
define('APP_X_CACHE_DIR', $cacheRoot);
define('APP_ENV', 'testing');
define('APP_DEBUG', false);
define('APP_LOG_ENABLED', false);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/api.php';

$pass = 0;
$fail = 0;

function v1172b_check(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$message}\n";
        return;
    }
    $fail++;
    echo "FAIL: {$message}\n";
}

function v1172b_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        is_dir($path) ? v1172b_rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

try {
    $initial = x_widget_connection_status();
    v1172b_check(($initial['state'] ?? null) === 'unverified', 'configured token starts as unverified without an X API probe');
    v1172b_check(($initial['configured'] ?? false) === true && ($initial['can_add'] ?? false) === true, 'unverified configured token does not block widget registration');

    $authReason = '';
    try {
        x_api_request_json('https://api.x.com/2/test', static fn(string $url, string $token): array => [
            'ok' => true,
            'status' => 401,
            'body' => '{"title":"Unauthorized"}',
        ]);
    } catch (XApiRequestException $exception) {
        $authReason = $exception->reasonCode();
    }
    v1172b_check($authReason === 'x_auth_failed', 'HTTP 401 remains an explicit X authentication failure');
    $failed = x_widget_connection_status();
    v1172b_check(($failed['state'] ?? null) === 'auth_failed', '401 marks the current Bearer Token as authentication failed');
    v1172b_check(is_string($failed['checked_at'] ?? null) && $failed['checked_at'] !== '', 'authentication result records a check timestamp');

    $json = x_api_request_json('https://api.x.com/2/test', static fn(string $url, string $token): array => [
        'ok' => true,
        'status' => 200,
        'body' => '{"data":{"id":"1"}}',
    ]);
    v1172b_check(($json['data']['id'] ?? null) === '1', 'valid X API JSON is returned after successful authentication');
    $verified = x_widget_connection_status();
    v1172b_check(($verified['state'] ?? null) === 'verified', 'successful X API response replaces prior auth failure with verified state');

    $public = api_x_config_status(7, []);
    $publicState = $public['body']['data']['x_api'] ?? null;
    v1172b_check(($public['status'] ?? null) === 200 && is_array($publicState), 'x.config.status returns a normal authenticated API payload');
    v1172b_check(($publicState['state'] ?? null) === 'verified', 'public connection status exposes only the normalized state');
    v1172b_check(!array_key_exists('token_fingerprint', $publicState), 'public connection status does not expose the token fingerprint');
    $encoded = json_encode($public, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    v1172b_check(!str_contains($encoded, APP_X_BEARER_TOKEN), 'public connection status never exposes the Bearer Token value');

    $cachePath = x_widget_connection_status_cache_path();
    $cacheText = is_file($cachePath) ? (string) file_get_contents($cachePath) : '';
    v1172b_check($cacheText !== '' && !str_contains($cacheText, APP_X_BEARER_TOKEN), 'persistent connection-state cache stores no raw Bearer Token');
    v1172b_check(x_widget_connection_status_cache_read('different-placeholder-token') === null, 'connection state is ignored when the token fingerprint changes');
} finally {
    v1172b_rrmdir($cacheRoot);
}

echo "SUMMARY PASS={$pass} FAIL={$fail}\n";
exit($fail === 0 ? 0 : 1);
