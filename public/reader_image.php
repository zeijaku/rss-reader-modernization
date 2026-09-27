<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/reader/reader_image_proxy.php';

function reader_image_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Referrer-Policy: no-referrer');
    app_send_private_no_store_headers();
    echo $message;
    exit;
}

app_session_start();
app_send_private_no_store_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    reader_image_error(405, 'Method not allowed.');
}

$userId = app_session_user_id();
if ($userId === null) {
    reader_image_error(401, 'Authentication is required.');
}

$token = reader_image_proxy_valid_token($_GET['id'] ?? null);
if ($token === null) {
    reader_image_error(404, 'Image not found.');
}

// Release the PHP session lock before cache/dns/network I/O.
app_session_release();

try {
    $loaded = ReaderImageProxyService::fromRuntimeConfiguration()->load($userId, $token);
} catch (Throwable $exception) {
    error_log(sprintf(
        'Reader Image proxy failed user_id=%d class=%s',
        $userId,
        $exception::class
    ));
    reader_image_error(502, 'Image unavailable.');
}

if (($loaded['ok'] ?? false) !== true) {
    $code = is_string($loaded['error_code'] ?? null) ? (string) $loaded['error_code'] : 'transport_error';
    $status = in_array($code, ['invalid_token', 'not_found', 'proxy_disabled'], true) ? 404 : 502;
    error_log(sprintf(
        'Reader Image proxy unavailable user_id=%d code=%s upstream_status=%d',
        $userId,
        preg_match('/\A[a-z0-9_]{1,64}\z/D', $code) === 1 ? $code : 'transport_error',
        max(0, min(599, (int) ($loaded['status'] ?? 0)))
    ));
    reader_image_error($status, 'Image unavailable.');
}

$contentType = reader_image_proxy_content_type($loaded['content_type'] ?? null);
$body = is_string($loaded['body'] ?? null) ? (string) $loaded['body'] : '';
if ($contentType === null || $body === '' || !reader_image_proxy_body_matches_type($body, $contentType)) {
    reader_image_error(502, 'Image unavailable.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $contentType);
header('Content-Length: ' . strlen($body));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Referrer-Policy: no-referrer');
app_send_private_no_store_headers();
echo $body;
exit;
