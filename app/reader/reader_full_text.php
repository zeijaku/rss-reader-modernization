<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/validation.php';
require_once dirname(__DIR__) . '/url_normalizer.php';
require_once dirname(__DIR__) . '/http_fetch.php';

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


function reader_full_text_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($value, 'UTF-8');
        return is_int($length) ? $length : strlen($value);
    }
    return strlen($value);
}

function reader_full_text_normalized_text(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

/**
 * Resolve an article-body link or image against the final fetched page URL.
 * Only http/https is retained. Known tracking parameters are removed.
 */
function reader_full_text_resolve_resource_url(string $baseUrl, mixed $value, bool $allowFragment): ?string
{
    if (!is_string($value) || $value === '' || strlen($value) > 2048
        || !app_is_valid_utf8($value) || app_has_control_characters($value)
    ) {
        return null;
    }

    $value = trim($value);
    if ($value === '' || preg_match('/\s/u', $value) === 1) {
        return null;
    }

    $fragment = '';
    $hashAt = strpos($value, '#');
    if ($hashAt !== false) {
        if ($allowFragment) {
            $fragment = substr($value, $hashAt + 1);
            if (strlen($fragment) > 512 || app_has_control_characters($fragment)) {
                return null;
            }
        }
        $value = substr($value, 0, $hashAt);
    }

    if ($value === '') {
        $resolved = app_validate_feed_url($baseUrl);
    } else {
        $resolved = app_resolve_redirect_url($baseUrl, $value);
    }
    if ($resolved === null) {
        return null;
    }

    $resolved = app_remove_tracking_parameters($resolved);
    if ($allowFragment && $fragment !== '') {
        $withFragment = app_validate_external_link($resolved . '#' . $fragment, 2048);
        return $withFragment !== null ? $withFragment : null;
    }
    return $resolved;
}

/** @return DOMDocument|null */
function reader_full_text_parse_document(string $html): ?object
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return null;
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $flags = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;
        if (defined('LIBXML_COMPACT')) {
            $flags |= LIBXML_COMPACT;
        }
        $loaded = $document->loadHTML(
            '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>',
            $flags
        );
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return $loaded === true ? $document : null;
}

function reader_full_text_remove_noise(object $document): void
{
    if (!class_exists('DOMXPath') || !($document instanceof DOMDocument)) {
        return;
    }

    $xpath = new DOMXPath($document);
    $queries = [
        '//script', '//style', '//noscript', '//template', '//iframe', '//object', '//embed',
        '//form', '//input', '//button', '//select', '//textarea',
        '//nav', '//aside', '//footer',
        '//svg', '//math', '//canvas', '//audio', '//video',
        '//*[@hidden]', '//*[@aria-hidden="true"]',
    ];
    foreach ($queries as $query) {
        $nodes = $xpath->query($query);
        if ($nodes === false) {
            continue;
        }
        $remove = [];
        foreach ($nodes as $node) {
            $remove[] = $node;
        }
        foreach ($remove as $node) {
            if ($node->parentNode !== null) {
                $node->parentNode->removeChild($node);
            }
        }
    }
}

function reader_full_text_candidate_score(object $node, object $xpath): int
{
    if (!($node instanceof DOMElement) || !($xpath instanceof DOMXPath)) {
        return PHP_INT_MIN;
    }

    $text = reader_full_text_normalized_text($node->textContent ?? '');
    $textLength = reader_full_text_text_length($text);
    if ($textLength === 0) {
        return PHP_INT_MIN;
    }

    $paragraphs = $xpath->query('.//p', $node);
    $headings = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $node);
    $listItems = $xpath->query('.//li', $node);
    $links = $xpath->query('.//a', $node);

    $linkTextLength = 0;
    if ($links !== false) {
        foreach ($links as $link) {
            $linkTextLength += reader_full_text_text_length(
                reader_full_text_normalized_text($link->textContent ?? '')
            );
        }
    }

    $tag = strtolower($node->tagName);
    $role = strtolower(trim($node->getAttribute('role')));
    $semantic = strtolower($node->getAttribute('id') . ' ' . $node->getAttribute('class'));
    $bonus = 0;
    if ($tag === 'article') {
        $bonus += 1200;
    }
    if ($tag === 'main') {
        $bonus += 900;
    }
    if ($role === 'main') {
        $bonus += 700;
    }
    if (preg_match('/(?:^|[\s_-])(article|entry|post|story|content|main)(?:$|[\s_-])/i', $semantic) === 1) {
        $bonus += 350;
    }

    $paragraphCount = $paragraphs === false ? 0 : $paragraphs->length;
    $headingCount = $headings === false ? 0 : $headings->length;
    $listItemCount = $listItems === false ? 0 : $listItems->length;
    $linkPenalty = min($textLength, $linkTextLength * 2);

    return $textLength
        + ($paragraphCount * 180)
        + ($headingCount * 70)
        + ($listItemCount * 25)
        + $bonus
        - $linkPenalty;
}

