<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/feed/feed_http_headers.php';
require_once __DIR__ . '/../app/feed/feed_retry.php';
require_once __DIR__ . '/../app/http_fetch.php';
require_once __DIR__ . '/../app/remote_file/remote_exception.php';
require_once __DIR__ . '/../app/remote_file/remote_path.php';

define('APP_REMOTE_ALLOWED_PORTS', '21,22,443,2222');
define('APP_REMOTE_PRIVATE_NETWORK_ENABLED', true);
define('APP_REMOTE_PRIVATE_NETWORK_CIDRS', '192.168.10.0/24,fd12:3456::/48');
define('APP_REMOTE_CREDENTIAL_KEY_ID', 'test');
define('APP_REMOTE_CREDENTIAL_KEY_B64', base64_encode(str_repeat("K", 32)));

require_once __DIR__ . '/../app/remote_file/remote_host.php';
require_once __DIR__ . '/../app/remote_file/remote_crypto.php';
require_once __DIR__ . '/../app/remote_file/remote_connection.php';

$pass = 0;
$fail = 0;
function check_v129b(bool $condition, string $message): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$message}\n";
    } else {
        $fail++;
        echo "FAIL: {$message}\n";
    }
}

check_v129b(remote_path_normalize_base('/var/www/html/') === '/var/www/html', 'base path normalizes without escaping root');
check_v129b(remote_path_normalize_relative('/docs/file.txt') === '/docs/file.txt', 'relative browser path is canonical');
check_v129b(remote_path_normalize_relative('/docs/../secret') === null, 'dot-dot path is rejected');
check_v129b(remote_path_normalize_relative('/docs/./file') === null, 'dot path segment is rejected');
check_v129b(remote_path_normalize_relative("/docs/a\0b") === null, 'null byte path is rejected');
check_v129b(remote_path_normalize_relative('/docs\\secret') === null, 'backslash path is rejected');
check_v129b(remote_path_join('/srv/files', '/docs/a.txt') === '/srv/files/docs/a.txt', 'relative path joins under base path');
check_v129b(remote_path_parent('/docs/a.txt') === '/docs', 'parent stays in relative namespace');
check_v129b(remote_path_child('/docs', '../x') === null, 'child name cannot traverse');

$publicResolver = static fn(string $host): array => ['93.184.216.34'];
$target = remote_validate_target('sftp', 'example.com', 22, false, $publicResolver);
check_v129b($target['ok'] === true && $target['ips'] === ['93.184.216.34'], 'public SFTP target is accepted and resolved');

$privateResolver = static fn(string $host): array => ['192.168.10.20'];
$target = remote_validate_target('sftp', 'nas.example', 22, false, $privateResolver);
check_v129b($target['ok'] === false && $target['error_code'] === 'private_address_not_allowed', 'private target requires connection opt-in');
$target = remote_validate_target('sftp', 'nas.example', 22, true, $privateResolver);
check_v129b($target['ok'] === true, 'admin-allowlisted private target works with connection opt-in');

$wrongPrivate = static fn(string $host): array => ['192.168.20.20'];
$target = remote_validate_target('sftp', 'nas.example', 22, true, $wrongPrivate);
check_v129b($target['ok'] === false, 'private target outside administrator CIDR remains denied');

$loopback = static fn(string $host): array => ['127.0.0.1'];
$target = remote_validate_target('sftp', 'loop.example', 22, true, $loopback);
check_v129b($target['ok'] === false && $target['error_code'] === 'address_not_allowed', 'loopback remains denied even with private access enabled');

$mixed = static fn(string $host): array => ['93.184.216.34', '127.0.0.1'];
$target = remote_validate_target('webdav', 'mixed.example', 443, false, $mixed);
check_v129b($target['ok'] === false, 'mixed public and blocked DNS answers fail closed');

$target = remote_validate_target('sftp', 'example.com', 12345, false, $publicResolver);
check_v129b($target['ok'] === false && $target['error_code'] === 'port_not_allowed', 'unapproved arbitrary port is rejected');

$envelope = remote_crypto_encrypt(7, 9, ['password' => 'secret-value']);
$credentials = remote_crypto_decrypt(7, 9, $envelope);
check_v129b(($credentials['password'] ?? '') === 'secret-value', 'credential AEAD round trip succeeds');
try {
    remote_crypto_decrypt(8, 9, $envelope);
    check_v129b(false, 'AAD owner mismatch is rejected');
} catch (AppRemoteCredentialException) {
    check_v129b(true, 'AAD owner mismatch is rejected');
}
try {
    remote_crypto_decrypt(7, 10, $envelope);
    check_v129b(false, 'AAD connection mismatch is rejected');
} catch (AppRemoteCredentialException) {
    check_v129b(true, 'AAD connection mismatch is rejected');
}

$data = remote_connection_validate_input([
    'name' => 'Production SFTP',
    'protocol' => 'sftp',
    'host' => 'files.example.com',
    'port' => '22',
    'username' => 'deploy',
    'auth_type' => 'private_key',
    'private_key' => "-----BEGIN OPENSSH " . "PRIVATE KEY-----\nTEST\n-----END OPENSSH " . "PRIVATE KEY-----",
    'passphrase' => 'pass',
    'base_path' => '/srv/app',
    'allow_private' => '0',
    'enabled' => '1',
], true);
check_v129b($data['protocol'] === 'sftp' && $data['auth_type'] === 'private_key', 'SFTP private-key credential shape validates');

try {
    remote_connection_validate_input([
        'name' => 'FTP', 'protocol' => 'ftp', 'host' => 'ftp.example.com', 'port' => '21',
        'username' => 'u', 'auth_type' => 'private_key', 'private_key' => 'x', 'base_path' => '/',
    ], true);
    check_v129b(false, 'FTP cannot select private-key authentication');
} catch (AppRemoteValidationException) {
    check_v129b(true, 'FTP cannot select private-key authentication');
}

echo "RESULT: PASS {$pass} / FAIL {$fail}\n";
exit($fail === 0 ? 0 : 1);
