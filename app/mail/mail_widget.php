<?php

declare(strict_types=1);

function mail_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

function mail_widget_validate_item_limit(mixed $value): ?int
{
    $limit = app_validate_positive_int($value);
    return $limit !== null && in_array($limit, [5, 10], true) ? $limit : null;
}

/** @return array{schema:int,title:string,item_limit:int}|null */
function mail_widget_config_from_input(array $input): ?array
{
    $title = mail_widget_validate_title($input['mail_title'] ?? null);
    $limit = mail_widget_validate_item_limit($input['mail_item_limit'] ?? null);
    if ($title === null || $limit === null) {
        return null;
    }
    return ['schema' => 1, 'title' => $title, 'item_limit' => $limit];
}

/** @return array{schema:int,title:string,item_limit:int} */
function mail_widget_config_from_storage(mixed $value, string $fallbackTitle = 'Mail'): array
{
    $config = dashboard_widget_decode_config($value);
    $title = mail_widget_validate_title($config['title'] ?? null);
    $limit = mail_widget_validate_item_limit($config['item_limit'] ?? null);
    $fallback = mail_widget_validate_title($fallbackTitle) ?? 'Mail';
    return ['schema' => 1, 'title' => $title ?? $fallback, 'item_limit' => $limit ?? 5];
}

function mail_widget_safe_text(mixed $value, int $maxLength, string $fallback = ''): string
{
    $text = is_string($value) ? $value : '';
    if (!app_is_valid_utf8($text)) {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            $text = is_string($converted) ? $converted : '';
        } else {
            $text = '';
        }
    }
    $text = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text));
    if ($text === '') {
        return $fallback;
    }
    if (app_text_length($text) <= $maxLength) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }
    return substr($text, 0, $maxLength);
}

/** @return array{ok:bool,code:string,messages:list<array<string,mixed>>} */
function mail_widget_read_latest(array $account, string $password, int $limit, ?callable $resolver = null): array
{
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return ['ok' => false, 'code' => 'dependency_unavailable', 'messages' => []];
    }
    if (!in_array($limit, [5, 10], true) || $password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'credential_unavailable', 'messages' => []];
    }

    $target = mail_validate_target(
        $account['host'] ?? null,
        $account['port'] ?? null,
        $account['encryption'] ?? null,
        $resolver
    );
    if (!$target['ok']) {
        return ['ok' => false, 'code' => $target['error_code'], 'messages' => []];
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

            // Keep INBOX read-only. MessageQuery is created directly so Folder::messages()
            // cannot issue SELECT after this EXAMINE command.
            $mailbox->connection()->examine('INBOX');
            $folder = new DirectoryTree\ImapEngine\Folder($mailbox, 'INBOX');
            $query = new DirectoryTree\ImapEngine\MessageQuery(
                $folder,
                new DirectoryTree\ImapEngine\Connection\ImapQueryBuilder()
            );
            $collection = $query
                ->withHeaders()
                ->withFlags()
                ->leaveUnread()
                ->newest()
                ->limit($limit)
                ->get();

            $messages = [];
            foreach ($collection as $message) {
                if (!$message instanceof DirectoryTree\ImapEngine\MessageInterface) {
                    continue;
                }
                $from = $message->from();
                $fromName = $from instanceof DirectoryTree\ImapEngine\Address
                    ? mail_widget_safe_text($from->name(), 128)
                    : '';
                $fromEmail = $from instanceof DirectoryTree\ImapEngine\Address
                    ? mail_widget_safe_text($from->email(), 320)
                    : '';
                $date = $message->date();
                $messages[] = [
                    'uid' => $message->uid(),
                    'from_name' => $fromName,
                    'from_email' => $fromEmail,
                    'from' => $fromName !== '' ? $fromName : ($fromEmail !== '' ? $fromEmail : '送信者不明'),
                    'subject' => mail_widget_safe_text($message->subject(), 512, '件名なし'),
                    'date' => $date === null ? '' : $date->toIso8601String(),
                    'unread' => !$message->isSeen(),
                ];
            }
            $mailbox->disconnect();
            return ['ok' => true, 'code' => 'loaded', 'messages' => $messages];
        } catch (Throwable $exception) {
            if ($mailbox instanceof DirectoryTree\ImapEngine\Mailbox) {
                $mailbox->disconnect();
            }
            if (is_a($exception, DirectoryTree\ImapEngine\Exceptions\ImapCommandException::class)) {
                return ['ok' => false, 'code' => 'imap_rejected', 'messages' => []];
            }
        }
    }

    return ['ok' => false, 'code' => 'connection_failed', 'messages' => []];
}

