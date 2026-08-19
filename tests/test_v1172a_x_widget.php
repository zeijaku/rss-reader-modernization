<?php

declare(strict_types=1);

$cacheRoot = sys_get_temp_dir() . '/rss-reader-v1172a-x-' . bin2hex(random_bytes(6));
define('APP_X_BEARER_TOKEN', 'v1172a-test-token-not-secret');
define('APP_X_CACHE_TTL_SECONDS', 300);
define('APP_X_STALE_MAX_AGE_SECONDS', 3600);
define('APP_X_TIMEOUT_MS', 5000);
define('APP_X_CACHE_DIR', $cacheRoot);
define('APP_ENV', 'testing');
define('APP_DEBUG', false);
define('APP_LOG_ENABLED', false);

require dirname(__DIR__) . '/app/bootstrap.php';

$pass = 0;
$fail = 0;

function v1172a_check(bool $condition, string $message): void
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

function v1172a_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        is_dir($path) ? v1172a_rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

try {
    v1172a_check(in_array('x_timeline', dashboard_widget_types(), true), 'dashboard widget type includes x_timeline');
    v1172a_check(in_array('x_timeline', information_widget_types(), true), 'shared information persistence accepts x_timeline');

    v1172a_check(x_widget_validate_username('@XDevelopers') === 'XDevelopers', 'username accepts leading @ and normalizes it');
    v1172a_check(x_widget_validate_username('abc_123') === 'abc_123', 'username accepts X handle characters');
    v1172a_check(x_widget_validate_username('bad-handle') === null, 'username rejects unsupported characters');
    v1172a_check(x_widget_validate_username(str_repeat('a', 16)) === null, 'username rejects more than 15 characters');

    $config = x_widget_config_from_input([
        'x_title' => 'X News',
        'x_username' => '@XDevelopers',
        'x_display_count' => '3',
        'x_show_replies' => '0',
        'x_show_reposts' => '0',
    ]);
    v1172a_check(is_array($config), 'valid X widget config is normalized');
    v1172a_check(($config['username'] ?? null) === 'XDevelopers', 'normalized config stores username without @');
    v1172a_check(($config['display_count'] ?? null) === 3, 'display count 3 is accepted');
    v1172a_check(($config['show_replies'] ?? true) === false && ($config['show_reposts'] ?? true) === false, 'reply/repost filters are normalized');
    v1172a_check(x_widget_validate_display_count('4') === null, 'unsupported display count is rejected');

    foreach ([401 => 'x_auth_failed', 403 => 'x_access_forbidden', 404 => 'x_not_found', 429 => 'x_rate_or_usage_limited'] as $status => $reason) {
        $actualReason = '';
        try {
            x_api_request_json('https://api.x.com/2/test', static fn(string $url, string $token): array => [
                'ok' => true, 'status' => $status, 'body' => '{\"title\":\"test\"}',
            ]);
        } catch (XApiRequestException $exception) {
            $actualReason = $exception->reasonCode();
        }
        v1172a_check($actualReason === $reason, 'HTTP ' . $status . ' maps to ' . $reason);
    }

    $calls = [];
    $fetcher = static function (string $url, string $token) use (&$calls): array {
        $calls[] = ['url' => $url, 'token' => $token];
        if (str_contains($url, '/2/users/by/username/')) {
            return [
                'ok' => true,
                'status' => 200,
                'body' => json_encode(['data' => [
                    'id' => '2244994945',
                    'name' => 'X Developers',
                    'username' => 'XDevelopers',
                    'protected' => false,
                ]], JSON_UNESCAPED_SLASHES),
            ];
        }
        if (str_contains($url, '/2/users/2244994945/tweets')) {
            $posts = [];
            for ($i = 1; $i <= 5; $i++) {
                $posts[] = [
                    'id' => (string) (1000000000000000000 + $i),
                    'text' => 'Post ' . $i,
                    'created_at' => sprintf('2026-08-19T0%d:00:00Z', $i),
                ];
            }
            return [
                'ok' => true,
                'status' => 200,
                'body' => json_encode(['data' => $posts], JSON_UNESCAPED_SLASHES),
            ];
        }
        return ['ok' => false, 'status' => 500, 'body' => ''];
    };

    /** @var array<string,mixed> $config */
    $timeline = x_widget_fetch_timeline($config, true, $fetcher);
    v1172a_check(count($timeline['posts'] ?? []) === 3, 'display count 3 returns only three posts');
    v1172a_check(($timeline['account']['username'] ?? null) === 'XDevelopers', 'timeline returns normalized public account data');
    v1172a_check(($timeline['stale'] ?? null) === false, 'successful API result is not stale');

    $timelineCalls = array_values(array_filter($calls, static fn(array $call): bool => str_contains($call['url'], '/tweets')));
    $timelineUrl = (string) ($timelineCalls[0]['url'] ?? '');
    $query = [];
    parse_str((string) parse_url($timelineUrl, PHP_URL_QUERY), $query);
    v1172a_check(($query['max_results'] ?? null) === '5', 'X API minimum max_results is five when widget displays three');
    v1172a_check(($query['post_fields'] ?? $query['post.fields'] ?? null) === 'created_at', 'timeline requests created_at via post.fields');
    v1172a_check(($query['exclude'] ?? null) === 'replies,retweets', 'timeline excludes replies and retweets by default');
    v1172a_check(!str_contains(json_encode($timeline, JSON_UNESCAPED_SLASHES) ?: '', APP_X_BEARER_TOKEN), 'Bearer Token is not included in normalized timeline output');
    v1172a_check(count($calls) === 2 && $calls[0]['token'] === APP_X_BEARER_TOKEN && $calls[1]['token'] === APP_X_BEARER_TOKEN, 'Bearer Token is used only by server-side fetch callback');

    $cachedCalls = 0;
    $cachedFetcher = static function () use (&$cachedCalls): array {
        $cachedCalls++;
        return ['ok' => false, 'status' => 500, 'body' => ''];
    };
    $cached = x_widget_fetch_timeline($config, false, $cachedFetcher);
    v1172a_check($cachedCalls === 0, 'fresh timeline cache avoids another X API request');
    v1172a_check(($cached['posts'][0]['text'] ?? null) === 'Post 1', 'fresh timeline cache preserves posts');

    $failingFetcher = static fn(string $url, string $token): array => [
        'ok' => false,
        'status' => 0,
        'body' => '',
        'error_code' => 'timeout',
        'error_message' => 'test timeout',
    ];
    $fallback = x_widget_fetch_timeline($config, true, $failingFetcher);
    v1172a_check(($fallback['stale'] ?? null) === true, 'API failure falls back to cached timeline and marks it stale');
    v1172a_check(($fallback['posts'][2]['text'] ?? null) === 'Post 3', 'stale fallback keeps the last successful posts');

    $forbiddenReason = '';
    $forbiddenFetcher = static fn(string $url, string $token): array => [
        'ok' => true,
        'status' => 403,
        'body' => '{\"title\":\"Forbidden\"}',
    ];
    try {
        x_widget_fetch_timeline($config, true, $forbiddenFetcher);
    } catch (XApiRequestException $exception) {
        $forbiddenReason = $exception->reasonCode();
    }
    v1172a_check($forbiddenReason === 'x_access_forbidden', 'authorization/access failure does not expose stale cached posts');

    $requestCaptured = null;
    $resolver = static fn(string $host): array => $host === 'api.x.com' ? ['104.244.42.193'] : [];
    $transport = static function (array $request) use (&$requestCaptured): array {
        $requestCaptured = $request;
        return [
            'ok' => true,
            'status' => 200,
            'body' => '{"data":{}}',
            'error_code' => '',
            'error_message' => '',
        ];
    };
    $safe = x_api_safe_fetch('https://api.x.com/2/users/by/username/XDevelopers', APP_X_BEARER_TOKEN, $resolver, $transport);
    v1172a_check(($safe['ok'] ?? false) === true, 'safe X fetch accepts only the fixed HTTPS API host');
    v1172a_check(is_array($requestCaptured) && ($requestCaptured['authorization_bearer'] ?? null) === APP_X_BEARER_TOKEN, 'safe transport receives explicit Bearer field');
    v1172a_check(is_array($requestCaptured) && ($requestCaptured['host'] ?? null) === 'api.x.com' && ($requestCaptured['ip'] ?? null) === '104.244.42.193', 'safe transport keeps DNS-pinned X target');
    $blocked = x_api_safe_fetch('https://example.com/2/users/1/tweets', APP_X_BEARER_TOKEN, $resolver, $transport);
    v1172a_check(($blocked['ok'] ?? true) === false && ($blocked['error_code'] ?? null) === 'invalid_target', 'safe X fetch rejects non-X hosts');

    $protectedFetcher = static function (string $url, string $token): array {
        return [
            'ok' => true,
            'status' => 200,
            'body' => json_encode(['data' => [
                'id' => '123456789',
                'name' => 'Private Account',
                'username' => 'PrivateTest',
                'protected' => true,
            ]], JSON_UNESCAPED_SLASHES),
        ];
    };
    $protectedConfig = $config;
    $protectedConfig['username'] = 'PrivateTest';
    $protectedReason = '';
    try {
        x_widget_fetch_timeline($protectedConfig, true, $protectedFetcher);
    } catch (XApiRequestException $exception) {
        $protectedReason = $exception->reasonCode();
    }
    v1172a_check($protectedReason === 'x_protected_account', 'protected accounts return a specific app-only access error');
} finally {
    v1172a_rrmdir($cacheRoot);
}

echo "SUMMARY PASS={$pass} FAIL={$fail}\n";
exit($fail === 0 ? 0 : 1);
