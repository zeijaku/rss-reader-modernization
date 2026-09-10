<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_message.php';
require_once __DIR__ . '/mail_attachment.php';

/**
 * Locate the Sent folder without assuming one provider's folder name.
 * RFC 6154 SPECIAL-USE \Sent is preferred. If the server does not advertise
 * SPECIAL-USE, accept a single well-known/localized Sent folder name only.
 */
function mail_sent_find_folder(DirectoryTree\ImapEngine\Mailbox $mailbox): ?DirectoryTree\ImapEngine\FolderInterface
{
    $fallbacks = [];
    $fallbackNames = [
        'sent', 'sent mail', 'sent items', 'sent messages',
        '送信済み', '送信済みメール', '送信済みアイテム',
    ];

    foreach ($mailbox->folders()->get() as $folder) {
        if (!$folder instanceof DirectoryTree\ImapEngine\FolderInterface) {
            continue;
        }
        $flags = array_map(static fn (mixed $flag): string => strtolower((string) $flag), $folder->flags());
        if (in_array('\\noselect', $flags, true) || in_array('\\nonexistent', $flags, true)) {
            continue;
        }
        if (in_array('\\sent', $flags, true)) {
            return $folder;
        }

        try {
            $name = strtolower(trim($folder->name()));
        } catch (Throwable) {
            $name = '';
        }
        if ($name !== '' && in_array($name, $fallbackNames, true)) {
            $fallbacks[] = $folder;
        }
    }

    // Ambiguous name-only matches are deliberately rejected rather than
    // appending a sent message to the wrong mailbox folder.
    return count($fallbacks) === 1 ? $fallbacks[0] : null;
}

function mail_sent_message_exists(DirectoryTree\ImapEngine\FolderInterface $folder, string $messageId): bool
{
    if (mail_message_normalize_message_id($messageId) === '') {
        throw new InvalidArgumentException('Invalid Message-ID.');
    }
    return $folder->messages()->messageId($messageId)->count() > 0;
}

function mail_sent_append_message(DirectoryTree\ImapEngine\FolderInterface $folder, string $mimeMessage): void
{
    if ($mimeMessage === '' || strlen($mimeMessage) > mail_attachment_max_mime_bytes() || str_contains($mimeMessage, "\0")) {
        throw new InvalidArgumentException('Invalid sent MIME message.');
    }
    // Sent messages are appended as already read. The exact SMTP MIME message
    // is retained so Message-ID / reply headers match what was transmitted.
    $folder->messages()->append($mimeMessage, ['\\Seen']);
}

/**
 * Save or verify one successfully transmitted message in the IMAP Sent folder.
 *
 * Modes:
 * - server: do not touch IMAP; the provider is responsible for Sent storage.
 * - reader: append immediately; intended for providers that do not auto-save.
 * - auto: check immediately, after 1 second, and after a further 2 seconds.
 *         If the same Message-ID is still absent, append once.
 *
 * The callback sleeper exists only to make the 1s + 2s policy testable without
 * delaying focused tests. Production uses sleep().
 *
 * @param array{host:mixed,port:mixed,encryption:mixed,username:mixed} $account
 * @param callable(string):list<string>|null $resolver
 * @param callable(int):void|null $sleeper
 * @return array{ok:bool,code:string,folder?:string,source?:string}
 */
function mail_sent_store_after_send(
    array $account,
    string $password,
    string $mode,
    string $messageId,
    string $mimeMessage,
    ?callable $resolver = null,
    ?callable $sleeper = null
): array {
    if (!in_array($mode, ['auto', 'server', 'reader'], true)) {
        return ['ok' => false, 'code' => 'invalid_sent_save_mode'];
    }
    if ($mode === 'server') {
        return ['ok' => true, 'code' => 'server_managed', 'source' => 'server'];
    }
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return ['ok' => false, 'code' => 'dependency_unavailable'];
    }
    if ($password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }
    $normalizedMessageId = mail_message_normalize_message_id($messageId);
    if ($normalizedMessageId === '' || $mimeMessage === '' || strlen($mimeMessage) > mail_attachment_max_mime_bytes() || str_contains($mimeMessage, "\0")) {
        return ['ok' => false, 'code' => 'message_unavailable'];
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

    try {
        $username = mail_account_validate_username($account['username'] ?? null);
    } catch (AppMailValidationException) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
    }

    $sleep = $sleeper ?? static function (int $seconds): void {
        sleep($seconds);
    };

    foreach (array_slice($target['ips'], 0, 3) as $ip) {
        $mailbox = null;
        $connected = false;
        $appendAttempted = false;
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
            $connected = true;

            $folder = mail_sent_find_folder($mailbox);
            if (!$folder instanceof DirectoryTree\ImapEngine\FolderInterface) {
                return ['ok' => false, 'code' => 'sent_folder_unavailable'];
            }
            $folderPath = $folder->path();

            if ($mode === 'auto') {
                if (mail_sent_message_exists($folder, $normalizedMessageId)) {
                    return ['ok' => true, 'code' => 'server_saved', 'folder' => $folderPath, 'source' => 'server'];
                }
                foreach ([1, 2] as $seconds) {
                    $sleep($seconds);
                    try {
                        $mailbox->connection()->noop();
                    } catch (Throwable) {
                        // SEARCH below is the authoritative check. Some older
                        // servers can reject NOOP despite remaining usable.
                    }
                    if (mail_sent_message_exists($folder, $normalizedMessageId)) {
                        return ['ok' => true, 'code' => 'server_saved', 'folder' => $folderPath, 'source' => 'server'];
                    }
                }
            }

            $appendAttempted = true;
            mail_sent_append_message($folder, $mimeMessage);
            return ['ok' => true, 'code' => 'reader_saved', 'folder' => $folderPath, 'source' => 'reader'];
        } catch (Throwable $exception) {
            // After APPEND begins, never retry against another IP. The server
            // may have committed the message even when the final acknowledgement
            // was lost, so a retry could create a duplicate Sent item.
            if ($appendAttempted) {
                return ['ok' => false, 'code' => 'sent_save_uncertain'];
            }
            if ($connected || is_a($exception, DirectoryTree\ImapEngine\Exceptions\ImapCommandException::class)) {
                return ['ok' => false, 'code' => 'imap_rejected'];
            }
            // Connection failed before any mailbox operation; the next already
            // validated public IP can be attempted safely.
        } finally {
            if ($mailbox instanceof DirectoryTree\ImapEngine\Mailbox) {
                try {
                    $mailbox->disconnect();
                } catch (Throwable) {
                }
            }
        }
    }

    return ['ok' => false, 'code' => 'connection_failed'];
}
