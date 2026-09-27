<?php

declare(strict_types=1);

/**
 * Reader Full Text fetch target.
 *
 * Article links may contain a browser-only fragment, but server-side fetches do
 * not need it. Known tracking parameters are removed before the safe outbound
 * HTTP boundary is entered.
 */
function reader_full_text_fetch_url(mixed $value): ?string
{
    $url = app_validate_external_link($value, 2048);
    if ($url === null) {
        return null;
    }

    $url = app_remove_tracking_parameters($url);
    $fragment = strpos($url, '#');
    if ($fragment !== false) {
        $url = substr($url, 0, $fragment);
    }

    // Reuse the existing strict server-side fetch URL contract (including the
    // 1024-byte bound and no userinfo/fragment).
    return app_validate_feed_url($url);
}

function reader_full_text_content_type(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 512 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return null;
    }

    $parts = explode(';', $value, 2);
    $type = strtolower(trim($parts[0]));
    return $type !== '' ? $type : null;
}

function reader_full_text_content_type_allowed(mixed $value): bool
{
    $type = reader_full_text_content_type($value);
    return $type !== null && in_array($type, ['text/html', 'application/xhtml+xml'], true);
}