function mail_widget_safe_body_text(mixed $value, int $maxChars = 20000): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }

    $text = str_replace(["\r\n", "\r"], "\n", $value);
    if (!app_is_valid_utf8($text)) {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            $text = is_string($converted) ? $converted : '';
        } else {
            $text = '';
        }
    }
    $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = (string) preg_replace('/[ \t]+\n/u', "\n", $text);
    $text = trim($text);
    if ($text === '' || app_text_length($text) <= $maxChars) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxChars, 'UTF-8');
    }
    if (function_exists('iconv_substr')) {
        $truncated = iconv_substr($text, 0, $maxChars, 'UTF-8');
        if (is_string($truncated)) {
            return $truncated;
        }
    }
    return substr($text, 0, $maxChars);
}

/** @return array{ok:bool,code:string,uid:int,body:string,truncated:bool} */
function mail_widget_read_message_text(array $account, string $password, int $uid, ?callable $resolver = null): array
{
    $empty = ['ok' => false, 'code' => 'connection_failed', 'uid' => $uid, 'body' => '', 'truncated' => false];
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return array_merge($empty, ['code' => 'dependency_unavailable']);
    }
    if ($uid <= 0 || $password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return array_merge($empty, ['code' => 'credential_unavailable']);
    }

    $target = mail_validate_target(
        $account['host'] ?? null,
        $account['port'] ?? null,
        $account['encryption'] ?? null,
        $resolver
    );
    if (!$target['ok']) {
        return array_merge($empty, ['code' => $target['error_code']]);
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

            // V1.9-D: body preview is always read-only. Fetch only BODYSTRUCTURE,
            // then the text/plain MIME part with BODY.PEEK so opening a preview
            // cannot add the \\Seen flag on the IMAP server.
            $mailbox->connection()->examine('INBOX');
            $folder = new DirectoryTree\ImapEngine\Folder($mailbox, 'INBOX');
            $query = new DirectoryTree\ImapEngine\MessageQuery(
                $folder,
                new DirectoryTree\ImapEngine\Connection\ImapQueryBuilder()
            );
            $message = $query
                ->withBodyStructure()
                ->withFlags()
                ->leaveUnread()
                ->find($uid);
            if (!$message instanceof DirectoryTree\ImapEngine\MessageInterface) {
                $mailbox->disconnect();
                return array_merge($empty, ['code' => 'message_not_found']);
            }

            $structure = $message->bodyStructure();
            $part = null;
            if ($structure instanceof DirectoryTree\ImapEngine\BodyStructureCollection) {
                foreach ($structure->flatten() as $candidate) {
                    if ($candidate->isText() && !$candidate->isAttachment()) {
                        $part = $candidate;
                        break;
                    }
                }
            }
            if (!$part instanceof DirectoryTree\ImapEngine\BodyStructurePart) {
                $mailbox->disconnect();
                return ['ok' => true, 'code' => 'no_plain_text', 'uid' => $uid, 'body' => '', 'truncated' => false];
            }

            // Avoid pulling unexpectedly large message bodies into one request.
            $partSize = $part->size();
            if (is_int($partSize) && $partSize > 262144) {
                $mailbox->disconnect();
                return array_merge($empty, ['code' => 'body_too_large']);
            }

            $raw = $message->bodyPart($part->partNumber(), true);
            $decoded = DirectoryTree\ImapEngine\Support\BodyPartDecoder::text($part, $raw);
            $safe = mail_widget_safe_body_text($decoded, 20000);
            $truncated = is_string($decoded) && app_text_length(trim($decoded)) > app_text_length($safe);
            $mailbox->disconnect();
            return ['ok' => true, 'code' => 'loaded', 'uid' => $uid, 'body' => $safe, 'truncated' => $truncated];
        } catch (Throwable $exception) {
            if ($mailbox instanceof DirectoryTree\ImapEngine\Mailbox) {
                $mailbox->disconnect();
            }
            if (is_a($exception, DirectoryTree\ImapEngine\Exceptions\ImapCommandException::class)) {
                return array_merge($empty, ['code' => 'imap_rejected']);
            }
        }
    }

    return $empty;
}

