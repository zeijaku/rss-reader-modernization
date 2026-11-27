<?php

declare(strict_types=1);

const CALENDAR_SEARCH_MAX_DAYS = 366;
const CALENDAR_SEARCH_LIMIT = 50;

/** @return array{results:list<array<string,mixed>>,has_more:bool} */
function calendar_search(int $ownerId, array $input): array
{
    $startDate = calendar_validate_date($input['search_start'] ?? null);
    $endDate = calendar_validate_date($input['search_end'] ?? null);
    $mode = $input['search_mode'] ?? null;
    if ($startDate === null || $endDate === null || $startDate > $endDate
        || !in_array($mode, ['events', 'marks'], true)) {
        throw new InvalidArgumentException('検索対象と日付を確認してください。');
    }
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    if ($start->diff($end)->days >= CALENDAR_SEARCH_MAX_DAYS) {
        throw new InvalidArgumentException('検索期間は366日以内で指定してください。');
    }
    $query = app_validate_text($input['search_query'] ?? null, $mode === 'marks' ? 12 : 128, $mode === 'marks');
    if ($query === null) {
        throw new InvalidArgumentException($mode === 'marks'
            ? 'マーク名は12文字以内で入力してください。'
            : '検索語を128文字以内で入力してください。');
    }

    if ($mode === 'marks') {
        $marks = calendar_day_mark_list($ownerId, $startDate, $endDate);
        if ($marks === null) {
            throw new OutOfBoundsException('日付マークを利用するにはDBマイグレーション036を適用してください。');
        }
        $results = [];
        foreach (array_reverse($marks, true) as $date => $mark) {
            if ($query !== '' && $mark['label'] !== $query) {
                continue;
            }
            $results[] = ['date' => $date, 'label' => $mark['label'], 'note' => $mark['note']];
            if (count($results) > CALENDAR_SEARCH_LIMIT) {
                break;
            }
        }
    } else {
        $results = calendar_search_events($ownerId, $start, $end, $query);
    }

    return [
        'results' => array_slice($results, 0, CALENDAR_SEARCH_LIMIT),
        'has_more' => count($results) > CALENDAR_SEARCH_LIMIT,
    ];
}

function calendar_search_events(int $ownerId, DateTimeImmutable $start, DateTimeImmutable $end, string $query): array
{
    $matches = [];
    $seen = [];
    for ($chunkStart = $start; $chunkStart <= $end; $chunkStart = $chunkEnd->modify('+1 day')) {
        $chunkEnd = min($end, $chunkStart->modify('+' . (CALENDAR_RANGE_MAX_DAYS - 1) . ' days'));
        $from = $chunkStart->format('Y-m-d');
        $to = $chunkEnd->format('Y-m-d');
        $ics = calendar_ics_range($ownerId, $from, $to);
        foreach (array_merge(calendar_range_event_list($ownerId, $from, $to), $ics['events']) as $event) {
            $key = $event['occurrence_key'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            foreach (['title', 'location', 'note'] as $field) {
                $text = (string) ($event[$field] ?? '');
                $found = function_exists('mb_stripos') ? mb_stripos($text, $query, 0, 'UTF-8') : stripos($text, $query);
                if ($found !== false) {
                    $matches[] = $event;
                    break;
                }
            }
        }
        usort($matches, static function (array $left, array $right): int {
            return strcmp($right['occurrence_start_date'], $left['occurrence_start_date'])
                ?: strcmp((string) $left['start_time'], (string) $right['start_time'])
                ?: strcmp($left['occurrence_key'], $right['occurrence_key']);
        });
        $matches = array_slice($matches, 0, CALENDAR_SEARCH_LIMIT + 1);
    }

    // Local details need the same source metadata as the Calendar grid. ICS
    // results already carry their subscription metadata.
    $local = array_values(array_filter($matches, static fn(array $event): bool => empty($event['external_read_only'])));
    $localByKey = $local === [] ? [] : array_column(calendar_source_attach_to_events($ownerId, $local)['events'], null, 'occurrence_key');
    foreach ($matches as &$match) {
        $match = $localByKey[$match['occurrence_key']] ?? $match;
    }
    unset($match);
    return $matches;
}
