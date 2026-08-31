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
    if (in_array($label, ['PHP >= 8.1', 'OpenSSL extension', 'Sodium extension', 'cURL extension'], true) && !$ok) {
        $failedCore = true;
    }
}

$knownHosts = getenv('APP_REMOTE_SSH_KNOWN_HOSTS_FILE');
if (is_string($knownHosts) && trim($knownHosts) !== '') {
    $knownHosts = trim($knownHosts);
    printf("[%s] APP_REMOTE_SSH_KNOWN_HOSTS_FILE is readable\n", remote_env_bool(is_file($knownHosts) && is_readable($knownHosts)));
} else {
    echo "[INFO] APP_REMOTE_SSH_KNOWN_HOSTS_FILE is not visible in the process environment; if configured in config/local.php, verify it there.\n";
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