/** @return array<string,mixed>|null */
function mail_widget_find_owned(int $ownerId, int $widgetId): ?array
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return null;
    }
    $stmt = conn_db()->prepare(
        'SELECT w.* FROM ' . db_table_identifier('dashboard_widget') . ' w '
        . "WHERE w.widget_id = :widget_id AND w.widget_owner = :owner AND w.widget_type = 'mail' AND w.widget_flag = 0 LIMIT 1"
    );
    $stmt->execute([':widget_id' => $widgetId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** @return list<array<string,mixed>> */
function mail_widget_list(int $ownerId, int $location): array
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null) {
        return [];
    }
    $stmt = conn_db()->prepare(
        'SELECT w.*, a.mail_account_display_name, a.mail_account_enabled '
        . 'FROM ' . db_table_identifier('dashboard_widget') . ' w '
        . 'INNER JOIN ' . mail_account_table_identifier() . ' a '
        . 'ON a.mail_account_id = w.widget_reference_id AND a.mail_account_owner = w.widget_owner '
        . "WHERE w.widget_owner = :owner AND w.widget_location = :location AND w.widget_type = 'mail' "
        . 'AND w.widget_flag = 0 AND a.mail_account_flag = 0 '
        . 'ORDER BY w.widget_sort_order ASC, w.widget_id ASC'
    );
    $stmt->execute([':owner' => $ownerId, ':location' => $location]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $widgetId = app_validate_positive_int($row['widget_id'] ?? null);
        $accountId = app_validate_positive_int($row['widget_reference_id'] ?? null);
        $width = dashboard_widget_validate_width($row['widget_width'] ?? null);
        $height = dashboard_widget_validate_height($row['widget_height'] ?? 1);
        $style = app_normalize_content_style($row['widget_style'] ?? null);
        $sort = dashboard_widget_non_negative_int($row['widget_sort_order'] ?? null);
        if ($widgetId === null || $accountId === null || $width === null || $height === null || $style === null || $sort === null) {
            continue;
        }
        $accountName = mail_widget_safe_text($row['mail_account_display_name'] ?? '', 128, 'Mail');
        $result[] = [
            'widget_id' => $widgetId,
            'mail_account_id' => $accountId,
            'account_name' => $accountName,
            'account_enabled' => (int) ($row['mail_account_enabled'] ?? 0) === 1,
            'widget_location' => $location,
            'widget_sort_order' => $sort,
            'widget_width' => $width,
            'widget_height' => $height,
            'widget_style' => $style,
            'widget_config' => mail_widget_config_from_storage($row['widget_config'] ?? null, $accountName),
        ];
    }
    return $result;
}

function mail_widget_create(int $ownerId, int $accountId, int $location, string $style, int $width, int $height, array $config): int
{
    if ($ownerId <= 0 || $accountId <= 0 || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null || mail_widget_config_from_input([
            'mail_title' => $config['title'] ?? null,
            'mail_item_limit' => $config['item_limit'] ?? null,
        ]) === null || mail_account_find_owned($ownerId, $accountId, false, false) === null) {
        throw new InvalidArgumentException('Mail Widget settings are invalid.');
    }

    $pdo = conn_db();
    $existing = $pdo->prepare(
        'SELECT widget_id, widget_flag FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_owner = :owner AND widget_type = 'mail' AND widget_reference_id = :reference_id LIMIT 1"
    );
    $existing->execute([':owner' => $ownerId, ':reference_id' => $accountId]);
    $row = $existing->fetch();
    if (is_array($row) && (int) ($row['widget_flag'] ?? 1) === 0) {
        throw new LengthException('A Mail Widget already exists for this account.');
    }

    $now = app_now();
    $sort = dashboard_widget_next_sort_order($pdo, $ownerId, $location);
    $encoded = dashboard_widget_encode_config($config);
    if (is_array($row)) {
        $widgetId = (int) $row['widget_id'];
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' SET widget_location = :location, widget_sort_order = :sort_order, '
            . 'widget_width = :width, widget_height = :height, widget_style = :style, widget_config = :config, widget_flag = 0, widget_updated_at = :updated_at '
            . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'mail'"
        );
        $stmt->execute([':location' => $location, ':sort_order' => $sort, ':width' => $width, ':height' => $height, ':style' => $style,
            ':config' => $encoded, ':updated_at' => $now, ':widget_id' => $widgetId, ':owner' => $ownerId]);
        return $widgetId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ' . db_table_identifier('dashboard_widget') . ' '
        . '(widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
        . "VALUES (:owner, :location, 'mail', :reference_id, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
    );
    $stmt->execute([':owner' => $ownerId, ':location' => $location, ':reference_id' => $accountId, ':sort_order' => $sort,
        ':width' => $width, ':height' => $height, ':style' => $style, ':config' => $encoded, ':created_at' => $now, ':updated_at' => $now]);
    return (int) $pdo->lastInsertId();
}

