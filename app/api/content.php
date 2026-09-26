<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/feed/feed_error.php';
require_once dirname(__DIR__) . '/reader/reader_full_text.php';

/**
 * V1.19-B broad module extracted from the v1.18.0 facade.
 * Function bodies are intentionally kept unchanged.
 */

/** @return array{status:int,body:array<string,mixed>} */
function api_content_create(int $userId, array $input): array
{
    $url = app_validate_feed_url($input['content_value'] ?? null);
    $style = app_normalize_content_style($input['content_style'] ?? null);
    $location = app_validate_content_location($input['content_location'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? '1');
    $height = dashboard_widget_validate_height($input['widget_height'] ?? '1');
    $itemLimit = dashboard_widget_validate_feed_item_limit($input['feed_item_limit'] ?? null);

    if ($url === null) {
        return api_validation_error('Feed URL must be an absolute http/https URL without userinfo or fragment and at most 1024 characters.');
    }
    if ($style === null) {
        return api_validation_error('content_style is invalid.');
    }
    if ($location === null) {
        return api_validation_error('content_location must be 0, 1, 2, or 3.');
    }
    if ($width === null || $height === null) {
        return api_validation_error('Widget size is invalid.');
    }
    if ($itemLimit === null) {
        return api_validation_error('feed_item_limit must be auto/blank or an integer from 1 to 30.');
    }

    try {
        $contentId = dashboard_widget_create_feed($userId, $url, $style, $location, $width, $height, $itemLimit);
    } catch (PDOException $exception) {
        error_log('Dashboard Widget create failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['content_id' => $contentId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_content_update(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    $url = app_validate_feed_url($input['content_value'] ?? null);
    $style = app_normalize_content_style($input['content_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? '1');
    $height = dashboard_widget_validate_height($input['widget_height'] ?? '1');
    $itemLimit = dashboard_widget_validate_feed_item_limit($input['feed_item_limit'] ?? null);

    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }
    if ($url === null) {
        return api_validation_error('Feed URL is invalid.');
    }
    if ($style === null) {
        return api_validation_error('content_style is invalid.');
    }
    if ($width === null || $height === null) {
        return api_validation_error('Widget size is invalid.');
    }
    if ($itemLimit === null) {
        return api_validation_error('feed_item_limit must be auto/blank or an integer from 1 to 30.');
    }

    if (find_owned_active_content($userId, $contentId) === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    try {
        if (!dashboard_widget_update_feed($userId, $contentId, $url, $style, $width, $height, $itemLimit)) {
            return api_error('not_found', 'Content was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Dashboard Widget update failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['content_id' => $contentId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_content_delete(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }

    if (find_owned_active_content($userId, $contentId) === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    try {
        if (!dashboard_widget_delete_feed($userId, $contentId)) {
            return api_error('not_found', 'Content was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Dashboard Widget delete failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['content_id' => $contentId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_create(int $userId, array $input): array
{
    $url = app_validate_stock_url($input['stock_data'] ?? null);
    if ($url !== null) {
        $url = app_remove_tracking_parameters($url);
    }
    $title = app_validate_text($input['stock_title'] ?? null, 128, true);
    if ($url === null) {
        return api_validation_error('stock_data must be a valid http/https URL at most 512 characters.');
    }
    if ($title === null) {
        return api_validation_error('stock_title must be valid UTF-8 text at most 128 characters.');
    }

    // SB-09: do not make a second server-side request to the article URL.
    // The title was already present in the authenticated user's fetched feed.
    $stockId = info_dbsave($userId, $url, $title);

    // V1.11: keep Stock registration one-step. Existing user tags that match
    // the title with high confidence are attached automatically. New tags are
    // only suggested on ?tab=stock and are never created silently.
    try {
        $userTags = stock_tag_list_user($userId);
        if ($userTags !== []) {
            $stockRow = [
                'stock_id' => $stockId,
                'stock_title' => $title,
                'stock_data' => $url,
            ];
            $domain = stock_tag_domain_from_url($url);
            $domainTendencies = stock_tag_domain_tendencies($userId, [$stockRow]);
            $cooccurrenceTendencies = stock_tag_cooccurrence_tendencies($userId);
            $suggestions = stock_tag_suggestions(
                $stockRow,
                $userTags,
                [],
                $domain !== '' ? ($domainTendencies[$domain] ?? []) : [],
                $cooccurrenceTendencies
            );
            foreach ($suggestions as $suggestion) {
                if ((int) ($suggestion['tag_id'] ?? 0) <= 0 || !($suggestion['auto_attach'] ?? false)) {
                    continue;
                }
                stock_tag_attach($userId, $stockId, (int) $suggestion['tag_id'], null);
            }
        }
    } catch (Throwable $exception) {
        // Tag automation is optional enrichment; Stock registration must not fail.
        error_log('Stock auto-tag skipped: ' . $exception->getMessage());
    }

    return api_success(['stock_id' => $stockId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_delete(int $userId, array $input): array
{
    $stockId = api_positive_int($input, 'stock_id');
    if ($stockId === null) {
        return api_validation_error('stock_id must be a positive integer.');
    }

    if (find_owned_active_stock($userId, $stockId) === null) {
        return api_error('not_found', 'Stock was not found.', 404);
    }

    if (delete_stock_owned($userId, $stockId) === 0) {
        return api_error('not_found', 'Stock was not found.', 404);
    }

    return api_success(['stock_id' => $stockId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_attach(int $userId, array $input): array
{
    $stockId = api_positive_int($input, 'stock_id');
    $tagId = api_positive_int($input, 'tag_id');
    $tagNameRaw = api_string($input, 'tag_name');
    $tagName = $tagNameRaw !== '' ? stock_tag_validate_name($tagNameRaw) : null;

    if ($stockId === null) {
        return api_validation_error('stock_id must be a positive integer.');
    }
    if ($tagId === null && $tagName === null) {
        return api_validation_error('tag_id or tag_name is required.');
    }
    if ($tagNameRaw !== '' && $tagName === null) {
        return api_validation_error('tag_name must be valid UTF-8 text at most 40 characters.');
    }

    try {
        $result = stock_tag_attach($userId, $stockId, $tagId, $tagName);
    } catch (LengthException $exception) {
        return api_error('tag_limit', $exception->getMessage(), 409);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Stock tag attach failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be saved.', 503);
    }

    return api_success(['stock_id' => $stockId, 'tag' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_detach(int $userId, array $input): array
{
    $stockId = api_positive_int($input, 'stock_id');
    $tagId = api_positive_int($input, 'tag_id');
    if ($stockId === null || $tagId === null) {
        return api_validation_error('stock_id and tag_id must be positive integers.');
    }

    try {
        if (!stock_tag_detach($userId, $stockId, $tagId)) {
            return api_error('not_found', 'Stock tag was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Stock tag detach failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be removed.', 503);
    }

    return api_success(['stock_id' => $stockId, 'tag_id' => $tagId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_rename(int $userId, array $input): array
{
    $tagId = api_positive_int($input, 'tag_id');
    $tagNameRaw = api_string($input, 'tag_name');
    $tagName = stock_tag_validate_name($tagNameRaw);
    if ($tagId === null) {
        return api_validation_error('tag_id must be a positive integer.');
    }
    if ($tagName === null) {
        return api_validation_error('tag_name must be valid UTF-8 text at most 40 characters.');
    }

    try {
        $result = stock_tag_rename($userId, $tagId, $tagName);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Stock tag rename failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be renamed.', 503);
    }

    return api_success(['tag' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_delete(int $userId, array $input): array
{
    $tagId = api_positive_int($input, 'tag_id');
    if ($tagId === null) {
        return api_validation_error('tag_id must be a positive integer.');
    }

    try {
        $result = stock_tag_delete($userId, $tagId);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Stock tag delete failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be deleted.', 503);
    }

    return api_success(['tag' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_keyword_create(int $userId, array $input): array
{
    $keywordValueRaw = api_string($input, 'keyword_value');
    $keywordValue = feed_keyword_validate_value($keywordValueRaw);
    if ($keywordValue === null) {
        return api_validation_error('keyword_value must be valid UTF-8 text at most 64 characters.');
    }

    try {
        $keyword = feed_keyword_create($userId, $keywordValue);
    } catch (LengthException $exception) {
        return api_error('keyword_limit', $exception->getMessage(), 409);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('RSS Highlight keyword create failed: ' . $exception->getMessage());
        return api_error('feed_keyword_unavailable', 'RSS Highlight keyword could not be saved.', 503);
    } catch (RuntimeException $exception) {
        error_log('RSS Highlight keyword create failed: ' . $exception->getMessage());
        return api_error('feed_keyword_unavailable', 'RSS Highlight keyword could not be saved.', 503);
    }

    return api_success(['keyword' => $keyword], $keyword['created'] ? 201 : 200);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_keyword_delete(int $userId, array $input): array
{
    $keywordId = api_positive_int($input, 'keyword_id');
    if ($keywordId === null) {
        return api_validation_error('keyword_id must be a positive integer.');
    }

    try {
        if (!feed_keyword_delete($userId, $keywordId)) {
            return api_error('not_found', 'RSS Highlight keyword was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('RSS Highlight keyword delete failed: ' . $exception->getMessage());
        return api_error('feed_keyword_unavailable', 'RSS Highlight keyword could not be removed.', 503);
    } catch (RuntimeException $exception) {
        error_log('RSS Highlight keyword delete failed: ' . $exception->getMessage());
        return api_error('feed_keyword_unavailable', 'RSS Highlight keyword could not be removed.', 503);
    }

    return api_success(['keyword_id' => $keywordId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_fetch(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }

    // Ownership remains ahead of cache lookup. A shared URL cache must never
    // turn into a way to query another user's configured content record.
    $content = find_owned_active_content($userId, $contentId);
    if ($content === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    $url = app_validate_feed_url($content['content_value'] ?? null);
    if ($url === null) {
        $details = feed_public_error_details('fetch', 'invalid_url');
        return api_error($details['code'], $details['message'], $details['status']);
    }

    $sourceMapper = new FeedSourceMapper();
    $source = $sourceMapper->fromOwnedContent($content, $userId, $url);
    if ($source === null) {
        return api_feed_internal_failure(
            'feed.source.map',
            $userId,
            $contentId,
            new RuntimeException('Feed source mapping rejected.')
        );
    }

    try {
        $service = FeedFetchService::fromRuntimeConfiguration();
        $loaded = $service->load($source);
    } catch (Throwable $exception) {
        return api_feed_internal_failure('feed.fetch', $userId, $contentId, $exception);
    }
    if (($loaded['ok'] ?? false) !== true) {
        $errorType = ($loaded['error_type'] ?? '') === 'parse' ? 'parse' : 'fetch';
        $fetch = is_array($loaded['fetch'] ?? null) ? $loaded['fetch'] : [];
        $details = feed_public_error_details(
            $errorType,
            (string) ($fetch['error_code'] ?? ''),
            (int) ($fetch['status'] ?? 0)
        );
        error_log(sprintf(
            'RSS upstream failure user_id=%d content_id=%d category=%s internal_code=%s http_status=%d',
            $userId,
            $contentId,
            $details['code'],
            preg_match('/\A[a-z0-9_]{1,64}\z/D', (string) ($fetch['error_code'] ?? '')) === 1
                ? (string) $fetch['error_code']
                : 'unknown',
            max(0, min(599, (int) ($fetch['status'] ?? 0)))
        ));
        return api_error($details['code'], $details['message'], $details['status']);
    }

    $resultFeed = is_array($loaded['result_feed'] ?? null) ? $loaded['result_feed'] : [];
    $effectiveUrl = is_string($loaded['effective_url'] ?? null) ? $loaded['effective_url'] : $source->url;

    try {
        $state = feed_item_state_sync(
            $userId,
            $contentId,
            isset($resultFeed['item']) && is_array($resultFeed['item']) ? $resultFeed['item'] : []
        );
    } catch (Throwable $exception) {
        return api_feed_internal_failure('feed.item.state', $userId, $contentId, $exception);
    }

    $resultFeed['item'] = $state['items'];
    $safeFeed = api_safe_feed_payload($resultFeed, $effectiveUrl);
    $safeFeed['new_count'] = $state['new_count'];
    $safeFeed['initial_baseline'] = $state['initial_baseline'];

    return api_success([
        'content_id' => $contentId,
        'result_feed' => $safeFeed,
    ]);
}

/** @return array{title:string,source:string,date:string,body:string,body_source:string,article_url:string,item_identity:string,full_text:bool}|null */
function api_feed_reader_payload(array $feed, string $itemIdentity): ?array
{
    $items = isset($feed['item']) && is_array($feed['item']) ? $feed['item'] : [];
    $target = null;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $identity = feed_item_state_valid_identity($item['item_identity'] ?? null);
        if ($identity !== null && hash_equals($itemIdentity, $identity)) {
            $target = $item;
            break;
        }
    }
    if ($target === null) {
        return null;
    }

    $contentText = api_reader_plain_text($target['content'] ?? '', 65536);
    $descriptionText = api_reader_plain_text($target['description'] ?? '', 32768);
    $bodySource = 'none';
    $body = '';
    if ($contentText !== '') {
        $body = $contentText;
        $bodySource = 'content';
    } elseif ($descriptionText !== '') {
        $body = $descriptionText;
        $bodySource = 'description';
    }

    $channel = isset($feed['channel']) && is_array($feed['channel']) ? $feed['channel'] : [];
    $articleUrl = app_validate_external_link($target['link'] ?? null, 2048);
    if ($articleUrl !== null) {
        $articleUrl = app_remove_tracking_parameters($articleUrl);
    }

    return [
        'title' => api_feed_text($target['title'] ?? '', 512),
        'source' => api_feed_text($channel['title'] ?? '', 512),
        'date' => api_feed_text($target['date'] ?? '', 64),
        'body' => $body,
        'body_source' => $bodySource,
        'article_url' => $articleUrl ?? '',
        'item_identity' => $itemIdentity,
        'full_text' => false,
    ];
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_reader(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    $itemIdentity = feed_item_state_valid_identity($input['item_identity'] ?? null);
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }
    if ($itemIdentity === null) {
        return api_validation_error('item_identity is invalid.');
    }

    // Reader Modeも通常Feedと同じく、共有Cacheを見る前に必ず所有権を確認する。
    $content = find_owned_active_content($userId, $contentId);
    if ($content === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    $url = app_validate_feed_url($content['content_value'] ?? null);
    if ($url === null) {
        $details = feed_public_error_details('fetch', 'invalid_url');
        return api_error($details['code'], $details['message'], $details['status']);
    }

    $source = (new FeedSourceMapper())->fromOwnedContent($content, $userId, $url);
    if ($source === null) {
        return api_feed_internal_failure(
            'feed.reader.source.map',
            $userId,
            $contentId,
            new RuntimeException('Feed source mapping rejected.')
        );
    }

    try {
        // V1.38-Aは元記事を取得しない。既存RSS Cache/Fetch経路だけを再利用する。
        $loaded = FeedFetchService::fromRuntimeConfiguration()->load($source);
    } catch (Throwable $exception) {
        return api_feed_internal_failure('feed.reader', $userId, $contentId, $exception);
    }

    if (($loaded['ok'] ?? false) !== true) {
        $errorType = ($loaded['error_type'] ?? '') === 'parse' ? 'parse' : 'fetch';
        $fetch = is_array($loaded['fetch'] ?? null) ? $loaded['fetch'] : [];
        $details = feed_public_error_details(
            $errorType,
            (string) ($fetch['error_code'] ?? ''),
            (int) ($fetch['status'] ?? 0)
        );
        return api_error($details['code'], $details['message'], $details['status']);
    }

    $feed = is_array($loaded['result_feed'] ?? null) ? $loaded['result_feed'] : [];
    $reader = api_feed_reader_payload($feed, $itemIdentity);
    if ($reader === null) {
        return api_error('reader_item_not_found', 'Reader content was not found.', 404);
    }

    return api_success([
        'content_id' => $contentId,
        'reader' => $reader,
    ]);
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
            return api_error(
                'reader_full_text_blocked',
                '元記事の取得先を安全に確認できませんでした。RSS本文を表示しています。',
                422
            );
        }
        if ($internalCode === 'unsupported_content_type') {
            return api_error(
                'reader_full_text_unsupported',
                '元記事はReader Modeで扱えない形式でした。RSS本文を表示しています。',
                415
            );
        }
        if ($internalCode === 'response_too_large') {
            return api_error(
                'reader_full_text_too_large',
                '元記事が大きすぎるため全文を取得できませんでした。RSS本文を表示しています。',
                413
            );
        }
        if ($internalCode === 'timeout') {
            return api_error(
                'reader_full_text_timeout',
                '元記事の取得がタイムアウトしました。RSS本文を表示しています。',
                504
            );
        }

        return api_error(
            'reader_full_text_unavailable',
            '元記事を取得できませんでした。RSS本文を表示しています。',
            502
        );
    }

    $cacheStatus = is_string($loaded['cache_status'] ?? null) ? (string) $loaded['cache_status'] : 'miss';
    if (!in_array($cacheStatus, ['hit', 'miss', 'stale', 'disabled'], true)) {
        $cacheStatus = 'miss';
    }
    $contentType = reader_full_text_content_type($loaded['content_type'] ?? null) ?? '';
    $body = is_string($loaded['body'] ?? null) ? (string) $loaded['body'] : '';

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
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_new_clear(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }

    if (find_owned_active_content($userId, $contentId) === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    $identityInput = $input['item_identity'] ?? null;
    $itemIdentity = null;
    if ($identityInput !== null && $identityInput !== '') {
        $itemIdentity = feed_item_state_valid_identity($identityInput);
        if ($itemIdentity === null) {
            return api_validation_error('item_identity is invalid.');
        }
    }

    try {
        $cleared = feed_item_state_mark_seen($userId, $contentId, $itemIdentity);
    } catch (Throwable $exception) {
        error_log(sprintf(
            'Feed NEW clear failed user_id=%d content_id=%d [%s]: %s',
            $userId,
            $contentId,
            $exception::class,
            $exception->getMessage()
        ));
        return api_error('feed_item_state_unavailable', 'Feed item state could not be updated.', 503);
    }

    return api_success([
        'content_id' => $contentId,
        'item_identity' => $itemIdentity ?? '',
        'cleared_count' => $cleared,
    ]);
}
