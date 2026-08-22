<?php

declare(strict_types=1);

/**
 * Runtime configuration.
 *
 * Precedence: environment variable > config/local.php > safe default.
 * config/local.php is private (outside DocumentRoot) and must never be committed.
 */
function app_local_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $config = [];
    $path = dirname(__DIR__, 2) . '/config/local.php';
    if (!is_file($path)) {
        return $config;
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('config/local.php must return an array.');
    }

    $config = $loaded;
    return $config;
}

function app_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    $local = app_local_config();
    if (array_key_exists($name, $local) && $local[$name] !== null && $local[$name] !== '') {
        if (is_bool($local[$name])) {
            return $local[$name] ? 'true' : 'false';
        }
        if (is_scalar($local[$name])) {
            return (string) $local[$name];
        }
        throw new RuntimeException(sprintf('Configuration value %s must be scalar.', $name));
    }

    return $default;
}

function app_env_bool(string $name, bool $default = false): bool
{
    $value = app_env($name);
    if ($value === null) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
}

if (!defined('APP_ENV')) {
    define('APP_ENV', app_env('APP_ENV', 'production'));
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', app_env_bool('APP_DEBUG', false));
}
if (!defined('APP_LOG_ENABLED')) {
    define('APP_LOG_ENABLED', app_env_bool('APP_LOG_ENABLED', false));
}
if (!defined('APP_LOG_PATH')) {
    define('APP_LOG_PATH', app_env('APP_LOG_PATH', dirname(__DIR__, 2) . '/var/log/access.log'));
}
if (!defined('APP_ERROR_LOG_PATH')) {
    define('APP_ERROR_LOG_PATH', app_env('APP_ERROR_LOG_PATH', dirname(__DIR__, 2) . '/var/log/error.log'));
}


if (!defined('REGISTRATION_ENABLED')) {
    define('REGISTRATION_ENABLED', app_env_bool('REGISTRATION_ENABLED', true));
}
if (!defined('AUTH_PASSWORD_MIN_LENGTH')) {
    define('AUTH_PASSWORD_MIN_LENGTH', max(8, (int) app_env('AUTH_PASSWORD_MIN_LENGTH', '12')));
}
if (!defined('AUTH_PASSWORD_MAX_LENGTH')) {
    define('AUTH_PASSWORD_MAX_LENGTH', max(AUTH_PASSWORD_MIN_LENGTH, (int) app_env('AUTH_PASSWORD_MAX_LENGTH', '72')));
}
if (!defined('SESSION_COOKIE_NAME')) {
    define('SESSION_COOKIE_NAME', app_env('SESSION_COOKIE_NAME', 'iguguru_session'));
}
if (!defined('SESSION_IDLE_TIMEOUT')) {
    define('SESSION_IDLE_TIMEOUT', max(300, (int) app_env('SESSION_IDLE_TIMEOUT', '7200')));
}
if (!defined('SESSION_ABSOLUTE_TIMEOUT')) {
    define('SESSION_ABSOLUTE_TIMEOUT', max(SESSION_IDLE_TIMEOUT, (int) app_env('SESSION_ABSOLUTE_TIMEOUT', '43200')));
}
if (!defined('LOGIN_RATE_WINDOW')) {
    define('LOGIN_RATE_WINDOW', max(60, (int) app_env('LOGIN_RATE_WINDOW', '900')));
}
if (!defined('LOGIN_RATE_MAX_PAIR')) {
    define('LOGIN_RATE_MAX_PAIR', max(2, (int) app_env('LOGIN_RATE_MAX_PAIR', '5')));
}
if (!defined('LOGIN_RATE_MAX_IP')) {
    define('LOGIN_RATE_MAX_IP', max(LOGIN_RATE_MAX_PAIR, (int) app_env('LOGIN_RATE_MAX_IP', '30')));
}
if (!defined('LOGIN_RATE_BLOCK_SECONDS')) {
    define('LOGIN_RATE_BLOCK_SECONDS', max(60, (int) app_env('LOGIN_RATE_BLOCK_SECONDS', '900')));
}
if (!defined('REGISTRATION_RATE_WINDOW')) {
    define('REGISTRATION_RATE_WINDOW', max(60, (int) app_env('REGISTRATION_RATE_WINDOW', '900')));
}
if (!defined('REGISTRATION_RATE_MAX_IP')) {
    define('REGISTRATION_RATE_MAX_IP', max(1, (int) app_env('REGISTRATION_RATE_MAX_IP', '10')));
}
if (!defined('REGISTRATION_RATE_BLOCK_SECONDS')) {
    define('REGISTRATION_RATE_BLOCK_SECONDS', max(60, (int) app_env('REGISTRATION_RATE_BLOCK_SECONDS', '900')));
}
if (!defined('APP_API_MAX_REQUEST_BYTES')) {
    // Application-level guard. Keep the Web server/PHP post_max_size at or
    // below an appropriate deployment limit as PHP parses normal POST data
    // before userland code runs.
    define('APP_API_MAX_REQUEST_BYTES', max(65536, min(4194304, (int) app_env('APP_API_MAX_REQUEST_BYTES', '1048576'))));
}


