<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/feed_health.php';

/** @return array{content:array<string,mixed>,source:FeedSource}|null */
function api_feed_health_owned_source(int $userId, int $contentId): ?array
{
    $content = find_owned_active_content($userId, $contentId);
    if ($content === null) {
        return null;
    }
    $url = app_validate_feed_url($content['content_value'] ?? null);
    if ($url === null) {
        return null;
    }
    $source = (new FeedSourceMapper())->fromOwnedContent($content, $userId, $url);
    return $source instanceof FeedSource ? ['content' => $content, 'source' => $source] : null;
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_fetch_with_health(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }

    feed_health_clear_observation($contentId);
    $response = api_feed_fetch_with_metadata_title($userId, $input);
    $observation = feed_health_take_observation($contentId);
    $sourceInfo = api_feed_health_owned_source($userId, $contentId);
    if ($sourceInfo === null) {
        return $response;
    }
    $source = $sourceInfo['source'];

    try {
        $isOk = ($response['body']['ok'] ?? false) === true;
        if ($observation !== null && ($observation['ok'] ?? false) === true) {
            if ($isOk) {
                $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
                $feed = is_array($data['result_feed'] ?? null) ? $data['result_feed'] : [];
                $items = isset($feed['item']) && is_array($feed['item']) ? $feed['item'] : [];
                feed_health_finalize_success($source, $items, $observation);
            } else {
                $errorCode = (string) ($response['body']['error']['code'] ?? '');
                if ($errorCode === 'invalid_feed') {
                    feed_health_write_failure(
                        $source,
                        (int) ($observation['status'] ?? 0),
                        'parse_error',
                        'Upstream response is not a supported RSS or Atom feed.'
                    );
                } elseif (!in_array($errorCode, ['upstream_error', 'upstream_blocked'], true)) {
                    // The HTTP fetch/parser succeeded and a downstream local feature failed.
                    // Feed Health should still record the upstream feed as healthy.
                    feed_health_finalize_success($source, [], $observation);
                }
            }
        }

        $health = feed_health_get_owned($userId, $contentId);
        if (($response['body']['ok'] ?? false) === true) {
            $response['body']['data']['health'] = $health;
        }
    } catch (Throwable $exception) {
        // Feed Health must never turn a normal RSS fetch into an application failure.
        error_log(sprintf(
            'Feed Health finalize skipped user_id=%d content_id=%d [%s]: %s',
            $userId,
            $contentId,
            $exception::class,
            $exception->getMessage()
        ));
    }

    // V1.22-D: Rules run only after the normal Feed fetch, item-state, metadata
    // and Feed Health paths have completed. Rules never perform outbound I/O.
    if (($response['body']['ok'] ?? false) === true && function_exists('rss_rule_apply_to_feed')) {
        try {
            $feed = $response['body']['data']['result_feed'] ?? null;
            if (is_array($feed)) {
                $applied = rss_rule_apply_to_feed($userId, $contentId, $feed, $source->url);
                $response['body']['data']['result_feed'] = $applied['feed'];
                $response['body']['data']['rules'] = [
                    'matched' => $applied['matched'],
                    'hidden' => $applied['hidden'],
                    'highlighted' => $applied['highlighted'],
                    'auto_stocked' => $applied['auto_stocked'],
                ];
            }
        } catch (Throwable $exception) {
            // Rules are optional enrichment. A Rule failure must not break RSS reading.
            error_log(sprintf(
                'RSS Rules apply skipped user_id=%d content_id=%d [%s]: %s',
                $userId,
                $contentId,
                $exception::class,
                $exception->getMessage()
            ));
        }
    }

    return $response;
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_health_get(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }
    if (find_owned_active_content($userId, $contentId) === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }
    try {
        return api_success(['health' => feed_health_get_owned($userId, $contentId)]);
    } catch (PDOException $exception) {
        error_log('Feed Health get failed: ' . $exception->getMessage());
        return api_error('feed_health_unavailable', 'Feed Health migration is required.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_health_list(int $userId): array
{
    try {
        return api_success(['health' => array_values(feed_health_list_owned($userId))]);
    } catch (PDOException $exception) {
        error_log('Feed Health list failed: ' . $exception->getMessage());
        return api_error('feed_health_unavailable', 'Feed Health migration is required.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_health_recheck(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }

    $sourceInfo = api_feed_health_owned_source($userId, $contentId);
    if ($sourceInfo === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }
    $source = $sourceInfo['source'];

    try {
        // Verify the migration before starting outbound I/O so a deployment
        // problem does not cause an unnecessary external request.
        feed_health_get_owned($userId, $contentId);
    } catch (PDOException $exception) {
        error_log('Feed Health recheck unavailable: ' . $exception->getMessage());
        return api_error('feed_health_unavailable', 'Feed Health migration is required.', 503);
    }

    feed_health_clear_observation($contentId);
    $fetch = (new FeedFetcher())->fetch($source);
    $observation = feed_health_take_observation($contentId);
    if (($fetch['ok'] ?? false) !== true) {
        try {
            return api_success([
                'recheck_ok' => false,
                'health' => feed_health_get_owned($userId, $contentId),
            ]);
        } catch (Throwable $exception) {
            error_log('Feed Health failed after recheck fetch error: ' . $exception->getMessage());
            return api_error('feed_health_unavailable', 'Feed Health could not be updated.', 503);
        }
    }

    $body = is_string($fetch['body'] ?? null) ? $fetch['body'] : '';
    $parser = new FeedParser();
    $feed = $parser->parse_start($body, $source->url, true);
    if ($feed === []) {
        try {
            feed_health_write_failure(
                $source,
                (int) ($fetch['status'] ?? 0),
                'parse_error',
                'Upstream response is not a supported RSS or Atom feed.'
            );
            return api_success([
                'recheck_ok' => false,
                'health' => feed_health_get_owned($userId, $contentId),
            ]);
        } catch (Throwable $exception) {
            error_log('Feed Health failed after recheck parse error: ' . $exception->getMessage());
            return api_error('feed_health_unavailable', 'Feed Health could not be updated.', 503);
        }
    }

    try {
        $items = isset($feed['item']) && is_array($feed['item']) ? $feed['item'] : [];
        feed_health_finalize_success($source, $items, $observation ?? [
            'status' => (int) ($fetch['status'] ?? 0),
            'url' => (string) ($fetch['url'] ?? $source->url),
        ]);
        return api_success([
            'recheck_ok' => true,
            'health' => feed_health_get_owned($userId, $contentId),
        ]);
    } catch (Throwable $exception) {
        error_log('Feed Health recheck save failed: ' . $exception->getMessage());
        return api_error('feed_health_unavailable', 'Feed Health could not be updated.', 503);
    }
}
