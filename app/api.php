<?php

declare(strict_types=1);

require_once __DIR__ . '/url_normalizer.php';
require_once __DIR__ . '/feed/feed_item_state.php';

/**
 * Secure Baseline API contract (SB-05..10).
 *
 * The public endpoint authenticates the session and validates CSRF before
 * calling this dispatcher. Ownership comes only from the authenticated
 * session. This layer validates each action before DB or outbound I/O.
 */

/** @return array{status:int,body:array<string,mixed>} */
function api_success(array $data = [], int $status = 200): array
{
    return [
        'status' => $status,
        'body' => [
            'ok' => true,
            'data' => $data,
        ],
    ];
}

/** @return array{status:int,body:array<string,mixed>} */
function api_error(string $code, string $message, int $status): array
{
    return [
        'status' => $status,
        'body' => [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ],
    ];
}

/** @return array{status:int,body:array<string,mixed>} */
function api_validation_error(string $message): array
{
    return api_error('validation_error', $message, 422);
}

function api_string(array $input, string $key, string $default = ''): string
{
    $value = $input[$key] ?? $default;
    return is_string($value) ? $value : $default;
}

function api_positive_int(array $input, string $key): ?int
{
    return app_validate_positive_int($input[$key] ?? null);
}

function api_feed_text(mixed $value, int $maxLength): string
{
    $text = is_string($value) ? $value : '';
    if (!app_is_valid_utf8($text)) {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            $text = is_string($converted) ? $converted : '';
        } else {
            $text = '';
        }
    }
    $text = trim(strip_tags($text));
    if (app_text_length($text) <= $maxLength) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }
    if (function_exists('iconv_substr')) {
        $truncated = iconv_substr($text, 0, $maxLength, 'UTF-8');
        return is_string($truncated) ? $truncated : '';
    }
    return substr($text, 0, $maxLength);
}

/** @return array{channel:array{title:string,link:string,description:string},item:list<array{title:string,link:string,description:string,content:string,date:string}>} */
function api_safe_feed_payload(array $feed, string $sourceUrl): array
{
    $channel = isset($feed['channel']) && is_array($feed['channel']) ? $feed['channel'] : [];
    $channelLink = app_validate_external_link($channel['link'] ?? null, 2048) ?? $sourceUrl;

    $items = [];
    $rawItems = isset($feed['item']) && is_array($feed['item']) ? $feed['item'] : [];
    foreach ($rawItems as $rawItem) {
        if (!is_array($rawItem)) {
            continue;
        }
        $itemLink = app_validate_external_link($rawItem['link'] ?? null, 2048);
        $itemIdentity = feed_item_state_valid_identity($rawItem['item_identity'] ?? null);
        $items[] = [
            'title' => api_feed_text($rawItem['title'] ?? '', 512),
            'link' => $itemLink === null ? '' : app_remove_tracking_parameters($itemLink),
            'description' => api_feed_text($rawItem['description'] ?? '', 2048),
            'content' => api_feed_text($rawItem['content'] ?? '', 4096),
            'date' => api_feed_text($rawItem['date'] ?? '', 64),
            'item_identity' => $itemIdentity ?? '',
            'is_new' => $itemIdentity !== null && ($rawItem['is_new'] ?? false) === true,
        ];
    }

    return [
        'channel' => [
            'title' => api_feed_text($channel['title'] ?? '', 512),
            'link' => $channelLink,
            'description' => api_feed_text($channel['description'] ?? '', 2048),
        ],
        'item' => $items,
    ];
}

