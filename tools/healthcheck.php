<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

// Configure session policy without starting a browser session.
app_session_configure();
$status = app_runtime_status();

echo "RSS Reader runtime health check\n";
echo "Build: " . APP_VERSION_LABEL . "\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "DB driver: " . $status['driver'] . "\n";
echo "DB table prefix: " . $status['table_prefix'] . "\n";
echo "PDO drivers: " . ($status['pdo_drivers'] ? implode(', ', $status['pdo_drivers']) : '(none)') . "\n";
echo "Environment: " . APP_ENV . "\n";
echo "Debug mode: " . (APP_DEBUG ? 'ON' : 'off') . "\n";
echo "APP_HASH_KEY configured: " . ((string) INI_HASH_KEY !== '' ? 'yes' : 'no') . "\n";
echo "APP_HASH_KEY minimum length: " . (strlen((string) INI_HASH_KEY) >= 32 ? 'yes' : 'no') . "\n";
echo "Private config: " . ($status['local_config_present'] ? 'present' : 'not present') . "\n";
echo "Session save path: " . app_session_storage_path() . "\n";
echo "Session directory writable: " . (is_writable(app_session_storage_path()) ? 'yes' : 'no') . "\n";
echo "Session idle timeout: " . SESSION_IDLE_TIMEOUT . " seconds\n";
echo "Session absolute timeout: " . SESSION_ABSOLUTE_TIMEOUT . " seconds\n";
echo "Registration enabled: " . (REGISTRATION_ENABLED ? 'yes' : 'no') . "\n";
echo "Password minimum: " . AUTH_PASSWORD_MIN_LENGTH . " characters\n";
echo "Feed cache enabled: " . ($status['feed_cache_enabled'] ? 'yes' : 'no') . "\n";
echo "Feed cache TTL: " . $status['feed_cache_ttl_seconds'] . " seconds\n";
echo "Feed cache path: " . $status['feed_cache_dir'] . "\n";
echo "Feed cache writable: " . (is_writable((string) $status['feed_cache_dir']) ? 'yes' : 'no') . "\n";

$throttleDirectory = login_throttle_prepare_directory();
echo "Login throttle storage: " . $throttleDirectory . "\n";
echo "Login throttle writable: " . (is_writable($throttleDirectory) ? 'yes' : 'no') . "\n";

$publicRoot = dirname(__DIR__) . '/public';
$publicRealPath = realpath($publicRoot);
$pathIsOutsidePublic = static function (string $path) use ($publicRealPath): bool {
    $pathReal = realpath($path);
    if (!is_string($publicRealPath) || !is_string($pathReal)) {
        return false;
    }

    return $pathReal !== $publicRealPath
        && !str_starts_with($pathReal . DIRECTORY_SEPARATOR, $publicRealPath . DIRECTORY_SEPARATOR);
};

$sessionPrivate = $pathIsOutsidePublic(app_session_storage_path());
$cachePrivate = !$status['feed_cache_enabled'] || $pathIsOutsidePublic((string) $status['feed_cache_dir']);
$throttlePrivate = $pathIsOutsidePublic($throttleDirectory);
$localConfigPrivate = !$status['local_config_present'] || $pathIsOutsidePublic(dirname(__DIR__) . '/config/local.php');

echo "Session storage outside public/: " . ($sessionPrivate ? 'yes' : 'no') . "\n";
echo "Feed cache outside public/: " . ($cachePrivate ? 'yes' : 'no') . "\n";
echo "Login throttle outside public/: " . ($throttlePrivate ? 'yes' : 'no') . "\n";
echo "Private config outside public/: " . ($localConfigPrivate ? 'yes' : 'no') . "\n";
$requiredAssets = [
    'css/bootstrap.min.css',
    'css/all.css',
    'css/drawer.min.css',
    'js/jquery-3.7.1.min.js',
    'js/popper.min.js',
    'js/bootstrap.min.js',
];

$assetIssues = [];
foreach ($requiredAssets as $asset) {
    if (!is_file($publicRoot . '/' . $asset)) {
        $assetIssues[] = 'Missing public asset: ' . $asset;
    }
}

if ($assetIssues) {
    $status['issues'] = array_merge($status['issues'], $assetIssues);
}

if (!is_writable(app_session_storage_path())) {
    $status['issues'][] = 'Session storage is not writable.';
}
if (!is_writable($throttleDirectory)) {
    $status['issues'][] = 'Login throttle storage is not writable.';
}
if (APP_ENV === 'production' && APP_DEBUG) {
    $status['issues'][] = 'APP_DEBUG must be disabled in production.';
}
if (!$sessionPrivate) {
    $status['issues'][] = 'Session storage must remain outside public/.';
}
if (!$cachePrivate) {
    $status['issues'][] = 'Feed cache storage must remain outside public/.';
}
if (!$throttlePrivate) {
    $status['issues'][] = 'Login throttle storage must remain outside public/.';
}
if (!$localConfigPrivate) {
    $status['issues'][] = 'Private config must remain outside public/.';
}

echo "Required public assets: " . ($assetIssues ? 'missing' : 'present') . "\n";

if ($status['issues']) {
    echo "STATUS: NOT READY\n";
    foreach ($status['issues'] as $issue) {
        echo "- {$issue}\n";
    }
    exit(1);
}

echo "STATUS: CONFIGURATION READY\n";
exit(0);
