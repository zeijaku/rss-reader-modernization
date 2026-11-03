<?php

declare(strict_types=1);

// Run without a real FTP server or cURL extension; exercise the public
// diagnostics mapping and verify that secrets never reach API responses.
require_once dirname(__DIR__) . '/app/remote_file/remote_exception.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_curl_transport.php';

function api_error(string $code, string $message, int $status): array
{
    return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]];
}

require_once dirname(__DIR__) . '/app/remote_file/remote_api.php';

function diagnostic_check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

$cases = [
    6 => 'hostname',
    7 => 'outbound network',
    9 => 'denied access',
    10 => 'FTP data connection',
    13 => 'FTP data connection',
    19 => 'FTP server',
    35 => 'TLS handshake',
    60 => 'TLS certificate',
    64 => 'FTPS TLS',
    67 => 'login was rejected',
    77 => 'CA certificates',
    95 => 'connection or transfer failed',
];
foreach ($cases as $errno => $expectedText) {
    $code = remote_curl_diagnostic_code($errno);
    diagnostic_check($code === 'curl_' . $errno, "cURL {$errno} survives transport");
    $response = remote_api_failure('connection.test', 42, new AppRemoteTransportException($code));
    diagnostic_check($response['status'] === 502, "cURL {$errno} maps to HTTP 502");
    diagnostic_check(($response['body']['error']['code'] ?? '') === 'remote_curl_' . $errno, "cURL {$errno} has a stable API code");
    $message = (string) ($response['body']['error']['message'] ?? '');
    diagnostic_check(str_contains($message, $expectedText) && str_contains($message, 'cURL error ' . $errno),
        "cURL {$errno} displays a useful, numbered diagnosis");
}

diagnostic_check(remote_curl_diagnostic_code(28) === 'timeout', 'Existing timeout classification remains unchanged');
diagnostic_check(remote_curl_diagnostic_code(0) === 'transport_error', 'Unknown transport failure remains generic');
diagnostic_check(remote_curl_diagnostic_code(-1) === 'transport_error', 'Invalid negative cURL number remains generic');
diagnostic_check(remote_curl_diagnostic_code(1000) === 'transport_error', 'Out-of-range cURL number remains generic');

$secret = 'password-must-not-be-disclosed';
$response = remote_api_failure('connection.test', 42,
    new AppRemoteTransportException('curl_60', "ftp://user:{$secret}@private.example/secret"));
diagnostic_check(!str_contains(json_encode($response, JSON_THROW_ON_ERROR), $secret),
    'Server exception text and credentials do not reach the API');

foreach (['curl_0', 'curl_1000', 'curl_67-extra', 'curl_-1'] as $invalid) {
    $response = remote_api_failure('connection.test', 42, new AppRemoteTransportException($invalid));
    diagnostic_check(($response['body']['error']['code'] ?? '') === 'remote_operation_failed',
        "Malformed diagnostic identifier {$invalid} remains generic");
}
echo "All current Remote Files cURL diagnostics tests passed.\n";