/** @return array{node:DOMElement,strategy:string}|null */
function reader_full_text_select_candidate(object $document): ?array
{
    if (!class_exists('DOMXPath') || !($document instanceof DOMDocument)) {
        return null;
    }

    $xpath = new DOMXPath($document);
    $query = '//article|//main|//*[@role="main"]'
        . '|//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"article")]'
        . '|//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"entry-content")]'
        . '|//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"post-content")]'
        . '|//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"post-body")]'
        . '|//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"story-body")]'
        . '|//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"main-content")]'
        . '|//*[contains(translate(@id,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"article")]'
        . '|//*[contains(translate(@id,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"content")]';

    $candidates = $xpath->query($query);
    $best = null;
    $bestScore = PHP_INT_MIN;
    if ($candidates !== false) {
        foreach ($candidates as $candidate) {
            if (!($candidate instanceof DOMElement)) {
                continue;
            }
            $score = reader_full_text_candidate_score($candidate, $xpath);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }
    }

    if ($best instanceof DOMElement
        && reader_full_text_text_length(reader_full_text_normalized_text($best->textContent ?? '')) >= 80
    ) {
        $tag = strtolower($best->tagName);
        $role = strtolower(trim($best->getAttribute('role')));
        $strategy = $tag === 'article' ? 'article'
            : ($tag === 'main' ? 'main' : ($role === 'main' ? 'role-main' : 'semantic'));
        return ['node' => $best, 'strategy' => $strategy];
    }

    $body = $document->getElementsByTagName('body')->item(0);
    return $body instanceof DOMElement ? ['node' => $body, 'strategy' => 'body'] : null;
}

function reader_full_text_sanitize_node(object $node, object $output, string $baseUrl): ?object
{
    if (!($output instanceof DOMDocument)) {
        return null;
    }

    if ($node instanceof DOMText) {
        return $output->createTextNode($node->nodeValue ?? '');
    }
    if (!($node instanceof DOMElement)) {
        return null;
    }

    $tag = strtolower($node->tagName);
    $dropTags = [
        'script', 'style', 'noscript', 'template', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'select', 'textarea', 'svg', 'math', 'canvas', 'audio', 'video',
        'meta', 'link', 'base',
    ];
    if (in_array($tag, $dropTags, true)) {
        return null;
    }

    $allowed = [
        'article', 'section', 'div',
        'p', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'strong', 'em', 'b', 'i', 'u', 's',
        'a', 'img', 'figure', 'figcaption',
    ];

    if (!in_array($tag, $allowed, true)) {
        $fragment = $output->createDocumentFragment();
        foreach ($node->childNodes as $child) {
            $safeChild = reader_full_text_sanitize_node($child, $output, $baseUrl);
            if ($safeChild !== null) {
                $fragment->appendChild($safeChild);
            }
        }
        return $fragment;
    }

    if ($tag === 'img') {
        $src = reader_full_text_resolve_resource_url($baseUrl, $node->getAttribute('src'), false);
        if ($src === null) {
            return null;
        }
        $safe = $output->createElement('img');
        $safe->setAttribute('src', $src);
        $alt = reader_full_text_normalized_text($node->getAttribute('alt'));
        if ($alt !== '') {
            if (reader_full_text_text_length($alt) > 512) {
                $alt = function_exists('mb_substr') ? mb_substr($alt, 0, 512, 'UTF-8') : substr($alt, 0, 512);
            }
            $safe->setAttribute('alt', $alt);
        } else {
            $safe->setAttribute('alt', '');
        }
        foreach (['width', 'height'] as $dimension) {
            $value = $node->getAttribute($dimension);
            if (preg_match('/\A\d{1,4}\z/D', $value) === 1) {
                $number = (int) $value;
                if ($number >= 1 && $number <= 4096) {
                    $safe->setAttribute($dimension, (string) $number);
                }
            }
        }
        $safe->setAttribute('loading', 'lazy');
        $safe->setAttribute('decoding', 'async');
        $safe->setAttribute('referrerpolicy', 'no-referrer');
        return $safe;
    }

    $safe = $output->createElement($tag);
    if ($tag === 'a') {
        $href = reader_full_text_resolve_resource_url($baseUrl, $node->getAttribute('href'), true);
        if ($href !== null) {
            $safe->setAttribute('href', $href);
            $safe->setAttribute('target', '_blank');
            $safe->setAttribute('rel', 'noopener noreferrer');
            $safe->setAttribute('referrerpolicy', 'no-referrer');
        }
    }

    foreach ($node->childNodes as $child) {
        $safeChild = reader_full_text_sanitize_node($child, $output, $baseUrl);
        if ($safeChild !== null) {
            $safe->appendChild($safeChild);
        }
    }
    return $safe;
}

