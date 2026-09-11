<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_phpmailer_loader.php';
require_once __DIR__ . '/mail_attachment.php';

function mail_smtp_client_available(): bool
{
    return mail_phpmailer_load();
}

function mail_smtp_client_timeout_seconds(): int
{
    $raw = function_exists('app_env') ? (int) app_env('APP_MAIL_SMTP_TIMEOUT_SECONDS', '5') : 5;
    return max(2, min(10, $raw));
}

/** @return array<string,mixed> */
function mail_smtp_client_tls_options(string $host): array
{
    $ssl = [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false,
        'peer_name' => $host,
    ];
    if (filter_var($host, FILTER_VALIDATE_IP) === false) {
        $ssl['SNI_enabled'] = true;
        $ssl['SNI_server_name'] = $host;
    }
    return ['ssl' => $ssl];
}

function mail_smtp_client_connect_host(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return '[' . $ip . ']';
    }
    return $ip;
}

/**
 * Build the common SMTP transport configuration.
 *
 * Authentication is deliberately not configured here. V1.34-B/C use the
 * password helper below; a future OAuth2 implementation can reuse the same
 * transport/TLS/SSRF-safe setup and apply XOAUTH2 separately.
 *
 * @param array{host:string,port:int,encryption:string} $target
 */
function mail_smtp_client_create_mailer(array $target, string $ip): object
{
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->SMTPDebug = 0;
    $mailer->Debugoutput = static function (string $message, int $level): void {
        // Intentionally discard PHPMailer debug output. It can contain
        // endpoint/authentication details and must not reach web/logs.
    };
    $mailer->Host = mail_smtp_client_connect_host($ip);
    $mailer->Port = $target['port'];
    $mailer->SMTPAutoTLS = false;
    $mailer->SMTPSecure = $target['encryption'] === 'ssl'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Timeout = mail_smtp_client_timeout_seconds();
    $mailer->SMTPKeepAlive = false;
    $mailer->SMTPOptions = mail_smtp_client_tls_options($target['host']);

    return $mailer;
}

/**
 * Apply the V1.34-B/C username/password authentication mode.
 *
 * Future OAuth2 support should be added as a sibling helper rather than
 * changing the transport builder above. PHPMailer supports AuthType=XOAUTH2
 * with an OAuthTokenProvider; no OAuth token fields are introduced in B.
 */
function mail_smtp_client_apply_password_auth(object $mailer, string $username, string $password): void
{
    $mailer->SMTPAuth = true;
    $mailer->AuthType = '';
    $mailer->Username = $username;
    $mailer->Password = $password;
}

function mail_smtp_client_clear_auth(object $mailer): void
{
    if (property_exists($mailer, 'Username')) {
        $mailer->Username = '';
    }
    if (property_exists($mailer, 'Password')) {
        $mailer->Password = '';
    }
}

function mail_smtp_client_valid_address(string $address): bool
{
    return $address !== ''
        && app_is_valid_utf8($address)
        && !mail_has_control_characters($address)
        && mail_text_length($address) <= 320
        && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
}

function mail_smtp_client_valid_plain_message(
    string $fromAddress,
    string $fromName,
    string $to,
    string $subject,
    string $body
): bool {
    if (!mail_smtp_client_valid_address($fromAddress) || !mail_smtp_client_valid_address($to)) {
        return false;
    }
    if (
        !app_is_valid_utf8($fromName)
        || mail_has_control_characters($fromName)
        || mail_text_length($fromName) > 128
        || $subject === ''
        || !app_is_valid_utf8($subject)
        || mail_has_control_characters($subject)
        || mail_text_length($subject) > 255
        || $body === ''
        || !app_is_valid_utf8($body)
        || str_contains($body, "\0")
        || mail_text_length($body) > 20000
        || strlen($body) > 100000
        || preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body) === 1
    ) {
        return false;
    }
    return trim($body) !== '';
}

