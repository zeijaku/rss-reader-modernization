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

function mail_account_validate_smtp_flag(mixed $value, int $default, string $reason): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
        return 1;
    }
    if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
        return 0;
    }
    throw new AppMailValidationException($reason);
}

function mail_account_validate_sent_save_mode(mixed $value, string $default = 'auto'): string
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (!is_string($value)) {
        throw new AppMailValidationException('invalid_sent_save_mode');
    }
    $mode = strtolower(trim($value));
    if (!in_array($mode, ['auto', 'server', 'reader'], true)) {
        throw new AppMailValidationException('invalid_sent_save_mode');
    }
    return $mode;
}

function mail_account_validate_smtp_username(mixed $value, bool $required): ?string
{
    if ($value === null || $value === '') {
        if ($required) {
            throw new AppMailValidationException('smtp_username_required');
        }
        return null;
    }
    if (!is_string($value)) {
        throw new AppMailValidationException('invalid_smtp_username');
    }
    $username = trim($value);
    if ($username === '' || !app_is_valid_utf8($username) || mail_has_control_characters($username) || mail_text_length($username) > 320) {
        throw new AppMailValidationException('invalid_smtp_username');
    }
    return $username;
}

function mail_account_validate_smtp_password(mixed $value, bool $required): ?string
{
    if ($value === null || $value === '') {
        if ($required) {
            throw new AppMailValidationException('smtp_password_required');
        }
        return null;
    }
    if (!is_string($value) || strlen($value) > 8192 || str_contains($value, "\0")) {
        throw new AppMailValidationException('invalid_smtp_password');
    }
    return $value;
}

function mail_account_validate_from_address(mixed $value): string
{
    $address = is_string($value) ? trim($value) : '';
    if (
        $address === ''
        || !app_is_valid_utf8($address)
        || mail_has_control_characters($address)
        || mail_text_length($address) > 320
        || filter_var($address, FILTER_VALIDATE_EMAIL) === false
    ) {
        throw new AppMailValidationException('invalid_from_address');
    }
    return $address;
}

function mail_account_validate_from_name(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value)) {
        throw new AppMailValidationException('invalid_from_name');
    }
    $name = trim($value);
    if ($name === '') {
        return null;
    }
    if (!app_is_valid_utf8($name) || mail_has_control_characters($name) || mail_text_length($name) > 128) {
        throw new AppMailValidationException('invalid_from_name');
    }
    return $name;
}

