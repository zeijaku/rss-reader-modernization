(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var recurrenceEndpoint = './calendar_recurrence_api.php';
    var detailEndpoint = './calendar_color_api.php';
    var dragState = null;
    var pending = false;
    var suppressClickUntil = 0;

    function text(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function validIsoDate(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text(value));
        var date;
        if (!match) {
            return '';
        }
        date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
        return date.getUTCFullYear() === Number(match[1])
            && date.getUTCMonth() + 1 === Number(match[2])
            && date.getUTCDate() === Number(match[3]) ? match[0] : '';
    }

    function dateObject(value) {
        var valid = validIsoDate(value);
        var parts = valid ? valid.split('-').map(Number) : [];
        return valid ? new Date(Date.UTC(parts[0], parts[1] - 1, parts[2])) : null;
    }

    function formatDate(date) {
        return date.getUTCFullYear() + '-'
            + String(date.getUTCMonth() + 1).padStart(2, '0') + '-'
            + String(date.getUTCDate()).padStart(2, '0');
    }

    function dayDelta(fromDate, toDate) {
        var from = dateObject(fromDate);
        var to = dateObject(toDate);
        return from && to ? Math.round((to.getTime() - from.getTime()) / 86400000) : null;
    }

    function shiftDate(value, delta) {
        var date = dateObject(value);
        if (!date || !Number.isInteger(delta) || Math.abs(delta) > 3660) {
            return '';
        }
        date.setUTCDate(date.getUTCDate() + delta);
        return formatDate(date);
    }

    function shiftRange(start, end, anchor, target) {
        start = validIsoDate(start);
        end = validIsoDate(end);
        anchor = validIsoDate(anchor);
        target = validIsoDate(target);
        var delta = dayDelta(anchor, target);
        if (!start || !end || end < start || !anchor || !target || delta === null) {
            return null;
        }
        return {
            start: shiftDate(start, delta),
            end: shiftDate(end, delta),
            delta: delta
        };
    }

    function attr(entry, name, fallback) {
        var value = entry && typeof entry.getAttribute === 'function' ? entry.getAttribute(name) : null;
        return value === null ? text(fallback) : text(value);
    }

    function validColor(value) {
        value = text(value || 'blue');
        return ['red', 'blue', 'green', 'yellow', 'purple'].indexOf(value) !== -1 ? value : 'blue';
    }

    function validTime(value) {
        value = text(value);
        return /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(value) ? value : '';
    }

    function sourceState(entry, anchorDate) {
        if (!entry || !/^\d+$/.test(attr(entry, 'data-event-id', ''))) {
            return null;
        }
        var repeat = attr(entry, 'data-calendar-event-repeat-type', 'none');
        var recurring = repeat !== 'none';
        var exceptionKind = attr(entry, 'data-calendar-exception-kind', '');
        if (exceptionKind === 'cancelled') {
            return null;
        }
        var start = recurring
            ? attr(entry, 'data-calendar-occurrence-start-date', attr(entry, 'data-event-start-date', ''))
            : attr(entry, 'data-event-start-date', '');
        var end = recurring
            ? attr(entry, 'data-calendar-occurrence-end-date', attr(entry, 'data-event-end-date', start))
            : attr(entry, 'data-event-end-date', start);
        start = validIsoDate(start);
        end = validIsoDate(end);
        anchorDate = validIsoDate(anchorDate);
        if (!start || !end || end < start || !anchorDate) {
            return null;
        }
        return {
            eventId: attr(entry, 'data-event-id', ''),
            title: attr(entry, 'data-event-title', ''),
            note: attr(entry, 'data-event-note', ''),
            start: start,
            end: end,
            anchor: anchorDate,
            color: validColor(attr(entry, 'data-calendar-event-color', 'blue')),
            allDay: attr(entry, 'data-calendar-event-all-day', '1') !== '0',
            startTime: validTime(attr(entry, 'data-calendar-event-start-time', '')),
            endTime: validTime(attr(entry, 'data-calendar-event-end-time', '')),
            url: attr(entry, 'data-calendar-event-url', ''),
            recurring: recurring,
            originalStart: validIsoDate(attr(entry, 'data-calendar-original-occurrence-start-date', start)),
            revision: attr(entry, 'data-calendar-occurrence-revision', '')
        };
    }

    function buildMovePlan(state, targetDate) {
        if (!state) {
            return null;
        }
        var range = shiftRange(state.start, state.end, state.anchor, targetDate);
        if (!range || range.delta === 0) {
            return null;
        }
        var payload = {
            event_id: state.eventId,
            calendar_event_title: state.title,
            calendar_event_start_date: range.start,
            calendar_event_end_date: range.end,
            calendar_event_note: state.note,
            calendar_event_color: state.color,
            calendar_event_all_day: state.allDay ? '1' : '0',
            calendar_event_start_time: state.allDay ? '' : state.startTime,
            calendar_event_end_time: state.allDay ? '' : state.endTime,
            calendar_event_url: state.url
        };
        if (state.recurring) {
            if (!state.originalStart) {
                return null;
            }
            payload.original_occurrence_start_date = state.originalStart;
            payload.occurrence_revision = state.revision;
            return {endpoint: recurrenceEndpoint, action: 'calendar.occurrence.update', payload: payload, recurring: true, range: range};
        }
        return {endpoint: detailEndpoint, action: 'calendar.color.update', payload: payload, recurring: false, range: range};
    }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function updateCsrf(xhr) {
        if (!xhr || typeof xhr.getResponseHeader !== 'function') {
            return;
        }
        var token = xhr.getResponseHeader('X-CSRF-Token') || '';
        if (/^[a-f0-9]{64}$/.test(token)) {
            $('meta[name="csrf-token"]').attr('content', token);
        }
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }
        $notice.removeClass('alert-success alert-info alert-danger')
            .addClass(type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger'))
            .attr('role', type === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(text(message));
    }

    function request(plan) {
        return $.ajax({
            url: plan.endpoint,
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: $.extend({}, plan.payload, {action: plan.action, csrf_token: csrfToken()})
        }).always(function (first, status, third) {
            var xhr = third && typeof third.getResponseHeader === 'function' ? third : first;
            updateCsrf(xhr && typeof xhr.getResponseHeader === 'function' ? xhr : null);
        });
    }

    function clearDropTarget() {
        $('.calendar-drag-drop-target').removeClass('calendar-drag-drop-target');
    }

    function finishDrag() {
        clearDropTarget();
        $('.calendar-event-dragging').removeClass('calendar-event-dragging');
        dragState = null;
    }

    function calendarDateForElement(element) {
        var day = element && typeof element.closest === 'function' ? element.closest('.calendar-day[data-calendar-date]') : null;
        return day ? validIsoDate(day.getAttribute('data-calendar-date')) : '';
    }

    function prepareEntries(root) {
        $(root || document).find('.calendar-event-edit-trigger').each(function () {
            var $entry = $(this);
            var recurring = String($entry.attr('data-calendar-event-repeat-type') || 'none') !== 'none';
            var cancelled = String($entry.attr('data-calendar-exception-kind') || '') === 'cancelled';
            var anchor = calendarDateForElement(this);
            var eligible = !cancelled && anchor !== '' && (!recurring || validIsoDate($entry.attr('data-calendar-original-occurrence-start-date')) !== '');
            $entry.attr('draggable', eligible ? 'true' : 'false');
            if (eligible) {
                $entry.attr('data-calendar-drag-ready', '1');
            } else {
                $entry.removeAttr('data-calendar-drag-ready');
            }
        });
    }

    function onDragStart(event) {
        if (pending) {
            event.preventDefault();
            return;
        }
        var entry = event.target && typeof event.target.closest === 'function'
            ? event.target.closest('.calendar-event-edit-trigger[data-calendar-drag-ready="1"]') : null;
        if (!entry) {
            return;
        }
        var state = sourceState(entry, calendarDateForElement(entry));
        if (!state) {
            event.preventDefault();
            return;
        }
        dragState = {entry: entry, state: state};
        entry.classList.add('calendar-event-dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            try {
                event.dataTransfer.setData('text/plain', 'calendar-event:' + state.eventId);
            } catch (error) {
                // Some browsers limit setData for synthetic/restricted drags.
            }
        }
    }

    function onDragOver(event) {
        if (!dragState || pending) {
            return;
        }
        var day = event.target && typeof event.target.closest === 'function'
            ? event.target.closest('.calendar-day[data-calendar-date]') : null;
        if (!day || !validIsoDate(day.getAttribute('data-calendar-date'))) {
            return;
        }
        event.preventDefault();
        clearDropTarget();
        day.classList.add('calendar-drag-drop-target');
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }
    }

    function onDrop(event) {
        if (!dragState || pending) {
            return;
        }
        var day = event.target && typeof event.target.closest === 'function'
            ? event.target.closest('.calendar-day[data-calendar-date]') : null;
        var targetDate = day ? validIsoDate(day.getAttribute('data-calendar-date')) : '';
        if (!targetDate) {
            finishDrag();
            return;
        }
        event.preventDefault();
        var plan = buildMovePlan(dragState.state, targetDate);
        if (!plan) {
            finishDrag();
            return;
        }
        pending = true;
        suppressClickUntil = Date.now() + 800;
        clearDropTarget();
        showNotice(plan.recurring ? 'この回の予定を移動しています…' : '予定を移動しています…', 'info');
        request(plan)
            .done(function (response) {
                if (response && response.ok === true) {
                    showNotice(plan.recurring ? 'この回の予定を移動しました' : '予定を移動しました', 'success');
                    $(document).trigger(plan.recurring ? 'calendar:occurrenceChanged' : 'calendar:eventChanged');
                    window.setTimeout(function () { window.location.reload(); }, 150);
                    return;
                }
                showNotice(response && response.error && response.error.message ? response.error.message : '予定を移動出来ませんでした', 'danger');
            })
            .fail(function (xhr, status) {
                var message = status === 'timeout' ? '通信がタイムアウトしました' : '予定を移動出来ませんでした';
                if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
                    message = xhr.responseJSON.error.message;
                }
                showNotice(message, 'danger');
                if (xhr && xhr.status === 409) {
                    $(document).trigger('calendar:occurrenceChanged');
                }
            })
            .always(function () {
                pending = false;
                finishDrag();
            });
    }

    function onClickCapture(event) {
        if (Date.now() < suppressClickUntil) {
            var entry = event.target && typeof event.target.closest === 'function' ? event.target.closest('.calendar-event-edit-trigger') : null;
            if (entry) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }
    }

    document.addEventListener('dragstart', onDragStart, true);
    document.addEventListener('dragover', onDragOver, true);
    document.addEventListener('drop', onDrop, true);
    document.addEventListener('dragend', finishDrag, true);
    document.addEventListener('click', onClickCapture, true);
    $(function () { prepareEntries(document); });
    $(document).on('calendar:rangeRendered calendar:eventChanged calendar:occurrenceChanged', function () {
        window.setTimeout(function () { prepareEntries(document); }, 0);
    });

    window.IguguruCalendarDragDrop = Object.freeze({
        dayDelta: dayDelta,
        shiftDate: shiftDate,
        shiftRange: shiftRange,
        sourceState: sourceState,
        buildMovePlan: buildMovePlan
    });
}(window.jQuery, window, document));
