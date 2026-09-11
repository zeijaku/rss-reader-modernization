<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_message.php';
require_once __DIR__ . '/mail_attachment.php';
require_once __DIR__ . '/mail_reply.php';
require_once __DIR__ . '/mail_sent.php';
require_once __DIR__ . '/mail_smtp_client.php';

/** @return list<array<string,mixed>> */
function mail_service_list_accounts(int $ownerId): array
{
    return mail_account_list($ownerId);
}

/** @return array<string,mixed> */
function mail_service_create_account(int $ownerId, array $input): array
{
    return mail_account_create($ownerId, $input);
}

/** @return array<string,mixed>|null */
function mail_service_update_account(int $ownerId, int $accountId, array $input): ?array
{
    return mail_account_update($ownerId, $accountId, $input);
}

function mail_service_delete_account(int $ownerId, int $accountId): bool
{
    return mail_account_delete($ownerId, $accountId);
}

/** @return array{ok:bool,code:string} */
function mail_service_test_account(int $ownerId, int $accountId): array
{
    $account = mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'disabled'];
    }

    try {
        $password = mail_crypto_decrypt(
            $ownerId,
            $accountId,
            (string) ($account['mail_account_secret'] ?? '')
        );
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }

    try {
        return mail_client_test_credentials([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/** @return array{ok:bool,code:string} */
function mail_service_test_smtp_account(int $ownerId, int $accountId): array
{
    $account = mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'disabled'];
    }
    if ((int) ($account['mail_account_smtp_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'smtp_disabled'];
    }

    $useImap = (int) ($account['mail_account_smtp_use_imap_credentials'] ?? 1) === 1;
    $username = $useImap
        ? (string) ($account['mail_account_username'] ?? '')
        : (string) ($account['mail_account_smtp_username'] ?? '');

    try {
        $password = $useImap
            ? mail_crypto_decrypt($ownerId, $accountId, (string) ($account['mail_account_secret'] ?? ''))
            : mail_crypto_decrypt_smtp($ownerId, $accountId, (string) ($account['mail_account_smtp_secret'] ?? ''));
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'smtp_credential_unavailable'];
    }

    try {
        return mail_smtp_client_test_credentials([
            'host' => $account['mail_account_smtp_host'] ?? null,
            'port' => $account['mail_account_smtp_port'] ?? null,
            'encryption' => $account['mail_account_smtp_encryption'] ?? null,
        ], $username, $password);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/**
 * @return array{ok:bool,code:string,sent_save_status?:string,sent_save_source?:string,sent_save_code?:string,sent_folder?:string}
 */
function mail_service_send_plain_text(int $ownerId, int $accountId, array $input, array $files = []): array
{
    $message = mail_message_from_input($input);
    $attachments = mail_attachment_from_files($files);
    $replyMessageId = '';
    if (array_key_exists('in_reply_to', $input) && $input['in_reply_to'] !== null && $input['in_reply_to'] !== '') {
        $replyMessageId = mail_message_normalize_message_id($input['in_reply_to']);
        if ($replyMessageId === '') {
            throw new AppMailValidationException('invalid_reply_reference');
        }
    }
    $account = mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'disabled'];
    }
    if ((int) ($account['mail_account_smtp_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'smtp_disabled'];
    }

    $fromAddress = (string) ($account['mail_account_from_address'] ?? '');
    $fromName = (string) ($account['mail_account_from_name'] ?? '');
    try {
        $fromAddress = mail_account_validate_from_address($fromAddress);
        $fromName = mail_account_validate_from_name($fromName) ?? '';
        $sentSaveMode = mail_account_validate_sent_save_mode($account['mail_account_sent_save_mode'] ?? null);
    } catch (AppMailValidationException) {
        return ['ok' => false, 'code' => 'smtp_configuration_unavailable'];
    }

    $useImap = (int) ($account['mail_account_smtp_use_imap_credentials'] ?? 1) === 1;
    $username = $useImap
        ? (string) ($account['mail_account_username'] ?? '')
        : (string) ($account['mail_account_smtp_username'] ?? '');

    try {
        $smtpPassword = $useImap
            ? mail_crypto_decrypt($ownerId, $accountId, (string) ($account['mail_account_secret'] ?? ''))
            : mail_crypto_decrypt_smtp($ownerId, $accountId, (string) ($account['mail_account_smtp_secret'] ?? ''));
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'smtp_credential_unavailable'];
    }

    try {
        $sendResult = mail_smtp_client_send_plain_text([
            'host' => $account['mail_account_smtp_host'] ?? null,
            'port' => $account['mail_account_smtp_port'] ?? null,
            'encryption' => $account['mail_account_smtp_encryption'] ?? null,
        ], $username, $smtpPassword, $fromAddress, $fromName, $message['to'], $message['subject'], $message['body'], null, $replyMessageId, $attachments);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($smtpPassword);
        }
    }

    if (($sendResult['ok'] ?? false) !== true || ($sendResult['code'] ?? '') !== 'sent') {
        return $sendResult;
    }

    // Server mode intentionally performs no IMAP operation after SMTP success.
    if ($sentSaveMode === 'server') {
        return [
            'ok' => true,
            'code' => 'sent',
            'sent_save_status' => 'server_managed',
            'sent_save_source' => 'server',
        ];
    }

    $messageId = isset($sendResult['message_id']) && is_string($sendResult['message_id'])
        ? $sendResult['message_id']
        : '';
    $mimeMessage = isset($sendResult['mime_message']) && is_string($sendResult['mime_message'])
        ? $sendResult['mime_message']
        : '';
    if ($messageId === '' || $mimeMessage === '') {
        return [
            'ok' => true,
            'code' => 'sent',
            'sent_save_status' => 'failed',
            'sent_save_source' => 'none',
            'sent_save_code' => 'message_unavailable',
        ];
    }

    // Sent storage always uses IMAP credentials even when SMTP has a separate
    // username/password. Decrypt only after a known successful SMTP send.
    try {
        $imapPassword = mail_crypto_decrypt(
            $ownerId,
            $accountId,
            (string) ($account['mail_account_secret'] ?? '')
        );
    } catch (AppMailCredentialException) {
        return [
            'ok' => true,
            'code' => 'sent',
            'sent_save_status' => 'failed',
            'sent_save_source' => 'none',
            'sent_save_code' => 'credential_unavailable',
        ];
    }

    try {
        $sentResult = mail_sent_store_after_send([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $imapPassword, $sentSaveMode, $messageId, $mimeMessage);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($imapPassword);
        }
    }

    if (($sentResult['ok'] ?? false) === true) {
        return [
            'ok' => true,
            'code' => 'sent',
            'sent_save_status' => ($sentResult['code'] ?? '') === 'server_managed' ? 'server_managed' : 'saved',
            'sent_save_source' => (string) ($sentResult['source'] ?? 'none'),
            'sent_folder' => (string) ($sentResult['folder'] ?? ''),
        ];
    }

    return [
        'ok' => true,
        'code' => 'sent',
        'sent_save_status' => 'failed',
        'sent_save_source' => 'none',
        'sent_save_code' => (string) ($sentResult['code'] ?? 'sent_save_failed'),
    ];
}

/** @return array{ok:bool,code:string,to?:string,to_name?:string,subject?:string,message_id?:string,recipient_source?:string,folder?:string,mail_account_id?:int} */
function mail_service_reply_context(int $ownerId, int $widgetId, int $uid, string $requestedFolder): array
{
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null || $uid <= 0) {
        return ['ok' => false, 'code' => 'not_found'];
    }

    $folder = mail_widget_validate_folder($requestedFolder);
    if ($folder === null) {
        return ['ok' => false, 'code' => 'invalid_folder'];
    }
    $config = mail_widget_config_from_storage($widget['widget_config'] ?? null);
    $storedFolder = $config['folder'];
    $sameFolder = $folder === $storedFolder
        || (strcasecmp($folder, 'INBOX') === 0 && strcasecmp($storedFolder, 'INBOX') === 0);
    if (!$sameFolder) {
        return ['ok' => false, 'code' => 'folder_changed'];
    }
    $folderToRead = strcasecmp($storedFolder, 'INBOX') === 0 ? $folder : $storedFolder;

    $accountId = app_validate_positive_int($widget['widget_reference_id'] ?? null);
    $account = $accountId === null ? null : mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null || $accountId === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'disabled'];
    }
    if ((int) ($account['mail_account_smtp_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'smtp_disabled'];
    }

    try {
        $password = mail_crypto_decrypt(
            $ownerId,
            $accountId,
            (string) ($account['mail_account_secret'] ?? '')
        );
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }

    try {
        $result = mail_reply_read_context([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password, $uid, $folderToRead);
        if (($result['ok'] ?? false) !== true) {
            return $result;
        }
        $result['mail_account_id'] = $accountId;
        return $result;
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

