<?php

declare(strict_types=1);

// Force the secure policy even if the CI machine has no private deployment config.
define('APP_REMOTE_PLAINTEXT_FTP_ENABLED', false);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/api.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_bootstrap.php';

function ftp_policy_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

ftp_policy_check(APP_REMOTE_PLAINTEXT_FTP_ENABLED === false, 'test policy disabled');
$ftp = [
    'name' => 'Test FTP',
    'protocol' => 'ftp',
    'host' => 'ftp.example.com',
    'port' => 21,
    'username' => 'tester',
    'auth_type' => 'password',
    'password' => 'placeholder-password',
    'base_path' => '/',
];

try {
    remote_connection_validate_input($ftp, true);
    throw new RuntimeException('Plain FTP input was incorrectly accepted.');
} catch (AppRemoteValidationException $exception) {
    ftp_policy_check($exception->reason() === 'plaintext_ftp_disabled',
        'new FTP connections rejected before credentials or network');
}

$mapped = remote_api_validation_message('plaintext_ftp_disabled');
ftp_policy_check(str_contains($mapped, 'disabled by server policy'),
    'validation output explicitly states server policy');

$response = remote_api_failure('connection.test', 42, new AppRemoteTransportException('plaintext_ftp_disabled'));
ftp_policy_check($response['status'] === 403, 'existing FTP connection reports HTTP 403');
ftp_policy_check(($response['body']['error']['code'] ?? '') === 'remote_plaintext_ftp_disabled',
    'existing FTP connection reports stable typed error');
ftp_policy_check(!str_contains(json_encode($response, JSON_THROW_ON_ERROR), 'placeholder-password'),
    'FTP rejection never includes credential material');

echo "All current FTP server policy tests passed.\n";