if (!defined('APP_HTTP_CONNECT_TIMEOUT_MS')) {
    define('APP_HTTP_CONNECT_TIMEOUT_MS', max(500, (int) app_env('APP_HTTP_CONNECT_TIMEOUT_MS', '3000')));
}
if (!defined('APP_HTTP_TIMEOUT_MS')) {
    define('APP_HTTP_TIMEOUT_MS', max(APP_HTTP_CONNECT_TIMEOUT_MS, (int) app_env('APP_HTTP_TIMEOUT_MS', '8000')));
}
if (!defined('APP_HTTP_MAX_REDIRECTS')) {
    define('APP_HTTP_MAX_REDIRECTS', max(0, min(5, (int) app_env('APP_HTTP_MAX_REDIRECTS', '3'))));
}
if (!defined('APP_HTTP_MAX_BYTES')) {
    define('APP_HTTP_MAX_BYTES', max(65536, min(8388608, (int) app_env('APP_HTTP_MAX_BYTES', '2097152'))));
}
if (!defined('APP_HTTP_USER_AGENT')) {
    define('APP_HTTP_USER_AGENT', app_env('APP_HTTP_USER_AGENT', 'iGuguru-RSS/1.0 (+Secure-Baseline)'));
}

if (!defined('APP_FEED_CACHE_ENABLED')) {
    define('APP_FEED_CACHE_ENABLED', app_env_bool('APP_FEED_CACHE_ENABLED', true));
}
if (!defined('APP_FEED_CONDITIONAL_REQUEST_ENABLED')) {
    define('APP_FEED_CONDITIONAL_REQUEST_ENABLED', app_env_bool('APP_FEED_CONDITIONAL_REQUEST_ENABLED', true));
}
if (!defined('APP_FEED_CACHE_TTL_SECONDS')) {
    define('APP_FEED_CACHE_TTL_SECONDS', max(1, min(86400, (int) app_env('APP_FEED_CACHE_TTL_SECONDS', '60'))));
}
if (!defined('APP_FEED_CACHE_LOCK_TIMEOUT_MS')) {
    define('APP_FEED_CACHE_LOCK_TIMEOUT_MS', max(0, min(30000, (int) app_env('APP_FEED_CACHE_LOCK_TIMEOUT_MS', '9000'))));
}
if (!defined('APP_FEED_RETRY_ENABLED')) {
    define('APP_FEED_RETRY_ENABLED', app_env_bool('APP_FEED_RETRY_ENABLED', true));
}
if (!defined('APP_FEED_RETRY_MAX_DELAY_SECONDS')) {
    define('APP_FEED_RETRY_MAX_DELAY_SECONDS', max(60, min(86400, (int) app_env('APP_FEED_RETRY_MAX_DELAY_SECONDS', '3600'))));
}
if (!defined('APP_FEED_STALE_IF_ERROR_ENABLED')) {
    define('APP_FEED_STALE_IF_ERROR_ENABLED', app_env_bool('APP_FEED_STALE_IF_ERROR_ENABLED', true));
}
if (!defined('APP_FEED_STALE_MAX_AGE_SECONDS')) {
    define('APP_FEED_STALE_MAX_AGE_SECONDS', max(APP_FEED_CACHE_TTL_SECONDS, min(604800, (int) app_env('APP_FEED_STALE_MAX_AGE_SECONDS', '86400'))));
}
if (!defined('APP_FEED_CACHE_DIR')) {
    define('APP_FEED_CACHE_DIR', dirname(__DIR__, 2) . '/var/cache/feed');
}
if (!defined('APP_FEED_ITEM_STATE_RETENTION_DAYS')) {
    define('APP_FEED_ITEM_STATE_RETENTION_DAYS', max(1, min(3650, (int) app_env('APP_FEED_ITEM_STATE_RETENTION_DAYS', '90'))));
}

