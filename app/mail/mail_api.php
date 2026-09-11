<?php

declare(strict_types=1);

function api_mail_validation_message(string $reason): string
{
    return match ($reason) {
        'invalid_display_name' => 'Mail account display name is invalid.',
        'invalid_username' => 'IMAP username is invalid.',
        'password_required' => 'IMAP password is required.',
        'invalid_password' => 'IMAP password is invalid.',
        'invalid_enabled' => 'enabled must be 0 or 1.',
        'invalid_host' => 'IMAP host must be a valid public FQDN or IP address.',
        'invalid_transport' => 'Use SSL on port 993 or STARTTLS on port 143.',
        'dns_failed' => 'IMAP host could not be resolved.',
        'non_public_address' => 'IMAP host resolves to a non-public address.',
        'invalid_smtp_enabled' => 'SMTP enabled must be 0 or 1.',
        'invalid_smtp_use_imap_credentials' => 'SMTP credential mode is invalid.',
        'invalid_smtp_host' => 'SMTP host must be a valid public FQDN or IP address.',
        'invalid_smtp_transport' => 'Use SSL/TLS on port 465 or STARTTLS on port 587 for SMTP.',
        'smtp_dns_failed' => 'SMTP host could not be resolved.',
        'smtp_non_public_address' => 'SMTP host resolves to a non-public address.',
        'smtp_username_required' => 'SMTP username is required when IMAP credentials are not reused.',
        'invalid_smtp_username' => 'SMTP username is invalid.',
        'smtp_password_required' => 'SMTP password is required when IMAP credentials are not reused.',
        'invalid_smtp_password' => 'SMTP password is invalid.',
        'invalid_from_address' => 'From address is invalid.',
        'invalid_from_name' => 'From name is invalid.',
        'invalid_sent_save_mode' => 'Sent save mode must be auto, server, or reader.',
        'invalid_recipient' => 'To address is invalid.',
        'invalid_subject' => 'Subject is invalid.',
        'invalid_body' => 'Mail body is invalid.',
        'invalid_reply_reference' => 'Mail reply reference is invalid.',
        'invalid_attachment_name' => 'Attachment file name is invalid.',
        'attachment_type_blocked' => 'This attachment file type is not allowed.',
        'attachment_count_exceeded' => 'Up to 5 attachment files are allowed.',
        'attachment_too_large' => 'Each attachment must be 10 MB or smaller.',
        'attachment_total_too_large' => 'Total attachment size must be 20 MB or smaller.',
        'invalid_attachment_upload' => 'Attachment upload failed.',
        default => 'Mail account settings are invalid.',
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_internal_failure(string $operation, int $userId, Throwable $exception): array
{
    // Do not log exception messages here: IMAP/SMTP/library messages may contain
    // endpoint data and this layer must never risk credential leakage.
    error_log(sprintf(
        'Mail API failure operation=%s user_id=%d class=%s',
        $operation,
        $userId,
        $exception::class
    ));
    return api_error('mail_operation_failed', 'Mail operation failed.', 500);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_list(int $userId, array $input): array
{
    try {
        return api_success(['accounts' => mail_service_list_accounts($userId)]);
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.list', $userId, $exception);
    }
}

/** @return array<string,mixed> */
function api_mail_account_input(array $input, bool $includeSmtp): array
{
    $account = [
        'display_name' => api_string($input, 'display_name'),
        'host' => api_string($input, 'host'),
        'port' => $input['port'] ?? null,
        'encryption' => api_string($input, 'encryption'),
        'username' => api_string($input, 'username'),
        'password' => isset($input['password']) && is_string($input['password']) ? $input['password'] : null,
        'enabled' => $input['enabled'] ?? '1',
    ];

    if ($includeSmtp) {
        $account['smtp_enabled'] = $input['smtp_enabled'] ?? '0';
        $account['smtp_host'] = api_string($input, 'smtp_host');
        $account['smtp_port'] = $input['smtp_port'] ?? null;
        $account['smtp_encryption'] = api_string($input, 'smtp_encryption');
        $account['smtp_use_imap_credentials'] = $input['smtp_use_imap_credentials'] ?? '1';
        $account['smtp_username'] = api_string($input, 'smtp_username');
        $account['smtp_password'] = isset($input['smtp_password']) && is_string($input['smtp_password'])
            ? $input['smtp_password']
            : null;
        $account['from_address'] = api_string($input, 'from_address');
        $account['from_name'] = api_string($input, 'from_name');
    }
    if (array_key_exists('sent_save_mode', $input)) {
        $account['sent_save_mode'] = $input['sent_save_mode'];
    }

    return $account;
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_create(int $userId, array $input): array
{
    try {
        $account = mail_service_create_account($userId, api_mail_account_input($input, true));
        return api_success(['account' => $account], 201);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (AppMailCredentialException $exception) {
        return api_error('mail_credential_unavailable', 'Mail credential encryption is unavailable.', 503);
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.create', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_update(int $userId, array $input): array
{
    $accountId = api_positive_int($input, 'mail_account_id');
    if ($accountId === null) {
        return api_validation_error('mail_account_id must be a positive integer.');
    }

    // Preserve all SMTP fields when an older cached V1.33.1 client submits an
    // account update without any SMTP keys.
    $includeSmtp = mail_account_has_smtp_input($input);

    try {
        $account = mail_service_update_account($userId, $accountId, api_mail_account_input($input, $includeSmtp));
        return $account === null
            ? api_error('not_found', 'Mail account was not found.', 404)
            : api_success(['account' => $account]);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (AppMailCredentialException $exception) {
        return api_error('mail_credential_unavailable', 'Mail credential encryption is unavailable.', 503);
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.update', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_delete(int $userId, array $input): array
{
    $accountId = api_positive_int($input, 'mail_account_id');
    if ($accountId === null) {
        return api_validation_error('mail_account_id must be a positive integer.');
    }

    try {
        if (mail_account_find_owned($userId, $accountId, false, false) === null) {
            return api_error('not_found', 'Mail account was not found.', 404);
        }
        if (mail_account_active_widget_count($userId, $accountId) > 0) {
            return api_error(
                'mail_account_in_use',
                'このMail AccountはMail Widgetで使用中です。先にWidget側のAccountを変更またはWidgetを削除してください。',
                409
            );
        }
        return mail_service_delete_account($userId, $accountId)
            ? api_success(['mail_account_id' => $accountId])
            : api_error('not_found', 'Mail account was not found.', 404);
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.delete', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_test(int $userId, array $input): array
{
    $accountId = api_positive_int($input, 'mail_account_id');
    if ($accountId === null) {
        return api_validation_error('mail_account_id must be a positive integer.');
    }

    $connection = isset($input['connection']) && is_string($input['connection'])
        ? strtolower(trim($input['connection']))
        : 'imap';
    if (!in_array($connection, ['imap', 'smtp'], true)) {
        return api_validation_error('connection must be imap or smtp.');
    }

    try {
        $result = $connection === 'smtp'
            ? mail_service_test_smtp_account($userId, $accountId)
            : mail_service_test_account($userId, $accountId);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.test.' . $connection, $userId, $exception);
    }

    if ($connection === 'smtp') {
        return match ($result['code']) {
            'connected' => api_success(['connected' => true, 'connection' => 'smtp']),
            'not_found' => api_error('not_found', 'Mail account was not found.', 404),
            'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
            'smtp_disabled' => api_error('mail_smtp_disabled', 'SMTP sending is disabled for this account.', 409),
            'smtp_dependency_unavailable' => api_error('mail_smtp_dependency_unavailable', 'SMTP dependency is unavailable.', 503),
            'smtp_credential_unavailable' => api_error('mail_smtp_credential_unavailable', 'SMTP credential must be re-entered.', 503),
            'invalid_smtp_host', 'invalid_smtp_transport', 'smtp_dns_failed', 'smtp_non_public_address'
                => api_validation_error(api_mail_validation_message($result['code'])),
            'smtp_rejected' => api_error('mail_smtp_rejected', 'SMTP server rejected the connection or authentication.', 422),
            default => api_error('mail_smtp_connection_failed', 'Could not connect to the SMTP server.', 502),
        };
    }

    return match ($result['code']) {
        'connected' => api_success(['connected' => true]),
        'not_found' => api_error('not_found', 'Mail account was not found.', 404),
        'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
        'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
        'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
        'invalid_host', 'invalid_transport', 'dns_failed', 'non_public_address' => api_validation_error(api_mail_validation_message($result['code'])),
        'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
        default => api_error('mail_connection_failed', 'Could not connect to the IMAP server.', 502),
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_message_send(int $userId, array $input, array $files = []): array
{
    $accountId = api_positive_int($input, 'mail_account_id');
    if ($accountId === null) {
        return api_validation_error('mail_account_id must be a positive integer.');
    }

    try {
        $messageInput = [
            'to' => isset($input['to']) && is_string($input['to']) ? $input['to'] : null,
            'subject' => isset($input['subject']) && is_string($input['subject']) ? $input['subject'] : null,
            'body' => isset($input['body']) && is_string($input['body']) ? $input['body'] : null,
        ];
        if (array_key_exists('in_reply_to', $input) && $input['in_reply_to'] !== null && $input['in_reply_to'] !== '') {
            if (!is_string($input['in_reply_to'])) {
                throw new AppMailValidationException('invalid_reply_reference');
            }
            $messageInput['in_reply_to'] = $input['in_reply_to'];
        }
        $result = mail_service_send_plain_text($userId, $accountId, $messageInput, $files);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('message.send', $userId, $exception);
    }

    return match ($result['code']) {
        'sent' => api_success([
            'sent' => true,
            'sent_save_status' => (string) ($result['sent_save_status'] ?? 'failed'),
            'sent_save_source' => (string) ($result['sent_save_source'] ?? 'none'),
            'sent_save_code' => (string) ($result['sent_save_code'] ?? ''),
            'sent_folder' => (string) ($result['sent_folder'] ?? ''),
        ]),
        'not_found' => api_error('not_found', 'Mail account was not found.', 404),
        'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
        'smtp_disabled' => api_error('mail_smtp_disabled', 'SMTP sending is disabled for this account.', 409),
        'smtp_dependency_unavailable' => api_error('mail_smtp_dependency_unavailable', 'SMTP dependency is unavailable.', 503),
        'smtp_credential_unavailable' => api_error('mail_smtp_credential_unavailable', 'SMTP credential must be re-entered.', 503),
        'smtp_configuration_unavailable' => api_error('mail_smtp_configuration_unavailable', 'SMTP sender configuration is unavailable.', 409),
        'invalid_smtp_host', 'invalid_smtp_transport', 'smtp_dns_failed', 'smtp_non_public_address'
            => api_validation_error(api_mail_validation_message($result['code'])),
        'smtp_message_invalid' => api_validation_error('Mail message is invalid.'),
        'smtp_attachment_invalid' => api_validation_error('Mail attachment is invalid.'),
        'smtp_rejected' => api_error('mail_smtp_rejected', 'SMTP server rejected the connection or authentication.', 422),
        'smtp_send_failed' => api_error('mail_send_failed', 'Mail could not be sent. The result is uncertain; do not automatically retry.', 502),
        default => api_error('mail_send_failed', 'Mail could not be sent.', 502),
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_message_reply_context(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $uid = api_positive_int($input, 'mail_uid');
    $folder = isset($input['mail_folder']) && is_string($input['mail_folder'])
        ? $input['mail_folder']
        : '';
    if ($widgetId === null || $uid === null || $folder === '') {
        return api_validation_error('widget_id, mail_uid, and mail_folder are invalid.');
    }

    try {
        $result = mail_service_reply_context($userId, $widgetId, $uid, $folder);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('message.reply.context', $userId, $exception);
    }

    return match ($result['code']) {
        'loaded' => api_success([
            'mail_account_id' => $result['mail_account_id'] ?? 0,
            'to' => $result['to'] ?? '',
            'to_name' => $result['to_name'] ?? '',
            'subject' => $result['subject'] ?? 'Re: 件名なし',
            'message_id' => $result['message_id'] ?? '',
            'recipient_source' => $result['recipient_source'] ?? 'from',
            'folder' => $result['folder'] ?? $folder,
        ]),
        'not_found', 'message_not_found' => api_error('not_found', 'Mail message was not found.', 404),
        'folder_changed' => api_error('mail_folder_changed', 'Mail folder changed. Refresh the Widget and try again.', 409),
        'invalid_folder' => api_validation_error('Mail folder is invalid.'),
        'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
        'smtp_disabled' => api_error('mail_smtp_disabled', 'SMTP sending is disabled for this account.', 409),
        'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
        'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
        'invalid_host', 'invalid_transport', 'dns_failed', 'non_public_address'
            => api_validation_error(api_mail_validation_message($result['code'])),
        'no_reply_address' => api_error('mail_reply_address_unavailable', 'A valid Reply-To or From address was not found.', 422),
        'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
        default => api_error('mail_connection_failed', 'Could not prepare the Mail reply.', 502),
    };
}