function mail_account_has_smtp_input(array $input): bool
{
    foreach ([
        'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
        'smtp_use_imap_credentials', 'smtp_username', 'smtp_password',
        'from_address', 'from_name',
    ] as $key) {
        if (array_key_exists($key, $input)) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{smtp_enabled:int,smtp_host:?string,smtp_port:?int,smtp_encryption:?string,smtp_use_imap_credentials:int,smtp_username:?string,smtp_password:?string,from_address:?string,from_name:?string}
 */
function mail_account_validate_smtp_input(array $input, bool $hasExistingSmtpPassword): array
{
    $enabled = mail_account_validate_smtp_flag($input['smtp_enabled'] ?? null, 0, 'invalid_smtp_enabled');
    $useImap = mail_account_validate_smtp_flag(
        $input['smtp_use_imap_credentials'] ?? null,
        1,
        'invalid_smtp_use_imap_credentials'
    );

    // New accounts keep SMTP completely dormant until explicitly enabled.
    if ($enabled !== 1) {
        return [
            'smtp_enabled' => 0,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'smtp_use_imap_credentials' => $useImap,
            'smtp_username' => null,
            'smtp_password' => null,
            'from_address' => null,
            'from_name' => null,
        ];
    }

    $target = mail_validate_smtp_target(
        $input['smtp_host'] ?? null,
        $input['smtp_port'] ?? null,
        $input['smtp_encryption'] ?? null
    );
    if (!$target['ok']) {
        throw new AppMailValidationException($target['error_code']);
    }

    $smtpUsername = null;
    $smtpPassword = null;
    if ($useImap !== 1) {
        $smtpUsername = mail_account_validate_smtp_username($input['smtp_username'] ?? null, true);
        $smtpPassword = mail_account_validate_smtp_password(
            $input['smtp_password'] ?? null,
            !$hasExistingSmtpPassword
        );
    }

    return [
        'smtp_enabled' => 1,
        'smtp_host' => $target['host'],
        'smtp_port' => $target['port'],
        'smtp_encryption' => $target['encryption'],
        'smtp_use_imap_credentials' => $useImap,
        'smtp_username' => $smtpUsername,
        'smtp_password' => $smtpPassword,
        'from_address' => mail_account_validate_from_address($input['from_address'] ?? null),
        'from_name' => mail_account_validate_from_name($input['from_name'] ?? null),
    ];
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
        'smtp_enabled' => (int) ($row['mail_account_smtp_enabled'] ?? 0) === 1,
        'smtp_host' => (string) ($row['mail_account_smtp_host'] ?? ''),
        'smtp_port' => isset($row['mail_account_smtp_port']) ? (int) $row['mail_account_smtp_port'] : null,
        'smtp_encryption' => (string) ($row['mail_account_smtp_encryption'] ?? ''),
        'smtp_use_imap_credentials' => (int) ($row['mail_account_smtp_use_imap_credentials'] ?? 1) === 1,
        'smtp_username' => (string) ($row['mail_account_smtp_username'] ?? ''),
        'from_address' => (string) ($row['mail_account_from_address'] ?? ''),
        'from_name' => (string) ($row['mail_account_from_name'] ?? ''),
        'sent_save_mode' => mail_account_validate_sent_save_mode($row['mail_account_sent_save_mode'] ?? null),
        'created_at' => (string) ($row['mail_account_created_at'] ?? ''),
        'updated_at' => (string) ($row['mail_account_updated_at'] ?? ''),
    ];
}

function mail_account_supports_for_update(PDO $conn): bool
{
    return strtolower((string) $conn->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'mysql';
}

function mail_account_public_columns(): string
{
    return 'mail_account_id, mail_account_owner, mail_account_display_name, mail_account_host, mail_account_port, '
        . 'mail_account_encryption, mail_account_username, mail_account_enabled, mail_account_flag, '
        . 'mail_account_smtp_enabled, mail_account_smtp_host, mail_account_smtp_port, mail_account_smtp_encryption, '
        . 'mail_account_smtp_use_imap_credentials, mail_account_smtp_username, mail_account_from_address, mail_account_from_name, '
        . 'mail_account_sent_save_mode, '
        . 'mail_account_created_at, mail_account_updated_at';
}

/** @return array<string,mixed>|null */
function mail_account_find_owned(int $ownerId, int $accountId, bool $includeSecret = false, bool $lock = false): ?array
{
    if ($ownerId <= 0 || $accountId <= 0) {
        return null;
    }

    $columns = $includeSecret ? '*' : mail_account_public_columns();
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
        'SELECT ' . mail_account_public_columns() . ' FROM ' . mail_account_table_name() . ' '
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
    $smtp = mail_account_validate_smtp_input($input, false);
    $sentSaveMode = mail_account_validate_sent_save_mode($input['sent_save_mode'] ?? null);
    $conn = conn_db();

    try {
        $conn->beginTransaction();
        $now = app_now();
        $stmt = $conn->prepare(
            'INSERT INTO ' . mail_account_table_name() . ' ('
            . 'mail_account_owner, mail_account_display_name, mail_account_host, mail_account_port, '
            . 'mail_account_encryption, mail_account_username, mail_account_secret, mail_account_enabled, '
            . 'mail_account_smtp_enabled, mail_account_smtp_host, mail_account_smtp_port, mail_account_smtp_encryption, '
            . 'mail_account_smtp_use_imap_credentials, mail_account_smtp_username, mail_account_smtp_secret, '
            . 'mail_account_from_address, mail_account_from_name, mail_account_sent_save_mode, '
            . 'mail_account_flag, mail_account_created_at, mail_account_updated_at'
            . ') VALUES ('
            . ':owner, :display_name, :host, :port, :encryption, :username, :secret, :enabled, '
            . ':smtp_enabled, :smtp_host, :smtp_port, :smtp_encryption, :smtp_use_imap_credentials, '
            . ':smtp_username, :smtp_secret, :from_address, :from_name, :sent_save_mode, 0, :created_at, :updated_at'
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
            ':smtp_enabled' => $smtp['smtp_enabled'],
            ':smtp_host' => $smtp['smtp_host'],
            ':smtp_port' => $smtp['smtp_port'],
            ':smtp_encryption' => $smtp['smtp_encryption'],
            ':smtp_use_imap_credentials' => $smtp['smtp_use_imap_credentials'],
            ':smtp_username' => $smtp['smtp_username'],
            ':smtp_secret' => null,
            ':from_address' => $smtp['from_address'],
            ':from_name' => $smtp['from_name'],
            ':sent_save_mode' => $sentSaveMode,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $accountId = (int) $conn->lastInsertId();
        if ($accountId <= 0 || !is_string($data['password'])) {
            throw new RuntimeException('Mail account insert did not return an ID.');
        }

        $secret = mail_crypto_encrypt($ownerId, $accountId, $data['password']);
        $smtpSecret = null;
        if ($smtp['smtp_enabled'] === 1 && $smtp['smtp_use_imap_credentials'] !== 1) {
            if (!is_string($smtp['smtp_password'])) {
                throw new RuntimeException('SMTP credential was not available after validation.');
            }
            $smtpSecret = mail_crypto_encrypt_smtp($ownerId, $accountId, $smtp['smtp_password']);
        }

        $stmt = $conn->prepare(
            'UPDATE ' . mail_account_table_name() . ' SET mail_account_secret = :secret, mail_account_smtp_secret = :smtp_secret '
            . 'WHERE mail_account_id = :account_id AND mail_account_owner = :owner AND mail_account_flag = 0'
        );
        $stmt->execute([
            ':secret' => $secret,
            ':smtp_secret' => $smtpSecret,
            ':account_id' => $accountId,
            ':owner' => $ownerId,
        ]);
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
        if (isset($smtp['smtp_password']) && is_string($smtp['smtp_password']) && function_exists('sodium_memzero')) {
            sodium_memzero($smtp['smtp_password']);
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
    $smtpInputProvided = mail_account_has_smtp_input($input);
    $smtp = null;
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

        // Old V1.33.1 JS does not send SMTP fields. Preserve all SMTP data in
        // that case so a stale browser cache cannot silently disable sending.
        $smtpEnabled = (int) ($current['mail_account_smtp_enabled'] ?? 0);
        $smtpHost = $current['mail_account_smtp_host'] ?? null;
        $smtpPort = isset($current['mail_account_smtp_port']) ? (int) $current['mail_account_smtp_port'] : null;
        $smtpEncryption = $current['mail_account_smtp_encryption'] ?? null;
        $smtpUseImap = (int) ($current['mail_account_smtp_use_imap_credentials'] ?? 1);
        $smtpUsername = $current['mail_account_smtp_username'] ?? null;
        $smtpSecret = $current['mail_account_smtp_secret'] ?? null;
        $fromAddress = $current['mail_account_from_address'] ?? null;
        $fromName = $current['mail_account_from_name'] ?? null;
        $sentSaveMode = mail_account_validate_sent_save_mode($current['mail_account_sent_save_mode'] ?? null);
        if (array_key_exists('sent_save_mode', $input)) {
            $sentSaveMode = mail_account_validate_sent_save_mode($input['sent_save_mode']);
        }

        if ($smtpInputProvided) {
            $requestedSmtpEnabled = mail_account_validate_smtp_flag(
                $input['smtp_enabled'] ?? null,
                0,
                'invalid_smtp_enabled'
            );
            if ($requestedSmtpEnabled !== 1) {
                // Disabling SMTP is non-destructive: retain the validated
                // configuration so it can be re-enabled later.
                $smtpEnabled = 0;
            } else {
                $hasExistingSmtpPassword = is_string($smtpSecret) && $smtpSecret !== '';
                $smtp = mail_account_validate_smtp_input($input, $hasExistingSmtpPassword);
                $smtpEnabled = 1;
                $smtpHost = $smtp['smtp_host'];
                $smtpPort = $smtp['smtp_port'];
                $smtpEncryption = $smtp['smtp_encryption'];
                $smtpUseImap = $smtp['smtp_use_imap_credentials'];
                $fromAddress = $smtp['from_address'];
                $fromName = $smtp['from_name'];

                if ($smtpUseImap === 1) {
                    // Do not retain an unused second password when IMAP
                    // credentials are explicitly selected for SMTP.
                    $smtpUsername = null;
                    $smtpSecret = null;
                } else {
                    $smtpUsername = $smtp['smtp_username'];
                    if (is_string($smtp['smtp_password'])) {
                        $smtpSecret = mail_crypto_encrypt_smtp($ownerId, $accountId, $smtp['smtp_password']);
                    }
                }
            }
        }

        $stmt = $conn->prepare(
            'UPDATE ' . mail_account_table_name() . ' SET '
            . 'mail_account_display_name = :display_name, mail_account_host = :host, mail_account_port = :port, '
            . 'mail_account_encryption = :encryption, mail_account_username = :username, mail_account_secret = :secret, '
            . 'mail_account_enabled = :enabled, '
            . 'mail_account_smtp_enabled = :smtp_enabled, mail_account_smtp_host = :smtp_host, mail_account_smtp_port = :smtp_port, '
            . 'mail_account_smtp_encryption = :smtp_encryption, mail_account_smtp_use_imap_credentials = :smtp_use_imap_credentials, '
            . 'mail_account_smtp_username = :smtp_username, mail_account_smtp_secret = :smtp_secret, '
            . 'mail_account_from_address = :from_address, mail_account_from_name = :from_name, '
            . 'mail_account_sent_save_mode = :sent_save_mode, mail_account_updated_at = :updated_at '
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
            ':smtp_enabled' => $smtpEnabled,
            ':smtp_host' => $smtpHost,
            ':smtp_port' => $smtpPort,
            ':smtp_encryption' => $smtpEncryption,
            ':smtp_use_imap_credentials' => $smtpUseImap,
            ':smtp_username' => $smtpUsername,
            ':smtp_secret' => $smtpSecret,
            ':from_address' => $fromAddress,
            ':from_name' => $fromName,
            ':sent_save_mode' => $sentSaveMode,
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
        if (is_array($smtp) && isset($smtp['smtp_password']) && is_string($smtp['smtp_password']) && function_exists('sodium_memzero')) {
            sodium_memzero($smtp['smtp_password']);
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
        . 'SET mail_account_flag = 1, mail_account_enabled = 0, mail_account_smtp_enabled = 0, mail_account_updated_at = :updated_at '
        . 'WHERE mail_account_id = :account_id AND mail_account_owner = :owner AND mail_account_flag = 0'
    );
    $stmt->execute([':updated_at' => app_now(), ':account_id' => $accountId, ':owner' => $ownerId]);
    return $stmt->rowCount() === 1;
}
