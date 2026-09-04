<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/feed/feed_http_headers.php';
require_once __DIR__ . '/../app/feed/feed_retry.php';
require_once __DIR__ . '/../app/http_fetch.php';
require_once __DIR__ . '/../app/remote_file/remote_exception.php';
require_once __DIR__ . '/../app/remote_file/remote_path.php';
require_once __DIR__ . '/../app/remote_file/remote_host.php';
require_once __DIR__ . '/../app/remote_file/remote_provider.php';
require_once __DIR__ . '/../app/remote_file/remote_permission_provider.php';
require_once __DIR__ . '/../app/remote_file/remote_listing.php';

define('APP_REMOTE_TRANSFER_MAX_BYTES', 104857600);
define('APP_REMOTE_CONNECT_TIMEOUT_MS', 5000);
define('APP_REMOTE_TRANSFER_TIMEOUT_MS', 60000);
define('APP_REMOTE_USER_AGENT', 'test');
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
function check_v129c(bool $condition, string $message): void
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
    'remote_connection_username' => 'user',
    'remote_connection_base_path' => '/srv/files',
    'remote_connection_allow_private' => 0,
];
$target = ['host' => 'files.example.com', 'port' => 21, 'ips' => ['93.184.216.34']];
$requests = [];
$ftpTransport = static function (array $request) use (&$requests): array {
    $requests[] = $request;
    return [
        'ok' => true,
        'status' => 226,
        'body' => "type=dir;modify=20260830101010; docs\r\ntype=file;size=123;modify=20260831121212; sample.txt\r\n",
        'headers' => [], 'bytes' => 90, 'truncated' => false, 'error_code' => '',
    ];
};
$ftp = remote_provider_create($connection, ['password' => 'secret'], $target, $ftpTransport);
$entries = $ftp->list('/');
check_v129c(count($entries) === 2, 'FTP MLSD listing is parsed');
check_v129c($entries[0]['type'] === 'directory' && $entries[0]['path'] === '/docs', 'FTP directory receives safe relative path');
check_v129c($entries[1]['size'] === 123 && $entries[1]['path'] === '/sample.txt', 'FTP file size and path are returned');
check_v129c(str_starts_with((string) $requests[0]['url'], 'ftp://files.example.com:21/srv/files/'), 'FTP provider builds encoded URL under connection base path');
check_v129c(($requests[0]['protocol'] ?? '') === 'ftp' && ($requests[0]['ip'] ?? '') === '93.184.216.34', 'provider sends validated pinned IP to transport');

$ftpsConnection = $connection;
$ftpsConnection['remote_connection_protocol'] = 'ftps';
$ftps = remote_provider_create($ftpsConnection, ['password' => 'secret'], $target, static function (array $request): array {
    check_v129c(($request['protocol'] ?? '') === 'ftps', 'FTPS provider shares FTP behavior but selects TLS transport');
    return ['ok' => true, 'status' => 226, 'body' => '', 'headers' => [], 'bytes' => 0, 'truncated' => false, 'error_code' => ''];
});
$ftps->list('/');

$sftpConnection = $connection;
$sftpConnection['remote_connection_protocol'] = 'sftp';
$sftpConnection['remote_connection_port'] = 22;
$sftpConnection['remote_connection_base_path'] = '/var/www';
$sftpTarget = ['host' => 'files.example.com', 'port' => 22, 'ips' => ['93.184.216.34']];
$sftpRequests = [];
$sftp = remote_provider_create($sftpConnection, ['private_key' => 'KEY', 'passphrase' => 'pw'], $sftpTarget, static function (array $request) use (&$sftpRequests): array {
    $sftpRequests[] = $request;
    if (isset($request['quote'])) {
        return ['ok' => true, 'status' => 0, 'body' => '', 'headers' => [], 'bytes' => 0, 'truncated' => false, 'error_code' => ''];
    }
    return [
        'ok' => true, 'status' => 0,
        'body' => "drwxr-xr-x 2 user group 4096 Aug 31 12:00 app\n-rw-r--r-- 1 user group 456 Aug 31 12:01 index.php\n",
        'headers' => [], 'bytes' => 100, 'truncated' => false, 'error_code' => '',
    ];
});
$sftpEntries = $sftp->list('/');
check_v129c(count($sftpEntries) === 2 && $sftpEntries[0]['type'] === 'directory', 'SFTP Unix listing is parsed without shell execution');
$sftp->mkdir('/new-dir');
check_v129c(($sftpRequests[1]['quote'][0] ?? '') === 'mkdir "/var/www/new-dir"', 'SFTP mkdir uses libcurl SFTP quote command under base path');
$sftp->move('/index.php', '/renamed.php', true);
check_v129c(($sftpRequests[2]['quote'][0] ?? '') === 'rename "/var/www/index.php" "/var/www/renamed.php"', 'SFTP rename stays within normalized base path');

