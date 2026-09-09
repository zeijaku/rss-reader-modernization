<?php

declare(strict_types=1);

const CALENDAR_UPCOMING_DAYS = 14;
const CALENDAR_UPCOMING_LIMIT = 8;

/** @return list<array<string,mixed>> */
function calendar_event_upcoming_list(int $ownerId, string $today): array
{
    $windowStart = calendar_recurrence_date($today);
    if ($ownerId <= 0 || !$windowStart) {
        throw new InvalidArgumentException('Calendar upcoming range is invalid.');
    }
    $windowEnd = $windowStart->modify('+' . (CALENDAR_UPCOMING_DAYS - 1) . ' days');
    $windowStartValue = $windowStart->format('Y-m-d');
    $windowEndValue = $windowEnd->format('Y-m-d');
    $events = calendar_range_event_list($ownerId, $windowStartValue, $windowEndValue);

    usort($events, static function (array $left, array $right) use ($windowStartValue): int {
        $leftDate = max($windowStartValue, (string) ($left['occurrence_start_date'] ?? ''));
        $rightDate = max($windowStartValue, (string) ($right['occurrence_start_date'] ?? ''));
        $leftTime = ($left['all_day'] ?? true) === true ? '' : (string) ($left['start_time'] ?? '');
        $rightTime = ($right['all_day'] ?? true) === true ? '' : (string) ($right['start_time'] ?? '');
        if ((string) ($left['occurrence_start_date'] ?? '') < $windowStartValue) {
            $leftTime = '';
        }
        if ((string) ($right['occurrence_start_date'] ?? '') < $windowStartValue) {
            $rightTime = '';
        }
        return [$leftDate, $leftTime, (int) ($left['event_id'] ?? 0)]
            <=> [$rightDate, $rightTime, (int) ($right['event_id'] ?? 0)];
    });

    return array_slice($events, 0, CALENDAR_UPCOMING_LIMIT);
}
