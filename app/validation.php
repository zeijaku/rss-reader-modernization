<?php

declare(strict_types=1);

/**
 * SB-08 input validation and SB-10 output helpers.
 *
 * Input validation is deliberately separate from HTML escaping: validated data
 * is still treated as untrusted when rendered.
 */

/** @return list<string> */
function app_allowed_content_styles(): array
{
    return ['success', 'primary', 'info', 'secondary', 'dark', 'warning', 'danger'];
}

/** @return list<string> */
function app_allowed_themes(): array
{
    return [
        'bootstrap',
        'bootstrap-yeti',
        'bootstrap-minty',
        'bootstrap-flatly',
        'bootstrap-journal',
        'bootstrap-sketchy',
        'bootstrap-solar',
        'bootstrap-slate',
    ];
}

/** @return list<string> */
function app_allowed_nav_styles(): array
{
    return ['dark', 'primary', 'light'];
}

/** @return list<string> */
function app_allowed_nav_icons(): array
{
    return ['map-marker-alt', 'sign-out-alt', 'mail-bulk', 'search', 'images', 'edit', 'sync-alt'];
}

function app_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($value, 'UTF-8');
        if ($length !== false) {
            return $length;
        }
    }
    return strlen($value);
}

function app_is_valid_utf8(string $value): bool
{
    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding($value, 'UTF-8');
    }
    return preg_match('//u', $value) === 1;
}

function app_has_control_characters(string $value): bool
{
    return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
}

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = true): ?string
{
    if (!is_string($value) || !app_is_valid_utf8($value) || app_has_control_characters($value)) {
        return null;
    }

    $value = trim($value);
    if (!$allowEmpty && $value === '') {
        return null;
    }
    if (app_text_length($value) > $maxLength) {
        return null;
    }

    return $value;
}

function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
        return null;
    }
    if (strlen($value) > 10) {
        return null;
    }

    $result = (int) $value;
    return $result > 0 ? $result : null;
}

function app_validate_content_location(mixed $value): ?int
{
    if (is_int($value)) {
        return $value >= 0 && $value <= 3 ? $value : null;
    }
    if (!is_string($value) || preg_match('/^[0-3]$/', $value) !== 1) {
        return null;
    }
    return (int) $value;
}

/**
 * GET tab policy: exactly 0,1,2,3,stock. Missing/empty means the first tab (0).
 * Invalid values fail closed to tab 0 rather than becoming SQL input.
 */
function app_tab_from_query(mixed $value): int|string
{
    if ($value === null || $value === '') {
        return 0;
    }
    if ($value === 'stock') {
        return 'stock';
    }
    $location = app_validate_content_location($value);
    return $location ?? 0;
}

function app_validate_enum(mixed $value, array $allowed): ?string
{
    if (!is_string($value)) {
        return null;
    }
    return in_array($value, $allowed, true) ? $value : null;
}

function app_normalize_content_style(mixed $value): ?string
{
    return app_validate_enum($value, app_allowed_content_styles());
}

function app_normalize_theme(mixed $value): ?string
{
    return app_validate_enum($value, app_allowed_themes());
}

function app_normalize_nav_style(mixed $value): ?string
{
    return app_validate_enum($value, app_allowed_nav_styles());
}

function app_normalize_nav_icon(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    // Legacy rows contain values such as "fa-search". New writes use one form.
    if (str_starts_with($value, 'fa-')) {
        $value = substr($value, 3);
    }
    if (app_text_length($value) > 16) {
        return null;
    }
    return in_array($value, app_allowed_nav_icons(), true) ? $value : null;
}

function app_is_valid_hostname(string $host): bool
{
    if ($host === '') {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return true;
    }
    if (strlen($host) > 253 || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
        return false;
    }

    $labels = explode('.', rtrim($host, '.'));
    if ($labels === []) {
        return false;
    }
    foreach ($labels as $label) {
        if ($label === '' || strlen($label) > 63) {
            return false;
        }
        if (preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/', $label) !== 1) {
            return false;
        }
    }
    return true;
}

