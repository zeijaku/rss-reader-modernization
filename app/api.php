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
        'stock.delete' => api_stock_delete($userId, $input),
        'stock.tag.attach' => api_stock_tag_attach($userId, $input),
        'stock.tag.detach' => api_stock_tag_detach($userId, $input),
        'stock.tag.rename' => api_stock_tag_rename($userId, $input),
        'stock.tag.delete' => api_stock_tag_delete($userId, $input),
        'settings.update' => api_settings_update($userId, $input),
        'account.email.update' => api_account_email_update($userId, $input),
        'account.password.update' => api_account_password_update($userId, $input),
        'tabs.update' => api_tabs_update($userId, $input),
        'feed.fetch' => api_feed_fetch($userId, $input),
        'feed.new.clear' => api_feed_new_clear($userId, $input),
        'feed.keyword.create' => api_feed_keyword_create($userId, $input),
        'feed.keyword.delete' => api_feed_keyword_delete($userId, $input),
        'widget.list' => api_widget_list($userId, $input),
        'widget.reorder' => api_widget_reorder($userId, $input),
        'widget.search.create' => api_widget_search_create($userId, $input),
        'widget.search.update' => api_widget_search_update($userId, $input),
        'widget.search.delete' => api_widget_search_delete($userId, $input),
        'widget.search.fetch' => api_widget_search_fetch($userId, $input),
        'widget.clock.create' => api_widget_clock_create($userId, $input),
        'widget.clock.update' => api_widget_clock_update($userId, $input),
        'widget.clock.delete' => api_widget_clock_delete($userId, $input),
        'widget.memo.create' => api_widget_memo_create($userId, $input),
        'widget.memo.update' => api_widget_memo_update($userId, $input),
        'widget.memo.delete' => api_widget_memo_delete($userId, $input),
        'widget.task.create' => api_widget_task_create($userId, $input),
        'widget.task.update' => api_widget_task_update($userId, $input),
        'widget.task.delete' => api_widget_task_delete($userId, $input),
        'task.item.create' => api_task_item_create($userId, $input),
        'task.item.update' => api_task_item_update($userId, $input),
        'task.item.toggle' => api_task_item_toggle($userId, $input),
        'task.item.delete' => api_task_item_delete($userId, $input),
        'widget.calendar.create' => api_widget_calendar_create($userId, $input),
        'widget.calendar.update' => api_widget_calendar_update($userId, $input),
        'widget.calendar.delete' => api_widget_calendar_delete($userId, $input),
        'widget.game.create' => api_widget_game_create($userId, $input),
        'widget.game.update' => api_widget_game_update($userId, $input),
        'widget.game.delete' => api_widget_game_delete($userId, $input),
        'widget.links.create' => api_widget_links_create($userId, $input),
        'widget.links.update' => api_widget_links_update($userId, $input),
        'widget.links.delete' => api_widget_links_delete($userId, $input),
        'link.item.create' => api_link_item_create($userId, $input),
        'link.item.update' => api_link_item_update($userId, $input),
        'link.item.delete' => api_link_item_delete($userId, $input),
        'widget.weather.create' => api_widget_weather_create($userId, $input),
        'widget.weather.update' => api_widget_weather_update($userId, $input),
        'widget.weather.delete' => api_widget_weather_delete($userId, $input),
        'weather.forecast' => api_weather_forecast($userId, $input),
        'widget.sunmoon.create' => api_widget_sun_moon_create($userId, $input),
        'widget.sunmoon.update' => api_widget_sun_moon_update($userId, $input),
        'widget.sunmoon.delete' => api_widget_sun_moon_delete($userId, $input),
        'sunmoon.current' => api_sun_moon_current($userId, $input),
        'widget.airquality.create' => api_widget_air_quality_create($userId, $input),
        'widget.airquality.update' => api_widget_air_quality_update($userId, $input),
        'widget.airquality.delete' => api_widget_air_quality_delete($userId, $input),
        'airquality.current' => api_air_quality_current($userId, $input),
        'widget.earthquake.create' => api_widget_earthquake_create($userId, $input),
        'widget.earthquake.update' => api_widget_earthquake_update($userId, $input),
        'widget.earthquake.delete' => api_widget_earthquake_delete($userId, $input),
        'earthquake.latest' => api_earthquake_latest($userId, $input),
        'calendar.month.list' => api_calendar_month_list($userId, $input),
        'calendar.holiday.refresh' => api_calendar_holiday_refresh($userId, $input),
        'calendar.event.create' => api_calendar_event_create($userId, $input),
        'calendar.event.update' => api_calendar_event_update($userId, $input),
        'calendar.event.delete' => api_calendar_event_delete($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}

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
function api_widget_search_create(int $userId,array $input): array
{
    $loc=dashboard_widget_validate_location($input['widget_location']??null);$style=app_normalize_content_style($input['widget_style']??null);$width=dashboard_widget_validate_width($input['widget_width']??null);$height=dashboard_widget_validate_height($input['widget_height']??null);$cfg=search_feed_config_from_input($input);
    if($loc===null||$style===null||$width===null||$height===null||$cfg===null)return api_validation_error('Search Feed settings are invalid.');
    return api_success(['widget_id'=>search_feed_create($userId,$loc,$style,$width,$cfg,$height)],201);
}
function api_widget_search_update(int $userId,array $input): array
{
    $id=api_positive_int($input,'widget_id');$style=app_normalize_content_style($input['widget_style']??null);$width=dashboard_widget_validate_width($input['widget_width']??null);$height=dashboard_widget_validate_height($input['widget_height']??null);$cfg=search_feed_config_from_input($input);
    if($id===null||$style===null||$width===null||$height===null||$cfg===null)return api_validation_error('Search Feed settings are invalid.');
    if(!search_feed_update($userId,$id,$style,$width,$cfg,$height))return api_error('not_found','Search Feed was not found.',404);return api_success(['widget_id'=>$id]);
}
function api_widget_search_delete(int $userId,array $input): array
{
    $id=api_positive_int($input,'widget_id');if($id===null)return api_validation_error('widget_id must be a positive integer.');if(!search_feed_delete($userId,$id))return api_error('not_found','Search Feed was not found.',404);return api_success(['widget_id'=>$id]);
}
function api_widget_search_fetch(int $userId,array $input): array
{
    $id=api_positive_int($input,'widget_id');if($id===null)return api_validation_error('widget_id must be a positive integer.');$r=search_feed_execute($userId,$id);if(($r['ok']??false)!==true)return api_error('not_found','Search Feed was not found.',404);return api_success(['search_result'=>$r]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_list(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }

    try {
        return api_success(['widgets' => dashboard_widget_public_list($userId, $location)]);
    } catch (PDOException $exception) {
        error_log('Dashboard Widget list failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
}


/** @return array{status:int,body:array<string,mixed>} */
function api_widget_reorder(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $previousIds = dashboard_widget_decode_order_list($input['previous_widget_ids'] ?? null);
    $orderedIds = dashboard_widget_decode_order_list($input['widget_ids'] ?? null);
    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($previousIds === null || $orderedIds === null) {
        return api_validation_error('Widget order must be a JSON array of unique positive integer IDs.');
    }

    try {
        $result = dashboard_widget_reorder($userId, $location, $previousIds, $orderedIds);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Dashboard Widget reorder failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget order could not be saved.', 503);
    } catch (RuntimeException $exception) {
        error_log('Dashboard Widget reorder changed during update: ' . $exception->getMessage());
        return api_error('widget_order_conflict', 'Widget order changed. Reload the page and try again.', 409);
    }

    if ($result['conflict']) {
        return api_error('widget_order_conflict', 'Widget order changed. Reload the page and try again.', 409);
    }

    return api_success([
        'widget_ids' => $result['widget_ids'],
        'sort_orders' => $result['sort_orders'],
        'updated' => $result['updated'],
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_clock_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_clock_config_from_input($input);

    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($config === null) {
        return api_validation_error('Clock Widget settings are invalid.');
    }

    try {
        $widgetId = dashboard_widget_create_clock($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Clock Widget create failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Clock Widget could not be created.', 503);
    }

    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_clock_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_clock_config_from_input($input);

    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($config === null) {
        return api_validation_error('Clock Widget settings are invalid.');
    }

    try {
        if (!dashboard_widget_update_clock($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Clock Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Clock Widget update failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Clock Widget could not be updated.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_clock_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!dashboard_widget_delete_clock($userId, $widgetId)) {
            return api_error('not_found', 'Clock Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Clock Widget delete failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Clock Widget could not be deleted.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_memo_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = dashboard_widget_validate_memo_title($input['memo_title'] ?? null);
    $body = dashboard_widget_validate_memo_body($input['memo_body'] ?? null);

    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($title === null || $body === null) {
        return api_validation_error('Memo title or body is invalid.');
    }

    try {
        $created = dashboard_widget_create_memo($userId, $location, $style, $width, $title, $body, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Memo Widget create failed: ' . $exception->getMessage());
        return api_error('memo_unavailable', 'Memo migration is required.', 503);
    }

    return api_success($created, 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_memo_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = dashboard_widget_validate_memo_title($input['memo_title'] ?? null);
    $body = dashboard_widget_validate_memo_body($input['memo_body'] ?? null);

    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($title === null || $body === null) {
        return api_validation_error('Memo title or body is invalid.');
    }

    try {
        if (!dashboard_widget_update_memo($userId, $widgetId, $style, $width, $title, $body, $height)) {
            return api_error('not_found', 'Memo Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Memo Widget update failed: ' . $exception->getMessage());
        return api_error('memo_unavailable', 'Memo could not be updated.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_memo_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!dashboard_widget_delete_memo($userId, $widgetId)) {
            return api_error('not_found', 'Memo Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Memo Widget delete failed: ' . $exception->getMessage());
        return api_error('memo_unavailable', 'Memo could not be deleted.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_task_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_task_config_from_input($input);
    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($config === null) {
        return api_validation_error('Task Widget settings are invalid.');
    }
    try {
        $widgetId = dashboard_widget_create_task_widget($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task Widget create failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_task_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_task_config_from_input($input);
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    if ($style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Task Widget settings are invalid.');
    }
    try {
        if (!dashboard_widget_update_task_widget($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Task Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task Widget update failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_task_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!dashboard_widget_delete_task_widget($userId, $widgetId)) {
            return api_error('not_found', 'Task Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Task Widget delete failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_create(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $title = dashboard_widget_validate_task_title($input['task_title'] ?? null);
    $dueDate = dashboard_widget_validate_task_due_date($input['task_due_date'] ?? null);
    $priority = dashboard_widget_validate_task_priority($input['task_priority'] ?? null);
    if ($widgetId === null || $title === null || $dueDate === null || $priority === null) {
        return api_validation_error('Task settings are invalid.');
    }
    try {
        $created = dashboard_widget_create_task_item($userId, $widgetId, $title, $dueDate, $priority);
    } catch (InvalidArgumentException|LengthException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task item create failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task could not be created.', 503);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    }
    return api_success($created, 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_update(int $userId, array $input): array
{
    $taskId = api_positive_int($input, 'task_id');
    $title = dashboard_widget_validate_task_title($input['task_title'] ?? null);
    $dueDate = dashboard_widget_validate_task_due_date($input['task_due_date'] ?? null);
    $priority = dashboard_widget_validate_task_priority($input['task_priority'] ?? null);
    if ($taskId === null || $title === null || $dueDate === null || $priority === null) {
        return api_validation_error('Task settings are invalid.');
    }
    try {
        if (!dashboard_widget_update_task_item($userId, $taskId, $title, $dueDate, $priority)) {
            return api_error('not_found', 'Task was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task item update failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task could not be updated.', 503);
    }
    return api_success(['task_id' => $taskId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_toggle(int $userId, array $input): array
{
    $taskId = api_positive_int($input, 'task_id');
    $completed = dashboard_widget_validate_boolean($input['task_completed'] ?? null);
    if ($taskId === null || $completed === null) {
        return api_validation_error('task_id or task_completed is invalid.');
    }
    try {
        if (!dashboard_widget_toggle_task_item($userId, $taskId, $completed)) {
            return api_error('not_found', 'Task was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Task item toggle failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task status could not be updated.', 503);
    }
    return api_success(['task_id' => $taskId, 'completed' => $completed]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_delete(int $userId, array $input): array
{
    $taskId = api_positive_int($input, 'task_id');
    if ($taskId === null) {
        return api_validation_error('task_id must be a positive integer.');
    }
    try {
        if (!dashboard_widget_delete_task_item($userId, $taskId)) {
            return api_error('not_found', 'Task was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Task item delete failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task could not be deleted.', 503);
    }
    return api_success(['task_id' => $taskId]);
}


/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calendar_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = calendar_widget_config_from_input($input);
    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Calendar Widget settings are invalid.');
    }
    try {
        $widgetId = dashboard_widget_create_calendar($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar Widget create failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calendar_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = calendar_widget_config_from_input($input);
    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Calendar Widget settings are invalid.');
    }
    try {
        if (!dashboard_widget_update_calendar($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Calendar Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar Widget update failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calendar_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!dashboard_widget_delete_calendar($userId, $widgetId)) {
            return api_error('not_found', 'Calendar Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Calendar Widget delete failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_game_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = mini_game_widget_config_from_input($input);
    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Game Widget settings are invalid.');
    }
    try {
        $widgetId = mini_game_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Game Widget create failed: ' . $exception->getMessage());
        return api_error('game_widget_unavailable', 'Game Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_game_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = mini_game_widget_config_from_input($input);
    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Game Widget settings are invalid.');
    }
    try {
        if (!mini_game_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Game Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Game Widget update failed: ' . $exception->getMessage());
        return api_error('game_widget_unavailable', 'Game Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_game_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!mini_game_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Game Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Game Widget delete failed: ' . $exception->getMessage());
        return api_error('game_widget_unavailable', 'Game Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_month_list(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $year = calendar_validate_year($input['calendar_year'] ?? null);
    $month = calendar_validate_month($input['calendar_month'] ?? null);
    if ($widgetId === null || $year === null || $month === null) {
        return api_validation_error('Calendar month request is invalid.');
    }
    try {
        $config = calendar_owned_widget_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Calendar Widget was not found.', 404);
        }
        return api_success(calendar_month_data($userId, $year, $month, $config['show_completed_tasks']));
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar month load failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar data could not be loaded.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_holiday_refresh(int $userId, array $input): array
{
    if ($userId <= 0) {
        return api_error('unauthenticated', 'Authentication is required.', 401);
    }
    try {
        $result = japanese_holiday_refresh();
        return api_success([
            'refreshed' => (bool) ($result['refreshed'] ?? false),
            'count' => max(0, (int) ($result['count'] ?? 0)),
        ]);
    } catch (Throwable $exception) {
        error_log('Holiday refresh failed: ' . $exception->getMessage());
        return api_success(['refreshed' => false, 'count' => 0]);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_event_create(int $userId, array $input): array
{
    $title = calendar_validate_event_title($input['calendar_event_title'] ?? null);
    $note = calendar_validate_event_note($input['calendar_event_note'] ?? '');
    $range = calendar_validate_event_range($input['calendar_event_start_date'] ?? null, $input['calendar_event_end_date'] ?? null);
    if ($title === null || $note === null || $range === null) {
        return api_validation_error('Calendar event settings are invalid.');
    }
    try {
        $eventId = calendar_create_event($userId, $title, $range[0], $range[1], $note);
    } catch (InvalidArgumentException|LengthException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar event create failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar event could not be created.', 503);
    }
    return api_success(['event_id' => $eventId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_event_update(int $userId, array $input): array
{
    $eventId = api_positive_int($input, 'event_id');
    $title = calendar_validate_event_title($input['calendar_event_title'] ?? null);
    $note = calendar_validate_event_note($input['calendar_event_note'] ?? '');
    $range = calendar_validate_event_range($input['calendar_event_start_date'] ?? null, $input['calendar_event_end_date'] ?? null);
    if ($eventId === null || $title === null || $note === null || $range === null) {
        return api_validation_error('Calendar event settings are invalid.');
    }
    try {
        if (!calendar_update_event($userId, $eventId, $title, $range[0], $range[1], $note)) {
            return api_error('not_found', 'Calendar event was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar event update failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar event could not be updated.', 503);
    }
    return api_success(['event_id' => $eventId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_event_delete(int $userId, array $input): array
{
    $eventId = api_positive_int($input, 'event_id');
    if ($eventId === null) {
        return api_validation_error('event_id must be a positive integer.');
    }
    try {
        if (!calendar_delete_event($userId, $eventId)) {
            return api_error('not_found', 'Calendar event was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Calendar event delete failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar event could not be deleted.', 503);
    }
    return api_success(['event_id' => $eventId]);
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
function api_account_email_update(int $userId, array $input): array
{
    $newEmail = api_string($input, 'new_email');
    $currentPassword = api_string($input, 'current_password');

    if (!auth_email_is_valid($newEmail)) {
        return api_validation_error('新しいメールアドレスを確認してください。');
    }
    if (!account_settings_current_password_is_valid($currentPassword)) {
        return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
    }

    $rate = api_account_settings_rate_status($userId);
    if ($rate['blocked']) {
        return api_error('account_settings_throttled', '試行回数が多いため、しばらく待ってから再度お試しください。', 429);
    }

    try {
        $result = account_settings_change_email($userId, $newEmail, $currentPassword);
    } catch (Throwable $exception) {
        error_log('Account email update failed.');
        return api_error('account_update_failed', 'メールアドレスを変更出来ませんでした。', 503);
    }

    $reason = (string) ($result['reason'] ?? '');
    if (($result['ok'] ?? false) !== true) {
        if ($reason === 'invalid_current_password') {
            api_account_settings_record_failure($userId);
            return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
        }
        if ($reason === 'identity_exists') {
            return api_error('email_in_use', 'このメールアドレスは使用出来ません。', 409);
        }
        if ($reason === 'email_unchanged') {
            return api_validation_error('新しいメールアドレスを入力してください。');
        }
        if ($reason === 'invalid_email') {
            return api_validation_error('新しいメールアドレスを確認してください。');
        }
        if ($reason === 'not_found') {
            return api_error('not_found', 'Account was not found.', 404);
        }
        return api_error('account_update_failed', 'メールアドレスを変更出来ませんでした。', 409);
    }

    api_account_settings_record_success($userId);
    $csrfToken = api_account_settings_rotate_session($userId);
    return api_success(['csrf_token' => $csrfToken]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_account_password_update(int $userId, array $input): array
{
    $currentPassword = api_string($input, 'current_password');
    $newPassword = api_string($input, 'new_password');
    $newPasswordConfirmation = api_string($input, 'new_password_confirmation');

    if (!account_settings_current_password_is_valid($currentPassword)) {
        return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
    }
    if (!auth_password_is_valid_for_registration($newPassword)) {
        return api_validation_error('新しいパスワードは' . AUTH_PASSWORD_MIN_LENGTH . '文字以上' . AUTH_PASSWORD_MAX_LENGTH . '文字以下で入力してください。');
    }
    if (!hash_equals($newPassword, $newPasswordConfirmation)) {
        return api_validation_error('新しいパスワードが一致していません。');
    }

    $rate = api_account_settings_rate_status($userId);
    if ($rate['blocked']) {
        return api_error('account_settings_throttled', '試行回数が多いため、しばらく待ってから再度お試しください。', 429);
    }

    try {
        $result = account_settings_change_password($userId, $currentPassword, $newPassword, $newPasswordConfirmation);
    } catch (Throwable $exception) {
        error_log('Account password update failed.');
        return api_error('account_update_failed', 'パスワードを変更出来ませんでした。', 503);
    }

    $reason = (string) ($result['reason'] ?? '');
    if (($result['ok'] ?? false) !== true) {
        if ($reason === 'invalid_current_password') {
            api_account_settings_record_failure($userId);
            return api_error('current_password_invalid', '現在のパスワードを確認してください。', 403);
        }
        if ($reason === 'password_mismatch') {
            return api_validation_error('新しいパスワードが一致していません。');
        }
        if ($reason === 'password_unchanged') {
            return api_validation_error('現在とは異なるパスワードを入力してください。');
        }
        if ($reason === 'invalid_password') {
            return api_validation_error('新しいパスワードは' . AUTH_PASSWORD_MIN_LENGTH . '文字以上' . AUTH_PASSWORD_MAX_LENGTH . '文字以下で入力してください。');
        }
        if ($reason === 'not_found') {
            return api_error('not_found', 'Account was not found.', 404);
        }
        return api_error('account_update_failed', 'パスワードを変更出来ませんでした。', 409);
    }

    api_account_settings_record_success($userId);
    persistent_login_clear_cookie();
    $csrfToken = api_account_settings_rotate_session($userId);
    return api_success(['csrf_token' => $csrfToken]);
}

/** @return array{blocked:bool,retry_after:int} */
function api_account_settings_rate_status(int $userId): array
{
    return login_throttle_status(account_settings_throttle_identity($userId), api_account_settings_remote_ip());
}

function api_account_settings_record_failure(int $userId): void
{
    login_throttle_record_failure(account_settings_throttle_identity($userId), api_account_settings_remote_ip());
}

function api_account_settings_record_success(int $userId): void
{
    login_throttle_record_success(account_settings_throttle_identity($userId), api_account_settings_remote_ip());
}

function api_account_settings_remote_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 128);
}

function api_account_settings_rotate_session(int $userId): string
{
    if (session_status() === PHP_SESSION_ACTIVE && app_session_user_id() === $userId) {
        app_session_login($userId);
        return app_csrf_token();
    }
    return '';
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

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_links_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = links_widget_config_from_input($input);
    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Links Widget settings are invalid.');
    }
    try {
        $widgetId = links_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Links Widget create failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Links migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_links_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = links_widget_config_from_input($input);
    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Links Widget settings are invalid.');
    }
    try {
        if (!links_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Links Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Links Widget update failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Links Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_links_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!links_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Links Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Links Widget delete failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Links Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_link_item_create(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $title = links_validate_item_title($input['link_title'] ?? null);
    $url = links_validate_item_url($input['link_url'] ?? null);
    if ($widgetId === null || $title === null || $url === null) {
        return api_validation_error('Link settings are invalid.');
    }
    try {
        $created = links_item_create($userId, $widgetId, $title, $url);
    } catch (InvalidArgumentException|LengthException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Link item create failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Link could not be created.', 503);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    }
    return api_success($created, 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_link_item_update(int $userId, array $input): array
{
    $linkId = api_positive_int($input, 'link_id');
    $title = links_validate_item_title($input['link_title'] ?? null);
    $url = links_validate_item_url($input['link_url'] ?? null);
    if ($linkId === null || $title === null || $url === null) {
        return api_validation_error('Link settings are invalid.');
    }
    try {
        if (!links_item_update($userId, $linkId, $title, $url)) {
            return api_error('not_found', 'Link was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Link item update failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Link could not be updated.', 503);
    }
    return api_success(['link_id' => $linkId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_link_item_delete(int $userId, array $input): array
{
    $linkId = api_positive_int($input, 'link_id');
    if ($linkId === null) {
        return api_validation_error('link_id must be a positive integer.');
    }
    try {
        if (!links_item_delete($userId, $linkId)) {
            return api_error('not_found', 'Link was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Link item delete failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Link could not be deleted.', 503);
    }
    return api_success(['link_id' => $linkId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_weather_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Weather Widget settings are invalid.');
    }
    $config = weather_widget_config_from_input($input);
    if ($config === null) {
        return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
    }
    try {
        $widgetId = weather_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Weather Widget create failed: ' . $exception->getMessage());
        return api_error('weather_unavailable', 'Weather Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_weather_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = weather_widget_validate_title($input['weather_title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($input['weather_location'] ?? null);
    $days = weather_widget_validate_forecast_days($input['weather_forecast_days'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null
        || $title === null || $locationQuery === null || $days === null) {
        return api_validation_error('Weather Widget settings are invalid.');
    }

    try {
        $currentConfig = weather_widget_owned_config($userId, $widgetId);
        if ($currentConfig === null) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }

        if (hash_equals((string) $currentConfig['location_query'], $locationQuery)) {
            $config = $currentConfig;
            $config['title'] = $title;
            $config['forecast_days'] = $days;
        } else {
            $config = weather_widget_config_from_input($input);
            if ($config === null) {
                return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
            }
        }

        if (!weather_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Weather Widget update failed: ' . $exception->getMessage());
        return api_error('weather_unavailable', 'Weather Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_weather_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!weather_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Weather Widget delete failed: ' . $exception->getMessage());
        return api_error('weather_unavailable', 'Weather Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_weather_forecast(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $force = dashboard_widget_validate_boolean($input['force'] ?? '0');
    if ($widgetId === null || $force === null) {
        return api_validation_error('Weather request is invalid.');
    }
    try {
        $config = weather_widget_owned_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }
        return api_success(['forecast' => weather_forecast($config, $force)]);
    } catch (RuntimeException $exception) {
        error_log('Weather forecast failed: ' . $exception->getMessage());
        return api_error('weather_fetch_failed', '天気情報を取得出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_sun_moon_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Sun / Moon Widget settings are invalid.');
    }
    $config = sun_moon_widget_config_from_input($input);
    if ($config === null) {
        return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
    }
    try {
        $widgetId = sun_moon_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget create failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_sun_moon_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = sun_moon_widget_validate_title($input['sun_moon_title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($input['sun_moon_location'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null
        || $title === null || $locationQuery === null) {
        return api_validation_error('Sun / Moon Widget settings are invalid.');
    }

    try {
        $currentConfig = sun_moon_widget_owned_config($userId, $widgetId);
        if ($currentConfig === null) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
        if (hash_equals((string) $currentConfig['location_query'], $locationQuery)) {
            $config = $currentConfig;
            $config['title'] = $title;
        } else {
            $config = sun_moon_widget_config_from_input($input);
            if ($config === null) {
                return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
            }
        }
        if (!sun_moon_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget update failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_sun_moon_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!sun_moon_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget delete failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_sun_moon_current(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('Sun / Moon request is invalid.');
    }
    try {
        $config = sun_moon_widget_owned_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
        return api_success([
            'widget_id' => $widgetId,
            'sun_moon' => sun_moon_current($config),
        ]);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget read failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be read.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_air_quality_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Air Quality Widget settings are invalid.');
    }
    $config = air_quality_widget_config_from_input($input);
    if ($config === null) {
        return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
    }
    try {
        $widgetId = air_quality_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Air Quality Widget create failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_air_quality_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = air_quality_widget_validate_title($input['air_quality_title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($input['air_quality_location'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null
        || $title === null || $locationQuery === null) {
        return api_validation_error('Air Quality Widget settings are invalid.');
    }

    try {
        $currentConfig = air_quality_widget_owned_config($userId, $widgetId);
        if ($currentConfig === null) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
        if (hash_equals((string) $currentConfig['location_query'], $locationQuery)) {
            $config = $currentConfig;
            $config['title'] = $title;
        } else {
            $config = air_quality_widget_config_from_input($input);
            if ($config === null) {
                return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
            }
        }
        if (!air_quality_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Air Quality Widget update failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_air_quality_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!air_quality_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Air Quality Widget delete failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_air_quality_current(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $force = dashboard_widget_validate_boolean($input['force'] ?? '0');
    if ($widgetId === null || $force === null) {
        return api_validation_error('Air Quality request is invalid.');
    }
    try {
        $config = air_quality_widget_owned_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
        return api_success([
            'widget_id' => $widgetId,
            'air_quality' => air_quality_current($config, $force),
        ]);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Air Quality Widget read failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be read.', 503);
    } catch (RuntimeException $exception) {
        error_log('Air Quality fetch failed: ' . $exception->getMessage());
        return api_error('air_quality_fetch_failed', '大気情報を取得出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_earthquake_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Earthquake Widget settings are invalid.');
    }
    try {
        $widgetId = earthquake_widget_create($userId, $location, $style, $width, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Earthquake Widget create failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_earthquake_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Earthquake Widget settings are invalid.');
    }
    try {
        if (!earthquake_widget_update($userId, $widgetId, $style, $width, $height)) {
            return api_error('not_found', 'Earthquake Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Earthquake Widget update failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_earthquake_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!earthquake_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Earthquake Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Earthquake Widget delete failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_earthquake_latest(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $force = dashboard_widget_validate_boolean($input['force'] ?? '0');
    if ($widgetId === null || $force === null) {
        return api_validation_error('Earthquake request is invalid.');
    }
    try {
        $widget = earthquake_widget_owned($userId, $widgetId);
        if ($widget === null) {
            return api_error('not_found', 'Earthquake Widget was not found.', 404);
        }
        return api_success([
            'widget_id' => $widgetId,
            'earthquake' => earthquake_latest($force),
        ]);
    } catch (PDOException $exception) {
        error_log('Earthquake Widget read failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be read.', 503);
    } catch (RuntimeException $exception) {
        error_log('Earthquake information fetch failed: ' . $exception->getMessage());
        return api_error('earthquake_fetch_failed', '地震情報を取得出来ませんでした。', 503);
    }
}

