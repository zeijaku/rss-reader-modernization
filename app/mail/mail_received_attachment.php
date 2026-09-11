<?php

declare(strict_types=1);

const MAIL_RECEIVED_ATTACHMENT_MAX_COUNT = 50;
const MAIL_RECEIVED_ATTACHMENT_MAX_DOWNLOAD_BYTES = 26214400; // 25 MiB decoded content
const MAIL_RECEIVED_ATTACHMENT_MAX_TRANSFER_BYTES = 83886080; // 80 MiB transfer-encoded safety cap

function mail_received_attachment_validate_part_id(mixed $value): ?string
{
    $partId = is_string($value) ? trim($value) : '';
    if ($partId === '' || strlen($partId) > 64 || preg_match('/^[1-9][0-9]*(?:\.[1-9][0-9]*)*$/D', $partId) !== 1) {
        return null;
    }
    return $partId;
}

function mail_received_attachment_safe_filename(mixed $value): string
{
    $name = is_string($value) && app_is_valid_utf8($value) ? trim($value) : '';
    if ($name !== '') {
        $name = trim((string) preg_replace('~[\x00-\x1F\x7F\\/]+~u', '_', $name));
    }
    if ($name === '' || $name === '.' || $name === '..') {
        return 'attachment';
    }
    return mail_message_truncate_utf8($name, 255);
}

function mail_received_attachment_safe_content_type(mixed $value): string
{
    $type = is_string($value) ? strtolower(trim($value)) : '';
    if ($type === '' || strlen($type) > 127 || preg_match('~^[a-z0-9!#$&^_.+\-]+/[a-z0-9!#$&^_.+\-]+$~D', $type) !== 1) {
        return 'application/octet-stream';
    }
    return $type;
}

function mail_received_attachment_part_is_file(object $part): bool
{
    try {
        if (method_exists($part, 'isAttachment') && $part->isAttachment()) {
            return true;
        }
    } catch (Throwable) {
    }

    try {
        if (method_exists($part, 'isInline') && $part->isInline()) {
            $filename = method_exists($part, 'filename') ? $part->filename() : null;
            return is_string($filename) && trim($filename) !== '';
        }
    } catch (Throwable) {
    }

    return false;
}

/** @return array{part_id:string,name:string,size:int|null,content_type:string,downloadable:bool}|null */
function mail_received_attachment_metadata_from_part(object $part): ?array
{
    if (!mail_received_attachment_part_is_file($part) || !method_exists($part, 'partNumber')) {
        return null;
    }

    try {
        $partId = mail_received_attachment_validate_part_id((string) $part->partNumber());
    } catch (Throwable) {
        return null;
    }
    if ($partId === null) {
        return null;
    }

    $filename = null;
    try {
        $filename = method_exists($part, 'filename') ? $part->filename() : null;
    } catch (Throwable) {
        $filename = null;
    }
    if (!is_string($filename) || trim($filename) === '') {
        try {
            $filename = method_exists($part, 'name') ? $part->name() : null;
        } catch (Throwable) {
            $filename = null;
        }
    }

    $transferSize = null;
    try {
        $rawSize = method_exists($part, 'size') ? $part->size() : null;
        if (is_int($rawSize) && $rawSize >= 0) {
            $transferSize = $rawSize;
        } elseif (is_string($rawSize) && preg_match('/^[0-9]+$/D', $rawSize) === 1) {
            $transferSize = (int) $rawSize;
        }
    } catch (Throwable) {
        $transferSize = null;
    }

    $encoding = '';
    try {
        $rawEncoding = method_exists($part, 'encoding') ? $part->encoding() : null;
        $encoding = is_string($rawEncoding) ? strtolower(trim($rawEncoding)) : '';
    } catch (Throwable) {
        $encoding = '';
    }
    $identityEncoding = $encoding === '' || in_array($encoding, ['7bit', '8bit', 'binary'], true);
    $displaySize = $identityEncoding ? $transferSize : null;
    $transferLimit = $identityEncoding
        ? MAIL_RECEIVED_ATTACHMENT_MAX_DOWNLOAD_BYTES
        : MAIL_RECEIVED_ATTACHMENT_MAX_TRANSFER_BYTES;

    $contentType = null;
    try {
        $contentType = method_exists($part, 'contentType') ? $part->contentType() : null;
    } catch (Throwable) {
        $contentType = null;
    }

    return [
        'part_id' => $partId,
        'name' => mail_received_attachment_safe_filename($filename),
        'size' => $displaySize,
        'content_type' => mail_received_attachment_safe_content_type($contentType),
        'downloadable' => $transferSize === null || $transferSize <= $transferLimit,
    ];
}

