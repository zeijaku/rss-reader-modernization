(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruCalendar';

    function appCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
            .attr('role', type === 'success' ? 'status' : 'alert')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
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
            'widget_width': $('.' + prefix + 'CalendarWidgetWidth').val()
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

    function calendarItemButton(item) {
        if (item.kind === 'task') {
            return $('<button>')
                .attr('type', 'button')
                .addClass('calendar-entry calendar-task-entry task-item-edit-trigger task-priority-' + item.priority + (item.completed ? ' task-completed' : ''))
                .attr('data-task-id', String(item.task_id))
                .attr('data-task-title', item.title)
                .attr('data-task-due-date', item.due_date)
                .attr('data-task-priority', item.priority)
                .attr('data-toggle', 'modal')
                .attr('data-target', '#changeTaskItem')
                .attr('title', 'Task: ' + item.title)
                .append($('<i>').addClass(item.completed ? 'fas fa-check-circle' : 'fas fa-check-square').attr('aria-hidden', 'true'))
                .append($('<span>').text(item.title));
        }
        return $('<button>')
            .attr('type', 'button')
            .addClass('calendar-entry calendar-event-entry calendar-event-edit-trigger')
            .attr('data-event-id', String(item.event_id))
            .attr('data-event-title', item.title)
            .attr('data-event-start-date', item.start_date)
            .attr('data-event-end-date', item.end_date)
            .attr('data-event-note', item.note || '')
            .attr('data-toggle', 'modal')
            .attr('data-target', '#changeCalendarEvent')
            .attr('title', item.note ? item.title + ': ' + item.note : item.title)
            .append($('<i>').addClass('far fa-calendar').attr('aria-hidden', 'true'))
            .append($('<span>').text(item.title));
    }

    function renderCalendar($card, data) {
        var year = Number(data.year || 0);
        var month = Number(data.month || 0);
        if (!year || !month) {
            return;
        }
        $card.attr('data-calendar-year', String(year)).attr('data-calendar-month', String(month));
        $card.find('.calendar-month-label').text(year + '年' + month + '月');

        var itemsByDate = {};
        (Array.isArray(data.events) ? data.events : []).forEach(function (event) {
            var start = String(event.start_date || '');
            var end = String(event.end_date || '');
            var visibleStart = start < data.month_start ? data.month_start : start;
            var visibleEnd = end > data.month_end ? data.month_end : end;
            eachIsoDate(visibleStart, visibleEnd, function (date) {
                addItemToDate(itemsByDate, date, $.extend({'kind': 'event'}, event));
            });
        });
        (Array.isArray(data.tasks) ? data.tasks : []).forEach(function (task) {
            addItemToDate(itemsByDate, String(task.due_date || ''), $.extend({'kind': 'task'}, task));
        });

        var firstDay = new Date(year, month - 1, 1).getDay();
        var dayCount = new Date(year, month, 0).getDate();
        var cellCount = Math.ceil((firstDay + dayCount) / 7) * 7;
        var today = localIsoDate(new Date());
        var $days = $card.find('.calendar-days').empty().attr('aria-busy', 'false');
        for (var cell = 0; cell < cellCount; cell += 1) {
            var dayNumber = cell - firstDay + 1;
            if (dayNumber < 1 || dayNumber > dayCount) {
                $days.append($('<div>').addClass('calendar-day calendar-day-empty').attr('aria-hidden', 'true'));
                continue;
            }
            var date = year + '-' + pad(month) + '-' + pad(dayNumber);
            var $day = $('<div>')
                .addClass('calendar-day')
                .toggleClass('calendar-day-today', date === today)
                .attr('role', 'gridcell')
                .attr('data-calendar-date', date);
            var $dateButton = $('<button>')
                .attr('type', 'button')
                .addClass('calendar-day-number calendar-day-add-trigger')
                .attr('data-calendar-date', date)
                .attr('data-toggle', 'modal')
                .attr('data-target', '#registerCalendarEvent')
                .attr('aria-label', date + 'に予定を追加')
                .text(String(dayNumber));
            $day.append($dateButton);
            var $entries = $('<div>').addClass('calendar-day-entries');
            (itemsByDate[date] || []).forEach(function (item) {
                $entries.append(calendarItemButton(item));
            });
            $day.append($entries);
            $days.append($day);
        }
    }

    function loadCalendar($card, year, month) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        if (!/^\d+$/.test(widgetId) || year < 2000 || year > 2100 || month < 1 || month > 12) {
            return;
        }
        var $days = $card.find('.calendar-days');
        $days.attr('aria-busy', 'true').empty();
        var $loading = $('<div>').addClass('calendar-loading').attr('role', 'status').appendTo($days);
        appendLoadingText($loading, 'Calendarを読み込んでいます');
        apiRequest('calendar.month.list', {
            'widget_id': widgetId,
            'calendar_year': String(year),
            'calendar_month': String(month)
        }, 5000)
            .done(function (response) {
                var data = apiResponseData(response);
                if (data !== null) {
                    renderCalendar($card, data);
                } else {
                    $days.attr('aria-busy', 'false').empty().append($('<div>').addClass('calendar-error').attr('role', 'alert').text('Calendarを読み込めませんでした'));
                }
            })
            .fail(function (xhr, textStatus) {
                $days.attr('aria-busy', 'false').empty().append($('<div>').addClass('calendar-error').attr('role', 'alert').text(apiErrorMessage(xhr, textStatus)));
            });
    }

    function moveCalendarMonth($card, offset) {
        var year = Number($card.attr('data-calendar-year') || new Date().getFullYear());
        var month = Number($card.attr('data-calendar-month') || (new Date().getMonth() + 1));
        var target = new Date(year, month - 1 + offset, 1);
        loadCalendar($card, target.getFullYear(), target.getMonth() + 1);
    }

    function initCalendars() {
        var now = new Date();
        $('[data-dashboard-widget-type="calendar"]').each(function () {
            loadCalendar($(this), now.getFullYear(), now.getMonth() + 1);
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
                prepareCalendarEventAdd($(this));
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
                var now = new Date();
                loadCalendar($(this).closest('[data-dashboard-widget-type="calendar"]'), now.getFullYear(), now.getMonth() + 1);
            });
    }

    function init() {
        bindEvents();
        initCalendars();
    }

    $(init);
})(jQuery, window, document);
