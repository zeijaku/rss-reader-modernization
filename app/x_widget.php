<?php

declare(strict_types=1);

/**
 * V1.17.2 X API Widget.
 *
 * The Bearer Token stays in private server configuration. Browser clients only
 * receive normalized public account/post data and a non-secret connection state
 * through api_v1.php.
 */

const X_API_HOST = 'api.x.com';

final class XApiRequestException extends RuntimeException
{
    private int $responseStatus;
    private string $reasonCode;

    public function __construct(string $message, int $responseStatus = 0, string $reasonCode = 'x_api_error')
    {
        parent::__construct($message);
        $this->responseStatus = $responseStatus;
        $this->reasonCode = $reasonCode;
    }

    public function responseStatus(): int
    {
        return $this->responseStatus;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}

/** @return array{schema:int,title:string,username:string,display_count:int,show_replies:bool,show_reposts:bool} */
function x_widget_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'X',
        'username' => '',
        'display_count' => 5,
        'show_replies' => false,
        'show_reposts' => false,
    ];
}

function x_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

function x_widget_validate_username(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $username = trim($value);
    if (str_starts_with($username, '@')) {
        $username = substr($username, 1);
    }
    if (preg_match('/\A[A-Za-z0-9_]{1,15}\z/D', $username) !== 1) {
        return null;
    }
    return $username;
}

function x_widget_validate_display_count(mixed $value): ?int
{
    $count = app_validate_positive_int($value);
    return $count !== null && in_array($count, [3, 5, 10], true) ? $count : null;
}

/** @return array{schema:int,title:string,username:string,display_count:int,show_replies:bool,show_reposts:bool}|null */
function x_widget_config_from_input(array $input): ?array
{
    $title = x_widget_validate_title($input['x_title'] ?? null);
    $username = x_widget_validate_username($input['x_username'] ?? null);
    $displayCount = x_widget_validate_display_count($input['x_display_count'] ?? null);
    $showReplies = dashboard_widget_validate_boolean($input['x_show_replies'] ?? null);
    $showReposts = dashboard_widget_validate_boolean($input['x_show_reposts'] ?? null);
    if ($title === null || $username === null || $displayCount === null || $showReplies === null || $showReposts === null) {
        return null;
    }

    return [
        'schema' => 1,
        'title' => $title,
        'username' => $username,
        'display_count' => $displayCount,
        'show_replies' => $showReplies,
        'show_reposts' => $showReposts,
    ];
}

/** @return array{schema:int,title:string,username:string,display_count:int,show_replies:bool,show_reposts:bool} */
function x_widget_config_from_storage(mixed $value): array
{
    $defaults = x_widget_defaults();
    $config = dashboard_widget_decode_config($value);
    $title = x_widget_validate_title($config['title'] ?? null);
    $username = x_widget_validate_username($config['username'] ?? null);
    $displayCount = x_widget_validate_display_count($config['display_count'] ?? null);
    $showReplies = dashboard_widget_validate_boolean($config['show_replies'] ?? null);
    $showReposts = dashboard_widget_validate_boolean($config['show_reposts'] ?? null);

    return [
        'schema' => 1,
        'title' => $title ?? $defaults['title'],
        'username' => $username ?? $defaults['username'],
        'display_count' => $displayCount ?? $defaults['display_count'],
        'show_replies' => $showReplies ?? $defaults['show_replies'],
        'show_reposts' => $showReposts ?? $defaults['show_reposts'],
    ];
}

function x_api_bearer_token_raw_value(): string
{
    return trim((string) APP_X_BEARER_TOKEN);
}

function x_api_bearer_token(): ?string
{
    $token = x_api_bearer_token_raw_value();
    if ($token === '' || strlen($token) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $token) === 1) {
        return null;
    }
    return $token;
}

function x_widget_connection_status_cache_path(): string
{
    return rtrim((string) APP_X_CACHE_DIR, '/\\') . '/connection-status.json';
}

