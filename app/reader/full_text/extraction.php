<?php

declare(strict_types=1);

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
        // The fixed XML encoding declaration makes DOMDocument treat the
        // fetched bytes as UTF-8 without wrapping a complete remote document in
        // a second html/body tree. DOMDocument still repairs fragment HTML.
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, $flags);
        $document->encoding = 'UTF-8';
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

function reader_full_text_sanitize_node(object $node, object $output, string $baseUrl, ?callable $imageTokenMapper = null): ?object
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
            $safeChild = reader_full_text_sanitize_node($child, $output, $baseUrl, $imageTokenMapper);
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
        if ($imageTokenMapper !== null) {
            try {
                $imageToken = $imageTokenMapper($src);
            } catch (Throwable) {
                $imageToken = null;
            }
            if (!is_string($imageToken) || preg_match('/\A[a-f0-9]{64}\z/D', $imageToken) !== 1) {
                // Privacy fail-safe: never fall back to the remote image URL
                // when proxy mapping was requested but could not be created.
                return null;
            }
            $safe->setAttribute('src', 'reader_image.php?id=' . rawurlencode($imageToken));
        } else {
            $safe->setAttribute('src', $src);
        }
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
        $safeChild = reader_full_text_sanitize_node($child, $output, $baseUrl, $imageTokenMapper);
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
function reader_full_text_extract(string $html, string $effectiveUrl, ?callable $imageTokenMapper = null): ?array
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
        $safeChild = reader_full_text_sanitize_node($child, $output, $baseUrl, $imageTokenMapper);
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
