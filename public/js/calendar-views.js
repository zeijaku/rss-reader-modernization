(function (window) {
    'use strict';

    var viewModes = ['day', 'week', 'month'];

    function pad(value) {
        return String(value).padStart(2, '0');
    }

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
        return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
    }

    function validMode(value) {
        var mode = String(value || 'month');
        return viewModes.indexOf(mode) !== -1 ? mode : 'month';
    }

    function addDays(value, amount) {
        var date = parseDate(value);
        if (!date) {
            return '';
        }
        date.setUTCDate(date.getUTCDate() + Number(amount || 0));
        return formatDate(date);
    }

    function addMonths(value, amount) {
        var date = parseDate(value);
        var targetMonth;
        var targetDay;
        var lastDay;
        if (!date) {
            return '';
        }
        targetDay = date.getUTCDate();
        targetMonth = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + Number(amount || 0), 1));
        lastDay = new Date(Date.UTC(targetMonth.getUTCFullYear(), targetMonth.getUTCMonth() + 1, 0)).getUTCDate();
        targetMonth.setUTCDate(Math.min(targetDay, lastDay));
        return formatDate(targetMonth);
    }

    function period(modeValue, anchorValue) {
        var mode = validMode(modeValue);
        var anchor = isoDate(anchorValue);
        var date;
        var start;
        var end;
        if (anchor === '') {
            return null;
        }
        date = parseDate(anchor);
        if (mode === 'day') {
            start = anchor;
            end = anchor;
        } else if (mode === 'week') {
            start = addDays(anchor, -date.getUTCDay());
            end = addDays(start, 6);
            if (start < '2000-01-01') {
                start = '2000-01-01';
            }
            if (end > '2100-12-31') {
                end = '2100-12-31';
            }
        } else {
            start = date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-01';
            end = date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-'
                + pad(new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)).getUTCDate());
        }
        return {mode: mode, anchor: anchor, start: start, end: end};
    }

    function move(modeValue, anchorValue, offset) {
        var mode = validMode(modeValue);
        var anchor = isoDate(anchorValue);
        if (anchor === '') {
            return '';
        }
        if (mode === 'day') {
            return addDays(anchor, offset);
        }
        if (mode === 'week') {
            return addDays(anchor, Number(offset || 0) * 7);
        }
        return addMonths(anchor, offset);
    }

    function dates(startValue, endValue) {
        var start = isoDate(startValue);
        var end = isoDate(endValue);
        var result = [];
        var current;
        var guard = 0;
        if (start === '' || end === '' || end < start) {
            return result;
        }
        current = start;
        while (current <= end && guard < 42) {
            result.push(current);
            current = addDays(current, 1);
            guard += 1;
        }
        return result;
    }

    function japaneseDate(value, includeYear) {
        var date = parseDate(value);
        var weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        if (!date) {
            return '';
        }
        return (includeYear ? date.getUTCFullYear() + '年' : '')
            + (date.getUTCMonth() + 1) + '月' + date.getUTCDate() + '日（' + weekdays[date.getUTCDay()] + '）';
    }

    function label(modeValue, periodValue) {
        var mode = validMode(modeValue);
        var value = periodValue || {};
        var startValue = value.start || value.range_start;
        var endValue = value.end || value.range_end;
        var start = parseDate(startValue);
        var end = parseDate(endValue);
        if (!start || !end) {
            return '----';
        }
        if (mode === 'day') {
            return japaneseDate(startValue, true);
        }
        if (mode === 'week') {
            if (start.getUTCFullYear() !== end.getUTCFullYear()) {
                return start.getUTCFullYear() + '年' + (start.getUTCMonth() + 1) + '月' + start.getUTCDate() + '日'
                    + '〜' + end.getUTCFullYear() + '年' + (end.getUTCMonth() + 1) + '月' + end.getUTCDate() + '日';
            }
            if (start.getUTCMonth() !== end.getUTCMonth()) {
                return start.getUTCFullYear() + '年' + (start.getUTCMonth() + 1) + '月' + start.getUTCDate() + '日'
                    + '〜' + (end.getUTCMonth() + 1) + '月' + end.getUTCDate() + '日';
            }
            return start.getUTCFullYear() + '年' + (start.getUTCMonth() + 1) + '月'
                + start.getUTCDate() + '日〜' + end.getUTCDate() + '日';
        }
        return start.getUTCFullYear() + '年' + (start.getUTCMonth() + 1) + '月';
    }

    function publicTime(value) {
        var time = String(value || '');
        return /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(time) ? time : '';
    }

    function minuteOfDay(value) {
        var time = publicTime(value);
        var parts;
        if (time === '') {
            return null;
        }
        parts = time.split(':').map(Number);
        return parts[0] * 60 + parts[1];
    }

    function eventStart(item) {
        return isoDate(item && (item.occurrence_start_date || item.start_date));
    }

    function eventEnd(item) {
        return isoDate(item && (item.occurrence_end_date || item.end_date));
    }

    function itemIdentity(item) {
        if (item && item.kind === 'task') {
            return 'task:' + String(item.task_id || '');
        }
        return String(item && item.occurrence_key || ('event:' + String(item && item.event_id || '')));
    }

    function eventSort(left, right) {
        var leftAllDay = left && left.all_day !== false ? 0 : 1;
        var rightAllDay = right && right.all_day !== false ? 0 : 1;
        var leftTime = publicTime(left && left.start_time);
        var rightTime = publicTime(right && right.start_time);
        return leftAllDay - rightAllDay
            || leftTime.localeCompare(rightTime)
            || eventStart(left).localeCompare(eventStart(right))
            || itemIdentity(left).localeCompare(itemIdentity(right));
    }

    function itemsForDate(data, dateValue) {
        var date = isoDate(dateValue);
        var result = [];
        if (date === '') {
            return result;
        }
        (Array.isArray(data && data.events) ? data.events : []).forEach(function (item) {
            var start = eventStart(item);
            var end = eventEnd(item);
            if (start !== '' && end !== '' && start <= date && end >= date) {
                result.push(Object.assign({kind: 'event'}, item));
            }
        });
        (Array.isArray(data && data.cancelled_occurrences) ? data.cancelled_occurrences : []).forEach(function (item) {
            if (isoDate(item && item.original_occurrence_start_date) === date) {
                result.push(Object.assign({kind: 'event', exception_kind: 'cancelled'}, item));
            }
        });
        (Array.isArray(data && data.tasks) ? data.tasks : []).forEach(function (item) {
            if (isoDate(item && item.due_date) === date) {
                result.push(Object.assign({kind: 'task'}, item));
            }
        });
        return result.sort(eventSort);
    }

    function timedCandidate(item, date) {
        var startDate = eventStart(item);
        var endDate = eventEnd(item);
        return item && item.kind !== 'task'
            && item.exception_kind !== 'cancelled'
            && item.all_day === false
            && startDate === date
            && endDate === date
            && minuteOfDay(item.start_time) !== null;
    }

    function allocateGroup(group) {
        var columnEnds = [];
        var column;
        group.forEach(function (entry) {
            column = 0;
            while (column < columnEnds.length && columnEnds[column] > entry.start_minute) {
                column += 1;
            }
            if (column === columnEnds.length) {
                columnEnds.push(entry.layout_end_minute);
            } else {
                columnEnds[column] = entry.layout_end_minute;
            }
            entry.column = column;
        });
        group.forEach(function (entry) {
            entry.columns = columnEnds.length;
        });
    }

    function dayLayout(data, dateValue) {
        var date = isoDate(dateValue);
        var allDay = [];
        var timed = [];
        var group = [];
        var groupEnd = -1;
        itemsForDate(data, date).forEach(function (item) {
            var start;
            var end;
            var openEnded;
            if (!timedCandidate(item, date)) {
                allDay.push(item);
                return;
            }
            start = minuteOfDay(item.start_time);
            end = minuteOfDay(item.end_time);
            openEnded = end === null;
            timed.push(Object.assign({}, item, {
                start_minute: start,
                end_minute: end,
                layout_end_minute: end !== null && end > start ? end : Math.min(1440, start + 30),
                open_ended: openEnded,
                zero_duration: end !== null && end === start,
                column: 0,
                columns: 1
            }));
        });
        timed.sort(function (left, right) {
            return left.start_minute - right.start_minute
                || left.layout_end_minute - right.layout_end_minute
                || itemIdentity(left).localeCompare(itemIdentity(right));
        });
        timed.forEach(function (entry) {
            if (group.length > 0 && entry.start_minute >= groupEnd) {
                allocateGroup(group);
                group = [];
                groupEnd = -1;
            }
            group.push(entry);
            groupEnd = Math.max(groupEnd, entry.layout_end_minute);
        });
        if (group.length > 0) {
            allocateGroup(group);
        }
        return {date: date, all_day: allDay, timed: timed};
    }

    window.iGuguruCalendarViews = Object.freeze({
        validMode: validMode,
        period: period,
        move: move,
        dates: dates,
        label: label,
        japaneseDate: japaneseDate,
        itemsForDate: itemsForDate,
        dayLayout: dayLayout
    });
}(window));
