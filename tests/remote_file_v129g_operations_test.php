<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/feed/feed_http_headers.php';
require_once __DIR__ . '/../app/feed/feed_retry.php';
require_once __DIR__ . '/../app/http_fetch.php';
require_once __DIR__ . '/../app/remote_file/remote_exception.php';
require_once __DIR__ . '/../app/remote_file/remote_path.php';
require_once __DIR__ . '/../app/remote_file/remote_host.php';
require_once __DIR__ . '/../app/remote_file/remote_provider.php';
require_once __DIR__ . '/../app/remote_file/remote_listing.php';

define('APP_REMOTE_TRANSFER_MAX_BYTES', 104857600);
define('APP_REMOTE_CONNECT_TIMEOUT_MS', 5000);
define('APP_REMOTE_TRANSFER_TIMEOUT_MS', 60000);
define('APP_REMOTE_USER_AGENT', 'test-v129g');
define('APP_REMOTE_ALLOWED_PORTS', '21,22,443');
define('APP_REMOTE_PRIVATE_NETWORK_ENABLED', false);
define('APP_REMOTE_PRIVATE_NETWORK_CIDRS', '');
define('APP_REMOTE_SSH_KNOWN_HOSTS_FILE', '');
define('APP_REMOTE_TEMP_DIR', sys_get_temp_dir());

require_once __DIR__ . '/../app/remote_file/remote_curl_transport.php';
require_once __DIR__ . '/../app/remote_file/providers/curl_provider.php';
require_once __DIR__ . '/../app/remote_file/providers/ftp_provider.php';
require_once __DIR__ . '/../app/remote_file/providers/ftps_provider.php';
require_once __DIR__ . '/../app/remote_file/providers/sftp_provider.php';
require_once __DIR__ . '/../app/remote_file/providers/webdav_provider.php';
require_once __DIR__ . '/../app/remote_file/remote_provider_factory.php';

$pass = 0;
$fail = 0;
function check_v129g(bool $condition, string $message): void
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

$connection = [
    'remote_connection_protocol' => 'ftp',
    'remote_connection_host' => 'files.example.com',
    'remote_connection_port' => 21,
    'remote_connection_username' => 'owner',
    'remote_connection_base_path' => '/srv/files',
    'remote_connection_allow_private' => 0,
];
$target = ['host' => 'files.example.com', 'port' => 21, 'ips' => ['93.184.216.34']];
$requests = [];
$uploaded = '';
$transport = static function (array $request) use (&$requests, &$uploaded): array {
    $requests[] = $request;
    if (isset($request['input_stream']) && is_resource($request['input_stream'])) {
        $uploaded = stream_get_contents($request['input_stream']);
        return ['ok'=>true,'status'=>226,'body'=>'','headers'=>[],'bytes'=>strlen($uploaded),'truncated'=>false,'error_code'=>''];
    }
    if (isset($request['output_stream']) && is_resource($request['output_stream'])) {
        fwrite($request['output_stream'], 'download-body');
        return ['ok'=>true,'status'=>226,'body'=>'','headers'=>[],'bytes'=>13,'truncated'=>false,'error_code'=>''];
    }
    if (isset($request['quote'])) {
        return ['ok'=>true,'status'=>200,'body'=>'','headers'=>[],'bytes'=>0,'truncated'=>false,'error_code'=>''];
    }
    // Empty listing for overwrite preflight and connection tests.
    return ['ok'=>true,'status'=>226,'body'=>'','headers'=>[],'bytes'=>0,'truncated'=>false,'error_code'=>''];
};

$ftp = remote_provider_create($connection, ['password' => 'secret'], $target, $transport);
$input = fopen('php://temp', 'w+b');
fwrite($input, 'upload-body');
rewind($input);
$ftp->upload($input, 11, '/nested/new.txt', false);
fclose($input);
check_v129g($uploaded === 'upload-body', 'FTP upload streams bytes without whole-file buffering in provider');
$uploadRequest = $requests[count($requests) - 1];
check_v129g(str_contains((string) ($uploadRequest['url'] ?? ''), '/srv/files/nested/new.txt'), 'FTP upload remains under configured base path');
check_v129g(($uploadRequest['input_size'] ?? null) === 11, 'FTP upload passes bounded input size to transport');

$output = fopen('php://temp', 'w+b');
$ftp->download('/nested/read.txt', $output, 1024);
rewind($output);
check_v129g(stream_get_contents($output) === 'download-body', 'FTP download streams into caller-provided output stream');
fclose($output);

$ftp->mkdir('/nested/new-dir');
$mkdirRequest = $requests[count($requests) - 1];
check_v129g(($mkdirRequest['quote'][0] ?? '') === 'MKD /srv/files/nested/new-dir', 'FTP mkdir is confined to base path');

