<?php

declare(strict_types=1);

/**
 * V1.29 Remote File Manager production capability probe.
 * CLI only. It prints capability names/versions, never credentials.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

function remote_env_ini_bytes(string $name): ?int
{
    $raw = ini_get($name);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $raw = trim($raw);
    if (preg_match('/\A(\d+)([KMG]?)\z/i', $raw, $m) !== 1) {
        return null;
    }
    $value = (int) $m[1];
    $unit = strtoupper($m[2]);
    return match ($unit) {
        'K' => $value * 1024,
        'M' => $value * 1024 * 1024,
        'G' => $value * 1024 * 1024 * 1024,
        default => $value,
    };
}

function remote_env_bool(bool $value): string
{
    return $value ? 'OK' : 'NG';
}

/** @return array<string,mixed> */
function remote_env_local_config(): array
{
    $path = dirname(__DIR__) . '/config/local.php';
    if (!is_file($path)) {
        return [];
    }
    $config = require $path;
    return is_array($config) ? $config : [];
}

function remote_env_config_value(string $name, array $localConfig): ?string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }
    if (!array_key_exists($name, $localConfig) || $localConfig[$name] === null || $localConfig[$name] === '') {
        return null;
    }
    if (is_bool($localConfig[$name])) {
        return $localConfig[$name] ? 'true' : 'false';
    }
    return is_scalar($localConfig[$name]) ? (string) $localConfig[$name] : null;
}

function remote_env_valid_credential_key(?string $encoded): bool
{
    if (!is_string($encoded) || trim($encoded) === '') {
        return false;
    }
    $decoded = base64_decode(trim($encoded), true);
    $ok = is_string($decoded) && strlen($decoded) === 32;
    if (is_string($decoded) && function_exists('sodium_memzero')) {
        sodium_memzero($decoded);
    }
    return $ok;
}

$localConfig = remote_env_local_config();
$credentialKey = remote_env_config_value('APP_REMOTE_CREDENTIAL_KEY_B64', $localConfig);
$remoteTempDir = remote_env_config_value('APP_REMOTE_TEMP_DIR', $localConfig);
$knownHosts = remote_env_config_value('APP_REMOTE_SSH_KNOWN_HOSTS_FILE', $localConfig);

$curlAvailable = extension_loaded('curl') && function_exists('curl_version');
$curl = $curlAvailable ? curl_version() : [];
$protocols = isset($curl['protocols']) && is_array($curl['protocols'])
    ? array_values(array_unique(array_map(static fn($v): string => strtolower((string) $v), $curl['protocols'])))
    : [];
$has = static fn(string $protocol): bool => in_array($protocol, $protocols, true);

$checks = [
    'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'OpenSSL extension' => extension_loaded('openssl'),
    'Sodium extension' => extension_loaded('sodium'),
    'Remote credential key (base64 -> 32 bytes)' => remote_env_valid_credential_key($credentialKey),
    'cURL extension' => $curlAvailable,
    'cURL FTP support' => $has('ftp'),
    'cURL SFTP support' => $has('sftp'),
    'cURL HTTPS support' => $has('https'),
    'SimpleXML (WebDAV listing)' => function_exists('simplexml_load_string'),
    'CURLOPT_SSH_KNOWNHOSTS' => defined('CURLOPT_SSH_KNOWNHOSTS'),
    'CURLOPT_SSH_PRIVATE_KEYFILE' => defined('CURLOPT_SSH_PRIVATE_KEYFILE'),
];

printf("Remote File Manager environment check\n");
printf("PHP: %s\n", PHP_VERSION);
printf("cURL: %s\n", $curlAvailable ? (string) ($curl['version'] ?? 'unknown') : 'unavailable');
printf("cURL SSL: %s\n", $curlAvailable ? (string) ($curl['ssl_version'] ?? 'unknown') : 'unavailable');
printf("Protocols: %s\n", $protocols === [] ? '(none)' : implode(', ', $protocols));
printf("upload_max_filesize: %s (%s bytes)\n", (string) ini_get('upload_max_filesize'), (string) (remote_env_ini_bytes('upload_max_filesize') ?? 0));
printf("post_max_size: %s (%s bytes)\n", (string) ini_get('post_max_size'), (string) (remote_env_ini_bytes('post_max_size') ?? 0));
printf("max_execution_time: %s\n", (string) ini_get('max_execution_time'));

$failedCore = false;
foreach ($checks as $label => $ok) {
    printf("[%s] %s\n", remote_env_bool($ok), $label);
    if (in_array($label, ['PHP >= 8.1', 'OpenSSL extension', 'Sodium extension', 'Remote credential key (base64 -> 32 bytes)', 'cURL extension'], true) && !$ok) {
        $failedCore = true;
    }
}

if (is_string($remoteTempDir) && trim($remoteTempDir) !== '') {
    $remoteTempDir = trim($remoteTempDir);
    printf("[%s] APP_REMOTE_TEMP_DIR exists and is writable\n", remote_env_bool(is_dir($remoteTempDir) && is_writable($remoteTempDir)));
} else {
    echo "[NG] APP_REMOTE_TEMP_DIR is not configured.\n";
    $failedCore = true;
}

if (is_string($knownHosts) && trim($knownHosts) !== '') {
    $knownHosts = trim($knownHosts);
    printf("[%s] APP_REMOTE_SSH_KNOWN_HOSTS_FILE is readable\n", remote_env_bool(is_file($knownHosts) && is_readable($knownHosts)));
} else {
    echo "[INFO] APP_REMOTE_SSH_KNOWN_HOSTS_FILE is not configured; required only when SFTP is used.\n";
}

if (!$has('sftp')) {
    echo "[WARN] SFTP cannot use the preferred libcurl provider on this PHP runtime.\n";
}
if (!$has('ftp')) {
    echo "[WARN] FTP/FTPS cannot use the libcurl provider on this PHP runtime.\n";
}
if (!$has('https') || !function_exists('simplexml_load_string')) {
    echo "[WARN] WebDAV listing is unavailable until HTTPS cURL + SimpleXML are both available.\n";
}

echo $failedCore ? "RESULT: CORE NG\n" : "RESULT: CORE OK (check protocol-specific warnings above)\n";
exit($failedCore ? 1 : 0);