function mail_smtp_client_valid_message_id(string $messageId): bool
{
    return $messageId !== ''
        && strlen($messageId) <= 998
        && str_starts_with($messageId, '<')
        && str_ends_with($messageId, '>')
        && str_contains($messageId, '@')
        && preg_match('/^<[!-~]+>$/D', $messageId) === 1
        && !str_contains(substr($messageId, 1, -1), '<')
        && !str_contains(substr($messageId, 1, -1), '>');
}

function mail_smtp_client_apply_plain_text_message(
    object $mailer,
    string $fromAddress,
    string $fromName,
    string $to,
    string $subject,
    string $body,
    string $replyMessageId = ''
): void {
    $mailer->CharSet = 'UTF-8';
    $mailer->Encoding = '8bit';
    $mailer->isHTML(false);
    $mailer->setFrom($fromAddress, $fromName);
    $mailer->addAddress($to);
    $mailer->Subject = $subject;
    $mailer->Body = $body;
    $mailer->AltBody = '';
    if ($replyMessageId !== '' && mail_smtp_client_valid_message_id($replyMessageId)) {
        $mailer->addCustomHeader('In-Reply-To', $replyMessageId);
        $mailer->addCustomHeader('References', $replyMessageId);
    }
}

/** @param list<array{path:string,name:string,size:int,mime:string}> $attachments */
function mail_smtp_client_apply_attachments(object $mailer, array $attachments): void
{
    foreach ($attachments as $attachment) {
        $added = $mailer->addAttachment(
            $attachment['path'],
            $attachment['name'],
            PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
            $attachment['mime'],
            'attachment'
        );
        if ($added !== true) {
            throw new RuntimeException('Attachment could not be added.');
        }
    }
}

/**
 * Send exactly one plain-text message.
 *
 * Connection/authentication may try up to three already validated public IPs.
 * Once one SMTP connection succeeds, send() is invoked at most once. A send
 * failure is never retried automatically because the remote server may have
 * accepted DATA even if the final acknowledgement was lost.
 *
 * @param array{host:mixed,port:mixed,encryption:mixed} $account
 * @param callable(string):list<string>|null $resolver
 * @return array{ok:bool,code:string,message_id?:string,mime_message?:string}
 */
function mail_smtp_client_send_plain_text(
    array $account,
    string $username,
    string $password,
    string $fromAddress,
    string $fromName,
    string $to,
    string $subject,
    string $body,
    ?callable $resolver = null,
    string $replyMessageId = '',
    array $attachments = []
): array {
    if (!mail_smtp_client_available()) {
        return ['ok' => false, 'code' => 'smtp_dependency_unavailable'];
    }
    if ($username === '' || mail_has_control_characters($username) || mail_text_length($username) > 320) {
        return ['ok' => false, 'code' => 'smtp_credential_unavailable'];
    }
    if ($password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'smtp_credential_unavailable'];
    }
    if (!mail_smtp_client_valid_plain_message($fromAddress, $fromName, $to, $subject, $body)) {
        return ['ok' => false, 'code' => 'smtp_message_invalid'];
    }
    if ($replyMessageId !== '' && !mail_smtp_client_valid_message_id($replyMessageId)) {
        return ['ok' => false, 'code' => 'smtp_message_invalid'];
    }
    if (!mail_attachment_prepared_valid($attachments)) {
        return ['ok' => false, 'code' => 'smtp_attachment_invalid'];
    }

    $target = mail_validate_smtp_target(
        $account['host'] ?? null,
        $account['port'] ?? null,
        $account['encryption'] ?? null,
        $resolver
    );
    if (!$target['ok']) {
        return ['ok' => false, 'code' => $target['error_code']];
    }

    foreach (array_slice($target['ips'], 0, 3) as $ip) {
        $mailer = null;
        $connected = false;
        try {
            $mailer = mail_smtp_client_create_mailer($target, $ip);
            mail_smtp_client_apply_password_auth($mailer, $username, $password);

            if (!$mailer->smtpConnect()) {
                continue;
            }
            $connected = true;
            mail_smtp_client_apply_plain_text_message(
                $mailer,
                $fromAddress,
                $fromName,
                $to,
                $subject,
                $body,
                $replyMessageId
            );
            mail_smtp_client_apply_attachments($mailer, $attachments);

            if (!$mailer->send()) {
                return ['ok' => false, 'code' => 'smtp_send_failed'];
            }

            // Only after SMTP confirms send success, capture the exact MIME
            // message and Message-ID for V1.34-E Sent-folder handling. Failure
            // to read these local PHPMailer values must never change a known
            // successful SMTP result into a send failure.
            $result = ['ok' => true, 'code' => 'sent'];
            try {
                $messageId = (string) $mailer->getLastMessageID();
                if (mail_smtp_client_valid_message_id($messageId)) {
                    $result['message_id'] = $messageId;
                }
            } catch (Throwable) {
            }
            try {
                $mimeMessage = (string) $mailer->getSentMIMEMessage();
                if ($mimeMessage !== '' && strlen($mimeMessage) <= mail_attachment_max_mime_bytes() && !str_contains($mimeMessage, "\0")) {
                    $result['mime_message'] = $mimeMessage;
                }
            } catch (Throwable) {
            }
            return $result;
        } catch (Throwable) {
            if ($connected) {
                return ['ok' => false, 'code' => 'smtp_send_failed'];
            }
            // Connection/authentication failed before any message send attempt;
            // trying the next already validated IP cannot duplicate a message.
        } finally {
            if ($mailer instanceof PHPMailer\PHPMailer\PHPMailer) {
                try {
                    $mailer->smtpClose();
                } catch (Throwable) {
                }
                mail_smtp_client_clear_auth($mailer);
            }
        }
    }

    return ['ok' => false, 'code' => 'smtp_rejected'];
}

