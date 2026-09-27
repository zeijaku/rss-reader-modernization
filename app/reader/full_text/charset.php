<?php

declare(strict_types=1);

function reader_full_text_normalize_charset_name(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 64
        || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
    ) {
        return null;
    }

    $name = strtolower(trim($value, " \t\n\r\0\x0B\"'"));
    $name = str_replace('_', '-', $name);

    return match ($name) {
        'utf-8', 'utf8' => 'UTF-8',
        'utf-16le' => 'UTF-16LE',
        'utf-16be' => 'UTF-16BE',
        'shift-jis', 'shiftjis', 'sjis', 'x-sjis', 'ms-kanji',
        'windows-31j', 'windows31j', 'cp932', 'ms932' => 'SJIS-win',
        'euc-jp', 'eucjp' => 'EUC-JP',
        'iso-2022-jp', 'iso2022jp', 'jis' => 'JIS',
        'windows-1252', 'cp1252' => 'Windows-1252',
        'iso-8859-1', 'iso8859-1', 'latin1' => 'ISO-8859-1',
        default => null,
    };
}

function reader_full_text_charset_from_content_type(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 512
        || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
    ) {
        return null;
    }

    $matches = [];
    if (preg_match("/(?:^|;)\\s*charset\\s*=\\s*[\"']?([A-Za-z0-9._:-]{1,40})/i", $value, $matches) !== 1) {
        return null;
    }

    return reader_full_text_normalize_charset_name($matches[1] ?? null);
}

function reader_full_text_charset_from_html(string $html): ?string
{
    if ($html === '') {
        return null;
    }

    $sample = substr($html, 0, 65536);
    $metaTags = [];
    if (preg_match_all('/<meta\\b[^>]{0,1024}>/i', $sample, $metaTags) !== false) {
        foreach (($metaTags[0] ?? []) as $tag) {
            $matches = [];
            if (preg_match("/\\bcharset\\s*=\\s*[\"']?\\s*([A-Za-z0-9._:-]{1,40})/i", $tag, $matches) === 1) {
                $charset = reader_full_text_normalize_charset_name($matches[1] ?? null);
                if ($charset !== null) {
                    return $charset;
                }
            }
        }
    }

    return null;
}

function reader_full_text_detect_charset(string $html, mixed $contentType): ?string
{
    if ($html === '') {
        return null;
    }

    if (str_starts_with($html, "\xEF\xBB\xBF")) {
        return 'UTF-8';
    }
    if (str_starts_with($html, "\xFF\xFE")) {
        return 'UTF-16LE';
    }
    if (str_starts_with($html, "\xFE\xFF")) {
        return 'UTF-16BE';
    }

    $httpCharset = reader_full_text_charset_from_content_type($contentType);
    if ($httpCharset !== null) {
        return $httpCharset;
    }

    $htmlCharset = reader_full_text_charset_from_html($html);
    if ($htmlCharset !== null) {
        return $htmlCharset;
    }

    if (app_is_valid_utf8($html)) {
        return 'UTF-8';
    }

    if (function_exists('mb_detect_encoding')) {
        try {
            $detected = mb_detect_encoding(
                $html,
                ['SJIS-win', 'EUC-JP', 'JIS', 'Windows-1252', 'ISO-8859-1'],
                true
            );
        } catch (Throwable) {
            $detected = false;
        }
        if (is_string($detected)) {
            return reader_full_text_normalize_charset_name($detected);
        }
    }

    return null;
}

/** @return array{html:string,source_charset:string}|null */
function reader_full_text_normalize_html_utf8(string $html, mixed $contentType): ?array
{
    $charset = reader_full_text_detect_charset($html, $contentType);
    if ($charset === null) {
        return null;
    }

    if ($charset === 'UTF-8') {
        $converted = str_starts_with($html, "\xEF\xBB\xBF") ? substr($html, 3) : $html;
    } else {
        $converted = false;
        if (function_exists('mb_convert_encoding')) {
            try {
                $converted = mb_convert_encoding($html, 'UTF-8', $charset);
            } catch (Throwable) {
                $converted = false;
            }
        }
        if (!is_string($converted) && function_exists('iconv')) {
            $iconvCharset = match ($charset) {
                'SJIS-win' => 'CP932',
                'JIS' => 'ISO-2022-JP',
                default => $charset,
            };
            $converted = @iconv($iconvCharset, 'UTF-8//IGNORE', $html);
        }
    }

    if (!is_string($converted) || $converted === '' || !app_is_valid_utf8($converted)) {
        return null;
    }

    return [
        'html' => $converted,
        'source_charset' => $charset,
    ];
}
