(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruCalendarUsability';
    var DAY_MS = 86400000;
    var startSelector = '.registerCalendarEventStartDate, .changeCalendarEventStartDate, '
        + '.registerCalendarEventStartTime, .changeCalendarEventStartTime';
    var endSelector = '.registerCalendarEventEndDate, .changeCalendarEventEndDate, '
        + '.registerCalendarEventEndTime, .changeCalendarEventEndTime';

    function prefixFor(form) {
        return form && form.id === 'changeCalendarEventForm' ? 'change' : 'register';
    }

    function field(form, suffix) {
        var prefix = prefixFor(form);
        return form ? form.querySelector('.' + prefix + 'CalendarEvent' + suffix) : null;
    }

    function validDate(value) {
        return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
    }

    function validTime(value) {
        return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(String(value || ''));
    }

    function dateMs(value) {
        if (!validDate(value)) {
            return null;
        }
        var parts = value.split('-').map(Number);
        var result = Date.UTC(parts[0], parts[1] - 1, parts[2]);
        var date = new Date(result);
        return date.getUTCFullYear() === parts[0]
            && date.getUTCMonth() === parts[1] - 1
            && date.getUTCDate() === parts[2] ? result : null;
    }

    function dateTimeMs(dateValue, timeValue) {
        var base = dateMs(dateValue);
        if (base === null || !validTime(timeValue)) {
            return null;
        }
        var parts = timeValue.split(':').map(Number);
        return base + parts[0] * 3600000 + parts[1] * 60000;
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function dateFromMs(value) {
        var date = new Date(value);
        return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
    }

    function timeFromMs(value) {
        var date = new Date(value);
        return pad(date.getUTCHours()) + ':' + pad(date.getUTCMinutes());
    }

    function schedule(form) {
        var startDate = field(form, 'StartDate');
        var endDate = field(form, 'EndDate');
        var startTime = field(form, 'StartTime');
        var endTime = field(form, 'EndTime');
        var allDay = field(form, 'AllDay');
        return {
            startDate: startDate ? String(startDate.value || '') : '',
            endDate: endDate ? String(endDate.value || '') : '',
            startTime: startTime ? String(startTime.value || '') : '',
            endTime: endTime ? String(endTime.value || '') : '',
            allDay: !allDay || allDay.checked === true
        };
    }

    function syncConstraints(form) {
        var values = schedule(form);
        var endDate = field(form, 'EndDate');
        var endTime = field(form, 'EndTime');
        if (endDate) {
            if (validDate(values.startDate)) {
                endDate.min = values.startDate;
            } else {
                endDate.removeAttribute('min');
            }
        }
        if (endTime) {
            if (!values.allDay && values.startDate === values.endDate && validTime(values.startTime)) {
                endTime.min = values.startTime;
            } else {
                endTime.removeAttribute('min');
            }
        }
    }

    function shiftEnd(form, before) {
        var current = schedule(form);
        var endDate = field(form, 'EndDate');
        var endTime = field(form, 'EndTime');
        if (!validDate(current.startDate) || !endDate) {
            syncConstraints(form);
            return;
        }

        if (!before || !validDate(before.startDate) || !validDate(before.endDate)) {
            if (!validDate(current.endDate)) {
                endDate.value = current.startDate;
            }
            syncConstraints(form);
            return;
        }

        var oldStartDate = dateMs(before.startDate);
        var oldEndDate = dateMs(before.endDate);
        var newStartDate = dateMs(current.startDate);
        if (oldStartDate === null || oldEndDate === null || newStartDate === null || oldEndDate < oldStartDate) {
            syncConstraints(form);
            return;
        }

        var useExactTime = !current.allDay
            && validTime(before.startTime)
            && validTime(before.endTime)
            && validTime(current.startTime)
            && endTime;
        if (useExactTime) {
            var oldStart = dateTimeMs(before.startDate, before.startTime);
            var oldEnd = dateTimeMs(before.endDate, before.endTime);
            var newStart = dateTimeMs(current.startDate, current.startTime);
            if (oldStart !== null && oldEnd !== null && newStart !== null && oldEnd >= oldStart) {
                var newEnd = newStart + (oldEnd - oldStart);
                endDate.value = dateFromMs(newEnd);
                endTime.value = timeFromMs(newEnd);
                syncConstraints(form);
                return;
            }
        }

        endDate.value = dateFromMs(newStartDate + (oldEndDate - oldStartDate));
        syncConstraints(form);
    }

    function calendarForm(target) {
        return target && typeof target.closest === 'function'
            ? target.closest('#registerCalendarEventForm, #changeCalendarEventForm')
            : null;
    }

    function isStartField(target) {
        return target && typeof target.matches === 'function' && target.matches(startSelector);
    }

    function isEndField(target) {
        return target && typeof target.matches === 'function' && target.matches(endSelector);
    }

    function enhanceDetails(form) {
        if (!form || form.querySelector('.calendar-event-more')) {
            return;
        }
        var body = form.querySelector('.modal-body');
        var urlGroup = form.querySelector('.calendar-event-url-field');
        var note = form.querySelector('[class*="CalendarEventNote"]');
        var noteGroup = note && typeof note.closest === 'function' ? note.closest('.mb-3') : null;
        if (!body || !urlGroup || !noteGroup) {
            return;
        }

        var details = document.createElement('details');
        details.className = 'calendar-event-more';
        var summary = document.createElement('summary');
        summary.className = 'calendar-event-more-summary';
        summary.textContent = '詳細（URL・メモ）';
        var content = document.createElement('div');
        content.className = 'calendar-event-more-body';
        details.appendChild(summary);
        details.appendChild(content);
        content.appendChild(urlGroup);
        content.appendChild(noteGroup);
        if (note) {
            note.rows = 2;
        }
        body.appendChild(details);
        syncDetails(form, false);
    }

    function syncDetails(form, keepOpen) {
        var details = form ? form.querySelector('.calendar-event-more') : null;
        if (!details) {
            return;
        }
        var url = field(form, 'Url');
        var note = field(form, 'Note');
        var hasContent = (url && String(url.value || '').trim() !== '')
            || (note && String(note.value || '').trim() !== '');
        if (hasContent) {
            details.open = true;
        } else if (!keepOpen) {
            details.open = false;
        }
    }

    function enhanceAll() {
        ['registerCalendarEventForm', 'changeCalendarEventForm'].forEach(function (id) {
            var form = document.getElementById(id);
            if (form) {
                enhanceDetails(form);
                syncConstraints(form);
            }
        });
    }

    document.addEventListener('focusin', function (event) {
        if (!isStartField(event.target)) {
            return;
        }
        var form = calendarForm(event.target);
        if (form) {
            form.__calendarStartAnchor = schedule(form);
        }
    }, true);

    document.addEventListener('change', function (event) {
        var form = calendarForm(event.target);
        if (!form) {
            return;
        }
        if (isStartField(event.target)) {
            shiftEnd(form, form.__calendarStartAnchor || null);
            form.__calendarStartAnchor = schedule(form);
            return;
        }
        if (isEndField(event.target)
            || (event.target && typeof event.target.matches === 'function'
                && event.target.matches('.registerCalendarEventAllDay, .changeCalendarEventAllDay'))) {
            syncConstraints(form);
            return;
        }
        if (event.target && typeof event.target.matches === 'function'
            && event.target.matches('.calendarOccurrenceScope')) {
            window.setTimeout(function () {
                syncConstraints(form);
                syncDetails(form, true);
            }, 0);
        }
    }, true);

    $(document)
        .off('shown.bs.modal' + namespace, '#registerCalendarEvent, #changeCalendarEvent')
        .on('shown.bs.modal' + namespace, '#registerCalendarEvent, #changeCalendarEvent', function () {
            var form = this.querySelector('form');
            if (!form) {
                return;
            }
            enhanceDetails(form);
            syncConstraints(form);
            syncDetails(form, false);
        });

    $(enhanceAll);
}(jQuery, window, document));
