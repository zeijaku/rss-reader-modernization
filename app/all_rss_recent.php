<?php

declare(strict_types=1);

/**
 * V1.20-E: All RSS Recent Widget.
 *
 * The widget deliberately reuses the existing `search` dashboard widget type
 * so no DB schema/type migration is required. A private config marker keeps it
 * distinguishable from a normal Search Feed while the visible query label
 * remains compatible with the existing Search Feed card renderer.
 */

const ALL_RSS_RECENT_MODE = 'all_rss_recent';
const ALL_RSS_RECENT_QUERY = "全RSS新着\u{2060}";

/** @return list<int> */
function all_rss_recent_allowed_limits(): array
{
    return [5, 10, 20, 30];
}

function all_rss_recent_validate_limit(mixed $value): ?int
{
    $limit = app_validate_positive_int($value);
    return $limit !== null && in_array($limit, all_rss_recent_allowed_limits(), true) ? $limit : null;
}

/** @return array{schema:int,mode:string,query:string,scope:string,condition:string,limit:int,category:string} */
function all_rss_recent_config(int $limit): array
{
    return [
        'schema' => 1,
        'mode' => ALL_RSS_RECENT_MODE,
        'query' => ALL_RSS_RECENT_QUERY,
        'scope' => 'owned',
        'condition' => 'or',
        'limit' => $limit,
        'category' => 'all',
    ];
}

/** @return array{schema:int,mode:string,query:string,scope:string,condition:string,limit:int,category:string}|null */
function all_rss_recent_config_from_storage(mixed $value): ?array
{
    $config = dashboard_widget_decode_config($value);
    $mode = is_string($config['mode'] ?? null) ? $config['mode'] : '';
    $query = is_string($config['query'] ?? null) ? $config['query'] : '';
    $limit = all_rss_recent_validate_limit($config['limit'] ?? null);

    if ($mode !== ALL_RSS_RECENT_MODE || $query !== ALL_RSS_RECENT_QUERY || $limit === null) {
        return null;
    }

    return all_rss_recent_config($limit);
}

/** @return array<string,mixed>|null */
function all_rss_recent_owned_widget(int $ownerId, int $widgetId): ?array
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return null;
    }

    $stmt = conn_db()->prepare(
        'SELECT * FROM ' . db_table_identifier('dashboard_widget') . ' '
        . "WHERE widget_id = :id AND widget_owner = :owner AND widget_type = 'search' AND widget_flag = 0"
    );
    $stmt->execute([':id' => $widgetId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    if (!is_array($row) || all_rss_recent_config_from_storage($row['widget_config'] ?? null) === null) {
        return null;
    }
    return $row;
}

function all_rss_recent_create(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    int $height,
    int $limit
): int {
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || all_rss_recent_validate_limit($limit) === null) {
        throw new InvalidArgumentException('All RSS Recent Widget settings are invalid.');
    }

    return search_feed_create(
        $ownerId,
        $location,
        $style,
        $width,
        all_rss_recent_config($limit),
        $height
    );
}

function all_rss_recent_update(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    int $height,
    int $limit
): bool {
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || all_rss_recent_validate_limit($limit) === null) {
        throw new InvalidArgumentException('All RSS Recent Widget settings are invalid.');
    }
    if (all_rss_recent_owned_widget($ownerId, $widgetId) === null) {
        return false;
    }

    return search_feed_update(
        $ownerId,
        $widgetId,
        $style,
        $width,
        all_rss_recent_config($limit),
        $height
    );
}

function all_rss_recent_delete(int $ownerId, int $widgetId): bool
{
    if (all_rss_recent_owned_widget($ownerId, $widgetId) === null) {
        return false;
    }
    return search_feed_delete($ownerId, $widgetId);
}

function all_rss_recent_item_timestamp(array $item): int
{
    $date = trim((string) ($item['date'] ?? ''));
    if ($date === '') {
        return 0;
    }
    try {
        return (new DateTimeImmutable($date))->getTimestamp();
    } catch (Throwable) {
        return 0;
    }
}

/** @return array<string,mixed> */
function all_rss_recent_execute(int $ownerId, int $widgetId): array
{
    $row = all_rss_recent_owned_widget($ownerId, $widgetId);
    if ($row === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }

    $config = all_rss_recent_config_from_storage($row['widget_config'] ?? null);
    if ($config === null) {
        return ['ok' => false, 'code' => 'invalid_config'];
    }

    $sources = search_feed_owned_sources($ownerId);
    $uniqueSources = [];
    $seenUrls = [];
    foreach ($sources as $source) {
        $url = (string) ($source['url'] ?? '');
        if ($url === '' || isset($seenUrls[$url])) {
            continue;
        }
        $seenUrls[$url] = true;
        $uniqueSources[] = $source;
    }

    $service = FeedFetchService::fromRuntimeConfiguration();
    $items = [];
    $seenItems = [];
    $failed = 0;
    $sequence = 0;

    foreach ($uniqueSources as $sourceRow) {
        try {
            $source = FeedSource::fromValidatedValues(
                (int) $sourceRow['source_id'],
                $ownerId,
                (string) $sourceRow['url']
            );
            $loaded = $service->load($source);
            if (($loaded['ok'] ?? false) !== true) {
                $failed++;
                continue;
            }

            $rawFeed = is_array($loaded['result_feed'] ?? null) ? $loaded['result_feed'] : [];
            $effectiveUrl = is_string($loaded['effective_url'] ?? null)
                ? $loaded['effective_url']
                : (string) $sourceRow['url'];
            $feed = api_safe_feed_payload($rawFeed, $effectiveUrl);
            $channel = is_array($feed['channel'] ?? null) ? $feed['channel'] : [];
            $sourceTitle = trim((string) ($channel['title'] ?? ''));
            if ($sourceTitle === '') {
                $sourceTitle = 'RSS';
            }

            $feedItems = is_array($feed['item'] ?? null) ? $feed['item'] : [];
            foreach ($feedItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = trim((string) ($item['title'] ?? ''));
                $link = trim((string) ($item['link'] ?? ''));
                if ($title === '' && $link === '') {
                    continue;
                }

                $key = hash('sha256', $link . "\n" . $title);
                if (isset($seenItems[$key])) {
                    continue;
                }
                $seenItems[$key] = true;
                $sequence++;
                $item['source_title'] = $sourceTitle;
                $item['_all_rss_timestamp'] = all_rss_recent_item_timestamp($item);
                $item['_all_rss_sequence'] = $sequence;
                $items[] = $item;
            }
        } catch (Throwable) {
            $failed++;
        }
    }

    usort($items, static function (array $left, array $right): int {
        $leftTime = (int) ($left['_all_rss_timestamp'] ?? 0);
        $rightTime = (int) ($right['_all_rss_timestamp'] ?? 0);
        if ($leftTime !== $rightTime) {
            return $rightTime <=> $leftTime;
        }
        return (int) ($right['_all_rss_sequence'] ?? 0) <=> (int) ($left['_all_rss_sequence'] ?? 0);
    });

    $limit = $config['limit'];
    $items = array_slice($items, 0, $limit);
    foreach ($items as &$item) {
        unset($item['_all_rss_timestamp'], $item['_all_rss_sequence']);
    }
    unset($item);

    return [
        'ok' => true,
        'query' => ALL_RSS_RECENT_QUERY,
        'items' => $items,
        'source_count' => count($uniqueSources),
        'failed_count' => $failed,
        'limit' => $limit,
    ];
}
