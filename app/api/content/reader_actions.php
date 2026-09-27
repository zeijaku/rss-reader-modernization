<?php

declare(strict_types=1);

function api_reader_full_text_diagnostic_error(
    string $code,
    string $message,
    int $status,
    string $category,
    int $upstreamStatus
): array {
    $safeCategory = preg_match('/\A[a-z0-9_]{1,64}\z/D', $category) === 1
        ? $category
        : 'transport_error';
    $safeUpstreamStatus = max(0, min(599, $upstreamStatus));

    $diagnosticText = ' 診断: ' . $safeCategory;
    if ($safeUpstreamStatus >= 100 && $safeUpstreamStatus <= 599) {
        $diagnosticText .= ' / HTTP ' . (string) $safeUpstreamStatus;
    }

    // Put the bounded diagnostic in the public message itself so production
    // diagnosis does not depend on a freshly cached dashboard.js. Keep the
    // structured field too for newer clients.
    $response = api_error($code, $message . $diagnosticText, $status);
    $response['body']['error']['diagnostic'] = [
        'category' => $safeCategory,
        'http_status' => $safeUpstreamStatus,
    ];
    return $response;
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_reader_full_text(int $userId, array $input): array
{
    // Resolve the article URL from the authenticated user's owned Feed and the
    // server-known item identity. Never accept a client-supplied article URL.
    $readerResponse = api_feed_reader($userId, $input);
    if (($readerResponse['status'] ?? 500) !== 200) {
        return $readerResponse;
    }

    $contentId = api_positive_int($input, 'content_id');
    $reader = $readerResponse['body']['data']['reader'] ?? null;
    if ($contentId === null || !is_array($reader)) {
        return api_error('reader_full_text_unavailable', '元記事を取得できませんでした。RSS本文を表示しています。', 502);
    }

    $articleUrl = is_string($reader['article_url'] ?? null) ? (string) $reader['article_url'] : '';
    if ($articleUrl === '') {
        return api_error('reader_full_text_no_url', '元記事URLがないため全文を取得できません。RSS本文を表示しています。', 422);
    }

    try {
        $loaded = ReaderFullTextService::fromRuntimeConfiguration()->load($articleUrl);
    } catch (Throwable $exception) {
        return api_feed_internal_failure('feed.reader.full_text', $userId, $contentId, $exception);
    }

    if (($loaded['ok'] ?? false) !== true) {
        $internalCode = is_string($loaded['error_code'] ?? null)
            && preg_match('/\A[a-z0-9_]{1,64}\z/D', (string) $loaded['error_code']) === 1
            ? (string) $loaded['error_code']
            : 'transport_error';
        $httpStatus = max(0, min(599, (int) ($loaded['status'] ?? 0)));

        error_log(sprintf(
            'Reader Full Text fetch failure user_id=%d content_id=%d internal_code=%s http_status=%d',
            $userId,
            $contentId,
            $internalCode,
            $httpStatus
        ));

        if (in_array($internalCode, ['invalid_url', 'port_not_allowed', 'non_public_address', 'invalid_redirect'], true)) {
            return api_reader_full_text_diagnostic_error(
                'reader_full_text_blocked',
                '元記事の取得先を安全に確認できませんでした。RSS本文を表示しています。',
                422,
                $internalCode,
                $httpStatus
            );
        }
        if ($internalCode === 'unsupported_content_type') {
            return api_reader_full_text_diagnostic_error(
                'reader_full_text_unsupported',
                '元記事はReader Modeで扱えない形式でした。RSS本文を表示しています。',
                415,
                $internalCode,
                $httpStatus
            );
        }
        if ($internalCode === 'response_too_large') {
            return api_reader_full_text_diagnostic_error(
                'reader_full_text_too_large',
                '元記事が大きすぎるため全文を取得できませんでした。RSS本文を表示しています。',
                413,
                $internalCode,
                $httpStatus
            );
        }
        if ($internalCode === 'timeout') {
            return api_reader_full_text_diagnostic_error(
                'reader_full_text_timeout',
                '元記事の取得がタイムアウトしました。RSS本文を表示しています。',
                504,
                $internalCode,
                $httpStatus
            );
        }

        return api_reader_full_text_diagnostic_error(
            'reader_full_text_unavailable',
            '元記事を取得できませんでした。RSS本文を表示しています。',
            502,
            $internalCode,
            $httpStatus
        );
    }

    $cacheStatus = is_string($loaded['cache_status'] ?? null) ? (string) $loaded['cache_status'] : 'miss';
    if (!in_array($cacheStatus, ['hit', 'miss', 'stale', 'disabled'], true)) {
        $cacheStatus = 'miss';
    }
    $contentType = reader_full_text_content_type($loaded['content_type'] ?? null) ?? '';
    $body = is_string($loaded['body'] ?? null) ? (string) $loaded['body'] : '';
    $effectiveUrl = is_string($loaded['effective_url'] ?? null)
        ? app_validate_feed_url((string) $loaded['effective_url'])
        : null;

    if ($effectiveUrl === null || $body === '') {
        return api_error(
            'reader_full_text_extract_failed',
            '元記事から本文を抽出できませんでした。RSS本文を表示しています。',
            422
        );
    }

    try {
        $imageProxy = ReaderImageProxyService::fromRuntimeConfiguration();
        $extracted = reader_full_text_extract(
            $body,
            $effectiveUrl,
            static fn (string $imageUrl): ?string => $imageProxy->register($userId, $imageUrl)
        );
    } catch (Throwable $exception) {
        return api_feed_internal_failure('feed.reader.full_text.extract', $userId, $contentId, $exception);
    }
    if ($extracted === null) {
        error_log(sprintf(
            'Reader Full Text extraction failed user_id=%d content_id=%d',
            $userId,
            $contentId
        ));
        return api_error(
            'reader_full_text_extract_failed',
            '元記事から本文を抽出できませんでした。RSS本文を表示しています。',
            422
        );
    }

    return api_success([
        'content_id' => $contentId,
        'item_identity' => (string) ($reader['item_identity'] ?? ''),
        'full_text_fetch' => [
            'fetched' => true,
            'cache_status' => $cacheStatus,
            'stale' => ($loaded['stale'] ?? false) === true,
            'content_type' => $contentType,
            'bytes' => strlen($body),
        ],
        'full_text' => [
            'html' => (string) $extracted['html'],
            'text_length' => (int) $extracted['text_length'],
            'strategy' => (string) $extracted['strategy'],
        ],
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
