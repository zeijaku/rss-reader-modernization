<?php

declare(strict_types=1);

function mail_account_table_name(): string
{
    return (string) DB_TABLE_PREFIX . 'mail_account';
}

function mail_account_table_identifier(): string
{
    return '`' . mail_account_table_name() . '`';
}

function mail_account_validate_display_name(mixed $value): string
{
    $name = is_string($value) ? trim($value) : '';
    if ($name === '' || !app_is_valid_utf8($name) || mail_has_control_characters($name) || mail_text_length($name) > 128) {
        throw new AppMailValidationException('invalid_display_name');
    }
    return $name;
}

function mail_account_validate_username(mixed $value): string
{
    $username = is_string($value) ? trim($value) : '';
    if ($username === '' || !app_is_valid_utf8($username) || mail_has_control_characters($username) || mail_text_length($username) > 320) {
        throw new AppMailValidationException('invalid_username');
    }
    return $username;
}

function mail_account_validate_password(mixed $value, bool $required): ?string
{
    if ($value === null || $value === '') {
        if ($required) {
            throw new AppMailValidationException('password_required');
        }
        return null;
    }
    if (!is_string($value) || strlen($value) > 8192 || str_contains($value, "\0")) {
        throw new AppMailValidationException('invalid_password');
    }
    return $value;
}

function mail_account_validate_enabled(mixed $value): int
{
    if ($value === null || $value === '') {
        return 1;
    }
    if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
        return 1;
    }
    if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
        return 0;
    }
    throw new AppMailValidationException('invalid_enabled');
}

/** @return array{display_name:string,host:string,port:int,encryption:string,username:string,password:?string,enabled:int} */
function mail_account_validate_input(array $input, bool $passwordRequired): array
{
    $target = mail_validate_target(
        $input['host'] ?? null,
        $input['port'] ?? null,
        $input['encryption'] ?? null
    );
    if (!$target['ok']) {
        throw new AppMailValidationException($target['error_code']);
    }

    return [
        'display_name' => mail_account_validate_display_name($input['display_name'] ?? null),
        'host' => $target['host'],
        'port' => $target['port'],
        'encryption' => $target['encryption'],
        'username' => mail_account_validate_username($input['username'] ?? null),
        'password' => mail_account_validate_password($input['password'] ?? null, $passwordRequired),
        'enabled' => mail_account_validate_enabled($input['enabled'] ?? null),
    ];
}

/** @return array<string,mixed> */
function mail_account_safe_row(array $row): array
{
    return [
        'mail_account_id' => (int) ($row['mail_account_id'] ?? 0),
        'display_name' => (string) ($row['mail_account_display_name'] ?? ''),
        'host' => (string) ($row['mail_account_host'] ?? ''),
        'port' => (int) ($row['mail_account_port'] ?? 0),
        'encryption' => (string) ($row['mail_account_encryption'] ?? ''),
        'username' => (string) ($row['mail_account_username'] ?? ''),
        'enabled' => (int) ($row['mail_account_enabled'] ?? 0) === 1,
        'created_at' => (string) ($row['mail_account_created_at'] ?? ''),
        'updated_at' => (string) ($row['mail_account_updated_at'] ?? ''),
    ];
}