$webdavConnection = $connection;
$webdavConnection['remote_connection_protocol'] = 'webdav';
$webdavConnection['remote_connection_port'] = 443;
$webdavConnection['remote_connection_base_path'] = '/remote.php/dav/files/user';
$webdavTarget = ['host' => 'cloud.example.com', 'port' => 443, 'ips' => ['93.184.216.34']];
$xml = <<<'XML'
<?xml version="1.0"?>
<d:multistatus xmlns:d="DAV:">
 <d:response><d:href>/remote.php/dav/files/user/</d:href><d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop></d:propstat></d:response>
 <d:response><d:href>/remote.php/dav/files/user/docs/</d:href><d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype><d:getlastmodified>Mon, 31 Aug 2026 10:00:00 GMT</d:getlastmodified></d:prop></d:propstat></d:response>
 <d:response><d:href>/remote.php/dav/files/user/data.csv</d:href><d:propstat><d:prop><d:resourcetype/><d:getcontentlength>789</d:getcontentlength><d:getlastmodified>Mon, 31 Aug 2026 11:00:00 GMT</d:getlastmodified></d:prop></d:propstat></d:response>
</d:multistatus>
XML;
$webdavRequests = [];
$webdav = remote_provider_create($webdavConnection, ['password' => 'app-password'], $webdavTarget, static function (array $request) use (&$webdavRequests, $xml): array {
    $webdavRequests[] = $request;
    return ['ok' => true, 'status' => 207, 'body' => $xml, 'headers' => [], 'bytes' => strlen($xml), 'truncated' => false, 'error_code' => ''];
});
if (function_exists('simplexml_load_string')) {
    $webdavEntries = $webdav->list('/');
    check_v129c(count($webdavEntries) === 2, 'WebDAV PROPFIND skips current collection and returns children');
    check_v129c($webdavEntries[0]['path'] === '/docs' && $webdavEntries[0]['type'] === 'directory', 'WebDAV collection becomes safe relative directory');
    check_v129c($webdavEntries[1]['size'] === 789 && $webdavEntries[1]['path'] === '/data.csv', 'WebDAV content length is parsed');
    check_v129c(($webdavRequests[0]['custom_request'] ?? '') === 'PROPFIND', 'WebDAV listing uses PROPFIND');
    check_v129c(in_array('Depth: 1', $webdavRequests[0]['headers'] ?? [], true), 'WebDAV PROPFIND is bounded to Depth 1');

    $maliciousXml = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><d:multistatus xmlns:d="DAV:"><d:response><d:href>&e;</d:href></d:response></d:multistatus>';
    $webdavBad = remote_provider_create($webdavConnection, ['password' => 'x'], $webdavTarget, static fn(array $request): array => ['ok'=>true,'status'=>207,'body'=>$maliciousXml,'headers'=>[],'bytes'=>strlen($maliciousXml),'truncated'=>false,'error_code'=>'']);
    try {
        $badEntries = $webdavBad->list('/');
        check_v129c($badEntries === [], 'WebDAV parser does not expand external entities');
    } catch (AppRemoteTransportException) {
        check_v129c(true, 'WebDAV parser fails closed on malicious XML');
    }
} else {
    try {
        $webdav->list('/');
        check_v129c(false, 'WebDAV fails closed when SimpleXML is unavailable');
    } catch (AppRemoteTransportException $exception) {
        check_v129c($exception->errorCode === 'invalid_response', 'WebDAV fails closed when SimpleXML is unavailable');
    }
}

echo "RESULT: PASS {$pass} / FAIL {$fail}\n";
exit($fail === 0 ? 0 : 1);