/** @return list<array{part_id:string,name:string,size:int|null,content_type:string,downloadable:bool}> */
function mail_received_attachment_list_from_structure(mixed $structure): array
{
    if (!$structure instanceof DirectoryTree\ImapEngine\BodyStructureCollection) {
        return [];
    }

    $items = [];
    foreach ($structure->flatten() as $part) {
        if (!is_object($part)) {
            continue;
        }
        $metadata = mail_received_attachment_metadata_from_part($part);
        if ($metadata === null) {
            continue;
        }
        $items[] = $metadata;
        if (count($items) >= MAIL_RECEIVED_ATTACHMENT_MAX_COUNT) {
            break;
        }
    }
    return $items;
}

function mail_received_attachment_decode(string $raw, mixed $encoding): ?string
{
    $name = strtolower(trim(is_string($encoding) ? $encoding : ''));
    if ($name === '' || in_array($name, ['7bit', '8bit', 'binary'], true)) {
        return $raw;
    }
    if ($name === 'base64') {
        $decoded = base64_decode(preg_replace('/\s+/', '', $raw) ?? $raw, true);
        return is_string($decoded) ? $decoded : null;
    }
    if (in_array($name, ['quoted-printable', 'quotedprintable'], true)) {
        return quoted_printable_decode($raw);
    }
    return null;
}

/**
 * @param array{host:mixed,port:mixed,encryption:mixed,username:mixed} $account
 * @return array{ok:bool,code:string,attachments?:list<array<string,mixed>>,folder?:string,content?:string,name?:string,size?:int,content_type?:string}
 */