/**
 * Test SMTP connection, TLS and password authentication only. No MAIL FROM,
 * RCPT TO or DATA command is sent in V1.34-B.
 *
 * @param array{host:mixed,port:mixed,encryption:mixed} $account
 * @param callable(string):list<string>|null $resolver
 * @return array{ok:bool,code:string}
 */
function mail_smtp_client_test_credentials(
    array $account,
    string $username,
    string $password,
    ?callable $resolver = null
): array {
    if (!mail_smtp_client_available()) {
        return ['ok' => false, 'code' => 'smtp_dependency_unavailable'];
    }
    if ($username === '' || mail_has_control_characters($username) || mail_text_length($username) > 320) {
        return ['ok' => false, 'code' => 'smtp_credential_unavailable'];
    }
    if ($password === '' || strlen($password) > 8192 || str_contains($password, "\0")) {
        return ['ok' => false, 'code' => 'smtp_credential_unavailable'];
    }

    $target = mail_validate_smtp_target(
        $account['host'] ?? null,
        $account['port'] ?? null,
        $account['encryption'] ?? null,
        $resolver
    );
    if (!$target['ok']) {
        return ['ok' => false, 'code' => $target['error_code']];
    }

    // Bound connection retries so a DNS answer with many public addresses cannot
    // keep a request alive for an excessive period. Every address was already
    // validated above; this only limits how many are attempted.
    foreach (array_slice($target['ips'], 0, 3) as $ip) {
        $mailer = null;
        try {
            $mailer = mail_smtp_client_create_mailer($target, $ip);
            mail_smtp_client_apply_password_auth($mailer, $username, $password);

            if ($mailer->smtpConnect()) {
                return ['ok' => true, 'code' => 'connected'];
            }
        } catch (Throwable) {
            // Do not expose or log PHPMailer exception text. Try the next IP
            // from the already validated DNS answer, matching the IMAP policy.
        } finally {
            if ($mailer instanceof PHPMailer\PHPMailer\PHPMailer) {
                try {
                    $mailer->smtpClose();
                } catch (Throwable) {
                }
                mail_smtp_client_clear_auth($mailer);
            }
        }
    }

    return ['ok' => false, 'code' => 'smtp_rejected'];
}
