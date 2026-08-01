<?php

declare(strict_types=1);

/** ETagとして再送できる値だけを返す。 */
function feed_clean_etag(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 512) {
        return null;
    }
    if (preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
        return null;
    }
    return preg_match('/\A(?:W\/)?"[\x21\x23-\x7E]*"\z/D', $value) === 1 ? $value : null;
}

/** Last-ModifiedをHTTP-dateへ揃える。 */
function feed_clean_last_modified(mixed $value): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 128) {
        return null;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return null;
    }

    $httpDate = preg_match('/\A[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT\z/D', $value) === 1
        || preg_match('/\A[A-Z][a-z]+, \d{2}-[A-Z][a-z]{2}-\d{2} \d{2}:\d{2}:\d{2} GMT\z/D', $value) === 1
        || preg_match('/\A[A-Z][a-z]{2} [A-Z][a-z]{2} [ 0-9]\d \d{2}:\d{2}:\d{2} \d{4}\z/D', $value) === 1;
    if (!$httpDate) {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return gmdate('D, d M Y H:i:s \\G\\M\\T', $timestamp);
}

/** @return list<string> */
function feed_conditional_request_headers(array $validators, string $requestUrl): array
{
    $resourceUrl = $validators['resource_url'] ?? null;
    if (!is_string($resourceUrl) || $resourceUrl === '' || !hash_equals($resourceUrl, $requestUrl)) {
        return [];
    }

    $headers = [];
    $etag = feed_clean_etag($validators['etag'] ?? null);
    $lastModified = feed_clean_last_modified($validators['last_modified'] ?? null);
    if ($etag !== null) {
        $headers[] = 'If-None-Match: ' . $etag;
    }
    if ($lastModified !== null) {
        $headers[] = 'If-Modified-Since: ' . $lastModified;
    }
    return $headers;
}
