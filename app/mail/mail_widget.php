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

function mail_widget_validate_search_type(mixed $value): ?string
{
    $type = is_string($value) ? strtolower(trim($value)) : '';
    return in_array($type, ['', 'subject', 'from'], true) ? $type : null;
}

function mail_widget_validate_search_query(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return '';
    }
    return app_validate_text($value, 128, false);
}

function mail_widget_validate_folder(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || !app_is_valid_utf8($value) || app_text_length($value) > 255) {
        return null;
    }
    if (str_contains($value, "\0") || preg_match('/[\r\n\x00-\x1F\x7F]/u', $value) === 1) {
        return null;
    }
    return $value;
}

/** @return array{schema:int,title:string,item_limit:int,folder:string}|null */
function mail_widget_config_from_input(array $input): ?array
{
    $title = mail_widget_validate_title($input['mail_title'] ?? null);
    $limit = mail_widget_validate_item_limit($input['mail_item_limit'] ?? null);
    $folder = mail_widget_validate_folder($input['mail_folder'] ?? 'INBOX');
    if ($title === null || $limit === null || $folder === null) {
        return null;
    }
    return ['schema' => 2, 'title' => $title, 'item_limit' => $limit, 'folder' => $folder];
}

/** @return array{schema:int,title:string,item_limit:int,folder:string} */
function mail_widget_config_from_storage(mixed $value, string $fallbackTitle = 'Mail'): array
{
    $config = dashboard_widget_decode_config($value);
    $title = mail_widget_validate_title($config['title'] ?? null);
    $limit = mail_widget_validate_item_limit($config['item_limit'] ?? null);
    $folder = mail_widget_validate_folder($config['folder'] ?? 'INBOX');
    $fallback = mail_widget_validate_title($fallbackTitle) ?? 'Mail';
    return ['schema' => 2, 'title' => $title ?? $fallback, 'item_limit' => $limit ?? 5, 'folder' => $folder ?? 'INBOX'];
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

/** @return list<array{path:string,name:string}> */
function mail_widget_folder_options(DirectoryTree\ImapEngine\Mailbox $mailbox): array
{
    $options = [];
    foreach ($mailbox->folders()->get() as $folder) {
        if (!$folder instanceof DirectoryTree\ImapEngine\FolderInterface) {
            continue;
        }
        $flags = array_map(static fn (mixed $flag): string => strtolower((string) $flag), $folder->flags());
        if (in_array('\\noselect', $flags, true) || in_array('\\nonexistent', $flags, true)) {
            continue;
        }
        $path = mail_widget_validate_folder($folder->path());
        if ($path === null) {
            continue;
        }
        try {
            $name = mail_widget_safe_text($folder->name(), 255, $path);
        } catch (Throwable) {
            $name = $path;
        }
        $options[$path] = ['path' => $path, 'name' => $name];
    }
    if (!array_filter(array_keys($options), static fn (string $path): bool => strcasecmp($path, 'INBOX') === 0)) {
        $options['INBOX'] = ['path' => 'INBOX', 'name' => 'INBOX'];
    }
    $result = array_values($options);
    usort($result, static function (array $left, array $right): int {
        $leftInbox = strcasecmp($left['path'], 'INBOX') === 0;
        $rightInbox = strcasecmp($right['path'], 'INBOX') === 0;
        if ($leftInbox !== $rightInbox) {
            return $leftInbox ? -1 : 1;
        }
        return strnatcasecmp($left['path'], $right['path']);
    });
    return $result;
}

/** @param list<array{path:string,name:string}> $folders */
function mail_widget_resolve_folder(array $folders, string $requested): ?string
{
    foreach ($folders as $folder) {
        $path = mail_widget_validate_folder($folder['path'] ?? null);
        if ($path !== null && $path === $requested) {
            return $path;
        }
    }
    if (strcasecmp($requested, 'INBOX') === 0) {
        foreach ($folders as $folder) {
            $path = mail_widget_validate_folder($folder['path'] ?? null);
            if ($path !== null && strcasecmp($path, 'INBOX') === 0) {
                return $path;
            }
        }
    }
    return null;
}

function mail_widget_status_count(array $status, string $name): int
{
    $needle = strtoupper($name);
    foreach ($status as $key => $value) {
        if (strtoupper((string) $key) !== $needle) {
            continue;
        }
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }
    }
    return 0;
}