function mail_widget_update(int $ownerId, int $widgetId, int $accountId, string $style, int $width, int $height, array $config): bool
{
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null || $accountId <= 0 || mail_account_find_owned($ownerId, $accountId, false, false) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null || mail_widget_config_from_input([
            'mail_title' => $config['title'] ?? null,
            'mail_item_limit' => $config['item_limit'] ?? null,
        ]) === null) {
        return false;
    }
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_identifier('dashboard_widget') . ' SET widget_reference_id = :reference_id, widget_width = :width, '
        . 'widget_height = :height, widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
        . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'mail' AND widget_flag = 0"
    );
    try {
        $stmt->execute([':reference_id' => $accountId, ':width' => $width, ':height' => $height, ':style' => $style,
            ':config' => dashboard_widget_encode_config($config), ':updated_at' => app_now(), ':widget_id' => $widgetId, ':owner' => $ownerId]);
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '23000') {
            throw new LengthException('A Mail Widget already exists for this account.');
        }
        throw $exception;
    }
    return true;
}

function mail_widget_delete(int $ownerId, int $widgetId): bool
{
    if (mail_widget_find_owned($ownerId, $widgetId) === null) {
        return false;
    }
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_identifier('dashboard_widget') . ' SET widget_flag = 1, widget_updated_at = :updated_at '
        . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'mail' AND widget_flag = 0"
    );
    $stmt->execute([':updated_at' => app_now(), ':widget_id' => $widgetId, ':owner' => $ownerId]);
    return $stmt->rowCount() === 1;
}

