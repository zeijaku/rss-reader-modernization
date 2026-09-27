<?php

declare(strict_types=1);

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

/**
 * Temporary V1.38-C production diagnostic.
 *
 * Browser-visible details are deliberately limited to a coarse internal
 * category and upstream HTTP status. Never expose URL, IP, path or exception
 * messages here.
 *
 * @return array{status:int,body:array<string,mixed>}
 */

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
