(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var changeFormId = 'changeCalendarEventForm';
    var registerFormId = 'registerCalendarEventForm';
    var copyButtonClass = 'copy_calendar_event';

    function value(input) {
        return input === null || input === undefined ? '' : String(input);
    }

    function fieldValue(form, selector) {
        var field = form ? form.querySelector(selector) : null;
        return field ? value(field.value) : '';
    }

    function setValue(form, selector, nextValue) {
        var field = form ? form.querySelector(selector) : null;
        if (field) {
            field.value = value(nextValue);
        }
    }

    function selectedOccurrenceOnly(form) {
        if (!form || form.getAttribute('data-calendar-occurrence-active') !== '1') {
            return false;
        }
        var selected = form.querySelector('.calendarOccurrenceScope:checked');
        return !selected || selected.value !== 'series';
    }

    function snapshotChangeForm(form) {
        if (!form) {
            return null;
        }
        var allDay = form.querySelector('.changeCalendarEventAllDay');
        var occurrenceOnly = selectedOccurrenceOnly(form);
        return {
            title: fieldValue(form, '.changeCalendarEventTitleValue'),
            start: fieldValue(form, '.changeCalendarEventStartDate'),
            end: fieldValue(form, '.changeCalendarEventEndDate'),
            note: fieldValue(form, '.changeCalendarEventNote'),
            color: fieldValue(form, '.changeCalendarEventColor') || 'blue',
            allDay: !allDay || allDay.checked === true,
            startTime: fieldValue(form, '.changeCalendarEventStartTime'),
            endTime: fieldValue(form, '.changeCalendarEventEndTime'),
            url: fieldValue(form, '.changeCalendarEventUrl'),
            repeat: occurrenceOnly ? 'none' : (fieldValue(form, '.changeCalendarEventRepeatType') || 'none'),
            repeatUntil: occurrenceOnly ? '' : fieldValue(form, '.changeCalendarEventRepeatUntil'),
            occurrenceOnly: occurrenceOnly
        };
    }

    function applySnapshot(registerForm, snapshot) {
        if (!registerForm || !snapshot) {
            return false;
        }
        setValue(registerForm, '.registerCalendarEventTitleValue', snapshot.title);
        setValue(registerForm, '.registerCalendarEventStartDate', snapshot.start);
        setValue(registerForm, '.registerCalendarEventEndDate', snapshot.end);
        setValue(registerForm, '.registerCalendarEventNote', snapshot.note);
        setValue(registerForm, '.registerCalendarEventColor', snapshot.color);
        setValue(registerForm, '.registerCalendarEventStartTime', snapshot.startTime);
        setValue(registerForm, '.registerCalendarEventEndTime', snapshot.endTime);
        setValue(registerForm, '.registerCalendarEventUrl', snapshot.url);
        setValue(registerForm, '.registerCalendarEventRepeatType', snapshot.repeat);
        setValue(registerForm, '.registerCalendarEventRepeatUntil', snapshot.repeatUntil);

        var allDay = registerForm.querySelector('.registerCalendarEventAllDay');
        if (allDay) {
            allDay.checked = snapshot.allDay === true;
            $(allDay).trigger('change');
        }
        $('.registerCalendarEventRepeatType').trigger('change');
        registerForm.setAttribute('data-calendar-copy-source', snapshot.occurrenceOnly ? 'occurrence' : 'event');
        // The normal add-trigger path marks recurrence data ready after resetAddFields().
        // Copy bypasses that trigger, but all recurrence values above came from the
        // already prepared edit form and will still be validated by the existing
        // client and server create path before persistence.
        registerForm.setAttribute('data-calendar-recurrence-submit-ready', '1');
        registerForm.setAttribute('aria-busy', 'false');
        var loading = registerForm.querySelector('.calendar-event-recurrence-loading');
        if (loading) {
            loading.hidden = true;
        }
        return true;
    }

    function showRegisterModal(changeForm, registerForm) {
        var changeModal = changeForm ? changeForm.closest('.modal') : null;
        var registerModal = registerForm ? registerForm.closest('.modal') : null;
        if (!registerModal || !window.bootstrap || !window.bootstrap.Modal) {
            return false;
        }
        var show = function () {
            window.bootstrap.Modal.getOrCreateInstance(registerModal).show();
            var title = registerForm.querySelector('.registerCalendarEventTitleValue');
            if (title && typeof title.focus === 'function') {
                window.setTimeout(function () { title.focus(); }, 0);
            }
        };
        if (changeModal) {
            var changeInstance = window.bootstrap.Modal.getInstance(changeModal);
            if (changeInstance) {
                $(changeModal).one('hidden.bs.modal', show);
                changeInstance.hide();
                return true;
            }
        }
        show();
        return true;
    }

    function copyToRegister() {
        var changeForm = document.getElementById(changeFormId);
        var registerForm = document.getElementById(registerFormId);
        var snapshot = snapshotChangeForm(changeForm);
        if (!snapshot || !registerForm || !applySnapshot(registerForm, snapshot)) {
            return false;
        }
        return showRegisterModal(changeForm, registerForm);
    }

    function ensureCopyButton() {
        var form = document.getElementById(changeFormId);
        if (!form || form.querySelector('.' + copyButtonClass)) {
            return;
        }
        var footer = form.querySelector('.modal-footer');
        if (!footer) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-primary ' + copyButtonClass;
        button.textContent = 'コピーして新規作成';
        button.setAttribute('aria-label', 'この予定の内容をコピーして新規予定を作成');
        var submit = footer.querySelector('.calendar-event-submit, button[type="submit"]');
        footer.insertBefore(button, submit || null);
    }

    document.addEventListener('click', function (event) {
        var target = event.target && typeof event.target.closest === 'function' ? event.target.closest('.' + copyButtonClass) : null;
        if (!target) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        copyToRegister();
    }, true);

    $(ensureCopyButton);

    window.IguguruCalendarCopy = {
        snapshotChangeForm: snapshotChangeForm,
        applySnapshot: applySnapshot,
        copyToRegister: copyToRegister
    };
}(window.jQuery, window, document));