/** @return array{ok:bool,code:string,messages:list<array<string,mixed>>,unread_count?:int,fetched_at?:string,search_type?:string,search_query?:string,folder?:string,folders?:list<array{path:string,name:string}>} */
function mail_widget_read_latest(array $account, string $password, int $limit, ?callable $resolver = null, string $searchType = '', string $searchQuery = '', string $folderPath = 'INBOX'): array
{
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return ['ok' => false, 'code' => 'dependency_unavailable', 'messages' => []];
    }
    if (!in_array($limit, [5, 10], true) || $password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'credential_unavailable', 'messages' => []];
    }
    $validatedSearchType = mail_widget_validate_search_type($searchType);
    $validatedSearchQuery = mail_widget_validate_search_query($searchQuery);
    $validatedFolder = mail_widget_validate_folder($folderPath);
    if ($validatedSearchType === null || $validatedSearchQuery === null) {
        return ['ok' => false, 'code' => 'invalid_search', 'messages' => []];
    }
    if ($validatedFolder === null) {
        return ['ok' => false, 'code' => 'invalid_folder', 'messages' => []];
    }
    if ($validatedSearchQuery === '') {
        $validatedSearchType = '';
    } elseif ($validatedSearchType === '') {
        $validatedSearchType = 'subject';
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

            // V1.12-I: only folders returned by the authenticated IMAP LIST are selectable.
            // \Noselect containers are removed by mail_widget_folder_options().
            $folders = mail_widget_folder_options($mailbox);
            $resolvedFolder = mail_widget_resolve_folder($folders, $validatedFolder);
            if ($resolvedFolder === null) {
                $mailbox->disconnect();
                return ['ok' => false, 'code' => 'folder_not_found', 'messages' => [], 'folders' => $folders];
            }
            $folder = new DirectoryTree\ImapEngine\Folder($mailbox, $resolvedFolder);

            // STATUS is read-only and reports the whole selected folder, independent of
            // the 5/10 message display limit. Do this before EXAMINE so the selected
            // read-only mailbox is then used only by the message query.
            $status = $folder->status();
            $unreadCount = mail_widget_status_count(is_array($status) ? $status : [], 'UNSEEN');

            // Keep the selected folder read-only. MessageQuery is created directly so
            // Folder::messages() cannot issue SELECT after this EXAMINE command.
            $mailbox->connection()->examine($resolvedFolder);
            $query = new DirectoryTree\ImapEngine\MessageQuery(
                $folder,
                new DirectoryTree\ImapEngine\Connection\ImapQueryBuilder()
            );
            if ($validatedSearchQuery !== '') {
                if ($validatedSearchType === 'from') {
                    $query->from($validatedSearchQuery);
                } else {
                    $query->subject($validatedSearchQuery);
                }
            }
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
            return [
                'ok' => true,
                'code' => 'loaded',
                'messages' => $messages,
                'unread_count' => $unreadCount,
                'fetched_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format(DATE_ATOM),
                'search_type' => $validatedSearchType,
                'search_query' => $validatedSearchQuery,
                'folder' => $resolvedFolder,
                'folders' => $folders,
            ];
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

/** @return array{ok:bool,code:string,folders:list<array{path:string,name:string}>} */
function mail_widget_read_folders(array $account, string $password, ?callable $resolver = null): array
{
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return ['ok' => false, 'code' => 'dependency_unavailable', 'folders' => []];
    }
    if ($password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'credential_unavailable', 'folders' => []];
    }
    $target = mail_validate_target(
        $account['host'] ?? null,
        $account['port'] ?? null,
        $account['encryption'] ?? null,
        $resolver
    );
    if (!$target['ok']) {
        return ['ok' => false, 'code' => $target['error_code'], 'folders' => []];
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
            $folders = mail_widget_folder_options($mailbox);
            $mailbox->disconnect();
            return ['ok' => true, 'code' => 'loaded', 'folders' => $folders];
        } catch (Throwable $exception) {
            if ($mailbox instanceof DirectoryTree\ImapEngine\Mailbox) {
                $mailbox->disconnect();
            }
            if (is_a($exception, DirectoryTree\ImapEngine\Exceptions\ImapCommandException::class)) {
                return ['ok' => false, 'code' => 'imap_rejected', 'folders' => []];
            }
        }
    }
    return ['ok' => false, 'code' => 'connection_failed', 'folders' => []];
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

