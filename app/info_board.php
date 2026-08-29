<?php

declare(strict_types=1);

/**
 * V1.26-B Information Board backend.
 *
 * The first release uses RSS only. Keep the stored source_type explicit so a
 * later phase can add another source without changing the current RSS fetch
 * pipeline or introducing an article scraper.
 *
 * Like V1.20-E All RSS Recent, this backend stores a private mode marker in an
 * existing Search Widget row. This keeps V1.26-B schema-free while preserving
 * the current dashboard placement/width/height machinery.
 */

const INFO_BOARD_MODE = 'info_board';
const INFO_BOARD_SOURCE_TYPE = 'rss';
const INFO_BOARD_QUERY = "Information Board\u{2060}";
const INFO_BOARD_SUMMARY_HARD_LIMIT = 3000;

/** @return list<int> */
function info_board_allowed_limits(): array
{
    return [5, 10, 20];
}

/** @return list<string> */
function info_board_allowed_speeds(): array
{
    return ['slow', 'normal', 'fast'];
}

/** @return list<int> */
function info_board_allowed_summary_max(): array
{
    return [100, 200, 300, INFO_BOARD_SUMMARY_HARD_LIMIT];
}

function info_board_validate_feed_mode(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['all', 'specific'], true) ? $value : null;
}

function info_board_validate_limit(mixed $value): ?int
{
    $limit = app_validate_positive_int($value);
    return $limit !== null && in_array($limit, info_board_allowed_limits(), true) ? $limit : null;
}

function info_board_validate_speed(mixed $value): ?string
{
    return is_string($value) && in_array($value, info_board_allowed_speeds(), true) ? $value : null;
}

function info_board_validate_summary_max(mixed $value): ?int
{
    $max = app_validate_positive_int($value);
    return $max !== null && in_array($max, info_board_allowed_summary_max(), true) ? $max : null;
}

/** @return array{schema:int,mode:string,source_type:string,feed_mode:string,feed_id:?int,limit:int,speed:string,show_summary:bool,summary_max:int,query:string,scope:string,condition:string,category:string} */
function info_board_config(
    string $feedMode,
    ?int $feedId,
    int $limit,
    string $speed,
    bool $showSummary,
    int $summaryMax
): array {
    return [
        'schema' => 1,
        'mode' => INFO_BOARD_MODE,
        'source_type' => INFO_BOARD_SOURCE_TYPE,
        'feed_mode' => $feedMode,
        'feed_id' => $feedMode === 'specific' ? $feedId : null,
        'limit' => $limit,
        'speed' => $speed,
        'show_summary' => $showSummary,
        'summary_max' => $summaryMax,
        // Keep Search Feed's storage contract valid because V1.26-B deliberately
        // reuses that dashboard row type, following the V1.20-E precedent.
        'query' => INFO_BOARD_QUERY,
        'scope' => 'owned',
        'condition' => 'or',
        'category' => 'all',
    ];
}

/** @return array{schema:int,mode:string,source_type:string,feed_mode:string,feed_id:?int,limit:int,speed:string,show_summary:bool,summary_max:int,query:string,scope:string,condition:string,category:string}|null */
function info_board_config_from_input(array $input): ?array
{
    $feedMode = info_board_validate_feed_mode($input['info_board_feed_mode'] ?? null);
    $feedId = app_validate_positive_int($input['info_board_feed_id'] ?? null);
    $limit = info_board_validate_limit($input['info_board_limit'] ?? null);
    $speed = info_board_validate_speed($input['info_board_speed'] ?? null);
    $showSummary = dashboard_widget_validate_boolean($input['info_board_show_summary'] ?? null);
    $summaryMax = info_board_validate_summary_max($input['info_board_summary_max'] ?? null);

    if ($feedMode === null || $limit === null || $speed === null || $showSummary === null || $summaryMax === null) {
        return null;
    }
    if ($feedMode === 'specific' && $feedId === null) {
        return null;
    }

    return info_board_config($feedMode, $feedMode === 'specific' ? $feedId : null, $limit, $speed, $showSummary, $summaryMax);
}