function x_widget_token_fingerprint(string $token): string
{
    return hash('sha256', $token);
}

/** @return array{state:string,checked_at:string}|null */
function x_widget_connection_status_cache_read(string $token): ?array
{
    $cached = information_widget_cache_read(
        x_widget_connection_status_cache_path(),
        'connection',
        2592000,
        2592000,
        4096,
        true,
        true
    );
    if ($cached === null
        || !is_string($cached['token_fingerprint'] ?? null)
        || !hash_equals(x_widget_token_fingerprint($token), (string) $cached['token_fingerprint'])
        || !in_array($cached['state'] ?? null, ['verified', 'auth_failed'], true)
        || !is_string($cached['checked_at'] ?? null)) {
        return null;
    }

    return [
        'state' => (string) $cached['state'],
        'checked_at' => (string) $cached['checked_at'],
    ];
}

function x_widget_connection_status_mark(string $state): void
{
    if (!in_array($state, ['verified', 'auth_failed'], true)) {
        return;
    }
    $token = x_api_bearer_token();
    if ($token === null) {
        return;
    }
    information_widget_cache_write(
        (string) APP_X_CACHE_DIR,
        x_widget_connection_status_cache_path(),
        '.x-connection-',
        'connection',
        [
            'token_fingerprint' => x_widget_token_fingerprint($token),
            'state' => $state,
            'checked_at' => gmdate('c'),
        ],
        4096
    );
}

/** @return array{state:string,configured:bool,can_add:bool,checked_at:?string} */
function x_widget_connection_status(): array
{
    $raw = x_api_bearer_token_raw_value();
    if ($raw === '') {
        return ['state' => 'missing', 'configured' => false, 'can_add' => false, 'checked_at' => null];
    }

    $token = x_api_bearer_token();
    if ($token === null) {
        return ['state' => 'invalid_format', 'configured' => true, 'can_add' => false, 'checked_at' => null];
    }

    $cached = x_widget_connection_status_cache_read($token);
    if ($cached !== null) {
        return [
            'state' => $cached['state'],
            'configured' => true,
            'can_add' => true,
            'checked_at' => $cached['checked_at'],
        ];
    }

    return ['state' => 'unverified', 'configured' => true, 'can_add' => true, 'checked_at' => null];
}

/** @return array{ok:bool,url:string,status:int,body:string,error_code:string,error_message:string} */
function x_api_safe_fetch(string $url, string $token, ?callable $resolver = null, ?callable $transport = null): array
{
    $parts = parse_url($url);
    if (!is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== X_API_HOST
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
        return ['ok' => false, 'url' => $url, 'status' => 0, 'body' => '', 'error_code' => 'invalid_target', 'error_message' => 'X API target is invalid.'];
    }

    $target = app_validate_fetch_target($url, $resolver);
    if (($target['ok'] ?? false) !== true || strtolower((string) ($target['host'] ?? '')) !== X_API_HOST) {
        return ['ok' => false, 'url' => $url, 'status' => 0, 'body' => '', 'error_code' => 'invalid_target', 'error_message' => 'X API target is invalid.'];
    }

    $transportFn = $transport ?? 'app_curl_single_hop';
    $response = $transportFn([
        'url' => (string) $target['url'],
        'host' => (string) $target['host'],
        'port' => (int) $target['port'],
        'ip' => (string) $target['ips'][0],
        'max_bytes' => 524288,
        'connect_timeout_ms' => min(APP_HTTP_CONNECT_TIMEOUT_MS, APP_X_TIMEOUT_MS),
        'total_timeout_ms' => APP_X_TIMEOUT_MS,
        'user_agent' => APP_HTTP_USER_AGENT,
        'accept' => 'application/json, */*;q=0.1',
        'request_headers' => [],
        'authorization_bearer' => $token,
    ]);

    return [
        'ok' => ($response['ok'] ?? false) === true,
        'url' => (string) ($target['url'] ?? $url),
        'status' => (int) ($response['status'] ?? 0),
        'body' => is_string($response['body'] ?? null) ? $response['body'] : '',
        'error_code' => (string) ($response['error_code'] ?? ''),
        'error_message' => (string) ($response['error_message'] ?? ''),
    ];
}

