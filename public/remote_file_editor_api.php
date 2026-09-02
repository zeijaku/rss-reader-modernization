<?php

declare(strict_types=1);

define('APP_RESPONSE_FORMAT', 'json');

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/api.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_bootstrap.php';

/** @param array<string,mixed> $body */
function remote_editor_emit(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    if (app_session_is_authenticated()) {
        $token = app_csrf_current_token();
        if ($token !== null) {
            header('X-CSRF-Token: ' . $token);
        }
    }
    app_send_no_store_headers();
    echo json_encode(
        $body,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    exit;
}

function remote_editor_public_message(string $code): string
{
    return match ($code) {
        'editor_type_unsupported' => 'This remote file type is not editable.',
        'editor_too_large' => 'This remote file exceeds the editor size limit.',
        'editor_binary_unsupported' => 'This remote file is not supported as editable text.',
        'editor_encoding_unsupported' => 'Only UTF-8 remote text files can be edited.',
        'editor_line_endings_unsupported' => 'This remote file uses unsupported or mixed line endings.',
        'editor_state_invalid' => 'Editor state is invalid. Reload the remote file before saving.',
        'editor_conflict' => 'The remote file changed after it was opened. Reload before saving.',
        'editor_stage_unavailable' => 'Remote save staging is temporarily unavailable.',
        'editor_save_verification_failed' => 'The remote save could not be verified.',
        'editor_request_too_large' => 'The editor save request is too large.',
        default => 'Remote text operation could not be completed.',
    };
}

function remote_editor_base64_max_bytes(): int
{
    return 4 * intdiv(APP_REMOTE_EDITOR_MAX_BYTES + 2, 3);
}

function remote_editor_request_max_bytes(): int
{
    // Base64 is bounded to about 4/3 of decoded editor text. Keep a small
    // fixed allowance for JSON metadata, path, CSRF and optimistic SHA state.
    return remote_editor_base64_max_bytes() + 65536;
}

function remote_editor_content_length(): ?int
{
    $raw = $_SERVER['CONTENT_LENGTH'] ?? null;
    if (!is_string($raw) && !is_int($raw)) {
        return null;
    }
    $raw = trim((string) $raw);
    if ($raw === '' || preg_match('/\A\d{1,20}\z/D', $raw) !== 1) {
        return null;
    }
    return (int) $raw;
}

function remote_editor_read_request_body(): string
{
    $stream = @fopen('php://input', 'rb');
    if (!is_resource($stream)) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    try {
        $raw = stream_get_contents($stream, remote_editor_request_max_bytes() + 1);
    } finally {
        fclose($stream);
    }
    if (!is_string($raw)) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    if (strlen($raw) > remote_editor_request_max_bytes()) {
        throw new AppRemoteEditorException('editor_request_too_large', 413);
    }
    return $raw;
}

/** @return array<string,mixed> */
function remote_editor_decode_save_request(string $raw): array
{
    try {
        $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    if (!is_array($input)) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    return $input;
}

/** @param array<string,mixed> $input */
function remote_editor_decode_save_text(array $input): string
{
    $encoded = $input['text_base64'] ?? null;
    if (!is_string($encoded)) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    if (strlen($encoded) > remote_editor_base64_max_bytes()) {
        throw new AppRemoteEditorException('editor_too_large', 413);
    }

    $decoded = base64_decode($encoded, true);
    if (!is_string($decoded) || base64_encode($decoded) !== $encoded) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    if (strlen($decoded) > APP_REMOTE_EDITOR_MAX_BYTES) {
        throw new AppRemoteEditorException('editor_too_large', 413);
    }
    return $decoded;
}

app_session_start();
app_send_private_no_store_headers();

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    remote_editor_emit(405, [
        'ok' => false,
        'error' => ['code' => 'method_not_allowed', 'message' => 'GET or POST is required.'],
    ]);
}

$userId = app_session_user_id();
if ($userId === null) {
    remote_editor_emit(401, [
        'ok' => false,
        'error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.'],
    ]);
}

if ($method === 'GET') {
    $connectionId = app_validate_positive_int($_GET['remote_connection_id'] ?? null);
    $path = remote_path_normalize_relative($_GET['path'] ?? null);
    if ($connectionId === null || $path === null || $path === '/') {
        remote_editor_emit(404, [
            'ok' => false,
            'error' => ['code' => 'not_found', 'message' => 'File not found.'],
        ]);
    }

    app_session_release();
    try {
        $data = remote_editor_read($userId, $connectionId, $path);
        remote_editor_emit(200, ['ok' => true, 'data' => $data]);
    } catch (AppRemoteEditorException $exception) {
        remote_editor_emit($exception->httpStatus, [
            'ok' => false,
            'error' => [
                'code' => $exception->errorCode,
                'message' => remote_editor_public_message($exception->errorCode),
            ],
        ]);
    } catch (Throwable $exception) {
        $response = remote_api_failure('editor.read', $userId, $exception);
        remote_editor_emit($response['status'], $response['body']);
    }
}

$contentTypeRaw = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
$contentType = trim(explode(';', $contentTypeRaw, 2)[0]);
if ($contentType !== 'application/json') {
    remote_editor_emit(415, [
        'ok' => false,
        'error' => ['code' => 'unsupported_media_type', 'message' => 'application/json is required.'],
    ]);
}

$contentLength = remote_editor_content_length();
if ($contentLength !== null && $contentLength > remote_editor_request_max_bytes()) {
    remote_editor_emit(413, [
        'ok' => false,
        'error' => ['code' => 'editor_request_too_large', 'message' => remote_editor_public_message('editor_request_too_large')],
    ]);
}

try {
    $input = remote_editor_decode_save_request(remote_editor_read_request_body());
    $csrf = isset($input['csrf_token']) && is_string($input['csrf_token']) ? $input['csrf_token'] : null;
    if (!app_csrf_is_valid($csrf)) {
        remote_editor_emit(403, [
            'ok' => false,
            'error' => ['code' => 'csrf_invalid', 'message' => 'CSRF validation failed.'],
        ]);
    }

    $connectionId = app_validate_positive_int($input['remote_connection_id'] ?? null);
    $path = remote_path_normalize_relative($input['path'] ?? null);
    $expectedSha256 = $input['expected_sha256'] ?? null;
    if ($connectionId === null || $path === null || $path === '/' || !is_string($expectedSha256)) {
        throw new AppRemoteEditorException('editor_state_invalid', 422);
    }
    $text = remote_editor_decode_save_text($input);

    app_session_release();
    $data = remote_editor_save($userId, $connectionId, $path, $text, $expectedSha256);
    remote_editor_emit(200, ['ok' => true, 'data' => $data]);
} catch (AppRemoteEditorException $exception) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        app_session_release();
    }
    remote_editor_emit($exception->httpStatus, [
        'ok' => false,
        'error' => [
            'code' => $exception->errorCode,
            'message' => remote_editor_public_message($exception->errorCode),
        ],
    ]);
} catch (Throwable $exception) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        app_session_release();
    }
    $response = remote_api_failure('editor.save', $userId, $exception);
    remote_editor_emit($response['status'], $response['body']);
}