/** @return array{ok:bool,code:string,uid:int,body:string,truncated:bool,folder?:string} */
function mail_widget_read_message_text(array $account, string $password, int $uid, string $folderPath = 'INBOX', ?callable $resolver = null): array
{
    $empty = ['ok' => false, 'code' => 'connection_failed', 'uid' => $uid, 'body' => '', 'truncated' => false];
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return array_merge($empty, ['code' => 'dependency_unavailable']);
    }
    if ($uid <= 0 || $password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return array_merge($empty, ['code' => 'credential_unavailable']);
    }
    $validatedFolder = mail_widget_validate_folder($folderPath);
    if ($validatedFolder === null) {
        return array_merge($empty, ['code' => 'invalid_folder']);
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
            $mailbox->connection()->examine($validatedFolder);
            $folder = new DirectoryTree\ImapEngine\Folder($mailbox, $validatedFolder);
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
                return ['ok' => true, 'code' => 'no_plain_text', 'uid' => $uid, 'body' => '', 'truncated' => false, 'folder' => $validatedFolder];
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
            return ['ok' => true, 'code' => 'loaded', 'uid' => $uid, 'body' => $safe, 'truncated' => $truncated, 'folder' => $validatedFolder];
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
            'mail_folder' => $config['folder'] ?? 'INBOX',
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
            'mail_folder' => $config['folder'] ?? 'INBOX',
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

/** @return array{ok:bool,code:string,folder?:string,folders?:list<array{path:string,name:string}>} */
function mail_widget_fetch_folders(int $ownerId, int $widgetId): array
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    $accountId = app_validate_positive_int($widget['widget_reference_id'] ?? null);
    $account = $accountId === null ? null : mail_account_find_owned($ownerId, $accountId, true, false);
    if ($account === null || $accountId === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
    if ((int) ($account['mail_account_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'code' => 'disabled'];
    }
    $config = mail_widget_config_from_storage($widget['widget_config'] ?? null, (string) ($account['mail_account_display_name'] ?? 'Mail'));
    try {
        $password = mail_crypto_decrypt($ownerId, $accountId, (string) ($account['mail_account_secret'] ?? ''));
    } catch (AppMailCredentialException) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }
    try {
        $result = mail_widget_read_folders([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password);
        if (($result['ok'] ?? false) !== true) {
            return $result;
        }
        return [
            'ok' => true,
            'code' => 'loaded',
            'folder' => $config['folder'],
            'folders' => is_array($result['folders'] ?? null) ? $result['folders'] : [],
        ];
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/** @return array{ok:bool,code:string,folder?:string,folders?:list<array{path:string,name:string}>} */
function mail_widget_update_folder(int $ownerId, int $widgetId, string $folderPath): array
{
    $validatedFolder = mail_widget_validate_folder($folderPath);
    if ($ownerId <= 0 || $widgetId <= 0 || $validatedFolder === null) {
        return ['ok' => false, 'code' => 'invalid_folder'];
    }
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }
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
        $result = mail_widget_read_folders([
            'host' => $account['mail_account_host'] ?? null,
            'port' => $account['mail_account_port'] ?? null,
            'encryption' => $account['mail_account_encryption'] ?? null,
            'username' => $account['mail_account_username'] ?? null,
        ], $password);
        if (($result['ok'] ?? false) !== true) {
            return $result;
        }
        $folders = is_array($result['folders'] ?? null) ? $result['folders'] : [];
        $resolvedFolder = mail_widget_resolve_folder($folders, $validatedFolder);
        if ($resolvedFolder === null) {
            return ['ok' => false, 'code' => 'folder_not_found', 'folders' => $folders];
        }
        $config = mail_widget_config_from_storage($widget['widget_config'] ?? null, (string) ($account['mail_account_display_name'] ?? 'Mail'));
        $config['schema'] = 2;
        $config['folder'] = $resolvedFolder;
        $stmt = conn_db()->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' SET widget_config = :config, widget_updated_at = :updated_at '
            . "WHERE widget_id = :widget_id AND widget_owner = :owner AND widget_type = 'mail' AND widget_flag = 0"
        );
        $stmt->execute([
            ':config' => dashboard_widget_encode_config($config),
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
        ]);
        return ['ok' => true, 'code' => 'updated', 'folder' => $resolvedFolder, 'folders' => $folders];
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/** @return array{ok:bool,code:string,messages:list<array<string,mixed>>,unread_count?:int,fetched_at?:string,search_type?:string,search_query?:string,folder?:string,folders?:list<array{path:string,name:string}>} */
function mail_widget_fetch(int $ownerId, int $widgetId, string $searchType = '', string $searchQuery = ''): array
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
        ], $password, $config['item_limit'], null, $searchType, $searchQuery, $config['folder']);
    } finally {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($password);
        }
    }
}

