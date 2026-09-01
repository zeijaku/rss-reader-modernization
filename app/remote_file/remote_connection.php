<?php

declare(strict_types=1);

function remote_connection_table_name(): string
{
    return (string) DB_TABLE_PREFIX . 'remote_connection';
}

function remote_connection_table_identifier(): string
{
    return '`' . remote_connection_table_name() . '`';
}

function remote_connection_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function remote_connection_validate_name(mixed $value): string
{
    $name = is_string($value) ? trim($value) : '';
    if ($name === '' || !remote_path_is_utf8($name) || remote_path_has_control_characters($name) || remote_connection_text_length($name) > 128) {
        throw new AppRemoteValidationException('invalid_name');
    }
    return $name;
}

function remote_connection_validate_username(mixed $value): string
{
    $username = is_string($value) ? trim($value) : '';
    if ($username === '' || !remote_path_is_utf8($username) || remote_path_has_control_characters($username) || remote_connection_text_length($username) > 320) {
        throw new AppRemoteValidationException('invalid_username');
    }
    return $username;
}

function remote_connection_validate_bool(mixed $value, bool $default): int
{
    if ($value === null || $value === '') {
        return $default ? 1 : 0;
    }
    if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
        return 1;
    }
    if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
        return 0;
    }
    throw new AppRemoteValidationException('invalid_boolean');
}

/** @return array{auth_type:string,credentials:?array<string,string>} */
function remote_connection_validate_credentials(string $protocol, mixed $authTypeValue, array $input, bool $required): array
{
    $authType = is_string($authTypeValue) ? strtolower(trim($authTypeValue)) : '';
    $allowed = $protocol === 'sftp' ? ['password', 'private_key'] : ['password'];
    if (!in_array($authType, $allowed, true)) {
        throw new AppRemoteValidationException('invalid_auth_type');
    }

    $credentials = [];
    $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
    $privateKey = isset($input['private_key']) && is_string($input['private_key']) ? $input['private_key'] : '';
    $passphrase = isset($input['passphrase']) && is_string($input['passphrase']) ? $input['passphrase'] : '';

    if ($authType === 'password') {
        if ($password !== '') {
            $credentials['password'] = $password;
        } elseif ($required) {
            throw new AppRemoteValidationException('credential_required');
        }
    } else {
        if ($privateKey !== '') {
            $credentials['private_key'] = $privateKey;
            if ($passphrase !== '') {
                $credentials['passphrase'] = $passphrase;
            }
        } elseif ($required) {
            throw new AppRemoteValidationException('credential_required');
        }
    }

    if ($credentials !== []) {
        try {
            $credentials = remote_crypto_validate_credentials($credentials);
        } catch (AppRemoteCredentialException) {
            throw new AppRemoteValidationException('invalid_credential');
        }
    }
    return ['auth_type' => $authType, 'credentials' => $credentials === [] ? null : $credentials];
}

