<?php

declare(strict_types=1);

/** Retry-Afterとして保存できる値だけを返す。 */
function feed_clean_retry_after(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    if ($value === '' || strlen($value) > 128 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/\A[0-9]{1,10}\z/D', $value) === 1) {
        return $value;
    }

    $date = DateTimeImmutable::createFromFormat(DATE_RFC7231, $value, new DateTimeZone('GMT'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $date->setTimezone(new DateTimeZone('GMT'))->format(DATE_RFC7231);
}

/** Retry-Afterを秒数へ変換する。上限を超える値は上限へ丸める。 */
function feed_retry_after_seconds(mixed $value, int $now, int $maxDelaySeconds): ?int
{
    $clean = feed_clean_retry_after($value);
    if ($clean === null || $now <= 0 || $maxDelaySeconds <= 0) {
        return null;
    }

    if (preg_match('/\A[0-9]+\z/D', $clean) === 1) {
        $seconds = (int) $clean;
    } else {
        $timestamp = strtotime($clean);
        if (!is_int($timestamp)) {
            return null;
        }
        $seconds = $timestamp - $now;
    }

    if ($seconds <= 0) {
        return null;
    }
    return min($seconds, $maxDelaySeconds);
}

/** Feed取得エラーを再試行用に分類する。 */
function feed_failure_kind(string $errorType, array $fetch = []): string
{
    if ($errorType === 'parse') {
        return 'transient';
    }

    $code = is_string($fetch['error_code'] ?? null) ? $fetch['error_code'] : '';
    $status = is_int($fetch['status'] ?? null) ? $fetch['status'] : 0;

    if (in_array($code, [
        'invalid_url',
        'port_not_allowed',
        'non_public_address',
        'invalid_redirect',
        'tls_error',
        'response_too_large',
    ], true)) {
        return 'security';
    }

    if (in_array($code, ['timeout', 'dns_failed', 'transport_error', 'empty_response'], true)) {
        return 'transient';
    }

    if ($code === 'http_status' && in_array($status, [408, 425, 429, 500, 502, 503, 504], true)) {
        return 'transient';
    }

    return 'permanent';
}

/** 失敗回数と種別から次回までの待機秒数を返す。 */
function feed_retry_delay_seconds(
    int $consecutiveFailures,
    string $failureKind,
    array $fetch,
    int $now,
    int $maxDelaySeconds
): int {
    if ($maxDelaySeconds <= 0 || $failureKind === 'security') {
        return 0;
    }

    $status = is_int($fetch['status'] ?? null) ? $fetch['status'] : 0;
    if (in_array($status, [429, 503], true)) {
        $retryAfter = feed_retry_after_seconds($fetch['retry_after'] ?? null, $now, $maxDelaySeconds);
        if ($retryAfter !== null) {
            return $retryAfter;
        }
    }

    if ($failureKind === 'permanent') {
        return min(900, $maxDelaySeconds);
    }

    $steps = [60, 300, 900, 3600];
    $index = max(0, min(count($steps) - 1, $consecutiveFailures - 1));
    return min($steps[$index], $maxDelaySeconds);
}
