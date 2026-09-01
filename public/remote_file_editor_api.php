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
        default => 'Remote text could not be opened for editing.',
    };
}

app_session_start();
app_send_private_no_store_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    remote_editor_emit(405, [
        'ok' => false,
        'error' => ['code' => 'method_not_allowed', 'message' => 'GET is required.'],
    ]);
}

$userId = app_session_user_id();
if ($userId === null) {
    remote_editor_emit(401, [
        'ok' => false,
        'error' => ['code' => 'unauthenticated', 'message' => 'Authentication is required.'],
    ]);
}

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