/** @return array<string,mixed> */
function x_api_request_json(string $url, ?callable $fetcher = null): array
{
    $rawToken = x_api_bearer_token_raw_value();
    $token = x_api_bearer_token();
    if ($token === null) {
        $reason = $rawToken === '' ? 'x_not_configured' : 'x_token_invalid_format';
        throw new XApiRequestException('X API Bearer Token is not configured correctly.', 503, $reason);
    }

    $response = $fetcher !== null ? $fetcher($url, $token) : x_api_safe_fetch($url, $token);
    if (!is_array($response)) {
        throw new XApiRequestException('X API transport returned an invalid response.', 0, 'x_transport_error');
    }
    $status = (int) ($response['status'] ?? 0);
    if (($response['ok'] ?? false) !== true) {
        throw new XApiRequestException('X API transport failed.', $status, 'x_transport_error');
    }
    if ($status < 200 || $status >= 300) {
        $reason = match ($status) {
            401 => 'x_auth_failed',
            403 => 'x_access_forbidden',
            404 => 'x_not_found',
            429 => 'x_rate_or_usage_limited',
            402 => 'x_credit_required',
            default => 'x_api_error',
        };
        if ($reason === 'x_auth_failed') {
            x_widget_connection_status_mark('auth_failed');
        }
        throw new XApiRequestException('X API returned HTTP ' . $status . '.', $status, $reason);
    }

    $body = is_string($response['body'] ?? null) ? $response['body'] : '';
    try {
        $json = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new XApiRequestException('X API returned invalid JSON.', $status, 'x_invalid_json');
    }
    if (!is_array($json)) {
        throw new XApiRequestException('X API returned an invalid payload.', $status, 'x_invalid_json');
    }
    x_widget_connection_status_mark('verified');
    return $json;
}

function x_widget_cache_key_username(string $username): string
{
    return strtolower($username);
}

function x_widget_account_cache_path(string $username): string
{
    $key = hash('sha256', 'account|' . x_widget_cache_key_username($username));
    return rtrim((string) APP_X_CACHE_DIR, '/\\') . '/account-' . $key . '.json';
}

/** @param array<string,mixed> $config */
function x_widget_timeline_cache_path(array $config): string
{
    $key = hash('sha256', implode('|', [
        x_widget_cache_key_username((string) $config['username']),
        (string) $config['display_count'],
        !empty($config['show_replies']) ? 'replies' : 'no-replies',
        !empty($config['show_reposts']) ? 'reposts' : 'no-reposts',
    ]));
    return rtrim((string) APP_X_CACHE_DIR, '/\\') . '/timeline-' . $key . '.json';
}

/** @return array<string,mixed>|null */
function x_widget_account_cache_read(string $username, bool $allowStale = false): ?array
{
    return information_widget_cache_read(
        x_widget_account_cache_path($username),
        'account',
        21600,
        604800,
        65536,
        $allowStale,
        true
    );
}

/** @param array<string,mixed> $account */
function x_widget_account_cache_write(string $username, array $account): void
{
    information_widget_cache_write(
        (string) APP_X_CACHE_DIR,
        x_widget_account_cache_path($username),
        '.x-account-',
        'account',
        $account,
        65536
    );
}

/** @param array<string,mixed> $config @return array<string,mixed>|null */
function x_widget_timeline_cache_read(array $config, bool $allowStale = false): ?array
{
    return information_widget_cache_read(
        x_widget_timeline_cache_path($config),
        'timeline',
        APP_X_CACHE_TTL_SECONDS,
        APP_X_STALE_MAX_AGE_SECONDS,
        262144,
        $allowStale,
        true
    );
}

