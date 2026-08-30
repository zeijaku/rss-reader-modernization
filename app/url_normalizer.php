<?php

declare(strict_types=1);

/**
 * 記事URLに付いた既知のTracking Parameterだけを除去する。
 *
 * Feed URLは取得条件にQuery Parameterを使う場合があるため、この処理へ
 * 渡さない。一般のQuery Parameter、Path、Fragmentはそのまま残す。
 */
function app_remove_tracking_parameters(string $url): string
{
    if ($url === '' || !str_contains($url, '?')) {
        return $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || (string) ($parts['host'] ?? '') === '') {
        return $url;
    }

    $queryStart = strpos($url, '?');
    if ($queryStart === false) {
        return $url;
    }

    $fragmentStart = strpos($url, '#', $queryStart);
    $queryEnd = $fragmentStart === false ? strlen($url) : $fragmentStart;
    $query = substr($url, $queryStart + 1, $queryEnd - $queryStart - 1);
    if ($query === '') {
        return $url;
    }

    $tokens = preg_split('/([&;])/', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($tokens)) {
        return $url;
    }

    $kept = [];
    for ($i = 0; $i < count($tokens); $i += 2) {
        $part = (string) ($tokens[$i] ?? '');
        if (app_is_tracking_query_part($part)) {
            continue;
        }

        $kept[] = [
            'separator' => $i === 0 ? '' : (string) ($tokens[$i - 1] ?? '&'),
            'part' => $part,
        ];
    }

    $cleanQuery = '';
    foreach ($kept as $index => $entry) {
        if ($index > 0) {
            $separator = $entry['separator'] === ';' ? ';' : '&';
            $cleanQuery .= $separator;
        }
        $cleanQuery .= $entry['part'];
    }

    $prefix = substr($url, 0, $queryStart);
    $fragment = $fragmentStart === false ? '' : substr($url, $fragmentStart);
    if ($cleanQuery === '') {
        return $prefix . $fragment;
    }

    return $prefix . '?' . $cleanQuery . $fragment;
}

/** Query文字列の1項目が削除対象かを確認する。 */
function app_is_tracking_query_part(string $part): bool
{
    $separator = strpos($part, '=');
    $rawName = $separator === false ? $part : substr($part, 0, $separator);
    $name = strtolower(rawurldecode(str_replace('+', ' ', $rawName)));

    return in_array($name, [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'utm_id',
        'fbclid',
        'gclid',
        'dclid',
        'msclkid',
        'mc_cid',
        'mc_eid',
        'ref_src',
    ], true);
}