function reader_full_text_inner_html(object $document, object $node): string
{
    if (!($document instanceof DOMDocument)) {
        return '';
    }
    $html = '';
    foreach ($node->childNodes as $child) {
        $part = $document->saveHTML($child);
        if (is_string($part)) {
            $html .= $part;
        }
    }
    return $html;
}

/**
 * Lightweight main-body extraction and allowlist sanitizer.
 *
 * @return array{html:string,text_length:int,strategy:string}|null
 */
function reader_full_text_extract(string $html, string $effectiveUrl): ?array
{
    $baseUrl = app_validate_feed_url($effectiveUrl);
    if ($baseUrl === null || $html === '' || strlen($html) > APP_HTTP_MAX_BYTES) {
        return null;
    }

    $document = reader_full_text_parse_document($html);
    if ($document === null) {
        return null;
    }
    reader_full_text_remove_noise($document);

    $selected = reader_full_text_select_candidate($document);
    if ($selected === null) {
        return null;
    }

    $output = new DOMDocument('1.0', 'UTF-8');
    $wrapper = $output->createElement('div');
    $output->appendChild($wrapper);

    foreach ($selected['node']->childNodes as $child) {
        $safeChild = reader_full_text_sanitize_node($child, $output, $baseUrl);
        if ($safeChild !== null) {
            $wrapper->appendChild($safeChild);
        }
    }

    $sanitizedHtml = trim(reader_full_text_inner_html($output, $wrapper));
    $plainText = reader_full_text_normalized_text($wrapper->textContent ?? '');
    $textLength = reader_full_text_text_length($plainText);
    $hasImage = $wrapper->getElementsByTagName('img')->length > 0;

    if ($sanitizedHtml === '' || ($textLength < 40 && !$hasImage) || strlen($sanitizedHtml) > 524288) {
        return null;
    }

    return [
        'html' => $sanitizedHtml,
        'text_length' => $textLength,
        'strategy' => (string) $selected['strategy'],
    ];
}


/**
 * Small file cache dedicated to raw article HTML.
 *
 * This intentionally does not reuse FeedCache because FeedCache is typed around
 * FeedSource/FeedCacheEntry and Feed parser semantics. The storage protections
 * remain equivalent: hashed file names, size cap, checksum, symlink rejection,
 * atomic replacement and bounded stale serving.
 */
final class ReaderFullTextCache
{
    private const SCHEMA_VERSION = 1;
    private const FILE_PREFIX = 'reader-full-text-v1-';
    private const FUTURE_CLOCK_TOLERANCE_SECONDS = 300;

    /** @var Closure():int */
    private Closure $clock;

