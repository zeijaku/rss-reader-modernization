<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_message.php';

/** @return array{email:string,name:string}|null */
function mail_reply_address_value(mixed $address): ?array
{
    if (!$address instanceof DirectoryTree\ImapEngine\Address) {
        return null;
    }

    try {
        $emailValue = $address->email();
    } catch (Throwable) {
        return null;
    }

    try {
        $email = mail_message_validate_recipient($emailValue);
    } catch (AppMailValidationException) {
        return null;
    }

    $name = '';
    try {
        $nameValue = $address->name();
        if (is_string($nameValue) && app_is_valid_utf8($nameValue)) {
            $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $nameValue));
            $name = mail_message_truncate_utf8($name, 128);
        }
    } catch (Throwable) {
        $name = '';
    }

    return ['email' => $email, 'name' => $name];
}

/**
 * Read only the headers needed to prepare a Reply draft.
 *
 * Reply-To is preferred when it contains a valid single email address. If it
 * is absent or malformed, From is used. No message flag is changed and no
 * body/attachment content is downloaded here.
 *
 * @param array{host:mixed,port:mixed,encryption:mixed,username:mixed} $account
 * @param callable(string):list<string>|null $resolver
 * @return array{ok:bool,code:string,to:string,to_name:string,subject:string,message_id?:string,recipient_source?:string,folder?:string}
 */
function mail_reply_read_context(
    array $account,
    string $password,
    int $uid,
    string $folderPath,
    ?callable $resolver = null
): array {
    $empty = [
        'ok' => false,
        'code' => 'connection_failed',
        'to' => '',
        'to_name' => '',
        'subject' => '',
    ];

    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return array_merge($empty, ['code' => 'dependency_unavailable']);
    }
    if ($uid <= 0 || $password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return array_merge($empty, ['code' => 'credential_unavailable']);
    }

    $folder = mail_widget_validate_folder($folderPath);
    if ($folder === null) {
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

            // EXAMINE + leaveUnread keeps this Reply preparation read-only.
            $mailbox->connection()->examine($folder);
            $imapFolder = new DirectoryTree\ImapEngine\Folder($mailbox, $folder);
            $query = new DirectoryTree\ImapEngine\MessageQuery(
                $imapFolder,
                new DirectoryTree\ImapEngine\Connection\ImapQueryBuilder()
            );
            $message = $query
                ->withHeaders()
                ->leaveUnread()
                ->find($uid);

            if (!$message instanceof DirectoryTree\ImapEngine\MessageInterface) {
                $mailbox->disconnect();
                return array_merge($empty, ['code' => 'message_not_found']);
            }

            $replyTo = null;
            try {
                $replyTo = mail_reply_address_value($message->replyTo());
            } catch (Throwable) {
                $replyTo = null;
            }

            $from = null;
            if ($replyTo === null) {
                try {
                    $from = mail_reply_address_value($message->from());
                } catch (Throwable) {
                    $from = null;
                }
            }

            $recipient = $replyTo ?? $from;
            if ($recipient === null) {
                $mailbox->disconnect();
                return array_merge($empty, ['code' => 'no_reply_address']);
            }

            try {
                $subject = mail_message_reply_subject($message->subject());
            } catch (Throwable) {
                $subject = mail_message_reply_subject('');
            }
            try {
                $messageId = mail_message_normalize_message_id($message->messageId());
            } catch (Throwable) {
                $messageId = '';
            }

            $mailbox->disconnect();
            return [
                'ok' => true,
                'code' => 'loaded',
                'to' => $recipient['email'],
                'to_name' => $recipient['name'],
                'subject' => $subject,
                'message_id' => $messageId,
                'recipient_source' => $replyTo !== null ? 'reply_to' : 'from',
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
                return array_merge($empty, ['code' => 'imap_rejected']);
            }
        }
    }

    return $empty;
}