/** @return array{ok:bool,code:string,uid:int,body:string,truncated:bool,folder?:string} */
function mail_widget_fetch_message_text(int $ownerId, int $widgetId, int $uid, ?string $requestedFolder = null): array
{
    $empty = ['ok' => false, 'code' => 'not_found', 'uid' => $uid, 'body' => '', 'truncated' => false];
    $widget = mail_widget_find_owned($ownerId, $widgetId);
    if ($widget === null || $uid <= 0) {
        return $empty;
    }
    $config = mail_widget_config_from_storage($widget['widget_config'] ?? null);
    $storedFolder = $config['folder'];
    $validatedRequestedFolder = mail_widget_validate_folder($requestedFolder);
    if ($validatedRequestedFolder === null) {
        return array_merge($empty, ['code' => 'invalid_folder']);
    }
    $sameFolder = $validatedRequestedFolder === $storedFolder
        || (strcasecmp($validatedRequestedFolder, 'INBOX') === 0 && strcasecmp($storedFolder, 'INBOX') === 0);
    if (!$sameFolder) {
        return array_merge($empty, ['code' => 'folder_changed']);
    }
    $folderToRead = strcasecmp($storedFolder, 'INBOX') === 0 ? $validatedRequestedFolder : $storedFolder;
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
        ], $password, $uid, $folderToRead);
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
        if ($action === 'mail.widget.folders') {
            $widgetId = api_positive_int($input, 'widget_id');
            if ($widgetId === null) {
                return api_validation_error('widget_id must be a positive integer.');
            }
            $result = mail_widget_fetch_folders($userId, $widgetId);
            return match ($result['code']) {
                'loaded' => api_success([
                    'folder' => $result['folder'] ?? 'INBOX',
                    'folders' => $result['folders'] ?? [],
                ]),
                'not_found' => api_error('not_found', 'Mail Widget was not found.', 404),
                'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
                'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
                'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
                'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
                default => api_error('mail_connection_failed', 'Could not load IMAP folders.', 502),
            };
        }
        if ($action === 'mail.widget.folder.update') {
            $widgetId = api_positive_int($input, 'widget_id');
            $folder = mail_widget_validate_folder($input['mail_folder'] ?? null);
            if ($widgetId === null || $folder === null) {
                return api_validation_error('widget_id and mail_folder are invalid.');
            }
            $result = mail_widget_update_folder($userId, $widgetId, $folder);
            return match ($result['code']) {
                'updated' => api_success([
                    'folder' => $result['folder'] ?? $folder,
                    'folders' => $result['folders'] ?? [],
                ]),
                'not_found' => api_error('not_found', 'Mail Widget was not found.', 404),
                'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
                'invalid_folder' => api_validation_error('Mail folder is invalid.'),
                'folder_not_found' => api_error('mail_folder_not_found', 'Selected Mail folder is not available.', 422),
                'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
                'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
                'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
                default => api_error('mail_connection_failed', 'Could not verify the selected IMAP folder.', 502),
            };
        }
        if ($action === 'mail.widget.message') {
            $widgetId = api_positive_int($input, 'widget_id');
            $uid = api_positive_int($input, 'mail_uid');
            $folder = mail_widget_validate_folder($input['mail_folder'] ?? null);
            if ($widgetId === null || $uid === null || $folder === null) {
                return api_validation_error('widget_id, mail_uid, and mail_folder are invalid.');
            }
            $result = mail_widget_fetch_message_text($userId, $widgetId, $uid, $folder);
            return match ($result['code']) {
                'loaded' => api_success([
                    'uid' => $result['uid'],
                    'body' => $result['body'],
                    'plain_text_available' => true,
                    'truncated' => $result['truncated'],
                    'folder' => $result['folder'] ?? $folder,
                ]),
                'no_plain_text' => api_success([
                    'uid' => $result['uid'],
                    'body' => '',
                    'plain_text_available' => false,
                    'truncated' => false,
                    'folder' => $result['folder'] ?? $folder,
                ]),
                'not_found', 'message_not_found' => api_error('not_found', 'Mail message was not found.', 404),
                'folder_changed' => api_error('mail_folder_changed', 'Mail folder changed. Refresh the Widget and try again.', 409),
                'invalid_folder' => api_validation_error('Mail folder is invalid.'),
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
            $searchType = mail_widget_validate_search_type($input['mail_search_type'] ?? '');
            $searchQuery = mail_widget_validate_search_query($input['mail_search_query'] ?? '');
            if ($widgetId === null) {
                return api_validation_error('widget_id must be a positive integer.');
            }
            if ($searchType === null || $searchQuery === null) {
                return api_validation_error('Mail search settings are invalid.');
            }
            if ($searchQuery === '') {
                $searchType = '';
            } elseif ($searchType === '') {
                $searchType = 'subject';
            }
            $result = mail_widget_fetch($userId, $widgetId, $searchType, $searchQuery);
            return match ($result['code']) {
                'loaded' => api_success([
                    'messages' => $result['messages'],
                    'unread_count' => $result['unread_count'] ?? 0,
                    'fetched_at' => $result['fetched_at'] ?? app_now(),
                    'search_type' => $result['search_type'] ?? '',
                    'search_query' => $result['search_query'] ?? '',
                    'folder' => $result['folder'] ?? 'INBOX',
                    'folders' => $result['folders'] ?? [],
                ]),
                'not_found' => api_error('not_found', 'Mail Widget was not found.', 404),
                'disabled' => api_error('mail_account_disabled', 'Mail account is disabled.', 409),
                'dependency_unavailable' => api_error('mail_dependency_unavailable', 'Mail dependency is unavailable.', 503),
                'credential_unavailable' => api_error('mail_credential_unavailable', 'Mail credential must be re-entered.', 503),
                'invalid_search' => api_validation_error('Mail search settings are invalid.'),
                'invalid_folder' => api_validation_error('Mail folder is invalid.'),
                'folder_not_found' => api_error('mail_folder_not_found', 'Selected Mail folder is not available.', 422),
                'imap_rejected' => api_error('mail_imap_rejected', 'IMAP server rejected the connection or authentication.', 422),
                default => api_error('mail_connection_failed', 'Could not load the selected IMAP folder.', 502),
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