/**
 * Validate and normalize an http(s) URL.
 *
 * - absolute URL required, except protocol-relative Legacy navbar values when
 *   explicitly enabled (they are normalized to https://)
 * - userinfo prohibited
 * - hostname required
 * - fragments can be prohibited for fetch/storage boundaries
 */
function app_normalize_http_url(
    mixed $value,
    int $maxLength,
    bool $allowProtocolRelative = false,
    bool $allowFragment = false
): ?string {
    if (!is_string($value) || !app_is_valid_utf8($value) || app_has_control_characters($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '' || strlen($value) > $maxLength || preg_match('/\s/u', $value) === 1) {
        return null;
    }

    if ($allowProtocolRelative && str_starts_with($value, '//')) {
        $value = 'https:' . $value;
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
    if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
        $host = substr($host, 1, -1);
    }
    if (!in_array($scheme, ['http', 'https'], true) || !app_is_valid_hostname($host)) {
        return null;
    }
    if (array_key_exists('user', $parts) || array_key_exists('pass', $parts)) {
        return null;
    }
    if (!$allowFragment && array_key_exists('fragment', $parts)) {
        return null;
    }

    $port = $parts['port'] ?? null;
    if ($port !== null && (!is_int($port) || $port < 1 || $port > 65535)) {
        return null;
    }

    $hostForUrl = str_contains($host, ':') ? '[' . $host . ']' : $host;
    $normalized = $scheme . '://' . $hostForUrl;
    if ($port !== null) {
        $normalized .= ':' . $port;
    }
    $normalized .= (string) ($parts['path'] ?? '');
    if (array_key_exists('query', $parts)) {
        $normalized .= '?' . (string) $parts['query'];
    }
    if ($allowFragment && array_key_exists('fragment', $parts)) {
        $normalized .= '#' . (string) $parts['fragment'];
    }

    return strlen($normalized) <= $maxLength ? $normalized : null;
}

function app_validate_feed_url(mixed $value): ?string
{
    return app_normalize_http_url($value, 1024, false, false);
}

function app_validate_stock_url(mixed $value): ?string
{
    return app_normalize_http_url($value, 512, false, true);
}

function app_validate_navbar_url(mixed $value): ?string
{
    if ($value === '') {
        return '';
    }
    return app_normalize_http_url($value, 512, true, true);
}

function app_validate_external_link(mixed $value, int $maxLength = 2048): ?string
{
    return app_normalize_http_url($value, $maxLength, false, true);
}

function app_html(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/** Return a complete selected attribute for a validated option value. */
function app_selected_attr(mixed $current, string $expected): string
{
    return is_string($current) && hash_equals($expected, $current) ? ' selected="selected"' : '';
}

/** Return a complete checked attribute for a validated radio value. */
function app_checked_attr(mixed $current, string $expected): string
{
    return is_string($current) && hash_equals($expected, $current) ? ' checked="checked"' : '';
}

/**
 * Defensive render-time normalization for existing Legacy DB rows.
 */
function app_safe_ui_config(array $config): array
{
    $defaults = default_ui_config();

    $config['conf_style'] = app_normalize_theme($config['conf_style'] ?? null) ?? $defaults['conf_style'];
    $config['conf_style_nav'] = app_normalize_nav_style($config['conf_style_nav'] ?? null) ?? $defaults['conf_style_nav'];

    for ($i = 1; $i <= 4; $i++) {
        $linkKey = 'conf_style_navlink' . $i;
        $viewKey = 'conf_style_navlink_view' . $i;
        $iconKey = 'conf_style_navlink_icon' . $i;
        $tabKey = 'conf_style_tabname' . $i;

        $config[$linkKey] = app_validate_navbar_url($config[$linkKey] ?? '') ?? '';
        $config[$viewKey] = app_validate_text($config[$viewKey] ?? '', 8, true) ?? $defaults[$viewKey];
        $config[$iconKey] = app_normalize_nav_icon($config[$iconKey] ?? null) ?? $defaults[$iconKey];
        $config[$tabKey] = app_validate_text($config[$tabKey] ?? '', 16, true) ?? $defaults[$tabKey];
    }

    return $config;
}