/** @param array<string,mixed> $config @param array<string,mixed> $timeline */
function x_widget_timeline_cache_write(array $config, array $timeline): void
{
    information_widget_cache_write(
        (string) APP_X_CACHE_DIR,
        x_widget_timeline_cache_path($config),
        '.x-timeline-',
        'timeline',
        $timeline,
        262144
    );
}

/** @return array{id:string,name:string,username:string,protected:bool} */
function x_widget_lookup_account(string $username, ?callable $fetcher = null): array
{
    $username = x_widget_validate_username($username);
    if ($username === null) {
        throw new InvalidArgumentException('X username is invalid.');
    }

    $cached = x_widget_account_cache_read($username);
    if ($cached !== null) {
        $id = isset($cached['id']) && is_string($cached['id']) && preg_match('/\A[0-9]{1,19}\z/D', $cached['id']) === 1 ? $cached['id'] : null;
        $name = app_validate_text($cached['name'] ?? null, 100, false);
        $cachedUsername = x_widget_validate_username($cached['username'] ?? null);
        if ($id !== null && $name !== null && $cachedUsername !== null) {
            return [
                'id' => $id,
                'name' => $name,
                'username' => $cachedUsername,
                'protected' => (bool) ($cached['protected'] ?? false),
            ];
        }
    }

    $url = 'https://' . X_API_HOST . '/2/users/by/username/' . rawurlencode($username)
        . '?' . http_build_query(['user.fields' => 'name,username,protected'], '', '&', PHP_QUERY_RFC3986);
    $json = x_api_request_json($url, $fetcher);
    $data = $json['data'] ?? null;
    if (!is_array($data)) {
        throw new XApiRequestException('X user was not found.', 404, 'x_user_not_found');
    }
    $id = isset($data['id']) && is_string($data['id']) && preg_match('/\A[0-9]{1,19}\z/D', $data['id']) === 1 ? $data['id'] : null;
    $name = app_validate_text($data['name'] ?? null, 100, false);
    $resolvedUsername = x_widget_validate_username($data['username'] ?? null);
    if ($id === null || $name === null || $resolvedUsername === null) {
        throw new XApiRequestException('X user payload is invalid.', 502, 'x_invalid_payload');
    }

    $account = [
        'id' => $id,
        'name' => $name,
        'username' => $resolvedUsername,
        'protected' => (bool) ($data['protected'] ?? false),
    ];
    x_widget_account_cache_write($username, $account);
    return $account;
}

