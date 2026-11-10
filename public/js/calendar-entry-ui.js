/* Calendar event entry UX, based on v1.50.0-dev.13.
 * Decorates existing Bootstrap forms: API payloads, field names, and recurring
 * occurrence semantics remain owned by the original Calendar modules.
 */
(function ($, window, document) {
    'use strict';
    if (!$) { return; }

    var namespace = '.iguguruCalendarEntryUi';
    var formSelector = '#registerCalendarEventForm, #changeCalendarEventForm';
    var modalSelector = '#registerCalendarEvent, #changeCalendarEvent';
    var DAY_MS = 86400000;

    function create(tag, className, text) {
        var node = document.createElement(tag);
        node.className = className || '';
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function prefix(form) {
        return form.id === 'changeCalendarEventForm' ? 'change' : 'register';
    }

    function field(form, suffix) {
        return form.querySelector('.' + prefix(form) + 'CalendarEvent' + suffix);
    }

    function validDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) { return false; }
        var parts = value.split('-').map(Number);
        var date = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
        return date.getUTCFullYear() === parts[0]
            && date.getUTCMonth() + 1 === parts[1]
            && date.getUTCDate() === parts[2];
    }

    function validTime(value) {
        return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value || '');
    }

    function pad(value) { return String(value).padStart(2, '0'); }

    /* UTC arithmetic here represents local wall-clock fields, not a real UTC timezone.
     * It avoids DST shifts in the display-only duration picker.
     */
    function minutesFrom(date, time) {
        if (!validDate(date) || !validTime(time)) { return null; }
        var d = date.split('-').map(Number);
        var t = time.split(':').map(Number);
        return Date.UTC(d[0], d[1] - 1, d[2], t[0], t[1]) / 60000;
    }

    function syncDuration(form) {
        var container = form.querySelector('.calendar-entry-ui-duration');
        var allDay = field(form, 'AllDay');
        if (!container) { return; }
        container.hidden = !!(allDay && allDay.checked);
        var startDate = field(form, 'StartDate');
        var startTime = field(form, 'StartTime');
        var endDate = field(form, 'EndDate');
        var endTime = field(form, 'EndTime');
        var first = minutesFrom(startDate && startDate.value, startTime && startTime.value);
        var last = minutesFrom(endDate && endDate.value, endTime && endTime.value);
        var selected = first !== null && last !== null && last >= first
            ? String([30, 60, 120].indexOf(last - first) >= 0 ? last - first : 'custom')
            : '';
        container.querySelectorAll('[data-calendar-duration]').forEach(function (button) {
            var active = button.getAttribute('data-calendar-duration') === selected;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function dispatchChange(element) {
        element.dispatchEvent(new window.Event('change', {bubbles: true}));
    }

    function setDuration(form, minutes) {
        var startDate = field(form, 'StartDate');
        var endDate = field(form, 'EndDate');
        var startTime = field(form, 'StartTime');
        var endTime = field(form, 'EndTime');
        if (!startDate || !endDate || !startTime || !endTime || !validDate(startDate.value)) {
            return;
        }
        if (!validTime(startTime.value)) {
            startTime.value = '09:00';
        }
        var begin = minutesFrom(startDate.value, startTime.value);
        if (begin === null) { return; }
        var finish = new Date((begin + minutes) * 60000);
        endDate.value = finish.getUTCFullYear() + '-' + pad(finish.getUTCMonth() + 1)
            + '-' + pad(finish.getUTCDate());
        endTime.value = pad(finish.getUTCHours()) + ':' + pad(finish.getUTCMinutes());
        dispatchChange(endDate);
        dispatchChange(endTime);
        syncDuration(form);
    }

    function syncSourceAccent(form) {
        var select = field(form, 'Source');
        var group = form.querySelector('.calendar-entry-ui-source-field');
        if (!select || !group) { return; }
        var option = select.options[select.selectedIndex];
        var color = option ? option.getAttribute('data-calendar-source-color') : '';
        if (!/^(blue|green|purple|yellow|red)$/.test(color || '')) { color = 'blue'; }
        group.setAttribute('data-source-color', color);
    }

    function addDurationPicker(form) {
        var timeRow = form.querySelector('.calendar-event-time-fields');
        if (!timeRow || timeRow.querySelector('.calendar-entry-ui-duration')) { return; }
        var container = create('div', 'col-12 calendar-entry-ui-duration');
        container.appendChild(create('div', 'calendar-entry-ui-duration-label', '所要時間'));
        var buttons = create('div', 'btn-group btn-group-sm calendar-entry-ui-duration-buttons');
        buttons.setAttribute('role', 'group');
        buttons.setAttribute('aria-label', '予定の所要時間');
        [['30', '30分'], ['60', '1時間'], ['120', '2時間'], ['custom', '指定']].forEach(function (item) {
            var button = create('button', 'btn btn-outline-primary', item[1]);
            button.type = 'button';
            button.setAttribute('data-calendar-duration', item[0]);
            button.setAttribute('aria-pressed', 'false');
            buttons.appendChild(button);
        });
        container.appendChild(buttons);
        timeRow.appendChild(container);
    }

    function enhance(form) {
        if (!form || form.classList.contains('calendar-entry-ui-ready')) { return; }
        var body = form.querySelector('.modal-body');
        var title = field(form, 'TitleValue');
        var source = form.querySelector('.calendar-event-source-field');
        var eventColor = form.querySelector('.calendar-event-color-field');
        var dateInput = field(form, 'StartDate');
        var dateRow = dateInput && dateInput.closest('.row');
        var schedule = form.querySelector('.calendar-event-schedule-fields');
        if (!body || !title || !source || !eventColor || !dateRow || !schedule
            || dateRow.parentNode !== body || schedule.parentNode !== body) { return; }

        title.classList.add('form-control-lg');
        var titleGroup = title.closest('.mb-3');
        if (titleGroup) { titleGroup.classList.add('calendar-entry-ui-title-field'); }

        /* Keep both source and event color editable; they mean different things. */
        var meta = create('div', 'calendar-entry-ui-meta-grid');
        body.insertBefore(meta, dateRow);
        source.classList.add('calendar-entry-ui-source-field');
        meta.appendChild(source);
        meta.appendChild(eventColor);

        var panel = create('section', 'calendar-entry-ui-datetime-panel');
        var header = create('div', 'calendar-entry-ui-section-title', '日時');
        panel.appendChild(header);
        body.insertBefore(panel, dateRow);
        dateRow.classList.add('calendar-entry-ui-dates');
        panel.appendChild(dateRow);
        panel.appendChild(schedule);

        addDurationPicker(form);
        var details = form.querySelector('.calendar-event-more-summary');
        if (details) { details.textContent = '場所・URL・メモ（詳細を開く）'; }

        form.classList.add('calendar-entry-ui-ready');
        syncSourceAccent(form);
        syncDuration(form);
    }

    function enhanceAll() {
        document.querySelectorAll(formSelector).forEach(enhance);
    }

    $(function () {
        enhanceAll();
        $(document)
            .off('shown.bs.modal' + namespace, modalSelector)
            .on('shown.bs.modal' + namespace, modalSelector, function () {
                var form = this.querySelector('form');
                enhance(form);
                if (form) {
                    syncSourceAccent(form);
                    syncDuration(form);
                }
            })
            .off('click' + namespace, '[data-calendar-duration]')
            .on('click' + namespace, '[data-calendar-duration]', function () {
                var form = this.closest(formSelector);
                if (!form) { return; }
                var value = this.getAttribute('data-calendar-duration');
                if (value === 'custom') {
                    var endTime = field(form, 'EndTime');
                    if (endTime) { endTime.focus(); }
                    return;
                }
                var minutes = Number(value);
                if ([30, 60, 120].indexOf(minutes) >= 0) { setDuration(form, minutes); }
            })
            .off('change' + namespace, formSelector + ' input, ' + formSelector + ' select')
            .on('change' + namespace, formSelector + ' input, ' + formSelector + ' select', function () {
                var form = this.closest(formSelector);
                if (!form) { return; }
                var allDay = field(form, 'AllDay');
                if (this === allDay && !allDay.checked) {
                    var startTime = field(form, 'StartTime');
                    var endTime = field(form, 'EndTime');
                    if (startTime && !validTime(startTime.value)) { startTime.value = '09:00'; }
                    if (endTime && !validTime(endTime.value)) { setDuration(form, 60); }
                }
                syncSourceAccent(form);
                syncDuration(form);
            });
    });
})(window.jQuery, window, document);
