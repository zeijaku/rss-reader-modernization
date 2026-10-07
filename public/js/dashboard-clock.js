(function (window, document) {
    'use strict';

    const dashboardCore = window.IGuguruDashboardCore;
    if (!dashboardCore) {
        throw new Error('Dashboard core is not available.');
    }

    const apiRequestPromise = dashboardCore.apiRequestPromise;
    const apiResponseOk = dashboardCore.apiResponseOk;
    const requestStartElement = dashboardCore.requestStartElement;
    const requestEndElement = dashboardCore.requestEndElement;
    const requestFailReason = dashboardCore.requestFailReason;
    const showNotice = dashboardCore.showNotice;
    let eventsBound = false;

    function first(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    function value(selector) {
        const element = first(selector);
        return element ? element.value : undefined;
    }

    function setValue(selector, nextValue) {
        const element = first(selector);
        if (element) {
            element.value = String(nextValue);
        }
    }

    function setChecked(selector, checked) {
        const element = first(selector);
        if (element) {
            element.checked = Boolean(checked);
        }
    }

    function attribute(element, name, fallback) {
        if (!element || typeof element.getAttribute !== 'function') {
            return fallback;
        }
        const result = element.getAttribute(name);
        return result === null ? fallback : result;
    }

    function closestMatch(target, selector) {
        return target && typeof target.closest === 'function' ? target.closest(selector) : null;
    }

    function submitButton(form) {
        return form ? form.querySelector('button[type="submit"]') : null;
    }

    function clockFormPayload(prefix) {
        const showSeconds = first('.' + prefix + 'ClockShowSeconds');
        const showDate = first('.' + prefix + 'ClockShowDate');
        return {
            'clock_title': value('.' + prefix + 'ClockName'),
            'clock_hour_format': value('.' + prefix + 'ClockHourFormat'),
            'clock_show_seconds': showSeconds && showSeconds.checked ? '1' : '0',
            'clock_show_date': showDate && showDate.checked ? '1' : '0',
            'widget_style': value('.' + prefix + 'ClockStyle'),
            'widget_width': value('.' + prefix + 'ClockWidth'),
            'widget_height': value('.' + prefix + 'ClockHeight')
        };
    }

    function runMutation(button, action, payload, onSuccess) {
        if (!requestStartElement(button)) {
            return;
        }
        apiRequestPromise(action, payload, 3000)
            .then(function (data) {
                if (apiResponseOk(data)) {
                    onSuccess(data);
                }
            })
            .catch(requestFailReason)
            .finally(function () {
                requestEndElement(button);
            });
    }

    function addClock(form) {
        const payload = clockFormPayload('register');
        payload.widget_location = value('.registerClockLocation');
        runMutation(submitButton(form), 'widget.clock.create', payload, function () {
            window.location.reload();
        });
    }

    function editClock(trigger) {
        setValue('.changeClockId', attribute(trigger, 'data-widget-id', ''));
        setValue('.changeClockName', attribute(trigger, 'data-clock-title', 'Clock'));
        setValue('.changeClockHourFormat', attribute(trigger, 'data-clock-hour-format', '24'));
        setChecked('.changeClockShowSeconds', attribute(trigger, 'data-clock-show-seconds', '0') === '1');
        setChecked('.changeClockShowDate', attribute(trigger, 'data-clock-show-date', '1') === '1');
        setValue('.changeClockStyle', attribute(trigger, 'data-widget-style', 'primary'));
        setValue('.changeClockWidth', attribute(trigger, 'data-widget-width', '1'));
        setValue('.changeClockHeight', attribute(trigger, 'data-widget-height', '1'));
    }

    function changeClock(form) {
        const payload = clockFormPayload('change');
        payload.widget_id = value('.changeClockId');
        runMutation(submitButton(form), 'widget.clock.update', payload, function () {
            window.location.reload();
        });
    }

    function deleteClock(button) {
        const widgetId = String(value('.changeClockId') || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するClockを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このClockを削除しますか？Browserに保存されたTimer状態も削除します。')) {
            return;
        }
        runMutation(button, 'widget.clock.delete', {'widget_id': widgetId}, function () {
            if (window.RssClockTimer && typeof window.RssClockTimer.removeWidgetState === 'function') {
                window.RssClockTimer.removeWidgetState(widgetId);
            }
            window.location.reload();
        });
    }

    function handleSubmit(event) {
        const registerForm = closestMatch(event.target, '#registerClockForm');
        if (registerForm) {
            event.preventDefault();
            addClock(registerForm);
            return;
        }
        const changeForm = closestMatch(event.target, '#changeClockForm');
        if (changeForm) {
            event.preventDefault();
            changeClock(changeForm);
        }
    }

    function handleClick(event) {
        const editTrigger = closestMatch(event.target, '.clock-edit-trigger');
        if (editTrigger) {
            editClock(editTrigger);
            return;
        }
        const deleteTrigger = closestMatch(event.target, '.delete_clock');
        if (deleteTrigger) {
            deleteClock(deleteTrigger);
        }
    }

    function bindEvents() {
        if (eventsBound) {
            return;
        }
        eventsBound = true;
        document.addEventListener('submit', handleSubmit);
        document.addEventListener('click', handleClick);
    }

    window.IGuguruDashboardClock = {
        bindEvents: bindEvents
    };
})(window, document);