    /** @param Closure():int|null $clock */
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds,
        private readonly int $staleMaxAgeSeconds,
        private readonly int $maxBodyBytes,
        ?Closure $clock = null
    ) {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Reader Full Text cache directory must be non-empty.');
        }
        if ($ttlSeconds <= 0 || $staleMaxAgeSeconds < $ttlSeconds || $maxBodyBytes <= 0) {
            throw new InvalidArgumentException('Reader Full Text cache settings are invalid.');
        }
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function cachePath(string $sourceUrl): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::FILE_PREFIX . hash('sha256', $sourceUrl) . '.json';
    }

    /** @return array<string,mixed>|null */
    public function read(string $sourceUrl): ?array
    {
        $path = $this->cachePath($sourceUrl);
        if (!is_file($path) || is_link($path)) {
            return null;
        }

        $maxFileBytes = (int) ceil($this->maxBodyBytes * 4 / 3) + 65536;
        $size = @filesize($path);
        if (!is_int($size) || $size <= 0 || $size > $maxFileBytes) {
            $this->delete($sourceUrl);
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '' || strlen($json) > $maxFileBytes) {
            $this->delete($sourceUrl);
            return null;
        }

        try {
            $payload = json_decode($json, true, 24, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->delete($sourceUrl);
            return null;
        }
        if (!is_array($payload)
            || ($payload['schema'] ?? null) !== self::SCHEMA_VERSION
            || !is_string($payload['source_url'] ?? null)
            || !hash_equals($sourceUrl, (string) $payload['source_url'])
            || !is_string($payload['effective_url'] ?? null)
            || app_validate_feed_url((string) $payload['effective_url']) !== (string) $payload['effective_url']
            || !reader_full_text_content_type_allowed($payload['content_type'] ?? null)
            || !is_int($payload['status'] ?? null)
            || (int) $payload['status'] < 200
            || (int) $payload['status'] >= 300
            || !is_int($payload['fetched_at'] ?? null)
            || (int) $payload['fetched_at'] <= 0
            || !is_string($payload['body_base64'] ?? null)
            || !is_string($payload['body_sha256'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', (string) $payload['body_sha256']) !== 1
        ) {
            $this->delete($sourceUrl);
            return null;
        }

        $body = base64_decode((string) $payload['body_base64'], true);
        if (!is_string($body) || $body === '' || strlen($body) > $this->maxBodyBytes
            || !hash_equals((string) $payload['body_sha256'], hash('sha256', $body))
        ) {
            $this->delete($sourceUrl);
            return null;
        }

        $now = ($this->clock)();
        $fetchedAt = (int) $payload['fetched_at'];
        if ($fetchedAt > $now + self::FUTURE_CLOCK_TOLERANCE_SECONDS) {
            $this->delete($sourceUrl);
            return null;
        }

        return [
            'source_url' => $sourceUrl,
            'effective_url' => (string) $payload['effective_url'],
            'status' => (int) $payload['status'],
            'content_type' => reader_full_text_content_type($payload['content_type']) ?? '',
            'fetched_at' => $fetchedAt,
            'body' => $body,
        ];
    }

    /** @param array<string,mixed> $entry */
    public function ageSeconds(array $entry): ?int
    {
        $fetchedAt = $entry['fetched_at'] ?? null;
        if (!is_int($fetchedAt) || $fetchedAt <= 0) {
            return null;
        }
        $age = ($this->clock)() - $fetchedAt;
        return $age >= 0 ? $age : null;
    }

    /** @param array<string,mixed> $entry */
    public function isFresh(array $entry): bool
    {
        $age = $this->ageSeconds($entry);
        return $age !== null && $age <= $this->ttlSeconds;
    }

    /** @param array<string,mixed> $entry */
    public function canServeStale(array $entry): bool
    {
        $age = $this->ageSeconds($entry);
        return $age !== null && $age <= $this->staleMaxAgeSeconds;
    }

    /** @param array<string,mixed> $fetch */
    public function writeSuccessfulFetch(string $sourceUrl, array $fetch): bool
    {
        if (($fetch['ok'] ?? false) !== true
            || ($fetch['not_modified'] ?? false) === true
            || reader_full_text_fetch_url($sourceUrl) !== $sourceUrl
        ) {
            return false;
        }

        $body = $fetch['body'] ?? null;
        $effectiveUrl = $fetch['url'] ?? null;
        $status = $fetch['status'] ?? null;
        $contentType = reader_full_text_content_type($fetch['content_type'] ?? null);
        if (!is_string($body) || $body === '' || strlen($body) > $this->maxBodyBytes
            || !is_string($effectiveUrl) || app_validate_feed_url($effectiveUrl) !== $effectiveUrl
            || !is_int($status) || $status < 200 || $status >= 300
            || $contentType === null || !reader_full_text_content_type_allowed($contentType)
        ) {
            return false;
        }

        $payload = [
            'schema' => self::SCHEMA_VERSION,
            'source_url' => $sourceUrl,
            'effective_url' => $effectiveUrl,
            'status' => $status,
            'content_type' => $contentType,
            'fetched_at' => ($this->clock)(),
            'body_base64' => base64_encode($body),
            'body_sha256' => hash('sha256', $body),
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            return false;
        }
        if (!is_string($json) || !$this->ensureDirectory()) {
            return false;
        }

        $tmp = @tempnam($this->directory, '.reader-full-text-');
        if (!is_string($tmp) || $tmp === '') {
            return false;
        }

        $written = @file_put_contents($tmp, $json, LOCK_EX);
        if (!is_int($written) || $written !== strlen($json)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0600);

        $path = $this->cachePath($sourceUrl);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @chmod($path, 0600);
        return true;
    }

    public function delete(string $sourceUrl): void
    {
        $path = $this->cachePath($sourceUrl);
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }

    private function ensureDirectory(): bool
    {
        if (is_link($this->directory)) {
            return false;
        }
        if (is_dir($this->directory)) {
            return true;
        }
        if (!@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            return false;
        }
        @chmod($this->directory, 0700);
        return !is_link($this->directory);
    }
}

final class ReaderFullTextService
{
    public function __construct(
        private readonly ?ReaderFullTextCache $cache,
        private readonly bool $cacheEnabled
    ) {
    }

    public static function fromRuntimeConfiguration(): self
    {
        return new self(
            new ReaderFullTextCache(
                (string) APP_READER_FULL_TEXT_CACHE_DIR,
                (int) APP_READER_FULL_TEXT_CACHE_TTL_SECONDS,
                (int) APP_READER_FULL_TEXT_STALE_MAX_AGE_SECONDS,
                (int) APP_HTTP_MAX_BYTES
            ),
            (bool) APP_READER_FULL_TEXT_CACHE_ENABLED
        );
    }

    /** @return array<string,mixed> */
    public function load(string $articleUrl): array
    {
        $sourceUrl = reader_full_text_fetch_url($articleUrl);
        if ($sourceUrl === null) {
            return $this->failure('invalid_url', 0);
        }

        $stale = null;
        if ($this->cacheEnabled && $this->cache !== null) {
            $cached = $this->cache->read($sourceUrl);
            if ($cached !== null) {
                if ($this->cache->isFresh($cached)) {
                    return $this->cachedSuccess($cached, 'hit', false);
                }
                if ($this->cache->canServeStale($cached)) {
                    $stale = $cached;
                }
            }
        }

        $fetch = app_safe_http_fetch($sourceUrl);
        if (($fetch['ok'] ?? false) !== true) {
            if ($stale !== null) {
                return $this->cachedSuccess($stale, 'stale', true, (string) ($fetch['error_code'] ?? 'transport_error'));
            }
            return $this->failure(
                is_string($fetch['error_code'] ?? null) ? (string) $fetch['error_code'] : 'transport_error',
                is_int($fetch['status'] ?? null) ? (int) $fetch['status'] : 0
            );
        }

        $contentType = reader_full_text_content_type($fetch['content_type'] ?? null);
        if ($contentType === null || !reader_full_text_content_type_allowed($contentType)) {
            if ($stale !== null) {
                return $this->cachedSuccess($stale, 'stale', true, 'unsupported_content_type');
            }
            return $this->failure('unsupported_content_type', (int) ($fetch['status'] ?? 0));
        }

        $body = is_string($fetch['body'] ?? null) ? (string) $fetch['body'] : '';
        if ($body === '') {
            if ($stale !== null) {
                return $this->cachedSuccess($stale, 'stale', true, 'empty_response');
            }
            return $this->failure('empty_response', (int) ($fetch['status'] ?? 0));
        }

        if ($this->cacheEnabled && $this->cache !== null) {
            $fetch['content_type'] = $contentType;
            $this->cache->writeSuccessfulFetch($sourceUrl, $fetch);
        }

        return [
            'ok' => true,
            'source_url' => $sourceUrl,
            'effective_url' => is_string($fetch['url'] ?? null) ? (string) $fetch['url'] : $sourceUrl,
            'status' => (int) ($fetch['status'] ?? 200),
            'content_type' => $contentType,
            'body' => $body,
            'cache_status' => $this->cacheEnabled ? 'miss' : 'disabled',
            'stale' => false,
            'stale_reason' => '',
        ];
    }

    /** @param array<string,mixed> $entry @return array<string,mixed> */
    private function cachedSuccess(array $entry, string $cacheStatus, bool $stale, string $staleReason = ''): array
    {
        return [
            'ok' => true,
            'source_url' => (string) ($entry['source_url'] ?? ''),
            'effective_url' => (string) ($entry['effective_url'] ?? ''),
            'status' => (int) ($entry['status'] ?? 200),
            'content_type' => (string) ($entry['content_type'] ?? ''),
            'body' => (string) ($entry['body'] ?? ''),
            'cache_status' => $cacheStatus,
            'stale' => $stale,
            'stale_reason' => $staleReason,
        ];
    }

    /** @return array<string,mixed> */
    private function failure(string $errorCode, int $status): array
    {
        return [
            'ok' => false,
            'error_code' => preg_match('/\A[a-z0-9_]{1,64}\z/D', $errorCode) === 1 ? $errorCode : 'transport_error',
            'status' => max(0, min(599, $status)),
        ];
    }
}
