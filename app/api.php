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

// V1.19-B: keep this file as the stable API facade/dispatcher and load broad action groups here.
require_once __DIR__ . '/api/content.php';
require_once __DIR__ . '/api/dashboard.php';
require_once __DIR__ . '/api/account.php';
require_once __DIR__ . '/api/integrations.php';
require_once __DIR__ . '/api/opml.php';

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
        'feed.fetch' => api_feed_fetch_with_metadata_title($userId, $input),
        'feed.new.clear' => api_feed_new_clear($userId, $input),
        'feed.keyword.create' => api_feed_keyword_create($userId, $input),
        'feed.keyword.delete' => api_feed_keyword_delete($userId, $input),
        'opml.list' => api_opml_dispatch($action, $userId, $input),
        'opml.import' => api_opml_dispatch($action, $userId, $input),
        'opml.export' => api_opml_dispatch($action, $userId, $input),
        'widget.list' => api_widget_list($userId, $input),
        'widget.reorder' => api_widget_reorder($userId, $input),
        'widget.search.create' => api_widget_search_create($userId, $input),
        'widget.search.update' => api_widget_search_update($userId, $input),
        'widget.search.delete' => api_widget_search_delete($userId, $input),
        'widget.search.fetch' => api_widget_search_fetch($userId, $input),
        'widget.clock.create' => api_widget_clock_create($userId, $input),
        'widget.clock.update' => api_widget_clock_update($userId, $input),
        'widget.clock.delete' => api_widget_clock_delete($userId, $input),
        'widget.calculator.create' => api_widget_calculator_create($userId, $input),
        'widget.calculator.update' => api_widget_calculator_update($userId, $input),
        'widget.calculator.delete' => api_widget_calculator_delete($userId, $input),
        'widget.blindspot.create' => api_widget_blind_spot_create($userId, $input),
        'widget.blindspot.update' => api_widget_blind_spot_update($userId, $input),
        'widget.blindspot.delete' => api_widget_blind_spot_delete($userId, $input),
        'blindspot.fetch' => api_blind_spot_fetch($userId, $input),
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
        'widget.healthprobe.create' => api_widget_health_probe_create($userId, $input),
        'widget.healthprobe.update' => api_widget_health_probe_update($userId, $input),
        'widget.healthprobe.delete' => api_widget_health_probe_delete($userId, $input),
        'widget.x.create' => api_widget_x_create($userId, $input),
        'widget.x.update' => api_widget_x_update($userId, $input),
        'widget.x.delete' => api_widget_x_delete($userId, $input),
        'x.config.status' => api_x_config_status($userId, $input),
        'x.timeline.fetch' => api_x_timeline_fetch($userId, $input),
        'calendar.month.list' => api_calendar_month_list($userId, $input),
        'calendar.holiday.refresh' => api_calendar_holiday_refresh($userId, $input),
        'calendar.event.create' => api_calendar_event_create($userId, $input),
        'calendar.event.update' => api_calendar_event_update($userId, $input),
        'calendar.event.delete' => api_calendar_event_delete($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}