/** @return array<string,mixed> */
function remote_connection_validate_input(array $input, bool $credentialRequired): array
{
    $protocol = remote_protocol($input['protocol'] ?? null);
    if ($protocol === null) {
        throw new AppRemoteValidationException('invalid_protocol');
    }
    $host = remote_normalize_host($input['host'] ?? null);
    if ($host === null) {
        throw new AppRemoteValidationException('invalid_host');
    }
    $port = filter_var($input['port'] ?? remote_protocol_default_port($protocol), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if (!is_int($port) || !in_array($port, remote_allowed_ports(), true)) {
        throw new AppRemoteValidationException('port_not_allowed');
    }
    if ($protocol === 'webdav' && $port !== 443 && !in_array($port, remote_allowed_ports(), true)) {
        throw new AppRemoteValidationException('port_not_allowed');
    }

    $basePath = remote_path_normalize_base(is_string($input['base_path'] ?? null) ? $input['base_path'] : '/');
    if ($basePath === null) {
        throw new AppRemoteValidationException('invalid_base_path');
    }
    $auth = remote_connection_validate_credentials($protocol, $input['auth_type'] ?? 'password', $input, $credentialRequired);

    return [
        'name' => remote_connection_validate_name($input['name'] ?? null),
        'protocol' => $protocol,
        'host' => $host,
        'port' => $port,
        'username' => remote_connection_validate_username($input['username'] ?? null),
        'auth_type' => $auth['auth_type'],
        'credentials' => $auth['credentials'],
        'base_path' => $basePath,
        'allow_private' => remote_connection_validate_bool($input['allow_private'] ?? null, false),
        'enabled' => remote_connection_validate_bool($input['enabled'] ?? null, true),
    ];
}

/** @return array<string,mixed> */
function remote_connection_safe_row(array $row): array
{
    return [
        'remote_connection_id' => (int) ($row['remote_connection_id'] ?? 0),
        'name' => (string) ($row['remote_connection_name'] ?? ''),
        'protocol' => (string) ($row['remote_connection_protocol'] ?? ''),
        'host' => (string) ($row['remote_connection_host'] ?? ''),
        'port' => (int) ($row['remote_connection_port'] ?? 0),
        'username' => (string) ($row['remote_connection_username'] ?? ''),
        'auth_type' => (string) ($row['remote_connection_auth_type'] ?? ''),
        'base_path' => (string) ($row['remote_connection_base_path'] ?? '/'),
        'allow_private' => (int) ($row['remote_connection_allow_private'] ?? 0) === 1,
        'enabled' => (int) ($row['remote_connection_enabled'] ?? 0) === 1,
        'created_at' => (string) ($row['remote_connection_created_at'] ?? ''),
        'updated_at' => (string) ($row['remote_connection_updated_at'] ?? ''),
    ];
}

/** @return array<string,mixed>|null */
function remote_connection_find_owned(int $ownerId, int $connectionId, bool $includeSecret = false): ?array
{
    if ($ownerId <= 0 || $connectionId <= 0) {
        return null;
    }
    $columns = $includeSecret ? '*' : implode(', ', [
        'remote_connection_id', 'remote_connection_owner', 'remote_connection_name', 'remote_connection_protocol',
        'remote_connection_host', 'remote_connection_port', 'remote_connection_username', 'remote_connection_auth_type',
        'remote_connection_base_path', 'remote_connection_allow_private', 'remote_connection_enabled',
        'remote_connection_flag', 'remote_connection_created_at', 'remote_connection_updated_at',
    ]);
    $stmt = conn_db()->prepare(
        'SELECT ' . $columns . ' FROM ' . remote_connection_table_name() . ' '
        . 'WHERE remote_connection_id = :id AND remote_connection_owner = :owner AND remote_connection_flag = 0 LIMIT 1'
    );
    $stmt->execute([':id' => $connectionId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return list<array<string,mixed>> */
function remote_connection_list(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }
    $stmt = conn_db()->prepare(
        'SELECT remote_connection_id, remote_connection_owner, remote_connection_name, remote_connection_protocol, '
        . 'remote_connection_host, remote_connection_port, remote_connection_username, remote_connection_auth_type, '
        . 'remote_connection_base_path, remote_connection_allow_private, remote_connection_enabled, '
        . 'remote_connection_flag, remote_connection_created_at, remote_connection_updated_at '
        . 'FROM ' . remote_connection_table_name() . ' '
        . 'WHERE remote_connection_owner = :owner AND remote_connection_flag = 0 ORDER BY remote_connection_id ASC'
    );
    $stmt->execute([':owner' => $ownerId]);
    $rows = $stmt->fetchAll();
    return array_values(array_map('remote_connection_safe_row', is_array($rows) ? $rows : []));
}

/** @return array<string,mixed> */
function remote_connection_create(int $ownerId, array $input): array
{
    if ($ownerId <= 0) {
        throw new AppRemoteValidationException('invalid_owner');
    }
    $data = remote_connection_validate_input($input, true);
    $conn = conn_db();
    try {
        $conn->beginTransaction();
        $now = app_now();
        $stmt = $conn->prepare(
            'INSERT INTO ' . remote_connection_table_name() . ' ('
            . 'remote_connection_owner, remote_connection_name, remote_connection_protocol, remote_connection_host, '
            . 'remote_connection_port, remote_connection_username, remote_connection_auth_type, remote_connection_secret, '
            . 'remote_connection_base_path, remote_connection_allow_private, remote_connection_enabled, remote_connection_flag, '
            . 'remote_connection_created_at, remote_connection_updated_at'
            . ') VALUES (:owner, :name, :protocol, :host, :port, :username, :auth_type, :secret, :base_path, '
            . ':allow_private, :enabled, 0, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':owner' => $ownerId, ':name' => $data['name'], ':protocol' => $data['protocol'], ':host' => $data['host'],
            ':port' => $data['port'], ':username' => $data['username'], ':auth_type' => $data['auth_type'], ':secret' => '',
            ':base_path' => $data['base_path'], ':allow_private' => $data['allow_private'], ':enabled' => $data['enabled'],
            ':created_at' => $now, ':updated_at' => $now,
        ]);
        $connectionId = (int) $conn->lastInsertId();
        if ($connectionId <= 0 || !is_array($data['credentials'])) {
            throw new RuntimeException('Remote connection insert did not return an ID.');
        }
        $secret = remote_crypto_encrypt($ownerId, $connectionId, $data['credentials']);
        $stmt = $conn->prepare(
            'UPDATE ' . remote_connection_table_name() . ' SET remote_connection_secret = :secret '
            . 'WHERE remote_connection_id = :id AND remote_connection_owner = :owner AND remote_connection_flag = 0'
        );
        $stmt->execute([':secret' => $secret, ':id' => $connectionId, ':owner' => $ownerId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Remote credential update failed.');
        }
        $conn->commit();
        $row = remote_connection_find_owned($ownerId, $connectionId, false);
        if ($row === null) {
            throw new RuntimeException('Created remote connection could not be loaded.');
        }
        return remote_connection_safe_row($row);
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    } finally {
        if (isset($data['credentials']) && is_array($data['credentials']) && function_exists('sodium_memzero')) {
            foreach ($data['credentials'] as &$value) {
                if (is_string($value)) {
                    sodium_memzero($value);
                }
            }
            unset($value);
        }
    }
}

/** @return array<string,mixed>|null */
function remote_connection_update(int $ownerId, int $connectionId, array $input): ?array
{
    if ($ownerId <= 0 || $connectionId <= 0) {
        return null;
    }
    $current = remote_connection_find_owned($ownerId, $connectionId, true);
    if ($current === null) {
        return null;
    }
    $authTypeChanged = is_string($input['auth_type'] ?? null)
        && strtolower(trim($input['auth_type'])) !== (string) ($current['remote_connection_auth_type'] ?? '');
    $data = remote_connection_validate_input($input, $authTypeChanged);
    $secret = (string) ($current['remote_connection_secret'] ?? '');
    if (is_array($data['credentials'])) {
        $secret = remote_crypto_encrypt($ownerId, $connectionId, $data['credentials']);
    }
    $stmt = conn_db()->prepare(
        'UPDATE ' . remote_connection_table_name() . ' SET '
        . 'remote_connection_name = :name, remote_connection_protocol = :protocol, remote_connection_host = :host, '
        . 'remote_connection_port = :port, remote_connection_username = :username, remote_connection_auth_type = :auth_type, '
        . 'remote_connection_secret = :secret, remote_connection_base_path = :base_path, '
        . 'remote_connection_allow_private = :allow_private, remote_connection_enabled = :enabled, remote_connection_updated_at = :updated_at '
        . 'WHERE remote_connection_id = :id AND remote_connection_owner = :owner AND remote_connection_flag = 0'
    );
    $stmt->execute([
        ':name' => $data['name'], ':protocol' => $data['protocol'], ':host' => $data['host'], ':port' => $data['port'],
        ':username' => $data['username'], ':auth_type' => $data['auth_type'], ':secret' => $secret,
        ':base_path' => $data['base_path'], ':allow_private' => $data['allow_private'], ':enabled' => $data['enabled'],
        ':updated_at' => app_now(), ':id' => $connectionId, ':owner' => $ownerId,
    ]);
    $row = remote_connection_find_owned($ownerId, $connectionId, false);
    return $row === null ? null : remote_connection_safe_row($row);
}

function remote_connection_delete(int $ownerId, int $connectionId): bool
{
    if ($ownerId <= 0 || $connectionId <= 0) {
        return false;
    }
    $stmt = conn_db()->prepare(
        'UPDATE ' . remote_connection_table_name() . ' SET remote_connection_flag = 1, remote_connection_updated_at = :updated_at '
        . 'WHERE remote_connection_id = :id AND remote_connection_owner = :owner AND remote_connection_flag = 0'
    );
    $stmt->execute([':updated_at' => app_now(), ':id' => $connectionId, ':owner' => $ownerId]);
    return $stmt->rowCount() === 1;
}