/** @return array{ok:bool,code:string,messages:list<array<string,mixed>>} */
function mail_widget_fetch(int $ownerId, int $widgetId): array
{
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null) {
        return ['ok' => false, 'code' => 'not_found', 'messages' => []];
    }
    $accountId = app_validate_positive_int($widget['widget_reference_id'] ?? null);
    $account = $accountId === null ? null : mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null || $accountId === null) {
        return ['ok' => false, 'code' => 'not_found', 'messages' => []];
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'disabled', 'messages' => []];
    }
    $config = mail_widget_config_from_storage($widget['widget_config'] ?? null, (string) ($account['mail_account_display_name'] ?? 'Mail'));
    try {
        $password = mail_crypto_decrypt($ownerId, $accountId, (string) ($account['mail_account_secret'] ?? ''));
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'credential_unavailable', 'messages' => []];
    }
    try {
        return mail_widget_read_latest([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password, $config['item_limit']);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/** @return array{ok:bool,code:string,uid:int,body:string,truncated:bool} */
function mail_widget_fetch_message_text(int $ownerId, int $widgetId, int $uid): array
{
    $empty = ['ok' => false, 'code' => 'not_found', 'uid' => $uid, 'body' => '', 'truncated' => false];
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null || $uid <= 0) {
        return $empty;
    }
    $accountId = app_validate_positive_int($widget['widget_reference_id'] ?? null);
    $account = $accountId === null ? null : mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null || $accountId === null) {
        return $empty;
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return array_merge($empty, ['code' => 'disabled']);
    }
    try {
        $password = mail_crypto_decrypt($ownerId, $accountId, (string) ($account['mail_account_secret'] ?? ''));
    } catch (AppMailCredentialException) {
        return array_merge($empty, ['code' => 'credential_unavailable']);
    }
    try {
        return mail_widget_read_message_text([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password, $uid);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_mail_widget_dispatch(string $action, int $userId, array $input): array
{
    try {
        if ($action === 'mail.widget.list') {
            $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
            if ($location === null) {
                return api_validation_error('widget_location must be 0, 1, 2, or 3.');
            }
            return api_success(['widgets' => mail_widget_list($userId, $location), 'accounts' => mail_service_list_accounts($userId)]);
        }
        if ($action === 'mail.widget.create' || $action === 'mail.widget.update') {
            $accountId = api_positive_int($input, 'mail_account_id');
            $style = app_normalize_content_style($input['widget_style'] ?? null);
            $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
            $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
            $config = mail_widget_config_from_input($input);
            if ($accountId === null || $style === null || $width === null || $height === null || $config === null) {
                return api_validation_error('Mail Widget settings are invalid.');
            }
            if ($action === 'mail.widget.create') {
                $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
                if ($location === null) {
                    return api_validation_error('widget_location must be 0, 1, 2, or 3.');
                }
                return api_success(['widget_id' => mail_widget_create($userId, $accountId, $location, $style, $width, $height, $config)], 201);
            }
            $widgetId = api_positive_int($input, 'widget_id');
            if ($widgetId === null) {
                return api_validation_error('widget_id must be a positive integer.');
            }
            return mail_widget_update($userId, $widgetId, $accountId, $style, $width, $height, $config)
                ? api_success(['widget_id' => $widgetId])
                : api_error('not_found', 'Mail Widget was not found.', 404);
        }
        if ($action === 'mail.widget.delete') {
            $widgetId = api_positive_int($input, 'widget_id');
            if ($widgetId === null) {
                return api_validation_error('widget_id must be a positive integer.');
            }
            return mail_widget_delete($userId, $widgetId)
                ? api_success(['widget_id' => $widgetId])
                : api_error('not_found', 'Mail Widget was not found.', 404);
        }
        if ($action === 'mail.widget.message') {
            $widgetId = api_positive_int($input, 'widget_id');
            $uid = api_positive_int($input, 'mail_uid');
            if ($widgetId === null || $uid === null) {
                return api_validation_error('widget_id and mail_uid must be positive integers.');
            }
            $result = mail_widget_fetch_message_text($userId, $widgetId, $uid);
            return match ($result['code']) {
                'loaded' => api_success([
                    'uid' => $result['uid'],
                    'body' => $result['body'],
                    'plain_text_available' => true,
                    'truncated' => $result['truncated'],
                ]),
                'no_plain_text' => api_success([
                    'uid' => $result['uid'],
                    'body' => '',
                    'plain_text_available' => false,
                    'truncated' => false,
                ]),
                'not_found', 'message_not_found' => api_error('not_found', 'Mail message was not found.', 404),
                'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
                'body_too_large' => api_error('mail_body_too_large', 'Mail body is too large to preview.', 413),
                'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
                'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
                'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
                default => api_error('mail_connection_failed', 'Could not load the Mail body.', 502),
            };
        }
        if ($action === 'mail.widget.fetch') {
            $widgetId = api_positive_int($input, 'widget_id');
            if ($widgetId === null) {
                return api_validation_error('widget_id must be a positive integer.');
            }
            $result = mail_widget_fetch($userId, $widgetId);
            return match ($result['code']) {
                'loaded' => api_success(['messages' => $result['messages']]),
                'not_found' => api_error('not_found', 'Mail Widget was not found.', 404),
                'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
                'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
                'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
                'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
                default => api_error('mail_connection_failed', 'Could not load the IMAP inbox.', 502),
            };
        }
        return api_error('unknown_action', 'Unknown API action.', 400);
    } catch (LengthException $exception) {
        return api_error('mail_widget_conflict', $exception->getMessage(), 409);
    } catch (AppMailValidationException $exception) {
        return api_validation_error(api_mail_validation_message($exception->reason()));
    } catch (PDOException $exception) {
        return api_error('mail_widget_unavailable', 'Mail Widget storage is unavailable.', 503);
    } catch (Throwable $exception) {
        return api_mail_internal_failure('widget.' . $action, $userId, $exception);
    }
}