/** @param array<string,mixed> $config @param array{id:string,name:string,username:string,protected:bool} $account @return array<string,mixed> */
function x_widget_load_timeline_from_api(array $config, array $account, ?callable $fetcher = null): array
{
    if ($account['protected']) {
        throw new XApiRequestException('Protected X account posts require user-context access.', 403, 'x_protected_account');
    }

    $maxResults = max(5, (int) $config['display_count']);
    $exclude = [];
    if (empty($config['show_replies'])) {
        $exclude[] = 'replies';
    }
    if (empty($config['show_reposts'])) {
        $exclude[] = 'retweets';
    }
    $query = [
        'max_results' => $maxResults,
        'post.fields' => 'created_at',
    ];
    if ($exclude !== []) {
        $query['exclude'] = implode(',', $exclude);
    }

    $url = 'https://' . X_API_HOST . '/2/users/' . rawurlencode($account['id']) . '/tweets?'
        . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $json = x_api_request_json($url, $fetcher);
    $rawPosts = $json['data'] ?? [];
    if ($rawPosts === null) {
        $rawPosts = [];
    }
    if (!is_array($rawPosts)) {
        throw new XApiRequestException('X timeline payload is invalid.', 502, 'x_invalid_payload');
    }

    $posts = [];
    foreach ($rawPosts as $rawPost) {
        if (!is_array($rawPost)) {
            continue;
        }
        $id = isset($rawPost['id']) && is_string($rawPost['id']) && preg_match('/\A[0-9]{1,19}\z/D', $rawPost['id']) === 1 ? $rawPost['id'] : null;
        $text = app_validate_text($rawPost['text'] ?? null, 10000, false);
        $createdAt = isset($rawPost['created_at']) && is_string($rawPost['created_at']) ? trim($rawPost['created_at']) : '';
        if ($id === null || $text === null || $createdAt === '') {
            continue;
        }
        try {
            $date = new DateTimeImmutable($createdAt);
            $createdAt = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {
            continue;
        }
        $posts[] = [
            'id' => $id,
            'text' => $text,
            'created_at' => $createdAt,
            'url' => 'https://x.com/' . rawurlencode($account['username']) . '/status/' . $id,
        ];
        if (count($posts) >= (int) $config['display_count']) {
            break;
        }
    }

    return [
        'account' => [
            'name' => $account['name'],
            'username' => $account['username'],
            'url' => 'https://x.com/' . rawurlencode($account['username']),
        ],
        'posts' => $posts,
        'updated_at' => gmdate('c'),
        'stale' => false,
    ];
}

function x_widget_exception_allows_stale(XApiRequestException $exception): bool
{
    return in_array($exception->reasonCode(), [
        'x_transport_error',
        'x_rate_or_usage_limited',
        'x_api_error',
        'x_invalid_json',
        'x_invalid_payload',
    ], true);
}

/** @param array<string,mixed> $config @return array<string,mixed> */
function x_widget_fetch_timeline(array $config, bool $force = false, ?callable $fetcher = null): array
{
    $config = x_widget_config_from_storage(dashboard_widget_encode_config($config));
    if ($config['username'] === '') {
        throw new InvalidArgumentException('X username is invalid.');
    }

    if (!$force) {
        $cached = x_widget_timeline_cache_read($config);
        if ($cached !== null) {
            return $cached;
        }
    }

    try {
        $account = x_widget_lookup_account($config['username'], $fetcher);
        $timeline = x_widget_load_timeline_from_api($config, $account, $fetcher);
        x_widget_timeline_cache_write($config, $timeline);
        return $timeline;
    } catch (XApiRequestException $exception) {
        if (x_widget_exception_allows_stale($exception)) {
            $stale = x_widget_timeline_cache_read($config, true);
            if ($stale !== null) {
                $stale['stale'] = true;
                return $stale;
            }
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $config */
function x_widget_create(int $ownerId, int $location, string $style, int $width, int $height, array $config): int
{
    $validated = x_widget_config_from_input([
        'x_title' => $config['title'] ?? null,
        'x_username' => $config['username'] ?? null,
        'x_display_count' => $config['display_count'] ?? null,
        'x_show_replies' => $config['show_replies'] ?? null,
        'x_show_reposts' => $config['show_reposts'] ?? null,
    ]);
    if ($validated === null) {
        throw new InvalidArgumentException('X Widget settings are invalid.');
    }
    return information_widget_create_record($ownerId, $location, 'x_timeline', $style, $width, $height, $validated);
}

/** @param array<string,mixed> $config */
function x_widget_update(int $ownerId, int $widgetId, string $style, int $width, int $height, array $config): bool
{
    $validated = x_widget_config_from_input([
        'x_title' => $config['title'] ?? null,
        'x_username' => $config['username'] ?? null,
        'x_display_count' => $config['display_count'] ?? null,
        'x_show_replies' => $config['show_replies'] ?? null,
        'x_show_reposts' => $config['show_reposts'] ?? null,
    ]);
    if ($validated === null) {
        throw new InvalidArgumentException('X Widget settings are invalid.');
    }
    return information_widget_update_record($ownerId, $widgetId, 'x_timeline', $style, $width, $height, $validated);
}

function x_widget_delete(int $ownerId, int $widgetId): bool
{
    return information_widget_delete_record($ownerId, $widgetId, 'x_timeline');
}

/** @return array<string,mixed>|null */
function x_widget_owned_config(int $ownerId, int $widgetId): ?array
{
    return information_widget_owned_config($ownerId, $widgetId, 'x_timeline', 'x_widget_config_from_storage', 'username');
}
