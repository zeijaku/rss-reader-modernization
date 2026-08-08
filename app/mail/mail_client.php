<?php

declare(strict_types=1);

function mail_client_available(): bool
{
    return class_exists(DirectoryTree\ImapEngine\Mailbox::class)
        && class_exists(DirectoryTree\ImapEngine\Connection\ImapConnection::class)
        && class_exists(DirectoryTree\ImapEngine\Connection\Streams\ImapStream::class);
}

/** @return array<string,mixed> */
function mail_client_tls_context_options(string $host, array $options = []): array
{
    $ssl = isset($options['ssl']) && is_array($options['ssl']) ? $options['ssl'] : [];
    $ssl['verify_peer'] = true;
    $ssl['verify_peer_name'] = true;
    $ssl['allow_self_signed'] = false;
    $ssl['peer_name'] = $host;
    if (filter_var($host, FILTER_VALIDATE_IP) === false) {
        $ssl['SNI_enabled'] = true;
        $ssl['SNI_server_name'] = $host;
    }
    $options['ssl'] = $ssl;
    return $options;
}

function mail_client_socket_address(string $transport, string $ip, int $port): string
{
    if (!in_array($transport, ['ssl', 'tcp'], true)) {
        throw new RuntimeException('Unsupported IMAP socket transport.');
    }
    if (filter_var($ip, FILTER_VALIDATE_IP) === false || $port < 1 || $port > 65535) {
        throw new RuntimeException('Invalid validated IMAP endpoint.');
    }
    $host = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    return $transport . '://' . $host . ':' . $port;
}

if (class_exists(DirectoryTree\ImapEngine\Connection\Streams\ImapStream::class)) {
    final class AppMailPinnedImapStream extends DirectoryTree\ImapEngine\Connection\Streams\ImapStream
    {
        public function __construct(
            private string $expectedHost,
            private string $pinnedIp
        ) {
        }

        public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool
        {
            if (!hash_equals($this->expectedHost, $host)) {
                throw new DirectoryTree\ImapEngine\Exceptions\ImapConnectionFailedException('IMAP hostname validation failed.');
            }

            $address = mail_client_socket_address($transport, $this->pinnedIp, $port);
            $context = stream_context_create(mail_client_tls_context_options($this->expectedHost, $options));
            $this->stream = @stream_socket_client(
                $address,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
            if (!$this->stream) {
                throw new DirectoryTree\ImapEngine\Exceptions\ImapConnectionFailedException('Unable to connect to validated IMAP endpoint.', $errno);
            }

            // ImapEngine's timeout is used for connect(). Apply the same bounded
            // value to subsequent stream reads so a dead server cannot block the
            // request indefinitely.
            @stream_set_timeout($this->stream, $timeout);
            return true;
        }
    }
}

/**
 * Test credentials against INBOX using EXAMINE (read-only).
 *
 * @param array{host:mixed,port:mixed,encryption:mixed,username:mixed} $account
 * @param callable(string):list<string>|null $resolver
 * @return array{ok:bool,code:string}
 */
function mail_client_test_credentials(array $account, string $password, ?callable $resolver = null): array
{
    if (!mail_client_available() || !class_exists('AppMailPinnedImapStream')) {
        return ['ok' => false, 'code' => 'dependency_unavailable'];
    }
    if ($password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'credential_unavailable'];
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
            $mailbox->connection()->examine('INBOX');
            $mailbox->disconnect();
            return ['ok' => true, 'code' => 'connected'];
        } catch (Throwable $exception) {
            if ($mailbox instanceof DirectoryTree\ImapEngine\Mailbox) {
                $mailbox->disconnect();
            }
            if (is_a($exception, DirectoryTree\ImapEngine\Exceptions\ImapCommandException::class)) {
                return ['ok' => false, 'code' => 'imap_rejected'];
            }
            // Try another address from the same validated DNS answer before
            // reporting a generic connection failure.
        }
    }

    return ['ok' => false, 'code' => 'connection_failed'];
}
