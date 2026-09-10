<?php

declare(strict_types=1);

function mail_message_validate_recipient(mixed $value): string
{
    $address = is_string($value) ? trim($value) : '';
    if (
        $address === ''
        || !app_is_valid_utf8($address)
        || mail_has_control_characters($address)
        || mail_text_length($address) > 320
        || filter_var($address, FILTER_VALIDATE_EMAIL) === false
    ) {
        throw new AppMailValidationException('invalid_recipient');
    }
    return $address;
}

function mail_message_validate_subject(mixed $value): string
{
    $subject = is_string($value) ? trim($value) : '';
    if (
        $subject === ''
        || !app_is_valid_utf8($subject)
        || mail_has_control_characters($subject)
        || mail_text_length($subject) > 255
    ) {
        throw new AppMailValidationException('invalid_subject');
    }
    return $subject;
}

function mail_message_validate_body(mixed $value): string
{
    if (!is_string($value) || $value === '' || !app_is_valid_utf8($value)) {
        throw new AppMailValidationException('invalid_body');
    }
    if (str_contains($value, "\0") || mail_text_length($value) > 20000 || strlen($value) > 100000) {
        throw new AppMailValidationException('invalid_body');
    }

    // Preserve normal plain-text formatting while rejecting non-printing
    // controls that do not belong in a message body. CR/LF/TAB are allowed.
    if (preg_match('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
        throw new AppMailValidationException('invalid_body');
    }
    if (trim($value) === '') {
        throw new AppMailValidationException('invalid_body');
    }

    return str_replace(["\r\n", "\r"], "\n", $value);
}

function mail_message_truncate_utf8(string $value, int $maxLength): string
{
    if (mail_text_length($value) <= $maxLength) {
        return $value;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    // mail_text_length() falls back to bytes when mbstring is unavailable.
    // Keep the same effective limit while never cutting through a UTF-8 codepoint.
    $truncated = substr($value, 0, $maxLength);
    while ($truncated !== '' && !app_is_valid_utf8($truncated)) {
        $truncated = substr($truncated, 0, -1);
    }
    return $truncated;
}

/**
 * Build the editable subject used by V1.34-D Reply.
 *
 * Received headers are treated as untrusted input. Controls are flattened,
 * invalid UTF-8 falls back to the existing no-subject label, and repeated
 * Re:/Re[n]: prefixes are not stacked.
 */
function mail_message_reply_subject(mixed $value): string
{
    $subject = is_string($value) && app_is_valid_utf8($value) ? $value : '';
    $subject = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $subject));
    if ($subject === '') {
        $subject = '件名なし';
    }

    $hasReplyPrefix = preg_match('/^re(?:\[[0-9]+\])?\s*:/iu', $subject) === 1;
    $prefix = $hasReplyPrefix ? '' : 'Re: ';
    $available = 255 - mail_text_length($prefix);
    return $prefix . mail_message_truncate_utf8($subject, $available);
}

/**
 * Normalize a Message-ID for optional Reply threading headers.
 *
 * Message-ID is never required for sending. Only a conservative ASCII form is
 * accepted so an untrusted received header can never become a custom-header
 * injection path.
 */
function mail_message_normalize_message_id(mixed $value): string
{
    $messageId = is_string($value) ? trim($value) : '';
    if (
        $messageId === ''
        || strlen($messageId) > 998
        || !str_starts_with($messageId, '<')
        || !str_ends_with($messageId, '>')
        || !str_contains($messageId, '@')
        || preg_match('/^<[!-~]+>$/D', $messageId) !== 1
        || str_contains(substr($messageId, 1, -1), '<')
        || str_contains(substr($messageId, 1, -1), '>')
    ) {
        return '';
    }
    return $messageId;
}

/** @return array{to:string,subject:string,body:string} */
function mail_message_from_input(array $input): array
{
    return [
        'to' => mail_message_validate_recipient($input['to'] ?? null),
        'subject' => mail_message_validate_subject($input['subject'] ?? null),
        'body' => mail_message_validate_body($input['body'] ?? null),
    ];
}
