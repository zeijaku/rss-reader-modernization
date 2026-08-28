(function ($, window, document) {
    'use strict';

    var endpoint = './calendar_color_api.php';
    var namespace = '.iguguruCalendarEventDetails';
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

    function validColor(value) {
        var color = String(value || '');
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

    function createEventDetailFields(formId, prefix) {
        var form = document.getElementById(formId);
        if (!form || form.querySelector('.calendar-event-detail-fields')) {
            return;
        }
        var body = form.querySelector('.modal-body') || form;
        var note = body.querySelector('textarea');
        var noteGroup = note ? note.closest('.mb-3') : null;
        var wrapper = document.createElement('div');
        wrapper.className = 'calendar-event-detail-fields';

        var allDayGroup = document.createElement('div');
        allDayGroup.className = 'form-check mb-3 calendar-event-all-day-field';
        var allDay = document.createElement('input');
        allDay.type = 'checkbox';
        allDay.className = 'form-check-input ' + prefix + 'CalendarEventAllDay';
        allDay.id = prefix + 'CalendarEventAllDay';
        allDay.checked = true;
        var allDayLabel = document.createElement('label');
        allDayLabel.className = 'form-check-label';
        allDayLabel.setAttribute('for', allDay.id);
        allDayLabel.textContent = '終日';
        allDayGroup.appendChild(allDay);
        allDayGroup.appendChild(allDayLabel);
        wrapper.appendChild(allDayGroup);

        var timeRow = document.createElement('div');
        timeRow.className = 'row g-2 calendar-event-time-fields';

        var startGroup = document.createElement('div');
        startGroup.className = 'mb-3 col-12 col-sm-6';
        var startTime = document.createElement('input');
        startTime.type = 'time';
        startTime.step = '60';
        startTime.className = 'form-control ' + prefix + 'CalendarEventStartTime';
        startTime.id = prefix + 'CalendarEventStartTime';
        startGroup.appendChild(createSmallLabel('開始時刻', startTime.id));
        startGroup.appendChild(startTime);

        var endGroup = document.createElement('div');
        endGroup.className = 'mb-3 col-12 col-sm-6';
        var endTime = document.createElement('input');
        endTime.type = 'time';
        endTime.step = '60';
        endTime.className = 'form-control ' + prefix + 'CalendarEventEndTime';
        endTime.id = prefix + 'CalendarEventEndTime';
        endGroup.appendChild(createSmallLabel('終了時刻', endTime.id));
        endGroup.appendChild(endTime);

        timeRow.appendChild(startGroup);
        timeRow.appendChild(endGroup);
        wrapper.appendChild(timeRow);

        var urlGroup = document.createElement('div');
        urlGroup.className = 'mb-3 calendar-event-url-field';
        var url = document.createElement('input');
        url.type = 'url';
        url.inputMode = 'url';
        url.maxLength = 2048;
        url.className = 'form-control ' + prefix + 'CalendarEventUrl';
        url.id = prefix + 'CalendarEventUrl';
        url.placeholder = 'https://example.com/...';
        urlGroup.appendChild(createSmallLabel('関連URL', url.id));
        urlGroup.appendChild(url);
        var help = document.createElement('div');
        help.className = 'form-text';
        help.textContent = '任意。http / https のURLのみ保存出来ます。';
        urlGroup.appendChild(help);
        wrapper.appendChild(urlGroup);

        var loading = document.createElement('div');
        loading.className = 'small text-muted calendar-event-detail-loading';
        loading.setAttribute('role', 'status');
        loading.hidden = true;
        loading.textContent = '時刻・URL情報を読み込んでいます...';
        wrapper.appendChild(loading);

        if (noteGroup && noteGroup.parentNode === body) {
            body.insertBefore(wrapper, noteGroup);
        } else {
            body.appendChild(wrapper);
        }
        syncTimeState(form);
    }

    function ensureFields() {
        createEventDetailFields('registerCalendarEventForm', 'register');
        createEventDetailFields('changeCalendarEventForm', 'change');
    }

    function syncTimeState(form) {
        var allDay = form.querySelector('[class*="CalendarEventAllDay"]');
        var startTime = form.querySelector('[class*="CalendarEventStartTime"]');
        var endTime = form.querySelector('[class*="CalendarEventEndTime"]');
        var row = form.querySelector('.calendar-event-time-fields');
        if (!allDay || !startTime || !endTime || !row) {
            return;
        }
        var isAllDay = allDay.checked === true;
        row.hidden = isAllDay;
        startTime.disabled = isAllDay;
        endTime.disabled = isAllDay;
        startTime.required = !isAllDay;
        startTime.setAttribute('aria-required', isAllDay ? 'false' : 'true');
    }

    function setPending(form, pending) {
        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = pending === true;
        }
    }

    function setMetaLoading(form, loading) {
        var status = form.querySelector('.calendar-event-detail-loading');
        form.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (status) {
            status.hidden = !loading;
        }
        setPending(form, loading);
    }

    function resetAddFields() {
        var form = document.getElementById('registerCalendarEventForm');
        if (!form) {
            return;
        }
        var allDay = form.querySelector('.registerCalendarEventAllDay');
        if (allDay) {
            allDay.checked = true;
        }
        $('.registerCalendarEventStartTime').val('');
        $('.registerCalendarEventEndTime').val('');
        $('.registerCalendarEventUrl').val('');
        syncTimeState(form);
        setMetaLoading(form, false);
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
            calendar_event_url: formValue(form, '.' + prefix + 'CalendarEventUrl')
        };
    }

    function validateEventDetailForm(form, payload) {
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
        return true;
    }

    function submitEvent(form) {
        var isChange = form.id === 'changeCalendarEventForm';
        var payload = eventPayload(form);
        if (!validateEventDetailForm(form, payload)) {
            return;
        }
        if (isChange) {
            payload.event_id = formValue(form, '.changeCalendarEventId');
        }
        setPending(form, true);
        request(isChange ? 'calendar.color.update' : 'calendar.color.create', payload, 3000)
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
                setPending(form, false);
            });
    }

    function captureSubmit(event) {
        var form = event.target;
        if (!form || (form.id !== 'registerCalendarEventForm' && form.id !== 'changeCalendarEventForm')) {
            return;
        }
        // This V1.25-C handler is registered before calendar-colors.js.
        // Keep the event text/date/color/time/URL in one transaction.
        event.preventDefault();
        event.stopImmediatePropagation();
        submitEvent(form);
    }

    function publicTime(value) {
        var time = String(value || '');
        return /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(time) ? time : '';
    }

    function normalizeMeta(item) {
        var id = String(item && item.event_id || '');
        if (!/^[1-9][0-9]*$/.test(id)) {
            return null;
        }
        var url = item && typeof item.url === 'string' ? item.url : '';
        return {
            event_id: id,
            all_day: !(item && item.all_day === false),
            start_time: publicTime(item && item.start_time),
            end_time: publicTime(item && item.end_time),
            url: url
        };
    }

    function timeLabelForEntry($entry, meta) {
        if (meta.all_day || meta.start_time === '') {
            return '';
        }
        var startDate = String($entry.attr('data-event-start-date') || '');
        var endDate = String($entry.attr('data-event-end-date') || '');
        var dayDate = String($entry.closest('.calendar-day').attr('data-calendar-date') || '');
        if (startDate === endDate) {
            return meta.start_time + (meta.end_time !== '' ? '–' + meta.end_time : '');
        }
        if (dayDate === startDate) {
            return meta.start_time + '〜';
        }
        if (dayDate === endDate && meta.end_time !== '') {
            return '〜' + meta.end_time;
        }
        return '';
    }

    function decorateEntry($entry, meta) {
        $entry
            .attr('data-calendar-event-meta-ready', '1')
            .attr('data-calendar-event-all-day', meta.all_day ? '1' : '0')
            .attr('data-calendar-event-start-time', meta.start_time)
            .attr('data-calendar-event-end-time', meta.end_time)
            .attr('data-calendar-event-url', meta.url);

        $entry.find('.calendar-event-time-label').remove();
        var label = timeLabelForEntry($entry, meta);
        if (label !== '') {
            var $title = $entry.children('span').not('.calendar-event-time-label').first();
            var $time = $('<span>').addClass('calendar-event-time-label').text(label);
            if ($title.length > 0) {
                $time.insertBefore($title);
            } else {
                $entry.append($time);
            }
        }
    }

    function decorateCard($card, events) {
        var map = {};
        (Array.isArray(events) ? events : []).forEach(function (item) {
            var meta = normalizeMeta(item);
            if (meta !== null) {
                map[meta.event_id] = meta;
            }
        });
        $card.find('.calendar-event-edit-trigger').each(function () {
            var $entry = $(this);
            var id = String($entry.attr('data-event-id') || '');
            var meta = map[id] || {
                event_id: id,
                all_day: true,
                start_time: '',
                end_time: '',
                url: ''
            };
            decorateEntry($entry, meta);
        });
        $card.attr('data-calendar-event-meta-ready', '1');
    }

    function loadMetaForCard($card, year, month) {
        if ($card.length === 0) {
            return $.Deferred().reject().promise();
        }
        return request('calendar.event.meta.list', {
            calendar_year: String(year),
            calendar_month: String(month)
        }, 3000).done(function (response) {
            if (response && response.ok === true && response.data) {
                decorateCard($card, response.data.events || []);
            }
        });
    }

    function populateEditFields(trigger) {
        var form = document.getElementById('changeCalendarEventForm');
        if (!form) {
            return;
        }
        var allDayValue = String(trigger.getAttribute('data-calendar-event-all-day') || '1');
        var allDay = form.querySelector('.changeCalendarEventAllDay');
        if (allDay) {
            allDay.checked = allDayValue !== '0';
        }
        $('.changeCalendarEventStartTime').val(String(trigger.getAttribute('data-calendar-event-start-time') || ''));
        $('.changeCalendarEventEndTime').val(String(trigger.getAttribute('data-calendar-event-end-time') || ''));
        $('.changeCalendarEventUrl').val(String(trigger.getAttribute('data-calendar-event-url') || ''));
        syncTimeState(form);
        setMetaLoading(form, false);
    }

    function prepareEditFields(trigger) {
        var form = document.getElementById('changeCalendarEventForm');
        var $entry = $(trigger);
        var $card = $entry.closest('[data-dashboard-widget-type="calendar"]');
        if (!form) {
            return;
        }
        if (String(trigger.getAttribute('data-calendar-event-meta-ready') || '') === '1') {
            populateEditFields(trigger);
            return;
        }

        var year = Number($card.attr('data-calendar-year') || 0);
        var month = Number($card.attr('data-calendar-month') || 0);
        if (year < 2000 || year > 2100 || month < 1 || month > 12) {
            setMetaLoading(form, true);
            showNotice('予定の時刻・URL情報を確認出来ないため変更を保存出来ません', 'danger');
            return;
        }

        setMetaLoading(form, true);
        loadMetaForCard($card, year, month)
            .done(function (response) {
                if (response && response.ok === true) {
                    populateEditFields(trigger);
                    return;
                }
                showNotice('予定の時刻・URL情報を読み込めませんでした', 'danger');
            })
            .fail(function (xhr, status) {
                showNotice(errorMessage(xhr, status), 'danger');
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
            ensureFields();
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

    function bindMonthLoadObserver() {
        $(document)
            .off('ajaxSuccess' + namespace)
            .on('ajaxSuccess' + namespace, function (event, xhr, settings) {
                var action = settingValue(settings, 'action');
                if (action !== 'calendar.month.list') {
                    return;
                }
                var widgetId = settingValue(settings, 'widget_id');
                var year = Number(settingValue(settings, 'calendar_year'));
                var month = Number(settingValue(settings, 'calendar_month'));
                if (!/^[1-9][0-9]*$/.test(widgetId) || year < 2000 || year > 2100 || month < 1 || month > 12) {
                    return;
                }
                var $card = $('[data-dashboard-widget-type="calendar"][data-dashboard-widget-id="' + widgetId + '"]').first();
                loadMetaForCard($card, year, month);
            });
    }

    function bindFieldChanges() {
        $(document)
            .off('change' + namespace, '.registerCalendarEventAllDay, .changeCalendarEventAllDay')
            .on('change' + namespace, '.registerCalendarEventAllDay, .changeCalendarEventAllDay', function () {
                syncTimeState(this.form);
            });
    }

    document.addEventListener('submit', captureSubmit, true);
    document.addEventListener('click', captureClick, true);
    bindMonthLoadObserver();
    bindFieldChanges();
    $(ensureFields);
}(jQuery, window, document));
