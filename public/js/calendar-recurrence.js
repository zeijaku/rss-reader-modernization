(function ($, window, document) {
    'use strict';

    var endpoint = './calendar_recurrence_api.php';
    var namespace = '.iguguruCalendarRecurrence';
    var repeatValues = ['none', 'daily', 'weekly', 'monthly', 'yearly'];
    var colorValues = ['red', 'blue', 'green'];

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
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + (type === 'success' ? 'success' : 'danger'))
            .attr('role', type === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function errorMessage(xhr, status) {
        if (status === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return '通信に失敗しました';
    }

    function request(action, data, timeout) {
        return $.ajax({
            url: endpoint,
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 4000,
            data: $.extend({}, data || {}, {
                action: action,
                csrf_token: csrfToken()
            })
        }).always(function (first, textStatus, third) {
            var xhr = third && typeof third.getResponseHeader === 'function' ? third : first;
            updateCsrf(xhr && typeof xhr.getResponseHeader === 'function' ? xhr : null);
        });
    }

    function validRepeat(value) {
        var repeat = String(value || 'none');
        return repeatValues.indexOf(repeat) !== -1 ? repeat : 'none';
    }

    function validColor(value) {
        var color = String(value || 'blue');
        return colorValues.indexOf(color) !== -1 ? color : 'blue';
    }

    function formValue(form, selector) {
        var element = form.querySelector(selector);
        return element ? String(element.value || '') : '';
    }

    function createSmallLabel(text, inputId) {
        var label = document.createElement('label');
        label.className = 'form-label';
        label.setAttribute('for', inputId);
        var small = document.createElement('small');
        small.className = 'text-dark';
        small.textContent = text;
        label.appendChild(small);
        return label;
    }

    function createRepeatFields(formId, prefix) {
        var form = document.getElementById(formId);
        if (!form || form.querySelector('.calendar-event-recurrence-fields')) {
            return;
        }
        var body = form.querySelector('.modal-body') || form;
        var wrapper = document.createElement('div');
        wrapper.className = 'calendar-event-recurrence-fields';

        var repeatGroup = document.createElement('div');
        repeatGroup.className = 'mb-3';
        var repeat = document.createElement('select');
        repeat.className = 'form-select ' + prefix + 'CalendarEventRepeatType';
        repeat.id = prefix + 'CalendarEventRepeatType';
        [
            ['none', '繰り返さない'],
            ['daily', '毎日'],
            ['weekly', '毎週'],
            ['monthly', '毎月'],
            ['yearly', '毎年']
        ].forEach(function (optionData) {
            var option = document.createElement('option');
            option.value = optionData[0];
            option.textContent = optionData[1];
            repeat.appendChild(option);
        });
        repeatGroup.appendChild(createSmallLabel('繰り返し', repeat.id));
        repeatGroup.appendChild(repeat);
        wrapper.appendChild(repeatGroup);

        var untilGroup = document.createElement('div');
        untilGroup.className = 'mb-3 calendar-event-repeat-until-field';
        var until = document.createElement('input');
        until.type = 'date';
        until.className = 'form-control ' + prefix + 'CalendarEventRepeatUntil';
        until.id = prefix + 'CalendarEventRepeatUntil';
        untilGroup.appendChild(createSmallLabel('繰り返し終了日', until.id));
        untilGroup.appendChild(until);
        wrapper.appendChild(untilGroup);

        var help = document.createElement('div');
        help.className = 'form-text calendar-event-recurrence-help';
        help.textContent = '終了日は任意です。月次の存在しない日、年次の存在しない日はその回をスキップします。変更・削除はシリーズ全体に反映されます。';
        wrapper.appendChild(help);

        var status = document.createElement('div');
        status.className = 'small text-muted calendar-event-recurrence-loading';
        status.setAttribute('role', 'status');
        status.hidden = true;
        status.textContent = '繰り返し情報を読み込んでいます...';
        wrapper.appendChild(status);

        var detailFields = body.querySelector('.calendar-event-detail-fields');
        var note = body.querySelector('textarea');
        var noteGroup = note ? note.closest('.mb-3') : null;
        if (detailFields && detailFields.parentNode === body) {
            detailFields.insertAdjacentElement('afterend', wrapper);
        } else if (noteGroup && noteGroup.parentNode === body) {
            body.insertBefore(wrapper, noteGroup);
        } else {
            body.appendChild(wrapper);
        }
        syncRepeatState(form);
    }

    function ensureFields() {
        createRepeatFields('registerCalendarEventForm', 'register');
        createRepeatFields('changeCalendarEventForm', 'change');
    }

    function syncRepeatState(form) {
        if (!form) {
            return;
        }
        var repeat = form.querySelector('[class*="CalendarEventRepeatType"]');
        var until = form.querySelector('[class*="CalendarEventRepeatUntil"]');
        var group = form.querySelector('.calendar-event-repeat-until-field');
        if (!repeat || !until || !group) {
            return;
        }
        var recurring = validRepeat(repeat.value) !== 'none';
        group.hidden = !recurring;
        until.disabled = !recurring;
        if (!recurring) {
            until.value = '';
        }
    }

    function setFormRecurrenceReady(form, ready) {
        if (form) {
            form.setAttribute('data-calendar-recurrence-submit-ready', ready ? '1' : '0');
        }
    }

    function setRepeatLoading(form, loading) {
        if (!form) {
            return;
        }
        var status = form.querySelector('.calendar-event-recurrence-loading');
        if (status) {
            status.hidden = !loading;
        }
        setFormRecurrenceReady(form, !loading);
    }

    function resetAddFields() {
        var form = document.getElementById('registerCalendarEventForm');
        if (!form) {
            return;
        }
        $('.registerCalendarEventRepeatType').val('none');
        $('.registerCalendarEventRepeatUntil').val('');
        syncRepeatState(form);
        setRepeatLoading(form, false);
    }

    function eventPayload(form) {
        var isChange = form.id === 'changeCalendarEventForm';
        var prefix = isChange ? 'change' : 'register';
        var allDay = form.querySelector('.' + prefix + 'CalendarEventAllDay');
        var isAllDay = !allDay || allDay.checked === true;
        return {
            calendar_event_title: formValue(form, '.' + prefix + 'CalendarEventTitleValue'),
            calendar_event_start_date: formValue(form, '.' + prefix + 'CalendarEventStartDate'),
            calendar_event_end_date: formValue(form, '.' + prefix + 'CalendarEventEndDate'),
            calendar_event_note: formValue(form, '.' + prefix + 'CalendarEventNote'),
            calendar_event_color: validColor(formValue(form, '.' + prefix + 'CalendarEventColor')),
            calendar_event_all_day: isAllDay ? '1' : '0',
            calendar_event_start_time: isAllDay ? '' : formValue(form, '.' + prefix + 'CalendarEventStartTime'),
            calendar_event_end_time: isAllDay ? '' : formValue(form, '.' + prefix + 'CalendarEventEndTime'),
            calendar_event_url: formValue(form, '.' + prefix + 'CalendarEventUrl'),
            calendar_event_repeat_type: validRepeat(formValue(form, '.' + prefix + 'CalendarEventRepeatType')),
            calendar_event_repeat_until: formValue(form, '.' + prefix + 'CalendarEventRepeatUntil')
        };
    }

    function dateDiffDays(startValue, endValue) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(startValue) || !/^\d{4}-\d{2}-\d{2}$/.test(endValue)) {
            return null;
        }
        var start = Date.parse(startValue + 'T00:00:00Z');
        var end = Date.parse(endValue + 'T00:00:00Z');
        if (!Number.isFinite(start) || !Number.isFinite(end) || end < start) {
            return null;
        }
        return Math.round((end - start) / 86400000);
    }

    function validatePayload(form, payload) {
        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
            return false;
        }
        if (payload.calendar_event_all_day === '0' && payload.calendar_event_start_time === '') {
            showNotice('時刻を指定する予定は開始時刻を入力してください', 'danger');
            return false;
        }
        if (payload.calendar_event_all_day === '0'
            && payload.calendar_event_start_date === payload.calendar_event_end_date
            && payload.calendar_event_end_time !== ''
            && payload.calendar_event_end_time < payload.calendar_event_start_time) {
            showNotice('同日の終了時刻は開始時刻以降を指定してください', 'danger');
            return false;
        }
        if (payload.calendar_event_repeat_type !== 'none'
            && payload.calendar_event_repeat_until !== ''
            && payload.calendar_event_repeat_until < payload.calendar_event_start_date) {
            showNotice('繰り返し終了日は開始日以降を指定してください', 'danger');
            return false;
        }

        var spanDays = dateDiffDays(payload.calendar_event_start_date, payload.calendar_event_end_date);
        if (payload.calendar_event_repeat_type === 'daily' && spanDays !== 0) {
            showNotice('毎日の繰り返しは開始日と終了日を同じ日にしてください', 'danger');
            return false;
        }
        if (payload.calendar_event_repeat_type === 'weekly' && spanDays !== null && spanDays > 6) {
            showNotice('毎週の繰り返しは予定期間を7日未満にしてください', 'danger');
            return false;
        }
        if (payload.calendar_event_repeat_type === 'monthly' && spanDays !== null && spanDays > 27) {
            showNotice('毎月の繰り返しは予定期間を28日未満にしてください', 'danger');
            return false;
        }
        return true;
    }

    function submitEvent(form) {
        if (String(form.getAttribute('data-calendar-recurrence-submit-ready') || '0') !== '1') {
            showNotice('繰り返し情報の確認が完了していないため保存出来ません', 'danger');
            return;
        }
        var isChange = form.id === 'changeCalendarEventForm';
        var payload = eventPayload(form);
        if (!validatePayload(form, payload)) {
            return;
        }
        if (isChange) {
            payload.event_id = formValue(form, '.changeCalendarEventId');
        }
        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
        }
        request(isChange ? 'calendar.recurrence.update' : 'calendar.recurrence.create', payload, 3500)
            .done(function (response) {
                if (response && response.ok === true) {
                    window.location.reload();
                    return;
                }
                showNotice(
                    response && response.error && response.error.message ? response.error.message : '予定を保存出来ませんでした',
                    'danger'
                );
            })
            .fail(function (xhr, status) {
                showNotice(errorMessage(xhr, status), 'danger');
            })
            .always(function () {
                if (button) {
                    button.disabled = false;
                }
            });
    }

    function captureSubmit(event) {
        var form = event.target;
        if (!form || (form.id !== 'registerCalendarEventForm' && form.id !== 'changeCalendarEventForm')) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        submitEvent(form);
    }

    function pad(value) {
        return String(value).padStart(2, '0');
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
        var guard = 0;
        if (!current || !last || current.getTime() > last.getTime()) {
            return;
        }
        while (current.getTime() <= last.getTime() && guard < 370) {
            callback(isoFromUtcDate(current));
            current.setUTCDate(current.getUTCDate() + 1);
            guard += 1;
        }
    }

    function publicTime(value) {
        var time = String(value || '');
        return /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(time) ? time : '';
    }

    function timeLabel(item, date) {
        if (item.all_day !== false || publicTime(item.start_time) === '') {
            return '';
        }
        var startTime = publicTime(item.start_time);
        var endTime = publicTime(item.end_time);
        if (item.occurrence_start_date === item.occurrence_end_date) {
            return startTime + (endTime !== '' ? '–' + endTime : '');
        }
        if (date === item.occurrence_start_date) {
            return startTime + '〜';
        }
        if (date === item.occurrence_end_date && endTime !== '') {
            return '〜' + endTime;
        }
        return '';
    }

    function eventButton(item, date) {
        var color = validColor(item.color);
        var title = String(item.title || '');
        var note = String(item.note || '');
        var $button = $('<button>')
            .attr('type', 'button')
            .addClass('calendar-entry calendar-event-entry calendar-event-edit-trigger calendar-event-recurring calendar-event-color-' + color)
            .attr('data-event-id', String(item.event_id || ''))
            .attr('data-event-title', title)
            .attr('data-event-start-date', String(item.source_start_date || ''))
            .attr('data-event-end-date', String(item.source_end_date || ''))
            .attr('data-event-note', note)
            .attr('data-calendar-occurrence-start-date', String(item.occurrence_start_date || ''))
            .attr('data-calendar-occurrence-end-date', String(item.occurrence_end_date || ''))
            .attr('data-calendar-event-color', color)
            .attr('data-calendar-event-color-ready', '1')
            .attr('data-calendar-event-meta-ready', '1')
            .attr('data-calendar-event-all-day', item.all_day === false ? '0' : '1')
            .attr('data-calendar-event-start-time', publicTime(item.start_time))
            .attr('data-calendar-event-end-time', publicTime(item.end_time))
            .attr('data-calendar-event-url', String(item.url || ''))
            .attr('data-calendar-event-repeat-type', validRepeat(item.repeat_type))
            .attr('data-calendar-event-repeat-until', String(item.repeat_until || ''))
            .attr('data-bs-toggle', 'modal')
            .attr('data-bs-target', '#changeCalendarEvent')
            .attr('title', (note ? title + ': ' + note : title) + '（繰り返し予定）');

        $button.append($('<i>').addClass('far fa-calendar').attr('aria-hidden', 'true'));
        var label = timeLabel(item, date);
        if (label !== '') {
            $button.append($('<span>').addClass('calendar-event-time-label').text(label));
        }
        $button.append($('<span>').addClass('calendar-event-repeat-label').attr('aria-hidden', 'true').text('↻'));
        $button.append($('<span>').text(title));
        return $button;
    }

    function eventMap(events) {
        var map = {};
        (Array.isArray(events) ? events : []).forEach(function (item) {
            var id = String(item && item.event_id || '');
            if (/^[1-9][0-9]*$/.test(id) && map[id] === undefined) {
                map[id] = item;
            }
        });
        return map;
    }

    function renderRecurring($card, events) {
        var list = Array.isArray(events) ? events : [];
        var ids = {};
        list.forEach(function (item) {
            var id = String(item && item.event_id || '');
            if (/^[1-9][0-9]*$/.test(id)) {
                ids[id] = true;
            }
        });

        $card.find('.calendar-event-recurring').remove();
        Object.keys(ids).forEach(function (id) {
            $card.find('.calendar-event-edit-trigger[data-event-id="' + id + '"]').remove();
        });

        list.forEach(function (item) {
            var start = String(item.occurrence_start_date || '');
            var end = String(item.occurrence_end_date || '');
            eachIsoDate(start, end, function (date) {
                var $day = $card.find('.calendar-day[data-calendar-date="' + date + '"]').first();
                var $entries = $day.find('.calendar-day-entries').first();
                if ($entries.length > 0) {
                    $entries.append(eventButton(item, date));
                }
            });
        });
    }

    function storeRecurring($card, events, year, month) {
        $card
            .data('calendar-recurrence-events', Array.isArray(events) ? events : [])
            .data('calendar-recurrence-map', eventMap(events))
            .data('calendar-recurrence-year', Number(year))
            .data('calendar-recurrence-month', Number(month))
            .attr('data-calendar-recurrence-ready', '1');
        renderRecurring($card, events);
    }

    function loadRecurringForCard($card, year, month) {
        $card.attr('data-calendar-recurrence-ready', '0');
        return request('calendar.recurrence.list', {
            calendar_year: String(year),
            calendar_month: String(month)
        }, 3500).done(function (response) {
            if (response && response.ok === true && response.data) {
                storeRecurring($card, response.data.events || [], year, month);
            }
        }).fail(function (xhr, status) {
            showNotice(errorMessage(xhr, status), 'danger');
        });
    }

    function populateRepeatFields(form, item) {
        var repeat = item ? validRepeat(item.repeat_type) : 'none';
        var until = item && item.repeat_until ? String(item.repeat_until) : '';
        $('.changeCalendarEventRepeatType').val(repeat);
        $('.changeCalendarEventRepeatUntil').val(until);
        if (item && item.source_start_date) {
            $('.changeCalendarEventStartDate').val(String(item.source_start_date));
        }
        if (item && item.source_end_date) {
            $('.changeCalendarEventEndDate').val(String(item.source_end_date));
        }
        syncRepeatState(form);
        setRepeatLoading(form, false);
        $('.delete_calendar_event').text(repeat === 'none' ? '削除する' : 'シリーズを削除');
    }

    function prepareEditFields(trigger) {
        var form = document.getElementById('changeCalendarEventForm');
        var $entry = $(trigger);
        var $card = $entry.closest('[data-dashboard-widget-type="calendar"]');
        var eventId = String(trigger.getAttribute('data-event-id') || '');
        if (!form) {
            return;
        }
        ensureFields();

        var repeatAttr = trigger.getAttribute('data-calendar-event-repeat-type');
        if (repeatAttr !== null) {
            populateRepeatFields(form, {
                repeat_type: repeatAttr,
                repeat_until: trigger.getAttribute('data-calendar-event-repeat-until') || '',
                source_start_date: trigger.getAttribute('data-event-start-date') || '',
                source_end_date: trigger.getAttribute('data-event-end-date') || ''
            });
            return;
        }

        var map = $card.data('calendar-recurrence-map') || {};
        if (String($card.attr('data-calendar-recurrence-ready') || '') === '1') {
            populateRepeatFields(form, map[eventId] || null);
            return;
        }

        setRepeatLoading(form, true);
        var year = Number($card.attr('data-calendar-year') || 0);
        var month = Number($card.attr('data-calendar-month') || 0);
        if (year < 2000 || year > 2100 || month < 1 || month > 12) {
            showNotice('繰り返し情報を確認出来ないため変更を保存出来ません', 'danger');
            return;
        }
        loadRecurringForCard($card, year, month).done(function (response) {
            if (!(response && response.ok === true)) {
                return;
            }
            var currentMap = $card.data('calendar-recurrence-map') || {};
            populateRepeatFields(form, currentMap[eventId] || null);
        });
    }

    function captureClick(event) {
        var target = event.target && typeof event.target.closest === 'function' ? event.target : null;
        if (!target) {
            return;
        }
        var addTrigger = target.closest('.calendar-event-add-trigger, .calendar-day-add-trigger');
        if (addTrigger) {
            ensureFields();
            resetAddFields();
            return;
        }
        var editTrigger = target.closest('.calendar-event-edit-trigger');
        if (editTrigger) {
            prepareEditFields(editTrigger);
        }
    }

    function settingValue(settings, key) {
        var data = settings ? settings.data : null;
        if (data && typeof data === 'object' && data[key] !== undefined) {
            return String(data[key]);
        }
        if (typeof data === 'string') {
            try {
                return String(new URLSearchParams(data).get(key) || '');
            } catch (error) {
                return '';
            }
        }
        return '';
    }

    function rerenderMonth(year, month) {
        $('[data-dashboard-widget-type="calendar"]').each(function () {
            var $card = $(this);
            if (Number($card.attr('data-calendar-year') || 0) === year
                && Number($card.attr('data-calendar-month') || 0) === month
                && Number($card.data('calendar-recurrence-year') || 0) === year
                && Number($card.data('calendar-recurrence-month') || 0) === month) {
                renderRecurring($card, $card.data('calendar-recurrence-events') || []);
            }
        });
    }

    function bindAjaxObserver() {
        $(document)
            .off('ajaxSuccess' + namespace)
            .on('ajaxSuccess' + namespace, function (event, xhr, settings) {
                var action = settingValue(settings, 'action');
                var year = Number(settingValue(settings, 'calendar_year'));
                var month = Number(settingValue(settings, 'calendar_month'));
                if (year < 2000 || year > 2100 || month < 1 || month > 12) {
                    return;
                }
                if (action === 'calendar.month.list') {
                    var widgetId = settingValue(settings, 'widget_id');
                    if (!/^[1-9][0-9]*$/.test(widgetId)) {
                        return;
                    }
                    var $card = $('[data-dashboard-widget-type="calendar"][data-dashboard-widget-id="' + widgetId + '"]').first();
                    loadRecurringForCard($card, year, month);
                    return;
                }
                if (action === 'calendar.event.meta.list' || action === 'calendar.color.list') {
                    rerenderMonth(year, month);
                }
            });
    }

    function bindFieldChanges() {
        $(document)
            .off('change' + namespace, '.registerCalendarEventRepeatType, .changeCalendarEventRepeatType')
            .on('change' + namespace, '.registerCalendarEventRepeatType, .changeCalendarEventRepeatType', function () {
                syncRepeatState(this.form);
            });
    }

    document.addEventListener('submit', captureSubmit, true);
    document.addEventListener('click', captureClick, true);
    bindAjaxObserver();
    bindFieldChanges();
    $(function () {
        window.setTimeout(ensureFields, 0);
    });
}(jQuery, window, document));