function mail_received_attachment_read(
    array $account,
    string $password,
    int $uid,
    string $folderPath,
    ?string $wantedPartId = null,
    ?callable $resolver = null
): array {
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return ['ok' => false, 'code' => 'dependency_unavailable'];
    }
    if ($uid <= 0 || $password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }
    $folder = mail_widget_validate_folder($folderPath);
    if ($folder === null) {
        return ['ok' => false, 'code' => 'invalid_folder'];
    }
    if ($wantedPartId !== null && mail_received_attachment_validate_part_id($wantedPartId) === null) {
        return ['ok' => false, 'code' => 'invalid_attachment'];
    }

    $target = mail_validate_target(
        $account['host'] ?? null,
        $account['port'] ?? null,
        $account['encryption'] ?? null,
        $resolver
    );
    if (!$target['ok']) {
        return ['ok' => false, 'code' => $target['error_code']];
    }
    $username = mail_account_validate_username($account['username'] ?? null);

    foreach ($target['ips'] as $ip) {
        $mailbox = null;
        try {
            $mailbox = new DirectoryTree\ImapEngine\Mailbox([
                'host' => $target['host'],
                'port' => $target['port'],
                'timeout' => (int) APP_MAIL_IMAP_TIMEOUT_SECONDS,
                'debug' => false,
                'username' => $username,
                'password' => $password,
                'encryption' => $target['encryption'],
                'validate_cert' => true,
                'authentication' => 'plain',
            ]);
            $stream = new AppMailPinnedImapStream($target['host'], $ip);
            $connection = new DirectoryTree\ImapEngine\Connection\ImapConnection($stream, null);
            $mailbox->connect($connection);
            $mailbox->connection()->examine($folder);

            $imapFolder = new DirectoryTree\ImapEngine\Folder($mailbox, $folder);
            $query = new DirectoryTree\ImapEngine\MessageQuery(
                $imapFolder,
                new DirectoryTree\ImapEngine\Connection\ImapQueryBuilder()
            );
            $message = $query
                ->withBodyStructure()
                ->leaveUnread()
                ->find($uid);
            if (!$message instanceof DirectoryTree\ImapEngine\MessageInterface) {
                $mailbox->disconnect();
                return ['ok' => false, 'code' => 'message_not_found'];
            }

            $structure = $message->bodyStructure();
            $attachments = mail_received_attachment_list_from_structure($structure);
            if ($wantedPartId === null) {
                $mailbox->disconnect();
                return ['ok' => true, 'code' => 'loaded', 'attachments' => $attachments, 'folder' => $folder];
            }

            $metadata = null;
            $partObject = null;
            if ($structure instanceof DirectoryTree\ImapEngine\BodyStructureCollection) {
                foreach ($structure->flatten() as $part) {
                    if (!is_object($part)) {
                        continue;
                    }
                    $candidate = mail_received_attachment_metadata_from_part($part);
                    if ($candidate !== null && hash_equals($candidate['part_id'], $wantedPartId)) {
                        $metadata = $candidate;
                        $partObject = $part;
                        break;
                    }
                }
            }
            if ($metadata === null || $partObject === null) {
                $mailbox->disconnect();
                return ['ok' => false, 'code' => 'attachment_not_found'];
            }
            if (($metadata['downloadable'] ?? false) !== true) {
                $mailbox->disconnect();
                return ['ok' => false, 'code' => 'attachment_too_large'];
            }

            $raw = $message->bodyPart($wantedPartId, true);
            if (!is_string($raw)) {
                $mailbox->disconnect();
                return ['ok' => false, 'code' => 'attachment_fetch_failed'];
            }
            $encoding = null;
            try {
                $encoding = method_exists($partObject, 'encoding') ? $partObject->encoding() : null;
            } catch (Throwable) {
                $encoding = null;
            }
            $content = mail_received_attachment_decode($raw, $encoding);
            if ($content === null) {
                $mailbox->disconnect();
                return ['ok' => false, 'code' => 'attachment_decode_failed'];
            }
            if (strlen($content) > MAIL_RECEIVED_ATTACHMENT_MAX_DOWNLOAD_BYTES) {
                $mailbox->disconnect();
                return ['ok' => false, 'code' => 'attachment_too_large'];
            }

            $mailbox->disconnect();
            return [
                'ok' => true,
                'code' => 'downloaded',
                'content' => $content,
                'name' => $metadata['name'],
                'size' => strlen($content),
                'content_type' => $metadata['content_type'],
                'folder' => $folder,
            ];
        } catch (Throwable $exception) {
            if ($mailbox instanceof DirectoryTree\ImapEngine\Mailbox) {
                try {
                    $mailbox->disconnect();
                } catch (Throwable) {
                }
            }
            if (is_a($exception, DirectoryTree\ImapEngine\Exceptions\ImapCommandException::class)) {
                return ['ok' => false, 'code' => 'imap_rejected'];
            }
        }
    }

    return ['ok' => false, 'code' => 'connection_failed'];
}