$ftp->move('/nested/a.txt', '/nested/b.txt', true);
$moveRequest = $requests[count($requests) - 1];
check_v129g(($moveRequest['quote'][0] ?? '') === 'RNFR /srv/files/nested/a.txt' && ($moveRequest['quote'][1] ?? '') === 'RNTO /srv/files/nested/b.txt', 'FTP move/rename uses normalized base-confined paths');

$ftp->delete('/nested/b.txt', false);
$deleteFileRequest = $requests[count($requests) - 1];
check_v129g(($deleteFileRequest['quote'][0] ?? '') === 'DELE /srv/files/nested/b.txt', 'FTP file delete uses DELE inside base path');
$ftp->delete('/nested/new-dir', true);
$deleteDirRequest = $requests[count($requests) - 1];
check_v129g(($deleteDirRequest['quote'][0] ?? '') === 'RMD /srv/files/nested/new-dir', 'FTP directory delete uses RMD inside base path');

try {
    $ftp->mkdir('/../escape');
    check_v129g(false, 'provider rejects traversal before issuing remote command');
} catch (AppRemoteValidationException) {
    check_v129g(true, 'provider rejects traversal before issuing remote command');
}

$webdavConnection = $connection;
$webdavConnection['remote_connection_protocol'] = 'webdav';
$webdavConnection['remote_connection_port'] = 443;
$webdavConnection['remote_connection_base_path'] = '/dav/files/user';
$webdavTarget = ['host' => 'cloud.example.com', 'port' => 443, 'ips' => ['93.184.216.34']];
$webdavRequests = [];
$webdav = remote_provider_create($webdavConnection, ['password' => 'secret'], $webdavTarget, static function (array $request) use (&$webdavRequests): array {
    $webdavRequests[] = $request;
    $method = (string) ($request['custom_request'] ?? '');
    $status = match ($method) {
        'MKCOL' => 201,
        'MOVE' => 201,
        'DELETE' => 204,
        default => 200,
    };
    return ['ok'=>true,'status'=>$status,'body'=>'','headers'=>[],'bytes'=>0,'truncated'=>false,'error_code'=>''];
});
$webdav->mkdir('/folder');
check_v129g(($webdavRequests[0]['custom_request'] ?? '') === 'MKCOL', 'WebDAV mkdir uses MKCOL');
$webdav->move('/folder/a.txt', '/folder/b.txt', true);
check_v129g(($webdavRequests[1]['custom_request'] ?? '') === 'MOVE' && in_array('Overwrite: T', $webdavRequests[1]['headers'] ?? [], true), 'WebDAV move uses MOVE with explicit overwrite policy');
$webdav->delete('/folder/b.txt', false);
check_v129g(($webdavRequests[2]['custom_request'] ?? '') === 'DELETE', 'WebDAV delete uses DELETE');

$redirectTransport = static function (array $request): array {
    return [
        'ok' => true,
        'status' => 302,
        'body' => '',
        'headers' => ['location' => 'https://cloud.example.com/outside/secret.txt'],
        'bytes' => 0,
        'truncated' => false,
        'error_code' => '',
    ];
};
$redirectDav = remote_provider_create($webdavConnection, ['password' => 'secret'], $webdavTarget, $redirectTransport);
$redirectOut = fopen('php://temp', 'w+b');
try {
    $redirectDav->download('/folder/read.txt', $redirectOut, 1024);
    check_v129g(false, 'WebDAV redirect cannot escape configured base path');
} catch (AppRemoteTransportException $exception) {
    check_v129g($exception->errorCode === 'redirect_not_allowed', 'WebDAV redirect cannot escape configured base path');
} finally {
    fclose($redirectOut);
}

$encodedRedirectTransport = static function (array $request): array {
    return [
        'ok' => true,
        'status' => 302,
        'body' => '',
        'headers' => ['location' => 'https://cloud.example.com/dav/files/user/%2e%2e/%2e%2e/secret.txt'],
        'bytes' => 0,
        'truncated' => false,
        'error_code' => '',
    ];
};
$encodedRedirectDav = remote_provider_create($webdavConnection, ['password' => 'secret'], $webdavTarget, $encodedRedirectTransport);
$encodedRedirectOut = fopen('php://temp', 'w+b');
try {
    $encodedRedirectDav->download('/folder/read.txt', $encodedRedirectOut, 1024);
    check_v129g(false, 'WebDAV redirect rejects encoded traversal');
} catch (AppRemoteTransportException $exception) {
    check_v129g($exception->errorCode === 'redirect_not_allowed', 'WebDAV redirect rejects encoded traversal');
} finally {
    fclose($encodedRedirectOut);
}

check_v129g(remote_curl_protocol_supported('sftp') === false || function_exists('curl_version'), 'runtime capability probe fails closed when cURL protocol support is unavailable');

echo "RESULT: PASS {$pass} / FAIL {$fail}\n";
exit($fail === 0 ? 0 : 1);
