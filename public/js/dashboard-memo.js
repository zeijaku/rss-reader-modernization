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

    function memoFormPayload(prefix) {
        return {
            'memo_title': value('.' + prefix + 'MemoTitleValue'),
            'memo_body': value('.' + prefix + 'MemoBody'),
            'widget_style': value('.' + prefix + 'MemoStyle'),
            'widget_width': value('.' + prefix + 'MemoWidth'),
            'widget_height': value('.' + prefix + 'MemoHeight')
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
            }, requestFailReason)
            .finally(function () {
                requestEndElement(button);
            });
    }

    function addMemo(form) {
        const payload = memoFormPayload('register');
        payload.widget_location = value('.registerMemoLocation');
        runMutation(submitButton(form), 'widget.memo.create', payload, function () {
            window.location.reload();
        });
    }

    function editMemo(trigger) {
        const card = closestMatch(trigger, '[data-dashboard-widget-type="memo"]');
        const title = card ? first('.memo-title', card) : null;
        const body = card ? first('.memo-body', card) : null;
        setValue('.changeMemoWidgetId', attribute(trigger, 'data-widget-id', ''));
        setValue('.changeMemoId', attribute(trigger, 'data-memo-id', ''));
        setValue('.changeMemoTitleValue', title && title.textContent ? title.textContent : 'Memo');
        setValue('.changeMemoBody', body ? body.textContent : '');
        setValue('.changeMemoStyle', attribute(trigger, 'data-widget-style', 'success'));
        setValue('.changeMemoWidth', attribute(trigger, 'data-widget-width', '1'));
        setValue('.changeMemoHeight', attribute(trigger, 'data-widget-height', '1'));
    }

    function changeMemo(form) {
        const payload = memoFormPayload('change');
        payload.widget_id = value('.changeMemoWidgetId');
        runMutation(submitButton(form), 'widget.memo.update', payload, function () {
            window.location.reload();
        });
    }

    function deleteMemo(button) {
        const widgetId = String(value('.changeMemoWidgetId') || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するMemoを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このMemoを削除しますか？')) {
            return;
        }
        runMutation(button, 'widget.memo.delete', {'widget_id': widgetId}, function () {
            window.location.reload();
        });
    }

    function handleSubmit(event) {
        const registerForm = closestMatch(event.target, '#registerMemoForm');
        if (registerForm) {
            event.preventDefault();
            addMemo(registerForm);
            return;
        }
        const changeForm = closestMatch(event.target, '#changeMemoForm');
        if (changeForm) {
            event.preventDefault();
            changeMemo(changeForm);
        }
    }

    function handleClick(event) {
        const editTrigger = closestMatch(event.target, '.memo-edit-trigger');
        if (editTrigger) {
            editMemo(editTrigger);
            return;
        }
        const deleteTrigger = closestMatch(event.target, '.delete_memo');
        if (deleteTrigger) {
            deleteMemo(deleteTrigger);
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

    window.IGuguruDashboardMemo = {
        bindEvents: bindEvents
    };
})(window, document);