/** @return array{schema:int,mode:string,source_type:string,feed_mode:string,feed_id:?int,limit:int,speed:string,show_summary:bool,summary_max:int,query:string,scope:string,condition:string,category:string}|null */
function info_board_config_from_storage(mixed $value): ?array
{
    $config = dashboard_widget_decode_config($value);
    if (($config['schema'] ?? null) !== 1
        || ($config['mode'] ?? null) !== INFO_BOARD_MODE
        || ($config['source_type'] ?? null) !== INFO_BOARD_SOURCE_TYPE
        || ($config['query'] ?? null) !== INFO_BOARD_QUERY) {
        return null;
    }

    $feedMode = info_board_validate_feed_mode($config['feed_mode'] ?? null);
    $feedId = app_validate_positive_int($config['feed_id'] ?? null);
    $limit = info_board_validate_limit($config['limit'] ?? null);
    $speed = info_board_validate_speed($config['speed'] ?? null);
    $showSummary = dashboard_widget_validate_boolean($config['show_summary'] ?? null);
    $summaryMax = info_board_validate_summary_max($config['summary_max'] ?? null);

    if ($feedMode === null || $limit === null || $speed === null || $showSummary === null || $summaryMax === null) {
        return null;
    }
    if ($feedMode === 'specific' && $feedId === null) {
        return null;
    }

    return info_board_config($feedMode, $feedMode === 'specific' ? $feedId : null, $limit, $speed, $showSummary, $summaryMax);
}

/** @return array<string,mixed>|null */
function info_board_owned_widget(int $ownerId, int $widgetId): ?array
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
    if (!is_array($row) || info_board_config_from_storage($row['widget_config'] ?? null) === null) {
        return null;
    }
    return $row;
}

