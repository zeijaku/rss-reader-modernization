<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_error.php';

/** @return array{status:int,body:array<string,mixed>}|null */
function api_mail_error_from_code(string $code): ?array
{
    $normalized = match ($code) {
        'credential_unavailable' => 'mail_credential_unavailable',
        'smtp_credential_unavailable' => 'mail_smtp_credential_unavailable',
        default => $code,
    };
    $details = mail_public_error_details($normalized);
    return $details === null
        ? null
        : api_error($details['code'], $details['message'], $details['status']);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_storage_failure(): array
{
    return api_mail_error_from_code('mail_storage_unavailable')
        ?? api_error('mail_storage_unavailable', 'Mail Accountの保存領域を利用できません。', 503);
}

function api_mail_validation_message(string $reason): string
{
    return match ($reason) {
        'invalid_display_name' => 'Mail Accountの表示名が正しくありません。',
        'invalid_username' => 'IMAP User名が正しくありません。',
        'password_required' => 'IMAP Passwordを入力してください。',
        'invalid_password' => 'IMAP Passwordが正しくありません。',
        'invalid_enabled' => 'Mail Accountの有効・無効設定が正しくありません。',
        'invalid_host' => 'IMAP Hostには公開FQDNまたは公開IP Addressを指定してください。',
        'invalid_transport' => 'IMAPはSSL（993）またはSTARTTLS（143）を指定してください。',
        'dns_failed' => 'IMAP Hostの名前解決に失敗しました。Host名を確認してください。',
        'non_public_address' => 'IMAP HostがPrivate Addressを指しています。公開Addressを指定してください。',
        'invalid_smtp_enabled' => 'SMTPの有効・無効設定が正しくありません。',
        'invalid_smtp_use_imap_credentials' => 'SMTP認証情報の選択が正しくありません。',
        'invalid_smtp_host' => 'SMTP Hostには公開FQDNまたは公開IP Addressを指定してください。',
        'invalid_smtp_transport' => 'SMTPはSSL/TLS（465）またはSTARTTLS（587）を指定してください。',
        'smtp_dns_failed' => 'SMTP Hostの名前解決に失敗しました。Host名を確認してください。',
        'smtp_non_public_address' => 'SMTP HostがPrivate Addressを指しています。公開Addressを指定してください。',
        'smtp_username_required' => 'IMAP認証情報を共用しない場合はSMTP User名が必要です。',
        'invalid_smtp_username' => 'SMTP User名が正しくありません。',
        'smtp_password_required' => 'IMAP認証情報を共用しない場合はSMTP Passwordが必要です。',
        'invalid_smtp_password' => 'SMTP Passwordが正しくありません。',
        'invalid_from_address' => '差出人Addressが正しくありません。',
        'invalid_from_name' => '差出人名が正しくありません。',
        'invalid_sent_save_mode' => '送信済み保存方式が正しくありません。',
        'invalid_recipient' => '送信先Addressが正しくありません。',
        'invalid_subject' => '件名が正しくありません。',
        'invalid_body' => 'Mail本文が正しくありません。',
        'invalid_reply_reference' => '返信元Mailの識別情報が正しくありません。',
        'invalid_attachment_name' => '添付ファイル名が正しくありません。',
        'attachment_type_blocked' => 'この種類の添付ファイルは送信できません。',
        'attachment_count_exceeded' => '添付ファイルは5個までです。',
        'attachment_too_large' => '添付ファイル1個の上限は10 MBです。',
        'attachment_total_too_large' => '添付ファイル合計の上限は20 MBです。',
        'invalid_attachment_upload' => '添付ファイルのUploadに失敗しました。',
        default => 'Mail Account設定が正しくありません。',
    };
}

function api_mail_sent_save_message(string $code): string
{
    $details = mail_public_error_details($code);
    if ($details !== null) {
        return $details['message'];
    }
    return match ($code) {
        '' => '',
        'sent_folder_unavailable' => '送信済みFolderを確認できませんでした。',
        'sent_save_uncertain' => '送信済みへの保存結果を確認できません。重複防止のため自動再試行しません。',
        'message_unavailable' => '送信済みへ保存するMailデータを作成できませんでした。',
        'dependency_unavailable' => '送信済みへの保存に必要なMail機能を利用できません。',
        'invalid_folder' => '送信済みFolderの設定が正しくありません。',
        'imap_rejected' => 'Mailは送信しましたが、IMAP Serverが送信済みへの保存を拒否しました。',
        'connection_failed' => 'Mailは送信しましたが、IMAP Serverへ接続できず送信済みに保存できませんでした。',
        default => 'Mailは送信しましたが、送信済みへの保存を確認できませんでした。',
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_internal_failure(string $operation, int $userId, Throwable $exception): array
{
    // Do not log exception messages here: IMAP/SMTP/library messages may contain
    // endpoint data and this layer must never risk credential leakage.
    try {
        $reference = bin2hex(random_bytes(6));
    } catch (Throwable) {
        $reference = substr(hash('sha256', uniqid('', true)), 0, 12);
    }
    error_log(sprintf(
        'Mail API failure ref=%s operation=%s user_id=%d class=%s',
        $reference,
        $operation,
        $userId,
        $exception::class
    ));
    return api_error(
        'mail_operation_failed',
        'Mail処理を完了できませんでした。参照番号: ' . $reference,
        500
    );
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_account_list(int $userId, array $input): array
{
    try {
        return api_success(['accounts' => mail_service_list_accounts($userId)]);
    } catch (PDOException $exception) {
        return api_mail_storage_failure();
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.list', $userId, $exception);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_google_oauth_begin(int $userId): array
{
    try {
        $result = mail_google_oauth_begin($userId);
        return api_success([
            'authorization_url' => $result['authorization_url'],
        ]);
    } catch (AppMailGoogleOAuthException $exception) {
        $code = mail_log_auth_failure('oauth.google.begin', $userId, null, $exception);
        return api_mail_error_from_code($code)
            ?? api_error('mail_oauth_unavailable', 'Gmail OAuth2を開始できませんでした。', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('oauth.google.begin', $userId, $exception);
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
        $code = mail_log_auth_failure('account.create', $userId, null, $exception);
        return api_mail_error_from_code($code)
            ?? api_error('mail_credential_unavailable', 'Mail認証情報を保存できませんでした。', 503);
    } catch (PDOException $exception) {
        return api_mail_storage_failure();
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
            ? api_error('not_found', 'Mail Accountが見つかりません。', 404)
            : api_success(['account' => $account]);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (AppMailCredentialException $exception) {
        $code = mail_log_auth_failure('account.update', $userId, $accountId, $exception);
        return api_mail_error_from_code($code)
            ?? api_error('mail_credential_unavailable', 'Mail認証情報を保存できませんでした。', 503);
    } catch (PDOException $exception) {
        return api_mail_storage_failure();
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
            return api_error('not_found', 'Mail Accountが見つかりません。', 404);
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
            : api_error('not_found', 'Mail Accountが見つかりません。', 404);
    } catch (PDOException $exception) {
        return api_mail_storage_failure();
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
        return api_mail_storage_failure();
    } catch (Throwable $exception) {
        return api_mail_internal_failure('account.test.' . $connection, $userId, $exception);
    }

    $authFailure = api_mail_error_from_code((string) ($result['code'] ?? ''));
    if ($authFailure !== null) {
        return $authFailure;
    }

    if ($connection === 'smtp') {
        return match ($result['code']) {
            'connected' => api_success(['connected' => true, 'connection' => 'smtp']),
            'not_found' => api_error('not_found', 'Mail Accountが見つかりません。', 404),
            'disabled' => api_error('mail_account_disabled', 'Mail Accountが無効です。Account管理で有効にしてください。', 409),
            'smtp_disabled' => api_error('mail_smtp_disabled', 'このMail AccountではSMTP送信が無効です。', 409),
            'smtp_dependency_unavailable' => api_error('mail_smtp_dependency_unavailable', 'SMTP接続に必要なMail機能を利用できません。Server設定を確認してください。', 503),
            'smtp_credential_unavailable' => api_error('mail_smtp_credential_unavailable', 'SMTP認証情報を利用できません。Mail Account設定を確認してください。', 503),
            'invalid_smtp_host', 'invalid_smtp_transport', 'smtp_dns_failed', 'smtp_non_public_address'
                => api_validation_error(api_mail_validation_message($result['code'])),
            'smtp_rejected' => api_error('mail_smtp_rejected', 'SMTP Serverが接続または認証を拒否しました。SMTP設定と認証情報を確認してください。', 422),
            default => api_error('mail_smtp_connection_failed', 'SMTP Serverへ接続できませんでした。Host・Port・暗号化方式を確認してください。', 502),
        };
    }

    return match ($result['code']) {
        'connected' => api_success(['connected' => true]),
        'not_found' => api_error('not_found', 'Mail Accountが見つかりません。', 404),
        'disabled' => api_error('mail_account_disabled', 'Mail Accountが無効です。Account管理で有効にしてください。', 409),
        'dependency_unavailable' => api_error('mail_dependency_unavailable', 'IMAP接続に必要なMail機能を利用できません。Server設定を確認してください。', 503),
        'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail認証情報を利用できません。Mail Account設定を確認してください。', 503),
        'invalid_host', 'invalid_transport', 'dns_failed', 'non_public_address' => api_validation_error(api_mail_validation_message($result['code'])),
        'imap_rejected' => api_error('mail_imap_rejected', 'IMAP Serverが接続または認証を拒否しました。IMAP設定と認証情報を確認してください。', 422),
        default => api_error('mail_connection_failed', 'IMAP Serverへ接続できませんでした。Host・Port・暗号化方式を確認してください。', 502),
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
        return api_mail_storage_failure();
    } catch (Throwable $exception) {
        return api_mail_internal_failure('message.send', $userId, $exception);
    }

    $authFailure = api_mail_error_from_code((string) ($result['code'] ?? ''));
    if ($authFailure !== null) {
        return $authFailure;
    }

    return match ($result['code']) {
        'sent' => api_success([
            'sent' => true,
            'sent_save_status' => (string) ($result['sent_save_status'] ?? 'failed'),
            'sent_save_source' => (string) ($result['sent_save_source'] ?? 'none'),
            'sent_save_code' => (string) ($result['sent_save_code'] ?? ''),
            'sent_save_message' => api_mail_sent_save_message((string) ($result['sent_save_code'] ?? '')),
            'sent_folder' => (string) ($result['sent_folder'] ?? ''),
        ]),
        'not_found' => api_error('not_found', 'Mail Accountが見つかりません。', 404),
        'disabled' => api_error('mail_account_disabled', 'Mail Accountが無効です。Account管理で有効にしてください。', 409),
        'smtp_disabled' => api_error('mail_smtp_disabled', 'このMail AccountではSMTP送信が無効です。', 409),
        'smtp_dependency_unavailable' => api_error('mail_smtp_dependency_unavailable', 'SMTP送信に必要なMail機能を利用できません。Server設定を確認してください。', 503),
        'smtp_credential_unavailable' => api_error('mail_smtp_credential_unavailable', 'SMTP認証情報を利用できません。Mail Account設定を確認してください。', 503),
        'smtp_configuration_unavailable' => api_error('mail_smtp_configuration_unavailable', 'SMTPの差出人設定を利用できません。Mail Account設定を確認してください。', 409),
        'invalid_smtp_host', 'invalid_smtp_transport', 'smtp_dns_failed', 'smtp_non_public_address'
            => api_validation_error(api_mail_validation_message($result['code'])),
        'smtp_message_invalid' => api_validation_error('送信するMailの内容が正しくありません。'),
        'smtp_attachment_invalid' => api_validation_error('送信する添付ファイルが正しくありません。'),
        'smtp_rejected' => api_error('mail_smtp_rejected', 'SMTP Serverが接続または認証を拒否しました。SMTP設定と認証情報を確認してください。', 422),
        'smtp_send_failed' => api_error('mail_send_failed', 'Mailの送信結果を確認できません。重複送信を避けるため自動再試行はしないでください。', 502),
        default => api_error('mail_send_failed', 'Mailを送信できませんでした。', 502),
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
        return api_mail_storage_failure();
    } catch (Throwable $exception) {
        return api_mail_internal_failure('message.reply.context', $userId, $exception);
    }

    $authFailure = api_mail_error_from_code((string) ($result['code'] ?? ''));
    if ($authFailure !== null) {
        return $authFailure;
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
        'not_found', 'message_not_found' => api_error('not_found', '返信元Mailが見つかりません。Widgetを更新してください。', 404),
        'folder_changed' => api_error('mail_folder_changed', 'Folderが切り替わっています。Mail Widgetを更新してから再試行してください。', 409),
        'invalid_folder' => api_validation_error('Mail Folderが正しくありません。'),
        'disabled' => api_error('mail_account_disabled', 'Mail Accountが無効です。Account管理で有効にしてください。', 409),
        'smtp_disabled' => api_error('mail_smtp_disabled', 'このMail AccountではSMTP送信が無効です。', 409),
        'dependency_unavailable' => api_error('mail_dependency_unavailable', '返信準備に必要なMail機能を利用できません。Server設定を確認してください。', 503),
        'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail認証情報を利用できません。Mail Account設定を確認してください。', 503),
        'invalid_host', 'invalid_transport', 'dns_failed', 'non_public_address'
            => api_validation_error(api_mail_validation_message($result['code'])),
        'no_reply_address' => api_error('mail_reply_address_unavailable', '返信先として使用できるReply-ToまたはFrom Addressがありません。', 422),
        'imap_rejected' => api_error('mail_imap_rejected', 'IMAP Serverが接続または認証を拒否しました。IMAP設定と認証情報を確認してください。', 422),
        default => api_error('mail_connection_failed', '返信元Mailを取得できませんでした。IMAP接続を確認してください。', 502),
    };
}
