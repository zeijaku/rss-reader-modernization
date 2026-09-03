<?php

declare(strict_types=1);

function remote_api_validation_message(string $reason): string
{
    return match ($reason) {
        'invalid_name' => 'Connection name is invalid.',
        'invalid_protocol' => 'Protocol is invalid.',
        'invalid_host' => 'Remote host is invalid.',
        'port_not_allowed' => 'Remote port is not allowed by server policy.',
        'invalid_username' => 'Remote username is invalid.',
        'invalid_auth_type' => 'Authentication type is invalid for this protocol.',
        'credential_required' => 'Credential is required.',
        'invalid_credential' => 'Credential is invalid.',
        'invalid_base_path' => 'Base path is invalid.',
        'invalid_boolean' => 'Boolean option is invalid.',
        'invalid_path' => 'Remote path is invalid.',
        'invalid_filename' => 'Remote file name is invalid.',
        'invalid_transfer' => 'Transfer request is invalid.',
        'invalid_mode' => 'Permission mode must be exactly three octal digits (000-777).',
        'preview_not_supported' => 'Preview is not available for this file type.',
        default => 'Remote File Manager request is invalid.',
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_failure(string $operation, int $userId, Throwable $exception): array
{
    // Never log remote credentials or library/server exception messages here.
    error_log(sprintf('Remote File API failure operation=%s user_id=%d class=%s', $operation, $userId, $exception::class));

    if ($exception instanceof AppRemoteValidationException) {
        return api_validation_error(remote_api_validation_message($exception->reason()));
    }
    if ($exception instanceof AppRemoteCredentialException) {
        return api_error('remote_credential_unavailable', 'Remote credential encryption is unavailable.', 503);
    }
    if ($exception instanceof UserFileUploadException) {
        return api_error($exception->errorCode, $exception->getMessage(), $exception->httpStatus);
    }
    if ($exception instanceof PDOException) {
        return api_error('remote_connection_unavailable', 'Remote connection migration is required.', 503);
    }
    if ($exception instanceof AppRemoteTransportException) {
        return match ($exception->errorCode) {
            'not_found' => api_error('not_found', 'Remote connection was not found.', 404),
            'file_not_found' => api_error('not_found', 'File was not found.', 404),
            'connection_disabled' => api_error('remote_connection_disabled', 'Remote connection is disabled.', 409),
            'target_exists' => api_error('target_exists', 'A remote item already exists at the destination.', 409),
            'transfer_too_large' => api_error('transfer_too_large', 'Remote transfer exceeded the configured size limit.', 413),
            'dependency_unavailable' => api_error('remote_dependency_unavailable', 'Required remote protocol support is unavailable on this server.', 503),
            'known_hosts_unavailable' => api_error('remote_known_hosts_unavailable', 'SFTP known_hosts configuration is unavailable.', 503),
            'temp_unavailable' => api_error('remote_temp_unavailable', 'Private remote transfer workspace is unavailable.', 503),
            'chmod_unsupported' => api_error('remote_permission_unsupported', 'Remote permission changes are not supported by this connection.', 409),
            'chmod_denied' => api_error('remote_permission_denied', 'Remote server denied the permission change.', 403),
            'chmod_failed' => api_error('remote_permission_failed', 'Remote permission change failed.', 502),
            'dns_failed' => api_validation_error('Remote host could not be resolved.'),
            'private_address_not_allowed' => api_validation_error('Private remote address is not allowed by the configured policy.'),
            'address_not_allowed' => api_validation_error('Remote address is not allowed by the configured policy.'),
            'port_not_allowed' => api_validation_error('Remote port is not allowed by server policy.'),
            'redirect_not_allowed' => api_validation_error('WebDAV redirect target is not allowed.'),
            'timeout' => api_error('remote_timeout', 'Remote operation timed out.', 504),
            'invalid_response' => api_error('remote_invalid_response', 'Remote server returned an invalid response.', 502),
            default => api_error('remote_operation_failed', 'Remote operation failed.', 502),
        };
    }
    return api_error('remote_operation_failed', 'Remote operation failed.', 500);
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_connection_list(int $userId, array $input): array
{
    try {
        return api_success(['connections' => remote_connection_list($userId)]);
    } catch (Throwable $exception) {
        return remote_api_failure('connection.list', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_connection_create(int $userId, array $input): array
{
    try {
        $connection = remote_connection_create($userId, $input);
        return api_success(['connection' => $connection], 201);
    } catch (Throwable $exception) {
        return remote_api_failure('connection.create', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_connection_update(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    if ($connectionId === null) {
        return api_validation_error('remote_connection_id must be a positive integer.');
    }
    try {
        $connection = remote_connection_update($userId, $connectionId, $input);
        return $connection === null
            ? api_error('not_found', 'Remote connection was not found.', 404)
            : api_success(['connection' => $connection]);
    } catch (Throwable $exception) {
        return remote_api_failure('connection.update', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_connection_delete(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    if ($connectionId === null) {
        return api_validation_error('remote_connection_id must be a positive integer.');
    }
    try {
        return remote_connection_delete($userId, $connectionId)
            ? api_success(['remote_connection_id' => $connectionId])
            : api_error('not_found', 'Remote connection was not found.', 404);
    } catch (Throwable $exception) {
        return remote_api_failure('connection.delete', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_connection_test(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    if ($connectionId === null) {
        return api_validation_error('remote_connection_id must be a positive integer.');
    }
    try {
        $result = remote_service_test_connection($userId, $connectionId);
        return ($result['connected'] ?? false) === true
            ? api_success(['connected' => true])
            : remote_api_failure('connection.test', $userId, new AppRemoteTransportException((string) ($result['code'] ?? 'connection_failed')));
    } catch (Throwable $exception) {
        return remote_api_failure('connection.test', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_permission_capabilities(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    if ($connectionId === null) {
        return api_validation_error('remote_connection_id must be a positive integer.');
    }
    try {
        return api_success([
            'remote_connection_id' => $connectionId,
            'permission_capabilities' => remote_service_permission_capabilities($userId, $connectionId),
        ]);
    } catch (Throwable $exception) {
        return remote_api_failure('permission.capabilities', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_file_list(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    $path = api_string($input, 'path') ?? '/';
    if ($connectionId === null) {
        return api_validation_error('remote_connection_id must be a positive integer.');
    }
    try {
        $canonical = remote_path_normalize_relative($path);
        if ($canonical === null) {
            throw new AppRemoteValidationException('invalid_path');
        }
        return api_success([
            'path' => $canonical,
            'parent_path' => remote_path_parent($canonical),
            'entries' => remote_service_list($userId, $connectionId, $canonical),
        ]);
    } catch (Throwable $exception) {
        return remote_api_failure('file.list', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_file_mkdir(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    $path = api_string($input, 'path');
    if ($connectionId === null || $path === null) {
        return api_validation_error('Connection and path are required.');
    }
    try {
        remote_service_mkdir($userId, $connectionId, $path);
        return api_success(['path' => remote_path_normalize_relative($path)]);
    } catch (Throwable $exception) {
        return remote_api_failure('file.mkdir', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_file_move(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    $from = api_string($input, 'from_path');
    $to = api_string($input, 'to_path');
    $overwrite = ($input['overwrite'] ?? '0') === '1';
    if ($connectionId === null || $from === null || $to === null) {
        return api_validation_error('Connection and paths are required.');
    }
    try {
        remote_service_move($userId, $connectionId, $from, $to, $overwrite);
        return api_success(['from_path' => remote_path_normalize_relative($from), 'to_path' => remote_path_normalize_relative($to)]);
    } catch (Throwable $exception) {
        return remote_api_failure('file.move', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_file_delete(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    $path = api_string($input, 'path');
    $directory = ($input['directory'] ?? '0') === '1';
    if ($connectionId === null || $path === null) {
        return api_validation_error('Connection and path are required.');
    }
    try {
        remote_service_delete($userId, $connectionId, $path, $directory);
        return api_success(['path' => remote_path_normalize_relative($path)]);
    } catch (Throwable $exception) {
        return remote_api_failure('file.delete', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_file_chmod(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    $path = api_string($input, 'path');
    $mode = api_string($input, 'mode');
    if ($connectionId === null || $path === null || $mode === null) {
        return api_validation_error('Connection, path and mode are required.');
    }
    try {
        remote_service_chmod($userId, $connectionId, $path, $mode);
        return api_success([
            'path' => remote_path_normalize_relative($path),
            'mode' => remote_permission_normalize_mode($mode),
        ]);
    } catch (Throwable $exception) {
        return remote_api_failure('file.chmod', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_file_import(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    $path = api_string($input, 'path');
    if ($connectionId === null || $path === null) {
        return api_validation_error('Connection and path are required.');
    }
    try {
        return api_success(['file' => remote_service_import_to_library($userId, $connectionId, $path)], 201);
    } catch (Throwable $exception) {
        return remote_api_failure('file.import', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_file_export(int $userId, array $input): array
{
    $connectionId = api_positive_int($input, 'remote_connection_id');
    $fileId = api_positive_int($input, 'file_id');
    $path = api_string($input, 'path');
    $overwrite = ($input['overwrite'] ?? '0') === '1';
    if ($connectionId === null || $fileId === null || $path === null) {
        return api_validation_error('Connection, File Library id and path are required.');
    }
    try {
        remote_service_export_library_file($userId, $connectionId, $fileId, $path, $overwrite);
        return api_success(['file_id' => $fileId, 'path' => remote_path_normalize_relative($path)]);
    } catch (Throwable $exception) {
        return remote_api_failure('file.export', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function remote_api_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'remote.connection.list' => remote_api_connection_list($userId, $input),
        'remote.connection.create' => remote_api_connection_create($userId, $input),
        'remote.connection.update' => remote_api_connection_update($userId, $input),
        'remote.connection.delete' => remote_api_connection_delete($userId, $input),
        'remote.connection.test' => remote_api_connection_test($userId, $input),
        'remote.permission.capabilities' => remote_api_permission_capabilities($userId, $input),
        'remote.file.list' => remote_api_file_list($userId, $input),
        'remote.file.mkdir' => remote_api_file_mkdir($userId, $input),
        'remote.file.move' => remote_api_file_move($userId, $input),
        'remote.file.delete' => remote_api_file_delete($userId, $input),
        'remote.file.chmod' => remote_api_file_chmod($userId, $input),
        'remote.file.import' => remote_api_file_import($userId, $input),
        'remote.file.export' => remote_api_file_export($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}
