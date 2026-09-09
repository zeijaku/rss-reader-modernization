(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruCalendar';
    var rangeEndpoint = './calendar_recurrence_api.php';
    var holidayRefreshRequested = false;
    var noticeTimer = null;
    var calendarResizeObserver = null;

    function appCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function showNotice(message, type, autoCloseMs) {
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        var noticeText = String(message || '処理を完了出来ませんでした');
        var closeMs = Number(autoCloseMs);
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }
        if (noticeTimer !== null) {
            window.clearTimeout(noticeTimer);
            noticeTimer = null;
        }
        if (!(closeMs > 0)) {
            closeMs = noticeType === 'success' ? 2500 : (noticeType === 'info' ? 3000 : 6000);
        }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(noticeText);
        noticeTimer = window.setTimeout(function () {
            if ($('#app-notice').text() === noticeText) {
                $('#app-notice').prop('hidden', true).empty();
            }
            noticeTimer = null;
        }, closeMs);
    }

    function apiErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return xhr.responseJSON.error.message;
        }
        return '通信に失敗しました';
    }

    function apiResponseData(data) {
        if (data && data.ok === true && data.data) {
            return data.data;
        }
        if (data && data.error && data.error.message) {
            showNotice(data.error.message, 'danger');
        } else {
            showNotice('処理を完了出来ませんでした', 'danger');
        }
        return null;
    }

    function apiRequest(action, data, timeout) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 4000,
            data: $.extend({}, data || {}, {
                'action': action,
                'csrf_token': appCsrfToken()
            })
        });
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

    function rangeRequest(data) {
        return $.ajax({
            url: rangeEndpoint,
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 5000,
            data: $.extend({}, data || {}, {
                action: 'calendar.range.list',
                csrf_token: appCsrfToken()
            })
        }).always(function (first, textStatus, third) {
            var xhr = third && typeof third.getResponseHeader === 'function' ? third : first;
            updateCsrf(xhr && typeof xhr.getResponseHeader === 'function' ? xhr : null);
        });
    }

    function requestStart($button) {
        if ($button.data('request-pending') === true) {
            return false;
        }
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false).prop('disabled', false);
    }

    function appendLoadingText($target, message) {
        $target.empty();
        var $loading = $('<span>').addClass('loading-inline').appendTo($target);
        $('<i>')
            .addClass('fas fa-spinner fa-spin')
            .attr('aria-hidden', 'true')
            .appendTo($loading);
        $('<span>').text(String(message || '読み込み中...')).appendTo($loading);
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function localIsoDate(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function utcDateFromIso(value) {
        var parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) {
            return null;
        }
        return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
    }

    function isoFromUtcDate(date) {
        return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
    }

    function eachIsoDate(start, end, callback) {
        var current = utcDateFromIso(start);
        var last = utcDateFromIso(end);
        if (!current || !last || current.getTime() > last.getTime()) {
            return;
        }
        var guard = 0;
        while (current.getTime() <= last.getTime() && guard < 370) {
            callback(isoFromUtcDate(current));
            current.setUTCDate(current.getUTCDate() + 1);
            guard += 1;
        }
    }

    function calendarWidgetPayload(prefix) {
        return {
            'calendar_title': $('.' + prefix + 'CalendarWidgetTitleValue').val(),
            'calendar_show_completed_tasks': $('.' + prefix + 'CalendarShowCompletedTasks').prop('checked') ? '1' : '0',
            'widget_style': $('.' + prefix + 'CalendarWidgetStyle').val(),
            'widget_width': $('.' + prefix + 'CalendarWidgetWidth').val(),
            'widget_height': $('.' + prefix + 'CalendarWidgetHeight').val()
        };
    }

    function addCalendarWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = calendarWidgetPayload('register');
        payload.widget_location = $('.registerCalendarWidgetLocation').val();
        apiRequest('widget.calendar.create', payload, 3000)
            .done(function (data) {
                if (apiResponseData(data) !== null) {
                    window.location.reload();
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
            });
    }

    function editCalendarWidget($trigger) {
        $('.changeCalendarWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeCalendarWidgetTitleValue').val(String($trigger.attr('data-calendar-title') || 'Calendar'));
        $('.changeCalendarShowCompletedTasks').prop('checked', String($trigger.attr('data-calendar-show-completed-tasks') || '0') === '1');
        $('.changeCalendarWidgetStyle').val(String($trigger.attr('data-widget-style') || 'info'));
        $('.changeCalendarWidgetWidth').val(String($trigger.attr('data-widget-width') || '2'));
        $('.changeCalendarWidgetHeight').val(String($trigger.attr('data-widget-height') || '1'));
    }

    function changeCalendarWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = calendarWidgetPayload('change');
        payload.widget_id = $('.changeCalendarWidgetId').val();
        apiRequest('widget.calendar.update', payload, 3000)
            .done(function (data) {
                if (apiResponseData(data) !== null) {
                    window.location.reload();
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteCalendarWidget($button) {
        var widgetId = String($('.changeCalendarWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するCalendar Widgetを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このCalendar Widgetを削除しますか？ 登録済みの予定は残ります。') || !requestStart($button)) {
            return;
        }
        apiRequest('widget.calendar.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseData(data) !== null) {
                    window.location.reload();
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
            });
    }

    function calendarEventPayload(prefix) {
        return {
            'calendar_event_title': $('.' + prefix + 'CalendarEventTitleValue').val(),
            'calendar_event_start_date': $('.' + prefix + 'CalendarEventStartDate').val(),
            'calendar_event_end_date': $('.' + prefix + 'CalendarEventEndDate').val(),
            'calendar_event_note': $('.' + prefix + 'CalendarEventNote').val()
        };
    }

    function prepareCalendarEventAdd($trigger) {
        var date = String($trigger.attr('data-calendar-date') || '');
        var $card = $trigger.closest('[data-dashboard-widget-type="calendar"]');
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            date = String($card.attr('data-calendar-selected-date') || '');
        }
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            var year = Number($card.attr('data-calendar-year') || 0);
            var month = Number($card.attr('data-calendar-month') || 0);
            var now = new Date();
            date = year === now.getFullYear() && month === now.getMonth() + 1
                ? localIsoDate(now)
                : (year > 0 && month > 0 ? year + '-' + pad(month) + '-01' : localIsoDate(now));
        }
        $('.registerCalendarEventTitleValue').val('');
        $('.registerCalendarEventStartDate').val(date);
        $('.registerCalendarEventEndDate').val(date);
        $('.registerCalendarEventNote').val('');
    }

    function addCalendarEvent($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        apiRequest('calendar.event.create', calendarEventPayload('register'), 3000)
            .done(function (data) {
                if (apiResponseData(data) !== null) {
                    window.location.reload();
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
            });
    }

    function editCalendarEvent($trigger) {
        $('.changeCalendarEventId').val(String($trigger.attr('data-event-id') || ''));
        $('.changeCalendarEventTitleValue').val(String($trigger.attr('data-event-title') || ''));
        $('.changeCalendarEventStartDate').val(String($trigger.attr('data-event-start-date') || ''));
        $('.changeCalendarEventEndDate').val(String($trigger.attr('data-event-end-date') || ''));
        $('.changeCalendarEventNote').val(String($trigger.attr('data-event-note') || ''));
    }

    function changeCalendarEvent($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = calendarEventPayload('change');
        payload.event_id = $('.changeCalendarEventId').val();
        apiRequest('calendar.event.update', payload, 3000)
            .done(function (data) {
                if (apiResponseData(data) !== null) {
                    window.location.reload();
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteCalendarEvent($button) {
        var eventId = String($('.changeCalendarEventId').val() || '');
        if (!/^\d+$/.test(eventId)) {
            showNotice('削除する予定を確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('この予定を削除しますか？') || !requestStart($button)) {
            return;
        }
        apiRequest('calendar.event.delete', {'event_id': eventId}, 3000)
            .done(function (data) {
                if (apiResponseData(data) !== null) {
                    window.location.reload();
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
            });
    }

    function addItemToDate(map, date, item) {
        if (!map[date]) {
            map[date] = [];
        }
        map[date].push(item);
    }

    function publicTime(value) {
        var time = String(value || '');
        return /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(time) ? time : '';
    }

    function validEventColor(value) {
        var color = String(value || 'blue');
        return ['red', 'blue', 'green', 'yellow', 'purple'].indexOf(color) !== -1 ? color : 'blue';
    }

    function validRepeatType(value) {
        var repeat = String(value || 'none');
        return ['none', 'daily', 'weekly', 'monthly', 'yearly'].indexOf(repeat) !== -1 ? repeat : 'none';
    }

    function validExceptionKind(value) {
        var kind = String(value || '');
        return ['override', 'cancelled'].indexOf(kind) !== -1 ? kind : '';
    }

    function eventTimeLabel(item, date) {
        if (item.all_day !== false || publicTime(item.start_time) === '') {
            return '';
        }
        var start = String(item.occurrence_start_date || item.start_date || '');
        var end = String(item.occurrence_end_date || item.end_date || start);
        var startTime = publicTime(item.start_time);
        var endTime = publicTime(item.end_time);
        if (start === end) {
            return startTime + (endTime !== '' ? '–' + endTime : '');
        }
        if (date === start) {
            return startTime + '〜';
        }
        if (date === end && endTime !== '') {
            return '〜' + endTime;
        }
        return '';
    }

    function calendarItemButton(item, date) {
        if (item.kind === 'task') {
            return $('<button>')
                .attr('type', 'button')
                .addClass('calendar-entry calendar-task-entry task-item-edit-trigger task-priority-' + item.priority + (item.completed ? ' task-completed' : ''))
                .attr('data-task-id', String(item.task_id))
                .attr('data-task-title', item.title)
                .attr('data-task-due-date', item.due_date)
                .attr('data-task-priority', item.priority)
                .attr('data-bs-toggle', 'modal')
                .attr('data-bs-target', '#changeTaskItem')
                .attr('title', 'Task: ' + item.title)
                .append($('<i>').addClass(item.completed ? 'fas fa-check-circle' : 'fas fa-check-square').attr('aria-hidden', 'true'))
                .append($('<span>').text(item.title));
        }
        var color = validEventColor(item.color);
        var repeat = validRepeatType(item.repeat_type);
        var occurrenceStart = String(item.occurrence_start_date || item.start_date || '');
        var occurrenceEnd = String(item.occurrence_end_date || item.end_date || occurrenceStart);
        var sourceStart = String(item.source_start_date || item.start_date || occurrenceStart);
        var sourceEnd = String(item.source_end_date || item.end_date || occurrenceEnd);
        var originalStart = String(item.original_occurrence_start_date || occurrenceStart);
        var occurrenceKey = String(item.occurrence_key || ('event:' + String(item.event_id || '') + ':' + originalStart));
        var occurrenceRevision = /^[a-f0-9]{64}$/.test(String(item.occurrence_revision || ''))
            ? String(item.occurrence_revision)
            : '';
        var exceptionKind = validExceptionKind(item.exception_kind);
        var spanPosition = ['single', 'start', 'middle', 'end'].indexOf(String(item._calendar_span_position || '')) !== -1
            ? String(item._calendar_span_position)
            : 'single';
        var multiDay = item._calendar_is_multiday === true;
        var spanLabelVisible = item._calendar_span_label_visible !== false;
        var spanClasses = multiDay
            ? ' calendar-event-multiday calendar-event-span-' + spanPosition
                + (spanLabelVisible ? ' calendar-event-span-label-visible' : ' calendar-event-span-continuation')
                + (item._calendar_span_continues_before === true ? ' calendar-event-span-continued-before' : '')
                + (item._calendar_span_continues_after === true ? ' calendar-event-span-continued-after' : '')
            : '';
        var $button = $('<button>')
            .attr('type', 'button')
            .addClass('calendar-entry calendar-event-entry calendar-event-edit-trigger calendar-event-color-' + color
                + (repeat !== 'none' ? ' calendar-event-recurring' : '')
                + (exceptionKind === 'override' ? ' calendar-event-exception' : '')
                + (exceptionKind === 'cancelled' ? ' calendar-event-cancelled' : '')
                + spanClasses)
            .attr('data-event-id', String(item.event_id))
            .attr('data-event-title', item.title)
            .attr('data-event-start-date', sourceStart)
            .attr('data-event-end-date', sourceEnd)
            .attr('data-event-note', item.note || '')
            .attr('data-calendar-occurrence-key', occurrenceKey)
            .attr('data-calendar-original-occurrence-start-date', originalStart)
            .attr('data-calendar-occurrence-revision', occurrenceRevision)
            .attr('data-calendar-exception-id', item.exception_id ? String(item.exception_id) : '')
            .attr('data-calendar-exception-kind', exceptionKind)
            .attr('data-calendar-occurrence-start-date', occurrenceStart)
            .attr('data-calendar-occurrence-end-date', occurrenceEnd)
            .attr('data-calendar-lane', Number.isInteger(item._calendar_lane) ? String(item._calendar_lane) : null)
            .attr('data-calendar-span-position', multiDay ? spanPosition : 'single')
            .attr('data-calendar-source-title', String(item.source_title !== undefined ? item.source_title : item.title || ''))
            .attr('data-calendar-source-note', String(item.source_note !== undefined ? item.source_note : item.note || ''))
            .attr('data-calendar-source-color', validEventColor(item.source_color !== undefined ? item.source_color : color))
            .attr('data-calendar-source-all-day', (item.source_all_day !== undefined ? item.source_all_day : item.all_day) === false ? '0' : '1')
            .attr('data-calendar-source-start-time', publicTime(item.source_start_time !== undefined ? item.source_start_time : item.start_time))
            .attr('data-calendar-source-end-time', publicTime(item.source_end_time !== undefined ? item.source_end_time : item.end_time))
            .attr('data-calendar-source-url', String(item.source_url !== undefined && item.source_url !== null ? item.source_url : item.url || ''))
            .attr('data-calendar-event-color', color)
            .attr('data-calendar-event-color-ready', '1')
            .attr('data-calendar-event-meta-ready', '1')
            .attr('data-calendar-event-all-day', item.all_day === false ? '0' : '1')
            .attr('data-calendar-event-start-time', publicTime(item.start_time))
            .attr('data-calendar-event-end-time', publicTime(item.end_time))
            .attr('data-calendar-event-url', String(item.url || ''))
            .attr('data-calendar-event-repeat-type', repeat)
            .attr('data-calendar-event-repeat-until', String(item.repeat_until || ''))
            .attr('data-bs-toggle', 'modal')
            .attr('data-bs-target', '#changeCalendarEvent')
            .attr('aria-label', multiDay ? String(item.title || '') + '、' + date + '、複数日予定' : null)
            .attr('title', (item.note ? item.title + ': ' + item.note : item.title)
                + (exceptionKind === 'cancelled' ? '（取消済み）' : (repeat !== 'none' ? '（繰り返し予定）' : '')))
            .append($('<i>').addClass('far fa-calendar').attr('aria-hidden', 'true'));
        var timeLabel = eventTimeLabel(item, date);
        if (timeLabel !== '') {
            $button.append($('<span>').addClass('calendar-event-time-label').text(timeLabel));
        }
        if (repeat !== 'none') {
            $button.append($('<span>').addClass('calendar-event-repeat-label').attr('aria-hidden', 'true').text('↻'));
        }
        if (exceptionKind === 'cancelled') {
            $button.append($('<span>').addClass('calendar-event-cancelled-label').text('取消済み'));
        }
        return $button.append($('<span>').addClass('calendar-entry-title').text(item.title));
    }

    function calendarItemElement(item, date) {
        if (item && item._calendar_placeholder === true) {
            return $('<span>')
                .addClass('calendar-entry calendar-entry-placeholder')
                .attr('data-calendar-lane', String(item._calendar_lane || 0))
                .attr('aria-hidden', 'true');
        }
        return calendarItemButton(item, date);
    }

    function calendarViewModule() {
        return window.iGuguruCalendarViews && typeof window.iGuguruCalendarViews.period === 'function'
            ? window.iGuguruCalendarViews
            : null;
    }

    function calendarViewMode($card) {
        var views = calendarViewModule();
        var value = String($card.attr('data-calendar-view') || 'month');
        return views ? views.validMode(value) : 'month';
    }

    function calendarSelectedDate($card) {
        var value = String($card.attr('data-calendar-selected-date') || '');
        return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : localIsoDate(new Date());
    }

    function updateCompactCalendar($card) {
        var element = typeof $card.get === 'function' ? $card.get(0) : null;
        var width = element && typeof element.getBoundingClientRect === 'function'
            ? Number(element.getBoundingClientRect().width || 0)
            : 0;
        $card.toggleClass('calendar-view-compact', width > 0 && width < 720);
    }

    function observeCalendar($card) {
        var element = typeof $card.get === 'function' ? $card.get(0) : null;
        updateCompactCalendar($card);
        if (calendarResizeObserver && element) {
            calendarResizeObserver.observe(element);
        }
    }

    function updateCalendarChrome($card, data) {
        var mode = String(data.view_mode || 'month');
        var anchor = String(data.anchor_date || data.range_start || '');
        var views = calendarViewModule();
        var anchorDate = utcDateFromIso(anchor);
        var labels = {
            day: ['前の日', '次の日'],
            week: ['前の週', '次の週'],
            month: ['前の月', '次の月']
        };
        var navigation = labels[mode] || labels.month;
        if (anchorDate) {
            $card
                .attr('data-calendar-year', String(anchorDate.getUTCFullYear()))
                .attr('data-calendar-month', String(anchorDate.getUTCMonth() + 1));
        }
        $card
            .attr('data-calendar-view', mode)
            .attr('data-calendar-selected-date', anchor)
            .removeClass('calendar-view-day calendar-view-week calendar-view-month')
            .addClass('calendar-view-' + mode);
        $card.find('.calendar-month-label').text(views
            ? views.label(mode, data)
            : String(data.year || '') + '年' + String(data.month || '') + '月');
        $card.find('.calendar-prev-month').attr('aria-label', navigation[0]).attr('title', navigation[0]);
        $card.find('.calendar-next-month').attr('aria-label', navigation[1]).attr('title', navigation[1]);
        $card.find('.calendar-view-mode').removeClass('active').attr('aria-pressed', 'false');
        $card.find('.calendar-view-mode[data-calendar-view-mode="' + mode + '"]')
            .addClass('active')
            .attr('aria-pressed', 'true');
        $card.find('.calendar-weekdays').prop('hidden', mode !== 'month');
        updateCompactCalendar($card);
    }

    function renderMonthCalendar($card, data) {
        var year = Number(data.year || 0);
        var month = Number(data.month || 0);
        if (!year || !month) {
            return;
        }

        var holidays = data.holidays && typeof data.holidays === 'object' && !Array.isArray(data.holidays) ? data.holidays : {};
        var monthLayout = window.iGuguruCalendarMonthLayout;
        var itemsByDate = {};
        if (monthLayout && typeof monthLayout.place === 'function') {
            itemsByDate = monthLayout.place(data);
        } else {
            (Array.isArray(data.events) ? data.events : []).forEach(function (event) {
                var start = String(event.occurrence_start_date || event.start_date || '');
                var end = String(event.occurrence_end_date || event.end_date || '');
                var visibleStart = start < data.month_start ? data.month_start : start;
                var visibleEnd = end > data.month_end ? data.month_end : end;
                eachIsoDate(visibleStart, visibleEnd, function (date) {
                    addItemToDate(itemsByDate, date, $.extend({'kind': 'event'}, event));
                });
            });
            (Array.isArray(data.cancelled_occurrences) ? data.cancelled_occurrences : []).forEach(function (event) {
                var date = String(event.original_occurrence_start_date || '');
                if (date >= data.month_start && date <= data.month_end) {
                    addItemToDate(itemsByDate, date, $.extend({'kind': 'event', 'exception_kind': 'cancelled'}, event));
                }
            });
            (Array.isArray(data.tasks) ? data.tasks : []).forEach(function (task) {
                addItemToDate(itemsByDate, String(task.due_date || ''), $.extend({'kind': 'task'}, task));
            });
        }

        var firstDay = new Date(year, month - 1, 1).getDay();
        var dayCount = new Date(year, month, 0).getDate();
        var cellCount = Math.ceil((firstDay + dayCount) / 7) * 7;
        var today = localIsoDate(new Date());
        var $days = $card.find('.calendar-days')
            .removeClass('calendar-day-view calendar-week-view')
            .addClass('calendar-month-view')
            .empty()
            .attr('role', 'grid')
            .attr('aria-label', '月間Calendar')
            .attr('aria-busy', 'false')
            .toggleClass('calendar-month-layout-ready', Boolean(monthLayout && typeof monthLayout.place === 'function'));
        for (var cell = 0; cell < cellCount; cell += 1) {
            var dayNumber = cell - firstDay + 1;
            if (dayNumber < 1 || dayNumber > dayCount) {
                $days.append($('<div>').addClass('calendar-day calendar-day-empty').attr('aria-hidden', 'true'));
                continue;
            }
            var date = year + '-' + pad(month) + '-' + pad(dayNumber);
            var holidayName = typeof holidays[date] === 'string' ? String(holidays[date]).trim() : '';
            var $day = $('<div>')
                .addClass('calendar-day')
                .toggleClass('calendar-day-today', date === today)
                .toggleClass('calendar-day-holiday', holidayName !== '')
                .attr('role', 'gridcell')
                .attr('data-calendar-date', date);
            if (holidayName !== '') {
                $day.attr('data-calendar-holiday', holidayName).attr('title', holidayName);
            }
            var $dateButton = $('<button>')
                .attr('type', 'button')
                .addClass('calendar-day-number calendar-day-add-trigger')
                .attr('data-calendar-date', date)
                .attr('data-bs-toggle', 'modal')
                .attr('data-bs-target', '#registerCalendarEvent')
                .attr('aria-label', holidayName !== '' ? date + ' ' + holidayName + '。予定を追加' : date + 'に予定を追加')
                .attr('title', holidayName !== '' ? holidayName : null)
                .text(String(dayNumber));
            $day.append($dateButton);
            var $entries = $('<div>').addClass('calendar-day-entries');
            (itemsByDate[date] || []).forEach(function (item) {
                $entries.append(calendarItemElement(item, date));
            });
            $day.append($entries);
            $days.append($day);
        }
    }

    function dayItemCopy(item) {
        return $.extend({}, item, {
            _calendar_lane: null,
            _calendar_is_multiday: false,
            _calendar_span_position: 'single',
            _calendar_span_label_visible: true,
            _calendar_span_continues_before: false,
            _calendar_span_continues_after: false
        });
    }

    function appendDayEntry($target, item, date, extraClass) {
        var $entry = calendarItemButton(dayItemCopy(item), date);
        var start = String(item && (item.occurrence_start_date || item.start_date) || '');
        var end = String(item && (item.occurrence_end_date || item.end_date) || '');
        if (extraClass) {
            $entry.addClass(extraClass);
        }
        if (item && item.kind !== 'task' && start !== '' && end !== '' && start !== end) {
            $entry.attr('aria-label', String(item.title || '') + '、' + start + 'から' + end + 'までの複数日予定');
        }
        $target.append($entry);
        return $entry;
    }

    function renderDayCalendar($card, data) {
        var views = calendarViewModule();
        var date = String(data.range_start || data.anchor_date || '');
        var layout = views ? views.dayLayout(data, date) : {all_day: [], timed: []};
        var holidays = data.holidays && typeof data.holidays === 'object' ? data.holidays : {};
        var holidayName = typeof holidays[date] === 'string' ? String(holidays[date]).trim() : '';
        var today = localIsoDate(new Date());
        var $days = $card.find('.calendar-days')
            .removeClass('calendar-month-view calendar-week-view calendar-month-layout-ready')
            .addClass('calendar-day-view')
            .empty()
            .attr('role', 'group')
            .attr('aria-label', '日間Calendar ' + date)
            .attr('aria-busy', 'false');
        var $day = $('<section>')
            .addClass('calendar-day calendar-day-detail')
            .toggleClass('calendar-day-today', date === today)
            .toggleClass('calendar-day-holiday', holidayName !== '')
            .attr('data-calendar-date', date);
        if (date === today) {
            $day.attr('aria-current', 'date');
        }
        var $heading = $('<div>').addClass('calendar-day-detail-heading');
        $('<strong>')
            .text(views ? views.japaneseDate(date, true) : date)
            .appendTo($heading);
        if (holidayName !== '') {
            $('<span>').addClass('calendar-view-holiday').text(holidayName).appendTo($heading);
        }
        $('<button>')
            .attr('type', 'button')
            .addClass('btn btn-sm btn-outline-primary calendar-day-add-trigger')
            .attr('data-calendar-date', date)
            .attr('data-bs-toggle', 'modal')
            .attr('data-bs-target', '#registerCalendarEvent')
            .attr('aria-label', date + 'に予定を追加')
            .text('予定を追加')
            .appendTo($heading);
        $day.append($heading);

        var $allDay = $('<section>').addClass('calendar-day-all-day').attr('aria-label', '終日・複数日');
        $('<strong>').addClass('calendar-view-section-label').text('終日・複数日').appendTo($allDay);
        var $allDayEntries = $('<div>').addClass('calendar-day-all-day-entries').appendTo($allDay);
        if (layout.all_day.length === 0) {
            $('<span>').addClass('calendar-view-empty text-muted').text('予定はありません').appendTo($allDayEntries);
        } else {
            layout.all_day.forEach(function (item) {
                appendDayEntry($allDayEntries, item, date, 'calendar-day-list-entry');
            });
        }
        $day.append($allDay);

        var $timelineSection = $('<section>').addClass('calendar-day-time-section').attr('aria-label', '時間指定の予定');
        $('<strong>').addClass('calendar-view-section-label').text('時間指定').appendTo($timelineSection);
        var $scroll = $('<div>').addClass('calendar-day-timeline-scroll').appendTo($timelineSection);
        var $timeline = $('<div>').addClass('calendar-day-timeline').appendTo($scroll);
        for (var hour = 0; hour < 24; hour += 1) {
            var hourText = pad(hour) + ':00';
            $('<div>')
                .addClass('calendar-time-row')
                .attr('data-calendar-hour', String(hour))
                .append($('<span>').addClass('calendar-time-label').text(hourText))
                .appendTo($timeline);
        }
        var $eventsLayer = $('<div>').addClass('calendar-timeline-events').appendTo($timeline);
        layout.timed.forEach(function (item) {
            var heightMinutes = Math.max(30, item.layout_end_minute - item.start_minute);
            var $entry = appendDayEntry($eventsLayer, item, date, 'calendar-timeline-entry');
            var timeState = item.open_ended ? '開始のみ' : (item.zero_duration ? '同時刻' : '');
            $entry
                .toggleClass('calendar-timeline-entry-open-ended', item.open_ended === true)
                .toggleClass('calendar-timeline-entry-zero-duration', item.zero_duration === true)
                .attr('style', '--calendar-entry-top:' + Math.round(item.start_minute * 0.8) + 'px'
                    + ';--calendar-entry-height:' + Math.max(24, Math.round(heightMinutes * 0.8)) + 'px'
                    + ';--calendar-entry-left:' + ((item.column / item.columns) * 100).toFixed(4) + '%'
                    + ';--calendar-entry-width:' + (100 / item.columns).toFixed(4) + '%;');
            if (timeState !== '') {
                $('<span>').addClass('calendar-timeline-state').text(timeState).appendTo($entry);
            }
        });
        if (layout.timed.length === 0) {
            $('<span>').addClass('calendar-timeline-empty text-muted').text('時間指定の予定はありません').appendTo($eventsLayer);
        }
        $day.append($timelineSection);
        $days.append($day);
    }

    function renderWeekCalendar($card, data) {
        var views = calendarViewModule();
        var monthLayout = window.iGuguruCalendarMonthLayout;
        var holidays = data.holidays && typeof data.holidays === 'object' ? data.holidays : {};
        var today = localIsoDate(new Date());
        var itemsByDate = monthLayout && typeof monthLayout.place === 'function'
            ? monthLayout.place(data)
            : {};
        var $days = $card.find('.calendar-days')
            .removeClass('calendar-month-view calendar-day-view')
            .addClass('calendar-week-view calendar-month-layout-ready')
            .empty()
            .attr('role', 'grid')
            .attr('aria-label', '週間Calendar')
            .attr('aria-busy', 'false');
        (views ? views.dates(data.range_start, data.range_end) : []).forEach(function (date) {
            var holidayName = typeof holidays[date] === 'string' ? String(holidays[date]).trim() : '';
            var $day = $('<section>')
                .addClass('calendar-day calendar-week-day')
                .toggleClass('calendar-day-today', date === today)
                .toggleClass('calendar-day-holiday', holidayName !== '')
                .attr('role', 'gridcell')
                .attr('data-calendar-date', date);
            if (date === today) {
                $day.attr('aria-current', 'date');
            }
            var $heading = $('<div>').addClass('calendar-week-day-heading');
            $('<button>')
                .attr('type', 'button')
                .addClass('calendar-week-day-number calendar-day-add-trigger')
                .attr('data-calendar-date', date)
                .attr('data-bs-toggle', 'modal')
                .attr('data-bs-target', '#registerCalendarEvent')
                .attr('aria-label', date + 'に予定を追加')
                .text(views.japaneseDate(date, false))
                .appendTo($heading);
            if (holidayName !== '') {
                $('<span>').addClass('calendar-view-holiday').text(holidayName).appendTo($heading);
            }
            $day.append($heading);
            var $entries = $('<div>').addClass('calendar-day-entries');
            (itemsByDate[date] || []).forEach(function (item) {
                $entries.append(calendarItemElement(item, date));
            });
            if ((itemsByDate[date] || []).length === 0) {
                $('<span>').addClass('calendar-view-empty text-muted').text('予定なし').appendTo($entries);
            }
            $day.append($entries);
            $days.append($day);
        });
    }

    function renderCalendar($card, data) {
        var mode = String(data.view_mode || 'month');
        updateCalendarChrome($card, data);
        if (mode === 'day' && calendarViewModule()) {
            renderDayCalendar($card, data);
        } else if (mode === 'week' && calendarViewModule()) {
            renderWeekCalendar($card, data);
        } else {
            renderMonthCalendar($card, data);
        }
        $card
            .data('calendar-range-events', Array.isArray(data.events) ? data.events : [])
            .data('calendar-range-data', data)
            .attr('data-calendar-range-ready', '1')
            .attr('data-calendar-recurrence-ready', '1')
            .attr('data-calendar-event-meta-ready', '1');
        $card.trigger('calendar:rangeLoaded', [data]);
    }

    function refreshVisibleCalendars() {
        $('[data-dashboard-widget-type="calendar"]').each(function () {
            var $card = $(this);
            loadCalendarView($card, calendarViewMode($card), calendarSelectedDate($card), true);
        });
    }

    function requestHolidayRefresh() {
        if (holidayRefreshRequested) {
            return;
        }
        holidayRefreshRequested = true;
        apiRequest('calendar.holiday.refresh', {}, 6500)
            .done(function (response) {
                var data = response && response.ok === true && response.data ? response.data : null;
                if (!data || (data.refreshed !== true && Number(data.count || 0) <= 0)) {
                    return;
                }
                $('[data-dashboard-widget-type="calendar"]').each(function () {
                    var $calendar = $(this);
                    loadCalendarView($calendar, calendarViewMode($calendar), calendarSelectedDate($calendar), true);
                });
            });
    }

    function loadCalendarView($card, modeValue, anchorValue, skipHolidayRefresh) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        var views = calendarViewModule();
        var mode = views ? views.validMode(modeValue) : 'month';
        var anchor = /^\d{4}-\d{2}-\d{2}$/.test(String(anchorValue || ''))
            ? String(anchorValue)
            : localIsoDate(new Date());
        var period = views ? views.period(mode, anchor) : null;
        if (!period) {
            var fallback = utcDateFromIso(anchor);
            if (fallback) {
                period = {
                    mode: 'month',
                    anchor: anchor,
                    start: fallback.getUTCFullYear() + '-' + pad(fallback.getUTCMonth() + 1) + '-01',
                    end: fallback.getUTCFullYear() + '-' + pad(fallback.getUTCMonth() + 1) + '-'
                        + pad(new Date(Date.UTC(fallback.getUTCFullYear(), fallback.getUTCMonth() + 1, 0)).getUTCDate())
                };
            }
        }
        if (!/^\d+$/.test(widgetId) || !period || period.start < '2000-01-01' || period.end > '2100-12-31') {
            return;
        }
        var $days = $card.find('.calendar-days');
        var requestSequence = Number($card.data('calendar-range-request-sequence') || 0) + 1;
        var previousRequest = $card.data('calendar-range-request');
        if (previousRequest && typeof previousRequest.abort === 'function') {
            previousRequest.abort();
        }
        $card
            .data('calendar-range-request-sequence', requestSequence)
            .attr('data-calendar-range-ready', '0')
            .attr('data-calendar-view', period.mode)
            .attr('data-calendar-selected-date', period.anchor);
        $days.attr('aria-busy', 'true').empty();
        var $loading = $('<div>').addClass('calendar-loading').attr('role', 'status').appendTo($days);
        appendLoadingText($loading, 'Calendarを読み込んでいます');
        var activeRequest = rangeRequest({
            widget_id: widgetId,
            calendar_range_start: period.start,
            calendar_range_end: period.end
        });
        $card.data('calendar-range-request', activeRequest);
        activeRequest
            .done(function (response) {
                if (Number($card.data('calendar-range-request-sequence') || 0) !== requestSequence) {
                    return;
                }
                var data = apiResponseData(response);
                if (data !== null) {
                    var anchorDate = utcDateFromIso(period.anchor);
                    renderCalendar($card, $.extend({}, data, {
                        view_mode: period.mode,
                        anchor_date: period.anchor,
                        year: anchorDate ? anchorDate.getUTCFullYear() : 0,
                        month: anchorDate ? anchorDate.getUTCMonth() + 1 : 0,
                        month_start: period.start,
                        month_end: period.end
                    }));
                    if (skipHolidayRefresh !== true && data.holiday_refresh_due === true) {
                        requestHolidayRefresh();
                    }
                } else {
                    $days.attr('aria-busy', 'false').empty().append($('<div>').addClass('calendar-error').attr('role', 'alert').text('Calendarを読み込めませんでした'));
                }
            })
            .fail(function (xhr, textStatus) {
                if (textStatus === 'abort' || Number($card.data('calendar-range-request-sequence') || 0) !== requestSequence) {
                    return;
                }
                $days.attr('aria-busy', 'false').empty().append($('<div>').addClass('calendar-error').attr('role', 'alert').text(apiErrorMessage(xhr, textStatus)));
            })
            .always(function () {
                if (Number($card.data('calendar-range-request-sequence') || 0) === requestSequence) {
                    $card.removeData('calendar-range-request');
                }
            });
    }

    function loadCalendar($card, year, month, skipHolidayRefresh) {
        var validYear = Number(year || 0);
        var validMonth = Number(month || 0);
        if (validYear < 2000 || validYear > 2100 || validMonth < 1 || validMonth > 12) {
            return;
        }
        loadCalendarView($card, 'month', validYear + '-' + pad(validMonth) + '-01', skipHolidayRefresh);
    }

    function moveCalendarMonth($card, offset) {
        var views = calendarViewModule();
        var mode = calendarViewMode($card);
        var anchor = calendarSelectedDate($card);
        var target = views ? views.move(mode, anchor, offset) : '';
        if (!views) {
            var year = Number($card.attr('data-calendar-year') || new Date().getFullYear());
            var month = Number($card.attr('data-calendar-month') || (new Date().getMonth() + 1));
            var fallbackTarget = new Date(year, month - 1 + offset, 1);
            target = localIsoDate(fallbackTarget);
        }
        if (target !== '') {
            loadCalendarView($card, mode, target);
        }
    }

    function initCalendars() {
        var today = localIsoDate(new Date());
        if (typeof window.ResizeObserver === 'function') {
            calendarResizeObserver = new window.ResizeObserver(function (entries) {
                entries.forEach(function (entry) {
                    updateCompactCalendar($(entry.target));
                });
            });
        }
        $('[data-dashboard-widget-type="calendar"]').each(function () {
            var $card = $(this);
            observeCalendar($card);
            loadCalendarView($card, 'month', today);
        });
    }

    function bindEvents() {
        $(document)
            .off('submit' + eventNamespace, '#registerCalendarWidgetForm')
            .on('submit' + eventNamespace, '#registerCalendarWidgetForm', function (event) {
                event.preventDefault();
                addCalendarWidget($(this));
            })
            .off('click' + eventNamespace, '.calendar-widget-edit-trigger')
            .on('click' + eventNamespace, '.calendar-widget-edit-trigger', function () {
                editCalendarWidget($(this));
            })
            .off('submit' + eventNamespace, '#changeCalendarWidgetForm')
            .on('submit' + eventNamespace, '#changeCalendarWidgetForm', function (event) {
                event.preventDefault();
                changeCalendarWidget($(this));
            })
            .off('click' + eventNamespace, '.delete_calendar_widget')
            .on('click' + eventNamespace, '.delete_calendar_widget', function () {
                deleteCalendarWidget($(this));
            })
            .off('click' + eventNamespace, '.calendar-event-add-trigger, .calendar-day-add-trigger')
            .on('click' + eventNamespace, '.calendar-event-add-trigger, .calendar-day-add-trigger', function () {
                var $trigger = $(this);
                var $card = $trigger.closest('[data-dashboard-widget-type="calendar"]');
                var selectedDate = String($trigger.attr('data-calendar-date') || '');
                if (/^\d{4}-\d{2}-\d{2}$/.test(selectedDate)) {
                    $card.attr('data-calendar-selected-date', selectedDate);
                }
                prepareCalendarEventAdd($trigger);
            })
            .off('submit' + eventNamespace, '#registerCalendarEventForm')
            .on('submit' + eventNamespace, '#registerCalendarEventForm', function (event) {
                event.preventDefault();
                addCalendarEvent($(this));
            })
            .off('click' + eventNamespace, '.calendar-event-edit-trigger')
            .on('click' + eventNamespace, '.calendar-event-edit-trigger', function () {
                editCalendarEvent($(this));
            })
            .off('submit' + eventNamespace, '#changeCalendarEventForm')
            .on('submit' + eventNamespace, '#changeCalendarEventForm', function (event) {
                event.preventDefault();
                changeCalendarEvent($(this));
            })
            .off('click' + eventNamespace, '.delete_calendar_event')
            .on('click' + eventNamespace, '.delete_calendar_event', function () {
                deleteCalendarEvent($(this));
            })
            .off('click' + eventNamespace, '.calendar-prev-month')
            .on('click' + eventNamespace, '.calendar-prev-month', function () {
                moveCalendarMonth($(this).closest('[data-dashboard-widget-type="calendar"]'), -1);
            })
            .off('click' + eventNamespace, '.calendar-next-month')
            .on('click' + eventNamespace, '.calendar-next-month', function () {
                moveCalendarMonth($(this).closest('[data-dashboard-widget-type="calendar"]'), 1);
            })
            .off('click' + eventNamespace, '.calendar-today')
            .on('click' + eventNamespace, '.calendar-today', function () {
                var $card = $(this).closest('[data-dashboard-widget-type="calendar"]');
                loadCalendarView($card, calendarViewMode($card), localIsoDate(new Date()));
            })
            .off('click' + eventNamespace, '.calendar-view-mode')
            .on('click' + eventNamespace, '.calendar-view-mode', function () {
                var $button = $(this);
                var $card = $button.closest('[data-dashboard-widget-type="calendar"]');
                loadCalendarView(
                    $card,
                    String($button.attr('data-calendar-view-mode') || 'month'),
                    calendarSelectedDate($card)
                );
            })
            .off('calendar:occurrenceChanged' + eventNamespace)
            .on('calendar:occurrenceChanged' + eventNamespace, refreshVisibleCalendars);
    }

    function init() {
        bindEvents();
        initCalendars();
    }

    $(init);
})(jQuery, window, document);