if (!defined('APP_HOLIDAY_CSV_URL')) {
    define('APP_HOLIDAY_CSV_URL', app_env('APP_HOLIDAY_CSV_URL', 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv'));
}
if (!defined('APP_HOLIDAY_CACHE_DAYS')) {
    define('APP_HOLIDAY_CACHE_DAYS', max(1, min(365, (int) app_env('APP_HOLIDAY_CACHE_DAYS', '60'))));
}
if (!defined('APP_HOLIDAY_TIMEOUT_MS')) {
    define('APP_HOLIDAY_TIMEOUT_MS', max(1000, min(10000, (int) app_env('APP_HOLIDAY_TIMEOUT_MS', '5000'))));
}
if (!defined('APP_HOLIDAY_CSV_MAX_BYTES')) {
    define('APP_HOLIDAY_CSV_MAX_BYTES', 524288);
}
if (!defined('APP_HOLIDAY_CACHE_MAX_BYTES')) {
    define('APP_HOLIDAY_CACHE_MAX_BYTES', 1048576);
}
if (!defined('APP_HOLIDAY_CACHE_PATH')) {
    define('APP_HOLIDAY_CACHE_PATH', dirname(__DIR__, 2) . '/var/cache/japanese_holidays.json');
}
if (!defined('APP_HOLIDAY_LOCK_PATH')) {
    define('APP_HOLIDAY_LOCK_PATH', dirname(__DIR__, 2) . '/var/cache/japanese_holidays.lock');
}


if (!defined('APP_WEATHER_GEOCODING_URL')) {
    define('APP_WEATHER_GEOCODING_URL', app_env('APP_WEATHER_GEOCODING_URL', 'https://geocoding-api.open-meteo.com/v1/search'));
}
if (!defined('APP_WEATHER_FORECAST_URL')) {
    define('APP_WEATHER_FORECAST_URL', app_env('APP_WEATHER_FORECAST_URL', 'https://api.open-meteo.com/v1/forecast'));
}
if (!defined('APP_WEATHER_CACHE_TTL_SECONDS')) {
    define('APP_WEATHER_CACHE_TTL_SECONDS', max(300, min(21600, (int) app_env('APP_WEATHER_CACHE_TTL_SECONDS', '1800'))));
}
if (!defined('APP_WEATHER_TIMEOUT_MS')) {
    define('APP_WEATHER_TIMEOUT_MS', max(1000, min(10000, (int) app_env('APP_WEATHER_TIMEOUT_MS', '5000'))));
}
if (!defined('APP_WEATHER_CACHE_DIR')) {
    define('APP_WEATHER_CACHE_DIR', dirname(__DIR__, 2) . '/var/cache/weather');
}

// V1.17.2 X API Widget. Bearer Token remains private server configuration.
if (!defined('APP_X_BEARER_TOKEN')) {
    define('APP_X_BEARER_TOKEN', app_env('APP_X_BEARER_TOKEN', ''));
}
if (!defined('APP_X_CACHE_TTL_SECONDS')) {
    define('APP_X_CACHE_TTL_SECONDS', max(60, min(3600, (int) app_env('APP_X_CACHE_TTL_SECONDS', '300'))));
}
if (!defined('APP_X_STALE_MAX_AGE_SECONDS')) {
    define('APP_X_STALE_MAX_AGE_SECONDS', max(APP_X_CACHE_TTL_SECONDS, min(86400, (int) app_env('APP_X_STALE_MAX_AGE_SECONDS', '3600'))));
}
if (!defined('APP_X_TIMEOUT_MS')) {
    define('APP_X_TIMEOUT_MS', max(1000, min(10000, (int) app_env('APP_X_TIMEOUT_MS', '5000'))));
}
if (!defined('APP_X_CACHE_DIR')) {
    define('APP_X_CACHE_DIR', dirname(__DIR__, 2) . '/var/cache/x');
}

if (!defined('DB_DRIVER')) {
    define('DB_DRIVER', app_env('DB_DRIVER', 'mysql'));
}
if (!defined('DB_HOST')) {
    define('DB_HOST', app_env('DB_HOST', ''));
}
if (!defined('DB_PORT')) {
    define('DB_PORT', app_env('DB_PORT', '3306'));
}
if (!defined('DB_CONNECT')) {
    define('DB_CONNECT', app_env('DB_NAME', ''));
}
if (!defined('DB_USER')) {
    define('DB_USER', app_env('DB_USER', ''));
}
if (!defined('DB_PW')) {
    define('DB_PW', app_env('DB_PASSWORD', ''));
}

/**
 * Validate the configurable database table prefix before it is interpolated
 * into SQL identifiers. PDO parameters cannot bind identifiers, so only
 * ASCII letters, digits and underscore are accepted. The prefix must start
 * with a letter or underscore so generated identifiers remain unambiguous.
 */
function db_validate_table_prefix(string $prefix): string
{
    if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,39}\z/D', $prefix) !== 1) {
        throw new RuntimeException('DB_TABLE_PREFIX must be 1-40 ASCII characters, start with a letter or underscore, and contain only letters, digits, underscore.');
    }

    return $prefix;
}