/** @return array{source_id:int,url:string,name:string}|null */
function info_board_owned_source(int $ownerId, int $contentId): ?array
{
    if ($ownerId <= 0 || $contentId <= 0) {
        return null;
    }

    $stmt = conn_db()->prepare(
        'SELECT content_id, content_value FROM ' . db_table_identifier('content') . ' '
        . 'WHERE content_id = :content_id AND content_owner = :owner AND content_flag = 0'
    );
    $stmt->execute([':content_id' => $contentId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $sourceId = app_validate_positive_int($row['content_id'] ?? null);
    $url = app_validate_feed_url($row['content_value'] ?? null);
    if ($sourceId === null || $url === null) {
        return null;
    }

    return ['source_id' => $sourceId, 'url' => $url, 'name' => ''];
}

/** @return list<array{source_id:int,url:string,name:string}> */
function info_board_sources(int $ownerId, array $config): array
{
    if (($config['source_type'] ?? null) !== INFO_BOARD_SOURCE_TYPE) {
        return [];
    }

    if (($config['feed_mode'] ?? null) === 'specific') {
        $feedId = app_validate_positive_int($config['feed_id'] ?? null);
        if ($feedId === null) {
            return [];
        }
        $source = info_board_owned_source($ownerId, $feedId);
        return $source === null ? [] : [$source];
    }

    $sources = search_feed_owned_sources($ownerId);
    $unique = [];
    $seen = [];
    foreach ($sources as $source) {
        if (!is_array($source)) {
            continue;
        }
        $url = (string) ($source['url'] ?? '');
        if ($url === '' || isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $unique[] = $source;
    }
    return $unique;
}

function info_board_source_allowed(int $ownerId, array $config): bool
{
    if (($config['feed_mode'] ?? null) !== 'specific') {
        return true;
    }
    $feedId = app_validate_positive_int($config['feed_id'] ?? null);
    return $feedId !== null && info_board_owned_source($ownerId, $feedId) !== null;
}

function info_board_create(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    int $height,
    array $config
): int {
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || info_board_config_from_storage(dashboard_widget_encode_config($config)) === null
        || !info_board_source_allowed($ownerId, $config)) {
        throw new InvalidArgumentException('Information Board Widget settings are invalid.');
    }

    return search_feed_create($ownerId, $location, $style, $width, $config, $height);
}

function info_board_update(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    int $height,
    array $config
): bool {
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || info_board_config_from_storage(dashboard_widget_encode_config($config)) === null
        || !info_board_source_allowed($ownerId, $config)) {
        throw new InvalidArgumentException('Information Board Widget settings are invalid.');
    }
    if (info_board_owned_widget($ownerId, $widgetId) === null) {
        return false;
    }

    return search_feed_update($ownerId, $widgetId, $style, $width, $config, $height);
}

function info_board_delete(int $ownerId, int $widgetId): bool
{
    if (info_board_owned_widget($ownerId, $widgetId) === null) {
        return false;
    }
    return search_feed_delete($ownerId, $widgetId);
}

function info_board_single_line_text(mixed $value, int $maxLength): string
{
    $text = api_feed_text($value, $maxLength);
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

function info_board_text_length(string $value): int
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

/** @return array{kind:string,source_type:string,title:string,summary:string,text:string,link:string,date:string,source_title:string}|null */
function info_board_item_payload(array $item, string $sourceTitle, array $config): ?array
{
    $title = info_board_single_line_text($item['title'] ?? '', 512);
    if ($title === '') {
        return null;
    }

    $summary = '';
    if (($config['show_summary'] ?? false) === true) {
        $summaryMax = info_board_validate_summary_max($config['summary_max'] ?? null) ?? 200;
        $description = info_board_single_line_text($item['description'] ?? '', $summaryMax);
        $content = info_board_single_line_text($item['content'] ?? '', $summaryMax);
        $summary = $description;
        if ($summary === '') {
            $summary = $content;
        } elseif (info_board_text_length($content) > info_board_text_length($summary)) {
            $summary = $content;
        }
        if ($summary === $title) {
            $summary = '';
        }
    }

    $link = app_validate_external_link($item['link'] ?? null, 2048) ?? '';
    $date = info_board_single_line_text($item['date'] ?? '', 64);
    $safeSourceTitle = info_board_single_line_text($sourceTitle, 512);

    return [
        'kind' => 'NEWS',
        'source_type' => INFO_BOARD_SOURCE_TYPE,
        'title' => $title,
        'summary' => $summary,
        'text' => $summary === '' ? $title : '【' . $title . '】' . $summary,
        'link' => $link,
        'date' => $date,
        'source_title' => $safeSourceTitle === '' ? 'RSS' : $safeSourceTitle,
    ];
}

function info_board_item_timestamp(array $item): int
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
function info_board_execute(int $ownerId, int $widgetId): array
{
    $row = info_board_owned_widget($ownerId, $widgetId);
    if ($row === null) {
        return ['ok' => false, 'code' => 'not_found'];
    }

    $config = info_board_config_from_storage($row['widget_config'] ?? null);
    if ($config === null) {
        return ['ok' => false, 'code' => 'invalid_config'];
    }

    $sources = info_board_sources($ownerId, $config);
    if ($config['feed_mode'] === 'specific' && $sources === []) {
        return ['ok' => false, 'code' => 'not_found'];
    }

    $service = FeedFetchService::fromRuntimeConfiguration();
    $items = [];
    $seenItems = [];
    $failed = 0;
    $sequence = 0;

    foreach ($sources as $sourceRow) {
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
            $sourceTitle = info_board_single_line_text($channel['title'] ?? '', 512);
            if ($sourceTitle === '') {
                $sourceTitle = 'RSS';
            }

            foreach (is_array($feed['item'] ?? null) ? $feed['item'] : [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $displayConfig = $config;
                $displayConfig['summary_max'] = INFO_BOARD_SUMMARY_HARD_LIMIT;
                $payload = info_board_item_payload($item, $sourceTitle, $displayConfig);
                if ($payload === null) {
                    continue;
                }

                $key = hash('sha256', $payload['link'] . "\n" . $payload['title']);
                if (isset($seenItems[$key])) {
                    continue;
                }
                $seenItems[$key] = true;
                $sequence++;
                $payload['_info_board_timestamp'] = info_board_item_timestamp($payload);
                $payload['_info_board_sequence'] = $sequence;
                $items[] = $payload;
            }
        } catch (Throwable) {
            $failed++;
        }
    }

    usort($items, static function (array $left, array $right): int {
        $leftTime = (int) ($left['_info_board_timestamp'] ?? 0);
        $rightTime = (int) ($right['_info_board_timestamp'] ?? 0);
        if ($leftTime !== $rightTime) {
            return $rightTime <=> $leftTime;
        }
        return (int) ($left['_info_board_sequence'] ?? 0) <=> (int) ($right['_info_board_sequence'] ?? 0);
    });

    $items = array_slice($items, 0, $config['limit']);
    foreach ($items as &$item) {
        unset($item['_info_board_timestamp'], $item['_info_board_sequence']);
    }
    unset($item);

    return [
        'ok' => true,
        'source_type' => INFO_BOARD_SOURCE_TYPE,
        'feed_mode' => $config['feed_mode'],
        'feed_id' => $config['feed_id'],
        'items' => $items,
        'source_count' => count($sources),
        'failed_count' => $failed,
        'limit' => $config['limit'],
    ];
}
