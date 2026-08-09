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
        default => 'Mail account settings are invalid.',
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_internal_failure(string $operation, int $userId, Throwable $exception): array
{
    // Do not log exception messages here: IMAP/library messages may contain
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

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_create(int $userId, array $input): array
{
    try {
        $account = mail_service_create_account($userId, [
            'display_name' => api_string($input, 'display_name'),
            'host' => api_string($input, 'host'),
            'port' => $input['port'] ?? null,
            'encryption' => api_string($input, 'encryption'),
            'username' => api_string($input, 'username'),
            'password' => isset($input['password']) && is_string($input['password']) ? $input['password'] : null,
            'enabled' => $input['enabled'] ?? '1',
        ]);
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

    try {
        $account = mail_service_update_account($userId, $accountId, [
            'display_name' => api_string($input, 'display_name'),
            'host' => api_string($input, 'host'),
            'port' => $input['port'] ?? null,
            'encryption' => api_string($input, 'encryption'),
            'username' => api_string($input, 'username'),
            'password' => isset($input['password']) && is_string($input['password']) ? $input['password'] : null,
            'enabled' => $input['enabled'] ?? '1',
        ]);
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

    try {
        $result = mail_service_test_account($userId, $accountId);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (PDOException $exception) {
        return api_error('mail_account_unavailable', 'Mail account migration is required.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.test', $userId, $exception);
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