if (!defined('DB_TABLE_PREFIX')) {
    // Keep ig_ as the runtime fallback so an existing SB-13 R1 database does
    // not change table names merely by deploying R2 without editing local.php.
    define('DB_TABLE_PREFIX', db_validate_table_prefix((string) app_env('DB_TABLE_PREFIX', 'ig_')));
}

/** Return the physical table name for a known logical table. */
function db_table_name(string $logicalName): string
{
    static $allowed = ['user_info', 'user_conf', 'content', 'content_stock', 'feed_item_state', 'memo', 'task', 'calendar_event', 'dashboard_widget', 'remember_token', 'link_item', 'stock_tag', 'stock_tag_map', 'feed_keyword'];
    if (!in_array($logicalName, $allowed, true)) {
        throw new InvalidArgumentException('Unknown database table name.');
    }

    return (string) DB_TABLE_PREFIX . $logicalName;
}

/** Return a safely quoted physical table identifier. */
function db_table_identifier(string $logicalName): string
{
    // DB_TABLE_PREFIX and the logical names are constrained to [A-Za-z0-9_].
    return '`' . db_table_name($logicalName) . '`';
}

if (!defined('DB_SQLITE_PATH')) {
    define('DB_SQLITE_PATH', app_env('DB_SQLITE_PATH', ':memory:'));
}

if (!defined('INI_HASH_KEY')) {
    define('INI_HASH_KEY', app_env('APP_HASH_KEY', ''));
}

/** Return non-secret runtime readiness information. */
function app_runtime_status(): array
{
    $driver = strtolower((string) DB_DRIVER);
    $pdoDrivers = class_exists(PDO::class) ? PDO::getAvailableDrivers() : [];

    $issues = [];
    if (PHP_VERSION_ID < 80100) {
        $issues[] = 'PHP 8.1 or newer is required by the Secure Baseline runtime.';
    }
    if (!class_exists(PDO::class)) {
        $issues[] = 'PDO extension is unavailable.';
    }
    if ($driver === 'mysql' && !in_array('mysql', $pdoDrivers, true)) {
        $issues[] = 'pdo_mysql extension is unavailable.';
    }
    if ((string) INI_HASH_KEY === '' || strlen((string) INI_HASH_KEY) < 32) {
        $issues[] = 'APP_HASH_KEY is missing or too short (minimum 32 characters).';
    }
    if (!function_exists('curl_init')) {
        $issues[] = 'cURL extension is unavailable (required for safe RSS fetching).';
    }
    if (!function_exists('simplexml_load_string')) {
        $issues[] = 'SimpleXML extension is unavailable (required for RSS/Atom parsing).';
    }
    if (!function_exists('mb_detect_encoding')) {
        $issues[] = 'mbstring extension is unavailable (required for RSS/Atom character encoding handling).';
    }
    if (SESSION_ABSOLUTE_TIMEOUT < SESSION_IDLE_TIMEOUT) {
        $issues[] = 'SESSION_ABSOLUTE_TIMEOUT must be greater than or equal to SESSION_IDLE_TIMEOUT.';
    }
    if (APP_FEED_CACHE_ENABLED) {
        if (!is_dir((string) APP_FEED_CACHE_DIR) || !is_writable((string) APP_FEED_CACHE_DIR)) {
            $issues[] = 'Feed cache directory is missing or not writable: ' . (string) APP_FEED_CACHE_DIR;
        }
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        $cacheRoot = realpath((string) APP_FEED_CACHE_DIR);
        if (is_string($publicRoot) && is_string($cacheRoot) && str_starts_with($cacheRoot . DIRECTORY_SEPARATOR, $publicRoot . DIRECTORY_SEPARATOR)) {
            $issues[] = 'Feed cache directory must remain outside public/.';
        }
    }

    if ($driver === 'mysql') {
        foreach ([
            'DB_HOST' => DB_HOST,
            'DB_NAME' => DB_CONNECT,
            'DB_USER' => DB_USER,
            'DB_PASSWORD' => DB_PW,
        ] as $label => $value) {
            if ((string) $value === '') {
                $issues[] = $label . ' is not configured.';
            }
        }
    }

    return [
        'ok' => $issues === [],
        'driver' => $driver,
        'table_prefix' => (string) DB_TABLE_PREFIX,
        'pdo_drivers' => $pdoDrivers,
        'issues' => $issues,
        'local_config_present' => is_file(dirname(__DIR__, 2) . '/config/local.php'),
        'feed_cache_enabled' => (bool) APP_FEED_CACHE_ENABLED,
        'feed_conditional_request_enabled' => (bool) APP_FEED_CONDITIONAL_REQUEST_ENABLED,
        'feed_cache_ttl_seconds' => (int) APP_FEED_CACHE_TTL_SECONDS,
        'feed_cache_dir' => (string) APP_FEED_CACHE_DIR,
    ];
}