function mail_account_supports_for_update(PDO $conn): bool
{
    return strtolower((string) $conn->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
}

/** @return array<string,mixed>|null */
function mail_account_find_owned(int $ownerId, int $accountId, bool $includeSecret = false, bool $lock = false): ?array
{
    if ($ownerId <= 0 || $accountId <= 0) {
        return null;
    }

    $columns = $includeSecret
        ? '*'
        : 'mail_account_id, mail_account_owner, mail_account_display_name, mail_account_host, mail_account_port, '
            . 'mail_account_encryption, mail_account_username, mail_account_enabled, mail_account_flag, '
            . 'mail_account_created_at, mail_account_updated_at';
    $sql = 'SELECT ' . $columns . ' FROM ' . mail_account_table_name() . ' '
        . 'WHERE mail_account_id = :account_id AND mail_account_owner = :owner AND mail_account_flag = 0 LIMIT 1';
    $conn = conn_db();
    if ($lock && mail_account_supports_for_update($conn)) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([':account_id' => $accountId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return list<array<string,mixed>> */
function mail_account_list(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }
    $stmt = conn_db()->prepare(
        'SELECT mail_account_id, mail_account_owner, mail_account_display_name, mail_account_host, mail_account_port, '
        . 'mail_account_encryption, mail_account_username, mail_account_enabled, mail_account_flag, '
        . 'mail_account_created_at, mail_account_updated_at '
        . 'FROM ' . mail_account_table_name() . ' '
        . 'WHERE mail_account_owner = :owner AND mail_account_flag = 0 ORDER BY mail_account_id ASC'
    );
    $stmt->execute([':owner' => $ownerId]);
    $rows = $stmt->fetchAll();
    return array_values(array_map('mail_account_safe_row', is_array($rows) ? $rows : []));
}

/** @return array<string,mixed> */
function mail_account_create(int $ownerId, array $input): array
{
    if ($ownerId <= 0) {
        throw new AppMailValidationException('invalid_owner');
    }
    $data = mail_account_validate_input($input, true);
    $conn = conn_db();

    try {
        $conn->beginTransaction();
        $now = app_now();
        $stmt = $conn->prepare(
            'INSERT INTO ' . mail_account_table_name() . ' ('
            . 'mail_account_owner, mail_account_display_name, mail_account_host, mail_account_port, '
            . 'mail_account_encryption, mail_account_username, mail_account_secret, mail_account_enabled, '
            . 'mail_account_flag, mail_account_created_at, mail_account_updated_at'
            . ') VALUES ('
            . ':owner, :display_name, :host, :port, :encryption, :username, :secret, :enabled, 0, :created_at, :updated_at'
            . ')'
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':display_name' => $data['display_name'],
            ':host' => $data['host'],
            ':port' => $data['port'],
            ':encryption' => $data['encryption'],
            ':username' => $data['username'],
            ':secret' => '',
            ':enabled' => $data['enabled'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $accountId = (int) $conn->lastInsertId();
        if ($accountId <= 0 || !is_string($data['password'])) {
            throw new RuntimeException('Mail account insert did not return an ID.');
        }

        $secret = mail_crypto_encrypt($ownerId, $accountId, $data['password']);
        $stmt = $conn->prepare(
            'UPDATE ' . mail_account_table_name() . ' SET mail_account_secret = :secret '
            . 'WHERE mail_account_id = :account_id AND mail_account_owner = :owner AND mail_account_flag = 0'
        );
        $stmt->execute([':secret' => $secret, ':account_id' => $accountId, ':owner' => $ownerId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Mail account credential update did not affect one row.');
        }

        $conn->commit();
        $row = mail_account_find_owned($ownerId, $accountId, false, false);
        if ($row === null) {
            throw new RuntimeException('Created mail account could not be loaded.');
        }
        return mail_account_safe_row($row);
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    } finally {
        if (isset($data['password']) && is_string($data['password']) && function_exists('sodium_memzero')) {
            sodium_memzero($data['password']);
        }
    }
}

/** @return array<string,mixed>|null */
function mail_account_update(int $ownerId, int $accountId, array $input): ?array
{
    if ($ownerId <= 0 || $accountId <= 0) {
        return null;
    }
    $data = mail_account_validate_input($input, false);
    $conn = conn_db();

    try {
        $conn->beginTransaction();
        $current = mail_account_find_owned($ownerId, $accountId, true, true);
        if ($current === null) {
            $conn->rollBack();
            return null;
        }

        $secret = (string) ($current['mail_account_secret'] ?? '');
        if (is_string($data['password'])) {
            $secret = mail_crypto_encrypt($ownerId, $accountId, $data['password']);
        }

        $stmt = $conn->prepare(
            'UPDATE ' . mail_account_table_name() . ' SET '
            . 'mail_account_display_name = :display_name, mail_account_host = :host, mail_account_port = :port, '
            . 'mail_account_encryption = :encryption, mail_account_username = :username, mail_account_secret = :secret, '
            . 'mail_account_enabled = :enabled, mail_account_updated_at = :updated_at '
            . 'WHERE mail_account_id = :account_id AND mail_account_owner = :owner AND mail_account_flag = 0'
        );
        $stmt->execute([
            ':display_name' => $data['display_name'],
            ':host' => $data['host'],
            ':port' => $data['port'],
            ':encryption' => $data['encryption'],
            ':username' => $data['username'],
            ':secret' => $secret,
            ':enabled' => $data['enabled'],
            ':updated_at' => app_now(),
            ':account_id' => $accountId,
            ':owner' => $ownerId,
        ]);

        $conn->commit();
        $row = mail_account_find_owned($ownerId, $accountId, false, false);
        return $row === null ? null : mail_account_safe_row($row);
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    } finally {
        if (isset($data['password']) && is_string($data['password']) && function_exists('sodium_memzero')) {
            sodium_memzero($data['password']);
        }
    }
}


function mail_account_active_widget_count(int $ownerId, int $accountId): int
{
    if ($ownerId <= 0 || $accountId <= 0) {
        return 0;
    }

    $stmt = conn_db()->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_owner = :owner AND widget_type = 'mail' "
        . 'AND widget_reference_id = :account_id AND widget_flag = 0'
    );
    $stmt->execute([':owner' => $ownerId, ':account_id' => $accountId]);
    return max(0, (int) $stmt->fetchColumn());
}

function mail_account_delete(int $ownerId, int $accountId): bool
{
    if ($ownerId <= 0 || $accountId <= 0) {
        return false;
    }
    $stmt = conn_db()->prepare(
        'UPDATE ' . mail_account_table_name() . ' '
        . 'SET mail_account_flag = 1, mail_account_enabled = 0, mail_account_updated_at = :updated_at '
        . 'WHERE mail_account_id = :account_id AND mail_account_owner = :owner AND mail_account_flag = 0'
    );
    $stmt->execute([':updated_at' => app_now(), ':account_id' => $accountId, ':owner' => $ownerId]);
    return $stmt->rowCount() === 1;
}
