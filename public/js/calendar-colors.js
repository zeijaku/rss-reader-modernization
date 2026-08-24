(function ($, window, document) {
    'use strict';

    var endpoint = './calendar_color_api.php';
    var namespace = '.iguguruCalendarColors';
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

    function createColorField(formId, selectClass) {
        var form = document.getElementById(formId);
        if (!form || form.querySelector('.calendar-event-color-field')) {
            return;
        }
        var body = form.querySelector('.modal-body') || form;
        var group = document.createElement('div');
        group.className = 'mb-3 calendar-event-color-field';

        var label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = '色';

        var select = document.createElement('select');
        select.className = 'form-select ' + selectClass;
        select.setAttribute('aria-label', '予定の色');
        [
            ['blue', '青 - 通常'],
            ['red', '赤 - 重要'],
            ['green', '緑 - その他']
        ].forEach(function (optionData) {
            var option = document.createElement('option');
            option.value = optionData[0];
            option.textContent = optionData[1];
            select.appendChild(option);
        });
        select.value = 'blue';

        var help = document.createElement('div');
        help.className = 'form-text';
        help.textContent = '青=通常 / 赤=重要 / 緑=その他';

        group.appendChild(label);
        group.appendChild(select);
        group.appendChild(help);

        var note = body.querySelector('textarea');
        var noteGroup = note ? note.closest('.mb-3') : null;
        if (noteGroup && noteGroup.parentNode === body) {
            body.insertBefore(group, noteGroup);
        } else {
            body.appendChild(group);
        }
    }

    function ensureFields() {
        createColorField('registerCalendarEventForm', 'registerCalendarEventColor');
        createColorField('changeCalendarEventForm', 'changeCalendarEventColor');
    }

    function formValue(form, selector) {
        var element = form.querySelector(selector);
        return element ? String(element.value || '') : '';
    }

    function eventPayload(form, colorSelector) {
        return {
            calendar_event_title: formValue(form, '[class*="CalendarEventTitleValue"]'),
            calendar_event_start_date: formValue(form, '[class*="CalendarEventStartDate"]'),
            calendar_event_end_date: formValue(form, '[class*="CalendarEventEndDate"]'),
            calendar_event_note: formValue(form, '[class*="CalendarEventNote"]'),
            calendar_event_color: validColor(formValue(form, colorSelector))
        };
    }

    function setPending(form, pending) {
        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = pending === true;
        }
    }

    function submitEvent(form) {
        var isChange = form.id === 'changeCalendarEventForm';
        var payload = eventPayload(
            form,
            isChange ? '.changeCalendarEventColor' : '.registerCalendarEventColor'
        );
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
        // calendar-core.js owns the legacy submit handler on the bubble phase.
        // Stop it here so event text/date and color are saved in one transaction.
        event.preventDefault();
        event.stopImmediatePropagation();
        submitEvent(form);
    }

    function captureClick(event) {
        var target = event.target && typeof event.target.closest === 'function' ? event.target : null;
        if (!target) {
            return;
        }
        var addTrigger = target.closest('.calendar-event-add-trigger, .calendar-day-add-trigger');
        if (addTrigger) {
            ensureFields();
            $('.registerCalendarEventColor').val('blue');
            return;
        }
        var editTrigger = target.closest('.calendar-event-edit-trigger');
        if (editTrigger) {
            ensureFields();
            $('.changeCalendarEventColor').val(validColor(editTrigger.getAttribute('data-calendar-event-color')));
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

    function decorateCard($card, colors) {
        var map = {};
        (Array.isArray(colors) ? colors : []).forEach(function (item) {
            var id = String(item && item.event_id || '');
            if (/^[1-9][0-9]*$/.test(id)) {
                map[id] = validColor(item.color);
            }
        });
        $card.find('.calendar-event-edit-trigger').each(function () {
            var $entry = $(this);
            var id = String($entry.attr('data-event-id') || '');
            var color = map[id] || 'blue';
            $entry
                .removeClass('calendar-event-color-red calendar-event-color-blue calendar-event-color-green')
                .addClass('calendar-event-color-' + color)
                .attr('data-calendar-event-color', color);
        });
    }

    function loadColorsForCard(widgetId, year, month) {
        var $card = $('[data-dashboard-widget-type="calendar"][data-dashboard-widget-id="' + widgetId + '"]').first();
        if ($card.length === 0) {
            return;
        }
        request('calendar.color.list', {
            calendar_year: String(year),
            calendar_month: String(month)
        }, 3000)
            .done(function (response) {
                if (response && response.ok === true && response.data) {
                    decorateCard($card, response.data.colors || []);
                }
            });
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
                loadColorsForCard(widgetId, year, month);
            });
    }

    document.addEventListener('submit', captureSubmit, true);
    document.addEventListener('click', captureClick, true);
    bindMonthLoadObserver();
    $(ensureFields);
}(jQuery, window, document));
