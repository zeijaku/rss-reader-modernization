(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var endpoint = './calendar_recurrence_api.php';
    var formId = 'changeCalendarEventForm';
    var state = null;

    function value(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function validColor(color) {
        color = value(color || 'blue');
        return ['red', 'blue', 'green', 'yellow', 'purple'].indexOf(color) !== -1 ? color : 'blue';
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
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
            .attr('role', type === 'success' ? 'status' : 'alert')
            .prop('hidden', false)
            .text(value(message || '処理を完了出来ませんでした'));
    }

    function errorMessage(xhr, status) {
        if (status === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return value(xhr.responseJSON.error.message);
        }
        return '通信に失敗しました';
    }

    function request(action, data) {
        return $.ajax({
            url: endpoint,
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})
        }).always(function (first, textStatus, third) {
            var xhr = third && typeof third.getResponseHeader === 'function' ? third : first;
            updateCsrf(xhr && typeof xhr.getResponseHeader === 'function' ? xhr : null);
        });
    }

    function attribute(trigger, name, fallback) {
        var result = trigger.getAttribute(name);
        return result === null ? value(fallback) : value(result);
    }

    function triggerState(trigger) {
        var repeat = attribute(trigger, 'data-calendar-event-repeat-type', 'none');
        var occurrenceStart = attribute(trigger, 'data-calendar-occurrence-start-date', trigger.getAttribute('data-event-start-date'));
        var occurrenceEnd = attribute(trigger, 'data-calendar-occurrence-end-date', trigger.getAttribute('data-event-end-date'));
        return {
            recurring: repeat !== 'none',
            cancelled: attribute(trigger, 'data-calendar-exception-kind', '') === 'cancelled',
            exceptionKind: attribute(trigger, 'data-calendar-exception-kind', ''),
            eventId: attribute(trigger, 'data-event-id', ''),
            originalStart: attribute(trigger, 'data-calendar-original-occurrence-start-date', occurrenceStart),
            revision: attribute(trigger, 'data-calendar-occurrence-revision', ''),
            occurrence: {
                title: attribute(trigger, 'data-event-title', ''),
                note: attribute(trigger, 'data-event-note', ''),
                start: occurrenceStart,
                end: occurrenceEnd,
                color: validColor(attribute(trigger, 'data-calendar-event-color', 'blue')),
                allDay: attribute(trigger, 'data-calendar-event-all-day', '1') !== '0',
                startTime: attribute(trigger, 'data-calendar-event-start-time', ''),
                endTime: attribute(trigger, 'data-calendar-event-end-time', ''),
                url: attribute(trigger, 'data-calendar-event-url', '')
            },
            series: {
                title: attribute(trigger, 'data-calendar-source-title', trigger.getAttribute('data-event-title')),
                note: attribute(trigger, 'data-calendar-source-note', trigger.getAttribute('data-event-note')),
                start: attribute(trigger, 'data-event-start-date', occurrenceStart),
                end: attribute(trigger, 'data-event-end-date', occurrenceEnd),
                color: validColor(attribute(trigger, 'data-calendar-source-color', trigger.getAttribute('data-calendar-event-color'))),
                allDay: attribute(trigger, 'data-calendar-source-all-day', trigger.getAttribute('data-calendar-event-all-day')) !== '0',
                startTime: attribute(trigger, 'data-calendar-source-start-time', trigger.getAttribute('data-calendar-event-start-time')),
                endTime: attribute(trigger, 'data-calendar-source-end-time', trigger.getAttribute('data-calendar-event-end-time')),
                url: attribute(trigger, 'data-calendar-source-url', trigger.getAttribute('data-calendar-event-url')),
                repeat: repeat,
                repeatUntil: attribute(trigger, 'data-calendar-event-repeat-until', '')
            }
        };
    }

    function setValue(form, selector, nextValue) {
        var field = form.querySelector(selector);
        if (field) {
            field.value = value(nextValue);
        }
    }

    function selectedScope(form) {
        var selected = form.querySelector('.calendarOccurrenceScope:checked');
        return selected && selected.value === 'series' ? 'series' : 'occurrence';
    }

    function applyValues(form, values) {
        setValue(form, '.changeCalendarEventTitleValue', values.title);
        setValue(form, '.changeCalendarEventStartDate', values.start);
        setValue(form, '.changeCalendarEventEndDate', values.end);
        setValue(form, '.changeCalendarEventNote', values.note);
        setValue(form, '.changeCalendarEventColor', values.color);
        setValue(form, '.changeCalendarEventStartTime', values.startTime);
        setValue(form, '.changeCalendarEventEndTime', values.endTime);
        setValue(form, '.changeCalendarEventUrl', values.url);
        var allDay = form.querySelector('.changeCalendarEventAllDay');
        if (allDay) {
            allDay.checked = values.allDay === true;
            $(allDay).trigger('change');
        }
    }

    function syncScope(form) {
        var fieldset = form.querySelector('.calendar-occurrence-scope');
        var submit = form.querySelector('.calendar-event-submit');
        var remove = form.querySelector('.delete_calendar_event');
        var restore = form.querySelector('.restore_calendar_occurrence');
        var recurrence = form.querySelector('.calendar-event-recurrence-fields');
        if (!state || !state.recurring) {
            if (fieldset) {
                fieldset.hidden = true;
            }
            if (restore) {
                restore.hidden = true;
            }
            if (remove) {
                remove.hidden = false;
                remove.textContent = '削除する';
            }
            if (submit) {
                submit.textContent = '変更する';
            }
            if (recurrence) {
                recurrence.hidden = false;
            }
            form.removeAttribute('data-calendar-occurrence-active');
            return;
        }

        var scope = selectedScope(form);
        var occurrenceOnly = scope === 'occurrence';
        form.setAttribute('data-calendar-occurrence-active', occurrenceOnly ? '1' : '0');
        if (fieldset) {
            fieldset.hidden = false;
        }
        if (recurrence) {
            recurrence.hidden = occurrenceOnly;
        }
        applyValues(form, occurrenceOnly ? state.occurrence : state.series);
        if (!occurrenceOnly) {
            setValue(form, '.changeCalendarEventRepeatType', state.series.repeat);
            setValue(form, '.changeCalendarEventRepeatUntil', state.series.repeatUntil);
            $('.changeCalendarEventRepeatType').trigger('change');
        }
        if (submit) {
            submit.textContent = occurrenceOnly ? (state.cancelled ? 'この予定を変更して復活' : 'この予定を変更') : 'シリーズを変更';
        }
        if (remove) {
            remove.hidden = occurrenceOnly && state.cancelled;
            remove.textContent = occurrenceOnly ? 'この予定を取り消す' : 'シリーズを削除';
        }
        if (restore) {
            restore.hidden = !(occurrenceOnly && (state.exceptionKind === 'override' || state.cancelled));
        }
    }

    function prepareForm(trigger) {
        var form = document.getElementById(formId);
        if (!form) {
            return;
        }
        state = triggerState(trigger);
        setValue(form, '.changeCalendarOccurrenceOriginalStartDate', state.originalStart);
        setValue(form, '.changeCalendarOccurrenceRevision', state.revision);
        var occurrenceRadio = form.querySelector('.calendarOccurrenceScope[value="occurrence"]');
        if (occurrenceRadio) {
            occurrenceRadio.checked = true;
        }
        var context = form.querySelector('.calendar-occurrence-context');
        if (context) {
            var moved = state.occurrence.start !== state.originalStart;
            context.textContent = moved
                ? '対象: ' + state.occurrence.start + '（元の予定日: ' + state.originalStart + '）'
                : '対象: ' + state.originalStart;
        }
        window.setTimeout(function () {
            syncScope(form);
        }, 0);
    }

    function formPayload(form) {
        var allDay = form.querySelector('.changeCalendarEventAllDay');
        var allDayValue = !allDay || allDay.checked;
        function field(selector) {
            var element = form.querySelector(selector);
            return element ? value(element.value) : '';
        }
        return {
            event_id: state ? state.eventId : '',
            original_occurrence_start_date: state ? state.originalStart : '',
            occurrence_revision: state ? state.revision : '',
            calendar_event_title: field('.changeCalendarEventTitleValue'),
            calendar_event_start_date: field('.changeCalendarEventStartDate'),
            calendar_event_end_date: field('.changeCalendarEventEndDate'),
            calendar_event_note: field('.changeCalendarEventNote'),
            calendar_event_color: validColor(field('.changeCalendarEventColor')),
            calendar_event_all_day: allDayValue ? '1' : '0',
            calendar_event_start_time: allDayValue ? '' : field('.changeCalendarEventStartTime'),
            calendar_event_end_time: allDayValue ? '' : field('.changeCalendarEventEndTime'),
            calendar_event_url: field('.changeCalendarEventUrl')
        };
    }

    function setPending(form, pending) {
        form.querySelectorAll('.calendar-event-submit, .delete_calendar_event, .restore_calendar_occurrence').forEach(function (button) {
            button.disabled = pending;
        });
        form.setAttribute('aria-busy', pending ? 'true' : 'false');
    }

    function closeAndRefresh(form, message) {
        var modal = form.closest('.modal');
        showNotice(message, 'success');
        if (modal && window.bootstrap && window.bootstrap.Modal) {
            var instance = window.bootstrap.Modal.getInstance(modal);
            if (instance) {
                instance.hide();
            }
        }
        $(document).trigger('calendar:occurrenceChanged');
    }

    function perform(form, action, payload, successMessage) {
        if (form.getAttribute('aria-busy') === 'true') {
            return;
        }
        setPending(form, true);
        request(action, payload)
            .done(function (response) {
                if (response && response.ok === true) {
                    closeAndRefresh(form, successMessage);
                    return;
                }
                showNotice(response && response.error && response.error.message ? response.error.message : '予定を更新出来ませんでした', 'danger');
            })
            .fail(function (xhr, status) {
                showNotice(errorMessage(xhr, status), 'danger');
                if (xhr && xhr.status === 409) {
                    $(document).trigger('calendar:occurrenceChanged');
                }
            })
            .always(function () {
                setPending(form, false);
            });
    }

    function captureSubmit(event) {
        var form = event.target;
        if (!form || form.id !== formId || !state || !state.recurring || selectedScope(form) !== 'occurrence') {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
            return;
        }
        perform(form, 'calendar.occurrence.update', formPayload(form), state.cancelled ? 'この予定を変更して復活しました' : 'この予定のみ変更しました');
    }

    function captureClick(event) {
        var target = event.target && typeof event.target.closest === 'function' ? event.target : null;
        if (!target) {
            return;
        }
        var trigger = target.closest('.calendar-event-edit-trigger');
        if (trigger) {
            prepareForm(trigger);
            return;
        }
        var form = target.closest('#' + formId);
        if (!form || !state || !state.recurring) {
            return;
        }
        if (target.closest('.restore_calendar_occurrence')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (window.confirm('この回の個別変更または取消を元に戻しますか？')) {
                perform(form, 'calendar.occurrence.restore', {
                    event_id: state.eventId,
                    original_occurrence_start_date: state.originalStart,
                    occurrence_revision: state.revision
                }, 'この予定をシリーズの内容に戻しました');
            }
            return;
        }
        if (target.closest('.delete_calendar_event') && selectedScope(form) === 'occurrence') {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (!state.cancelled && window.confirm('この予定のみ取り消しますか？')) {
                perform(form, 'calendar.occurrence.cancel', {
                    event_id: state.eventId,
                    original_occurrence_start_date: state.originalStart,
                    occurrence_revision: state.revision
                }, 'この予定のみ取り消しました');
            }
        }
    }

    document.addEventListener('submit', captureSubmit, true);
    document.addEventListener('click', captureClick, true);
    $(document).on('change.iguguruCalendarOccurrence', '.calendarOccurrenceScope', function () {
        var form = document.getElementById(formId);
        if (form) {
            syncScope(form);
        }
    });
}(window.jQuery, window, document));
