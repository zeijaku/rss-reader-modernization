(function (window) {
    'use strict';

    function isoDate(value) {
        var text = String(value || '');
        var parts;
        var date;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
            return '';
        }
        parts = text.split('-').map(Number);
        date = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
        return date.getUTCFullYear() === parts[0]
            && date.getUTCMonth() + 1 === parts[1]
            && date.getUTCDate() === parts[2] ? text : '';
    }

    function parseDate(value) {
        var valid = isoDate(value);
        var parts = valid === '' ? [] : valid.split('-').map(Number);
        return valid === '' ? null : new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
    }

    function formatDate(date) {
        return date.getUTCFullYear() + '-'
            + String(date.getUTCMonth() + 1).padStart(2, '0') + '-'
            + String(date.getUTCDate()).padStart(2, '0');
    }

    function datesBetween(start, end) {
        var current = parseDate(start);
        var last = parseDate(end);
        var dates = [];
        var guard = 0;
        if (!current || !last || current.getTime() > last.getTime()) {
            return dates;
        }
        while (current.getTime() <= last.getTime() && guard < 42) {
            dates.push(formatDate(current));
            current.setUTCDate(current.getUTCDate() + 1);
            guard += 1;
        }
        return dates;
    }

    function splitByWeek(start, end) {
        var dates = datesBetween(start, end);
        var segments = [];
        var segment = [];
        dates.forEach(function (date) {
            var parsed = parseDate(date);
            segment.push(date);
            if (parsed && parsed.getUTCDay() === 6) {
                segments.push(segment);
                segment = [];
            }
        });
        if (segment.length > 0) {
            segments.push(segment);
        }
        return segments;
    }

    function lowestFreeLane(map, dates) {
        var lane = 0;
        // Range API bounds events, exceptions and Tasks independently. Keep
        // the client ceiling above their combined maximum without allowing an
        // unbounded loop when malformed data is injected locally.
        while (lane < 4096) {
            if (dates.every(function (date) {
                return !map[date] || map[date][lane] === undefined;
            })) {
                return lane;
            }
            lane += 1;
        }
        throw new Error('Calendar month contains too many overlapping entries.');
    }

    function cloneItem(item, values) {
        return Object.assign({}, item || {}, values || {});
    }

    function eventIdentity(item) {
        return String(item && item.occurrence_key || ('event:' + String(item && item.event_id || '')));
    }

    function eventSort(left, right) {
        var startOrder = left.visibleStart.localeCompare(right.visibleStart);
        var leftLength;
        var rightLength;
        if (startOrder !== 0) {
            return startOrder;
        }
        leftLength = datesBetween(left.visibleStart, left.visibleEnd).length;
        rightLength = datesBetween(right.visibleStart, right.visibleEnd).length;
        if (leftLength !== rightLength) {
            return rightLength - leftLength;
        }
        return eventIdentity(left.item).localeCompare(eventIdentity(right.item));
    }

    function eventRange(item, monthStart, monthEnd) {
        var start = isoDate(item && (item.occurrence_start_date || item.start_date));
        var end = isoDate(item && (item.occurrence_end_date || item.end_date));
        var visibleStart;
        var visibleEnd;
        if (start === '' || end === '' || end < start || end < monthStart || start > monthEnd) {
            return null;
        }
        visibleStart = start < monthStart ? monthStart : start;
        visibleEnd = end > monthEnd ? monthEnd : end;
        return {
            item: cloneItem(item, {kind: 'event'}),
            start: start,
            end: end,
            visibleStart: visibleStart,
            visibleEnd: visibleEnd,
            multiDay: start !== end
        };
    }

    function placeSegment(map, event, dates) {
        var lane = lowestFreeLane(map, dates);
        var segmentStart = dates[0];
        var segmentEnd = dates[dates.length - 1];
        dates.forEach(function (date, index) {
            var position = dates.length === 1 ? 'single' : (index === 0 ? 'start' : (index === dates.length - 1 ? 'end' : 'middle'));
            if (!map[date]) {
                map[date] = [];
            }
            map[date][lane] = cloneItem(event.item, {
                _calendar_lane: lane,
                _calendar_is_multiday: event.multiDay,
                _calendar_span_position: position,
                _calendar_span_label_visible: index === 0,
                _calendar_span_continues_before: index === 0 && event.start < segmentStart,
                _calendar_span_continues_after: index === dates.length - 1 && event.end > segmentEnd
            });
        });
    }

    function placeSingle(map, date, item) {
        var lane;
        if (!map[date]) {
            map[date] = [];
        }
        lane = lowestFreeLane(map, [date]);
        map[date][lane] = cloneItem(item, {
            _calendar_lane: lane,
            _calendar_is_multiday: false,
            _calendar_span_position: 'single',
            _calendar_span_label_visible: true,
            _calendar_span_continues_before: false,
            _calendar_span_continues_after: false
        });
    }

    function fillPlaceholders(map) {
        Object.keys(map).forEach(function (date) {
            var items = map[date];
            var last = items.length - 1;
            var lane;
            for (lane = 0; lane <= last; lane += 1) {
                if (items[lane] === undefined) {
                    items[lane] = {_calendar_placeholder: true, _calendar_lane: lane};
                }
            }
        });
        return map;
    }

    function place(data) {
        var monthStart = isoDate(data && (data.month_start || data.range_start));
        var monthEnd = isoDate(data && (data.month_end || data.range_end));
        var map = {};
        var spans = [];
        var singles = [];
        if (monthStart === '' || monthEnd === '' || monthEnd < monthStart) {
            return map;
        }

        (Array.isArray(data.events) ? data.events : []).forEach(function (item) {
            var event = eventRange(item, monthStart, monthEnd);
            if (!event) {
                return;
            }
            if (event.multiDay) {
                spans.push(event);
            } else {
                singles.push({date: event.visibleStart, item: event.item});
            }
        });
        spans.sort(eventSort).forEach(function (event) {
            splitByWeek(event.visibleStart, event.visibleEnd).forEach(function (dates) {
                placeSegment(map, event, dates);
            });
        });

        (Array.isArray(data.cancelled_occurrences) ? data.cancelled_occurrences : []).forEach(function (item) {
            var date = isoDate(item && item.original_occurrence_start_date);
            if (date >= monthStart && date <= monthEnd) {
                singles.push({date: date, item: cloneItem(item, {kind: 'event', exception_kind: 'cancelled'})});
            }
        });
        (Array.isArray(data.tasks) ? data.tasks : []).forEach(function (item) {
            var date = isoDate(item && item.due_date);
            if (date >= monthStart && date <= monthEnd) {
                singles.push({date: date, item: cloneItem(item, {kind: 'task'})});
            }
        });
        singles.forEach(function (single) {
            placeSingle(map, single.date, single.item);
        });
        return fillPlaceholders(map);
    }

    window.iGuguruCalendarMonthLayout = Object.freeze({place: place});
}(window));