/** @return array{status:int,body:array<string,mixed>} */
function api_dispatch(string $action, int $userId, array $input): array
{
    if ($userId <= 0) {
        return api_error('unauthenticated', 'Authentication is required.', 401);
    }

    return match ($action) {
        'content.create' => api_content_create($userId, $input),
        'content.update' => api_content_update($userId, $input),
        'content.delete' => api_content_delete($userId, $input),
        'stock.create' => api_stock_create($userId, $input),
        'settings.update' => api_settings_update($userId, $input),
        'tabs.update' => api_tabs_update($userId, $input),
        'feed.fetch' => api_feed_fetch($userId, $input),
        'feed.new.clear' => api_feed_new_clear($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_content_create(int $userId, array $input): array
{
    $url = app_validate_feed_url($input['content_value'] ?? null);
    $style = app_normalize_content_style($input['content_style'] ?? null);
    $location = app_validate_content_location($input['content_location'] ?? null);

    if ($url === null) {
        return api_validation_error('Feed URL must be an absolute http/https URL without userinfo or fragment and at most 1024 characters.');
    }
    if ($style === null) {
        return api_validation_error('content_style is invalid.');
    }
    if ($location === null) {
        return api_validation_error('content_location must be 0, 1, 2, or 3.');
    }

    $contentId = entry_content($userId, $url, $style, $location);
    return api_success(['content_id' => $contentId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_content_update(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    $url = app_validate_feed_url($input['content_value'] ?? null);
    $style = app_normalize_content_style($input['content_style'] ?? null);

    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }
    if ($url === null) {
        return api_validation_error('Feed URL is invalid.');
    }
    if ($style === null) {
        return api_validation_error('content_style is invalid.');
    }

    if (find_owned_active_content($userId, $contentId) === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    update_content_owned($userId, $contentId, $url, $style);
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

    delete_content_owned($userId, $contentId);
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
    return api_success(['stock_id' => $stockId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_settings_update(int $userId, array $input): array
{
    if (search_conf($userId) === []) {
        return api_error('not_found', 'User configuration was not found.', 404);
    }

    $style = app_normalize_theme($input['conf_style'] ?? null);
    $navStyle = app_normalize_nav_style($input['conf_style_nav'] ?? null);
    if ($style === null || $navStyle === null) {
        return api_validation_error('Theme or navbar style is invalid.');
    }

    $links = [];
    $views = [];
    $icons = [];
    for ($i = 1; $i <= 4; $i++) {
        $links[$i] = app_validate_navbar_url($input['conf_style_navlink' . $i] ?? null);
        $views[$i] = app_validate_text($input['conf_style_navlink_view' . $i] ?? null, 8, true);
        $icons[$i] = app_normalize_nav_icon($input['conf_style_navlink_icon' . $i] ?? null);
        if ($links[$i] === null || $views[$i] === null || $icons[$i] === null) {
            return api_validation_error('Navbar link, label, or icon is invalid.');
        }
    }

    update_setting(
        $userId,
        $style,
        $navStyle,
        $links[1],
        $views[1],
        $icons[1],
        $links[2],
        $views[2],
        $icons[2],
        $links[3],
        $views[3],
        $icons[3],
        $links[4],
        $views[4],
        $icons[4]
    );

    return api_success();
}

/** @return array{status:int,body:array<string,mixed>} */
function api_tabs_update(int $userId, array $input): array
{
    if (search_conf($userId) === []) {
        return api_error('not_found', 'User configuration was not found.', 404);
    }

    $tabs = [];
    for ($i = 1; $i <= 4; $i++) {
        $tabs[$i] = app_validate_text($input['conf_style_tabname' . $i] ?? null, 16, true);
        if ($tabs[$i] === null) {
            return api_validation_error('Tab names must be valid UTF-8 text at most 16 characters.');
        }
    }

    update_tab($userId, $tabs[1], $tabs[2], $tabs[3], $tabs[4]);
    return api_success();
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
        return api_error('upstream_blocked', 'Stored Feed URL is not allowed by the outbound policy.', 422);
    }

    $sourceMapper = new FeedSourceMapper();
    $source = $sourceMapper->fromOwnedContent($content, $userId, $url);
    if ($source === null) {
        error_log(sprintf(
            'Feed source mapping rejected user_id=%d content_id=%d',
            $userId,
            $contentId
        ));
        return api_error('internal_error', 'Feed source could not be resolved.', 500);
    }

    $service = FeedFetchService::fromRuntimeConfiguration();
    $loaded = $service->load($source);
    if (($loaded['ok'] ?? false) !== true) {
        if (($loaded['error_type'] ?? '') === 'fetch') {
            $fetch = is_array($loaded['fetch'] ?? null) ? $loaded['fetch'] : [];
            $code = (string) ($fetch['error_code'] ?? 'upstream_error');
            $blocked = in_array($code, ['invalid_url', 'port_not_allowed', 'non_public_address', 'invalid_redirect'], true);
            return api_error(
                $blocked ? 'upstream_blocked' : 'upstream_error',
                $blocked ? 'Feed URL was blocked by the outbound security policy.' : 'Feed could not be fetched.',
                $blocked ? 422 : 502
            );
        }

        $parseReason = preg_replace('/[\r\n]+/', ' ', (string) ($loaded['parse_error'] ?? 'unknown parse error'));
        error_log(sprintf(
            'Feed parse rejected user_id=%d content_id=%d reason=%s',
            $userId,
            $contentId,
            is_string($parseReason) ? $parseReason : 'unknown parse error'
        ));
        return api_error('invalid_feed', 'Upstream response is not a supported RSS or Atom feed.', 502);
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
        error_log(sprintf(
            'Feed item state failed user_id=%d content_id=%d [%s]: %s',
            $userId,
            $contentId,
            $exception::class,
            $exception->getMessage()
        ));
        return api_error(
            'feed_item_state_unavailable',
            'Feed item state is unavailable. Apply the Version 1.1-C database migration and try again.',
            503
        );
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