/** @return array{ok:bool,code:string,attachments?:list<array<string,mixed>>,folder?:string,content?:string,name?:string,size?:int,content_type?:string} */
function mail_received_attachment_for_user(
    int $ownerId,
    int $widgetId,
    int $uid,
    string $requestedFolder,
    ?string $partId = null
): array {
    if ($ownerId <= 0 || $widgetId <= 0 || $uid <= 0) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null) {
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

    try {
        $password = mail_crypto_decrypt($ownerId, $accountId, (string) ($account['mail_account_secret'] ?? ''));
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }

    try {
        return mail_received_attachment_read([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password, $uid, $folderToRead, $partId);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_received_attachment_list(int $ownerId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $uid = api_positive_int($input, 'mail_uid');
    $folder = isset($input['mail_folder']) && is_string($input['mail_folder']) ? $input['mail_folder'] : '';
    if ($widgetId === null || $uid === null || mail_widget_validate_folder($folder) === null) {
        return api_validation_error('widget_id, mail_uid, and mail_folder are invalid.');
    }

    $result = mail_received_attachment_for_user($ownerId, $widgetId, $uid, $folder);
    return match ($result['code'] ?? '') {
        'loaded' => api_success([
            'attachments' => $result['attachments'] ?? [],
            'attachment_count' => count($result['attachments'] ?? []),
            'folder' => $result['folder'] ?? $folder,
        ]),
        'not_found', 'message_not_found' => api_error('not_found', 'Mail message was not found.', 404),
        'folder_changed' => api_error('mail_folder_changed', 'Mail folder changed. Refresh the Widget and try again.', 409),
        'invalid_folder', 'invalid_attachment' => api_validation_error('Mail attachment request is invalid.'),
        'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
        'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
        'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
        'invalid_host', 'invalid_transport', 'dns_failed', 'non_public_address'
            => api_validation_error(api_mail_validation_message((string) $result['code'])),
        'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
        default => api_error('mail_connection_failed', 'Could not load Mail attachments.', 502),
    };
}

function mail_received_attachment_download_ascii_filename(string $name): string
{
    $fallback = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?? '';
    $fallback = trim($fallback, " .\t\r\n");
    if ($fallback === '' || $fallback === '.' || $fallback === '..') {
        return 'attachment';
    }
    return substr($fallback, 0, 120);
}

function mail_received_attachment_download_emit(int $ownerId, array $input): never
{
    $widgetId = api_positive_int($input, 'widget_id');
    $uid = api_positive_int($input, 'mail_uid');
    $folder = isset($input['mail_folder']) && is_string($input['mail_folder']) ? $input['mail_folder'] : '';
    $partId = mail_received_attachment_validate_part_id($input['part_id'] ?? null);
    if ($widgetId === null || $uid === null || mail_widget_validate_folder($folder) === null || $partId === null) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        app_send_no_store_headers();
        echo 'Mail attachment request is invalid.';
        exit;
    }

    try {
        $result = mail_received_attachment_for_user($ownerId, $widgetId, $uid, $folder, $partId);
    } catch (Throwable $exception) {
        error_log(sprintf('Mail attachment download failure user_id=%d class=%s', $ownerId, $exception::class));
        $result = ['ok' => false, 'code' => 'internal_error'];
    }

    if (($result['ok'] ?? false) !== true || ($result['code'] ?? '') !== 'downloaded') {
        $status = match ($result['code'] ?? '') {
            'not_found', 'message_not_found', 'attachment_not_found' => 404,
            'folder_changed', 'disabled' => 409,
            'dependency_unavailable', 'credential_unavailable' => 503,
            'attachment_too_large' => 413,
            'invalid_folder', 'invalid_attachment' => 400,
            'imap_rejected' => 422,
            'internal_error' => 500,
            default => 502,
        };
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        app_send_no_store_headers();
        echo ($result['code'] ?? '') === 'attachment_too_large'
            ? 'This attachment is too large to download in the Mail Widget.'
            : 'Mail attachment download failed.';
        exit;
    }

    $content = isset($result['content']) && is_string($result['content']) ? $result['content'] : '';
    $name = mail_received_attachment_safe_filename($result['name'] ?? 'attachment');
    $type = mail_received_attachment_safe_content_type($result['content_type'] ?? null);
    $ascii = mail_received_attachment_download_ascii_filename($name);

    http_response_code(200);
    header('Content-Type: ' . $type);
    header('Content-Length: ' . strlen($content));
    header('Content-Disposition: attachment; filename="' . addcslashes($ascii, "\\\"") . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    app_send_no_store_headers();
    echo $content;
    exit;
}
